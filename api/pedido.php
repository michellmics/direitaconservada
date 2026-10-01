<?php
// Pegar azeitona/pimenta (grátis; ver includes/pedidos.php):
//   POST JSON { acao: "criar", lado, email?, itens: [...] } → { pedido, itens, entrou }  (itens já ativos, no banco)
//   POST JSON { acao: "criar", lado, renovar: número }      → { renovado }               (renova na hora; precisa estar logado)
// E-mail que já tem conta (sem login): → { login: mensagem, entrar: tela para digitar o código enviado por e-mail }.
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
// só aceita chamadas do próprio site
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
    responder(['erro' => 'Origem não permitida.'], 403);
}

$bruto = file_get_contents('php://input', false, null, 0, 4_000_000); // fotos e selos vêm junto (já reduzidos)
$in = json_decode($bruto, true);
// rate limit por IP (includes/limite.php): geral das APIs + desta ação, por minuto
require_once dirname(__DIR__) . '/includes/limite.php';
limite_api('api', 150);
$acao = is_array($in) ? (string) ($in['acao'] ?? '') : '';

limite_api('pedido', 10); // criar itens (e mandar código de acesso) é caro: poucos por minuto
if ($acao !== 'criar') {
    responder(['erro' => 'Dados inválidos.'], 422);
}

try {
    $r = pedido_criar($in, current_user(), (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    responder($r, isset($r['erro']) ? 422 : 200);
} catch (Throwable $e) {
    logar('erro', 'sistema', 'erro_tratado', 'api/pedido: ' . $e->getMessage(), [], null, false, 500);
    responder(['erro' => 'Não conseguimos colocar no pote agora. Tente de novo em instantes.'], 500);
}
