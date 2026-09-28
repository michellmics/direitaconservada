<?php
$labels = array_column($S['types'], 'label');
$steps = [
    ['Escolha sua ' . $S['item'], implode(', ', array_slice($labels, 0, -1)) . ' ou ' . end($labels) . '. Coloque sua foto, um selo, seu nome, sua cidade e a frase que vai ficar gravada.'],
    ['Entre no pote', 'Pagou, caiu no pote. Sua ' . $S['item'] . ' fica visível para todo mundo, com número e data. A partir de ' . money(min_price($S)) . ' por ano.'],
    ['Receba o certificado', '“' . $S['cert_since'] . '…” pronto para baixar e compartilhar no WhatsApp, X e Stories.'],
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
