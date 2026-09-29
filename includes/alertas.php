<?php
// Alertas por e-mail para o administrador (migration 020). Destinatários: ENV_EMAIL_ALERTAS no .env, separados por vírgula.
//   qr      → abriu a tela de pagamento com o QR Code (ao gerar o pedido e em cada "Ver Pix")
//   copiou  → copiou o código Pix copia e cola
//   pagou   → clicou em "Já paguei"
//   atraso  → pedido aguardando confirmação há mais de ALERTA_ATRASO_MIN minutos (cron/alertas, 1 e-mail por pedido)
// Anti-enxurrada: o mesmo evento do mesmo pedido gera no máximo 1 e-mail a cada ALERTA_INTERVALO_MIN minutos
// (as vezes seguintes são contadas e aparecem no próximo e-mail).
// O envio roda depois que a resposta já foi para o navegador (quem compra não espera o SMTP).
// Pela cron (cron/alertas): expirado, painel, erros, moderação, presentes parados, resumo diário e banco fora do ar.
require_once __DIR__ . '/db.php';

const ALERTA_INTERVALO_MIN = 5;
const ALERTA_ATRASO_MIN    = 15;

// evento => [status no e-mail, cor, status do pedido exigido, 1 e-mail só por pedido]
const ALERTA_EVENTOS = [
    'qr'      => ['Abriu o QR Code para compra', '#2f6fb5', 'pendente', false],
    'copiou'  => ['Usuário copiou o código Pix copia e cola', '#b5832f', 'pendente', false],
    'pagou'   => ['Usuário clicou em "Já paguei": conferir o Pix', '#2f8a4a', 'pendente', false],
    'atraso'  => ['Aguardando confirmação de pagamento', '#b53a2f', 'pendente', true],
    'expirou' => ['Pedido expirou sem pagamento confirmado', '#6b6a55', 'expirado', true],
];

