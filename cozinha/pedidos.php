<?php
// Painel: pagamentos Pix para conferir no extrato. Aprovar = entra no pote; negar = pedido cancelado.
// Confira pelo nome do titular + valor + horário. A página se atualiza sozinha a cada minuto.
require dirname(__DIR__) . '/includes/config.php';
require dirname(__DIR__) . '/includes/admin_auth.php';
require dirname(__DIR__) . '/includes/pedidos.php';

if (!admin_logged_in()) {
    header('Location: ./'); // o login fica na página das enquetes
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok($_POST['csrf'] ?? null)) {
        logar('aviso', 'painel', 'painel_csrf_invalido', 'Formulário do painel com token inválido/expirado', ['acao' => $_POST['acao'] ?? null]);
        flash('Sessão expirada. Tente de novo.', 'erro');
        header('Location: pedidos');
        exit;
    }
    $id = (int) ($_POST['id'] ?? 0);
    try {
        switch ($_POST['acao'] ?? '') {
            case 'aprovar':
                pedido_aprovar($id);
                logar('info', 'pedido', 'pedido_aprovado', "Pedido #$id aprovado no painel", ['id' => $id], null, true);
                flash('Pagamento aprovado. Já está no pote.');
                break;
            case 'negar':
                pedido_negar($id);
                logar('info', 'pedido', 'pedido_negado', "Pedido #$id negado no painel", ['id' => $id], null, true);
                flash('Pedido negado.');
                break;
        }
    } catch (DomainException $ex) {
        logar('aviso', 'painel', 'pedido_acao_recusada', $ex->getMessage(), ['id' => $id, 'acao' => $_POST['acao'] ?? null], null, true);
        flash($ex->getMessage(), 'erro');
    } catch (PDOException $ex) {
        logar('erro', 'painel', 'painel_erro_banco', $ex->getMessage(), ['id' => $id], null, true, 500);
        flash('Erro no banco: ' . $ex->getMessage(), 'erro');
    }
    header('Location: pedidos');
    exit;
}

$flash = flash();
$dbErro = null;
$g = ['pendentes' => [], 'expirados' => [], 'resolvidos' => []];
try {
    $g = pedidos_painel();
} catch (PDOException $ex) {
    $dbErro = str_contains($ex->getMessage(), 'codigo')
        ? 'Falta a tabela nova de pedidos. Rode: php database/migrate.php'
        : 'Não conectou no banco: ' . $ex->getMessage();
}

function ha_quanto(int $min): string
{
    if ($min < 1) {
        return 'agora';
    }
    if ($min < 60) {
        return "há $min min";
    }
    return $min < 1440 ? 'há ' . intdiv($min, 60) . ' h' . ($min % 60 ? ' ' . ($min % 60) . ' min' : '') : 'há ' . intdiv($min, 1440) . ' dia(s)';
}

function acao_pedido(string $acao, int $id, string $label, string $classe, string $confirmar = ''): string
{
    return '<form method="post" class="adm-inline"' . ($confirmar ? ' data-confirm="' . e($confirmar) . '"' : '') . '>'
        . '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'
        . '<input type="hidden" name="acao" value="' . $acao . '"><input type="hidden" name="id" value="' . $id . '">'
        . '<button class="btn ' . $classe . ' btn-sm">' . $label . '</button></form>';
}

