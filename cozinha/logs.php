<?php
// Painel: logs do sistema (tabela logs), os mais recentes primeiro, 100 por página (paginação e ordenação no servidor).
// Filtros: nível, categoria, evento e busca livre (mensagem, evento, IP, e-mail). Atalhos: logins do painel, segurança, erros.
require dirname(__DIR__) . '/includes/config.php';
require dirname(__DIR__) . '/includes/admin_auth.php';

if (!admin_logged_in()) {
    header('Location: ./'); // o login fica na página das enquetes
    exit;
}

const LOGS_POR_PAGINA = 100;
// colunas que dá para ordenar (clique no título): nome no link → SQL
const LOGS_ORDENS = [
    'data'      => 'l.criado_em',
    'nivel'     => 'l.nivel',
    'categoria' => 'l.categoria',
    'evento'    => 'l.evento',
    'mensagem'  => 'l.mensagem',
    'quem'      => 'u.email',
    'ip'        => 'l.ip',
    'rota'      => 'l.rota',
    'status'    => 'l.status_http',
];

$f = [
    'nivel'     => in_array($_GET['nivel'] ?? '', LOG_NIVEIS, true) ? $_GET['nivel'] : '',
    'categoria' => preg_match('/^[\w-]{1,40}$/', $_GET['categoria'] ?? '') ? $_GET['categoria'] : '',
    'evento'    => preg_match('/^[\w%-]{1,80}$/', $_GET['evento'] ?? '') ? $_GET['evento'] : '',
    'q'         => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100),
    'ordem'     => isset(LOGS_ORDENS[$_GET['ordem'] ?? '']) ? $_GET['ordem'] : '',
    'dir'       => ($_GET['dir'] ?? '') === 'asc' ? 'asc' : '',
];
// padrão: os mais recentes primeiro
$ordemSql = LOGS_ORDENS[$f['ordem'] ?: 'data'];
$dirSql = $f['dir'] === 'asc' ? 'ASC' : 'DESC';
$pagina = max(1, (int) ($_GET['p'] ?? 1));

$onde = ['1'];
$params = [];
if ($f['nivel'] !== '') {
    $onde[] = 'l.nivel = ?';
    $params[] = $f['nivel'];
}
if ($f['categoria'] !== '') {
    $onde[] = 'l.categoria = ?';
    $params[] = $f['categoria'];
}
if ($f['evento'] !== '') {
    $onde[] = 'l.evento LIKE ?'; // "painel_login%" pega sucesso, falha e bloqueio
    $params[] = $f['evento'];
}
if ($f['q'] !== '') {
    $onde[] = '(l.mensagem LIKE ? OR l.evento LIKE ? OR l.ip = ? OR u.email LIKE ?)';
    $like = '%' . addcslashes($f['q'], '%_\\') . '%';
    array_push($params, $like, $like, $f['q'], $like);
}

