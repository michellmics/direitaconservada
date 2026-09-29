<?php
// Lê o arquivo .env da raiz do projeto (CHAVE=valor por linha; # comenta).
// Variáveis definidas no servidor (ambiente real do processo) têm prioridade sobre o arquivo.
// Não usa putenv(): no "php -S" o processo é reaproveitado entre acessos, e putenv
// faria mudanças no .env só valerem depois de reiniciar o servidor.

/**
 * Qual .env vale (o primeiro que existir):
 *   1. um nível acima do projeto   (site em /home/USUARIO/public_html → /home/USUARIO/.env)
 *   2. dois níveis acima           (site em /home/USUARIO/public_html/pote → /home/USUARIO/.env)
 *   3. na raiz do projeto          (desenvolvimento)
 * Os dois primeiros ficam fora do alcance do navegador. O painel mostra qual foi lido (Atualizar).
 */
function env_arquivo(): ?string
{
    static $arquivo = false;
    if ($arquivo === false) {
        $arquivo = null;
        foreach ([dirname(__DIR__, 2) . '/.env', dirname(__DIR__, 3) . '/.env', dirname(__DIR__) . '/.env'] as $opcao) {
            if (is_readable($opcao)) {
                $arquivo = realpath($opcao) ?: $opcao;
                break;
            }
        }
    }
    return $arquivo;
}

function env_file_vars(): array
{
    static $vars = null;
    if ($vars !== null) {
        return $vars;
    }
    $vars = [];
    $file = env_arquivo();
    if ($file === null) {
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
