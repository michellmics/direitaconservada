<?php
// Mapa da guerra dos potes: cada estado pintado com a cor de quem tem mais itens lá.
// O mesmo mapa nos dois potes; os textos (sarcásticos) vêm de includes/sides.php ('mapa').
// As cores e os números são preenchidos pelo JS (DC.mapa + compras deste navegador).
require_once __DIR__ . '/../includes/mapa-brasil.php';
$M   = $S['mapa'];
$esq = side('esquerda');
$dir = side('direita');

// estados pequenos: rótulo fora, com uma linha até o estado
$rotuloFora = ['RN' => [958, 256], 'PB' => [968, 296], 'PE' => [972, 336], 'AL' => [962, 378],
               'SE' => [938, 418], 'ES' => [856, 632], 'RJ' => [800, 730], 'DF' => [664, 500]];
?>
<section class="section mapa-section" id="mapa">
  <div class="section-head">
    <div>
      <span class="tag">Guerra dos potes <?= $esq['emoji'] ?> × <?= $dir['emoji'] ?></span>
      <h2><?= e($M['titulo']) ?></h2>
      <p class="section-sub mapa-manchete" id="mapa-manchete"></p>
    </div>
  </div>

  <div class="mapa-grid">
    <div class="mapa-wrap">
      <svg class="mapa" viewBox="<?= MAPA_BRASIL_VIEWBOX ?>" role="img" aria-label="Mapa do Brasil: cada estado pintado com a cor do pote que tem mais itens lá">
        <defs>
          <pattern id="mapa-empate" width="10" height="10" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
            <rect width="5" height="10" fill="<?= $esq['mapa']['cor'] ?>"/><rect x="5" width="5" height="10" fill="<?= $dir['mapa']['cor'] ?>"/>
          </pattern>
        </defs>
        <g class="mapa-estados">
          <?php foreach (MAPA_BRASIL as $uf => $est): ?>
            <path class="uf" data-uf="<?= $uf ?>" data-nome="<?= e($est['nome']) ?>" d="<?= $est['d'] ?>" tabindex="0" aria-label="<?= e($est['nome']) ?>"/>
          <?php endforeach; ?>
        </g>
        <g class="mapa-rotulos" aria-hidden="true">
          <?php foreach (MAPA_BRASIL as $uf => $est):
              [$x, $y] = $rotuloFora[$uf] ?? [$est['x'], $est['y']]; ?>
            <?php if (isset($rotuloFora[$uf])): ?>
              <line x1="<?= $est['x'] ?>" y1="<?= $est['y'] ?>" x2="<?= $x - 14 ?>" y2="<?= $y - 5 ?>"/>
            <?php endif; ?>
            <text x="<?= $x ?>" y="<?= $y ?>" data-uf="<?= $uf ?>"><?= $uf ?></text>
          <?php endforeach; ?>
        </g>
        <g class="mapa-coroas" aria-hidden="true"></g><!-- 👑 de quem manda em cada estado (JS) -->
      </svg>
      <ul class="mapa-legenda">
        <li><i style="background: <?= $esq['mapa']['cor'] ?>"></i><?= $esq['emoji'] ?> <?= e($esq['name']) ?> manda</li>
        <li><i style="background: <?= $dir['mapa']['cor'] ?>"></i><?= $dir['emoji'] ?> <?= e($dir['name']) ?> manda</li>
        <li><i class="leg-empate"></i>Empate</li>
      </ul>
    </div>

    <aside class="mapa-painel">
      <div class="mapa-placar" id="mapa-placar"></div>
      <div class="mapa-estado" id="mapa-estado" aria-live="polite"></div>
      <div class="mapa-fio">
        <h3>🎯 Estados por um fio</h3>
        <p class="prov-sub"><?= e($M['fio_sub']) ?></p>
        <ol id="mapa-fio"></ol>
      </div>
      <div class="mapa-ctas">
        <button class="btn btn-gold" type="button" data-open-buy>Quero minha <?= e($S['item']) ?></button>
        <button class="btn btn-ghost" type="button" id="mapa-indicar">📣 Chamar reforço</button>
      </div>
    </aside>
  </div>
</section>
