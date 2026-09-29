<?php
// Notificações do app (includes/push.php). Chamado pelo assets/js/push.js e pelo sw.js:
//   GET                                   → { chave }  chave pública VAPID (para o navegador se inscrever)
//   POST { acao: 'inscrever', endpoint, lado }
//   POST { acao: 'sair', endpoint }
//   POST { acao: 'ver', endpoint }        → { titulo, texto, url, aviso }  o sw.js pergunta qual aviso mostrar
//   POST { acao: 'clique', aviso }        → conta quem tocou na notificação
// Não passa pelo log_api() (o "ver" chega de todos os aparelhos a cada aviso).
require dirname(__DIR__) . '/includes/config.php';
require dirname(__DIR__) . '/includes/push.php';
require_once dirname(__DIR__) . '/includes/limite.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function resposta(array $dados, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

limite_api('push', 60);
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        resposta(['chave' => push_chaves()['publica']]);
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
        resposta(['erro' => 'Origem não permitida.'], 403);
    }
    $in = json_decode((string) file_get_contents('php://input'), true);
    $acao = is_array($in) ? (string) ($in['acao'] ?? '') : '';
    $endpoint = (string) ($in['endpoint'] ?? '');
    $endpointOk = strlen($endpoint) <= 700 && preg_match('#^https://[\w.-]+(:\d+)?/#', $endpoint);

    switch ($acao) {
        case 'inscrever':
            if (!$endpointOk) {
                resposta(['erro' => 'Inscrição inválida.'], 422);
            }
            require dirname(__DIR__) . '/includes/auth.php';
            $lado = (string) ($in['lado'] ?? '');
            push_inscrever($endpoint, isset(SIDES[$lado]) ? $lado : null, isset(current_user()['id']) ? (int) current_user()['id'] : null);
            logar('info', 'push', 'push_inscrito', 'Ativou as notificações', ['lado' => $lado ?: null]);
            resposta(['ok' => true]);
        case 'sair':
            if ($endpointOk) {
                push_desinscrever($endpoint);
            }
            resposta(['ok' => true]);
        case 'ver':
            $st = db()->prepare('SELECT a.id, a.titulo, a.texto, a.url FROM push_inscricoes i JOIN push_avisos a ON a.id = i.aviso_id WHERE i.hash = ?');
            $st->execute([hash('sha256', $endpoint)]);
            $a = $st->fetch();
            if (!$a) {
                resposta(['erro' => 'Nenhum aviso.'], 404);
            }
            db()->prepare('UPDATE push_avisos SET recebidos = recebidos + 1 WHERE id = ?')->execute([$a['id']]);
            db()->prepare('UPDATE push_inscricoes SET visto_em = NOW() WHERE hash = ?')->execute([hash('sha256', $endpoint)]);
            resposta(['aviso' => (int) $a['id'], 'titulo' => $a['titulo'], 'texto' => $a['texto'], 'url' => $a['url']]);
        case 'clique':
            db()->prepare('UPDATE push_avisos SET cliques = cliques + 1 WHERE id = ?')->execute([(int) ($in['aviso'] ?? 0)]);
            resposta(['ok' => true]);
    }
    resposta(['erro' => 'Ação inválida.'], 422);
} catch (Throwable $ex) {
    resposta(['erro' => 'Notificações indisponíveis agora.'], 503); // banco fora do ar ou migration 025 não rodada
}
