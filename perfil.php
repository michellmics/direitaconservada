<?php
// Perfil de quem está no pote: perfil.php?lado=direita&id=42
// O cartão é montado pelo JS (assim funciona também para quem acabou de comprar,
// cujos dados ainda estão só no navegador). Com o banco, dá para montar tudo aqui no PHP.
require __DIR__ . '/includes/config.php';
require __DIR__ . '/data/mock.php';

$S  = side($_GET['lado'] ?? 'direita');
$O  = side($S['other']);
$id = max(0, (int) ($_GET['id'] ?? 0));

$items      = mock_items($S['slug']);
$otherItems = mock_items($O['slug']);
// comentários dos dois potes: para mostrar o que a pessoa comentou aqui e do outro lado
$comments = array_merge(mock_comments($S['slug'], $items), mock_comments($O['slug'], $otherItems));

$person = null;
foreach ($items as $it) {
    if ($it['id'] === $id) {
        $person = $it;
        break;
    }
}
$pageTitle = $person ? nome_proprio($person['nome']) . ' · ' . $S['name'] : 'Perfil · ' . $S['name'];
if ($person) {
    $S['og'] = '“' . $person['frase'] . '” — ' . $S['cert_since'] . ' ' . date('d/m/Y', strtotime($person['desde']));
}

// só o necessário do outro pote (para dar contexto aos comentários feitos lá)
$extraJs = [
    'profileId'  => $id,
    'otherItems' => array_map(fn($o) => array_intersect_key($o, array_flip(['id', 'side', 'nome', 'frase', 'foto', 'tipo', 'selo'])), $otherItems),
];

require __DIR__ . '/includes/header.php';
?>
<main class="profile-page">
  <section class="profile-hero">
    <div class="profile-card" id="profile-card">
      <noscript><p class="section">Ative o JavaScript para ver o perfil.</p></noscript>
    </div>

    <aside class="profile-side">
      <div class="profile-jar jar-wrap">
        <h3>Onde está no pote</h3>
        <?php $jarItems = null; require __DIR__ . '/partials/jar.php'; ?>
        <div class="olive-tip" id="olive-tip" hidden></div>
        <p class="profile-jar-caption" id="profile-jar-caption"></p>
      </div>
    </aside>
  </section>

  <section class="section profile-stats-wrap">
    <div class="profile-stats" id="profile-stats"></div>
  </section>

  <section class="section" id="mural">
    <div class="section-head">
      <div>
        <span class="tag">No mural</span>
        <h2>Publicações</h2>
      </div>
    </div>
    <div class="feed" id="feed"></div>
    <button class="btn btn-ghost btn-more" id="feed-more">Carregar mais</button>
  </section>

  <section class="section alt">
    <div class="section-head">
      <div>
        <span class="tag">Debate</span>
        <h2>Comentários que fez</h2>
        <p class="section-sub">Aqui e no pote <?= e($O['name']) ?> <?= $O['emoji'] ?>.</p>
      </div>
    </div>
    <div class="made-comments" id="made-comments"></div>
  </section>

  <section class="section" id="my-items-section" hidden>
    <div class="section-head"><div><span class="tag">Só você vê</span><h2>Seus itens nos potes</h2></div></div>
    <div class="my-items" id="my-items"></div>
  </section>
</main>
<?php
require __DIR__ . '/partials/modals.php';
require __DIR__ . '/includes/footer.php';
