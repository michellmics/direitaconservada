<?php
// Página de um dos potes: /pote?c=… (lado cifrado; ver includes/rotas.php)
require __DIR__ . '/includes/config.php';
require __DIR__ . '/data/mock.php';
require __DIR__ . '/includes/tempero.php';
require __DIR__ . '/includes/comentarios.php';
require __DIR__ . '/includes/posts.php';

$P = rota_params(['lado']);
$S = side($P['lado'] ?? 'direita');

$items    = mock_items($S['slug']);
$ranking  = ranking_uf($items);
$today    = date('Y-m-d');
$hoje     = count(array_filter($items, fn($o) => $o['desde'] === $today));
$pageTitle = $S['name'];

// enquete no ar (do banco). Sem banco, o pote funciona normalmente, só sem enquete.
require_once __DIR__ . '/includes/enquetes.php';
require_once __DIR__ . '/includes/auth.php';
$extraJs = [
    'enquete'     => null,
    'tempero'     => tempero_mock(),
    'mapa'        => ['contagem' => contagem_por_uf(), 'reis' => reis_por_uf()],
    // comentários vêm de /api/comentarios, 10 por vez; aqui só as contagens e os provocadores do mês
    'comentarios' => ['contagem' => comentarios_contagem($S['slug']), 'provocadores' => comentarios_provocadores($S['slug'])],
    // mural: a 1ª página de 12 já vem na página; "Carregar mais" busca as próximas em /api/posts
    'feed'        => posts_pagina($S['slug']),
];
try {
    $usuario = current_user(); // pode renovar o cookie: antes de qualquer HTML
    if ($e = enquete_ativa($S['slug'])) {
        $extraJs['enquete'] = enquete_para_js($e, enquete_voto_de((int) $e['id'], $usuario ? (int) $usuario['id'] : null));
    }
} catch (Throwable $ex) {
    // banco fora do ar ou migration 002 não rodada
}

require __DIR__ . '/includes/header.php';
?>
<main>
  <?php require __DIR__ . '/partials/hero.php'; ?>
  <?php require __DIR__ . '/partials/mapa.php'; ?>
  <?php require __DIR__ . '/partials/ranking.php'; ?>
  <?php require __DIR__ . '/partials/mural.php'; ?>
  <section class="section poll-section" id="enquete" hidden>
    <div class="poll" id="poll"></div>
  </section>
  <?php require __DIR__ . '/partials/niveis.php'; ?>
  <?php require __DIR__ . '/partials/como-funciona.php'; ?>
</main>
<?php
require __DIR__ . '/partials/modals.php';
require __DIR__ . '/includes/footer.php';
