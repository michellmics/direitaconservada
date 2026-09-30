<?php
// Um duelo do Tretódromo: /duelo?n=ID (endereço fixo e público: vai para o Google).
// Mostra os duelistas, o placar da plateia, as 3 rodadas e o que quem está vendo pode fazer agora:
// aceitar/recusar, responder na sua vez, votar, compartilhar e (no fim) baixar o card de vitória.
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/duelos.php';
require __DIR__ . '/partials/arena.php';

$U = current_user();
$uid = $U ? (int) $U['id'] : null;
$id = (int) ($_GET['n'] ?? 0);
try {
    duelos_atualizar();
    $d = $id ? duelo_buscar($id) : null;
    if ($d) {
        $args = duelo_argumentos($id);
        $hist = ['a' => duelo_historico((int) $d['item_a']), 'b' => duelo_historico((int) $d['item_b'])];
        $meuVoto = duelo_meu_voto($id, $uid);
    }
} catch (PDOException $e) {
    $d = null;
}
if (!$d) {
    require __DIR__ . '/includes/erro.php';
    pagina_erro(404);
}

$eu = duelo_meu_lado($d, $uid);
$nome = fn(string $l) => nome_proprio($d[$l . '_nome']);
$total = $d['votos_a'] + $d['votos_b'];
$pa = $total ? (int) round($d['votos_a'] / $total * 100) : 50;
$aberto = in_array($d['status'], ['andamento', 'votacao'], true);
$status = match ($d['status']) {
    'aguardando' => ['Aguardando aceite', 'is-espera'],
    'andamento'  => ['Ao vivo · rodada ' . $d['rodada'], 'is-vivo'],
    'votacao'    => ['Votação da plateia', 'is-vivo'],
    'encerrado'  => ['Encerrado', 'is-fim'],
    'recusado'   => ['Desafio recusado', 'is-fim'],
    default      => ['Desafio expirado', 'is-fim'],
};
$titulo = $d['tema'] . ' — ' . $nome('a') . ' × ' . $nome('b') . ' | Tretódromo';
$descricao = 'Duelo de debate no Pote Político: ' . $nome('a') . ' (' . SIDES[$d['a_lado']]['name'] . ') contra ' . $nome('b')
    . ' (' . SIDES[$d['b_lado']]['name'] . '). ' . ($aberto ? 'Leia os argumentos e vote!' : 'Veja quem venceu.');
$indexar = in_array($d['status'], ['andamento', 'votacao', 'encerrado'], true);

