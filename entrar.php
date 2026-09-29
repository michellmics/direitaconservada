<?php
// Entrar com código enviado por e-mail:
//   GET               formulário (e-mail + nome na primeira vez)
//   POST acao=pedir   gera o código de 6 dígitos e envia por e-mail → tela para digitar o código
//   GET email (cifrado) tela do código direto (quem já recebeu o código pelo carrinho ou pelo presente)
//   POST acao=entrar  confere o código, abre a sessão e volta para onde a pessoa estava
// Parâmetros no ?c=… cifrado (lado, r = para onde voltar, email): ver includes/rotas.php.
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/limite.php';

$P = rota_params(['lado', 'r']);
$S = side($P['lado'] ?? $_POST['lado'] ?? 'direita');
$r = destino_seguro($P['r'] ?? $_POST['r'] ?? url('pote', ['lado' => $S['slug']]));
$tela = 'form';
$erro = null;
$email = '';
$emailCodigo = mb_strtolower(trim((string) ($P['email'] ?? '')));
if (filter_var($emailCodigo, FILTER_VALIDATE_EMAIL)) {
  $email = $emailCodigo;
  $tela = 'codigo';
}

try {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!csrf_publico_ok($_POST['csrf'] ?? null)) {
      $erro = 'A página ficou aberta tempo demais. Tente de novo.';
    } elseif (!limite_ok('entrar', 10)) { // rate limit por IP: cada pedido manda um e-mail, cada tentativa testa um código
      $erro = 'Muitas tentativas seguidas. Espere um minuto e tente de novo.';
    } elseif (($_POST['acao'] ?? '') === 'pedir') {
      $nome = trim((string) ($_POST['nome'] ?? ''));
      if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        $erro = 'Confira o e-mail digitado.';
      } elseif (mb_strlen($nome) > 60) {
        $erro = 'O nome pode ter até 60 caracteres.';
      } else {
        $res = pedir_codigo($email, $nome, $r);
        if (isset($res['codigo'])) {
          require_once __DIR__ . '/includes/mailer.php';
          [$assunto, $html, $texto] = email_codigo_login($S, nome_proprio($res['usuario']['nome']), $res['codigo']);
          if (enviar_email($email, nome_proprio($res['usuario']['nome']), $assunto, $html, $texto) !== null) {
            $erro = 'Não conseguimos enviar o e-mail agora. Tente de novo em alguns minutos.';
          } else {
            $tela = 'codigo';
          }
        } elseif ($res['erro'] === 'bloqueado') {
          $tela = 'codigo'; // não revela que a conta está bloqueada
        } else {
          $erro = $res['erro'];
          $tela = ($_POST['reenviar'] ?? '') ? 'codigo' : 'form';
        }
      }
    } elseif (($_POST['acao'] ?? '') === 'entrar') {
      $tela = 'codigo';
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $tela = 'form';
        $erro = 'Confira o e-mail digitado.';
      } else {
        $res = usar_codigo($email, (string) ($_POST['codigo'] ?? ''));
        if (isset($res['destino'])) {
          header('Location: ' . $res['destino']);
          exit;
        }
        $erro = $res['erro'];
      }
    }
  } elseif ($tela === 'form' && current_user()) {
    $tela = 'logado';
  }
} catch (PDOException $ex) {
  logar('erro', 'sistema', 'erro_tratado', 'login: ' . $ex->getMessage(), [], null, false, 500);
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

    <?php if ($tela === 'codigo'): ?>
      <h1>Digite o código</h1>
      <p>Enviamos um código de 6 dígitos para <b><?= e($email) ?></b>.</p>
      <?php if ($erro): ?><p class="auth-error"><?= e($erro) ?></p><?php endif; ?>
      <form method="post" class="auth-form">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="acao" value="entrar">
        <input type="hidden" name="lado" value="<?= $S['slug'] ?>">
        <input type="hidden" name="r" value="<?= e($r) ?>">
        <input type="hidden" name="email" value="<?= e($email) ?>">
        <label class="field"><span>Código</span>
          <input class="auth-codigo" name="codigo" required inputmode="numeric" autocomplete="one-time-code" pattern="\s*\d{3}\s*\d{3}\s*" maxlength="7" autofocus placeholder="000000">
        </label>
        <button class="btn btn-gold btn-block" data-enviando="Entrando…">Entrar <?= $S['emoji'] ?></button>
      </form>
      <p class="auth-small">Vale por <?= CODIGO_MINUTOS ?> minutos. Não chegou? Olhe no spam ou em “Promoções”.</p>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="acao" value="pedir">
        <input type="hidden" name="reenviar" value="1">
        <input type="hidden" name="lado" value="<?= $S['slug'] ?>">
        <input type="hidden" name="r" value="<?= e($r) ?>">
        <input type="hidden" name="email" value="<?= e($email) ?>">
        <button class="btn btn-ghost" data-enviando="Enviando…">Mandar outro código</button>
      </form>
      <p class="auth-small"><a href="<?= e(url('entrar', ['lado' => $S['slug'], 'r' => $r])) ?>">Usar outro e-mail</a></p>

    <?php elseif ($tela === 'logado'): ?>
      <h1>Você já entrou</h1>
      <p>Logado como <b><?= e(current_user()['email']) ?></b>.</p>
      <a class="btn btn-gold" href="<?= e($r) ?>">Voltar ao pote <?= $S['emoji'] ?></a>

    <?php else: ?>
      <h1>Entrar</h1>
      <p>Vamos enviar um código de acesso para o seu e-mail.</p>
      <?php if ($erro): ?><p class="auth-error"><?= e($erro) ?></p><?php endif; ?>
      <form method="post" class="auth-form">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="acao" value="pedir">
        <input type="hidden" name="lado" value="<?= $S['slug'] ?>">
        <input type="hidden" name="r" value="<?= e($r) ?>">
        <label class="field"><span>E-mail</span>
          <input type="email" name="email" required maxlength="190" autocomplete="email" autofocus value="<?= e($email) ?>" placeholder="voce@exemplo.com">
        </label>
        <label class="field"><span>Seu nome</span>
          <input name="nome" maxlength="60" autocomplete="name" value="<?= e($_POST['nome'] ?? '') ?>" placeholder="Como quer aparecer no site">
        </label>
        <button class="btn btn-gold btn-block" data-enviando="Enviando código…">Receber código</button>
      </form>
      <p class="auth-small">Ao entrar, você poderá votar nas enquetes.</p>
    <?php endif; ?>
  </section>
</main>
<script>
  // ao enviar: o botão mostra que está processando e não aceita um segundo clique
  document.querySelectorAll('.auth-card form').forEach(f => f.addEventListener('submit', () => {
    const b = f.querySelector('button[data-enviando]');
    if (b) { b.dataset.texto = b.textContent; b.textContent = b.dataset.enviando; b.disabled = true; }
  }));
  // voltou pelo botão "voltar" do navegador: o botão volta ao normal
  addEventListener('pageshow', () => document.querySelectorAll('button[data-texto]').forEach(b => {
    b.textContent = b.dataset.texto; b.disabled = false;
  }));
</script>
<?php
require __DIR__ . '/includes/footer.php';
