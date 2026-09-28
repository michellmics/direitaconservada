<?php
// Lê o arquivo .env da raiz do projeto (CHAVE=valor por linha; # comenta).
// Variáveis definidas no servidor (ambiente real do processo) têm prioridade sobre o arquivo.
// Não usa putenv(): no "php -S" o processo é reaproveitado entre acessos, e putenv
// faria mudanças no .env só valerem depois de reiniciar o servidor.

function env_file_vars(): array
{
    static $vars = null;
    if ($vars !== null) {
        return $vars;
    }
    $vars = [];
    $file = dirname(__DIR__) . '/.env';
    if (!is_readable($file)) {
        return $vars;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        // aceita valores entre aspas: DB_PASSWORD="minha senha"
        if (preg_match('/^(["\'])(.*)\1$/', $value, $m)) {
            $value = $m[2];
        }
        $vars[$key] = $value;
    }
    return $vars;
}

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }
    return env_file_vars()[$key] ?? $default;
}
