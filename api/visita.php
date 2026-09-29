<?php
// Contador de visitas (migration 024). Chamado pelo assets/js/visitas.js em todas as páginas públicas:
//   POST { t: 'ver',  p: '/pote', l: 'direita', r: document.referrer, u: utm_source }  → conta 1 página vista
//   POST { t: 'ping', p, l }  → "ainda estou aqui" (a cada ~30 s, com a aba visível) — alimenta o "online agora"
//   POST { t: 'sai' }         → fechou/saiu da página (sendBeacon)
// O visitante é um cookie anônimo (dc_vid); no banco vai só o hash dele. Robôs e quem usa o painel não contam.
// Não passa pelo log_api(): cada página vista viraria uma linha na tabela logs.
require dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/limite.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function fim(int $status = 204): never
{
    http_response_code($status);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fim(405);
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== $host) {
    fim(403);
}
$ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|headless|lighthouse|curl|wget|python|monitor/i', $ua)
    || isset($_COOKIE['dc_admin'])) { // painel aberto neste navegador: é o dono olhando, não conta
    fim();
}
limite_api('visita', 120); // folgado: muita gente de celular sai pelo mesmo IP da operadora

$in = json_decode((string) file_get_contents('php://input'), true);
$tipo = is_array($in) ? (string) ($in['t'] ?? '') : '';
if (!in_array($tipo, ['ver', 'ping', 'sai'], true)) {
    fim(422);
}

// visitante anônimo: cookie aleatório de 2 anos; sem cookie = primeira visita deste navegador
$vid = (string) ($_COOKIE['dc_vid'] ?? '');
$novo = !preg_match('/^[a-f0-9]{32}$/', $vid);
if ($novo) {
    if ($tipo !== 'ver') {
        fim();
    }
    $vid = bin2hex(random_bytes(16));
    setcookie('dc_vid', $vid, ['expires' => time() + 730 * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
}
$visitante = md5('dc-visita|' . $vid);

// página: só rotas conhecidas (nada de texto livre no banco), com o pote quando houver
$rota = trim((string) ($in['p'] ?? ''), '/');
$lado = (string) ($in['l'] ?? '');
if (isset(SIDES[$rota])) { // /direita, /esquerda = página do pote
    [$lado, $rota] = [$rota, 'pote'];
}
$rota = in_array($rota, ['pote', 'perfil', 'presente', 'avisos', 'entrar'], true) ? $rota : 'inicio';
$pagina = $rota . (isset(SIDES[$lado]) ? ' · ' . $lado : '');

$dispositivo = preg_match('/ipad|tablet|kindle|silk|playbook|(android(?!.*mobile))/i', $ua) ? 'tablet'
    : (preg_match('/mobi|iphone|ipod|android|blackberry|opera mini|iemobile/i', $ua) ? 'celular' : 'computador');

try {
    $pdo = db();
    if ($tipo === 'sai') {
        $pdo->prepare('DELETE FROM visitas_online WHERE visitante = ?')->execute([$visitante]);
        fim();
    }
    if ($tipo === 'ver') {
        // de onde veio: utm_source do link, o site que mandou (referrer) ou "direto"; navegação dentro do site = "interno"
        $utm = strtolower(preg_replace('/[^\w.-]/', '', (string) ($in['u'] ?? '')));
        $ref = strtolower((string) parse_url((string) ($in['r'] ?? ''), PHP_URL_HOST));
        $ref = preg_replace('/^(www\.|m\.|l\.|lm\.)/', '', $ref);
        $origem = $utm !== '' ? $utm : ($ref === '' ? 'direto' : ($ref === preg_replace('/^www\./', '', (string) $host) ? 'interno' : $ref));
        $pdo->prepare('INSERT INTO visitas (visitante, novo, pagina, origem, dispositivo) VALUES (?, ?, ?, ?, ?)')
            ->execute([$visitante, (int) $novo, $pagina, mb_substr($origem, 0, 80), $dispositivo]);
    }
    $pdo->prepare('INSERT INTO visitas_online (visitante, pagina, dispositivo) VALUES (?, ?, ?)
                   ON DUPLICATE KEY UPDATE entrou_em = IF(visto_em < NOW() - INTERVAL 30 MINUTE, NOW(), entrou_em), -- antes do visto_em (o MySQL aplica em ordem)
                                           visto_em = NOW(), pagina = VALUES(pagina), dispositivo = VALUES(dispositivo)')
        ->execute([$visitante, $pagina, $dispositivo]);
    if (random_int(1, 200) === 1) {
        $pdo->exec('DELETE FROM visitas_online WHERE visto_em < NOW() - INTERVAL 1 DAY');
    }
} catch (PDOException $ex) {
    fim(503); // banco fora do ar ou migration 024 não rodada: o site segue normal
}
fim();
