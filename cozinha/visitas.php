<?php
// Painel: contador de visitas (tabelas visitas e visitas_online, migration 024; quem grava é o api/visita.php).
// Online agora (atualiza sozinho a cada 10 s via ?online=1), números de hoje e do período comparados com o período
// anterior, tendência, gráfico por dia (com média de 7 dias), por mês, por hora (hoje × ontem), mapa de calor
// dia × hora, páginas, origens e aparelhos. Período: ?dias=7|30|90|365.
require dirname(__DIR__) . '/includes/config.php';
require dirname(__DIR__) . '/includes/admin_auth.php';

if (!admin_logged_in()) {
    if (isset($_GET['online'])) {
        http_response_code(401);
        exit;
    }
    header('Location: ./');
    exit;
}

const VISITAS_PERIODOS = [7 => '7 dias', 30 => '30 dias', 90 => '90 dias', 365 => '1 ano'];
const ONLINE_MINUTOS = 2; // visto nos últimos 2 min = online (o navegador avisa a cada 30 s)

/** Quem está no site agora: total, por página e a lista (do mais antigo no site ao mais novo). */
function visitas_online(): array
{
    $lista = db()->query('SELECT pagina, dispositivo, TIMESTAMPDIFF(SECOND, entrou_em, NOW()) AS ha
                          FROM visitas_online WHERE visto_em >= NOW() - INTERVAL ' . ONLINE_MINUTOS . ' MINUTE
                          ORDER BY entrou_em LIMIT 200')->fetchAll();
    $meiaHora = (int) db()->query('SELECT COUNT(*) FROM visitas_online WHERE visto_em >= NOW() - INTERVAL 30 MINUTE')->fetchColumn();
    $paginas = [];
    foreach ($lista as $l) {
        $paginas[$l['pagina']] = ($paginas[$l['pagina']] ?? 0) + 1;
    }
    arsort($paginas);
    return ['agora' => count($lista), 'meia_hora' => $meiaHora, 'paginas' => $paginas, 'lista' => $lista, 'hora' => date('H:i:s')];
}

if (isset($_GET['online'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    try {
        echo json_encode(visitas_online(), JSON_UNESCAPED_UNICODE);
    } catch (PDOException $ex) {
        http_response_code(503);
        echo '{}';
    }
    exit;
}

$dias = isset(VISITAS_PERIODOS[(int) ($_GET['dias'] ?? 0)]) ? (int) $_GET['dias'] : 30;

/** Visitantes únicos, páginas vistas e novos entre duas datas/horas (SQL). */
function visitas_resumo(string $de, string $ate): array
{
    $r = db()->query("SELECT COUNT(DISTINCT visitante) v, COUNT(*) pv, COUNT(DISTINCT IF(novo = 1, visitante, NULL)) n
                      FROM visitas WHERE criado_em >= $de AND criado_em < $ate")->fetch();
    return ['v' => (int) $r['v'], 'pv' => (int) $r['pv'], 'n' => (int) $r['n']];
}

/** Variação em % (null quando não há base para comparar). */
function variacao(int|float $agora, int|float $antes): ?float
{
    return $antes > 0 ? round(($agora - $antes) / $antes * 100, 1) : null;
}

function selo_variacao(?float $p, string $contra): string
{
    if ($p === null) {
        return '<span class="vis-var is-neutro" title="Sem dados ' . e($contra) . '">— sem base</span>';
    }
    $cls = $p > 0.5 ? 'is-sobe' : ($p < -0.5 ? 'is-desce' : 'is-neutro');
    $seta = $p > 0.5 ? '▲' : ($p < -0.5 ? '▼' : '●');
    return '<span class="vis-var ' . $cls . '">' . $seta . ' ' . ($p > 0 ? '+' : '') . number_format($p, 1, ',', '.') . '% <small>' . e($contra) . '</small></span>';
}

function n(int|float $x): string
{
    return number_format($x, 0, ',', '.');
}

$dbErro = null;
try {
    $online = visitas_online();

    // hoje × ontem até a mesma hora (comparação justa no meio do dia)
    $hoje = visitas_resumo('CURDATE()', 'NOW() + INTERVAL 1 MINUTE');
    $ontemAteAgora = visitas_resumo('CURDATE() - INTERVAL 1 DAY', 'NOW() - INTERVAL 1 DAY');
    $ontem = visitas_resumo('CURDATE() - INTERVAL 1 DAY', 'CURDATE()');

    // período escolhido (inclui hoje) × o mesmo tanto de dias antes dele
    $ini = 'CURDATE() - INTERVAL ' . ($dias - 1) . ' DAY';
    $per = visitas_resumo($ini, 'NOW() + INTERVAL 1 MINUTE');
    $ant = visitas_resumo('CURDATE() - INTERVAL ' . (2 * $dias - 1) . ' DAY', $ini);

    // por dia (dias sem visita entram com zero)
    $porDia = [];
    for ($i = $dias - 1; $i >= 0; $i--) {
        $porDia[date('Y-m-d', strtotime("-$i day"))] = ['v' => 0, 'pv' => 0, 'n' => 0];
    }
    foreach (db()->query("SELECT DATE(criado_em) d, COUNT(DISTINCT visitante) v, COUNT(*) pv, COUNT(DISTINCT IF(novo = 1, visitante, NULL)) n
                          FROM visitas WHERE criado_em >= $ini GROUP BY d") as $r) {
        if (isset($porDia[$r['d']])) {
            $porDia[$r['d']] = ['v' => (int) $r['v'], 'pv' => (int) $r['pv'], 'n' => (int) $r['n']];
        }
    }

    // tendência: reta dos mínimos quadrados sobre os visitantes por dia (sem hoje, que ainda não acabou)
    $ys = array_column(array_slice(array_values($porDia), 0, -1), 'v');
    $tendencia = null;
    if (count($ys) >= 5 && array_sum($ys) > 0) {
        $k = count($ys);
        $mx = ($k - 1) / 2;
        $my = array_sum($ys) / $k;
        $num = $den = 0;
        foreach ($ys as $x => $y) {
            $num += ($x - $mx) * ($y - $my);
            $den += ($x - $mx) ** 2;
        }
        $tendencia = round(($num / $den) * 7 / $my * 100, 1); // % por semana, sobre a média do período
    }

    // últimos 12 meses (visitantes únicos no mês) + projeção do mês atual
    $meses = [];
    for ($i = 11; $i >= 0; $i--) {
        $meses[date('Y-m', strtotime(date('Y-m-01') . " -$i month"))] = ['v' => 0, 'pv' => 0];
    }
    foreach (db()->query("SELECT DATE_FORMAT(criado_em, '%Y-%m') m, COUNT(DISTINCT visitante) v, COUNT(*) pv
                          FROM visitas WHERE criado_em >= DATE_FORMAT(CURDATE() - INTERVAL 11 MONTH, '%Y-%m-01') GROUP BY m") as $r) {
        if (isset($meses[$r['m']])) {
            $meses[$r['m']] = ['v' => (int) $r['v'], 'pv' => (int) $r['pv']];
        }
    }
    $mesAtual = end($meses);
    $diaDoMes = (int) date('j') - 1 + ((int) date('G') * 60 + (int) date('i')) / 1440; // dias já passados (com fração)
    $projecaoMes = $diaDoMes > 0.5 ? (int) round($mesAtual['v'] / $diaDoMes * (int) date('t')) : null;

    // por hora: hoje × ontem
    $horas = ['hoje' => array_fill(0, 24, 0), 'ontem' => array_fill(0, 24, 0)];
    foreach (db()->query("SELECT DATE(criado_em) = CURDATE() hoje, HOUR(criado_em) h, COUNT(DISTINCT visitante) v
                          FROM visitas WHERE criado_em >= CURDATE() - INTERVAL 1 DAY GROUP BY hoje, h") as $r) {
        $horas[$r['hoje'] ? 'hoje' : 'ontem'][(int) $r['h']] = (int) $r['v'];
    }
    $horaAgora = (int) date('G');

    // mapa de calor: dia da semana × hora (páginas vistas no período, mínimo de 4 semanas para ter padrão)
    $calorDias = max($dias, 28);
    $calor = array_fill(0, 7, array_fill(0, 24, 0));
    foreach (db()->query('SELECT DAYOFWEEK(criado_em) - 1 s, HOUR(criado_em) h, COUNT(*) pv FROM visitas
                          WHERE criado_em >= CURDATE() - INTERVAL ' . ($calorDias - 1) . ' DAY GROUP BY s, h') as $r) {
        $calor[(int) $r['s']][(int) $r['h']] = (int) $r['pv'];
    }
    $calorMax = max(1, ...array_map('max', $calor));

    $ranking = fn(string $sql) => db()->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);
    $paginas = $ranking("SELECT pagina, COUNT(*) c FROM visitas WHERE criado_em >= $ini GROUP BY pagina ORDER BY c DESC LIMIT 10");
    $origens = $ranking("SELECT origem, COUNT(DISTINCT visitante) c FROM visitas WHERE criado_em >= $ini AND origem <> 'interno' GROUP BY origem ORDER BY c DESC LIMIT 10");
    $aparelhos = $ranking("SELECT dispositivo, COUNT(DISTINCT visitante) c FROM visitas WHERE criado_em >= $ini GROUP BY dispositivo ORDER BY c DESC");
    $recorrentes = max(0, $per['v'] - $per['n']);
    $primeiraVisita = db()->query('SELECT MIN(criado_em) FROM visitas')->fetchColumn();
} catch (PDOException $ex) {
    $dbErro = str_contains($ex->getMessage(), "doesn't exist")
        ? 'As tabelas de visitas ainda não existem. Rode as migrations em 🚀 Atualizar (ou php database/migrate.php).'
        : 'Não conectou no banco: ' . $ex->getMessage();
}

$MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
$SEMANA = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

/** Lista de barras (ranking) no estilo do painel. */
function barras(array $itens, string $cor, string $unidade): string
{
    if (!$itens) {
        return '<p class="vis-vazio">Sem dados no período.</p>';
    }
    $max = max($itens);
    $total = array_sum($itens);
    $h = '<ul class="adm-bars vis-bars">';
    foreach ($itens as $nome => $c) {
        $h .= '<li><div class="adm-bar-top"><span>' . e((string) $nome) . '</span><b>' . n($c) . ' <small>' . $unidade . ' · ' . round($c / $total * 100) . '%</small></b></div>'
            . '<div class="adm-bar"><i style="width:' . max(2, round($c / $max * 100, 1)) . '%;background:' . $cor . '"></i></div></li>';
    }
    return $h . '</ul>';
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Painel · Visitas</title>
  <?= pwa_tags_admin() ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../<?= asset('assets/css/style.css') ?>">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js" defer></script>
  <script src="../<?= asset('assets/js/admin.js') ?>" defer></script>
</head>
<body class="admin-page">
  <header class="topbar adm-topbar">
    <a class="brand" href="./"><span>📊 Painel <b>Visitas</b></span></a>
    <button type="button" class="adm-menu-btn" aria-label="Abrir menu" aria-expanded="false" aria-controls="adm-nav" data-adm-menu><span></span></button>
    <nav id="adm-nav">
      <a href="visitas">📊 Visitas</a>
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

  <main class="adm-main vis-main">
    <?php if ($dbErro): ?>
      <p class="adm-flash adm-flash-erro"><?= e($dbErro) ?></p>
    <?php else: ?>
      <div class="vis-topo">
        <div class="adm-head"><h2>Visitas do site</h2>
          <span class="adm-rule"><?= $primeiraVisita ? 'contando desde ' . date('d/m/Y', strtotime($primeiraVisita)) : 'ainda sem visitas registradas' ?></span>
        </div>
        <nav class="vis-periodos" aria-label="Período">
          <?php foreach (VISITAS_PERIODOS as $d => $rot): ?>
            <a class="<?= $d === $dias ? 'is-on' : '' ?>" href="visitas<?= $d === 30 ? '' : '?dias=' . $d ?>"><?= $rot ?></a>
          <?php endforeach; ?>
        </nav>
      </div>

      <?php // ---------- ao vivo ---------- ?>
      <section class="adm-card vis-aovivo" id="vis-aovivo" aria-live="polite">
        <div class="vis-aovivo-num">
          <span class="vis-pulso" aria-hidden="true"></span>
          <div>
            <small>Online agora</small>
            <strong data-on="agora"><?= n($online['agora']) ?></strong>
            <span class="vis-sub"><b data-on="meia_hora"><?= n($online['meia_hora']) ?></b> nos últimos 30 min · atualizado às <b data-on="hora"><?= e($online['hora']) ?></b></span>
          </div>
        </div>
        <div class="vis-aovivo-graf">
          <canvas id="g-aovivo" height="70" aria-label="Pessoas online nos últimos minutos"></canvas>
          <small class="vis-sub">desde que você abriu esta página (a cada 10 s)</small>
        </div>
        <div class="vis-aovivo-onde">
          <small>Onde estão</small>
          <ul data-on="paginas">
            <?php foreach ($online['paginas'] as $p => $c): ?><li><span><?= e($p) ?></span><b><?= $c ?></b></li><?php endforeach; ?>
            <?php if (!$online['paginas']): ?><li class="vis-vazio">Ninguém no site agora.</li><?php endif; ?>
          </ul>
        </div>
      </section>

      <?php // ---------- números ---------- ?>
      <section class="vis-kpis">
        <article class="adm-card vis-kpi">
          <small>Visitantes hoje</small>
          <strong><?= n($hoje['v']) ?></strong>
          <?= selo_variacao(variacao($hoje['v'], $ontemAteAgora['v']), 'vs ontem até ' . date('H:i')) ?>
          <span class="vis-sub"><?= n($hoje['pv']) ?> páginas vistas · ontem inteiro: <?= n($ontem['v']) ?></span>
        </article>
        <article class="adm-card vis-kpi">
          <small>Visitantes · <?= VISITAS_PERIODOS[$dias] ?></small>
          <strong><?= n($per['v']) ?></strong>
          <?= selo_variacao(variacao($per['v'], $ant['v']), 'vs ' . VISITAS_PERIODOS[$dias] . ' anteriores') ?>
          <span class="vis-sub">média de <?= n($per['v'] / $dias) ?> por dia</span>
        </article>
        <article class="adm-card vis-kpi">
          <small>Páginas vistas · <?= VISITAS_PERIODOS[$dias] ?></small>
          <strong><?= n($per['pv']) ?></strong>
          <?= selo_variacao(variacao($per['pv'], $ant['pv']), 'vs anteriores') ?>
          <span class="vis-sub"><?= $per['v'] ? number_format($per['pv'] / $per['v'], 1, ',', '.') : '0' ?> páginas por visitante</span>
        </article>
        <article class="adm-card vis-kpi">
          <small>Visitantes novos · <?= VISITAS_PERIODOS[$dias] ?></small>
          <strong><?= n($per['n']) ?></strong>
          <?= selo_variacao(variacao($per['n'], $ant['n']), 'vs anteriores') ?>
          <span class="vis-sub"><?= n($recorrentes) ?> voltaram (<?= $per['v'] ? round($recorrentes / $per['v'] * 100) : 0 ?>% recorrentes)</span>
        </article>
      </section>

      <?php // ---------- veredito da tendência ---------- ?>
      <?php
      $pv = variacao($per['v'], $ant['v']);
      [$ic, $cls, $txt] = match (true) {
          $tendencia === null => ['⏳', 'is-neutro', 'Ainda há poucos dias de dados para medir a tendência. Volte em alguns dias.'],
          $tendencia >= 3 => ['📈', 'is-sobe', 'O tráfego está <b>subindo</b>: cerca de <b>+' . number_format($tendencia, 1, ',', '.') . '% por semana</b> no ritmo dos últimos ' . VISITAS_PERIODOS[$dias] . '.'],
          $tendencia <= -3 => ['📉', 'is-desce', 'O tráfego está <b>caindo</b>: cerca de <b>' . number_format($tendencia, 1, ',', '.') . '% por semana</b> no ritmo dos últimos ' . VISITAS_PERIODOS[$dias] . '.'],
          default => ['➡️', 'is-neutro', 'O tráfego está <b>estável</b> nos últimos ' . VISITAS_PERIODOS[$dias] . ' (' . ($tendencia > 0 ? '+' : '') . number_format($tendencia, 1, ',', '.') . '% por semana).'],
      };
      ?>
      <p class="vis-veredito <?= $cls ?>"><span aria-hidden="true"><?= $ic ?></span><span><?= $txt ?>
        <?php if ($pv !== null): ?> Comparando com os <?= VISITAS_PERIODOS[$dias] ?> anteriores: <b><?= ($pv > 0 ? '+' : '') . number_format($pv, 1, ',', '.') ?>%</b> de visitantes.<?php endif; ?>
        <?php if ($projecaoMes !== null && $dias >= 30): ?> Projeção para <?= $MESES[(int) date('n') - 1] ?>: <b>~<?= n($projecaoMes) ?> visitantes</b>.<?php endif; ?>
      </span></p>

      <?php // ---------- gráficos ---------- ?>
      <section class="adm-card vis-graf">
        <div class="vis-graf-top">
          <h3>Visitas por dia</h3>
          <p class="adm-legend">
            <span><i style="background:var(--vis-1)"></i>Visitantes</span>
            <span><i class="vis-leg-tracejado"></i>Média de 7 dias</span>
            <span><i style="background:var(--vis-2)"></i>Páginas vistas</span>
          </p>
        </div>
        <div class="vis-canvas vis-canvas-alto"><canvas id="g-dias" aria-label="Visitantes e páginas vistas por dia"></canvas></div>
      </section>

      <div class="vis-duas">
        <section class="adm-card vis-graf">
          <div class="vis-graf-top">
            <h3>Últimos 12 meses</h3>
            <p class="adm-legend"><span><i style="background:var(--vis-1)"></i>Visitantes no mês</span><?php if ($projecaoMes !== null): ?><span><i class="vis-leg-proj"></i>Projeção do mês</span><?php endif; ?></p>
          </div>
          <div class="vis-canvas"><canvas id="g-meses" aria-label="Visitantes únicos por mês"></canvas></div>
        </section>
        <section class="adm-card vis-graf">
          <div class="vis-graf-top">
            <h3>Por hora</h3>
            <p class="adm-legend"><span><i style="background:var(--vis-1)"></i>Hoje</span><span><i style="background:var(--vis-3)"></i>Ontem</span></p>
          </div>
          <div class="vis-canvas"><canvas id="g-horas" aria-label="Visitantes por hora, hoje e ontem"></canvas></div>
        </section>
      </div>

      <section class="adm-card vis-graf">
        <div class="vis-graf-top">
          <h3>Quando o site mais recebe gente</h3>
          <span class="vis-sub">páginas vistas por dia da semana e hora · últimos <?= $calorDias ?> dias</span>
        </div>
        <div class="vis-calor-wrap">
          <table class="vis-calor">
            <thead><tr><th></th><?php for ($h = 0; $h < 24; $h++): ?><th><?= $h % 3 === 0 ? $h . 'h' : '' ?></th><?php endfor; ?></tr></thead>
            <tbody>
              <?php foreach ([1, 2, 3, 4, 5, 6, 0] as $s): ?>
                <tr><th><?= $SEMANA[$s] ?></th>
                  <?php for ($h = 0; $h < 24; $h++): $c = $calor[$s][$h]; ?>
                    <td style="--q:<?= round($c / $calorMax, 3) ?>" title="<?= $SEMANA[$s] ?> <?= $h ?>h–<?= $h + 1 ?>h: <?= n($c) ?> páginas vistas"><span class="sr-only"><?= $c ?></span></td>
                  <?php endfor; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="adm-legend vis-calor-leg"><span>menos</span><i style="--q:.08"></i><i style="--q:.3"></i><i style="--q:.55"></i><i style="--q:.8"></i><i style="--q:1"></i><span>mais</span></p>
      </section>

      <div class="vis-tres">
        <section class="adm-card"><h3>Páginas mais vistas</h3><?= barras($paginas, 'var(--vis-2)', 'vistas') ?></section>
        <section class="adm-card"><h3>De onde vêm</h3><?= barras($origens, 'var(--vis-1)', 'pessoas') ?></section>
        <section class="adm-card"><h3>Aparelhos</h3>
          <?= barras(array_combine(array_map(fn($d) => ['celular' => '📱 Celular', 'computador' => '💻 Computador', 'tablet' => '📟 Tablet'][$d] ?? $d, array_keys($aparelhos)), $aparelhos), 'var(--vis-4)', 'pessoas') ?>
          <h3 class="vis-h3-sep">Novos × voltaram</h3>
          <?= barras(array_filter(['✨ Primeira vez' => $per['n'], '🔁 Voltaram' => $recorrentes]), 'var(--vis-3)', 'pessoas') ?>
        </section>
      </div>

      <p class="vis-rodape">Cada navegador conta como um visitante (cookie anônimo, sem IP). Robôs e o seu próprio navegador com o painel aberto não entram na conta. "Online" = com o site aberto nos últimos <?= ONLINE_MINUTOS ?> minutos.</p>

      <script>
        window.VISITAS = <?= json_encode([
            'dias'   => array_map(fn($d, $v) => ['d' => $d] + $v, array_keys($porDia), $porDia),
            'meses'  => array_map(fn($m, $v) => ['m' => $MESES[(int) substr($m, 5) - 1] . '/' . substr($m, 2, 2)] + $v, array_keys($meses), $meses),
            'projecao' => $projecaoMes,
            'horas'  => $horas,
            'horaAgora' => $horaAgora,
            'online' => $online['agora'],
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
      </script>
      <script src="../<?= asset('assets/js/admin-visitas.js') ?>" defer></script>
    <?php endif; ?>
  </main>
</body>
</html>
