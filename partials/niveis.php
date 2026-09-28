<?php
// Os níveis do pote (ícone ao lado do nome) e como subir. Regras em includes/tempero.php.
$T  = $S['tempero'];
$R  = TEMPERO;
$O  = side($S['other']);
$pts = function (float $p) use ($T): string {
    $n = (int) round($p * $T['fator']);
    return num($n) . ' ' . ($n === 1 ? preg_replace('/s$/', '', $T['unidade']) : $T['unidade']); // "1 ponto"
};
$topo = end($T['niveis']);
?>
<section class="section" id="niveis">
  <div class="section-head">
    <div>
      <span class="tag"><?= e($T['titulo']) ?></span>
      <h2><?= e($T['chamada']) ?></h2>
      <p class="section-sub">O ícone ao lado do nome mostra o nível de cada um. Sobe quem tem mais <?= e($S['items']) ?> no pote e quem participa do mural.</p>
    </div>
  </div>
  <ol class="niveis">
    <?php foreach ($T['niveis'] as $i => $nv): ?>
      <li class="nivel-card<?= $i === count($T['niveis']) - 1 ? ' is-topo' : '' ?>">
        <span class="nivel-card-icone" aria-hidden="true"><?= $nv['icone'] ?></span>
        <b><?= e($nv['nome']) ?></b>
        <small>a partir de <?= $pts($R['faixas'][$i]) ?></small>
      </li>
    <?php endforeach; ?>
  </ol>
  <ul class="niveis-regras">
    <li><span aria-hidden="true"><?= $S['emoji'] ?></span><p><b>Cada R$ 1 em <?= e($S['items']) ?> no pote: +<?= $pts($R['por_real']) ?>.</b> Se uma vencer, os pontos dela saem; renovando em dia, ficam.</p></li>
    <li><span aria-hidden="true">📝</span><p><b>Publicar no mural: +<?= $pts($R['post']) ?>. Comentar: +<?= $pts($R['comentario']) ?>.</b> Até <?= $R['comentarios_por_dia'] ?> comentários por dia contam, com pelo menos <?= $R['comentario_min'] ?> letras ou um vídeo.</p></li>
    <li><span aria-hidden="true">💬</span><p><b>Receber comentário: +<?= $pts($R['recebido']) ?>; de quem é <?= e($O['name']) ?> <?= $O['emoji'] ?>: +<?= $pts($R['recebido_outro']) ?>.</b> A cada <?= (int) round(1 / $R['curtida']) ?> curtidas recebidas: +<?= $pts(1) ?>.</p></li>
    <li><span aria-hidden="true"><?= $topo['icone'] ?></span><p><b>O nível <?= e($topo['nome']) ?> exige também <?= money($R['topo_compra'] / $R['por_real']) ?> em <?= e($S['items']) ?> no pote.</b> A participação conta pelos últimos <?= $R['janela_meses'] ?> meses.</p></li>
  </ul>
</section>
