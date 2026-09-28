<?php
/**
 * O pote (SVG). Espera:
 *   $S         configuração do lado (side())
 *   $jarItems  (opcional) itens para desenhar já no servidor — usado na página inicial.
 *              Sem isso, o pote sai vazio e o JS preenche o grupo #jar-items.
 *
 * Dois formatos ($S['jar']['shape']):
 *   pot    — pote largo de conserva (azeitonas)
 *   bottle — vidro de pimenta, alto e fino, com tampinha
 */
$p = $S['slug'];
$L = $S['jar'];
$jarItems = $jarItems ?? null;
$bottle = $L['shape'] === 'bottle';

$G = $bottle ? [
    'outer'  => 'M170 94 H230 V126 Q230 140 262 158 Q290 172 290 202 V494 Q290 522 262 522 H138 Q110 522 110 494 V202 Q110 172 138 158 Q170 140 170 126 Z',
    'inner'  => 'M177 100 H223 V128 Q223 147 256 164 Q283 178 283 204 V492 Q283 515 260 515 H140 Q117 515 117 492 V204 Q117 178 144 164 Q177 147 177 128 Z',
    'shine'  => 'M126 214 Q122 350 128 486',
    'label'  => ['x' => 124, 'y' => 286, 'w' => 152, 'h' => 132],
    'text'   => [314, 344, 370, 400],
    'long'   => 128,
] : [
    'outer'  => 'M70 100 H330 V140 Q360 150 360 190 V470 Q360 520 310 520 H90 Q40 520 40 470 V190 Q40 150 70 140 Z',
    'inner'  => 'M70 118 Q70 108 80 108 H320 Q330 108 330 118 V140 Q352 150 352 190 V470 Q352 512 310 512 H90 Q48 512 48 470 V190 Q48 150 70 140 Z',
    'shine'  => 'M60 180 Q56 330 64 470',
    'label'  => ['x' => 92, 'y' => 268, 'w' => 216, 'h' => 130],
    'text'   => [304, 336, 362, 382],
    'long'   => 184,
];
$lb = $G['label'];
$longName = mb_strlen($S['name_b']) > 11;
?>
<svg class="jar jar-<?= $L['shape'] ?>" <?= $jarItems === null ? 'id="jar"' : '' ?> viewBox="0 0 400 540" role="img" aria-label="Pote de <?= e($S['items']) ?>">
  <defs>
    <clipPath id="jar-inside-<?= $p ?>"><path d="<?= $G['inner'] ?>"/></clipPath>
    <linearGradient id="glass-<?= $p ?>" x1="0" x2="1">
      <stop offset="0" stop-color="#fff" stop-opacity=".28"/>
      <stop offset=".18" stop-color="#fff" stop-opacity=".05"/>
      <stop offset=".8" stop-color="#fff" stop-opacity=".02"/>
      <stop offset="1" stop-color="#fff" stop-opacity=".18"/>
    </linearGradient>
    <?php if ($S['brine']): ?>
    <linearGradient id="brine-<?= $p ?>" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="<?= $S['brine'][0] ?>" stop-opacity=".25"/>
      <stop offset="1" stop-color="<?= $S['brine'][1] ?>" stop-opacity=".45"/>
    </linearGradient>
    <?php endif; ?>
    <linearGradient id="lid-<?= $p ?>" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="<?= $S['lid'][0] ?>"/>
      <stop offset=".5" stop-color="<?= $S['lid'][1] ?>"/>
      <stop offset="1" stop-color="<?= $S['lid'][2] ?>"/>
    </linearGradient>
  </defs>

  <!-- tampa -->
  <?php if ($bottle): ?>
    <rect x="154" y="84" width="92" height="12" rx="5" fill="<?= $S['lid'][2] ?>"/>
    <rect x="160" y="34" width="80" height="54" rx="9" fill="url(#lid-<?= $p ?>)"/>
    <g stroke="#000" stroke-width="2.5" opacity=".2">
      <?php for ($x = 170; $x <= 230; $x += 10): ?><line x1="<?= $x ?>" y1="42" x2="<?= $x ?>" y2="82"/><?php endfor; ?>
    </g>
    <rect x="160" y="34" width="80" height="9" rx="4.5" fill="#fff" opacity=".3"/>
  <?php else: ?>
    <rect x="62" y="30" width="276" height="62" rx="12" fill="url(#lid-<?= $p ?>)"/>
    <g stroke="#000" stroke-width="3" opacity=".22">
      <?php for ($x = 90; $x <= 300; $x += 30): ?><line x1="<?= $x ?>" y1="36" x2="<?= $x ?>" y2="86"/><?php endfor; ?>
    </g>
    <rect x="62" y="30" width="276" height="10" rx="5" fill="#fff" opacity=".25"/>
  <?php endif; ?>

  <!-- vidro -->
  <path d="<?= $G['outer'] ?>" fill="#0a0604" fill-opacity=".35" stroke="#fff" stroke-opacity=".45" stroke-width="4"/>

  <g clip-path="url(#jar-inside-<?= $p ?>)">
    <?php if ($S['brine']): ?>
      <rect x="40" y="150" width="320" height="380" fill="url(#brine-<?= $p ?>)"/>
    <?php endif; ?>
    <g <?= $jarItems === null ? 'id="jar-items"' : '' ?>>
      <?php
      // mostra os mais recentes que couberem
      $visible = array_slice($jarItems ?? [], -jar_slots($L));
      foreach ($visible as $i => $it):
          $pos = jar_position($i, $L);
          $scale = ($S['scales'][$it['tipo']] ?? 1) * $L['itemScale']; ?>
        <g transform="translate(<?= round($pos['x'], 1) ?> <?= round($pos['y'], 1) ?>) scale(<?= $scale ?>)"><g transform="rotate(<?= round($pos['r']) ?>)"><?= $S['shapes'][$it['tipo']] ?></g></g>
      <?php endforeach; ?>
    </g>
    <?php if ($S['brine']): ?>
      <g class="bubbles">
        <circle cx="110" cy="480" r="4"/><circle cx="250" cy="500" r="3"/><circle cx="300" cy="470" r="5"/><circle cx="170" cy="505" r="3"/>
      </g>
    <?php endif; ?>
  </g>

  <!-- rótulo -->
  <g class="label">
    <rect x="<?= $lb['x'] ?>" y="<?= $lb['y'] ?>" width="<?= $lb['w'] ?>" height="<?= $lb['h'] ?>" rx="14" fill="#f4ecd8" stroke="<?= $S['theme']['gold'] ?>" stroke-width="4"/>
    <rect x="<?= $lb['x'] + 8 ?>" y="<?= $lb['y'] + 8 ?>" width="<?= $lb['w'] - 16 ?>" height="<?= $lb['h'] - 16 ?>" rx="10" fill="none" stroke="<?= $S['theme']['dark'] ?>" stroke-width="1.5" stroke-dasharray="4 3"/>
    <text x="200" y="<?= $G['text'][0] ?>" text-anchor="middle" class="label-small" fill="<?= $S['theme']['dark'] ?>"><?= e($S['label_top']) ?></text>
    <text x="200" y="<?= $G['text'][1] ?>" text-anchor="middle" class="label-big" fill="<?= $S['theme']['ink'] ?>"><?= e(mb_strtoupper($S['name_a'])) ?></text>
    <?php /* nomes longos ("DA RESISTÊNCIA") usam fonte menor e são limitados à largura do rótulo */ ?>
    <text x="200" y="<?= $G['text'][2] ?>" text-anchor="middle" class="label-big<?= $longName ? ' label-long' : '' ?>" fill="<?= $S['theme']['ink'] ?>" <?= $longName ? 'textLength="' . $G['long'] . '" lengthAdjust="spacingAndGlyphs"' : '' ?>><?= e(mb_strtoupper($S['name_b'])) ?></text>
    <text x="200" y="<?= $G['text'][3] ?>" text-anchor="middle" class="label-cap" fill="<?= $S['theme']['ink'] ?>">CAP. <?= num(JAR_CAPACITY) ?> <?= e(mb_strtoupper($S['items'])) ?></text>
  </g>

  <path d="<?= $G['shine'] ?>" stroke="#fff" stroke-opacity=".35" stroke-width="10" stroke-linecap="round" fill="none" pointer-events="none"/>
  <path d="<?= $G['outer'] ?>" fill="url(#glass-<?= $p ?>)" pointer-events="none" opacity=".6"/>
</svg>
