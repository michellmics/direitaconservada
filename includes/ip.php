<?php
// IP real do visitante atrás do Cloudflare. O site recebe as requisições pelo Cloudflare, então REMOTE_ADDR é
// um IP dele (todo mundo pareceria a mesma pessoa: rate limit, bloqueio do painel e logs quebrariam).
// Só confia no cabeçalho CF-Connecting-IP quando a requisição vem MESMO de um IP do Cloudflare — senão
// qualquer um forjaria o próprio IP mandando o cabeçalho direto para o servidor.
// Carregado pelo config.php: depois disso, $_SERVER['REMOTE_ADDR'] já é o IP do visitante.
// Faixas oficiais: https://www.cloudflare.com/ips/ (atualize se o Cloudflare publicar novas).

const CLOUDFLARE_IPS = [
    '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
    '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
    '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
    '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
];

/** O IP está dentro da faixa (CIDR)? Vale para IPv4 e IPv6. */
function ip_na_faixa(string $ip, string $faixa): bool
{
    [$rede, $bits] = explode('/', $faixa);
    $a = @inet_pton($ip);
    $b = @inet_pton($rede);
    if ($a === false || $b === false || strlen($a) !== strlen($b)) {
        return false;
    }
    $bits = (int) $bits;
    $bytes = intdiv($bits, 8);
    if (substr($a, 0, $bytes) !== substr($b, 0, $bytes)) {
        return false;
    }
    $resto = $bits % 8;
    if ($resto === 0) {
        return true;
    }
    $mascara = (0xFF << (8 - $resto)) & 0xFF;
    return (ord($a[$bytes]) & $mascara) === (ord($b[$bytes]) & $mascara);
}

function ip_do_cloudflare(string $ip): bool
{
    foreach (CLOUDFLARE_IPS as $faixa) {
        if (ip_na_faixa($ip, $faixa)) {
            return true;
        }
    }
    return false;
}

if (!defined('IP_REAL_AJUSTADO')) {
    define('IP_REAL_AJUSTADO', true);
    $origem = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $cf = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    if ($origem !== '' && $cf !== '' && filter_var($cf, FILTER_VALIDATE_IP) && ip_do_cloudflare($origem)) {
        $_SERVER['REMOTE_ADDR_PROXY'] = $origem; // o IP do Cloudflare que entregou a requisição
        $_SERVER['REMOTE_ADDR'] = $cf;
    }
}
