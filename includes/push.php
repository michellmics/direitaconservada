<?php
// Notificações do app (Web Push, migration 025). Sem biblioteca: só openssl + curl.
//
// Como funciona: o navegador de quem aceitou gera um "endpoint" (Google, Apple, Mozilla…), que fica em push_inscricoes.
// Para avisar, o servidor manda um push SEM conteúdo (assinado com a chave VAPID) para cada endpoint; o service worker
// (sw.js) acorda, pergunta a api/push qual é o aviso e mostra a notificação. Assim não é preciso criptografar o texto.
//
//   push_avisar('enquete', '🗳️ Enquete nova!', 'Pergunta…', 'direita#enquete')   → todos os inscritos
//   push_avisar('placar', …, lado: 'esquerda')                                    → só os inscritos do pote esquerda
//   push_avisar('comentario', …, usuarioId: 42)                                   → só os aparelhos da conta 42
//                                                   (comentário na publicação / citação: includes/avisos.php)
//   push_placar()   (cron) → a virada no placar avisa quem ficou para trás (no máximo 1 aviso a cada 6 h)
require_once __DIR__ . '/db.php';

const PUSH_TTL = 86400;            // o aviso espera até 1 dia pelo aparelho desligado
const PUSH_PLACAR_INTERVALO = 6;   // horas entre avisos de virada no placar

