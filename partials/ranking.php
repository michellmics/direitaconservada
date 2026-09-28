<?php
$max    = max($ranking ?: [1]);
?>
<section class="section alt" id="ranking">
  <div class="section-head">
    <div>
      <span class="tag">Placar</span>
      <h2><?= e($S['ranking']) ?></h2>
    </div>
  </div>
  <div class="ranking-grid">
    <ol class="ranking">
      <?php foreach ($ranking as $uf => $qtd): ?>
        <li>
          <span class="uf"><?= e($uf) ?></span>
          <span class="bar"><i style="width: <?= round($qtd / $max * 100) ?>%"></i></span>
          <span class="qtd"><?= num($qtd) ?></span>
        </li>
      <?php endforeach; ?>
    </ol>
    <div class="provocadores">
      <h3>🔥 Provocadores do mês</h3>
      <p class="prov-sub">Quem mais recebeu comentários de quem é <?= e(side($S['other'])['name']) ?> <?= side($S['other'])['emoji'] ?> em <span id="provocadores-mes"><?= e(date('m/Y')) ?></span>. O 1º ganha o selo de Provocador(a) do mês.</p>
      <ol class="prov-list" id="provocadores"></ol>
    </div>
    <div class="newest">
      <h3>✨ Recém-chegados ao pote</h3>
      <p class="prov-sub">As últimas pessoas que entraram. Dê as boas-vindas!</p>
      <ul id="newest"></ul>
    </div>
  </div>
</section>
