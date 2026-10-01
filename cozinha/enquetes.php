<?php
// Painel do administrador: enquetes (uma no ar por vez). Também é a tela de login e cuida do "Sair"
// (o /cozinha/ mostra esta página a quem não entrou; quem entrou vai para Visitas).
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/enquetes.php';

$erros = [];
$old = [];

// ---------- ações (POST → redirect → GET) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if (!csrf_ok($_POST['csrf'] ?? null)) {
        logar('aviso', 'painel', 'painel_csrf_invalido', 'Formulário do painel com token inválido/expirado', ['acao' => $_POST['acao'] ?? null]);
        flash('Sessão expirada. Tente de novo.', 'erro');
        header('Location: ./');
        exit;
    }

    if ($acao === 'login') {
        $erro = admin_login((string) ($_POST['senha'] ?? ''));
        if ($erro) {
            flash($erro, 'erro');
        }
        header('Location: ./');
        exit;
    }

    if (!admin_logged_in()) {
        header('Location: ./');
        exit;
    }

    try {
        $id = (int) ($_POST['id'] ?? 0);
        switch ($acao) {
            case 'sair':
                admin_logout();
                header('Location: ./');
                exit;
            case 'criar':
                [$dados, $erros] = enquete_validar($_POST);
                if (!$erros) {
                    $publicar = ($_POST['modo'] ?? '') === 'publicar';
                    enquete_criar($dados, $publicar);
                    logar('info', 'painel', $publicar ? 'enquete_publicada' : 'enquete_rascunho', 'Enquete: ' . $dados['pergunta'], ['lado' => $dados['lado'] ?? null], null, true);
                    flash($publicar ? 'Enquete publicada! Ela já aparece nos potes.' : 'Rascunho salvo.');
                    header('Location: enquetes');
                    exit;
                }
                $old = $_POST; // mostra o formulário de novo com os erros
                break;
            case 'publicar':
                enquete_publicar($id);
                logar('info', 'painel', 'enquete_publicada', "Enquete #$id publicada", ['id' => $id], null, true);
                flash('Enquete no ar. A anterior foi encerrada.');
                header('Location: enquetes');
                exit;
            case 'avisar': // notificação do app: "enquete nova" para os inscritos do(s) pote(s) onde ela aparece
                require_once dirname(__DIR__) . '/includes/push.php';
                $e = enquete_ativa();
                if (!$e) {
                    flash('Nenhuma enquete no ar para avisar.', 'erro');
                    header('Location: enquetes');
                    exit;
                }
                $enviados = 0;
                foreach ($e['lado'] ? [$e['lado']] : array_keys(SIDES) as $l) {
                    $enviados += push_avisar('enquete', '🗳️ Enquete nova no pote!', $e['pergunta'], $l . '#enquete', $l)['enviados'];
                }
                push_config('enquete_avisada', (string) $e['id']);
                flash($enviados ? "🔔 Aviso enviado para $enviados aparelho(s)." : 'Ninguém inscrito nos avisos ainda.');
                header('Location: enquetes');
                exit;
            case 'encerrar':
                enquete_encerrar($id);
                logar('info', 'painel', 'enquete_encerrada', "Enquete #$id encerrada", ['id' => $id], null, true);
                flash('Enquete encerrada.');
                header('Location: enquetes');
                exit;
            case 'excluir':
                enquete_excluir($id);
                logar('info', 'painel', 'enquete_excluida', "Enquete #$id excluída", ['id' => $id], null, true);
                flash('Enquete excluída.');
                header('Location: enquetes');
                exit;
        }
    } catch (PDOException $ex) {
        logar('erro', 'painel', 'painel_erro_banco', $ex->getMessage(), ['acao' => $acao, 'id' => $id], null, true, 500);
        flash('Erro no banco: ' . $ex->getMessage(), 'erro');
        header('Location: enquetes');
        exit;
    }
}

$logado = admin_logged_in();
$flash = flash();

$dbErro = null;
$ativa = null;
$historico = [];
$push = null;
if ($logado) {
    try {
        $todas = enquetes_listar();
        foreach ($todas as $e) {
            $e['status'] === 'ativa' ? $ativa = $e : $historico[] = $e;
        }
        try { // notificações do app (migration 025): sem as tabelas, o painel segue sem o botão
            require_once dirname(__DIR__) . '/includes/push.php';
            $push = push_resumo() + ['avisada' => push_config('enquete_avisada')];
        } catch (Throwable $ex) {
            $push = null;
        }
    } catch (PDOException $ex) {
        $dbErro = str_contains($ex->getMessage(), "doesn't exist")
            ? 'As tabelas de enquete ainda não existem. Rode: php database/migrate.php'
            : 'Não conectou no banco: ' . $ex->getMessage();
    }
}

