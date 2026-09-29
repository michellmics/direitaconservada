<?php
// Perfil:
//   { acao: "itens", lado, id, offset } → { itens, mais, total }  a lista de azeitonas da pessoa, 5 por vez (público)
//   { lado, foto: "data:image/jpeg;base64,…" | null, frase: "…" | null } → { ok, foto, frase }  editar o próprio perfil
//   foto  → vale para a pessoa inteira (as azeitonas e pimentas dela nos dois potes)
//   frase → vale neste pote (frase de azeitona não serve na pimenta): nas azeitonas dela e na frase do mural
//   nome  → 1 troca a cada NOME_TROCA_DIAS dias: a conta e as azeitonas/pimentas dela que tinham o nome antigo,
//           nos dois potes (as que ela pôs no nome de outra pessoa ficam como estão). Vai para o log e avisa o administrador.
// Presentes que ela deu e ninguém resgatou não mudam (são de outra pessoa).
require dirname(__DIR__) . '/includes/config.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/pedidos.php';

const NOME_TROCA_DIAS = 30; // troca de nome: 1 vez a cada 30 dias

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder(array $dados, int $status = 200): never
{
    log_api(basename(__FILE__, '.php'), $dados, $status); // tudo o que a API responde vai para o log
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['erro' => 'Use POST.'], 405);
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
    responder(['erro' => 'Origem não permitida.'], 403);
}
$in = json_decode(file_get_contents('php://input', false, null, 0, 1_000_000), true);
// rate limit por IP (includes/limite.php): geral das APIs + desta ação, por minuto
require_once dirname(__DIR__) . '/includes/limite.php';
limite_api('api', 150);
($in['acao'] ?? '') === 'itens' ? limite_api('perfil-itens', 60) : limite_api('perfil-editar', 10);
$lado = (string) ($in['lado'] ?? '');
if (!is_array($in) || !isset(SIDES[$lado])) {
    responder(['erro' => 'Dados inválidos.'], 422);
}

// a lista de azeitonas do perfil, 5 por vez (qualquer um vê): { acao: "itens", lado, id: número de uma delas, offset }
if (($in['acao'] ?? '') === 'itens') {
    $id = (int) ($in['id'] ?? 0);
    $offset = (int) ($in['offset'] ?? 0);
    if ($id < 1 || $offset < 0 || $offset > 100000) {
        responder(['erro' => 'Dados inválidos.'], 422);
    }
    try {
        responder(banco_pessoa_itens($lado, $id, $offset));
    } catch (PDOException $e) {
        logar('erro', 'sistema', 'erro_tratado', 'api/perfil itens: ' . $e->getMessage(), [], null, false, 500);
        responder(['erro' => 'Não deu para carregar agora. Tente de novo.'], 503);
    }
}

$u = current_user();
if (!$u) {
    responder(['erro' => 'Entre na sua conta para editar o perfil.', 'login' => true], 401);
}

