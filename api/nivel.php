<?php
// Subiu de nível: POST JSON { lado, pontos: { compra, tempo, participacao } } → { nivel, avisado }
// Manda o e-mail "você subiu de nível" para quem está logado (uma vez por nível, ver tempero_registrar_nivel()).
//
// Quem já tem itens no banco tem o nível calculado aqui, pelo banco (os pontos enviados são ignorados).
// Enquanto compras e comentários do protótipo ficam só no navegador, vale o que o navegador calculou;
// no pior caso a pessoa só manda para si mesma no máximo um e-mail por nível.
require dirname(__DIR__) . '/includes/config.php';
require dirname(__DIR__) . '/includes/tempero.php';

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
limite_api('nivel', 10);
$lado = (string) ($in['lado'] ?? '');
if (!isset(SIDES[$lado]) || !is_array($in['pontos'] ?? null)) {
    responder(['erro' => 'Dados inválidos.'], 422);
}

require dirname(__DIR__) . '/includes/auth.php';
$usuario = current_user();
if (!$usuario) {
    responder(['erro' => 'Entre para receber o aviso por e-mail.', 'login' => true], 401);
}

try {
    $uid = (int) $usuario['id'];
    $fatos = tempero_fatos($uid, $lado);
    if ($fatos['reais'] > 0) {
        $pontos = tempero_pontos($fatos);
    } else {
        $parte = fn(string $k) => max(0, min(1000000, (int) ($in['pontos'][$k] ?? 0)));
        $pontos = ['compra' => $parte('compra'), 'tempo' => $parte('tempo'), 'participacao' => $parte('participacao')];
        $pontos['total'] = $pontos['compra'] + $pontos['tempo'] + $pontos['participacao'];
    }
    $avisado = tempero_registrar_nivel($uid, $lado, $pontos);
    responder(['nivel' => tempero_nivel($pontos), 'avisado' => $avisado]);
} catch (PDOException $ex) {
    responder(['erro' => 'Não foi possível registrar o nível agora.'], 503); // banco fora do ar ou migration 005 não rodada
}
