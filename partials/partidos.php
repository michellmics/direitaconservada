<?php
// Apoio partidário, logo abaixo do hero: quadrados com as siglas de todos os partidos (até 3) e o ranking.
// Os quadrados e o ranking são desenhados pelo app.js (DC.partidos). Espera $S.
$TP = $S['partidos'];
?>
<section class="partidos-faixa" id="partidos">
  <div class="partidos-inner">
    <div class="partidos-escolha">
      <span class="tag">Apoio partidário <?= $S['emoji'] ?></span>
      <h2><?= e($TP['titulo']) ?></h2>
      <p class="partidos-sub"><?= e($TP['sub']) ?></p>
      <div class="partidos-grid" id="partidos-grid" role="group" aria-label="Partidos que você apoia"></div>
      <p class="partidos-status" id="partidos-status" aria-live="polite"></p>
    </div>
    <div class="partidos-ranking">
      <h3>🏆 Ranking geral de apoio partidário</h3>
      <ol id="partidos-ranking"></ol>
      <button class="btn btn-link partidos-todos" type="button" id="partidos-todos" hidden></button>
    </div>
  </div>
</section>
