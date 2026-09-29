<?php
// Página de erro amigável: pagina_erro(404) ou pagina_erro(500). Mostra o recado e o botão para a página inicial.
// Quem chama: erro.php (URL que não existe: .htaccess / router.php), o tratamento de exceção do log.php (500).
// Funciona em qualquer profundidade de URL (/app/a/b/c): o <base> aponta para a raiz do site (caminho do APP_URL).
require_once __DIR__ . '/env.php';

function pagina_erro(int $codigo = 404): never
{
    $codigo = in_array($codigo, [403, 404, 500], true) ? $codigo : 404;
    if (!headers_sent()) {
        http_response_code($codigo);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
    }
    $raiz = rtrim((string) parse_url((string) env('APP_URL', ''), PHP_URL_PATH), '/') . '/';
    [$emoji, $titulo, $texto] = match ($codigo) {
        500 => ['🫙💥', 'Deu ruim na cozinha', 'Alguma coisa quebrou do nosso lado. Já ficamos sabendo e vamos arrumar. Tente de novo em instantes.'],
        403 => ['🔒', 'Acesso proibido', 'Essa parte do pote é só para a cozinha. Volte para o salão.'],
        default => ['🫙', 'Esse pote está vazio', 'A página que você procurou não existe, mudou de lugar ou alguém comeu. Nem azeitona, nem pimenta por aqui.'],
    };
    $esc = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $css = is_file($arq = dirname(__DIR__) . '/assets/css/style.css') ? 'assets/css/style.css?v=' . filemtime($arq) : 'assets/css/style.css';
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <base href="<?= $esc($raiz) ?>">
  <title><?= $codigo ?> · <?= $esc($titulo) ?> · Pote Político</title>
  <meta name="theme-color" content="#120e0a">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $esc($css) ?>">
</head>
<body class="choose-page erro-page">
  <main class="erro">
    <div class="erro-pote" aria-hidden="true"><span><?= $emoji ?></span></div>
    <p class="erro-codigo">Erro <?= $codigo ?></p>
    <h1><?= $esc($titulo) ?></h1>
    <p class="erro-texto"><?= $esc($texto) ?></p>
    <div class="erro-acoes">
      <a class="btn btn-gold" href="./">🏠 Ir para a página inicial</a>
      <?php if ($codigo === 500): ?>
        <a class="btn btn-ghost" href="javascript:location.reload()">↻ Tentar de novo</a>
      <?php else: ?>
        <a class="btn btn-ghost" href="direita">🫒 Direita</a>
        <a class="btn btn-ghost" href="esquerda">🌶️ Esquerda</a>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
<?php
    exit;
}
