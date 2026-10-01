<?php
require_once dirname(__DIR__) . '/includes/pedidos.php'; // LIBERA_MESES e LIBERA_FRUTAS
$labels = array_column($S['types'], 'label');
$steps = [
    ['Escolha sua ' . $S['item'], 'Todo mundo começa com a ' . $labels[0] . '. ' . implode(', ', array_slice($labels, 1, -1)) . ' e ' . end($labels)
        . ' vão sendo liberadas: cada uma a cada ' . LIBERA_MESES . ' meses de conta e ' . num(LIBERA_FRUTAS) . ' frutas pegas. Coloque sua foto, um selo, seu nome, sua cidade e a frase.'],
    ['Entre no pote', 'É grátis e é uma por dia: preencheu, caiu no pote. Sua ' . $S['item'] . ' fica visível para todo mundo, com número e data, por 1 ano (e renova grátis).'],
    ['Participe do mural', 'Compartilhe ' . $S['mural_about'] . '. Só ' . $S['members'] . ' publicam; os outros leem e ' . $S['react'] . '.'],
    ['Comente do outro lado', 'Quem tem ' . side($S['other'])['item'] . ' também pode comentar aqui, e você pode comentar no pote de lá. Debate com tempero.'],
];
?>
<section class="section" id="como">
  <div class="section-head"><div><span class="tag">Simples assim</span><h2>Como funciona</h2></div></div>
  <div class="steps">
    <?php foreach ($steps as $i => [$titulo, $texto]): ?>
      <div class="step"><b><?= $i + 1 ?></b><h3><?= e($titulo) ?></h3><p><?= e($texto) ?></p></div>
    <?php endforeach; ?>
  </div>
</section>
