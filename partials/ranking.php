<?php
$max    = max($ranking ?: [1]);
$oldest = array_slice($items, 0, 5);
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
    <div class="oldest">
      <h3>Os mais antigos do pote</h3>
      <ul>
        <?php foreach ($oldest as $o): ?>
          <li data-olive-id="<?= $o['id'] ?>">
            <span class="num">#<?= str_pad((string) $o['id'], 4, '0', STR_PAD_LEFT) ?></span>
            <?php if ($o['foto']): ?>
              <img class="post-avatar avatar-photo" src="<?= e($o['foto']) ?>" alt="" loading="lazy">
            <?php else: ?>
              <span class="post-avatar avatar-<?= e($o['side']) ?>-<?= e($o['tipo']) ?>"><?= e(mb_strtoupper(mb_substr(nome_proprio($o['nome']), 0, 1))) ?></span>
            <?php endif; ?>
            <span><b><?= e(nome_proprio($o['nome'])) ?></b><small><?= e($o['cidade']) ?>/<?= e($o['uf']) ?> · desde <?= date('d/m/Y', strtotime($o['desde'])) ?></small></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</section>
