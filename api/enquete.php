<?php
// Voto na enquete: POST JSON { enquete, opcao, lado } → enquete atualizada (ou { erro })
require dirname(__DIR__) . '/includes/config.php';
require dirname(__DIR__) . '/includes/enquetes.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder(array $dados, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['erro' => 'Use POST.'], 405);
}
// só aceita chamadas do próprio site
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
    responder(['erro' => 'Origem não permitida.'], 403);
}

$in = json_decode(file_get_contents('php://input'), true);
$enqueteId = (int) ($in['enquete'] ?? 0);
$opcaoId   = (int) ($in['opcao'] ?? 0);
$lado      = (string) ($in['lado'] ?? '');
if (!$enqueteId || !$opcaoId || !isset(SIDES[$lado])) {
    responder(['erro' => 'Dados inválidos.'], 422);
}

require dirname(__DIR__) . '/includes/auth.php';
$usuario = current_user();
if (!$usuario) {
    responder(['erro' => 'Entre para votar.', 'login' => true], 401);
}

try {
    $uid = (int) $usuario['id'];
    $erro = enquete_votar($enqueteId, $opcaoId, $lado, $uid);
    $e = enquete_buscar($enqueteId);
    if (!$e) {
        responder(['erro' => 'Enquete não encontrada.'], 404);
    }
    $dados = enquete_para_js($e, enquete_voto_de($enqueteId, $uid));
    responder($erro ? ['erro' => $erro, 'enquete' => $dados] : $dados, $erro ? 409 : 200);
} catch (PDOException $ex) {
    responder(['erro' => 'Não foi possível registrar o voto agora.'], 503);
}
