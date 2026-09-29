<?php
// Painel: atualizar o sistema com o que está no GitHub (ver includes/deploy.php). Um botão, com confirmação.
require dirname(__DIR__) . '/includes/config.php';
require dirname(__DIR__) . '/includes/admin_auth.php';
require dirname(__DIR__) . '/includes/deploy.php';

if (!admin_logged_in()) {
    header('Location: ./'); // /cozinha/ manda para o login
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok($_POST['csrf'] ?? null)) {
        logar('aviso', 'painel', 'painel_csrf_invalido', 'Formulário do painel com token inválido/expirado', ['acao' => 'atualizar']);
        flash('Sessão expirada. Tente de novo.', 'erro');
    } else {
        $r = deploy_executar();
        $_SESSION['deploy_resultado'] = $r;
        flash($r['ok'] ? 'Sistema atualizado.' : 'A atualização não terminou. Veja o que aconteceu abaixo.', $r['ok'] ? 'ok' : 'erro');
    }
    header('Location: atualizar');
    exit;
}

$flash = flash();
$resultado = $_SESSION['deploy_resultado'] ?? null;
unset($_SESSION['deploy_resultado']);
$cfg = deploy_config();
$atual = deploy_estado();
$remoto = null;
$erroRemoto = null;
try {
    $remoto = deploy_commit_remoto();
} catch (Throwable $e) {
    $erroRemoto = $e->getMessage();
}
$emDia = $remoto && $atual && $atual['commit'] === $remoto['sha'];
$curto = fn(?string $sha) => $sha ? substr($sha, 0, 7) : '—';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Painel · Atualizar sistema</title>
  <?= pwa_tags_admin() ?>
  <link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">🚀</text></svg>') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../<?= asset('assets/css/style.css') ?>">
</head>
<body class="admin-page">
  <header class="topbar adm-topbar">
    <a class="brand" href="./"><span>🚀 Painel <b>Atualizar</b></span></a>
    <button type="button" class="adm-menu-btn" aria-label="Abrir menu" aria-expanded="false" aria-controls="adm-nav" data-adm-menu><span></span></button>
    <nav id="adm-nav">
      <a href="enquetes">🗳️ Enquetes</a>
      <a href="frases">💬 Frases</a>
      <a href="pedidos">💰 Pagamentos</a>
      <a href="logs">📜 Logs</a>
      <a href="atualizar">🚀 Atualizar</a>
    </nav>
    <form method="post" action="enquetes" class="adm-inline">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <button class="btn btn-ghost btn-sm" name="acao" value="sair">Sair</button>
    </form>
  </header>

  <main class="adm-main">
    <?php if ($flash): ?>
      <p class="adm-flash adm-flash-<?= e($flash[0]) ?>"><?= e($flash[1]) ?></p>
    <?php endif; ?>

    <section class="adm-section">
      <div class="adm-head"><h2>Atualizar o sistema</h2><span class="adm-rule"><?= e($cfg['repo']) ?> · <?= e($cfg['branch']) ?></span></div>

      <div class="adm-card deploy-card">
        <div class="deploy-versoes">
          <div>
            <small>No servidor</small>
            <b><?= e($curto($atual['commit'] ?? null)) ?></b>
            <span><?= $atual ? e($atual['mensagem'] ?? '') . '<br>atualizado em ' . date('d/m/Y H:i', strtotime($atual['data'])) : 'Nenhuma atualização feita por aqui ainda.' ?></span>
          </div>
          <div>
            <small>No GitHub</small>
            <?php if ($remoto): ?>
              <b><?= e($curto($remoto['sha'])) ?></b>
              <span><?= e($remoto['mensagem']) ?><br><?= e($remoto['autor']) ?> · <?= $remoto['data'] ? date('d/m/Y H:i', strtotime($remoto['data'])) : '' ?></span>
            <?php else: ?>
              <b>?</b><span class="deploy-erro"><?= e((string) $erroRemoto) ?></span>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($emDia): ?>
          <p class="adm-desc">✓ O servidor já está com o último commit.</p>
        <?php endif; ?>
        <form method="post" data-confirm="Atualizar o site com o commit <?= e($curto($remoto['sha'] ?? null)) ?> do GitHub? Os arquivos são trocados e as migrations pendentes rodam.">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <button class="btn btn-gold" <?= $remoto ? '' : 'disabled' ?>><?= $emDia ? 'Aplicar de novo' : '🚀 Atualizar agora' ?></button>
        </form>
        <p class="adm-desc deploy-nota">Baixa o código da branch <b><?= e($cfg['branch']) ?></b>, troca os arquivos, apaga os que saíram do repositório e roda as migrations.
          Nunca mexe em <code>.env</code> e <code>uploads/</code> (a <code>vendor/</code> vem junto do git). Tudo fica registrado em <a href="logs?categoria=deploy">Logs → deploy</a>.</p>
        <p class="adm-desc deploy-nota">Configuração (.env) lida de: <code><?= e(env_arquivo() ?? 'nenhum .env encontrado') ?></code></p>
      </div>

      <?php if ($resultado): ?>
        <div class="adm-card deploy-resultado <?= $resultado['ok'] ? 'is-ok' : 'is-erro' ?>">
          <h3><?= $resultado['ok'] ? 'Última atualização' : 'A atualização parou' ?></h3>
          <ol><?php foreach ($resultado['linhas'] as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ol>
        </div>
      <?php endif; ?>
    </section>
  </main>
  <script src="../<?= asset('assets/js/admin.js') ?>"></script>
</body>
</html>
