<?php
// Rotas sem ".php" no servidor embutido do PHP (em produção, o .htaccess faz o mesmo):
//
//   php -S localhost:8080 router.php
//
//   /             → index.php        /pote?c=…    → pote.php
//   /cozinha/     → cozinha/index.php (painel)  /api/nivel   → api/nivel.php
//   /pote.php?…   → redireciona para /pote?…
// Pastas internas (includes, data, database, partials, vendor) e arquivos ocultos (.env) não são servidos.
$raiz = __DIR__;
$caminho = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$query = $_SERVER['QUERY_STRING'] ?? '';

$naoEncontrado = function () {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Página não encontrada.';
    return true;
};

if (str_contains($caminho, '..') || preg_match('#^/(includes|data|database|partials|vendor)(/|$)|/\.|^/router\.php$|^/(README\.md|composer\.(json|lock))$#i', $caminho)
    || (preg_match('#^/uploads(/|$)#i', $caminho) && !preg_match('#^/uploads/pedidos/[a-f0-9]{24}\.(jpg|png)$#', $caminho))) { // uploads: só imagem
    return $naoEncontrado();
}

// quem pede ".php" vai para a rota (só GET; formulários já apontam para as rotas)
if (preg_match('#^(.*?)(/index)?\.php$#', $caminho, $m) && is_file($raiz . $caminho)) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $destino = ($m[2] ? $m[1] . '/' : $m[1]) ?: '/';
        header('Location: ' . $destino . ($query !== '' ? '?' . $query : ''), true, 301);
        return true;
    }
    $script = $caminho;
} elseif (is_file($raiz . $caminho)) {
    // No Windows o "php -S" corta arquivos estáticos acima de ~64 KB (app.js e style.css passam disso):
    // texto sai pelo PHP comprimido (gzip), bem abaixo do limite. Imagens e o resto: servidor embutido direto.
    $tipos = ['css' => 'text/css', 'js' => 'text/javascript', 'svg' => 'image/svg+xml', 'json' => 'application/json'];
    $ext = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));
    if (!isset($tipos[$ext])) {
        return false;
    }
    header('Content-Type: ' . $tipos[$ext] . '; charset=utf-8');
    header('Cache-Control: no-cache'); // sempre confere a versão nova (é o ambiente de desenvolvimento)
    ini_set('zlib.output_compression', '1');
    readfile($raiz . $caminho);
    return true;
} elseif (is_dir($raiz . $caminho) && !str_ends_with($caminho, '/')) {
    header('Location: ' . $caminho . '/' . ($query !== '' ? '?' . $query : ''), true, 301); // /cozinha → /cozinha/
    return true;
} else {
    $script = str_ends_with($caminho, '/') ? $caminho . 'index.php' : $caminho . '.php';
}

if (!is_file($raiz . $script)) {
    return $naoEncontrado();
}

// a página enxerga o próprio nome (header.php usa SCRIPT_NAME para saber em que página está)
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = $script;
$_SERVER['SCRIPT_FILENAME'] = $raiz . $script;
chdir(dirname($raiz . $script));
require $raiz . $script;
return true;
