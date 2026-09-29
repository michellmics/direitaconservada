<?php
// Perfil:
//   { acao: "itens", lado, id, offset } → { itens, mais, total }  a lista de azeitonas da pessoa, 5 por vez (público)
//   { lado, foto: "data:image/jpeg;base64,…" | null, frase: "…" | null } → { ok, foto, frase }  editar o próprio perfil
//   foto  → vale para a pessoa inteira (as azeitonas e pimentas dela nos dois potes)
//   frase → vale neste pote (frase de azeitona não serve na pimenta): nas azeitonas dela e na frase do mural
// Presentes que ela deu e ninguém resgatou não mudam (são de outra pessoa).
require dirname(__DIR__) . '/includes/config.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/pedidos.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder(array $dados, int $status = 200): never
{
    log_api(basename(__FILE__, '.php'), $dados, $status); // tudo o que a API responde vai para o log
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['erro' => 'Use POST.'], 405);
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
    responder(['erro' => 'Origem não permitida.'], 403);
}
$in = json_decode(file_get_contents('php://input', false, null, 0, 1_000_000), true);
// rate limit por IP (includes/limite.php): geral das APIs + desta ação, por minuto
require_once dirname(__DIR__) . '/includes/limite.php';
limite_api('api', 150);
($in['acao'] ?? '') === 'itens' ? limite_api('perfil-itens', 60) : limite_api('perfil-editar', 10);
$lado = (string) ($in['lado'] ?? '');
if (!is_array($in) || !isset(SIDES[$lado])) {
    responder(['erro' => 'Dados inválidos.'], 422);
}

// a lista de azeitonas do perfil, 5 por vez (qualquer um vê): { acao: "itens", lado, id: número de uma delas, offset }
if (($in['acao'] ?? '') === 'itens') {
    $id = (int) ($in['id'] ?? 0);
    $offset = (int) ($in['offset'] ?? 0);
    if ($id < 1 || $offset < 0 || $offset > 100000) {
        responder(['erro' => 'Dados inválidos.'], 422);
    }
    try {
        responder(banco_pessoa_itens($lado, $id, $offset));
    } catch (PDOException $e) {
        logar('erro', 'sistema', 'erro_tratado', 'api/perfil itens: ' . $e->getMessage(), [], null, false, 500);
        responder(['erro' => 'Não deu para carregar agora. Tente de novo.'], 503);
    }
}

$u = current_user();
if (!$u) {
    responder(['erro' => 'Entre na sua conta para editar o perfil.', 'login' => true], 401);
}

$uid = (int) $u['id'];
$meus = "usuario_id = ? AND presente_token IS NULL AND status IN ('pendente', 'ativo', 'vencido')";
try {
    $pdo = db();
    $st = $pdo->prepare("SELECT COUNT(*) FROM itens WHERE $meus AND lado = ?");
    $st->execute([$uid, $lado]);
    if (!(int) $st->fetchColumn()) {
        responder(['erro' => 'Você ainda não tem ' . SIDES[$lado]['item'] . ' neste pote.'], 422);
    }
    $frase = isset($in['frase']) ? trim(preg_replace('/\s+/u', ' ', (string) $in['frase'])) : null;
    if ($frase !== null && ($frase === '' || mb_strlen($frase) > 140)) {
        responder(['erro' => 'A frase precisa ter de 1 a 140 letras.'], 422);
    }
    $foto = null;
    if (!empty($in['foto'])) {
        $foto = pedido_salvar_imagem((string) $in['foto'], 512);
        if (!$foto) {
            responder(['erro' => 'Não deu para usar essa imagem. Tente outra.'], 422);
        }
    }

    $pdo->beginTransaction();
    $antigas = [];
    if ($foto) {
        $st = $pdo->prepare("SELECT DISTINCT foto_path FROM itens WHERE $meus AND foto_path IS NOT NULL");
        $st->execute([$uid]);
        $antigas = $st->fetchAll(PDO::FETCH_COLUMN);
        $pdo->prepare("UPDATE itens SET foto_path = ? WHERE $meus")->execute([$foto, $uid]);
    }
    if ($frase !== null) {
        $pdo->prepare("UPDATE itens SET frase = ? WHERE $meus AND lado = ?")->execute([$frase, $uid, $lado]);
        // a frase no mural (post da compra) acompanha
        $pdo->prepare("UPDATE posts p JOIN itens i ON i.id = p.item_id SET p.texto = ?
                       WHERE p.is_frase_compra = 1 AND i.usuario_id = ? AND i.presente_token IS NULL AND i.lado = ?")
            ->execute([$frase, $uid, $lado]);
    }
    $pdo->commit();
    foreach ($antigas as $a) {
        pedido_apagar_imagem($a); // só apaga se nenhum outro item usa
    }
    responder(['ok' => true, 'foto' => $foto, 'frase' => $frase]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logar('erro', 'sistema', 'erro_tratado', 'api/perfil: ' . $e->getMessage(), [], null, false, 500);
    responder(['erro' => 'Não deu para salvar agora. Tente de novo.'], 503);
}
