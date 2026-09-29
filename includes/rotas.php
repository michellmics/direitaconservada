<?php
// URLs do site: rotas sem ".php" e parâmetros criptografados com a ENV_KEY do .env.
//
//   url('pote', ['lado' => 'direita'], 'mapa')   → direita#mapa (os potes têm endereço fixo, por SEO)
//   url('perfil', ['lado' => 'direita', 'id' => 1]) → perfil?c=<token>
//   rota_params(['lado'])                        → lê ?c=… da página atual
//
// O token é AES-256-GCM (chave = sha256 da ENV_KEY): ninguém lê nem altera os parâmetros.
// Links antigos com parâmetros abertos (pote.php?lado=direita) são redirecionados para a versão cifrada.
// As rotas sem ".php" vêm do .htaccess (Apache) ou do router.php (php -S).
require_once __DIR__ . '/env.php';

const URL_PARAM = 'c';

function url_chave(): string
{
    static $chave = null;
    if ($chave === null) {
        $env = (string) env('ENV_KEY', '');
        if ($env === '') {
            throw new RuntimeException('Defina ENV_KEY no .env (chave usada para criptografar os links).');
        }
        $chave = hash('sha256', 'dc-url|' . $env, true);
    }
    return $chave;
}

function url_cifrar(array $dados): string
{
    $iv = random_bytes(12);
    $tag = '';
    $cifrado = openssl_encrypt(json_encode($dados, JSON_UNESCAPED_UNICODE), 'aes-256-gcm', url_chave(), OPENSSL_RAW_DATA, $iv, $tag);
    return rtrim(strtr(base64_encode($iv . $tag . $cifrado), '+/', '-_'), '=');
}

/** Dados do token, ou null se for inválido/alterado. */
function url_decifrar(string $token): ?array
{
    if (!preg_match('/^[A-Za-z0-9_-]{40,2000}$/', $token)) {
        return null;
    }
    $bin = base64_decode(strtr($token, '-_', '+/'), true);
    if ($bin === false || strlen($bin) < 29) {
        return null;
    }
    $json = openssl_decrypt(substr($bin, 28), 'aes-256-gcm', url_chave(), OPENSSL_RAW_DATA, substr($bin, 0, 12), substr($bin, 12, 16));
    $dados = $json === false ? null : json_decode($json, true);
    return is_array($dados) ? $dados : null;
}

/** Link relativo à raiz do site: url('perfil', ['lado' => 'direita', 'id' => 42]). Rota '' = página inicial. */
function url(string $rota, array $params = [], string $ancora = ''): string
{
    // os potes têm endereço fixo e legível (/direita, /esquerda): é o que o Google indexa (SEO)
    if ($rota === 'pote' && array_keys($params) === ['lado'] && isset(SIDES[$params['lado']])) {
        return $params['lado'] . ($ancora !== '' ? '#' . $ancora : '');
    }
    $u = $rota === '' ? './' : $rota;
    if ($params) {
        $u .= '?' . URL_PARAM . '=' . url_cifrar($params);
    }
    return $u . ($ancora !== '' ? '#' . $ancora : '');
}

/** Link completo (e-mails): https://site/pote?c=… */
function url_absoluta(string $rota, array $params = [], string $ancora = ''): string
{
    $base = rtrim((string) env('APP_URL', 'http://localhost:8080'), '/') . '/';
    return $base . ltrim(url($rota, $params, $ancora), './');
}

/** Rota da página atual (pote, perfil, entrar…; '' = inicial). */
function rota_atual(): string
{
    $nome = basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
    return $nome === 'index' ? '' : $nome;
}

/**
 * Parâmetros da página, vindos do ?c=… cifrado.
 * $abertos = nomes aceitos em links antigos (?lado=…): num GET, redireciona para a mesma página com eles cifrados.
 */
function rota_params(array $abertos = []): array
{
    if (isset($_GET[URL_PARAM])) {
        return url_decifrar((string) $_GET[URL_PARAM]) ?? [];
    }
    $velhos = array_intersect_key($_GET, array_flip($abertos));
    if ($velhos && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        header('Location: ' . url(rota_atual(), $velhos), true, 302);
        exit;
    }
    return [];
}

/**
 * Links de perfil já cifrados para o JS (a chave nunca vai para o navegador):
 * os itens da página, os autores dos comentários, o outro pote (perfil) e quem manda nos estados (mapa).
 */
function links_para_js(string $lado, array $items, array $comments, array $extra = []): array
{
    $perfil = [];
    $add = function (string $s, $id) use (&$perfil) {
        $id = (int) $id;
        if ($id > 0 && !isset($perfil[$s][$id])) {
            $perfil[$s][$id] = url('perfil', ['lado' => $s, 'id' => $id]);
        }
    };
    foreach ($items as $o) {
        $add($lado, $o['id']);
    }
    foreach ($comments as $c) {
        $add($c['autor']['side'], $c['autor']['id'] ?? 0);
    }
    foreach ($extra['otherItems'] ?? [] as $o) {
        $add($o['side'], $o['id']);
    }
    foreach ($extra['presentes'] ?? [] as $o) { // presentes que quem está vendo deu (perfil próprio)
        $add($o['side'], $o['id']);
    }
    foreach ($extra['meus'] ?? [] as $s => $lista) { // os itens de quem está vendo, nos dois potes
        foreach ($lista as $o) {
            $add($s, $o['id']);
        }
    }
    foreach ($extra['mapa']['reis'] ?? [] as $s => $porUf) {
        foreach ($porUf as $lista) {
            foreach ($lista as $r) {
                $add($s, $r['id']);
            }
        }
    }
    $potes = [];
    foreach (array_keys(SIDES) as $s) {
        $potes[$s] = url('pote', ['lado' => $s]);
    }
    return ['pote' => $potes, 'perfil' => $perfil, 'meuPerfil' => url('perfil', ['lado' => $lado, 'meu' => 1])];
}

