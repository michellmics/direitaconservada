<?php
// Entrada: a pessoa escolhe o lado e cai num dos potes
require __DIR__ . '/includes/config.php';
require __DIR__ . '/data/mock.php';

$order = ['esquerda', 'direita']; // esquerda à esquerda, direita à direita
$count = [];
foreach ($order as $slug) {
    $count[$slug] = count(mock_items($slug));
}
$total = array_sum($count);
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Escolha seu pote</title>
  <meta name="description" content="Direita Conservada ou Pimenta da Resistência? Escolha seu pote, garanta seu lugar e debata com o outro lado.">
  <meta property="og:title" content="<?= e(SITE_NAME) ?>">
  <meta property="og:description" content="Escolha seu pote: azeitona conservada ou pimenta da resistência.">
  <meta name="theme-color" content="#120e0a">
  <link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">🫙</text></svg>') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="choose-page">
  <?php require __DIR__ . '/partials/sprites.php'; ?>

  <main class="choose">
    <header class="choose-head">
      <span class="tag">Dois potes, um debate</span>
      <h1>Escolha seu pote</h1>
      <p>Cada lado tem seu pote, seu mural e seu certificado. E quem está num pote pode comentar no outro.</p>
    </header>

    <div class="choose-sides">
      <?php foreach ($order as $slug):
          $S = side($slug);
          $jarItems = array_slice(mock_items($slug), 0, 110); ?>
        <a class="side-card side-<?= $slug ?>" href="pote.php?lado=<?= $slug ?>" style="<?= theme_vars($S) ?>">
          <div class="side-jar"><?php require __DIR__ . '/partials/jar.php'; ?></div>
          <div class="side-info">
            <span class="tag"><?= e($S['tag']) ?></span>
            <h2><?= e($S['name_a']) ?> <em><?= e($S['name_b']) ?></em></h2>
            <p class="side-motto"><?= strip_tags($S['h1']) ?></p>
            <span class="side-count"><b><?= num($count[$slug]) ?></b> <?= e($S['items']) ?> no pote</span>
            <span class="btn btn-gold">Entrar no pote <?= $S['emoji'] ?></span>
          </div>
        </a>
      <?php endforeach; ?>
      <div class="versus" aria-hidden="true">VS</div>
    </div>

    <section class="scoreboard" aria-label="Placar dos potes">
      <div class="score-side" style="<?= theme_vars(side('esquerda')) ?>"><?= side('esquerda')['emoji'] ?> <b><?= num($count['esquerda']) ?></b></div>
      <div class="score-bar">
        <i style="width: <?= round($count['esquerda'] / $total * 100, 1) ?>%; background: <?= side('esquerda')['theme']['gold'] ?>"></i>
        <i style="background: <?= side('direita')['theme']['olive'] ?>"></i>
      </div>
      <div class="score-side" style="<?= theme_vars(side('direita')) ?>"><b><?= num($count['direita']) ?></b> <?= side('direita')['emoji'] ?></div>
      <small>Placar dos potes · capacidade de <?= num(JAR_CAPACITY) ?> em cada um</small>
    </section>
  </main>

  <footer class="footer">
    <p>Protótipo visual — nenhum pagamento é real.</p>
  </footer>
</body>
</html>
