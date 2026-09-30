<?php
// Destaque do Tretódromo (página inicial e potes): o duelo ao vivo mais votado, quantos estão rolando e os botões
// "Assistir e votar" / "Lançar desafio". Sem duelo ao vivo: o último vencedor, ou só o convite. Sem banco: nada.
require_once dirname(__DIR__) . '/includes/duelos.php';
require_once dirname(__DIR__) . '/partials/arena.php';

try {
    duelos_atualizar();
    $tdVivos = duelos_listar('aovivo', 1);
    $tdTotal = (int) db()->query("SELECT COUNT(*) FROM duelos WHERE status IN ('andamento', 'votacao')")->fetchColumn();
    $tdUltimo = $tdVivos ? null : (duelos_listar('encerrados', 1)[0] ?? null);
} catch (Throwable $e) {
    return; // migration 026 ainda não rodou
}
$tdDuelo = $tdVivos[0] ?? null;
?>
<section class="td-destaque" aria-labelledby="td-titulo">
  <div class="td-cabeca">
    <span class="td-marca"><?= arena_icone('espadas', 20) ?> Tretódromo</span>
    <?php if ($tdTotal): ?><span class="arena-card-selo is-vivo">● <?= $tdTotal ?> duelo<?= $tdTotal === 1 ? '' : 's' ?> ao vivo</span><?php endif; ?>
  </div>
  <?php if ($tdDuelo):
      $tot = $tdDuelo['votos_a'] + $tdDuelo['votos_b'];
      $pa = $tot ? (int) round($tdDuelo['votos_a'] / $tot * 100) : 50; ?>
    <h2 id="td-titulo"><?= e($tdDuelo['tema']) ?></h2>
    <div class="td-vs">
      <span class="c-<?= e($tdDuelo['a_lado']) ?>"><?= arena_item($tdDuelo['a_lado'], 26) ?> <b><?= e(nome_proprio($tdDuelo['a_nome'])) ?></b><?= $tot ? ' · ' . $pa . '%' : '' ?></span>
      <em>VS</em>
      <span class="c-<?= e($tdDuelo['b_lado']) ?>"><?= $tot ? (100 - $pa) . '% · ' : '' ?><b><?= e(nome_proprio($tdDuelo['b_nome'])) ?></b> <?= arena_item($tdDuelo['b_lado'], 26) ?></span>
    </div>
    <span class="arena-barra"><i class="bg-<?= e($tdDuelo['a_lado']) ?>" style="width:<?= $pa ?>%"></i><i class="bg-<?= e($tdDuelo['b_lado']) ?>"></i></span>
    <div class="td-acoes">
      <a class="btn btn-gold" href="<?= e(duelo_link((int) $tdDuelo['id'])) ?>">Assistir e votar</a>
      <a class="btn btn-ghost" href="tretodromo">Ver a arena</a>
    </div>
  <?php else: ?>
    <?php if ($tdUltimo && $tdUltimo['vencedor'] && $tdUltimo['vencedor'] !== 'empate'): ?>
      <h2 id="td-titulo"><?= arena_icone('trofeu', 24) ?> <?= e(nome_proprio($tdUltimo[$tdUltimo['vencedor'] . '_nome'])) ?> venceu o último duelo</h2>
      <p class="td-sub">“<?= e($tdUltimo['tema']) ?>” · <a href="<?= e(duelo_link((int) $tdUltimo['id'])) ?>">ver como foi</a></p>
    <?php else: ?>
      <h2 id="td-titulo">Direita × esquerda, um contra um</h2>
    <?php endif; ?>
    <p class="td-sub">Desafie alguém do outro pote para um debate de 3 rodadas. A plateia decide quem leva o troféu. É grátis para quem tem azeitona ou pimenta.</p>
    <div class="td-acoes">
      <a class="btn btn-gold" href="tretodromo#desafiar">⚔️ Lançar um desafio</a>
      <a class="btn btn-ghost" href="tretodromo">Ver a arena</a>
    </div>
  <?php endif; ?>
</section>
