<section class="hero" id="pote">
  <div class="hero-text">
    <span class="tag"><?= e($S['tag']) ?></span>
    <h1><?= $S['h1'] ?></h1>
    <p><?= str_replace('{preco}', money(min_price($S)), $S['lead']) ?></p>
    <div class="stats">
      <div><strong id="stat-total"><?= num(count($items)) ?></strong><span><?= e($S['items']) ?> no pote</span></div>
      <div><strong id="stat-hoje"><?= num($hoje) ?></strong><span>entraram hoje</span></div>
      <div><strong><?= e((string) array_key_first($ranking)) ?></strong><span><?= e($S['stat_uf']) ?></span></div>
    </div>
    <div class="hero-cta">
      <button class="btn btn-gold" data-open-buy>Entrar no pote · a partir de <?= money(min_price($S)) ?>/ano</button>
      <a class="btn btn-ghost" href="#mural">Ver o mural</a>
    </div>
  </div>

  <div class="jar-wrap">
    <?php $jarItems = null;
    require __DIR__ . '/jar.php'; ?>
    <div class="olive-tip" id="olive-tip" hidden></div>
    <p class="jar-capacity">
      <span class="jar-meter"><i id="jar-meter" style="width: <?= max(0.5, count($items) / JAR_CAPACITY * 100) ?>%"></i></span>
      <span><b id="jar-count"><?= num(count($items)) ?></b> de <?= num(JAR_CAPACITY) ?> lugares ocupados</span>
    </p>
  </div>
</section>