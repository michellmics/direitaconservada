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

// enquete no ar (do banco). Sem banco, o pote funciona normalmente, só sem enquete.
require_once __DIR__ . '/includes/enquetes.php';
$extraJs = ['enquete' => null];
try {
    $votante = votante_id(); // cookie: precisa vir antes de qualquer HTML
    if ($e = enquete_ativa($S['slug'])) {
        $extraJs['enquete'] = enquete_para_js($e, enquete_voto_de((int) $e['id'], $votante));
    }
} catch (Throwable $ex) {
    // banco fora do ar ou migration 002 não rodada
}

require __DIR__ . '/includes/header.php';
?>
<main>
  <?php require __DIR__ . '/partials/hero.php'; ?>
  <section class="section poll-section" id="enquete" hidden>
    <div class="poll" id="poll"></div>
  </section>
  <?php require __DIR__ . '/partials/mural.php'; ?>
  <?php require __DIR__ . '/partials/ranking.php'; ?>
  <?php require __DIR__ . '/partials/como-funciona.php'; ?>
</main>
<?php
require __DIR__ . '/partials/modals.php';
require __DIR__ . '/includes/footer.php';
