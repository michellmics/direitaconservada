<?php
/** Espera $S (lado atual) e $pageTitle. */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/frases.php'; // frases engraçadas do mural, compra e rodapé
$O = side($S['other']);
$U = current_user();
// página atual (rota + ?c=… cifrado): para onde voltar depois de entrar/sair
$paginaAtual = rota_atual() . (isset($_GET[URL_PARAM]) ? '?' . URL_PARAM . '=' . $_GET[URL_PARAM] : '');
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
  <script src="assets/js/pote-agito.js" defer></script>
</head>
<body class="lado-<?= $S['slug'] ?>">
  <?php require __DIR__ . '/../partials/sprites.php'; ?>
  <header class="topbar">
    <?php $home = basename($_SERVER['SCRIPT_NAME']) === 'pote.php' ? '' : e(url('pote', ['lado' => $S['slug']])); ?>
    <?php // celular: os links do topo vão para o menu sanduíche (no computador ele some e o <nav> aparece) ?>
    <details class="topo-menu menu-sanduiche">
      <summary aria-label="Menu"><span></span><span></span><span></span></summary>
      <div class="topo-menu-box">
        <a href="<?= $home ?>#pote">O Pote</a>
        <a href="<?= $home ?>#mural">Mural</a>
        <a href="<?= $home ?>#como">Como funciona</a>
      </div>
    </details>
    <a class="brand" href="./" title="Trocar de pote">
      <?= item_svg($S, $S['logo'], 'brand-icon') ?>
      <span><?= e($S['name_a']) ?> <b><?= e($S['name_b']) ?></b></span>
    </a>
    <nav>
      <a href="<?= $home ?>#pote">O Pote</a>
      <a href="<?= $home ?>#mural">Mural</a>
      <a href="<?= $home ?>#como">Como funciona</a>
    </nav>
    <a class="side-switch" href="<?= e(url('pote', ['lado' => $O['slug']])) ?>" style="<?= theme_vars($O) ?>" title="Ir para <?= e($O['name']) ?>">
      <?= item_svg($O, $O['logo'], 'side-switch-icon') ?><span>Espiar o outro pote</span>
    </a>
    <?php // canto direito: "Entrar" ou, logado, "Meu perfil" (menu com o perfil e o Sair) ?>
    <?php if ($U): ?>
      <details class="topo-menu user-menu">
        <summary title="Meu perfil">
          <?php // só a foto (o JS troca a inicial pela foto do item, quando houver) ?>
          <span class="user-initial avatar-<?= $S['slug'] ?>-<?= e($S['logo']) ?>"><?= e(mb_strtoupper(mb_substr(nome_proprio($U['nome']), 0, 1))) ?></span>
          <span class="sr-only">Meu perfil</span>
        </summary>
        <div class="user-menu-box">
          <p><b><?= e(nome_proprio($U['nome'])) ?></b><small><?= e($U['email']) ?></small></p>
          <a class="btn btn-gold btn-sm btn-block" id="my-profile-menu" href="<?= e(url('perfil', ['lado' => $S['slug'], 'meu' => 1])) ?>">Ver meu perfil</a>
          <form method="post" action="sair">
            <input type="hidden" name="csrf" value="<?= e(csrf_publico()) ?>">
            <input type="hidden" name="r" value="<?= e(destino_seguro($paginaAtual)) ?>">
            <button class="btn btn-ghost btn-sm btn-block">Sair</button>
          </form>
        </div>
      </details>
    <?php elseif (basename($_SERVER['SCRIPT_NAME']) !== 'entrar.php'): ?>
      <a class="login-link" href="<?= e(url('entrar', ['lado' => $S['slug'], 'r' => destino_seguro($paginaAtual)])) ?>">Entrar</a>
    <?php endif; ?>
  </header>