/**
 * SEO (Google): endereço oficial da página (canonical) + dados estruturados (JSON-LD).
 * Sem $canonica = página que não deve aparecer na busca (entrar, perfil, presente, avisos): noindex.
 */
function seo_tags(?string $canonica, array $jsonLd = []): string
{
    if ($canonica === null) {
        return '<meta name="robots" content="noindex, follow">';
    }
    $tags = ['<link rel="canonical" href="' . htmlspecialchars($canonica, ENT_QUOTES, 'UTF-8') . '">'];
    if ($jsonLd) {
        $tags[] = '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org'] + (array_is_list($jsonLd) ? ['@graph' => $jsonLd] : $jsonLd),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';
    }
    return implode("\n  ", $tags);
}

/** Endereço completo da página inicial (APP_URL com barra no fim). */
function url_base(): string
{
    return rtrim((string) env('APP_URL', 'http://localhost:8080'), '/') . '/';
}

/**
 * Tags de compartilhamento (WhatsApp, Facebook, X…): título, descrição e a imagem 1200×630 (assets/img/og-*.png).
 * Endereços absolutos (APP_URL): o WhatsApp não aceita caminho relativo. O ?v= força o WhatsApp a baixar de novo
 * quando a imagem muda (ele guarda em cache).
 */
function og_tags(string $titulo, string $descricao, string $imagem, ?string $canonica = null): string
{
    $base = rtrim((string) env('APP_URL', 'http://localhost:8080'), '/');
    $arq = dirname(__DIR__) . "/assets/img/$imagem";
    $img = "$base/assets/img/$imagem" . (is_file($arq) ? '?v=' . filemtime($arq) : '');
    $pagina = $canonica ?? $base . (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) . (isset($_GET[URL_PARAM]) ? '?' . URL_PARAM . '=' . rawurlencode((string) $_GET[URL_PARAM]) : '');
    $t = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    return implode("\n  ", [
        '<meta property="og:type" content="website">',
        '<meta property="og:site_name" content="' . $t(SITE_NAME) . '">',
        '<meta property="og:locale" content="pt_BR">',
        '<meta property="og:url" content="' . $t($pagina) . '">',
        '<meta property="og:title" content="' . $t($titulo) . '">',
        '<meta property="og:description" content="' . $t($descricao) . '">',
        '<meta property="og:image" content="' . $t($img) . '">',
        '<meta property="og:image:width" content="1200">',
        '<meta property="og:image:height" content="630">',
        '<meta property="og:image:alt" content="' . $t($titulo) . '">',
        '<meta name="twitter:card" content="summary_large_image">',
        '<meta name="twitter:image" content="' . $t($img) . '">',
    ]);
}

/**
 * Caminho de CSS/JS com ?v= = data do arquivo: mudou o arquivo, muda o endereço, e o navegador (e o celular)
 * baixa a versão nova em vez de usar a do cache. Não precisa mudar número à mão.
 */
function asset(string $caminho): string
{
    $arq = dirname(__DIR__) . '/' . $caminho;
    return $caminho . (is_file($arq) ? '?v=' . filemtime($arq) : '');
}

/**
 * App do painel (PWA "Cozinha", páginas em /cozinha/): manifest e ícones próprios, em outro tom, para diferenciar
 * do app do site no celular. O service worker (cozinha/sw.js) é registrado pelo assets/js/admin.js.
 */
function pwa_tags_admin(): string
{
    return implode("\n  ", [
        '<link rel="manifest" href="manifest.webmanifest">',
        '<meta name="theme-color" content="#121212">',
        '<link rel="icon" type="image/png" sizes="32x32" href="../' . asset('assets/img/admin/favicon-32.png') . '">',
        '<link rel="icon" type="image/png" sizes="192x192" href="../' . asset('assets/img/admin/icon-192.png') . '">',
        '<link rel="apple-touch-icon" href="../' . asset('assets/img/admin/apple-touch-icon.png') . '">',
        '<meta name="apple-mobile-web-app-capable" content="yes">',
        '<meta name="mobile-web-app-capable" content="yes">',
        '<meta name="apple-mobile-web-app-title" content="Cozinha">',
        '<meta name="apple-mobile-web-app-status-bar-style" content="black">',
        '<script src="../' . asset('assets/js/puxar.js') . '" defer></script>',
    ]);
}

/** App (PWA): manifest, ícones (PC, Android e iPhone), tags da Apple e o script que registra o service worker. */
function pwa_tags(): string
{
    return implode("\n  ", [
        '<link rel="manifest" href="manifest.webmanifest">',
        '<link rel="icon" type="image/png" sizes="32x32" href="' . asset('assets/img/favicon-32.png') . '">',
        '<link rel="icon" type="image/png" sizes="192x192" href="' . asset('assets/img/icon-192.png') . '">',
        '<link rel="apple-touch-icon" href="' . asset('assets/img/apple-touch-icon.png') . '">',
        '<meta name="apple-mobile-web-app-capable" content="yes">',
        '<meta name="mobile-web-app-capable" content="yes">',
        '<meta name="apple-mobile-web-app-title" content="Pote Político">',
        '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">',
        '<script src="' . asset('assets/js/pwa.js') . '" defer></script>',
        '<script src="' . asset('assets/js/puxar.js') . '" defer></script>', // puxar para atualizar (só no app)
    ]);
}
