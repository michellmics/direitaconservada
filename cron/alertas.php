<?php
// Cron (a cada 15 minutos, pela cron do cPanel). Rotinas (includes/alertas.php e includes/vencimentos.php):
//   - expira os pedidos vencidos
//   - pedido aguardando confirmação há 15+ min e pedido que expirou → alerta ao administrador (1 por pedido)
//   - painel (entradas, senha errada, bloqueio), erros do sistema e moderação → lidos dos logs desde a última vez
//   - presentes pagos há 3+ dias sem resgate (repete toda semana)
//   - resumo diário (a partir das 8h, uma vez por dia)
//   - clientes: aviso de vencimento 30, 15, 10, 5 e 1 dia(s) antes (das 9h às 20h)
//   - banco fora do ar → alerta direto (no máximo 1 por hora)
//   - notificações do app: virada no placar (includes/push.php)
//
//   GET /cron/alertas?chave=<ENV_CRON_CHAVE do .env>     (pela web; sem ENV_CRON_CHAVE no .env fica desligada)
//   php cron/alertas.php                                 (pelo terminal: não precisa de chave)
//   ?resumo=1 (ou "php cron/alertas.php resumo") manda o resumo diário agora, para testar
require dirname(__DIR__) . '/includes/config.php';
require dirname(__DIR__) . '/includes/pedidos.php';
require dirname(__DIR__) . '/includes/alertas.php';
require dirname(__DIR__) . '/includes/vencimentos.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$chave = (string) env('ENV_CRON_CHAVE', '');
$chaveOk = strlen($chave) >= 16 && hash_equals($chave, (string) ($_GET['chave'] ?? ''));
if (PHP_SAPI !== 'cli' && !$chaveOk) {
    try {
        logar('seguranca', 'sistema', 'cron_negada', 'Chamada da cron sem a chave certa', [], null, false, 403);
    } catch (Throwable $e) {
    }
    http_response_code(403);
    echo json_encode(['erro' => 'Chave inválida.']);
    exit;
}

ignore_user_abort(true);
set_time_limit(240);

try {
    db()->query('SELECT 1');
} catch (Throwable $e) {
    alerta_sem_banco($e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Banco fora do ar.']);
    exit;
}

$forcarResumo = !empty($_GET['resumo']) || in_array('resumo', $argv ?? [], true);
$rotinas = [
    'expirar'     => function () { pedidos_expirar(); return true; },
    'atrasados'   => fn() => alertas_atrasados(),
    'expirados'   => fn() => alertas_expirados(),
    'logs'        => fn() => alertas_logs(),
    'presentes'   => fn() => alertas_presentes(),
    'resumo'      => fn() => alerta_resumo_diario($forcarResumo),
    'vencimentos' => fn() => vencimentos_avisar(),
    // notificações do app: virada no placar avisa o pote que ficou para trás (includes/push.php, migration 025)
    'push_placar' => function () { require_once dirname(__DIR__) . '/includes/push.php'; return push_placar(); },
    // Tretódromo: desafio não aceito expira, quem não respondeu perde por W.O., votação encerrada é apurada
    'duelos'      => function () { require_once dirname(__DIR__) . '/includes/duelos.php'; return duelos_atualizar(); },
];
$saida = [];
foreach ($rotinas as $nome => $rotina) {
    try {
        $saida[$nome] = $rotina(); // uma rotina que falha não impede as outras
    } catch (Throwable $e) {
        $saida[$nome] = 'erro: ' . $e->getMessage();
        logar('erro', 'sistema', 'cron_falha', "cron/alertas ($nome): " . $e->getMessage(), [], null, false, 500);
    }
}
logar('debug', 'sistema', 'cron_alertas', 'Cron de alertas rodou', $saida);
echo json_encode(['ok' => true] + $saida, JSON_UNESCAPED_UNICODE);
