<?php
// Login por código de 6 dígitos enviado por e-mail, sessão de 30 dias e CSRF dos formulários.
// Tabelas: usuarios, login_tokens, sessoes (migrations 001, 003 e 019).
require_once __DIR__ . '/db.php';

const SESSAO_COOKIE     = 'dc_sessao';
const SESSAO_DIAS       = 30;
const CODIGO_MINUTOS    = 15;
const CODIGO_TENTATIVAS = 5;   // códigos errados antes de o código morrer
const LINK_LIMITE       = 3;   // códigos por e-mail…
const LINK_JANELA_MIN   = 15;  // …a cada 15 minutos

function https(): bool
{
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

function set_cookie(string $nome, string $valor, int $expira): void
{
    setcookie($nome, $valor, [
        'expires'  => $expira,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => https(),
    ]);
    $_COOKIE[$nome] = $valor;
}

function token_aleatorio(): string
{
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

// ---------- sessão ----------

/** Usuário logado (ou null). Pode renovar o cookie: chame antes de enviar HTML. */
function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;
    $token = $_COOKIE[SESSAO_COOKIE] ?? '';
    if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) {
        return null;
    }
    try {
        $st = db()->prepare("SELECT s.id AS sessao_id, s.expira_em, s.ultimo_acesso_em, u.*
                             FROM sessoes s JOIN usuarios u ON u.id = s.usuario_id
                             WHERE s.token_hash = ? AND s.expira_em > NOW() AND u.status = 'ativo'");
        $st->execute([hash('sha256', $token)]);
        $u = $st->fetch();
    } catch (PDOException $e) {
        return null; // banco fora do ar: segue como visitante
    }
    if (!$u) {
        set_cookie(SESSAO_COOKIE, '', time() - 3600);
        return null;
    }
    // sessão deslizante: quem usa o site com frequência não precisa entrar de novo
    if (strtotime($u['expira_em']) - time() < SESSAO_DIAS * 86400 / 2) {
        $expira = time() + SESSAO_DIAS * 86400;
        db()->prepare('UPDATE sessoes SET expira_em = ?, ultimo_acesso_em = NOW() WHERE id = ?')
            ->execute([date('Y-m-d H:i:s', $expira), $u['sessao_id']]);
        set_cookie(SESSAO_COOKIE, $token, $expira);
    } elseif (strtotime($u['ultimo_acesso_em']) < time() - 600) {
        db()->prepare('UPDATE sessoes SET ultimo_acesso_em = NOW() WHERE id = ?')->execute([$u['sessao_id']]);
    }
    unset($u['senha_hash']);
    log_usuario((int) $u['id']); // os logs desta requisição saem com quem está logado
    return $cache = $u;
}

function iniciar_sessao(int $usuarioId): void
{
    $token = token_aleatorio();
    $expira = time() + SESSAO_DIAS * 86400;
    db()->prepare('INSERT INTO sessoes (usuario_id, token_hash, user_agent, expira_em) VALUES (?, ?, ?, ?)')
        ->execute([$usuarioId, hash('sha256', $token), mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255), date('Y-m-d H:i:s', $expira)]);
    set_cookie(SESSAO_COOKIE, $token, $expira);
}

function encerrar_sessao(): void
{
    $token = $_COOKIE[SESSAO_COOKIE] ?? '';
    if ($token !== '') {
        try {
            db()->prepare('DELETE FROM sessoes WHERE token_hash = ?')->execute([hash('sha256', $token)]);
        } catch (PDOException $e) {
        }
        logar('info', 'conta', 'logout', 'Saiu da conta');
    }
    set_cookie(SESSAO_COOKIE, '', time() - 3600);
}

// ---------- código por e-mail ----------

// HMAC com a ENV_KEY: quem lê o banco não descobre o código (são só 1 milhão de possibilidades)
function codigo_hash(int $usuarioId, string $codigo): string
{
    return hash_hmac('sha256', "$usuarioId:$codigo", (string) env('ENV_KEY', ''));
}

/**
 * Cria (se preciso) o usuário e gera o código de 6 dígitos (os anteriores ainda não usados deixam de valer).
 * Retorna ['codigo' => …, 'usuario' => …] ou ['erro' => mensagem].
 */
