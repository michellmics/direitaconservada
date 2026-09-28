<?php
// Entrar com link mágico:
//   GET               formulário (e-mail + nome na primeira vez)
//   POST acao=pedir   gera o link e envia por e-mail
//   GET token (cifrado) tela "Entrar" (não gasta o link: filtros de e-mail abrem links sozinhos)
//   POST acao=entrar  gasta o link, abre a sessão e volta para onde a pessoa estava
// Parâmetros no ?c=… cifrado (lado, r = para onde voltar, token): ver includes/rotas.php.
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/auth.php';

$P = rota_params(['lado', 'r', 'token']); // links antigos (?token=…) de e-mails já enviados continuam valendo
$S = side($P['lado'] ?? $_POST['lado'] ?? 'direita');
$r = destino_seguro($P['r'] ?? $_POST['r'] ?? url('pote', ['lado' => $S['slug']]));
$tokenLink = (string) ($P['token'] ?? '');
$tela = 'form';
$erro = null;
$email = '';
$link = null;

try {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_publico_ok($_POST['csrf'] ?? null)) {
      $erro = 'A página ficou aberta tempo demais. Tente de novo.';
    } elseif (($_POST['acao'] ?? '') === 'pedir') {
      $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
      $nome  = trim((string) ($_POST['nome'] ?? ''));
      if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        $erro = 'Confira o e-mail digitado.';
      } elseif (mb_strlen($nome) > 60) {
        $erro = 'O nome pode ter até 60 caracteres.';
      } else {
        $res = pedir_link($email, $nome, $r);
        if (isset($res['token'])) {
          require_once __DIR__ . '/includes/mailer.php';
          $url = url_absoluta('entrar', ['token' => $res['token'], 'lado' => $S['slug']]);
          [$assunto, $html, $texto] = email_link_login($S, nome_proprio($res['usuario']['nome']), $url);
          if (enviar_email($email, nome_proprio($res['usuario']['nome']), $assunto, $html, $texto) !== null) {
            $erro = 'Não conseguimos enviar o e-mail agora. Tente de novo em alguns minutos.';
          } else {
            $tela = 'enviado';
          }
        } elseif ($res['erro'] === 'bloqueado') {
          $tela = 'enviado'; // não revela que a conta está bloqueada
        } else {
          $erro = $res['erro'];
        }
      }
    } elseif (($_POST['acao'] ?? '') === 'entrar') {
      $destino = usar_link((string) ($_POST['token'] ?? ''));
      if ($destino !== null) {
        header('Location: ' . $destino);
        exit;
      }
      $tela = 'invalido';
    }
  } elseif ($tokenLink !== '') {
    $link = ver_link($tokenLink);
    $tela = $link ? 'confirmar' : 'invalido';
  } elseif (current_user()) {
    $tela = 'logado';
  }
} catch (PDOException $ex) {
  error_log('[login] ' . $ex->getMessage());
  $erro = 'O login está indisponível agora. Tente de novo em instantes.';
}

$csrf = csrf_publico();
$items = [];
$pageTitle = 'Entrar · ' . $S['name'];
require __DIR__ . '/includes/header.php';
?>
<main class="auth-page">
  <section class="auth-card">
    <div class="auth-emoji"><?= item_svg($S, $S['logo'], 'auth-item', 1.4) ?></div>

    <?php if ($tela === 'enviado'): ?>
      <h1>Confira seu e-mail</h1>
      <p>Se <b><?= e($email) ?></b> estiver certo, o link para entrar chega em instantes.</p>
      <p class="auth-small">Ele vale por <?= LINK_MINUTOS ?> minutos. Não achou? Olhe no spam ou em “Promoções”.</p>
      <a class="btn btn-ghost" href="<?= e(url('entrar', ['lado' => $S['slug'], 'r' => $r])) ?>">Usar outro e-mail</a>

    <?php elseif ($tela === 'confirmar'): ?>
      <h1>Olá, <?= e(explode(' ', nome_proprio($link['nome']))[0]) ?>!</h1>
      <p>Você vai entrar como <b><?= e($link['email']) ?></b>.</p>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="acao" value="entrar">
        <input type="hidden" name="token" value="<?= e($tokenLink) ?>">
        <input type="hidden" name="lado" value="<?= $S['slug'] ?>">
        <button class="btn btn-gold btn-block">Entrar <?= $S['emoji'] ?></button>
      </form>

    <?php elseif ($tela === 'invalido'): ?>
      <h1>Este link não vale mais</h1>
      <p>Ele já foi usado ou passou dos <?= LINK_MINUTOS ?> minutos. Peça um novo — é rapidinho.</p>
      <a class="btn btn-gold" href="<?= e(url('entrar', ['lado' => $S['slug']])) ?>">Pedir novo link</a>

    <?php elseif ($tela === 'logado'): ?>
      <h1>Você já entrou</h1>
      <p>Logado como <b><?= e(current_user()['email']) ?></b>.</p>
      <a class="btn btn-gold" href="<?= e($r) ?>">Voltar ao pote <?= $S['emoji'] ?></a>

    <?php else: ?>
      <h1>Entrar</h1>
      <p>Será enviado um link de acesso para o seu endereço de e-mail.</p>
      <?php if ($erro): ?><p class="auth-error"><?= e($erro) ?></p><?php endif; ?>
      <form method="post" class="auth-form">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="acao" value="pedir">
        <input type="hidden" name="lado" value="<?= $S['slug'] ?>">
        <input type="hidden" name="r" value="<?= e($r) ?>">
        <label class="field"><span>E-mail</span>
          <input type="email" name="email" required maxlength="190" autocomplete="email" autofocus value="<?= e($email) ?>" placeholder="voce@exemplo.com">
        </label>
        <label class="field"><span>Seu nome <small>(só na primeira vez)</small></span>
          <input name="nome" maxlength="60" autocomplete="name" value="<?= e($_POST['nome'] ?? '') ?>" placeholder="Como quer aparecer no site">
        </label>
        <button class="btn btn-gold btn-block">Receber link de acesso</button>
      </form>
      <p class="auth-small">Ao entrar, você poderá votar nas enquetes.</p>
    <?php endif; ?>

    <?php if ($erro && $tela !== 'form'): ?><p class="auth-error"><?= e($erro) ?></p><?php endif; ?>
  </section>
</main>
<?php
require __DIR__ . '/includes/footer.php';
