<?php
// Presente: /presente?c=… (token cifrado). Quem abre vê a azeitona/pimenta e toca em "Resgatar":
//   logado          → passa para a conta e vai para o perfil
//   e-mail novo     → a conta nasce, já entra e resgata
//   e-mail com conta → recebe o código de acesso, digita em /entrar e volta para cá (aí é só tocar em "Resgatar")
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/presentes.php';
require __DIR__ . '/includes/limite.php';

$P = rota_params();
$token = (string) ($P['t'] ?? '');
$erro = null;
$p = null;

try {
  $p = presente_buscar($token);
  $U0 = current_user();
  if ($p && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_publico_ok($_POST['csrf'] ?? null)) {
      $erro = 'A página ficou aberta tempo demais. Tente de novo.';
    } elseif (!limite_ok('presente', 10)) { // rate limit por IP
      $erro = 'Muitas tentativas seguidas. Espere um minuto e tente de novo.';
    } else {
      $voltar = url('presente', ['t' => $token]);
      $u = pedido_usuario($U0, (string) ($_POST['email'] ?? ''), (string) $p['nome'], $p['lado'], $voltar, 'toque em “Resgatar”');
      if (isset($u['entrar'])) { // e-mail com conta: vai digitar o código e volta para cá
        header('Location: ' . $u['entrar']);
        exit;
      } elseif (isset($u['erro'])) {
        $erro = $u['erro'];
      } else {
        $r = presente_resgatar($token, $u['id']);
        if (isset($r['erro'])) {
          $erro = $r['erro'];
        } else {
          header('Location: ' . url('perfil', ['lado' => $r['lado'], 'id' => $r['numero']]));
          exit;
        }
      }
    }
  }
} catch (PDOException $ex) {
  logar('erro', 'sistema', 'erro_tratado', 'presente: ' . $ex->getMessage(), [], null, false, 500);
  $erro = 'O resgate está indisponível agora. Tente de novo em instantes.';
}

$U0 = current_user();
$S = side($p['lado'] ?? 'direita');
$items = [];
$csrf = csrf_publico();
$pageTitle = ($p ? 'Um presente para ' . nome_proprio($p['nome']) : 'Presente') . ' · ' . $S['name'];
$S['og'] = $p ? 'Você ganhou ' . ($S['item'] === 'pimenta' ? 'uma pimenta' : 'uma azeitona') . ' no pote ' . $S['name'] . '! Toque para resgatar.' : $S['og'];
$daPessoa = $p && $U0 && (int) $U0['id'] === (int) $p['usuario_id']; // quem deu abrindo o próprio link
require __DIR__ . '/includes/header.php';
?>
<main class="auth-page">
  <section class="auth-card presente-card">
    <?php if (!$p): ?>
      <div class="auth-emoji"><?= item_svg($S, $S['logo'], 'auth-item', 1.4) ?></div>
      <h1>Esse presente já foi resgatado</h1>
      <p>O link já foi usado (ou está incompleto). Se era para você, peça para quem deu conferir.</p>
      <a class="btn btn-gold" href="<?= e(url('pote', ['lado' => $S['slug']])) ?>">Conhecer o pote <?= $S['emoji'] ?></a>

    <?php else: ?>
      <span class="tag">🎁 Presente de <?= e(explode(' ', nome_proprio($p['quem_deu']))[0]) ?></span>
      <div class="presente-item">
        <?php if ($p['foto_path']): ?><img class="presente-foto" src="<?= e($p['foto_path']) ?>" alt=""><?php endif; ?>
        <?= item_svg($S, $p['tipo'], 'auth-item', 1.4) ?>
      </div>
      <h1><?= e(nome_proprio($p['nome'])) ?></h1>
      <p class="presente-meta"><?= e($S['Item']) ?> <?= e($p['tipo_nome']) ?> · #<?= str_pad((string) $p['numero'], 4, '0', STR_PAD_LEFT) ?> · <?= e(nome_proprio($p['cidade'])) ?>/<?= e($p['uf']) ?></p>
      <blockquote class="presente-frase">“<?= e($p['frase']) ?>”</blockquote>

      <?php if ($daPessoa): ?>
        <p>Esse é o presente que você deu. Mande o link para a pessoa: quem abrir primeiro e tocar em “Resgatar” fica com ele.</p>
        <a class="btn btn-gold btn-block" href="https://wa.me/?text=<?= rawurlencode('Te dei ' . ($S['item'] === 'pimenta' ? 'uma pimenta' : 'uma azeitona') . ' no pote ' . $S['name'] . '! ' . $S['emoji'] . ' Resgate aqui: ' . url_absoluta('presente', ['t' => $token])) ?>" target="_blank" rel="noopener">Mandar no WhatsApp</a>
      <?php elseif ($p['status'] !== 'ativo'): ?>
        <p>Esse presente ainda não foi liberado. Volte daqui a pouco para resgatar.</p>
      <?php else: ?>
        <p>Resgatando, ela vai para a sua conta: aparece no seu perfil e você publica, comenta e debate com o outro pote.</p>
        <?php if ($erro): ?><p class="auth-error"><?= e($erro) ?></p><?php endif; ?>
        <form method="post" class="auth-form">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <?php if ($U0): ?>
            <p class="auth-small">Vai para a conta <b><?= e($U0['email']) ?></b>.</p>
          <?php else: ?>
            <label class="field"><span>Seu e-mail</span>
              <input type="email" name="email" required maxlength="190" autocomplete="email" placeholder="voce@exemplo.com">
            </label>
            <p class="auth-small">É a sua conta: com ele você entra de novo, em qualquer aparelho.</p>
          <?php endif; ?>
          <button class="btn btn-gold btn-block">Resgatar <?= $S['emoji'] ?></button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</main>
<?php
require __DIR__ . '/includes/footer.php';
