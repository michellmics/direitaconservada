<?php
/** Espera $S (lado atual) e $pageTitle. */
require_once __DIR__ . '/auth.php';
$O = side($S['other']);
$U = current_user();
$paginaAtual = basename($_SERVER['SCRIPT_NAME']) . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
$comCompra = in_array(basename($_SERVER['SCRIPT_NAME']), ['pote.php', 'perfil.php'], true); // páginas com o modal de compra
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
    <?php $home = basename($_SERVER['SCRIPT_NAME']) === 'pote.php' ? '' : 'pote.php?lado=' . $S['slug']; ?>
    <nav>
      <a href="<?= $home ?>#pote">O Pote</a>
      <a href="<?= $home ?>#como">Como funciona</a>
    </nav>
    <a class="my-profile" id="my-profile" hidden title="Meu perfil"></a>
    <a class="side-switch" href="pote.php?lado=<?= $O['slug'] ?>" style="<?= theme_vars($O) ?>" title="Ir para <?= e($O['name']) ?>">
      <?= item_svg($O, array_key_first($O['types']), 'side-switch-icon') ?><span>Espiar o outro pote</span>
    </a>
    <?php if ($U): ?>
      <details class="user-menu">
        <summary title="<?= e($U['email']) ?>">
          <span class="user-initial avatar-<?= $S['slug'] ?>-<?= e(array_key_first($S['types'])) ?>"><?= e(mb_strtoupper(mb_substr(nome_proprio($U['nome']), 0, 1))) ?></span>
          <span class="user-name"><?= e(explode(' ', nome_proprio($U['nome']))[0]) ?></span>
        </summary>
        <div class="user-menu-box">
          <p><b><?= e(nome_proprio($U['nome'])) ?></b><small><?= e($U['email']) ?></small></p>
          <form method="post" action="sair.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_publico()) ?>">
            <input type="hidden" name="r" value="<?= e(destino_seguro($paginaAtual)) ?>">
            <button class="btn btn-ghost btn-sm btn-block">Sair</button>
          </form>
        </div>
      </details>
    <?php elseif (basename($_SERVER['SCRIPT_NAME']) !== 'entrar.php'): ?>
      <a class="login-link" href="entrar.php?lado=<?= $S['slug'] ?>&r=<?= urlencode(destino_seguro($paginaAtual)) ?>">Entrar</a>
    <?php endif; ?>
    <?php if ($comCompra): ?>
      <button class="btn btn-gold btn-sm" data-open-buy>Quero minha <?= e($S['item']) ?></button>
    <?php else: ?>
      <a class="btn btn-gold btn-sm" href="pote.php?lado=<?= $S['slug'] ?>">Quero minha <?= e($S['item']) ?></a>
    <?php endif; ?>
  </header>
