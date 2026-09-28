<?php
// Acesso ao painel /admin/: senha no .env (ADMIN_PASSWORD), sessão e token CSRF.
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
    // freio simples contra tentativa e erro
    $falhas = $_SESSION['admin_falhas'] ?? 0;
    if ($falhas >= 5 && ($_SESSION['admin_falha_at'] ?? 0) > time() - 300) {
        return 'Muitas tentativas. Espere 5 minutos.';
    }
    if (!hash_equals((string) env('ADMIN_PASSWORD'), $senha)) {
        $_SESSION['admin_falhas'] = $falhas + 1;
        $_SESSION['admin_falha_at'] = time();
        usleep(600000);
        return 'Senha incorreta.';
    }
    session_regenerate_id(true);
    $_SESSION['admin'] = true;
    $_SESSION['admin_at'] = time();
    unset($_SESSION['admin_falhas'], $_SESSION['admin_falha_at']);
    return null;
}

function admin_logout(): void
{
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
