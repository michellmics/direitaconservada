<?php
// Perfil de quem está no pote: /perfil?c=… (cifrado: lado + id, ou lado + meu=1; ver includes/rotas.php)
// O cartão é montado pelo JS com o que vem do banco (DC): a pessoa, as azeitonas dela (5 por vez) e o que ela publicou.
require __DIR__ . '/includes/config.php';
require __DIR__ . '/data/mock.php';
require __DIR__ . '/includes/tempero.php';
require __DIR__ . '/includes/comentarios.php';
require __DIR__ . '/includes/posts.php';
require __DIR__ . '/includes/pote_js.php';

$P  = rota_params(['lado', 'id', 'meu']);
$S  = side($P['lado'] ?? 'direita');
$O  = side($S['other']);
$id = max(0, (int) ($P['id'] ?? 0));
// o perfil é da pessoa: o link é de uma azeitona, a página mostra todas as dela neste pote ([0] = "meu perfil" sem item)
$grupo = $id ? banco_pessoa_numeros($S['slug'], $id) : [0];

// para o navegador vai só o necessário (não o pote inteiro): o vidro, as azeitonas da pessoa, os autores do que
// aparece na tela e, do outro pote, os donos dos posts que ela comentou lá
$feed   = posts_pagina($S['slug'], 'recentes', 0, $grupo); // posts da pessoa (as 20 últimas), 12 por vez
$provoc = comentarios_provocadores($S['slug']);
$feitos = comentarios_feitos($S['slug'], $grupo); // os 20 últimos
$donosPosts = [];
foreach ($feitos as $c) {
    $donosPosts[explode('-', $c['post'], 2)[0]][] = (int) $c['post_item'];
}
$items = pote_itens_js($S['slug'], array_merge($grupo, array_column($feed['posts'], 'oliveId'), array_column($provoc, 'id'), $donosPosts[$S['slug']] ?? []));
$quer = array_flip($donosPosts[$O['slug']] ?? []);
$otherItems = pote_itens_numeros($O['slug'], array_keys($quer));
$comNivel = [$S['slug'] => array_column($items, 'id'), $O['slug'] => array_column($otherItems, 'id')];
foreach (banco_meus_itens((int) (current_user()['id'] ?? 0)) as $s => $lista) { // o nível de quem está vendo
    foreach ($lista as $o) {
        $comNivel[$s][] = $o['id'];
    }
}

$person = null;
foreach ($id ? pote_itens_numeros($S['slug'], [$id]) : [] as $it) {
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
    'pessoa'     => $grupo, // o perfil é da pessoa: os números de todas as azeitonas dela neste pote
    'pessoaItens' => $id ? banco_pessoa_itens($S['slug'], $id) : null, // a lista do perfil: as 5 mais recentes ("Carregar mais" traz de 5 em 5)
    'meu'        => !empty($P['meu']), // "Ver meu perfil" do menu da conta
    'tempero'    => tempero_para_js($comNivel),
    'total'      => pote_totais($S['slug'])['total'], // itens no pote (o navegador só recebe os do vidro)
    'hoje'       => pote_totais($S['slug'])['hoje'],
    'otherItems' => array_map(fn($o) => array_intersect_key($o, array_flip(['id', 'side', 'nome', 'frase', 'foto', 'tipo', 'selo'])), $otherItems),
    // os comentários dos posts vêm de /api/comentarios (10 por vez); aqui as contagens e o que a pessoa comentou
    'comentarios' => [
        'contagem'     => $feed['contagem'],
        'provocadores' => $provoc,
        'feitos'       => $feitos,
    ],
    'feed'        => $feed,
];

require __DIR__ . '/includes/header.php';
?>
<main class="profile-page">
  <section class="section pedidos-pendentes" id="pedidos-pendentes" hidden></section>
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
      <?php // Tretódromo: desafiar a pessoa do perfil (quem é do outro pote e não é a própria pessoa)
      $meusNumeros = array_column(banco_meus_itens((int) (current_user()['id'] ?? 0))[$S['slug']] ?? [], 'id');
      if ($person && !in_array($id, $meusNumeros, true)): ?>
        <a class="btn btn-gold btn-block perfil-desafiar" href="<?= e(url('tretodromo', ['contra_lado' => $S['slug'], 'contra_num' => $id], 'desafiar')) ?>">⚔️ Desafiar para um duelo</a>
      <?php endif; ?>
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
