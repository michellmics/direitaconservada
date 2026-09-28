<?php
// Comentários de um post, 10 por vez: POST JSON { post: "direita-o12", antes: "direita-c40" | null }
// → { comentarios: [...] (mais antigo primeiro), mais: true/false }. "antes" = o mais antigo já mostrado.
require dirname(__DIR__) . '/includes/config.php';
require dirname(__DIR__) . '/data/mock.php';
require dirname(__DIR__) . '/includes/comentarios.php';

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
$post = (string) ($in['post'] ?? '');
$antes = isset($in['antes']) ? (string) $in['antes'] : null;
if (!preg_match('/^\w+-[op]\w+$/', $post) || ($antes !== null && !preg_match('/^\w+-c\d+$/', $antes))) {
    responder(['erro' => 'Dados inválidos.'], 422);
}
responder(comentarios_pagina($post, $antes));