$uid = (int) $u['id'];
$meus = "usuario_id = ? AND presente_token IS NULL AND status IN ('pendente', 'ativo', 'vencido')";
try {
    $pdo = db();
    $st = $pdo->prepare("SELECT COUNT(*) FROM itens WHERE $meus AND lado = ?");
    $st->execute([$uid, $lado]);
    if (!(int) $st->fetchColumn()) {
        responder(['erro' => 'Você ainda não tem ' . SIDES[$lado]['item'] . ' neste pote.'], 422);
    }
    $frase = isset($in['frase']) ? trim(preg_replace('/\s+/u', ' ', (string) $in['frase'])) : null;
    if ($frase !== null && ($frase === '' || mb_strlen($frase) > 140)) {
        responder(['erro' => 'A frase precisa ter de 1 a 140 letras.'], 422);
    }
    // nome: mesmas regras da compra; só conta como troca se mudou de verdade
    $nomeNovo = null;
    $nomeAntigo = null;
    if (isset($in['nome'])) {
        $nomeNovo = nome_proprio(mb_substr((string) $in['nome'], 0, 60));
        if ($nomeNovo === '' || mb_strlen($nomeNovo) > 28) {
            responder(['erro' => 'O nome precisa ter de 1 a 28 letras.'], 422);
        }
        $st = $pdo->prepare("SELECT nome FROM itens WHERE $meus AND lado = ? ORDER BY id DESC LIMIT 1"); // o nome que o perfil mostra
        $st->execute([$uid, $lado]);
        $nomeAntigo = (string) $st->fetchColumn();
        if ($nomeNovo === $nomeAntigo) {
            $nomeNovo = null;
        } else {
            $st = $pdo->prepare('SELECT nome_trocado_em + INTERVAL ' . NOME_TROCA_DIAS . ' DAY FROM usuarios
                                 WHERE id = ? AND nome_trocado_em > NOW() - INTERVAL ' . NOME_TROCA_DIAS . ' DAY');
            $st->execute([$uid]);
            if ($libera = $st->fetchColumn()) {
                responder(['erro' => 'Você já trocou o nome há pouco tempo. Poderá trocar de novo a partir de ' . date('d/m/Y', strtotime($libera)) . '.'], 422);
            }
        }
    }
    $foto = null;
    if (!empty($in['foto'])) {
        $foto = pedido_salvar_imagem((string) $in['foto'], 512);
        if (!$foto) {
            responder(['erro' => 'Não deu para usar essa imagem. Tente outra.'], 422);
        }
    }

    $pdo->beginTransaction();
    $antigas = [];
    if ($foto) {
        $st = $pdo->prepare("SELECT DISTINCT foto_path FROM itens WHERE $meus AND foto_path IS NOT NULL");
        $st->execute([$uid]);
        $antigas = $st->fetchAll(PDO::FETCH_COLUMN);
        $pdo->prepare("UPDATE itens SET foto_path = ? WHERE $meus")->execute([$foto, $uid]);
    }
    if ($frase !== null) {
        $pdo->prepare("UPDATE itens SET frase = ? WHERE $meus AND lado = ?")->execute([$frase, $uid, $lado]);
        // a frase no mural (post da compra) acompanha
        $pdo->prepare("UPDATE posts p JOIN itens i ON i.id = p.item_id SET p.texto = ?
                       WHERE p.is_frase_compra = 1 AND i.usuario_id = ? AND i.presente_token IS NULL AND i.lado = ?")
            ->execute([$frase, $uid, $lado]);
    }
    if ($nomeNovo !== null) {
        $st = $pdo->prepare("UPDATE itens SET nome = ? WHERE $meus AND nome = ?");
        $st->execute([$nomeNovo, $uid, $nomeAntigo]);
        $trocados = $st->rowCount();
        $pdo->prepare('UPDATE usuarios SET nome = ?, nome_trocado_em = NOW() WHERE id = ?')->execute([$nomeNovo, $uid]);
    }
    $pdo->commit();
    foreach ($antigas as $a) {
        pedido_apagar_imagem($a); // só apaga se nenhum outro item usa
    }
    if ($nomeNovo !== null) {
        $dadosTroca = ['antes' => $nomeAntigo, 'depois' => $nomeNovo, 'itens' => $trocados, 'lado' => $lado];
        logar('info', 'conta', 'nome_trocado', "Trocou o nome: {$nomeAntigo} → {$nomeNovo}", $dadosTroca, $uid);
        // avisa o administrador depois da resposta (quem editou não espera o SMTP)
        $email = (string) ($u['email'] ?? '');
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        register_shutdown_function(function () use ($dadosTroca, $uid, $email, $ip) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } elseif (function_exists('litespeed_finish_request')) {
                litespeed_finish_request();
            }
            ignore_user_abort(true);
            try {
                require_once dirname(__DIR__) . '/includes/alertas.php';
                alerta_admin('✏️ Troca de nome: ' . $dadosTroca['antes'] . ' → ' . $dadosTroca['depois'], 'Troca de nome no perfil', [[
                    'Quem trocou', null, [
                        ['Nome antigo', $dadosTroca['antes']],
                        ['Nome novo', $dadosTroca['depois']],
                        ['E-mail da conta', $email],
                        ['Conta nº', (string) $uid],
                        ['Pote do perfil', SIDES[$dadosTroca['lado']]['name']],
                        ['Azeitonas/pimentas renomeadas', (string) $dadosTroca['itens'] . ' (nos dois potes)'],
                        ['IP', $ip],
                        ['Próxima troca liberada', date('d/m/Y', strtotime('+' . NOME_TROCA_DIAS . ' days'))],
                    ],
                ]], 'Uma pessoa trocou o nome no perfil. Confira se o nome novo é adequado (sem ofensa nem se passando por outra pessoa).', 'cozinha/logs');
            } catch (Throwable $e) {
                logar('erro', 'email', 'alerta_falha', 'Alerta de troca de nome: ' . $e->getMessage(), ['usuario' => $uid]);
            }
        });
    }
    responder(['ok' => true, 'foto' => $foto, 'frase' => $frase, 'nome' => $nomeNovo]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logar('erro', 'sistema', 'erro_tratado', 'api/perfil: ' . $e->getMessage(), [], null, false, 500);
    responder(['erro' => 'Não deu para salvar agora. Tente de novo.'], 503);
}
