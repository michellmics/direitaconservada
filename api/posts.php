<?php
// Posts do mural, 12 por vez: POST JSON { lado, ordem: recentes|top|debate, offset, perfil: id|null }
// → { posts: [...], mais: true/false }. offset = quantos posts do servidor já estão na tela.
require dirname(__DIR__) . '/includes/config.php';
require dirname(__DIR__) . '/data/mock.php';
require dirname(__DIR__) . '/includes/comentarios.php';
require dirname(__DIR__) . '/includes/posts.php';

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
$lado = (string) ($in['lado'] ?? '');
$ordem = (string) ($in['ordem'] ?? 'recentes');
$offset = (int) ($in['offset'] ?? 0);
$perfil = isset($in['perfil']) ? (int) $in['perfil'] : null;
if (!isset(SIDES[$lado]) || !in_array($ordem, POSTS_ORDENS, true) || $offset < 0 || $offset > 100000) {
    responder(['erro' => 'Dados inválidos.'], 422);
}
responder(posts_pagina($lado, $ordem, $offset, $perfil ?: null));
