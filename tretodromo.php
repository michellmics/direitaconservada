<?php
// Tretódromo, a arena de duelos 1×1 (includes/duelos.php): duelos ao vivo / aguardando / encerrados, os duelos de
// quem está logado, gladiadores do mês e o formulário "Lançar desafio".
// ?f=aovivo|aguardando|encerrados · ?c=… (cifrado: contra_lado + contra_num) já traz o oponente escolhido (botão do perfil).
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/duelos.php';
require __DIR__ . '/partials/arena.php';

$U = current_user();
$filtro = in_array($_GET['f'] ?? '', ['aguardando', 'encerrados'], true) ? $_GET['f'] : 'aovivo';
$P = rota_params();
$erroBanco = false;
$lista = $meus = $meusItens = [];
$ranking = ['gladiadores' => [], 'potes' => []];
$contra = null;
try {
    duelos_atualizar();
    $lista = duelos_listar($filtro);
    $ranking = duelos_ranking();
    $contagem = db()->query("SELECT SUM(status IN ('andamento', 'votacao')), SUM(status = 'aguardando') FROM duelos")->fetch(PDO::FETCH_NUM);
    if ($U) {
        $meus = duelos_da_pessoa((int) $U['id']);
        $meusItens = duelo_meus_itens((int) $U['id']);
    }
    if (isset(SIDES[$P['contra_lado'] ?? '']) && !empty($P['contra_num'])) {
        $st = db()->prepare("SELECT id, nome, numero, uf, lado FROM itens WHERE lado = ? AND numero = ? AND status = 'ativo' AND presente_token IS NULL");
        $st->execute([$P['contra_lado'], (int) $P['contra_num']]);
        $contra = $st->fetch() ?: null;
    }
} catch (PDOException $e) {
    $erroBanco = true; // migration 026 não rodada ou banco fora do ar
}
$abas = ['aovivo' => ['Ao vivo', (int) ($contagem[0] ?? 0)], 'aguardando' => ['Aguardando aceite', (int) ($contagem[1] ?? 0)], 'encerrados' => ['Encerrados', null]];

arena_inicio('Tretódromo: duelos de debate direita × esquerda | Pote Político',
    'Um contra um, três rodadas, a plateia decide. Desafie alguém do outro pote para um debate e vote nos melhores argumentos entre direita e esquerda.',
    url_base() . 'tretodromo',
    ['@type' => 'CollectionPage', 'name' => 'Tretódromo', 'url' => url_base() . 'tretodromo', 'inLanguage' => 'pt-BR',
     'description' => 'Duelos de debate entre direita e esquerda no Pote Político.']);
