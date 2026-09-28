<?php
/**
 * O pote (SVG). Espera:
 *   $S         configuração do lado (side())
 *   $jarItems  (opcional) itens para desenhar já no servidor — usado na página inicial.
 *              Sem isso, o pote sai vazio e o JS preenche o grupo #jar-items.
 */
$p = $S['slug'];
$jarItems = $jarItems ?? null;
?>
<svg class="jar" <?= $jarItems === null ? 'id="jar"' : '' ?> viewBox="0 0 400 540" role="img" aria-label="Pote de <?= e($S['items']) ?>">
  <defs>
    <clipPath id="jar-inside-<?= $p ?>">
      <path d="M70 118 Q70 108 80 108 H320 Q330 108 330 118 V140 Q352 150 352 190 V470 Q352 512 310 512 H90 Q48 512 48 470 V190 Q48 150 70 140 Z"/>
    </clipPath>
    <linearGradient id="glass-<?= $p ?>" x1="0" x2="1">
      <stop offset="0" stop-color="#fff" stop-opacity=".28"/>
      <stop offset=".18" stop-color="#fff" stop-opacity=".05"/>
      <stop offset=".8" stop-color="#fff" stop-opacity=".02"/>
      <stop offset="1" stop-color="#fff" stop-opacity=".18"/>
    </linearGradient>
    <linearGradient id="brine-<?= $p ?>" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="<?= $S['brine'][0] ?>" stop-opacity=".25"/>
      <stop offset="1" stop-color="<?= $S['brine'][1] ?>" stop-opacity=".45"/>
    </linearGradient>
    <linearGradient id="lid-<?= $p ?>" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="<?= $S['lid'][0] ?>"/>
      <stop offset=".5" stop-color="<?= $S['lid'][1] ?>"/>
      <stop offset="1" stop-color="<?= $S['lid'][2] ?>"/>
    </linearGradient>
  </defs>

  <!-- tampa -->
  <rect x="62" y="30" width="276" height="62" rx="12" fill="url(#lid-<?= $p ?>)"/>
  <g stroke="#000" stroke-width="3" opacity=".22">
    <?php for ($x = 90; $x <= 300; $x += 30): ?><line x1="<?= $x ?>" y1="36" x2="<?= $x ?>" y2="86"/><?php endfor; ?>
  </g>
  <rect x="62" y="30" width="276" height="10" rx="5" fill="#fff" opacity=".25"/>

  <!-- vidro -->
  <path d="M70 100 H330 V140 Q360 150 360 190 V470 Q360 520 310 520 H90 Q40 520 40 470 V190 Q40 150 70 140 Z" fill="#0a0604" fill-opacity=".35" stroke="#fff" stroke-opacity=".45" stroke-width="4"/>

  <g clip-path="url(#jar-inside-<?= $p ?>)">
    <rect x="40" y="150" width="320" height="380" fill="url(#brine-<?= $p ?>)"/>
    <g <?= $jarItems === null ? 'id="jar-items"' : '' ?>>
      <?php foreach ($jarItems ?? [] as $i => $it):
          $pos = jar_position($i);
          $scale = $S['scales'][$it['tipo']] ?? 1; ?>
        <g transform="translate(<?= round($pos['x'], 1) ?> <?= round($pos['y'], 1) ?>) scale(<?= $scale ?>)"><g transform="rotate(<?= round($pos['r']) ?>)"><?= $S['shapes'][$it['tipo']] ?></g></g>
      <?php endforeach; ?>
    </g>
    <g class="bubbles">
      <circle cx="110" cy="480" r="4"/><circle cx="250" cy="500" r="3"/><circle cx="300" cy="470" r="5"/><circle cx="170" cy="505" r="3"/>
    </g>
  </g>

  <!-- rótulo -->
  <g class="label">
    <rect x="92" y="268" width="216" height="130" rx="14" fill="#f4ecd8" stroke="<?= $S['theme']['gold'] ?>" stroke-width="4"/>
    <rect x="100" y="276" width="200" height="114" rx="10" fill="none" stroke="<?= $S['theme']['dark'] ?>" stroke-width="1.5" stroke-dasharray="4 3"/>
    <text x="200" y="304" text-anchor="middle" class="label-small" fill="<?= $S['theme']['dark'] ?>"><?= e($S['label_top']) ?></text>
    <text x="200" y="336" text-anchor="middle" class="label-big" fill="<?= $S['theme']['ink'] ?>"><?= e(mb_strtoupper($S['name_a'])) ?></text>
    <?php /* nomes longos ("DA RESISTÊNCIA") usam fonte menor e são limitados à largura do rótulo */ ?>
    <text x="200" y="362" text-anchor="middle" class="label-big<?= mb_strlen($S['name_b']) > 11 ? ' label-long' : '' ?>" fill="<?= $S['theme']['ink'] ?>" <?= mb_strlen($S['name_b']) > 11 ? 'textLength="184" lengthAdjust="spacingAndGlyphs"' : '' ?>><?= e(mb_strtoupper($S['name_b'])) ?></text>
    <text x="200" y="382" text-anchor="middle" class="label-cap" fill="<?= $S['theme']['ink'] ?>">CAP. <?= num(JAR_CAPACITY) ?> <?= e(mb_strtoupper($S['items'])) ?></text>
  </g>

  <path d="M60 180 Q56 330 64 470" stroke="#fff" stroke-opacity=".35" stroke-width="10" stroke-linecap="round" fill="none" pointer-events="none"/>
  <path d="M70 100 H330 V140 Q360 150 360 190 V470 Q360 520 310 520 H90 Q40 520 40 470 V190 Q40 150 70 140 Z" fill="url(#glass-<?= $p ?>)" pointer-events="none" opacity=".6"/>
</svg>
