<?php
// Painel: frases engraçadas do site (tabela frases). Adicionar, editar, ligar/desligar e excluir.
require dirname(__DIR__) . '/includes/config.php';
require dirname(__DIR__) . '/includes/admin_auth.php';
require dirname(__DIR__) . '/includes/frases.php';

if (!admin_logged_in()) {
    header('Location: ./'); // /cozinha/ manda para o login
    exit;
}

// filtros — mantidos depois de salvar. O formulário de filtro manda ?lugar=…&lado=… abertos;
// rota_params() redireciona para a mesma página com eles cifrados (?c=…)
$P      = rota_params(['lugar', 'lado']);
$fLugar = isset(FRASE_LUGARES[$P['lugar'] ?? '']) ? $P['lugar'] : null;
$fLado  = in_array($P['lado'] ?? '', ['direita', 'esquerda', 'ambos'], true) ? $P['lado'] : null;
$volta  = url('frases', array_filter(['lugar' => $fLugar, 'lado' => $fLado]));

$erros = [];
$old = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok($_POST['csrf'] ?? null)) {
        logar('aviso', 'painel', 'painel_csrf_invalido', 'Formulário do painel com token inválido/expirado', ['acao' => $_POST['acao'] ?? null]);
        flash('Sessão expirada. Tente de novo.', 'erro');
        header('Location: ' . $volta);
        exit;
    }
    $id = (int) ($_POST['id'] ?? 0);
    try {
        switch ($_POST['acao'] ?? '') {
            case 'salvar':
                [$dados, $erros] = frase_validar($_POST);
                if (!$erros) {
                    frase_salvar($dados, $id ?: null);
                    logar('info', 'painel', $id ? 'frase_editada' : 'frase_criada', $dados['texto'] ?? '', ['id' => $id ?: null], null, true);
                    flash($id ? 'Frase atualizada.' : 'Frase adicionada! Já entra no sorteio.');
                    header('Location: ' . $volta);
                    exit;
                }
                $old = $_POST + ['id' => $id];
                break;
            case 'alternar':
                frase_alternar($id);
                logar('info', 'painel', 'frase_alternada', "Frase #$id ligada/desligada", ['id' => $id], null, true);
                flash('Pronto.');
                header('Location: ' . $volta);
                exit;
            case 'excluir':
                frase_excluir($id);
                logar('info', 'painel', 'frase_excluida', "Frase #$id excluída", ['id' => $id], null, true);
                flash('Frase excluída.');
                header('Location: ' . $volta);
                exit;
        }
    } catch (PDOException $ex) {
        logar('erro', 'painel', 'painel_erro_banco', $ex->getMessage(), ['id' => $id], null, true, 500);
        flash('Erro no banco: ' . $ex->getMessage(), 'erro');
        header('Location: ' . $volta);
        exit;
    }
}

$flash = flash();
$dbErro = null;
$lista = [];
try {
    $lista = frases_listar($fLugar, $fLado);
} catch (PDOException $ex) {
    $dbErro = str_contains($ex->getMessage(), "doesn't exist")
        ? 'A tabela de frases ainda não existe. Rode: php database/migrate.php'
        : 'Não conectou no banco: ' . $ex->getMessage();
}

const LADOS_FRASE = ['ambos' => '🌶️🫒 Os dois potes', 'direita' => '🫒 Só Direita', 'esquerda' => '🌶️ Só Pimenta'];

