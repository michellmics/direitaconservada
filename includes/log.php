<?php
// Log do sistema (tabela logs, migration 016): logar('info', 'pedido', 'pedido_criado', 'Pedido AB12CD34', [...]).
// Nunca derruba a página: se o banco falhar, cai no error_log do PHP.
// Também registra sozinho os erros do PHP (exceção não tratada, erro fatal e warnings).
// Consultar: painel → Logs (/cozinha/logs).
require_once __DIR__ . '/db.php';

const LOG_NIVEIS = ['debug', 'info', 'aviso', 'erro', 'seguranca'];
const LOG_GUARDAR_DIAS = 180; // logs mais velhos são apagados sozinhos

/** Quem está logado no site nesta requisição (definido por current_user(); o log não abre sessão sozinho). */
function log_usuario(?int $id = null): ?int
{
    static $atual = null;
    if ($id !== null) {
        $atual = $id;
    }
    return $atual;
}

/** Tira do JSON o que não pode ir para o log (senhas, tokens, imagens) e corta textos enormes. */
function log_limpar($v, int $nivel = 0)
{
    if (is_array($v)) {
        if ($nivel > 4) {
            return '[…]';
        }
        $out = [];
        foreach ($v as $k => $x) {
            $out[$k] = preg_match('/senha|password|pass|token|csrf|secret|chave/i', (string) $k) ? '[oculto]' : log_limpar($x, $nivel + 1);
        }
        return $out;
    }
    if (is_string($v)) {
        if (str_starts_with($v, 'data:')) {
            return '[imagem ' . strlen($v) . ' bytes]';
        }
        return mb_strlen($v) > 500 ? mb_substr($v, 0, 500) . '…' : $v;
    }
    return is_scalar($v) || $v === null ? $v : (string) json_encode($v);
}

function logar(string $nivel, string $categoria, string $evento, string $mensagem = '', array $dados = [], ?int $usuarioId = null, bool $admin = false, ?int $status = null): void
{
    static $gravando = false;
    if ($gravando) {
        return; // um erro ao gravar o log não gera outro log
    }
    $gravando = true;
    $nivel = in_array($nivel, LOG_NIVEIS, true) ? $nivel : 'info';
    $admin = $admin || !empty($_SESSION['admin']);
    try {
        db()->prepare('INSERT INTO logs (nivel, categoria, evento, mensagem, usuario_id, admin, ip, user_agent, metodo, rota, status_http, dados)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $nivel,
                mb_substr($categoria, 0, 40),
                mb_substr($evento, 0, 80),
                $mensagem !== '' ? mb_substr($mensagem, 0, 1000) : null,
                $usuarioId ?? log_usuario(),
                (int) $admin,
                $_SERVER['REMOTE_ADDR'] ?? (PHP_SAPI === 'cli' ? 'cli' : null),
                isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
                $_SERVER['REQUEST_METHOD'] ?? null,
                isset($_SERVER['REQUEST_URI']) ? mb_substr((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), 0, 255) : null,
                $status,
                $dados ? json_encode(log_limpar($dados), JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) : null,
            ]);
        if (random_int(1, 1000) === 1) {
            db()->exec('DELETE FROM logs WHERE criado_em < NOW() - INTERVAL ' . LOG_GUARDAR_DIAS . ' DAY LIMIT 5000');
        }
    } catch (Throwable $e) {
        error_log("[log] $nivel $categoria/$evento: $mensagem (" . $e->getMessage() . ')');
    }
    $gravando = false;
}

// ---------- erros do PHP vão para o log sozinhos ----------
if (!defined('LOG_HANDLERS')) {
    define('LOG_HANDLERS', true);

    set_error_handler(function (int $tipo, string $msg, string $arq = '', int $linha = 0) {
        if (!(error_reporting() & $tipo) || !($tipo & (E_WARNING | E_USER_WARNING | E_USER_ERROR | E_RECOVERABLE_ERROR))) {
            return false;
        }
        logar('erro', 'sistema', 'php_warning', $msg, ['arquivo' => str_replace(dirname(__DIR__), '', $arq), 'linha' => $linha]);
        return false; // segue o tratamento normal do PHP
    });

    set_exception_handler(function (Throwable $e) {
        logar('erro', 'sistema', 'php_excecao', get_class($e) . ': ' . $e->getMessage(),
            ['arquivo' => str_replace(dirname(__DIR__), '', $e->getFile()), 'linha' => $e->getLine(), 'pilha' => mb_substr($e->getTraceAsString(), 0, 2000)], null, false, 500);
        // página: tela de erro 500 com botão para a página inicial (includes/erro.php); API, terminal ou página
        // já no meio do envio: só o recado curto
        $api = str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/api/');
        if (PHP_SAPI !== 'cli' && !$api && !headers_sent()) {
            require_once __DIR__ . '/erro.php';
            pagina_erro(500);
        }
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo 'Ops, algo deu errado. Tente de novo em instantes.';
    });

    register_shutdown_function(function () {
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            logar('erro', 'sistema', 'php_fatal', $e['message'], ['arquivo' => str_replace(dirname(__DIR__), '', $e['file']), 'linha' => $e['line']], null, false, 500);
        }
    });
}

/**
 * Tudo o que uma API responde vai para o log (chamado pelo responder() de cada api/*.php).
 * Nível pelo resultado: 5xx = erro; 401/403/429 = segurança; outros 4xx = aviso; sucesso = info
 * (listagens que só leem — mural, comentários, links, lista do perfil — ficam como debug, para filtrar).
 */
function log_api(string $api, array $dados, int $status): void
{
    $in = is_array($GLOBALS['in'] ?? null) ? $GLOBALS['in'] : [];
    $acao = (string) ($in['acao'] ?? '');
    $leitura = in_array($api, ['posts', 'comentarios', 'link'], true) && !in_array($acao, ['publicar', 'curtir', 'comentar', 'apagar'], true)
            || ($api === 'perfil' && $acao === 'itens');
    $nivel = match (true) {
        $status >= 500 => 'erro',
        in_array($status, [401, 403, 429], true) => 'seguranca',
        $status >= 400 => 'aviso',
        $leitura || ($api === 'posts' && $acao === 'curtir') => 'debug',
        default => 'info',
    };
    $evento = 'api_' . $api . ($acao !== '' ? '_' . $acao : '') . ($status >= 400 ? '_recusado' : '');
    $resumo = array_intersect_key($dados, array_flip(['erro', 'login', 'ok', 'entrou', 'curtido', 'mais']));
    if (isset($dados['pedido']['codigo'])) {
        $resumo['pedido'] = $dados['pedido']['codigo'];
        $resumo['total'] = $dados['pedido']['total'] ?? null;
    }
    logar($nivel, $api === 'pedido' ? 'pedido' : ($api === 'perfil' ? 'perfil' : (in_array($api, ['posts', 'comentarios'], true) ? 'mural' : $api)),
        $evento, $dados['erro'] ?? ($dados['login'] ?? 'ok'), ['entrada' => $in, 'resposta' => $resumo], null, false, $status);
}