$logs = [];
$categorias = [];
$mais = false;
$dbErro = null;
try {
    $st = db()->prepare('SELECT l.*, u.email FROM logs l LEFT JOIN usuarios u ON u.id = l.usuario_id
                         WHERE ' . implode(' AND ', $onde) . '
                         ORDER BY ' . $ordemSql . ' ' . $dirSql . ', l.criado_em DESC, l.id DESC
                         LIMIT ' . (LOGS_POR_PAGINA + 1) . ' OFFSET ' . (($pagina - 1) * LOGS_POR_PAGINA));
    $st->execute($params);
    $logs = $st->fetchAll();
    $mais = count($logs) > LOGS_POR_PAGINA;
    $logs = array_slice($logs, 0, LOGS_POR_PAGINA);
    $categorias = db()->query('SELECT DISTINCT categoria FROM logs ORDER BY categoria')->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $ex) {
    $dbErro = str_contains($ex->getMessage(), "doesn't exist")
        ? 'A tabela de logs ainda não existe. Rode: php database/migrate.php'
        : 'Não conectou no banco: ' . $ex->getMessage();
}

/** Link desta página com os filtros atuais (mais as mudanças). */
function link_logs(array $f, array $muda = []): string
{
    $q = array_filter(array_merge($f, $muda), fn($v) => $v !== '' && $v !== null && $v !== 1);
    return 'logs' . ($q ? '?' . http_build_query($q) : '');
}

/** Título de coluna que ordena: 1º clique = crescente; de novo = decrescente (a data começa pelos mais recentes). */
function titulo_ordem(array $f, string $col, string $rotulo): string
{
    $ativa = ($f['ordem'] ?: 'data') === $col;
    $desc = $ativa ? $f['dir'] !== 'asc' : $col === 'data';
    $proxima = $ativa ? ($desc ? 'asc' : '') : ($col === 'data' ? '' : 'asc');
    $link = link_logs($f, ['ordem' => $col === 'data' && $proxima === '' ? '' : $col, 'dir' => $proxima, 'p' => 1]);
    $seta = $ativa ? ($desc ? '▼' : '▲') : '↕';
    return '<a class="th-ordem' . ($ativa ? ' is-on' : '') . '" href="' . e($link) . '" title="Ordenar por ' . e(mb_strtolower($rotulo)) . '">' . e($rotulo) . ' <span>' . $seta . '</span></a>';
}

$atalhos = [
    'Tudo'              => ['nivel' => '', 'categoria' => '', 'evento' => '', 'q' => ''],
    '🔐 Logins do painel' => ['nivel' => '', 'categoria' => 'painel', 'evento' => 'painel_login%', 'q' => ''],
    '🔑 Logins do site'   => ['nivel' => '', 'categoria' => 'conta', 'evento' => 'login%', 'q' => ''],
    '🛡️ Segurança'        => ['nivel' => 'seguranca', 'categoria' => '', 'evento' => '', 'q' => ''],
    '❌ Erros'            => ['nivel' => 'erro', 'categoria' => '', 'evento' => '', 'q' => ''],
    '💰 Pedidos'          => ['nivel' => '', 'categoria' => 'pedido', 'evento' => '', 'q' => ''],
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Painel · Logs</title>
  <link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">📜</text></svg>') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../<?= asset('assets/css/style.css') ?>">
</head>
<body class="admin-page">
  <header class="topbar adm-topbar">
    <a class="brand" href="./"><span>📜 Painel <b>Logs</b></span></a>
    <nav>
      <a href="./">🗳️ Enquetes</a>
      <a href="frases">💬 Frases</a>
      <a href="pedidos">💰 Pagamentos</a>
      <a href="logs">📜 Logs</a>
      <a href="atualizar">🚀 Atualizar</a>
    </nav>
    <form method="post" action="./" class="adm-inline">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <button class="btn btn-ghost btn-sm" name="acao" value="sair">Sair</button>
    </form>
  </header>

  <main class="adm-main adm-logs">
    <?php if ($dbErro): ?>
      <p class="adm-flash adm-flash-erro"><?= e($dbErro) ?></p>
    <?php else: ?>
      <div class="adm-head"><h2>Logs do sistema</h2><span class="adm-rule">mais recentes primeiro · <?= LOGS_POR_PAGINA ?> por página</span></div>

      <p class="adm-atalhos">
        <?php foreach ($atalhos as $rotulo => $a): ?>
          <a class="<?= $a == array_intersect_key($f, $a) ? 'is-on' : '' ?>" href="<?= e(link_logs($a)) ?>"><?= e($rotulo) ?></a>
        <?php endforeach; ?>
      </p>

      <form method="get" action="logs" class="adm-card adm-filtros">
        <?php if ($f['ordem']): ?><input type="hidden" name="ordem" value="<?= e($f['ordem']) ?>"><?php endif; ?>
        <?php if ($f['dir']): ?><input type="hidden" name="dir" value="asc"><?php endif; ?>
        <select name="nivel">
          <option value="">Todos os níveis</option>
          <?php foreach (LOG_NIVEIS as $n): ?><option <?= $f['nivel'] === $n ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?>
        </select>
        <select name="categoria">
          <option value="">Todas as categorias</option>
          <?php foreach ($categorias as $c): ?><option <?= $f['categoria'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
        </select>
        <input name="evento" value="<?= e($f['evento']) ?>" placeholder="evento (ex.: painel_login%)" size="18">
        <input name="q" value="<?= e($f['q']) ?>" placeholder="buscar: mensagem, IP, e-mail…" size="22">
        <button class="btn btn-gold btn-sm">Filtrar</button>
        <a href="logs">limpar</a>
      </form>

      <?php if (!$logs): ?>
        <div class="adm-card adm-empty">Nenhum log com esses filtros.</div>
      <?php else: ?>
        <div class="adm-tabela-wrap">
          <table class="adm-tabela">
            <thead><tr>
              <th><?= titulo_ordem($f, 'data', 'Quando') ?></th>
              <th><?= titulo_ordem($f, 'nivel', 'Nível') ?></th>
              <th><?= titulo_ordem($f, 'categoria', 'Categoria') ?> · <?= titulo_ordem($f, 'evento', 'Evento') ?></th>
              <th><?= titulo_ordem($f, 'mensagem', 'Mensagem') ?></th>
              <th><?= titulo_ordem($f, 'quem', 'Quem') ?></th>
              <th><?= titulo_ordem($f, 'ip', 'IP') ?></th>
              <th><?= titulo_ordem($f, 'rota', 'Requisição') ?> · <?= titulo_ordem($f, 'status', 'Status') ?></th>
            </tr></thead>
            <tbody>
              <?php foreach ($logs as $l): ?>
                <tr class="log-<?= e($l['nivel']) ?>">
                  <td class="log-data" title="<?= e($l['criado_em']) ?>"><?= date('d/m H:i:s', strtotime($l['criado_em'])) ?></td>
                  <td><a class="log-nivel" href="<?= e(link_logs($f, ['nivel' => $l['nivel'], 'p' => 1])) ?>"><?= e($l['nivel']) ?></a></td>
                  <td>
                    <a href="<?= e(link_logs($f, ['categoria' => $l['categoria'], 'p' => 1])) ?>"><?= e($l['categoria']) ?></a><br>
                    <a class="log-evento" href="<?= e(link_logs($f, ['evento' => $l['evento'], 'p' => 1])) ?>"><?= e($l['evento']) ?></a>
                  </td>
                  <td class="log-msg">
                    <?= e((string) $l['mensagem']) ?>
                    <?php if ($l['dados']): ?>
                      <details><summary>detalhes</summary><pre><?= e(json_encode(json_decode($l['dados'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre></details>
                    <?php endif; ?>
                  </td>
                  <td><?= $l['admin'] ? '<b>painel</b><br>' : '' ?><?= $l['email'] ? e($l['email']) : ($l['usuario_id'] ? '#' . (int) $l['usuario_id'] : '—') ?></td>
                  <td><a href="<?= e(link_logs($f, ['q' => $l['ip'], 'p' => 1])) ?>"><?= e((string) $l['ip']) ?></a></td>
                  <td class="log-req"><?= e(trim($l['metodo'] . ' ' . $l['rota'])) ?><?= $l['status_http'] ? ' <span class="log-status">' . (int) $l['status_http'] . '</span>' : '' ?>
                    <?php if ($l['user_agent']): ?><small title="<?= e($l['user_agent']) ?>"><?= e(mb_strimwidth($l['user_agent'], 0, 40, '…')) ?></small><?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <nav class="adm-paginas">
        <?php if ($pagina > 1): ?><a class="btn btn-ghost btn-sm" href="<?= e(link_logs($f, ['p' => $pagina - 1])) ?>">← Mais recentes</a><?php endif; ?>
        <span>Página <?= $pagina ?></span>
        <?php if ($mais): ?><a class="btn btn-ghost btn-sm" href="<?= e(link_logs($f, ['p' => $pagina + 1])) ?>">Mais antigos →</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  </main>
</body>
</html>