function select_html(string $nome, array $opcoes, ?string $atual, string $vazio = ''): string
{
    $h = '<select name="' . $nome . '">' . ($vazio !== '' ? '<option value="">' . e($vazio) . '</option>' : '');
    foreach ($opcoes as $v => $label) {
        $h .= '<option value="' . e($v) . '"' . ((string) $atual === (string) $v ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $h . '</select>';
}

// campos de uma frase (nova ou existente)
function campos_frase(array $f): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '"><input type="hidden" name="acao" value="salvar">'
        . '<input type="hidden" name="id" value="' . (int) ($f['id'] ?? 0) . '">'
        . '<textarea name="texto" maxlength="300" rows="2" required placeholder="Escreva a frase…">' . e($f['texto'] ?? '') . '</textarea>'
        . '<div class="adm-frase-opcoes">'
        . select_html('lugar', FRASE_LUGARES, $f['lugar'] ?? 'faixa')
        . select_html('lado', LADOS_FRASE, $f['lado'] ?? 'ambos')
        . select_html('uf', array_combine(UFS, UFS), $f['uf'] ?? null, 'UF (só p/ estado)')
        . '<label class="adm-check"><input type="checkbox" name="ativo" value="1"' . (($f['ativo'] ?? 1) ? ' checked' : '') . '> Ativa</label>'
        . '<button class="btn btn-gold btn-sm">' . (!empty($f['id']) ? 'Salvar' : 'Adicionar') . '</button>'
        . '</div>';
}

function acao_frase(string $acao, int $id, string $label, string $confirmar = ''): string
{
    return '<form method="post" class="adm-inline"' . ($confirmar ? ' data-confirm="' . e($confirmar) . '"' : '') . '>'
        . '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'
        . '<input type="hidden" name="acao" value="' . $acao . '"><input type="hidden" name="id" value="' . $id . '">'
        . '<button class="btn btn-ghost btn-sm">' . $label . '</button></form>';
}

$contagem = array_count_values(array_column($lista, 'lugar'));
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Painel · Frases</title>
  <?= pwa_tags_admin() ?>
  <link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">💬</text></svg>') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../<?= asset('assets/css/style.css') ?>">
</head>
<body class="admin-page">
  <header class="topbar adm-topbar">
    <a class="brand" href="./"><span>💬 Painel <b>Frases</b></span></a>
    <button type="button" class="adm-menu-btn" aria-label="Abrir menu" aria-expanded="false" aria-controls="adm-nav" data-adm-menu><span></span></button>
    <nav id="adm-nav">
      <a href="visitas">📊 Visitas</a>
      <a href="enquetes">🗳️ Enquetes</a>
      <a href="frases">💬 Frases</a>
      <a href="pedidos">🧾 Pedidos</a>
      <a href="logs">📜 Logs</a>
      <a href="atualizar">🚀 Atualizar</a>
      <a href="../<?= e(url('pote', ['lado' => 'esquerda'])) ?>" target="_blank">🌶️ Ver Pimenta</a>
      <a href="../<?= e(url('pote', ['lado' => 'direita'])) ?>" target="_blank">🫒 Ver Direita</a>
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

    <?php if ($dbErro): ?>
      <p class="adm-flash adm-flash-erro"><?= e($dbErro) ?></p>
    <?php else: ?>
      <section class="adm-section">
        <div class="adm-head"><h2>Nova frase</h2><span class="adm-rule">Sorteada a cada visita</span></div>
        <form method="post" class="adm-card adm-frase" id="nova">
          <?php if ($erros && empty($old['id'])): ?>
            <ul class="adm-flash adm-flash-erro"><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
          <?php endif; ?>
          <?= campos_frase(empty($old['id']) ? ($old ?: ['lugar' => $fLugar ?? 'faixa', 'lado' => $fLado ?? 'ambos']) : []) ?>
        </form>
      </section>

      <section class="adm-section">
        <div class="adm-head">
          <h2>Frases</h2>
          <span class="adm-rule"><?= count($lista) ?></span>
        </div>
        <form method="get" class="adm-card adm-filtros">
          <?= select_html('lugar', FRASE_LUGARES, $fLugar, 'Todos os lugares') ?>
          <?= select_html('lado', LADOS_FRASE, $fLado, 'Todos os potes') ?>
          <button class="btn btn-ghost btn-sm">Filtrar</button>
          <?php if ($fLugar || $fLado): ?><a href="frases">limpar</a><?php endif; ?>
        </form>
        <?php if (!$lista): ?>
          <div class="adm-card adm-empty">Nenhuma frase aqui.</div>
        <?php endif; ?>
        <?php $grupo = null;
        foreach ($lista as $f):
            if ($f['lugar'] !== $grupo): $grupo = $f['lugar']; ?>
              <h3 class="adm-grupo"><?= e(FRASE_LUGARES[$grupo] ?? $grupo) ?> <small><?= $contagem[$grupo] ?? 0 ?></small></h3>
            <?php endif;
            $emEdicao = !empty($old['id']) && (int) $old['id'] === (int) $f['id']; ?>
          <article class="adm-card adm-frase<?= $f['ativo'] ? '' : ' is-off' ?>" id="frase-<?= (int) $f['id'] ?>">
            <?php if ($emEdicao && $erros): ?>
              <ul class="adm-flash adm-flash-erro"><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
            <form method="post"><?= campos_frase($emEdicao ? $old : $f) ?></form>
            <div class="adm-actions">
              <?= acao_frase('alternar', (int) $f['id'], $f['ativo'] ? 'Desativar' : 'Ativar') ?>
              <?= acao_frase('excluir', (int) $f['id'], 'Excluir', 'Excluir esta frase? Não dá para desfazer.') ?>
            </div>
          </article>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>
  </main>

  <script src="../<?= asset('assets/js/admin.js') ?>"></script>
</body>
</html>