arena_inicio($titulo, $descricao, url_base() . duelo_link($id), $indexar ? [
    '@type' => 'DiscussionForumPosting', 'headline' => $d['tema'], 'url' => url_base() . duelo_link($id), 'datePublished' => date('c', strtotime($d['criado_em'])),
    'author' => ['@type' => 'Person', 'name' => $nome('a')], 'inLanguage' => 'pt-BR',
] : [], 'arena', $indexar);
?>
<main class="arena duelo" data-duelo="<?= $id ?>">
  <section class="duelo-topo">
    <span class="arena-card-selo <?= $status[1] ?>"><?= e($status[0]) ?></span>
    <span class="duelo-sobre">Tema do duelo #<?= str_pad((string) $id, 4, '0', STR_PAD_LEFT) ?></span>
    <h1><?= e($d['tema']) ?></h1>
    <p>Desafio lançado por <b><?= e($nome('a')) ?></b> · 3 rodadas · a plateia decide
      <?php if (in_array($d['status'], ['aguardando', 'andamento', 'votacao'], true)): ?> · <?= $d['status'] === 'votacao' ? 'votação encerra' : 'prazo' ?> em <b><?= e(duelo_falta($d['prazo_em'])) ?></b><?php endif; ?></p>
  </section>

  <section class="duelo-vs">
    <?php foreach (['a', 'b'] as $l): $S = SIDES[$d[$l . '_lado']]; ?>
      <a class="duelista duelista-<?= $l ?> tema-<?= e($d[$l . '_lado']) ?><?= $d['vencedor'] === $l ? ' is-vencedor' : '' ?>" href="<?= e(url('perfil', ['lado' => $d[$l . '_lado'], 'id' => (int) $d[$l . '_numero']])) ?>">
        <span class="duelista-avatar"><?= arena_item($d[$l . '_lado'], 52) ?></span>
        <span class="duelista-info">
          <small><?= $l === 'a' ? 'Desafiante' : 'Desafiado' ?> · <?= e($S['name']) ?></small>
          <b><?= e($nome($l)) ?></b>
          <span><?= e(ucfirst($S['item'])) ?> #<?= str_pad((string) $d[$l . '_numero'], 4, '0', STR_PAD_LEFT) ?> · <?= e($d[$l . '_uf']) ?> · <?= $hist[$l]['v'] ?> vitória<?= $hist[$l]['v'] === 1 ? '' : 's' ?> · <?= $hist[$l]['d'] ?> derrota<?= $hist[$l]['d'] === 1 ? '' : 's' ?></span>
        </span>
        <?php if ($d['vencedor'] === $l): ?><span class="duelista-coroa"><?= arena_icone('trofeu', 26) ?></span><?php endif; ?>
      </a>
      <?php if ($l === 'a'): ?><span class="duelo-x" aria-hidden="true">VS</span><?php endif; ?>
    <?php endforeach; ?>
  </section>

  <?php if ($d['status'] !== 'aguardando' && $d['status'] !== 'recusado' && $d['status'] !== 'expirado'): ?>
    <section class="duelo-placar" data-placar>
      <div class="duelo-placar-nums">
        <span><b class="c-<?= e($d['a_lado']) ?>" data-pct-a><?= $total ? $pa . '%' : '—' ?></b> <small data-votos-a><?= n_votos($d['votos_a']) ?></small></span>
        <span class="duelo-placar-rotulo">Placar da plateia</span>
        <span><small data-votos-b><?= n_votos($d['votos_b']) ?></small> <b class="c-<?= e($d['b_lado']) ?>" data-pct-b><?= $total ? (100 - $pa) . '%' : '—' ?></b></span>
      </div>
      <div class="arena-barra is-grande"><i class="bg-<?= e($d['a_lado']) ?>" style="width:<?= $pa ?>%" data-barra-a></i><i class="bg-<?= e($d['b_lado']) ?>"></i></div>
    </section>
  <?php endif; ?>

  <?php if ($d['status'] === 'encerrado'): ?>
    <section class="duelo-resultado">
      <?php if ($d['vencedor'] === 'empate'): ?>
        <h2>🤝 Empate!</h2><p>A plateia não conseguiu escolher. Revanche?</p>
      <?php else: $v = $d['vencedor']; ?>
        <h2><?= arena_icone('trofeu', 30) ?> <?= e($nome($v)) ?> venceu<?= $d['wo'] ? ' por W.O.' : '' ?>!</h2>
        <p><?= $d['wo'] ? e($nome($v === 'a' ? 'b' : 'a')) . ' não respondeu dentro do prazo.' : 'Decisão da plateia: ' . max($pa, 100 - $pa) . '% dos votos.' ?></p>
      <?php endif; ?>
    </section>
  <?php elseif ($d['status'] === 'recusado' || $d['status'] === 'expirado'): ?>
    <section class="duelo-resultado is-apagado">
      <h2><?= $d['status'] === 'recusado' ? '🐔 ' . e($nome('b')) . ' recusou o desafio' : '⌛ O desafio não foi aceito a tempo' ?></h2>
      <p>O duelo não aconteceu. <a href="tretodromo#desafiar">Lance um novo desafio</a>.</p>
    </section>
  <?php endif; ?>

  <section class="duelo-rodadas">
    <?php foreach (DUELO_RODADAS as $i => $rotulo): $r = $i + 1; if (!isset($args[$r]) && ($d['status'] !== 'andamento' || $r > $d['rodada'])) continue; ?>
      <div class="duelo-rodada">
        <h3>Rodada <?= $r ?> <small><?= $rotulo ?></small></h3>
        <div class="duelo-args">
          <?php foreach (['a', 'b'] as $l): $arg = $args[$r][$l] ?? null; ?>
            <?php if ($arg): ?>
              <article class="duelo-arg duelo-arg-<?= $l ?> tema-<?= e($d[$l . '_lado']) ?>">
                <p><?= nl2br(e($arg['texto'])) ?></p>
                <small><?= e($nome($l)) ?> · <?= date('d/m H:i', strtotime($arg['criado_em'])) ?></small>
              </article>
            <?php elseif ($d['status'] === 'andamento' && (int) $d['rodada'] === $r && $d['vez'] === $l): ?>
              <?php if ($eu === $l): ?>
                <form class="duelo-arg duelo-responder tema-<?= e($d[$l . '_lado']) ?>" data-argumentar>
                  <label for="resposta"><b>Sua vez!</b> Responda em até <?= e(duelo_falta($d['prazo_em'])) ?>, senão perde por W.O.</label>
                  <textarea id="resposta" name="texto" rows="5" maxlength="<?= DUELO_ARG_MAX ?>" required placeholder="Seu argumento…"></textarea>
                  <div class="duelo-responder-pe"><small class="arena-contador" data-contador>0 / <?= DUELO_ARG_MAX ?></small><button class="btn btn-gold btn-sm" type="submit">Enviar argumento</button></div>
                </form>
              <?php else: ?>
                <div class="duelo-arg duelo-espera"><?= arena_icone('relogio', 18) ?> <span><b><?= e($nome($l)) ?></b> está preparando o argumento…<br><small>Prazo: <?= e(duelo_falta($d['prazo_em'])) ?> · sem resposta, perde por W.O.</small></span></div>
              <?php endif; ?>
            <?php else: ?>
              <div class="duelo-arg duelo-vazio" aria-hidden="true"></div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </section>

  <?php if ($d['status'] === 'aguardando'): ?>
    <section class="duelo-painel">
      <?php if ($eu === 'b'): ?>
        <h2>Você foi desafiado!</h2>
        <p><?= e($nome('a')) ?> quer debater com você. Aceita? Você tem <?= e(duelo_falta($d['prazo_em'])) ?> para responder.</p>
        <div class="duelo-botoes">
          <button class="btn btn-gold" type="button" data-acao="aceitar">⚔️ Aceitar o duelo</button>
          <button class="btn btn-ghost" type="button" data-acao="recusar" data-confirmar="Recusar o desafio? Todo mundo vai ver que você amarelou 🐔">Recusar</button>
        </div>
      <?php else: ?>
        <h2>Esperando <?= e($nome('b')) ?> aceitar</h2>
        <p>O prazo termina em <?= e(duelo_falta($d['prazo_em'])) ?>. Se não aceitar, o desafio expira.</p>
      <?php endif; ?>
    </section>
  <?php elseif ($aberto): ?>
    <section class="duelo-painel">
      <h2>Quem está ganhando essa treta?</h2>
      <p>Vote no melhor argumento, não no seu pote. Um voto por conta; dá para mudar até o fim do duelo.</p>
      <?php if ($eu): ?>
        <p class="arena-aviso">Você está duelando: quem vota é a plateia. Chame a sua torcida!</p>
      <?php elseif (!$U): ?>
        <p class="arena-aviso"><a href="<?= e(url('entrar', ['lado' => 'direita', 'r' => duelo_link($id)])) ?>">Entre na sua conta</a> para votar.</p>
      <?php else: ?>
        <div class="duelo-botoes">
          <?php foreach (['a', 'b'] as $l): ?>
            <button class="btn duelo-votar tema-<?= e($d[$l . '_lado']) ?><?= $meuVoto === $l ? ' is-meu' : '' ?>" type="button" data-votar="<?= $l ?>" aria-pressed="<?= $meuVoto === $l ? 'true' : 'false' ?>">
              <?= arena_item($d[$l . '_lado'], 22) ?> <?= $meuVoto === $l ? 'Seu voto: ' : 'Votar em ' ?><?= e($nome($l)) ?>
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <section class="duelo-compartilhar">
    <?php $txt = $d['status'] === 'encerrado' && $d['vencedor'] !== 'empate'
        ? '🏆 ' . $nome($d['vencedor']) . ' venceu o duelo "' . $d['tema'] . '" no Tretódromo!'
        : '⚔️ Duelo no Tretódromo: "' . $d['tema'] . '" — ' . $nome('a') . ' × ' . $nome('b') . '. Vem votar!'; ?>
    <a class="btn btn-ghost btn-sm" href="https://wa.me/?text=<?= rawurlencode($txt . ' ' . url_base() . duelo_link($id)) ?>" target="_blank" rel="noopener">Mandar no WhatsApp</a>
    <button class="btn btn-ghost btn-sm" type="button" data-copiar="<?= e(url_base() . duelo_link($id)) ?>">Copiar link</button>
    <?php if ($d['status'] === 'encerrado' && $d['vencedor'] !== 'empate'): $v = $d['vencedor']; $p = $v === 'a' ? $pa : 100 - $pa; ?>
      <button class="btn btn-gold btn-sm" type="button" data-card='<?= e(json_encode([
          'id' => $id, 'tema' => $d['tema'], 'vencedor' => $nome($v), 'lado' => $d[$v . '_lado'], 'pote' => SIDES[$d[$v . '_lado']]['name'],
          'uf' => $d[$v . '_uf'], 'perdedor' => $nome($v === 'a' ? 'b' : 'a'), 'wo' => (bool) $d['wo'], 'pct' => $total ? $p : null,
          'site' => preg_replace('#^https?://#', '', rtrim(url_base(), '/')) . '/tretodromo',
      ], JSON_UNESCAPED_UNICODE)) ?>'>🏆 Baixar card de vitória</button>
    <?php endif; ?>
    <a class="btn btn-ghost btn-sm" href="tretodromo#desafiar">⚔️ Lançar um desafio</a>
  </section>
</main>
<?php
arena_fim();

function n_votos(int $n): string
{
    return number_format($n, 0, ',', '.') . ' voto' . ($n === 1 ? '' : 's');
}
