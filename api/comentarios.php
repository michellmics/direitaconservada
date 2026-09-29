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
in_array($in['acao'] ?? '', ['comentar', 'apagar'], true) ? limite_api('comentar', 20) : limite_api('comentarios', 60);

// comentar e apagar: de quem está logado
//   { acao: "comentar", post, texto, video, cita: "direita-c40" | null } → { comentario }
//   { acao: "apagar", id: "direita-c40" }                               → { ok }
if (in_array($in['acao'] ?? '', ['comentar', 'apagar'], true)) {
    require dirname(__DIR__) . '/includes/mural.php';
    $u = current_user();
    if (!$u) {
        responder(['erro' => 'Entre na sua conta para comentar.', 'login' => true], 401);
    }
    try {
        $r = $in['acao'] === 'comentar'
            ? mural_comentar((int) $u['id'], (string) ($in['post'] ?? ''), (string) ($in['texto'] ?? ''), $in['video'] ?? null, isset($in['cita']) ? (string) $in['cita'] : null)
            : mural_apagar_comentario((int) $u['id'], (string) ($in['id'] ?? ''));
    } catch (PDOException $e) {
        logar('erro', 'sistema', 'erro_tratado', 'api/comentarios: ' . $e->getMessage(), [], null, false, 500);
        responder(['erro' => 'Não deu para salvar agora. Tente de novo.'], 503);
    }
    responder($r, isset($r['erro']) ? 422 : 200);
}

$post = (string) ($in['post'] ?? '');
$antes = isset($in['antes']) ? (string) $in['antes'] : null;
if (!preg_match('/^\w+-[op]\w+$/', $post) || ($antes !== null && !preg_match('/^\w+-c\d+$/', $antes))) {
    responder(['erro' => 'Dados inválidos.'], 422);
}
$pagina = comentarios_pagina($post, $antes);
// níveis e links de quem comentou (dos dois potes): o navegador não recebe os potes inteiros
require_once dirname(__DIR__) . '/includes/tempero.php';
require_once dirname(__DIR__) . '/includes/pote_js.php';
$pagina['extras'] = pote_extras_js(array_map(fn($c) => [$c['autor']['side'], $c['autor']['id']], $pagina['comentarios']));
responder($pagina);
