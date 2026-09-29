<?php
// Apoio partidário: POST JSON { siglas: ["PT", "PSOL"] } (até 3, qualquer partido; [] tira o apoio)
// → { meus, ranking } ou { erro } / { login: true } para quem não entrou.
require dirname(__DIR__) . '/includes/config.php';
require dirname(__DIR__) . '/includes/partidos.php';

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
// só aceita chamadas do próprio site
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
    responder(['erro' => 'Origem não permitida.'], 403);
}

$in = json_decode(file_get_contents('php://input'), true);
// rate limit por IP (includes/limite.php): geral das APIs + desta ação, por minuto
require_once dirname(__DIR__) . '/includes/limite.php';
limite_api('api', 150);
limite_api('partidos', 20);
$siglas = $in['siglas'] ?? null;
if (!is_array($siglas)) {
    responder(['erro' => 'Dados inválidos.'], 422);
}

require dirname(__DIR__) . '/includes/auth.php';
$usuario = current_user();
if (!$usuario) {
    responder(['erro' => 'Entre para registrar seu apoio.', 'login' => true], 401);
}

try {
    $uid = (int) $usuario['id'];
    if ($erro = partidos_apoiar($uid, $siglas)) {
        responder(['erro' => $erro], 422);
    }
    responder(['meus' => partidos_da_pessoa($uid), 'ranking' => partidos_ranking()]);
} catch (Throwable $e) {
    responder(['erro' => 'Não foi possível registrar agora. Tente de novo.'], 503);
}
