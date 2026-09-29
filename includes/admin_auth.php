<?php
// Acesso ao painel /cozinha/: senha no .env (ADMIN_PASSWORD), sessão e token CSRF.
// Quando houver login de usuários, trocar por usuarios.is_admin.
require_once __DIR__ . '/env.php';

session_name('dc_admin');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']),
    'use_strict_mode' => true,
]);

const ADMIN_SESSION_TTL = 60 * 60 * 8; // 8 horas

function admin_password_configured(): bool
{
    return (string) env('ADMIN_PASSWORD', '') !== '';
}

function admin_logged_in(): bool
{
    $ok = !empty($_SESSION['admin']) && ($_SESSION['admin_at'] ?? 0) > time() - ADMIN_SESSION_TTL;
    if (!$ok) {
        unset($_SESSION['admin'], $_SESSION['admin_at']);
    }
    return $ok;
}

/** Tenta entrar. Retorna mensagem de erro ou null. */
function admin_login(string $senha): ?string
{
    if (!admin_password_configured()) {
        return 'Painel desativado: defina ADMIN_PASSWORD no .env.';
    }
    // bloqueio por IP (não pela sessão: quem descarta o cookie não escapa): 5 tentativas a cada 15 minutos
    require_once __DIR__ . '/limite.php';
    if (!limite_ok('painel-login', 5, 900)) {
        logar('seguranca', 'painel', 'painel_login_bloqueado', 'Login do painel bloqueado: tentativas demais deste IP', [], null, false, 429);
        usleep(600000);
        return 'Muitas tentativas. Espere 15 minutos.';
    }
    if (!hash_equals((string) env('ADMIN_PASSWORD'), $senha)) {
        logar('seguranca', 'painel', 'painel_login_falha', 'Senha errada no login do painel', ['tamanho_digitado' => mb_strlen($senha)], null, false, 401);
        usleep(600000);
        return 'Senha incorreta.';
    }
    session_regenerate_id(true);
    $_SESSION['admin'] = true;
    $_SESSION['admin_at'] = time();
    logar('seguranca', 'painel', 'painel_login_ok', 'Entrou no painel', [], null, true, 200);
    return null;
}

function admin_logout(): void
{
    logar('info', 'painel', 'painel_logout', 'Saiu do painel', [], null, true);
    $_SESSION = [];
    session_destroy();
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_ok(?string $token): bool
{
    return is_string($token) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

// mensagens que sobrevivem ao redirecionamento (padrão POST → redirect → GET)
function flash(?string $msg = null, string $tipo = 'ok'): ?array
{
    if ($msg !== null) {
        $_SESSION['flash'] = [$tipo, $msg];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}
