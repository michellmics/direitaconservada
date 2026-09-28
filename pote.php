<?php
// Página de um dos potes: pote.php?lado=direita | pote.php?lado=esquerda
require __DIR__ . '/includes/config.php';
require __DIR__ . '/data/mock.php';

$S = side($_GET['lado'] ?? 'direita');

$items    = mock_items($S['slug']);
$comments = mock_comments($S['slug'], $items);
$ranking  = ranking_uf($items);
$today    = date('Y-m-d');
$hoje     = count(array_filter($items, fn($o) => $o['desde'] === $today));
$pageTitle = $S['name'];

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
