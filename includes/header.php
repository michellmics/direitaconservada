<?php
/** Espera $S (lado atual) e $pageTitle. */
$O = side($S['other']);
?>
<!doctype html>
<html lang="pt-BR" style="<?= theme_vars($S) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="<?= e($S['og']) ?>">
  <meta property="og:title" content="<?= e($S['name']) ?>">
  <meta property="og:description" content="<?= e($S['og']) ?>">
  <meta name="theme-color" content="<?= $S['theme']['bg'] ?>">
  <link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">' . $S['emoji'] . '</text></svg>') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="lado-<?= $S['slug'] ?>">
  <?php require __DIR__ . '/../partials/sprites.php'; ?>
  <header class="topbar">
    <a class="brand" href="./" title="Trocar de pote">
      <?= item_svg($S, array_key_first($S['types']), 'brand-icon') ?>
      <span><?= e($S['name_a']) ?> <b><?= e($S['name_b']) ?></b></span>
    </a>
    <nav>
      <a href="#pote">O Pote</a>
      <a href="#mural">Mural</a>
      <a href="#ranking">Ranking</a>
      <a href="#como">Como funciona</a>
    </nav>
    <a class="side-switch" href="pote.php?lado=<?= $O['slug'] ?>" style="<?= theme_vars($O) ?>" title="Ir para <?= e($O['name']) ?>">
      <?= item_svg($O, array_key_first($O['types']), 'side-switch-icon') ?><span>Espiar o outro pote</span>
    </a>
    <button class="btn btn-gold btn-sm" data-open-buy>Quero minha <?= e($S['item']) ?></button>
  </header>
