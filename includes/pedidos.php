<?php
// Pedidos (tabelas pedidos, pedido_itens e itens). O site é GRÁTIS (migration 028): não há cobrança.
//
//   1. A pessoa monta o pedido. Sem login: e-mail novo cria a conta e já entra; e-mail que já tem conta recebe o
//      código de acesso (ninguém entra na conta dos outros).
//   2. Na hora nascem os itens já 'ativo' (para todo mundo, por 1 ano) e a frase no mural. O pedido fica
//      registrado como 'pago' com total 0 (histórico). Renovar também é grátis e vale na hora.
//   Freio (antes era o preço): uma fruta por vez, uma vez por dia em cada pote, e os tipos vão sendo liberados
//   com o tempo de conta e as frutas já pegas (pedido_liberacao()).
//   Aprovar, negar e expirar ficaram só para os pedidos antigos, do tempo do Pix.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/banco_dados.php';
require_once __DIR__ . '/itens.php';

const PEDIDO_EXPIRA_HORAS    = 24;
const PEDIDO_ALERTA_MIN      = 20;  // no painel, pendente há mais tempo que isso fica em destaque
// Uma fruta por vez, uma vez por dia em cada pote. Os tipos vão sendo liberados (na ordem de includes/sides.php):
// o 1º (básico) já vem liberado; o tipo N+1 exige N × LIBERA_MESES de conta e N × LIBERA_FRUTAS frutas já pegas
// (somando os dois potes, presentes dados incluídos). Igual no app.js (DC.liberacao vem daqui).
const LIBERA_MESES           = 6;
const LIBERA_FRUTAS          = 300;
const RENOVA_DIAS            = 30;  // renovar só nos últimos 30 dias antes de vencer (ou depois de vencido); igual no app.js
const PEDIDO_UPLOADS         = 'uploads/pedidos'; // fotos e selos enviados (relativo à raiz do site)
const PEDIDO_CODIGO_LETRAS   = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // sem 0/O, 1/I/L

function pedido_codigo_novo(): string
{
    $c = '';
    for ($i = 0; $i < 8; $i++) {
        $c .= PEDIDO_CODIGO_LETRAS[random_int(0, strlen(PEDIDO_CODIGO_LETRAS) - 1)];
    }
    return $c;
}

/** Itens de uma compra (não vale para renovação, que não cria itens). */
function pedido_itens_status(int $pedidoId, string $status, ?string $de = null): void
{
    db()->prepare("UPDATE itens i JOIN pedido_itens pi ON pi.id = i.pedido_item_id
                   SET i.status = ?" . ($status === 'ativo' ? ', i.desde = CURDATE(), i.valido_ate = CURDATE() + INTERVAL 1 YEAR' : '') . "
                   WHERE pi.pedido_id = ?" . ($de ? ' AND i.status = ?' : ''))
        ->execute($de ? [$status, $pedidoId, $de] : [$status, $pedidoId]);
}

/** Pendentes vencidos viram "expirado" e os itens deles saem. Barato: roda a cada consulta de pedidos e no painel. */
function pedidos_expirar(): void
{
    $pdo = db();
    $ids = $pdo->query("SELECT id FROM pedidos WHERE status = 'pendente' AND expira_em < NOW()")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        $st = $pdo->prepare("UPDATE pedidos SET status = 'expirado', resolvido_em = NOW() WHERE id = ? AND status = 'pendente'");
        $st->execute([$id]);
        if ($st->rowCount()) {
            pedido_itens_status((int) $id, 'removido', 'pendente');
            logar('info', 'pedido', 'pedido_expirado', "Pedido #$id expirou sem pagamento confirmado", ['id' => (int) $id]);
        }
    }
}

// ---------- imagens enviadas (foto e selo) ----------