/** Um pedido no painel. $acoes = mostra Aprovar/Negar. */
function cartao_pedido(array $p, bool $acoes): string
{
    $S = side($p['lado']);
    $atrasado = $p['status'] === 'pendente' && (int) $p['minutos'] >= PEDIDO_ALERTA_MIN;
    $badge = ['pendente' => 'Aguardando', 'expirado' => 'Expirado', 'pago' => 'Aprovado', 'cancelado' => 'Negado'][$p['status']] ?? $p['status'];
    $unid = array_sum(array_column($p['linhas'], 'quantidade'));
    $renova = $p['linhas'] && $p['linhas'][0]['renova_item_id'];
    ob_start(); ?>
    <article class="adm-card adm-pedido<?= $atrasado ? ' is-late' : '' ?>">
      <div class="adm-card-top">
        <span class="adm-badge adm-badge-<?= e($p['status']) ?>"><?= e($badge) ?></span>
        <span class="adm-where"><?= $S['emoji'] ?> <?= e($S['name']) ?> · pedido <b><?= e($p['codigo']) ?></b></span>
        <span class="adm-quando<?= $atrasado ? ' is-late' : '' ?>" title="<?= e(date('d/m/Y H:i', strtotime($p['criado_em']))) ?>">
          <?= date('d/m H:i', strtotime($p['criado_em'])) ?> · <?= e(ha_quanto((int) $p['minutos'])) ?>
        </span>
      </div>
      <div class="adm-pedido-main">
        <div class="adm-pedido-valor"><?= money($p['total_centavos'] / 100) ?></div>
        <div class="adm-pedido-quem">
          <small>Titular que vai pagar</small>
          <b><?= e($p['pagador_nome'] ?: '—') ?></b>
          <small><?= e($p['usuario_nome']) ?> · <?= e($p['email']) ?></small>
        </div>
      </div>
      <ul class="adm-pedido-itens">
        <?php foreach ($p['linhas'] as $l): ?>
          <li>
            <?= $renova ? '↻ Renovação: ' : (int) $l['quantidade'] . '× ' ?><?= !empty($l['presente']) ? '🎁 ' : '' ?><b><?= e($l['tipo_nome']) ?></b>
            · <?= e($l['nome_certificado']) ?> (<?= e($l['cidade']) ?>/<?= e($l['uf']) ?>)
            <q><?= e($l['frase']) ?></q>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($acoes): ?>
        <div class="adm-actions">
          <?= acao_pedido('aprovar', (int) $p['id'], '✓ Aprovar · caiu no extrato', 'btn-gold',
              'Aprovar o pedido ' . $p['codigo'] . ' de ' . money($p['total_centavos'] / 100) . ' (' . ($p['pagador_nome'] ?: '') . ')? '
              . ($renova ? 'A renovação passa a valer.' : $unid . ' ' . ($unid > 1 ? $S['items'] : $S['item']) . ' entram no pote.')) ?>
          <?= acao_pedido('negar', (int) $p['id'], 'Negar', 'btn-ghost',
              'Negar o pedido ' . $p['codigo'] . '? A compra some do navegador da pessoa.') ?>
        </div>
      <?php endif; ?>
    </article>
    <?php return ob_get_clean();
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta http-equiv="refresh" content="60">
  <title><?= $g['pendentes'] ? '(' . count($g['pendentes']) . ') ' : '' ?>Painel · Pagamentos</title>
  <link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">💰</text></svg>') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-page">
  <header class="topbar adm-topbar">
    <a class="brand" href="./"><span>💰 Painel <b>Pagamentos</b></span></a>
    <nav>
      <a href="./">🗳️ Enquetes</a>
      <a href="frases">💬 Frases</a>
      <a href="pedidos">💰 Pagamentos</a>
      <a href="logs">📜 Logs</a>
      <a href="atualizar">🚀 Atualizar</a>
      <a href="../<?= e(url('pote', ['lado' => 'esquerda'])) ?>" target="_blank">🌶️ Ver Pimenta</a>
      <a href="../<?= e(url('pote', ['lado' => 'direita'])) ?>" target="_blank">🫒 Ver Direita</a>
    </nav>
    <form method="post" action="./" class="adm-inline">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <button class="btn btn-ghost btn-sm" name="acao" value="sair">Sair</button>
    </form>
  </header>

  <main class="adm-main">
    <?php if ($flash): ?>
      <p class="adm-flash adm-flash-<?= e($flash[0]) ?>"><?= e($flash[1]) ?></p>
    <?php endif; ?>

    <?php if (!pix_configurado()): ?>
      <p class="adm-flash adm-flash-erro">Pix desligado: defina <code>PIX_CHAVE</code>, <code>PIX_NOME</code> e <code>PIX_CIDADE</code> no <code>.env</code>. Sem isso ninguém consegue comprar.</p>
    <?php endif; ?>

    <?php if ($dbErro): ?>
      <p class="adm-flash adm-flash-erro"><?= e($dbErro) ?></p>
    <?php else: ?>
      <section class="adm-section">
        <div class="adm-head">
          <h2>Aguardando conferência</h2>
          <span class="adm-rule"><?= count($g['pendentes']) ?></span>
        </div>
        <p class="adm-desc">Confira no extrato pelo <b>nome do titular</b>, o <b>valor</b> e o <b>horário</b>. Em destaque: há mais de <?= PEDIDO_ALERTA_MIN ?> minutos.
          Sem resposta em <?= PEDIDO_EXPIRA_HORAS ?> h, o pedido expira sozinho (e ainda dá para aprovar, se o Pix cair atrasado).</p>
        <?php if (!$g['pendentes']): ?>
          <div class="adm-card adm-empty">Nenhum pagamento esperando. 🎉</div>
        <?php endif; ?>
        <div class="adm-history"><?php foreach ($g['pendentes'] as $p) echo cartao_pedido($p, true); ?></div>
      </section>

      <?php if ($g['expirados']): ?>
        <section class="adm-section">
          <div class="adm-head"><h2>Expirados</h2><span class="adm-rule">últimos 7 dias</span></div>
          <div class="adm-history"><?php foreach ($g['expirados'] as $p) echo cartao_pedido($p, true); ?></div>
        </section>
      <?php endif; ?>

      <section class="adm-section">
        <div class="adm-head"><h2>Resolvidos</h2><span class="adm-rule">últimos 30</span></div>
        <?php if (!$g['resolvidos']): ?>
          <div class="adm-card adm-empty">Nada por aqui ainda.</div>
        <?php endif; ?>
        <div class="adm-history"><?php foreach ($g['resolvidos'] as $p) echo cartao_pedido($p, false); ?></div>
      </section>
    <?php endif; ?>
  </main>

  <script src="../assets/js/admin.js"></script>
</body>
</html>