function lado_label(?string $lado): string
{
    return $lado === null ? '🌶️ × 🫒 Nos dois potes (duelo)' : side($lado)['emoji'] . ' Só em ' . side($lado)['name'];
}

function resultado_label(string $r): string
{
    return ['sempre' => 'Resultado sempre visível', 'apos_votar' => 'Resultado depois de votar', 'apos_encerrar' => 'Resultado só no fim'][$r] ?? $r;
}

function data_br(?string $dt): string
{
    return $dt ? date('d/m/Y H:i', strtotime($dt)) : '—';
}

// barras de resultado; no duelo, cada barra é dividida entre os dois potes
function barras(array $e): string
{
    $total = max(1, $e['total_votos']);
    $html = '<ul class="adm-bars">';
    foreach ($e['opcoes'] as $o) {
        $pct = round($o['votos'] / $total * 100);
        $html .= '<li><div class="adm-bar-top"><span>' . e($o['texto']) . '</span><b>' . $pct . '% <small>(' . num($o['votos']) . ')</small></b></div><div class="adm-bar">';
        if ($e['lado'] === null) {
            foreach (['esquerda', 'direita'] as $s) {
                $n = $o['por_lado'][$s] ?? 0;
                $html .= '<i style="width:' . round($n / $total * 100, 1) . '%;background:' . side($s)['theme']['gold'] . '" title="' . e(side($s)['name']) . ': ' . $n . '"></i>';
            }
        } else {
            $html .= '<i style="width:' . $pct . '%;background:' . side($e['lado'])['theme']['gold'] . '"></i>';
        }
        $html .= '</div></li>';
    }
    $html .= '</ul>';
    if ($e['lado'] === null) {
        $html .= '<p class="adm-legend">';
        foreach (['esquerda', 'direita'] as $s) {
            $html .= '<span><i style="background:' . side($s)['theme']['gold'] . '"></i>' . e(side($s)['name']) . ': <b>' . num($e['votos_por_lado'][$s] ?? 0) . '</b> votos</span>';
        }
        $html .= '</p>';
    }
    return $html;
}

function acao_form(string $acao, int $id, string $label, string $classe, string $confirmar = ''): string
{
    return '<form method="post" class="adm-inline"' . ($confirmar ? ' data-confirm="' . e($confirmar) . '"' : '') . '>'
        . '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'
        . '<input type="hidden" name="acao" value="' . $acao . '"><input type="hidden" name="id" value="' . $id . '">'
        . '<button class="btn ' . $classe . ' btn-sm">' . $label . '</button></form>';
}