/** Salva uma imagem "data:image/…;base64" já reduzida pelo navegador. Retorna o caminho público ou null se inválida. */
function pedido_salvar_imagem(?string $dataUrl, int $maxLado): ?string
{
    if (!$dataUrl || !preg_match('#^data:image/(jpeg|png);base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
        return null;
    }
    $bin = base64_decode($m[2], true);
    if ($bin === false || strlen($bin) > 300000) {
        return null;
    }
    $info = @getimagesizefromstring($bin);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true) || $info[0] > $maxLado || $info[1] > $maxLado) {
        return null;
    }
    $dir = dirname(__DIR__) . '/' . PEDIDO_UPLOADS;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Não deu para criar a pasta ' . PEDIDO_UPLOADS);
    }
    $nome = bin2hex(random_bytes(12)) . ($info[2] === IMAGETYPE_PNG ? '.png' : '.jpg');
    file_put_contents("$dir/$nome", $bin);
    return PEDIDO_UPLOADS . '/' . $nome;
}

/** Imagem já enviada numa compra anterior da mesma pessoa (o cadastro reaproveita a foto e o selo). */
function pedido_imagem_da_pessoa(?string $caminho, int $usuarioId): ?string
{
    if (!$caminho || !preg_match('#^' . preg_quote(PEDIDO_UPLOADS, '#') . '/[a-f0-9]{24}\.(jpg|png)$#', $caminho)) {
        return null;
    }
    $st = db()->prepare('SELECT 1 FROM itens WHERE usuario_id = ? AND (foto_path = ? OR selo_valor = ?) LIMIT 1');
    $st->execute([$usuarioId, $caminho, $caminho]);
    return $st->fetchColumn() ? $caminho : null;
}

function pedido_apagar_imagem(?string $caminho): void
{
    if (!$caminho || !preg_match('#^' . preg_quote(PEDIDO_UPLOADS, '#') . '/[a-f0-9]{24}\.(jpg|png)$#', $caminho)) {
        return;
    }
    // a mesma imagem pode estar em outras compras (cadastro reaproveitado): só apaga se ninguém mais usa
    $st = db()->prepare("SELECT 1 FROM itens WHERE (foto_path = ? OR selo_valor = ?) AND status <> 'removido' LIMIT 1");
    $st->execute([$caminho, $caminho]);
    if (!$st->fetchColumn()) {
        @unlink(dirname(__DIR__) . '/' . $caminho);
    }
}

// ---------- quem compra ----------

/**
 * Quem está comprando: o usuário logado; sem login, pelo e-mail.
 * E-mail novo → cria a conta e já entra. E-mail que já tem conta → manda o código de acesso
 * (retorna 'login' = mensagem e 'entrar' = tela para digitar o código, que depois volta para $voltar).
 */
function pedido_usuario(?array $logado, string $email, string $nome, string $lado, ?string $voltar = null,
                        string $depois = 'faça a compra (o carrinho continua aqui)'): array
{
    if ($logado) {
        return ['id' => (int) $logado['id']];
    }
    $email = mb_strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        return ['erro' => 'Informe um e-mail válido.'];
    }
    $pdo = db();
    $st = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
    $st->execute([$email]);
    if ($st->fetchColumn()) {
        $voltar = destino_seguro($voltar ?? url('pote', ['lado' => $lado]));
        $res = pedir_codigo($email, '', $voltar);
        if (isset($res['codigo'])) {
            require_once __DIR__ . '/mailer.php';
            $S = side($lado);
            $nomeConta = nome_proprio($res['usuario']['nome']);
            [$assunto, $html, $texto] = email_codigo_login($S, $nomeConta, $res['codigo']);
            if (enviar_email($email, $nomeConta, $assunto, $html, $texto) !== null) {
                return ['erro' => 'Esse e-mail já tem conta, mas não conseguimos enviar o código de acesso agora. Tente de novo em instantes.'];
            }
        } elseif (($res['erro'] ?? '') !== 'bloqueado') { // bloqueado: não revela, responde igual
            return ['erro' => $res['erro']];
        }
        return [
            'login'  => "Esse e-mail já tem conta. Enviamos um código de acesso para $email: digite o código, entre e $depois.",
            'entrar' => url('entrar', ['lado' => $lado, 'r' => $voltar, 'email' => $email]),
        ];
    }
    $pdo->prepare('INSERT INTO usuarios (nome, email) VALUES (?, ?)')->execute([mb_substr($nome, 0, 80), $email]);
    $id = (int) $pdo->lastInsertId();
    logar('info', 'conta', 'conta_criada', "Conta criada: $email", ['origem' => $voltar ? 'presente' : 'compra'], $id);
    iniciar_sessao($id); // conta nova: já entra (o e-mail é confirmado no primeiro link que ela usar)
    return ['id' => $id, 'entrou' => true];
}

