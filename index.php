<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/data/mock.php';

$olives  = mock_olives();
$ranking = ranking_uf($olives);
$today   = date('Y-m-d');
$hoje    = count(array_filter($olives, fn($o) => $o['desde'] === $today));

require __DIR__ . '/includes/header.php';
?>
<main>
  <?php require __DIR__ . '/partials/hero.php'; ?>
  <?php require __DIR__ . '/partials/mural.php'; ?>
  <?php require __DIR__ . '/partials/ranking.php'; ?>
  <?php require __DIR__ . '/partials/como-funciona.php'; ?>
</main>
<?php
require __DIR__ . '/partials/modals.php';
require __DIR__ . '/includes/footer.php';
