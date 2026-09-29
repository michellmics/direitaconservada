<?php
// Entrada: a pessoa escolhe o lado e cai num dos potes
require __DIR__ . '/includes/config.php';
require __DIR__ . '/data/mock.php';
require __DIR__ . '/includes/tempero.php';
require __DIR__ . '/includes/pote_js.php';

$order = ['esquerda', 'direita']; // esquerda à esquerda, direita à direita
$count = [];
foreach ($order as $slug) {
  $count[$slug] = pote_totais($slug)['total'];
}
$total = array_sum($count);
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Escolha seu pote · <?= e(SITE_NAME) ?></title>
  <meta name="description" content="Direita Conservada ou Pimenta da Resistência? Escolha seu pote, garanta seu lugar e debata com o outro lado.">
  <?= og_tags('Escolha seu pote · ' . SITE_NAME, 'Direita Conservada ou Pimenta da Resistência? Entre no pote e debata com o outro lado.', 'og-inicio.png') ?>
  <meta name="theme-color" content="#120e0a">
  <?= pwa_tags() ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
  <script src="<?= asset('assets/js/pote-agito.js') ?>" defer></script>
</head>

<body class="choose-page">
  <?php require __DIR__ . '/partials/sprites.php'; ?>

  <main class="choose">
    <header class="choose-head">
      <span class="tag">Dois potes, um debate</span>
      <h1>Escolha seu pote</h1>
      <p>Esquerdista zoa a azeitona, tio do zap infarta com a pimenta e o grupo da família vira terra arrasada.</p>
      <?php require_once __DIR__ . '/includes/frases.php';
      if ($fraseEntrada = frase('entrada', null)): ?><p class="piada entrada-piada">“<?= e($fraseEntrada) ?>”</p><?php endif; ?>
    </header>

    <div class="choose-sides">
      <?php foreach ($order as $slug):
        $S = side($slug);
        $jarItems = pote_itens_sql($slug, '1', [], 'ORDER BY i.numero LIMIT 110'); ?>
        <a class="side-card side-<?= $slug ?>" href="<?= e(url('pote', ['lado' => $slug])) ?>" style="<?= theme_vars($S) ?>">
          <div class="side-jar"><?php require __DIR__ . '/partials/jar.php'; ?></div>
          <div class="side-info">
            <span class="tag"><?= e($S['tag']) ?></span>
            <h2><?= e($S['name_a']) ?> <em><?= e($S['name_b']) ?></em></h2>
            <p class="side-motto"><?= strip_tags(str_replace('<br>', ' ', $S['h1'])) ?></p>
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
    <button type="button" class="pwa-instalar" data-instalar-app hidden>📲 Instalar o app</button>
    <p>© <?= date('Y') ?> <?= e(SITE_NAME) ?></p>
  </footer>
  <script>
    // Feedback ao escolher o pote: o card escolhido "mergulha", o outro some e o botão mostra "Entrando…"
    (() => {
      const lados = document.querySelector('.choose-sides');
      document.querySelectorAll('.side-card').forEach((card) => {
        card.addEventListener('click', (e) => {
          if (e.defaultPrevented || e.ctrlKey || e.metaKey || e.shiftKey || e.button > 0) return;
          if (lados.classList.contains('entrando')) { e.preventDefault(); return; }
          lados.classList.add('entrando');
          card.classList.add('escolhido');
          const btn = card.querySelector('.btn');
          if (btn) btn.innerHTML = '<i class="spin" aria-hidden="true"></i> Entrando no pote…';
        });
      });
      // voltar pelo navegador (cache de página) não pode deixar a tela "presa" na animação
      window.addEventListener('pageshow', (e) => {
        if (!e.persisted) return;
        location.reload();
      });
    })();
  </script>
</body>

</html>