// ---------- criar ----------

/** Valida uma linha do carrinho e salva as imagens. Retorna [linha, erro]. */
function pedido_validar_linha(string $lado, array $en, array $tipos, ?int $usuarioId): array
{
    $tipo = $tipos[(string) ($en['tipo'] ?? '')] ?? null;
    $nome = nome_proprio(mb_substr((string) ($en['nome'] ?? ''), 0, 60));
    $cidade = nome_proprio(mb_substr((string) ($en['cidade'] ?? ''), 0, 60));
    $uf = (string) ($en['uf'] ?? '');
    $frase = trim(preg_replace('/\s+/u', ' ', (string) ($en['frase'] ?? '')));
    $qtd = (int) ($en['qtd'] ?? 1);
    if (!$tipo) {
        return [null, 'Tipo inválido.'];
    }
    if ($nome === '' || mb_strlen($nome) > 28 || $cidade === '' || mb_strlen($cidade) > 30 || !in_array($uf, UFS, true)) {
        return [null, 'Confira o nome, a cidade e a UF.'];
    }
    if ($frase === '' || mb_strlen($frase) > 140) {
        return [null, 'A frase precisa ter de 1 a 140 letras.'];
    }
    if ($qtd < 1 || $qtd > 50) {
        return [null, 'Quantidade inválida.'];
    }
    // foto e selo: imagem nova (data:…) ou a que a pessoa já usou numa compra anterior (cadastro)
    $imagem = fn($v, int $max) => str_starts_with((string) $v, 'data:image/')
        ? pedido_salvar_imagem((string) $v, $max)
        : ($usuarioId ? pedido_imagem_da_pessoa((string) $v, $usuarioId) : null);
    $selo = (string) ($en['selo'] ?? '');
    $seloTipo = null;
    $seloValor = null;
    if ($selo !== '' && isset(SIDES[$lado]['selos'][$selo])) {
        [$seloTipo, $seloValor] = ['preset', $selo];
    } elseif ($selo !== '') {
        $seloValor = $imagem($selo, 128);
        $seloTipo = $seloValor ? 'imagem' : null;
    }
    return [[
        'item_tipo_id' => $tipo['id'],
        'preco'        => $tipo['preco_centavos'],
        'qtd'          => $qtd,
        'nome'         => $nome,
        'cidade'       => $cidade,
        'uf'           => $uf,
        'frase'        => $frase,
        'foto'         => $imagem($en['foto'] ?? null, 512),
        'selo_tipo'    => $seloTipo,
        'selo_valor'   => $seloValor,
        'renova'       => null,
        'presente'     => !empty($en['presente']), // 🎁: vira link para entregar depois do pagamento
    ], null];
}

/**
 * O que a conta pode pegar agora: ['nivel' => quantos tipos além do básico estão liberados, 'meses', 'frutas',
 * 'hoje' => [lado => já pegou hoje?], 'proximo' => ['meses', 'frutas'] do próximo tipo (null = todos liberados)].
 * Sem conta: só o básico, nada pego hoje.
 */
