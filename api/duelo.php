<?php
// Tretódromo (includes/duelos.php). POST JSON { acao, … } — tudo exige estar logado:
//   { acao: 'buscar', lado, q }                          → { itens: [...] }  oponentes do pote `lado`
//   { acao: 'desafiar', meu, oponente, tema, argumento } → { duelo: id }
//   { acao: 'aceitar' | 'recusar', duelo }               → { ok }
//   { acao: 'argumentar', duelo, texto }                 → { ok }
//   { acao: 'votar', duelo, voto: 'a' | 'b' }            → { ok, votos: { votos_a, votos_b } }
require dirname(__DIR__) . '/includes/config.php';
require dirname(__DIR__) . '/includes/duelos.php';

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

$in = json_decode(file_get_contents('php://input'), true);
$in = is_array($in) ? $in : [];
$acao = (string) ($in['acao'] ?? '');
require_once dirname(__DIR__) . '/includes/limite.php';
limite_api('api', 150);
limite_api($acao === 'buscar' ? 'duelo-busca' : 'duelo', $acao === 'buscar' ? 60 : 20);

require dirname(__DIR__) . '/includes/auth.php';
$u = current_user();
if (!$u) {
    responder(['erro' => 'Entre na sua conta para participar do Tretódromo.', 'login' => true], 401);
}
$uid = (int) $u['id'];
$duelo = (int) ($in['duelo'] ?? 0);

try {
    $r = match ($acao) {
        'buscar'     => ['itens' => isset(SIDES[$in['lado'] ?? '']) ? duelo_buscar_oponentes($in['lado'], (string) ($in['q'] ?? ''), $uid) : []],
        'desafiar'   => duelo_criar($uid, (int) ($in['meu'] ?? 0), (int) ($in['oponente'] ?? 0), (string) ($in['tema'] ?? ''), (string) ($in['argumento'] ?? '')),
        'aceitar'    => duelo_responder_desafio($uid, $duelo, true),
        'recusar'    => duelo_responder_desafio($uid, $duelo, false),
        'argumentar' => duelo_argumentar($uid, $duelo, (string) ($in['texto'] ?? '')),
        'votar'      => duelo_votar($uid, $duelo, (string) ($in['voto'] ?? '')),
        default      => ['erro' => 'Ação inválida.'],
    };
} catch (PDOException $e) {
    logar('erro', 'sistema', 'erro_tratado', 'api/duelo: ' . $e->getMessage(), ['acao' => $acao], $uid, false, 500);
    responder(['erro' => 'O Tretódromo está indisponível agora. Tente de novo em instantes.'], 503);
}
responder($r, isset($r['erro']) ? 422 : 200);
