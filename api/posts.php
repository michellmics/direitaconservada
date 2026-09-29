<?php
// Posts do mural, 12 por vez: POST JSON { lado, ordem: recentes|top|debate, offset, perfil: número|null }
// perfil = uma azeitona da pessoa: vêm os posts de todas as azeitonas dela (no máximo as 20 últimas)
// → { posts: [...], mais: true/false, extras: { itens, fatos, donos, links } dos autores }. offset = quantos posts do servidor já estão na tela.
require dirname(__DIR__) . '/includes/config.php';
require dirname(__DIR__) . '/data/mock.php';
require dirname(__DIR__) . '/includes/comentarios.php';
require dirname(__DIR__) . '/includes/posts.php';

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
in_array($in['acao'] ?? '', ['publicar', 'curtir'], true) ? limite_api('posts-acao', 30) : limite_api('posts', 60);

// publicar e curtir: de quem está logado
//   { acao: "publicar", lado, texto, video }  → { post }
//   { acao: "curtir", post: "direita-o12", curtir: true|false } → { curtido, likes }
if (in_array($in['acao'] ?? '', ['publicar', 'curtir'], true)) {
    require dirname(__DIR__) . '/includes/mural.php';
    $u = current_user();
    if (!$u) {
        responder(['erro' => 'Entre na sua conta para isso.', 'login' => true], 401);
    }
    try {
        $r = $in['acao'] === 'publicar'
            ? (isset(SIDES[$in['lado'] ?? '']) ? mural_publicar((int) $u['id'], $in['lado'], (string) ($in['texto'] ?? ''), $in['video'] ?? null) : ['erro' => 'Pote inválido.'])
            : mural_curtir((int) $u['id'], (string) ($in['post'] ?? ''), !empty($in['curtir']));
    } catch (PDOException $e) {
        logar('erro', 'sistema', 'erro_tratado', 'api/posts: ' . $e->getMessage(), [], null, false, 500);
        responder(['erro' => 'Não deu para salvar agora. Tente de novo.'], 503);
    }
    responder($r, isset($r['erro']) ? 422 : 200);
}

$lado = (string) ($in['lado'] ?? '');
$ordem = (string) ($in['ordem'] ?? 'recentes');
$offset = (int) ($in['offset'] ?? 0);
$perfil = isset($in['perfil']) ? (int) $in['perfil'] : null;
if (!isset(SIDES[$lado]) || !in_array($ordem, POSTS_ORDENS, true) || $offset < 0 || $offset > 100000) {
    responder(['erro' => 'Dados inválidos.'], 422);
}
$pagina = posts_pagina($lado, $ordem, $offset, $perfil ? banco_pessoa_numeros($lado, $perfil) : null);
// os autores desta página (item, nível e link do perfil): o navegador não recebe o pote inteiro
require_once dirname(__DIR__) . '/includes/tempero.php';
require_once dirname(__DIR__) . '/includes/pote_js.php';
$pagina['extras'] = pote_extras_js(array_map(fn($p) => [$lado, $p['oliveId']], $pagina['posts']));
responder($pagina);