function pedido_liberacao(?int $usuarioId): array
{
    $maxNivel = max(array_map(fn($S) => count($S['types']), SIDES)) - 1;
    $meses = 0;
    $frutas = 0;
    $hoje = array_fill_keys(array_keys(SIDES), false);
    if ($usuarioId) {
        $pdo = db();
        $st = $pdo->prepare('SELECT TIMESTAMPDIFF(MONTH, criado_em, NOW()) FROM usuarios WHERE id = ?');
        $st->execute([$usuarioId]);
        $meses = (int) $st->fetchColumn();
        // frutas pegas = o que a conta pediu e entrou no pote (inclui as compras antigas, do tempo do Pix)
        $st = $pdo->prepare("SELECT COALESCE(SUM(pi.quantidade), 0) FROM pedido_itens pi JOIN pedidos p ON p.id = pi.pedido_id
                             WHERE p.usuario_id = ? AND p.status = 'pago' AND pi.renova_item_id IS NULL");
        $st->execute([$usuarioId]);
        $frutas = (int) $st->fetchColumn();
        $st = $pdo->prepare("SELECT DISTINCT p.lado FROM pedidos p JOIN pedido_itens pi ON pi.pedido_id = p.id
                             WHERE p.usuario_id = ? AND p.status IN ('pago', 'pendente') AND pi.renova_item_id IS NULL
                               AND p.criado_em >= CURDATE()");
        $st->execute([$usuarioId]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $lado) {
            $hoje[$lado] = true;
        }
    }
    $nivel = min($maxNivel, intdiv($meses, LIBERA_MESES), intdiv($frutas, LIBERA_FRUTAS));
    return [
        'nivel'   => $nivel,
        'meses'   => $meses,
        'frutas'  => $frutas,
        'hoje'    => $hoje,
        'proximo' => $nivel < $maxNivel ? ['meses' => ($nivel + 1) * LIBERA_MESES, 'frutas' => ($nivel + 1) * LIBERA_FRUTAS] : null,
        'regra'   => ['meses' => LIBERA_MESES, 'frutas' => LIBERA_FRUTAS],
    ];
}

/** Pode subir de nível (e avisa por e-mail). Falhar aqui não desfaz nada: o nível se acerta no próximo recálculo. */
function pedido_recalcular_nivel(int $usuarioId, string $lado): void
{
    try {
        require_once __DIR__ . '/tempero.php';
        tempero_recalcular($usuarioId, $lado);
    } catch (Throwable $e) {
    }
}

/**
 * Renova grátis, na hora, um item da pessoa (ativo ou vencido) por mais 1 ano — regra em renovar_item().
 * Retorna ['renovado' => ['numero', 'desde', 'valido_ate']] | ['erro' => mensagem].
 */
function pedido_renovar(string $lado, int $numero, ?array $logado): array
{
    if (!$logado) {
        return ['erro' => 'Entre na sua conta para renovar.'];
    }
    $uid = (int) $logado['id'];
    $pdo = db();
    $st = $pdo->prepare("SELECT id, status, valido_ate > CURDATE() + INTERVAL " . RENOVA_DIAS . " DAY AS cedo FROM itens
                         WHERE lado = ? AND numero = ? AND usuario_id = ? AND status IN ('ativo', 'vencido')");
    $st->execute([$lado, $numero, $uid]);
    $item = $st->fetch();
    if (!$item) {
        return ['erro' => 'Não encontramos esse item na sua conta.'];
    }
    if ($item['cedo']) {
        return ['erro' => 'Dá para renovar nos últimos ' . RENOVA_DIAS . ' dias antes de vencer.'];
    }
    $pdo->beginTransaction();
    try {
        renovar_item((int) $item['id']);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    pedido_recalcular_nivel($uid, $lado);
    $st = $pdo->prepare('SELECT desde, valido_ate FROM itens WHERE id = ?');
    $st->execute([(int) $item['id']]);
    return ['renovado' => ['numero' => $numero] + $st->fetch()];
}

/**
 * Cria o pedido (grátis: os itens já nascem ativos). $in = { lado, email?, itens: [{ tipo, nome, cidade, uf, frase, foto, selo, qtd }] }
 * ou, para renovar, { lado, renovar: número do item no pote }.
 * Retorna ['pedido' => …, 'itens' => [...], 'entrou' => bool] | ['renovado' => …] | ['login' => mensagem] | ['erro' => mensagem].
 */
function pedido_criar(array $in, ?array $logado, string $ip): array
{
    $lado = (string) ($in['lado'] ?? '');
    if (!isset(SIDES[$lado])) {
        return ['erro' => 'Pote inválido.'];
    }
    if (isset($in['renovar'])) {
        return pedido_renovar($lado, (int) $in['renovar'], $logado);
    }
    $pdo = db();

    $S = SIDES[$lado];
    // uma fruta por vez, de um tipo já liberado para a conta, e uma vez por dia neste pote
    $entradas = is_array($in['itens'] ?? null) ? $in['itens'] : [];
    if (count($entradas) !== 1 || !is_array($entradas[0]) || (int) ($entradas[0]['qtd'] ?? 1) !== 1) {
        return ['erro' => "É uma {$S['item']} por vez."];
    }
    $erroDia = "Você já pegou sua {$S['item']} de hoje. Volte amanhã para pegar mais uma!";
    $libera = pedido_liberacao($logado ? (int) $logado['id'] : null);
    if ($libera['hoje'][$lado]) {
        return ['erro' => $erroDia];
    }
    $ordem = array_search((string) ($entradas[0]['tipo'] ?? ''), array_keys($S['types']), true);
    if ($ordem !== false && $ordem > $libera['nivel']) {
        return ['erro' => "{$S['Item']} " . $S['types'][$entradas[0]['tipo']]['label'] . ' ainda não foi liberada para você.'];
    }
    $st = $pdo->prepare('SELECT slug, id, preco_centavos FROM item_tipos WHERE lado = ? AND ativo = 1');
    $st->execute([$lado]);
    $tipos = [];
    foreach ($st->fetchAll() as $t) {
        $tipos[$t['slug']] = ['id' => (int) $t['id'], 'preco_centavos' => (int) $t['preco_centavos']];
    }
    [$linha, $erro] = pedido_validar_linha($lado, ['qtd' => 1] + $entradas[0], $tipos, $logado ? (int) $logado['id'] : null);
    if ($erro) {
        return ['erro' => $erro];
    }
    $linhas = [$linha];

    $u = pedido_usuario($logado, (string) ($in['email'] ?? ''), $linha['nome'], $lado);
    if (!isset($u['id'])) {
        pedido_descartar_imagens($linhas);
        return $u; // erro ou "enviamos o link"
    }

    $numeros = [];
    $comPost = []; // números que ganharam a frase no mural
    $pdo->beginTransaction();
    try {
        // trava a conta: duas abas ao mesmo tempo não pegam duas no mesmo dia
        $pdo->prepare('SELECT id FROM usuarios WHERE id = ? FOR UPDATE')->execute([$u['id']]);
        if (pedido_liberacao($u['id'])['hoje'][$lado]) {
            $pdo->rollBack();
            pedido_descartar_imagens($linhas);
            return ['erro' => $erroDia];
        }
        for ($tentativa = 0; ; $tentativa++) {
            $codigo = pedido_codigo_novo();
            try {
                // grátis: o pedido só fica de histórico (já resolvido e avisado)
                $pdo->prepare("INSERT INTO pedidos (codigo, usuario_id, lado, status, subtotal_centavos, total_centavos, gateway, ip,
                                                    pago_em, resolvido_em, avisado_em)
                               VALUES (?, ?, ?, 'pago', 0, 0, 'gratis', ?, NOW(), NOW(), NOW())")
                    ->execute([$codigo, $u['id'], $lado, $ip]);
                break;
            } catch (PDOException $e) {
                if ($tentativa >= 3 || $e->errorInfo[1] !== 1062) { // 1062 = código repetido: sorteia outro
                    throw $e;
                }
            }
        }
        $pedidoId = (int) $pdo->lastInsertId();
        $insLinha = $pdo->prepare('INSERT INTO pedido_itens (pedido_id, item_tipo_id, quantidade, preco_unit_centavos, nome_certificado, cidade, uf,
                                                             frase, foto_path, selo_tipo, selo_valor, renova_item_id, presente)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        // os itens já nascem ativos (para todo mundo) com o número no pote e a frase no mural
        $insItem = $pdo->prepare("INSERT INTO itens (lado, numero, usuario_id, pedido_item_id, presente_token, item_tipo_id, nome, cidade, uf, frase,
                                                     foto_path, selo_tipo, selo_valor, desde, valido_ate, status)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURDATE() + INTERVAL 1 YEAR, 'ativo')");
        $insPost = $pdo->prepare('INSERT INTO posts (lado, item_id, usuario_id, texto, is_frase_compra) VALUES (?, ?, ?, ?, 1)');
        // frase que a pessoa já tem no mural (compra anterior, com o cadastro) não vira post de novo
        $jaNoMural = $pdo->prepare("SELECT 1 FROM posts p JOIN itens i ON i.id = p.item_id
                                    WHERE i.usuario_id = ? AND p.lado = ? AND p.is_frase_compra = 1 AND p.status = 'publicado'
                                      AND i.status IN ('pendente', 'ativo') AND p.texto = ? LIMIT 1");
        foreach ($linhas as $l) {
            $insLinha->execute([$pedidoId, $l['item_tipo_id'], $l['qtd'], 0, $l['nome'], $l['cidade'], $l['uf'],
                                $l['frase'], $l['foto'], $l['selo_tipo'], $l['selo_valor'], null, (int) $l['presente']]);
            $linhaId = (int) $pdo->lastInsertId();
            $n = $pdo->prepare('SELECT proximo_numero FROM potes WHERE slug = ? FOR UPDATE');
            $n->execute([$lado]);
            $numero = (int) $n->fetchColumn();
            $pdo->prepare('UPDATE potes SET proximo_numero = proximo_numero + ? WHERE slug = ?')->execute([$l['qtd'], $lado]);
            $jaNoMural->execute([$u['id'], $lado, $l['frase']]);
            $postar = $l['presente'] || !$jaNoMural->fetchColumn(); // presente é outra pessoa: a frase dela vai ao mural
            $jaNoMural->closeCursor();
            for ($k = 0; $k < $l['qtd']; $k++) {
                $insItem->execute([$lado, $numero + $k, $u['id'], $linhaId, $l['presente'] ? bin2hex(random_bytes(12)) : null, $l['item_tipo_id'], $l['nome'], $l['cidade'], $l['uf'],
                                   $l['frase'], $l['foto'], $l['selo_tipo'], $l['selo_valor']]);
                if ($k === 0 && $postar) { // cópias do mesmo item não repetem a frase no mural
                    $insPost->execute([$lado, $pdo->lastInsertId(), $u['id'], $l['frase']]);
                    $comPost[] = $numero + $k;
                }
                $numeros[] = $numero + $k;
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    pedido_recalcular_nivel($u['id'], $lado);
    logar('info', 'pedido', 'itens_gratis', count($numeros) . " item(ns) no pote $lado", ['numeros' => $numeros], $u['id']);
    // os itens novos, no formato do JS (para aparecerem na hora, sem recarregar)
    $itens = [];
    if ($numeros) {
        $st = $pdo->prepare('SELECT ' . BANCO_ITEM_CAMPOS . ' FROM itens i ' . BANCO_ITEM_JOINS . '
                             WHERE i.lado = ? AND i.numero IN (' . implode(',', $numeros) . ') ORDER BY i.numero');
        $st->execute([$lado]);
        foreach ($st->fetchAll() as $r) {
            $itens[] = banco_item_js($r) + ['criado' => (int) (microtime(true) * 1000) + count($itens), 'postado' => in_array((int) $r['id'], $comPost, true),
                                           'presente' => $r['presente_token'] !== null, 'dono' => (int) $r['id'],
                                           'link' => $r['presente_token'] !== null ? url_absoluta('presente', ['t' => $r['presente_token']]) : null];
        }
    }
    return ['pedido' => pedidos_js("p.id = $pedidoId")[0], 'itens' => $itens, 'entrou' => !empty($u['entrou'])];
}

function pedido_descartar_imagens(array $linhas): void
{
    foreach ($linhas as $l) {
        pedido_apagar_imagem($l['foto']);
        pedido_apagar_imagem($l['selo_tipo'] === 'imagem' ? $l['selo_valor'] : null);
    }
}

// ---------- consultar ----------

/** Pedidos no formato do JS (com o Pix, se ainda pendente). $onde = condição SQL sobre "p" (sem dados do usuário). */
function pedidos_js(string $onde, array $params = []): array
{
    $st = db()->prepare("SELECT p.*, (SELECT MAX(pi.renova_item_id) FROM pedido_itens pi WHERE pi.pedido_id = p.id) AS renova_id,
                                (SELECT i.numero FROM itens i WHERE i.id = (SELECT MAX(pi.renova_item_id) FROM pedido_itens pi WHERE pi.pedido_id = p.id)) AS renova,
                                (SELECT SUM(pi.quantidade) FROM pedido_itens pi WHERE pi.pedido_id = p.id) AS unidades
                         FROM pedidos p WHERE $onde ORDER BY p.criado_em");
    $st->execute($params);
    $out = [];
    foreach ($st->fetchAll() as $p) {
        $js = [
            'codigo'   => $p['codigo'],
            'lado'     => $p['lado'],
            'status'   => $p['status'], // pendente | pago | expirado | cancelado
            'total'    => (int) $p['total_centavos'] / 100,
            'titular'  => $p['pagador_nome'],
            'criado'   => $p['criado_em'],
            'renova'   => $p['renova'] !== null ? (int) $p['renova'] : null,
            'unidades' => (int) $p['unidades'],
        ];
        $out[] = $js;
    }
    return $out;
}

/**
 * Para as páginas: os pedidos pendentes da pessoa (com o Pix) e os avisos do que o painel resolveu desde a
 * última visita (aprovado, negado, expirado) — cada aviso aparece uma vez só.
 */
function pedidos_da_conta(int $usuarioId): array
{
    pedidos_expirar();
    $avisos = pedidos_js("p.usuario_id = ? AND p.status IN ('pago', 'cancelado', 'expirado') AND p.avisado_em IS NULL
                          AND p.resolvido_em > NOW() - INTERVAL 30 DAY", [$usuarioId]);
    if ($avisos) {
        db()->prepare("UPDATE pedidos SET avisado_em = NOW() WHERE usuario_id = ? AND status IN ('pago', 'cancelado', 'expirado') AND avisado_em IS NULL")
            ->execute([$usuarioId]);
    }
    $st = db()->prepare('SELECT pagador_nome FROM pedidos WHERE usuario_id = ? ORDER BY id DESC LIMIT 1');
    $st->execute([$usuarioId]);
    return [
        'pendentes' => pedidos_js("p.usuario_id = ? AND p.status = 'pendente'", [$usuarioId]),
        'avisos'    => $avisos,
        'titular'   => $st->fetchColumn() ?: null, // o último titular informado (para não digitar de novo)
    ];
}

/** Para o painel: pendentes (mais antigos primeiro), expirados dos últimos 7 dias e os últimos resolvidos. */
function pedidos_painel(): array
{
    pedidos_expirar();
    $pdo = db();
    $base = 'SELECT p.*, u.nome AS usuario_nome, u.email, TIMESTAMPDIFF(MINUTE, p.criado_em, NOW()) AS minutos
             FROM pedidos p JOIN usuarios u ON u.id = p.usuario_id ';
    $grupos = [
        'pendentes' => $pdo->query($base . "WHERE p.status = 'pendente' ORDER BY p.criado_em")->fetchAll(),
        'expirados' => $pdo->query($base . "WHERE p.status = 'expirado' AND p.criado_em > NOW() - INTERVAL 7 DAY ORDER BY p.criado_em DESC")->fetchAll(),
        'resolvidos' => $pdo->query($base . "WHERE p.status IN ('pago', 'cancelado') ORDER BY p.resolvido_em DESC LIMIT 30")->fetchAll(),
    ];
    $ids = array_merge(...array_map(fn($g) => array_column($g, 'id'), array_values($grupos)));
    $linhas = [];
    if ($ids) {
        $st = $pdo->query('SELECT pi.*, t.nome AS tipo_nome FROM pedido_itens pi JOIN item_tipos t ON t.id = pi.item_tipo_id
                           WHERE pi.pedido_id IN (' . implode(',', array_map('intval', $ids)) . ') ORDER BY pi.id');
        foreach ($st->fetchAll() as $l) {
            $linhas[$l['pedido_id']][] = $l;
        }
    }
    foreach ($grupos as &$g) {
        foreach ($g as &$p) {
            $p['linhas'] = $linhas[$p['id']] ?? [];
        }
    }
    return $grupos;
}

// ---------- aprovar / negar ----------

/** Pagamento conferido: os itens vão para o pote de todo mundo (ou a renovação vale). Aceita pendente e expirado. */
function pedido_aprovar(int $id): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM pedidos WHERE id = ? FOR UPDATE');
        $st->execute([$id]);
        $p = $st->fetch();
        if (!$p || !in_array($p['status'], ['pendente', 'expirado'], true)) {
            throw new DomainException('Esse pedido já foi resolvido.');
        }
        $st = $pdo->prepare('SELECT renova_item_id FROM pedido_itens WHERE pedido_id = ? AND renova_item_id IS NOT NULL');
        $st->execute([$id]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $itemId) {
            renovar_item((int) $itemId);
        }
        pedido_itens_status($id, 'ativo'); // 1 ano a partir de hoje (dia da confirmação)
        $pdo->prepare("UPDATE pedidos SET status = 'pago', pago_em = NOW(), resolvido_em = NOW(), avisado_em = NULL WHERE id = ?")->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    try {
        require_once __DIR__ . '/tempero.php';
        tempero_recalcular((int) $p['usuario_id'], $p['lado']); // pode subir de nível (e avisa por e-mail)
    } catch (Throwable $e) {
        // o pedido já está aprovado; o nível se acerta no próximo recálculo
    }
}

/** Pagamento não encontrado: cancela o pedido, tira os itens do ar e apaga as imagens enviadas. */
function pedido_negar(int $id): void
{
    $pdo = db();
    $st = $pdo->prepare("UPDATE pedidos SET status = 'cancelado', resolvido_em = NOW(), avisado_em = NULL WHERE id = ? AND status IN ('pendente', 'expirado')");
    $st->execute([$id]);
    if ($st->rowCount() !== 1) {
        throw new DomainException('Esse pedido já foi resolvido.');
    }
    pedido_itens_status($id, 'removido');
    $st = $pdo->prepare('SELECT foto_path, selo_tipo, selo_valor FROM pedido_itens WHERE pedido_id = ?');
    $st->execute([$id]);
    pedido_descartar_imagens(array_map(fn($l) => ['foto' => $l['foto_path'], 'selo_tipo' => $l['selo_tipo'], 'selo_valor' => $l['selo_valor']], $st->fetchAll()));
}
