<?php
// Avisos por e-mail de comentário: /avisos?c=… (link cifrado que vai no rodapé de cada aviso; ver includes/avisos.php).
// Abrir o link só mostra a pergunta; o botão (POST) é que desliga — robô de antivírus que "clica" nos links não desliga nada.
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/avisos.php';
require __DIR__ . '/includes/limite.php';

$P = rota_params();
$uid = (int) ($P['u'] ?? 0);
$erro = null;
$feito = null;
$u = null;

try {
  if ($uid > 0) {
    $st = db()->prepare('SELECT u.id, u.nome, u.avisos_email, (SELECT lado FROM itens WHERE usuario_id = u.id ORDER BY criado_em DESC LIMIT 1) AS lado
                         FROM usuarios u WHERE u.id = ?');
    $st->execute([$uid]);
    $u = $st->fetch() ?: null;
  }
  if ($u && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_publico_ok($_POST['csrf'] ?? null)) {
      $erro = 'A página ficou aberta tempo demais. Tente de novo.';
    } elseif (!limite_ok('avisos', 10)) {
      $erro = 'Muitas tentativas seguidas. Espere um minuto e tente de novo.';
    } else {
      $receber = ($_POST['receber'] ?? '') === '1';
      avisos_definir($uid, $receber);
      $u['avisos_email'] = (int) $receber;
      $feito = $receber ? 'ligados' : 'desligados';
    }
  }
} catch (PDOException $ex) {
  logar('erro', 'sistema', 'erro_tratado', 'avisos: ' . $ex->getMessage(), [], null, false, 500);
  $erro = 'Não deu para abrir agora. Tente de novo em instantes.';
}

$S = side($u['lado'] ?? 'direita');
$items = [];
$csrf = csrf_publico();
$pageTitle = 'Avisos por e-mail · ' . $S['name'];
$liga = $u && !(int) $u['avisos_email'];
require __DIR__ . '/includes/header.php';
?>
<main class="auth-page">
  <section class="auth-card">
    <div class="auth-emoji"><?= item_svg($S, $S['logo'], 'auth-item', 1.4) ?></div>
    <?php if (!$u): ?>
      <h1>Link incompleto</h1>
      <p>Abra de novo o link que veio no e-mail.</p>
    <?php else: ?>
      <h1>Avisos de comentário</h1>
      <?php if ($erro): ?><p class="auth-error"><?= e($erro) ?></p><?php endif; ?>
      <?php if ($feito === 'desligados'): ?>
        <p>Pronto, <?= e(explode(' ', nome_proprio($u['nome']))[0]) ?>: você não recebe mais e-mails quando comentarem nas suas publicações.</p>
        <p class="auth-small">Mas olha: o outro pote vai continuar falando de você. Só que agora você nem vai ficar sabendo. 🤷</p>
      <?php elseif ($feito === 'ligados'): ?>
        <p>Avisos ligados! Quando alguém comentar, você fica sabendo na hora. <?= $S['emoji'] ?></p>
      <?php elseif ($liga): ?>
        <p>Seus avisos estão desligados. Quer voltar a saber quando comentarem nas suas publicações?</p>
      <?php else: ?>
        <p>Parar de receber e-mail quando alguém comentar nas suas publicações ou responder aos seus comentários?</p>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="receber" value="<?= $liga ? '1' : '0' ?>">
        <button class="btn <?= $liga ? 'btn-gold' : 'btn-ghost' ?> btn-block"><?= $liga ? 'Voltar a receber os avisos' : 'Parar de receber' ?></button>
      </form>
      <p class="auth-small"><a href="<?= e(url('pote', ['lado' => $S['slug']])) ?>">Voltar para o pote <?= $S['emoji'] ?></a></p>
    <?php endif; ?>
  </section>
</main>
<?php
require __DIR__ . '/includes/footer.php';
