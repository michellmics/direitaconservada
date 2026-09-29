<?php
// /robots.txt (o .htaccess e o router.php mandam para cá): libera o site para o Google, fecha o painel e as APIs
// e aponta o sitemap (endereço completo vem do APP_URL).
require __DIR__ . '/includes/env.php';
header('Content-Type: text/plain; charset=utf-8');
// Site numa subpasta (ex.: APP_URL=https://potepolitico.com.br/app): o Google só lê o robots.txt da RAIZ do domínio,
// então copie esta saída para o robots.txt da raiz (os caminhos já vêm com a subpasta).
$base = rtrim((string) env('APP_URL', 'http://localhost:8080'), '/');
$pasta = rtrim((string) parse_url($base, PHP_URL_PATH), '/');
echo "User-agent: *\nDisallow: $pasta/cozinha/\nDisallow: $pasta/api/\nDisallow: $pasta/sair\n\nSitemap: $base/sitemap.xml\n";