function push_b64(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function push_config(string $chave, ?string $valor = null): ?string
{
    if ($valor !== null) {
        db()->prepare('INSERT INTO push_config (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)')->execute([$chave, $valor]);
        return $valor;
    }
    $st = db()->prepare('SELECT valor FROM push_config WHERE chave = ?');
    $st->execute([$chave]);
    $v = $st->fetchColumn();
    return $v === false ? null : $v;
}

/** Chaves VAPID (P-256). Geradas uma vez só, no primeiro uso, e guardadas no banco. */
function push_chaves(): array
{
    static $chaves = null;
    if ($chaves) {
        return $chaves;
    }
    $pub = push_config('vapid_publica');
    $pem = push_config('vapid_privada');
    if (!$pub || !$pem) {
        $opcoes = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
        if (PHP_OS_FAMILY === 'Windows' && is_file($cnf = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf')) {
            $opcoes['config'] = $cnf; // PHP no Windows (desenvolvimento) não acha o openssl.cnf sozinho
        }
        $k = openssl_pkey_new($opcoes);
        if (!$k || !openssl_pkey_export($k, $pem, null, $opcoes)) {
            throw new RuntimeException('Não deu para gerar as chaves das notificações (openssl).');
        }
        $ec = openssl_pkey_get_details($k)['ec'];
        $pub = push_b64("\x04" . str_pad($ec['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ec['y'], 32, "\0", STR_PAD_LEFT));
        db()->prepare("INSERT IGNORE INTO push_config (chave, valor) VALUES ('vapid_publica', ?), ('vapid_privada', ?)")->execute([$pub, $pem]);
        $pub = push_config('vapid_publica'); // se outro processo gerou junto, vale o que ficou no banco
        $pem = push_config('vapid_privada');
    }
    return $chaves = ['publica' => $pub, 'privada' => $pem];
}

/** Assinatura ECDSA do openssl (DER) → formato do JWT (r e s com 32 bytes cada). */
function push_der_para_jose(string $der): string
{
    $pos = 2; // 0x30, tamanho (sempre < 128 no P-256)
    $out = '';
    for ($i = 0; $i < 2; $i++) {
        $tam = ord($der[$pos + 1]); // 0x02, tamanho, número
        $out .= str_pad(ltrim(substr($der, $pos + 2, $tam), "\0"), 32, "\0", STR_PAD_LEFT);
        $pos += 2 + $tam;
    }
    return $out;
}

/** Cabeçalho Authorization (VAPID) para o serviço de push do endpoint. Um por serviço, válido por 12 h. */
function push_autorizacao(string $endpoint): string
{
    static $cache = [];
    $p = parse_url($endpoint);
    $aud = $p['scheme'] . '://' . $p['host'];
    if (!isset($cache[$aud])) {
        $chaves = push_chaves();
        $contato = (string) env('ENV_SMTP_USER', '');
        if (!str_contains($contato, '@')) {
            $contato = 'contato@' . preg_replace('/^www\./', '', (string) parse_url((string) env('APP_URL', ''), PHP_URL_HOST) ?: 'potepolitico.com.br');
        }
        $dados = push_b64(json_encode(['typ' => 'JWT', 'alg' => 'ES256'])) . '.'
               . push_b64(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => 'mailto:' . $contato]));
        openssl_sign($dados, $der, openssl_pkey_get_private($chaves['privada']), OPENSSL_ALGO_SHA256);
        $cache[$aud] = 'vapid t=' . $dados . '.' . push_b64(push_der_para_jose($der)) . ', k=' . $chaves['publica'];
    }
    return $cache[$aud];
}

/** Guarda (ou atualiza) a inscrição de um aparelho. */
function push_inscrever(string $endpoint, ?string $lado, ?int $usuarioId): void
{
    db()->prepare('INSERT INTO push_inscricoes (endpoint, hash, lado, usuario_id) VALUES (?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE lado = VALUES(lado), usuario_id = COALESCE(VALUES(usuario_id), usuario_id), visto_em = NOW()')
        ->execute([$endpoint, hash('sha256', $endpoint), $lado, $usuarioId]);
}

function push_desinscrever(string $endpoint): void
{
    db()->prepare('DELETE FROM push_inscricoes WHERE hash = ?')->execute([hash('sha256', $endpoint)]);
}

/**
 * Cria o aviso e manda para os inscritos: todos, só os do pote $lado ou só os aparelhos da conta $usuarioId
 * (aviso pessoal: sem aparelho inscrito, nada é criado). Envia em lotes paralelos (curl_multi);
 * endpoint que não existe mais (404/410) sai da lista. Retorna ['id', 'enviados', 'falhas'].
 */
function push_avisar(string $tipo, string $titulo, string $texto, string $url, ?string $lado = null, ?int $usuarioId = null): array
{
    $pdo = db();
    [$onde, $params] = $usuarioId ? [' WHERE usuario_id = ?', [$usuarioId]] : ($lado ? [' WHERE lado = ?', [$lado]] : ['', []]);
    $st = $pdo->prepare('SELECT id, endpoint FROM push_inscricoes' . $onde);
    $st->execute($params);
    $alvos = $st->fetchAll();
    if (!$alvos && $usuarioId) {
        return ['id' => 0, 'enviados' => 0, 'falhas' => 0];
    }
    $pdo->prepare('INSERT INTO push_avisos (tipo, lado, titulo, texto, url) VALUES (?, ?, ?, ?, ?)')
        ->execute([$tipo, $lado, mb_substr($titulo, 0, 120), mb_substr($texto, 0, 300), mb_substr($url, 0, 200)]);
    $avisoId = (int) $pdo->lastInsertId();
    $enviados = $falhas = 0;
    $mortos = [];
    foreach (array_chunk($alvos, 50) as $lote) {
        $pdo->prepare('UPDATE push_inscricoes SET aviso_id = ? WHERE id IN (' . implode(',', array_map('intval', array_column($lote, 'id'))) . ')')
            ->execute([$avisoId]); // antes de enviar: o aparelho pode perguntar "qual aviso?" na hora
        $multi = curl_multi_init();
        $hs = [];
        foreach ($lote as $a) {
            $h = curl_init($a['endpoint']);
            curl_setopt_array($h, [
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => '', CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
                CURLOPT_HTTPHEADER => ['Authorization: ' . push_autorizacao($a['endpoint']), 'TTL: ' . PUSH_TTL, 'Urgency: normal', 'Content-Length: 0'],
            ]);
            curl_multi_add_handle($multi, $h);
            $hs[(int) $a['id']] = $h;
        }
        do {
            $ativos = 0;
            curl_multi_exec($multi, $ativos);
            if ($ativos) {
                curl_multi_select($multi, 1);
            }
        } while ($ativos);
        foreach ($hs as $id => $h) {
            $status = (int) curl_getinfo($h, CURLINFO_HTTP_CODE);
            if ($status >= 200 && $status < 300) {
                $enviados++;
            } else {
                $falhas++;
                if (in_array($status, [404, 410], true)) {
                    $mortos[] = $id; // a pessoa desinstalou / bloqueou: não existe mais
                } elseif ($falhas <= 3) {
                    logar('aviso', 'push', 'push_falha', "Push recusado ($status)", ['servico' => parse_url(curl_getinfo($h, CURLINFO_EFFECTIVE_URL), PHP_URL_HOST), 'resposta' => mb_substr((string) curl_multi_getcontent($h), 0, 300)]);
                }
            }
            curl_multi_remove_handle($multi, $h);
            curl_close($h);
        }
        curl_multi_close($multi);
    }
    if ($mortos) {
        $pdo->exec('DELETE FROM push_inscricoes WHERE id IN (' . implode(',', $mortos) . ')');
    }
    $pdo->prepare('UPDATE push_avisos SET enviados = ?, falhas = ? WHERE id = ?')->execute([$enviados, $falhas, $avisoId]);
    logar($usuarioId ? 'debug' : 'info', 'push', 'push_enviado', "Aviso \"$titulo\": $enviados enviados, $falhas falhas", ['aviso' => $avisoId, 'tipo' => $tipo, 'lado' => $lado]);
    return ['id' => $avisoId, 'enviados' => $enviados, 'falhas' => $falhas];
}

/** Números para o painel: inscritos (total e por pote) e os últimos avisos. */
function push_resumo(): array
{
    return [
        'inscritos' => (int) db()->query('SELECT COUNT(*) FROM push_inscricoes')->fetchColumn(),
        'porLado'   => db()->query("SELECT COALESCE(lado, '?'), COUNT(*) FROM push_inscricoes GROUP BY lado")->fetchAll(PDO::FETCH_KEY_PAIR),
        'avisos'    => db()->query("SELECT * FROM push_avisos WHERE tipo IN ('enquete', 'placar') ORDER BY id DESC LIMIT 5")->fetchAll(),
    ];
}

/**
 * Cron: virada no placar (quem tem mais itens no pote). Quando o líder muda, avisa os inscritos do pote que ficou
 * para trás, chamando para ajudar. No máximo 1 aviso a cada PUSH_PLACAR_INTERVALO horas (vira-vira não vira spam).
 */
function push_placar(): string
{
    require_once __DIR__ . '/pote_js.php';
    $lados = array_keys(SIDES);
    $totais = [];
    foreach ($lados as $l) {
        $totais[$l] = pote_totais($l)['total'];
    }
    arsort($totais);
    [$lider, $segundo] = array_keys($totais);
    if ($totais[$lider] === $totais[$segundo]) {
        return 'empate';
    }
    $antes = push_config('placar_lider');
    push_config('placar_lider', $lider);
    if ($antes === null || $antes === $lider) {
        return 'lider: ' . $lider;
    }
    $ultimo = (int) push_config('placar_avisado_em');
    if ($ultimo > time() - PUSH_PLACAR_INTERVALO * 3600) {
        return 'virou, mas avisou há pouco';
    }
    push_config('placar_avisado_em', (string) time());
    $L = SIDES[$lider];
    $placar = number_format($totais[$lider], 0, ',', '.') . ' × ' . number_format($totais[$segundo], 0, ',', '.');
    $r = push_avisar('placar', '🔥 ' . $L['name'] . ' passou vocês!', "Placar: $placar. Venha ajudar o seu pote a virar de novo!", $segundo, $segundo);
    return "virada! avisados {$r['enviados']}";
}