?>
<main class="arena">
  <section class="arena-hero">
    <div>
      <span class="arena-sobre"><?= arena_icone('espadas', 18) ?> A arena do Pote Político</span>
      <h1>Tretódromo</h1>
      <p>Um contra um. Três rodadas. A plateia decide quem sai com o troféu.</p>
    </div>
    <a class="btn btn-gold" href="#desafiar">⚔️ Lançar um desafio</a>
  </section>

  <?php if ($erroBanco): ?>
    <p class="arena-aviso">O Tretódromo está sendo preparado. Volte em instantes!</p>
  <?php else: ?>

  <?php if ($meus): ?>
    <section class="arena-meus">
      <h2>Seus duelos</h2>
      <ul>
        <?php foreach ($meus as $d):
            $eu = duelo_meu_lado($d, (int) $U['id']);
            $outro = $eu === 'a' ? 'b' : 'a';
            $acao = match (true) {
                $d['status'] === 'aguardando' && $eu === 'b' => ['Você foi desafiado! Aceita?', true],
                $d['status'] === 'aguardando' => ['Esperando ' . nome_proprio($d[$outro . '_nome']) . ' aceitar', false],
                $d['status'] === 'andamento' && $d['vez'] === $eu => ['Sua vez de responder!', true],
                $d['status'] === 'andamento' => ['Vez de ' . nome_proprio($d[$outro . '_nome']), false],
                default => ['Em votação: chame sua torcida', false],
            }; ?>
          <li class="<?= $acao[1] ? 'is-urgente' : '' ?>">
            <a href="<?= e(duelo_link((int) $d['id'])) ?>">
              <b><?= e($d['tema']) ?></b>
              <span>contra <?= e(nome_proprio($d[$outro . '_nome'])) ?> · <?= e($acao[0]) ?></span>
              <small><?= arena_icone('relogio', 14) ?> <?= e(duelo_falta($d['prazo_em'])) ?></small>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <div class="arena-grade">
    <section>
      <nav class="arena-abas" aria-label="Duelos">
        <?php foreach ($abas as $k => [$rot, $n]): ?>
          <a class="<?= $k === $filtro ? 'is-on' : '' ?>" href="tretodromo<?= $k === 'aovivo' ? '' : '?f=' . $k ?>"><?= $rot ?><?= $n !== null ? ' · ' . $n : '' ?></a>
        <?php endforeach; ?>
      </nav>
      <?php if ($lista): ?>
        <div class="arena-lista"><?php foreach ($lista as $d) echo arena_card($d); ?></div>
      <?php else: ?>
        <p class="arena-vazio"><?= $filtro === 'encerrados' ? 'Nenhum duelo terminou ainda.' : ($filtro === 'aguardando' ? 'Nenhum desafio esperando resposta.' : 'Nenhum duelo rolando agora. Que tal começar um?') ?></p>
      <?php endif; ?>
    </section>

    <aside class="arena-lateral">
      <h2><?= arena_icone('trofeu') ?> Gladiadores do mês</h2>
      <?php if ($ranking['gladiadores']): ?>
        <ol class="arena-ranking">
          <?php foreach ($ranking['gladiadores'] as $i => $g): ?>
            <li><b><?= $i + 1 ?></b><i class="bg-<?= e($g['lado']) ?>"></i>
              <a href="<?= e(url('perfil', ['lado' => $g['lado'], 'id' => (int) $g['numero']])) ?>"><?= e(nome_proprio($g['nome'])) ?></a>
              <span><?= (int) $g['v'] ?> V</span></li>
          <?php endforeach; ?>
        </ol>
      <?php else: ?>
        <p class="arena-vazio">O primeiro campeão ainda não apareceu. Pode ser você.</p>
      <?php endif; ?>
      <div class="arena-placar-potes">
        <small>Vitórias por pote nos últimos 30 dias</small>
        <div><b class="c-direita"><?= (int) ($ranking['potes']['direita'] ?? 0) ?></b><span>×</span><b class="c-esquerda"><?= (int) ($ranking['potes']['esquerda'] ?? 0) ?></b></div>
        <div class="arena-placar-rotulos"><span>🫒 Direita</span><span>Esquerda 🌶️</span></div>
      </div>
    </aside>
  </div>

  <section class="arena-desafiar" id="desafiar">
    <h2>⚔️ Lançar um desafio</h2>
    <p class="arena-sub">É grátis. Basta ter uma azeitona ou uma pimenta. O duelo é sempre contra alguém do outro pote.</p>
    <?php if (!$U): ?>
      <p class="arena-aviso"><a href="<?= e(url('entrar', ['lado' => 'direita', 'r' => 'tretodromo'])) ?>">Entre na sua conta</a> para desafiar alguém.</p>
    <?php elseif (!$meusItens): ?>
      <p class="arena-aviso">Para duelar, você precisa de um item no pote. Garanta sua <a href="direita">🫒 azeitona</a> ou sua <a href="esquerda">🌶️ pimenta</a>.</p>
    <?php else: ?>
      <form class="arena-form" data-desafio>
        <fieldset>
          <legend>1 · Com qual item você entra na arena</legend>
          <div class="arena-escolha">
            <?php foreach ($meusItens as $i => $it): ?>
              <label><input type="radio" name="meu" value="<?= (int) $it['id'] ?>" data-lado="<?= e($it['lado']) ?>" <?= ($contra ? $contra['lado'] !== $it['lado'] : $i === 0) ? 'checked' : '' ?>>
                <span><?= arena_item($it['lado'], 28) ?> <?= e(nome_proprio($it['nome'])) ?> <small><?= e(SIDES[$it['lado']]['name']) ?> · #<?= str_pad((string) $it['numero'], 4, '0', STR_PAD_LEFT) ?></small></span></label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <fieldset>
          <legend>2 · Quem você vai enfrentar <small data-oponente-pote></small></legend>
          <input type="hidden" name="oponente" value="<?= (int) ($contra['id'] ?? 0) ?>">
          <div class="arena-oponente" data-oponente-escolhido <?= $contra ? '' : 'hidden' ?>><?php if ($contra): ?><?= arena_item($contra['lado'], 28) ?> <b><?= e(nome_proprio($contra['nome'])) ?></b> <small><?= e($contra['uf']) ?> · #<?= str_pad((string) $contra['numero'], 4, '0', STR_PAD_LEFT) ?></small><?php endif; ?></div>
          <input type="search" data-busca placeholder="Busque pelo nome…" autocomplete="off" aria-label="Buscar oponente pelo nome">
          <ul class="arena-resultados" data-resultados hidden></ul>
        </fieldset>
        <label class="arena-campo">3 · O tema da treta
          <input name="tema" maxlength="<?= DUELO_TEMA_MAX ?>" required placeholder="Ex.: O Estado deve privatizar os Correios?">
        </label>
        <label class="arena-campo">4 · Seu argumento de abertura
          <textarea name="argumento" rows="5" maxlength="<?= DUELO_ARG_MAX ?>" required placeholder="Abra o duelo com o seu melhor argumento."></textarea>
          <small class="arena-contador" data-contador><?= '0 / ' . DUELO_ARG_MAX ?></small>
        </label>
        <p class="arena-regras">Seu oponente tem <?= DUELO_HORAS ?> h para aceitar. Depois são 3 rodadas, com <?= DUELO_HORAS ?> h para cada resposta (quem não responde perde por W.O.), e <?= DUELO_HORAS ?> h de votação da plateia.</p>
        <button class="btn btn-gold btn-block" type="submit">⚔️ Desafiar</button>
      </form>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</main>
<?php arena_fim();