function pedir_codigo(string $email, string $nome, ?string $redirecionar): array
{
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM usuarios WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();

    if (!$u) {
        $nome = nome_proprio($nome !== '' ? $nome : preg_replace('/[._\-+\d]+/', ' ', strstr($email, '@', true)));
        $pdo->prepare('INSERT INTO usuarios (nome, email) VALUES (?, ?)')->execute([mb_substr($nome ?: 'Visitante', 0, 80), $email]);
        $u = ['id' => (int) $pdo->lastInsertId(), 'nome' => $nome, 'email' => $email, 'status' => 'ativo'];
        logar('info', 'conta', 'conta_criada', "Conta criada ao pedir código: $email", ['origem' => 'entrar'], $u['id']);
    }
    if ($u['status'] !== 'ativo') {
        logar('seguranca', 'conta', 'link_login_bloqueado', "Conta bloqueada pediu código: $email", [], (int) $u['id']);
        return ['erro' => 'bloqueado'];
    }

    $st = $pdo->prepare('SELECT COUNT(*) FROM login_tokens WHERE usuario_id = ? AND criado_em > NOW() - INTERVAL ' . LINK_JANELA_MIN . ' MINUTE');
    $st->execute([$u['id']]);
    if ((int) $st->fetchColumn() >= LINK_LIMITE) {
        logar('aviso', 'conta', 'link_login_limite', "Códigos demais pedidos para $email", [], (int) $u['id']);
        return ['erro' => 'Você já pediu vários códigos agora há pouco. Confira seu e-mail (e o spam) ou espere alguns minutos.'];
    }

    $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $pdo->prepare("UPDATE login_tokens SET usado_em = NOW() WHERE usuario_id = ? AND finalidade = 'login' AND usado_em IS NULL")
        ->execute([$u['id']]); // só o código mais novo vale
    $pdo->prepare("INSERT INTO login_tokens (usuario_id, token_hash, finalidade, redirecionar, expira_em)
                   VALUES (?, ?, 'login', ?, NOW() + INTERVAL " . CODIGO_MINUTOS . ' MINUTE)')
        ->execute([$u['id'], codigo_hash((int) $u['id'], $codigo), $redirecionar]);
    logar('info', 'conta', 'link_login_pedido', "Código de acesso gerado para $email", ['voltar' => $redirecionar], (int) $u['id']);
    return ['codigo' => $codigo, 'usuario' => $u];
}

/** Confere o código e abre a sessão. Retorna ['destino' => …] ou ['erro' => mensagem]. */
function usar_codigo(string $email, string $codigo): array
{
    $codigo = preg_replace('/\D/', '', $codigo);
    $invalido = ['erro' => 'Código inválido ou vencido. Peça um novo.'];
    $st = db()->prepare("SELECT t.*, u.email FROM login_tokens t JOIN usuarios u ON u.id = t.usuario_id
                         WHERE u.email = ? AND u.status = 'ativo' AND t.finalidade = 'login' AND t.usado_em IS NULL
                           AND t.expira_em > NOW() AND t.tentativas < " . CODIGO_TENTATIVAS . '
                         ORDER BY t.id DESC LIMIT 1');
    $st->execute([$email]);
    $t = $st->fetch();
    if (!$t) {
        logar('seguranca', 'conta', 'login_falha', "Código sem pedido válido: $email", [], null, false, 401);
        return $invalido;
    }
    if (strlen($codigo) !== 6 || !hash_equals($t['token_hash'], codigo_hash((int) $t['usuario_id'], $codigo))) {
        db()->prepare('UPDATE login_tokens SET tentativas = tentativas + 1 WHERE id = ?')->execute([$t['id']]);
        $restam = CODIGO_TENTATIVAS - (int) $t['tentativas'] - 1;
        logar('seguranca', 'conta', 'login_falha', "Código errado: $email", ['restam' => $restam], (int) $t['usuario_id'], false, 401);
        return ['erro' => $restam > 0
            ? 'Código errado. ' . ($restam === 1 ? 'Resta 1 tentativa.' : "Restam $restam tentativas.")
            : 'Código errado. Este código não vale mais: peça um novo.'];
    }
    // só uma tentativa certa ganha, mesmo com duas ao mesmo tempo
    $st = db()->prepare('UPDATE login_tokens SET usado_em = NOW() WHERE id = ? AND usado_em IS NULL');
    $st->execute([$t['id']]);
    if ($st->rowCount() !== 1) {
        return $invalido;
    }
    db()->prepare('UPDATE usuarios SET email_verificado_em = COALESCE(email_verificado_em, NOW()) WHERE id = ?')
        ->execute([$t['usuario_id']]);
    iniciar_sessao((int) $t['usuario_id']);
    logar('seguranca', 'conta', 'login_ok', 'Entrou pelo código: ' . $t['email'], [], (int) $t['usuario_id'], false, 200);
    return ['destino' => destino_seguro($t['redirecionar'])];
}

// Só deixa voltar para páginas do próprio site (nada de redirecionar para fora):
// "./", "pote?c=…", "perfil?c=…", "presente?c=…" (parâmetros cifrados, ver includes/rotas.php), com âncora opcional
function destino_seguro(?string $r): string
{
    $r = (string) $r;
    return preg_match('/^(\.\/|pote|perfil|presente|direita|esquerda|tretodromo)(\?c=[A-Za-z0-9_-]+)?(#[\w-]*)?$/', $r)
        || preg_match('/^duelo\?n=\d+(#[\w-]*)?$/', $r) ? $r : './';
}

// ---------- CSRF (cookie + campo escondido) ----------

function csrf_publico(): string
{
    $t = $_COOKIE['dc_csrf'] ?? '';
    if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $t)) {
        $t = token_aleatorio();
        set_cookie('dc_csrf', $t, 0);
    }
    return $t;
}

function csrf_publico_ok(?string $enviado): bool
{
    $t = $_COOKIE['dc_csrf'] ?? '';
    return $t !== '' && is_string($enviado) && hash_equals($t, $enviado);
}