$opcoesForm = array_values(array_filter((array) ($old['opcoes'] ?? []), 'strlen')) ?: ['', ''];
while (count($opcoesForm) < 2) {
    $opcoesForm[] = '';
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Painel · Enquetes</title>
  <?= pwa_tags_admin() ?>
  <link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">🗳️</text></svg>') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../<?= asset('assets/css/style.css') ?>">
</head>
<body class="admin-page">
  <header class="topbar adm-topbar">
    <a class="brand" href="./"><span>🗳️ Painel <b>Enquetes</b></span></a>
    <button type="button" class="adm-menu-btn" aria-label="Abrir menu" aria-expanded="false" aria-controls="adm-nav" data-adm-menu><span></span></button>
    <nav id="adm-nav">
      <?php if ($logado): ?><a href="visitas">📊 Visitas</a> <a href="frases">💬 Frases</a> <a href="pedidos">🧾 Pedidos</a>
      <a href="logs">📜 Logs</a>
      <a href="atualizar">🚀 Atualizar</a><?php endif; ?>
      <a href="../<?= e(url('pote', ['lado' => 'esquerda'])) ?>" target="_blank">🌶️ Ver Pimenta</a>
      <a href="../<?= e(url('pote', ['lado' => 'direita'])) ?>" target="_blank">🫒 Ver Direita</a>
    </nav>
    <?php if ($logado): ?>
      <form method="post" class="adm-inline">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <button class="btn btn-ghost btn-sm" name="acao" value="sair">Sair</button>
      </form>
    <?php endif; ?>
  </header>

  <main class="adm-main">
    <?php if ($flash): ?>
      <p class="adm-flash adm-flash-<?= e($flash[0]) ?>"><?= e($flash[1]) ?></p>
    <?php endif; ?>

    <?php if (!$logado): ?>
      <section class="adm-login">
        <h1>Painel do administrador</h1>
        <?php if (!admin_password_configured()): ?>
          <p class="adm-flash adm-flash-erro">Painel desativado. Defina <code>ADMIN_PASSWORD</code> no arquivo <code>.env</code> e recarregue.</p>
        <?php else: ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label class="field"><span>Senha</span><input type="password" name="senha" required autofocus autocomplete="current-password"></label>
            <button class="btn btn-gold btn-block" name="acao" value="login">Entrar</button>
          </form>
        <?php endif; ?>
      </section>

    <?php elseif ($dbErro): ?>
      <p class="adm-flash adm-flash-erro"><?= e($dbErro) ?></p>

    <?php else: ?>
      <!-- enquete no ar -->
      <section class="adm-section">
        <div class="adm-head"><h2>Enquete no ar</h2><span class="adm-rule">Só uma por vez</span></div>
        <?php if ($ativa): ?>
          <article class="adm-card adm-live">
            <div class="adm-card-top">
              <span class="adm-badge adm-badge-ativa">● No ar</span>
              <span class="adm-where"><?= e(lado_label($ativa['lado'])) ?></span>
            </div>
            <h3><?= e($ativa['pergunta']) ?></h3>
            <?php if ($ativa['descricao']): ?><p class="adm-desc"><?= e($ativa['descricao']) ?></p><?php endif; ?>
            <?= barras($ativa) ?>
            <div class="adm-meta">
              <span><b><?= num($ativa['total_votos']) ?></b> votos</span>
              <span>Publicada em <?= data_br($ativa['publicada_em']) ?></span>
              <span><?= $ativa['termina_em'] ? 'Encerra em ' . data_br($ativa['termina_em']) : 'Sem data para encerrar' ?></span>
              <span><?= e(resultado_label($ativa['resultado'])) ?></span>
            </div>
            <div class="adm-actions">
              <?php if ($push !== null): $jaAvisou = $push['avisada'] === (string) $ativa['id']; ?>
                <?= acao_form('avisar', (int) $ativa['id'], $jaAvisou ? '🔔 Avisar de novo' : '🔔 Avisar inscritos (' . num($push['inscritos']) . ')', $jaAvisou ? 'btn-ghost' : 'btn-gold',
                    ($jaAvisou ? 'Você JÁ avisou desta enquete. ' : '') . 'Mandar a notificação "Enquete nova" para ' . $push['inscritos'] . ' aparelho(s)?') ?>
              <?php endif; ?>
              <?= acao_form('encerrar', (int) $ativa['id'], 'Encerrar agora', 'btn-ghost', 'Encerrar esta enquete? Ela sai dos potes.') ?>
            </div>
            <?php if ($push && $push['avisos']): ?>
              <p class="adm-meta">🔔 Últimos avisos:
                <?php foreach (array_slice($push['avisos'], 0, 3) as $a): ?>
                  <span><?= data_br($a['criado_em']) ?> · <?= e($a['tipo']) ?><?= $a['lado'] ? ' (' . e($a['lado']) . ')' : '' ?>: <b><?= num($a['enviados']) ?></b> enviados, <b><?= num($a['recebidos']) ?></b> recebidos, <b><?= num($a['cliques']) ?></b> abriram</span>
                <?php endforeach; ?>
              </p>
            <?php endif; ?>
          </article>
        <?php else: ?>
          <div class="adm-card adm-empty">Nenhuma enquete no ar. Crie uma abaixo.</div>
        <?php endif; ?>
      </section>

      <!-- nova enquete -->
      <section class="adm-section" id="nova">
        <div class="adm-head"><h2>Nova enquete</h2></div>
        <form method="post" class="adm-card adm-form" id="form-enquete">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="acao" value="criar">
          <?php if ($erros): ?>
            <ul class="adm-flash adm-flash-erro"><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
          <?php endif; ?>

          <label class="field"><span>Pergunta</span>
            <input name="pergunta" maxlength="200" required value="<?= e($old['pergunta'] ?? '') ?>" placeholder="Ex.: Qual tema deve ser o duelo da semana?">
          </label>
          <label class="field"><span>Descrição <small>(opcional)</small></span>
            <textarea name="descricao" maxlength="500" rows="2" placeholder="Contexto rápido para quem vai votar"><?= e($old['descricao'] ?? '') ?></textarea>
          </label>

          <div class="field">
            <span>Opções <small>(de <?= ENQUETE_MIN_OPCOES ?> a <?= ENQUETE_MAX_OPCOES ?>)</small></span>
            <ol class="adm-options" id="adm-options" data-max="<?= ENQUETE_MAX_OPCOES ?>" data-min="<?= ENQUETE_MIN_OPCOES ?>">
              <?php foreach ($opcoesForm as $i => $op): ?>
                <li><input name="opcoes[]" maxlength="120" value="<?= e($op) ?>" placeholder="Opção <?= $i + 1 ?>" <?= $i < 2 ? 'required' : '' ?>><button type="button" class="adm-remove" aria-label="Remover opção">×</button></li>
              <?php endforeach; ?>
            </ol>
            <button type="button" class="btn btn-ghost btn-sm" id="adm-add-option">+ Adicionar opção</button>
          </div>

          <div class="field">
            <span>Onde aparece</span>
            <div class="adm-choices">
              <?php $ladoOld = $old['lado'] ?? ''; ?>
              <label><input type="radio" name="lado" value="" <?= $ladoOld === '' ? 'checked' : '' ?>><span><b>🌶️ × 🫒 Nos dois potes</b><small>Duelo: o resultado mostra quanto cada lado votou</small></span></label>
              <?php foreach (['esquerda', 'direita'] as $s): ?>
                <label><input type="radio" name="lado" value="<?= $s ?>" <?= $ladoOld === $s ? 'checked' : '' ?>><span><b><?= side($s)['emoji'] ?> Só em <?= e(side($s)['name']) ?></b><small>Só quem está nesse pote vê</small></span></label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="adm-row">
            <label class="field"><span>Quando mostrar o resultado</span>
              <select name="resultado">
                <?php foreach (['apos_votar', 'sempre', 'apos_encerrar'] as $r): ?>
                  <option value="<?= $r ?>" <?= ($old['resultado'] ?? 'apos_votar') === $r ? 'selected' : '' ?>><?= e(resultado_label($r)) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="field"><span>Encerrar automaticamente em <small>(opcional)</small></span>
              <input type="datetime-local" name="termina_em" value="<?= e($old['termina_em'] ?? '') ?>" min="<?= date('Y-m-d\TH:i') ?>">
            </label>
          </div>

          <?php if ($ativa): ?>
            <p class="adm-warn">⚠️ Já existe uma enquete no ar. Ao publicar esta, <b>“<?= e($ativa['pergunta']) ?>”</b> será encerrada.</p>
          <?php endif; ?>
          <div class="adm-actions">
            <button class="btn btn-gold" name="modo" value="publicar">Publicar agora</button>
            <button class="btn btn-ghost" name="modo" value="rascunho" formnovalidate>Salvar rascunho</button>
          </div>
        </form>
      </section>

      <!-- histórico -->
      <section class="adm-section">
        <div class="adm-head"><h2>Rascunhos e anteriores</h2><span class="adm-rule"><?= count($historico) ?></span></div>
        <?php if (!$historico): ?>
          <div class="adm-card adm-empty">Nada por aqui ainda.</div>
        <?php endif; ?>
        <div class="adm-history">
          <?php foreach ($historico as $e): ?>
            <article class="adm-card">
              <div class="adm-card-top">
                <span class="adm-badge adm-badge-<?= $e['status'] ?>"><?= $e['status'] === 'rascunho' ? 'Rascunho' : 'Encerrada' ?></span>
                <span class="adm-where"><?= e(lado_label($e['lado'])) ?></span>
              </div>
              <h3><?= e($e['pergunta']) ?></h3>
              <?= barras($e) ?>
              <div class="adm-meta">
                <span><b><?= num($e['total_votos']) ?></b> votos</span>
                <span><?= $e['status'] === 'rascunho' ? 'Criada em ' . data_br($e['criado_em']) : 'Encerrada em ' . data_br($e['encerrada_em']) ?></span>
              </div>
              <div class="adm-actions">
                <?= acao_form('publicar', (int) $e['id'], $e['status'] === 'rascunho' ? 'Publicar' : 'Reabrir',
                    'btn-gold', $ativa ? 'Publicar esta enquete? A que está no ar será encerrada.' : '') ?>
                <?= acao_form('excluir', (int) $e['id'], 'Excluir', 'btn-ghost', 'Excluir esta enquete e todos os votos? Não dá para desfazer.') ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
  </main>

  <script src="../<?= asset('assets/js/admin.js') ?>"></script>
</body>
</html>