/** Endereços de ENV_EMAIL_ALERTAS (vazio = alertas desligados). */
function alerta_destinos(): array
{
    $lista = array_map('trim', explode(',', (string) env('ENV_EMAIL_ALERTAS', '')));
    return array_values(array_filter($lista, fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
}

/** Registra o evento e manda o alerta depois da resposta. $codigo = código do pedido. */
function alerta_pedido_agendar(string $codigo, string $evento, ?int $usuarioId = null): void
{
    if (!isset(ALERTA_EVENTOS[$evento]) || !alerta_destinos()) {
        return;
    }
    $ua = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    register_shutdown_function(function () use ($codigo, $evento, $usuarioId, $ua, $ip) {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request(); // PHP-FPM: entrega a resposta e segue enviando
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request(); // LiteSpeed (comum em cPanel)
        }
        ignore_user_abort(true);
        try {
            alerta_pedido($codigo, $evento, ['ua' => $ua, 'ip' => $ip, 'usuario' => $usuarioId]);
        } catch (Throwable $e) {
            logar('erro', 'email', 'alerta_falha', 'Alerta de pedido: ' . $e->getMessage(), ['pedido' => $codigo, 'evento' => $evento]);
        }
    });
}

/**
 * Conta o evento e, se não mandou um igual há pouco, envia o alerta. Retorna true se o e-mail saiu.
 * $ctx: ua, ip (de quem disparou), usuario (só aceita se o pedido for dele), minutos (atraso).
 */
function alerta_pedido(string $codigo, string $evento, array $ctx = []): bool
{
    $destinos = alerta_destinos();
    if (!$destinos || !isset(ALERTA_EVENTOS[$evento]) || !is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
        return false;
    }
    $pdo = db();
    $st = $pdo->prepare("SELECT p.*, u.nome AS usuario_nome, u.email, u.criado_em AS conta_criada_em, u.email_verificado_em,
                                TIMESTAMPDIFF(MINUTE, p.criado_em, NOW()) AS minutos, NOW() AS agora
                         FROM pedidos p JOIN usuarios u ON u.id = p.usuario_id WHERE p.codigo = ?");
    $st->execute([$codigo]);
    $p = $st->fetch();
    if (!$p || $p['status'] !== ALERTA_EVENTOS[$evento][2] || (isset($ctx['usuario']) && (int) $ctx['usuario'] !== (int) $p['usuario_id'])) {
        return false;
    }

    // conta o evento; o e-mail só sai se não saiu um igual há pouco (atraso e expirou: uma vez só)
    $pdo->prepare('INSERT INTO pedido_alertas (pedido_id, evento, vezes) VALUES (?, ?, 1)
                   ON DUPLICATE KEY UPDATE vezes = vezes + 1, ultimo_em = NOW()')->execute([$p['id'], $evento]);
    $st = $pdo->prepare('UPDATE pedido_alertas SET enviado_em = NOW() WHERE pedido_id = ? AND evento = ? AND (enviado_em IS NULL'
        . (ALERTA_EVENTOS[$evento][3] ? ')' : ' OR enviado_em < NOW() - INTERVAL ' . ALERTA_INTERVALO_MIN . ' MINUTE)'));
    $st->execute([$p['id'], $evento]);
    if ($st->rowCount() !== 1) {
        return false;
    }

    require_once __DIR__ . '/mailer.php'; // antes de montar o e-mail: ele usa email_botao() e email_moldura()
    [$assunto, $html, $texto] = alerta_pedido_email($p, $evento, $ctx);
    $ok = false;
    foreach ($destinos as $para) {
        $ok = enviar_email($para, 'Administrador', $assunto, $html, $texto) === null || $ok;
    }
    if (!$ok) { // o SMTP falhou: a próxima vez (ou a próxima cron) tenta de novo
        $pdo->prepare('UPDATE pedido_alertas SET enviado_em = NULL WHERE pedido_id = ? AND evento = ?')->execute([$p['id'], $evento]);
    }
    logar('info', 'pedido', 'alerta_' . $evento, "Alerta \"{$evento}\" do pedido {$p['codigo']}", ['pedido' => $p['codigo'], 'enviado' => $ok]);
    return $ok;
}

function alerta_reais(int $centavos): string
{
    return 'R$ ' . number_format($centavos / 100, 2, ',', '.');
}

function alerta_data(?string $dt): string
{
    return $dt ? date('d/m/Y H:i:s', strtotime($dt)) : '—';
}

/** Monta o e-mail do alerta: [assunto, html, texto]. */
function alerta_pedido_email(array $p, string $evento, array $ctx): array
{
    $pdo = db();
    $S = side($p['lado']);
    [$status, $cor] = ALERTA_EVENTOS[$evento];
    if ($evento === 'expirou') {
        $status .= ' (' . PEDIDO_EXPIRA_HORAS . 'h)';
    }
    $minutos = (int) $p['minutos'];
    if ($evento === 'atraso') {
        $status .= " há {$minutos} min";
    }

    // o que está comprando (com os números no pote)
    $st = $pdo->prepare("SELECT pi.*, t.nome AS tipo_nome,
                                (SELECT GROUP_CONCAT(i.numero ORDER BY i.numero SEPARATOR ', ') FROM itens i WHERE i.pedido_item_id = pi.id) AS numeros,
                                (SELECT r.numero FROM itens r WHERE r.id = pi.renova_item_id) AS renova_numero
                         FROM pedido_itens pi JOIN item_tipos t ON t.id = pi.item_tipo_id WHERE pi.pedido_id = ? ORDER BY pi.id");
    $st->execute([$p['id']]);
    $linhas = $st->fetchAll();

    // histórico da pessoa
    $st = $pdo->prepare("SELECT SUM(status = 'pago') AS pagos, COALESCE(SUM(CASE WHEN status = 'pago' THEN total_centavos END), 0) AS gasto,
                                SUM(status = 'pendente') AS pendentes, SUM(status IN ('expirado', 'cancelado')) AS perdidos
                         FROM pedidos WHERE usuario_id = ? AND id <> ?");
    $st->execute([$p['usuario_id'], $p['id']]);
    $h = $st->fetch();

    // o que já aconteceu neste pedido
    $st = $pdo->prepare('SELECT evento, vezes, primeiro_em, ultimo_em FROM pedido_alertas WHERE pedido_id = ?');
    $st->execute([$p['id']]);
    $eventos = [];
    foreach ($st->fetchAll() as $ev) {
        $eventos[$ev['evento']] = $ev;
    }

    $contaNova = abs(strtotime($p['criado_em']) - strtotime($p['conta_criada_em'])) < 180;
    $unidades = array_sum(array_column($linhas, 'quantidade'));
    $info = [
        'Status'           => $status,
        'Pedido'           => $p['codigo'] . ' · pote ' . $S['name'],
        'Valor total'      => alerta_reais((int) $p['total_centavos']) . " ({$unidades} " . ($unidades === 1 ? 'unidade' : 'unidades') . ')',
        'Cliente'          => nome_proprio($p['usuario_nome']),
        'E-mail'           => $p['email'],
        'Titular do Pix'   => $p['pagador_nome'] . ' (nome que deve aparecer no extrato)',
        'Conta'            => $contaNova ? 'Criada agora, nesta compra' : 'Desde ' . alerta_data($p['conta_criada_em']) . ($p['email_verificado_em'] ? ' · e-mail confirmado' : ' · e-mail ainda não confirmado'),
        'Pedido criado em' => alerta_data($p['criado_em']) . " (há {$minutos} min)",
        'Horário do alerta' => alerta_data($p['agora']),
        'Pix vence em'     => alerta_data($p['expira_em']),
        'Abriu o QR Code'  => isset($eventos['qr']) ? $eventos['qr']['vezes'] . 'x (última: ' . alerta_data($eventos['qr']['ultimo_em']) . ')' : 'não',
        'Copiou o código'  => isset($eventos['copiou']) ? $eventos['copiou']['vezes'] . 'x (última: ' . alerta_data($eventos['copiou']['ultimo_em']) . ')' : 'não',
        'Clicou em Já paguei' => isset($eventos['pagou']) ? $eventos['pagou']['vezes'] . 'x (última: ' . alerta_data($eventos['pagou']['ultimo_em']) . ')' : 'não',
        'Histórico'        => (int) $h['pagos'] . ' pedido(s) pago(s) antes, total ' . alerta_reais((int) $h['gasto'])
                              . ' · ' . (int) $h['pendentes'] . ' outro(s) pendente(s) · ' . (int) $h['perdidos'] . ' expirado(s)/negado(s)',
        'IP'               => $p['ip'] . (!empty($ctx['ip']) && $ctx['ip'] !== $p['ip'] ? ' (agora: ' . $ctx['ip'] . ')' : ''),
    ];
    if (!empty($ctx['ua'])) {
        $info['Aparelho'] = $ctx['ua'];
    }

    $tr = fn($k, $v) => '<tr><td style="padding:6px 10px 6px 0;color:#6b6a55;font-size:13px;vertical-align:top;white-space:nowrap;">' . e($k)
        . '</td><td style="padding:6px 0;font-size:14px;">' . e($v) . '</td></tr>';
    $tabela = '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">'
        . implode('', array_map($tr, array_keys($info), $info)) . '</table>';

    $itensHtml = '';
    $itensTexto = '';
    foreach ($linhas as $l) {
        $sub = alerta_reais((int) $l['preco_unit_centavos'] * (int) $l['quantidade']);
        $titulo = $l['quantidade'] . 'x ' . $l['tipo_nome'] . ' · ' . alerta_reais((int) $l['preco_unit_centavos']) . ' = ' . $sub;
        $det = [];
        if ($l['renova_item_id']) {
            $det[] = 'Renovação do #' . str_pad((string) $l['renova_numero'], 4, '0', STR_PAD_LEFT);
        } elseif ($l['numeros']) {
            $det[] = 'Número(s) no pote: ' . $l['numeros'];
        }
        $det[] = 'Nome: ' . $l['nome_certificado'] . ' · ' . $l['cidade'] . '/' . $l['uf'];
        $det[] = 'Frase: “' . $l['frase'] . '”';
        if (!empty($l['presente'])) {
            $det[] = '🎁 É presente';
        }
        if ($l['foto_path']) {
            $det[] = 'Com foto';
        }
        $itensHtml .= '<div style="margin:0 0 10px;padding:10px 12px;background:#fff;border-radius:10px;border:1px solid #e4d9bd;">'
            . '<b>' . e($titulo) . '</b><br><span style="font-size:13px;color:#4a4936;">' . implode('<br>', array_map('e', $det)) . '</span></div>';
        $itensTexto .= "- {$titulo}\n  " . implode("\n  ", $det) . "\n";
    }

    $painel = url_absoluta('cozinha/pedidos');
    $corpo = '<p style="margin:0 0 16px;text-align:center;"><span style="display:inline-block;background:' . $cor
        . ';color:#fff;font-weight:bold;padding:8px 16px;border-radius:999px;font-size:14px;">' . e($status) . '</span></p>'
        . $tabela
        . '<h3 style="margin:20px 0 10px;font-size:16px;">O que está comprando</h3>' . $itensHtml
        . email_botao($S, $painel, 'Abrir pedidos no painel');

    $texto = "Status: {$status}\n\n";
    foreach ($info as $k => $v) {
        $texto .= "{$k}: {$v}\n";
    }
    $texto .= "\nO que está comprando:\n{$itensTexto}\nPainel: {$painel}\n";

    $html = email_moldura($S, '🔔', 'Alerta de pagamento', $corpo);
    $assunto = "🔔 {$status} · {$p['codigo']} · " . alerta_reais((int) $p['total_centavos']) . ' · ' . nome_proprio($p['usuario_nome']);
    return [$assunto, $html, $texto];
}

/** Para a cron: pedidos pendentes há mais de ALERTA_ATRASO_MIN minutos que ainda não geraram o alerta. */
function alertas_atrasados(): array
{
    $st = db()->query("SELECT p.codigo, TIMESTAMPDIFF(MINUTE, p.criado_em, NOW()) AS minutos FROM pedidos p
                       WHERE p.status = 'pendente' AND p.criado_em < NOW() - INTERVAL " . ALERTA_ATRASO_MIN . " MINUTE
                         AND NOT EXISTS (SELECT 1 FROM pedido_alertas a WHERE a.pedido_id = p.id AND a.evento = 'atraso' AND a.enviado_em IS NOT NULL)
                       ORDER BY p.criado_em LIMIT 50");
    $enviados = [];
    foreach ($st->fetchAll() as $r) {
        if (alerta_pedido($r['codigo'], 'atraso', ['minutos' => (int) $r['minutos']])) {
            $enviados[] = $r['codigo'];
        }
    }
    return $enviados;
}

/** Para a cron: pedidos que expiraram (sem pagamento confirmado) nas últimas 24 h e ainda não foram avisados. */
function alertas_expirados(): array
{
    $st = db()->query("SELECT p.codigo FROM pedidos p
                       WHERE p.status = 'expirado' AND p.resolvido_em > NOW() - INTERVAL 1 DAY
                         AND NOT EXISTS (SELECT 1 FROM pedido_alertas a WHERE a.pedido_id = p.id AND a.evento = 'expirou' AND a.enviado_em IS NOT NULL)
                       ORDER BY p.resolvido_em LIMIT 50");
    $enviados = [];
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $codigo) {
        if (alerta_pedido($codigo, 'expirou')) {
            $enviados[] = $codigo;
        }
    }
    return $enviados;
}

// =====================================================================
// Alertas gerais ao administrador (cron): painel, erros, moderação, presentes e resumo diário
// =====================================================================

const ALERTA_MOD_POSTS       = 8;   // publicações de uma pessoa em 15 min para virar alerta
const ALERTA_MOD_COMENTARIOS = 20;  // comentários de uma pessoa em 15 min
const ALERTA_PRESENTE_DIAS   = 3;   // presente pago há X dias sem resgate
const ALERTA_RESUMO_HORA     = 8;   // resumo diário sai a partir desta hora

/** Lê (ou grava, com $valor) um valor de estado das rotinas. */
function alerta_estado(string $chave, ?string $valor = null): ?string
{
    if ($valor !== null) {
        db()->prepare('INSERT INTO alertas_estado (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)')
            ->execute([$chave, $valor]);
        return $valor;
    }
    $st = db()->prepare('SELECT valor FROM alertas_estado WHERE chave = ?');
    $st->execute([$chave]);
    $v = $st->fetchColumn();
    return $v === false ? null : (string) $v;
}

/**
 * true se (tipo, id) já foi avisado; senão registra e devolve false.
 * $repetirHoras: depois de tantas horas pode avisar de novo (null = nunca repete).
 */
function alerta_ja_enviado(string $tipo, int $refId, ?int $repetirHoras = null): bool
{
    $novo = $repetirHoras ? 'IF(enviado_em < NOW() - INTERVAL ' . (int) $repetirHoras . ' HOUR, NOW(), enviado_em)' : 'enviado_em';
    $st = db()->prepare("INSERT INTO alertas_enviados (tipo, ref_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE enviado_em = $novo");
    $st->execute([$tipo, $refId]);
    return $st->rowCount() === 0; // 1 = novo, 2 = repetiu, 0 = avisado há pouco
}

/** Tabela simples para os e-mails do administrador ($cabecalho null = sem linha de títulos). */
function alerta_tabela(?array $cabecalho, array $linhas): string
{
    $th = $cabecalho ? '<tr>' . implode('', array_map(fn($c) => '<th style="text-align:left;padding:6px 8px;border-bottom:2px solid #e4d9bd;font-size:12px;color:#6b6a55;">' . e($c) . '</th>', $cabecalho)) . '</tr>' : '';
    $trs = '';
    foreach ($linhas as $l) {
        $trs .= '<tr>' . implode('', array_map(fn($v) => '<td style="padding:6px 8px;border-bottom:1px solid #eee3c8;font-size:13px;vertical-align:top;">' . e((string) $v) . '</td>', $l)) . '</tr>';
    }
    return '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;background:#fff;border-radius:10px;margin:0 0 16px;">'
        . "$th$trs</table>";
}

/** Texto puro da mesma tabela (versão sem HTML do e-mail). */
function alerta_tabela_texto(?array $cabecalho, array $linhas): string
{
    $out = $cabecalho ? implode(' | ', $cabecalho) . "\n" : '';
    foreach ($linhas as $l) {
        $out .= implode(' | ', array_map('strval', $l)) . "\n";
    }
    return $out;
}

/**
 * Manda um e-mail ao administrador. $secoes = [[título, cabeçalho, linhas], …] (cabeçalho null = linhas são [rótulo, valor]).
 */
function alerta_admin(string $assunto, string $faixa, array $secoes, string $intro = '', ?string $botao = null): bool
{
    $destinos = alerta_destinos();
    if (!$destinos || !is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
        return false;
    }
    require_once __DIR__ . '/mailer.php';
    $S = side(array_key_first(SIDES));
    $corpo = $intro !== '' ? '<p style="margin:0 0 16px;">' . e($intro) . '</p>' : '';
    $texto = $intro !== '' ? "$intro\n\n" : '';
    foreach ($secoes as [$titulo, $cab, $linhas]) {
        $corpo .= '<h3 style="margin:18px 0 8px;font-size:16px;">' . e($titulo) . '</h3>';
        $corpo .= alerta_tabela($cab, $linhas);
        $texto .= "== $titulo ==\n" . alerta_tabela_texto($cab, $linhas) . "\n";
    }
    $painel = url_absoluta($botao ?? 'cozinha/');
    $corpo .= email_botao($S, $painel, 'Abrir o painel');
    $texto .= "Painel: $painel\n";
    $html = email_moldura($S, '🔔', $faixa, $corpo);
    $ok = false;
    foreach ($destinos as $para) {
        $ok = enviar_email($para, 'Administrador', $assunto, $html, $texto) === null || $ok;
    }
    return $ok;
}

/** Data/hora curta para as tabelas. */
function alerta_hora(?string $dt): string
{
    return $dt ? date('d/m H:i', strtotime($dt)) : '—';
}

/**
 * Lê os logs novos desde a última execução e manda até 3 e-mails: painel (entradas, senha errada, bloqueio),
 * erros do sistema e moderação (rate limit, quem publica/comenta demais, denúncias). Retorna o que mandou.
 */
function alertas_logs(): array
{
    $pdo = db();
    $max = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM logs')->fetchColumn();
    $ultimo = alerta_estado('logs_ultimo_id');
    alerta_estado('logs_ultimo_id', (string) $max);
    if ($ultimo === null) {
        return []; // primeira execução: começa a olhar daqui para frente (sem enxurrada do passado)
    }
    $st = $pdo->prepare("SELECT id, criado_em, nivel, categoria, evento, mensagem, ip, user_agent, rota, dados FROM logs
                         WHERE id > ? AND id <= ?
                           AND (evento IN ('painel_login_ok', 'painel_login_falha', 'painel_login_bloqueado', 'cron_negada', 'limite_excedido')
                                OR nivel = 'erro')
                         ORDER BY id LIMIT 3000");
    $st->execute([(int) $ultimo, $max]);
    $painel = $erros = $limites = [];
    foreach ($st->fetchAll() as $l) {
        if (str_starts_with($l['evento'], 'painel_login') || $l['evento'] === 'cron_negada') {
            $painel[] = $l;
        } elseif ($l['evento'] === 'limite_excedido') {
            $limites[] = $l;
        } else {
            $erros[] = $l;
        }
    }
    $enviados = [];

    // ---- painel ----
    if ($painel) {
        $nomes = ['painel_login_ok' => '✅ Entrou no painel', 'painel_login_falha' => '❌ Senha errada', 'painel_login_bloqueado' => '⛔ IP bloqueado (tentativas demais)',
                  'cron_negada' => '⚠️ Chamada da cron com chave errada'];
        $linhas = array_map(fn($l) => [alerta_hora($l['criado_em']), $nomes[$l['evento']] ?? $l['evento'], $l['ip'] ?: '—', mb_substr((string) $l['user_agent'], 0, 70)], $painel);
        $suspeito = array_filter($painel, fn($l) => $l['evento'] !== 'painel_login_ok');
        $assunto = $suspeito ? '⚠️ Painel: ' . count($suspeito) . ' tentativa(s) suspeita(s)' : '🔐 Painel: alguém entrou (' . count($painel) . 'x)';
        if (alerta_admin($assunto, 'Acesso ao painel', [['Acessos ao painel /cozinha', ['Quando', 'O quê', 'IP', 'Aparelho'], $linhas]],
            'Se não foi você, troque a ADMIN_PASSWORD no .env.', 'cozinha/logs')) {
            $enviados[] = 'painel';
        }
    }

    // ---- erros do sistema (agrupados: mesmo erro repetido vira uma linha com a contagem) ----
    if ($erros) {
        $grupos = [];
        foreach ($erros as $l) {
            $k = $l['evento'] . '|' . mb_substr((string) $l['mensagem'], 0, 160);
            $grupos[$k] ??= ['n' => 0, 'l' => $l];
            $grupos[$k]['n']++;
            $grupos[$k]['l'] = $l; // o mais recente
        }
        uasort($grupos, fn($a, $b) => $b['n'] <=> $a['n']);
        $linhas = array_map(fn($g) => [$g['n'] . 'x', alerta_hora($g['l']['criado_em']), $g['l']['categoria'] . ' / ' . $g['l']['evento'],
                                       mb_substr((string) $g['l']['mensagem'], 0, 220), $g['l']['rota'] ?: '—'], array_slice(array_values($grupos), 0, 30));
        if (alerta_admin('🚨 ' . count($erros) . ' erro(s) no sistema', 'Erros do sistema',
            [['Erros desde o último alerta', ['Vezes', 'Último', 'Onde', 'Mensagem', 'Rota'], $linhas]], '', 'cozinha/logs')) {
            $enviados[] = 'erros';
        }
    }

    // ---- moderação ----
    $secoes = [];
    if ($limites) {
        $grupos = [];
        foreach ($limites as $l) {
            $d = json_decode((string) $l['dados'], true) ?: [];
            $k = ($l['ip'] ?? '') . '|' . ($d['limite'] ?? '');
            $grupos[$k] ??= ['n' => 0, 'ip' => $l['ip'], 'limite' => $d['limite'] ?? '?', 'max' => $d['max'] ?? '?', 'ua' => $l['user_agent'], 'ultimo' => $l['criado_em']];
            $grupos[$k]['n']++;
            $grupos[$k]['ultimo'] = $l['criado_em'];
        }
        uasort($grupos, fn($a, $b) => $b['n'] <=> $a['n']);
        $secoes[] = ['Limite de ações estourado (possível robô ou abuso)', ['IP', 'Onde', 'Vezes', 'Último', 'Aparelho'],
            array_map(fn($g) => [$g['ip'] ?: '—', $g['limite'] . ' (máx. ' . $g['max'] . '/min)', $g['n'] . 'x', alerta_hora($g['ultimo']), mb_substr((string) $g['ua'], 0, 60)],
                      array_slice(array_values($grupos), 0, 30))];
    }
    $secoes = array_merge($secoes, alertas_moderacao_conteudo());
    if ($secoes && alerta_admin('🛡️ Moderação: fique de olho', 'Moderação', $secoes)) {
        $enviados[] = 'moderacao';
    }
    return $enviados;
}

/** Quem publicou/comentou demais nos últimos 15 minutos e denúncias novas (seções para o e-mail de moderação). */
function alertas_moderacao_conteudo(): array
{
    $pdo = db();
    $secoes = [];
    $st = $pdo->query("SELECT u.id, u.nome, u.email, SUM(x.tipo = 'post') AS posts, SUM(x.tipo = 'comentario') AS comentarios,
                              SUBSTRING(MAX(x.texto), 1, 90) AS exemplo
                       FROM (SELECT 'post' AS tipo, p.item_id, p.texto FROM posts p WHERE p.criado_em > NOW() - INTERVAL 15 MINUTE AND p.is_frase_compra = 0
                             UNION ALL
                             SELECT 'comentario', c.item_id, c.texto FROM comentarios c WHERE c.criado_em > NOW() - INTERVAL 15 MINUTE) x
                       JOIN itens i ON i.id = x.item_id JOIN usuarios u ON u.id = i.usuario_id
                       GROUP BY u.id, u.nome, u.email
                       HAVING posts >= " . ALERTA_MOD_POSTS . ' OR comentarios >= ' . ALERTA_MOD_COMENTARIOS);
    $linhas = [];
    foreach ($st->fetchAll() as $r) {
        if (!alerta_ja_enviado('mod_usuario', (int) $r['id'], 1)) { // a mesma pessoa: no máximo 1 alerta por hora
            $linhas[] = [nome_proprio($r['nome']), $r['email'], (int) $r['posts'], (int) $r['comentarios'], (string) $r['exemplo']];
        }
    }
    if ($linhas) {
        $secoes[] = ['Publicando/comentando demais (últimos 15 min)', ['Pessoa', 'E-mail', 'Publicações', 'Comentários', 'Exemplo'], $linhas];
    }

    // denúncias abertas novas
    $ultima = (int) (alerta_estado('denuncias_ultimo_id') ?? 0);
    $st = $pdo->prepare("SELECT d.id, d.alvo_tipo, d.alvo_id, d.motivo, d.detalhes, d.criado_em, u.email FROM denuncias d
                         LEFT JOIN usuarios u ON u.id = d.usuario_id WHERE d.id > ? AND d.status = 'aberta' ORDER BY d.id LIMIT 50");
    $st->execute([$ultima]);
    $den = $st->fetchAll();
    if ($den) {
        alerta_estado('denuncias_ultimo_id', (string) max(array_column($den, 'id')));
        $secoes[] = ['Denúncias novas', ['Quando', 'Alvo', 'Motivo', 'Detalhes', 'Quem denunciou'],
            array_map(fn($d) => [alerta_hora($d['criado_em']), $d['alvo_tipo'] . ' #' . $d['alvo_id'], $d['motivo'], (string) $d['detalhes'], $d['email'] ?: 'visitante'], $den)];
    }
    return $secoes;
}

/** Presentes pagos há ALERTA_PRESENTE_DIAS dias ou mais que ninguém resgatou (repete toda semana enquanto não resgatar). */
function alertas_presentes(): bool
{
    $st = db()->query("SELECT i.id, i.lado, i.numero, i.nome, i.presente_token, u.nome AS comprador, u.email, p.codigo,
                              DATEDIFF(NOW(), COALESCE(p.pago_em, p.resolvido_em)) AS dias
                       FROM itens i JOIN pedido_itens pi ON pi.id = i.pedido_item_id JOIN pedidos p ON p.id = pi.pedido_id
                       JOIN usuarios u ON u.id = i.usuario_id
                       WHERE i.presente_token IS NOT NULL AND i.presente_resgatado_em IS NULL AND i.status = 'ativo' AND p.status = 'pago'
                         AND COALESCE(p.pago_em, p.resolvido_em) < NOW() - INTERVAL " . ALERTA_PRESENTE_DIAS . ' DAY
                       ORDER BY dias DESC LIMIT 100');
    $linhas = [];
    foreach ($st->fetchAll() as $r) {
        if (!alerta_ja_enviado('presente', (int) $r['id'], 24 * 7)) {
            $S = side($r['lado']);
            $linhas[] = [$S['Item'] . ' #' . str_pad((string) $r['numero'], 4, '0', STR_PAD_LEFT) . ' · ' . $S['name'], $r['nome'],
                         nome_proprio($r['comprador']) . ' <' . $r['email'] . '>', (int) $r['dias'] . ' dias', $r['codigo'],
                         url_absoluta('presente', ['t' => $r['presente_token']])];
        }
    }
    if (!$linhas) {
        return false;
    }
    return alerta_admin('🎁 ' . count($linhas) . ' presente(s) pago(s) e ainda não resgatado(s)', 'Presentes parados',
        [['Presentes sem resgate', ['Item', 'Para', 'Quem deu', 'Pago há', 'Pedido', 'Link do resgate'], $linhas]],
        'Vale lembrar quem deu de mandar o link para a pessoa (WhatsApp).', 'cozinha/pedidos');
}

/** Resumo do dia anterior, uma vez por dia a partir das ALERTA_RESUMO_HORA horas. */
function alerta_resumo_diario(bool $forcar = false): bool
{
    $hoje = date('Y-m-d');
    if (!$forcar && ((int) date('G') < ALERTA_RESUMO_HORA || alerta_estado('resumo_dia') === $hoje)) {
        return false;
    }
    alerta_estado('resumo_dia', $hoje);
    $pdo = db();
    $um = fn(string $sql) => $pdo->query($sql)->fetch(PDO::FETCH_NUM);
    $ontem = "BETWEEN CURDATE() - INTERVAL 1 DAY AND CURDATE() - INTERVAL 1 SECOND";

    [$criados, $criadosV] = $um("SELECT COUNT(*), COALESCE(SUM(total_centavos), 0) FROM pedidos WHERE criado_em $ontem");
    [$pagos, $pagosV] = $um("SELECT COUNT(*), COALESCE(SUM(total_centavos), 0) FROM pedidos WHERE status = 'pago' AND resolvido_em $ontem");
    [$expirados, $expiradosV] = $um("SELECT COUNT(*), COALESCE(SUM(total_centavos), 0) FROM pedidos WHERE status = 'expirado' AND resolvido_em $ontem");
    [$negados] = $um("SELECT COUNT(*) FROM pedidos WHERE status = 'cancelado' AND resolvido_em $ontem");
    [$pend, $pendV, $pendMin] = $um("SELECT COUNT(*), COALESCE(SUM(total_centavos), 0), MIN(criado_em) FROM pedidos WHERE status = 'pendente'");
    [$mes, $mesV] = $um("SELECT COUNT(*), COALESCE(SUM(total_centavos), 0) FROM pedidos WHERE status = 'pago'
                         AND resolvido_em >= DATE_FORMAT(CURDATE() - INTERVAL 1 DAY, '%Y-%m-01')");
    [$contas] = $um("SELECT COUNT(*) FROM usuarios WHERE criado_em $ontem");
    [$posts] = $um("SELECT COUNT(*) FROM posts WHERE is_frase_compra = 0 AND criado_em $ontem");
    [$coment] = $um("SELECT COUNT(*) FROM comentarios WHERE criado_em $ontem");
    [$ativos] = $um("SELECT COUNT(*) FROM itens WHERE status = 'ativo'");
    [$vencendo] = $um("SELECT COUNT(*) FROM itens WHERE status = 'ativo' AND valido_ate BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY");
    [$venceram] = $um("SELECT COUNT(*) FROM itens WHERE status IN ('ativo', 'vencido') AND valido_ate = CURDATE() - INTERVAL 1 DAY");
    [$avisosVenc] = $um("SELECT COUNT(*) FROM item_avisos_vencimento WHERE enviado_em $ontem");
    [$presentes] = $um("SELECT COUNT(*) FROM itens WHERE presente_token IS NOT NULL AND presente_resgatado_em IS NULL AND status = 'ativo'");
    [$erros, $seg, $limites, $painelFalha, $emailFalha] = $um("SELECT SUM(nivel = 'erro'), SUM(nivel = 'seguranca'), SUM(evento = 'limite_excedido'),
                                                                     SUM(evento IN ('painel_login_falha', 'painel_login_bloqueado')), SUM(evento = 'email_falha')
                                                              FROM logs WHERE criado_em $ontem");
    $r = fn($c) => alerta_reais((int) $c);
    $dia = date('d/m/Y', strtotime('-1 day'));

    $vendas = [
        ['Pedidos gerados', "$criados · " . $r($criadosV)],
        ['Pagos (aprovados)', "$pagos · " . $r($pagosV)],
        ['Expirados sem pagamento', "$expirados · " . $r($expiradosV)],
        ['Negados', (string) $negados],
        ['Aguardando confirmação agora', "$pend · " . $r($pendV) . ($pendMin ? ' · mais antigo: ' . alerta_hora($pendMin) : '')],
        ['Faturado no mês', "$mes pedido(s) · " . $r($mesV)],
        ['Conversão do dia', $criados ? round($pagos / $criados * 100) . '% dos pedidos gerados' : '—'],
    ];
    $site = [
        ['Contas novas', (string) $contas],
        ['Publicações no mural', (string) $posts],
        ['Comentários', (string) $coment],
        ['Itens ativos nos potes', (string) $ativos],
        ['Vencem nos próximos 30 dias', (string) $vencendo],
        ['Venceram ontem', (string) $venceram],
        ['Avisos de vencimento enviados', (string) $avisosVenc],
        ['Presentes esperando resgate', (string) $presentes],
    ];
    $saude = [
        ['Erros do sistema', (string) (int) $erros],
        ['Eventos de segurança', (string) (int) $seg],
        ['Limite de ações estourado', (string) (int) $limites],
        ['Senha errada / bloqueio no painel', (string) (int) $painelFalha],
        ['E-mails que falharam', (string) (int) $emailFalha],
    ];
    return alerta_admin("📊 Resumo de $dia · " . $r($pagosV) . " em $pagos venda(s)", "Resumo de $dia",
        [['Vendas', null, $vendas], ['Site', null, $site], ['Saúde do sistema', null, $saude]], '', 'cozinha/pedidos');
}

/** Banco fora do ar: e-mail direto (sem depender do MySQL), no máximo 1 por hora. */
function alerta_sem_banco(string $erro): void
{
    $marca = sys_get_temp_dir() . '/potepolitico-alerta-banco.txt';
    if (is_file($marca) && filemtime($marca) > time() - 3600) {
        return;
    }
    @touch($marca);
    alerta_admin('🚨 Banco de dados fora do ar', 'Erro grave', [['O que aconteceu', null, [
        ['Quando', date('d/m/Y H:i:s')],
        ['Erro', mb_substr($erro, 0, 300)],
        ['Efeito', 'O site não mostra os potes, não gera Pix e ninguém entra na conta até o MySQL voltar.'],
    ]]], 'Confira o MySQL no cPanel e as credenciais DB_* do .env.');
}
