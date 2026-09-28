<?php
$steps = [
    ['Escolha sua azeitona', 'Verde, preta, recheada ou grande. Coloque seu nome, sua cidade e a frase que vai ficar gravada.'],
    ['Entre no pote', 'Pagou, caiu no pote. Sua azeitona fica visível para todo mundo, com número e data. A partir de ' . money(min_price()) . ' por ano.'],
    ['Receba o certificado', '“Conservado desde…” pronto para baixar e compartilhar no WhatsApp, X e Stories.'],
    ['Participe do mural', 'Compartilhe ideias, opiniões, notícias e vídeos sobre a direita. Só quem é conservado publica; os outros leem e azeitonam.'],
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
