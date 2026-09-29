<?php
// Página de um dos potes: /pote?c=… (lado cifrado; ver includes/rotas.php)
require __DIR__ . '/includes/config.php';
require __DIR__ . '/data/mock.php';
require __DIR__ . '/includes/tempero.php';
require __DIR__ . '/includes/comentarios.php';
require __DIR__ . '/includes/posts.php';
require __DIR__ . '/includes/pote_js.php';

$P = rota_params(['lado']);
$S = side($P['lado'] ?? 'direita');

// contas com o pote inteiro ficam aqui no servidor; para o navegador vai só o vidro + quem aparece na tela
$totais   = pote_totais($S['slug']);
$ranking  = ranking_uf($S['slug']);
$hoje     = $totais['hoje'];
$feed     = posts_pagina($S['slug']);
$provoc   = comentarios_provocadores($S['slug']);
$mapa     = ['contagem' => contagem_por_uf(), 'reis' => reis_por_uf()];
$items    = pote_itens_js($S['slug'], array_merge(array_column($feed['posts'], 'oliveId'), array_column($provoc, 'id')));
// níveis só de quem vai para a tela: os itens enviados e quem manda nos estados (os dois potes)
$comNivel = [$S['slug'] => array_column($items, 'id')];
foreach ($mapa['reis'] as $s => $porUf) {
    foreach ($porUf as $lista) {
        foreach ($lista as $r) {
            $comNivel[$s][] = $r['id'];
        }
    }
}
foreach (banco_meus_itens((int) (current_user()['id'] ?? 0)) as $s => $lista) { // o nível de quem está vendo
    foreach ($lista as $o) {
        $comNivel[$s][] = $o['id'];
    }
}
$pageTitle = $S['name'] . ' · ' . SITE_NAME;

// enquete no ar (do banco). Sem banco, o pote funciona normalmente, só sem enquete.
require_once __DIR__ . '/includes/enquetes.php';
require_once __DIR__ . '/includes/auth.php';
$extraJs = [
    'enquete'     => null,
    'tempero'     => tempero_para_js($comNivel),
    'mapa'        => $mapa,
    'total'       => $totais['total'], // itens no pote (o navegador só recebe os do vidro)
    'hoje'        => $hoje,
    // comentários vêm de /api/comentarios, 10 por vez; aqui só as contagens e os provocadores do mês
    'comentarios' => ['contagem' => $feed['contagem'], 'provocadores' => $provoc],
    // mural: a 1ª página de 12 já vem na página; "Carregar mais" busca as próximas em /api/posts
    'feed'        => $feed,
];
try {
    $usuario = current_user(); // pode renovar o cookie: antes de qualquer HTML
    if ($e = enquete_ativa($S['slug'])) {
        $extraJs['enquete'] = enquete_para_js($e, enquete_voto_de((int) $e['id'], $usuario ? (int) $usuario['id'] : null));
    }
} catch (Throwable $ex) {
    // banco fora do ar ou migration 002 não rodada
}
// apoio partidário (todos os partidos, até 3 por pessoa). null = sem banco / migration 009: a seção não aparece
require_once __DIR__ . '/includes/partidos.php';
$extraJs['partidos'] = partidos_para_js($usuario ?? null);

require __DIR__ . '/includes/header.php';
?>
<main>
  <?php require __DIR__ . '/partials/hero.php'; ?>
  <section class="section pedidos-pendentes" id="pedidos-pendentes" hidden></section>
  <?php if ($extraJs['partidos']) require __DIR__ . '/partials/partidos.php'; ?>
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
