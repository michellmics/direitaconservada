<?php
// URLs do site: rotas sem ".php" e parâmetros criptografados com a ENV_KEY do .env.
//
//   url('pote', ['lado' => 'direita'], 'mapa')   → pote?c=<token>#mapa
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
