<?php
// Links de perfil cifrados para itens que só existem no navegador (compras do protótipo):
// POST JSON { itens: [{ lado, id }, …] } → { links: { lado: { id: "perfil?c=…" } } }
// A chave (ENV_KEY) fica no servidor; só gera links de perfil (lado + número), nada além disso.
require dirname(__DIR__) . '/includes/config.php';

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
$itens = is_array($in['itens'] ?? null) ? array_slice($in['itens'], 0, 100) : [];
$links = [];
foreach ($itens as $it) {
    $lado = (string) ($it['lado'] ?? '');
    $id = (int) ($it['id'] ?? 0);
    if (isset(SIDES[$lado]) && $id > 0 && $id < 1000000000) {
        $links[$lado][$id] = url('perfil', ['lado' => $lado, 'id' => $id]);
    }
}
responder(['links' => $links]);
