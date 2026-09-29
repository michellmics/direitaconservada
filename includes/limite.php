<?php
// Rate limit por IP (janela fixa): protege as APIs e os formulários de quem dispara requisições sem parar.
// Os contadores ficam em arquivos na pasta temporária do servidor (não no banco: um ataque não pode virar carga no MySQL).
//   limite_api('posts', 60)      → nas APIs: passou de 60 no minuto, responde 429 (JSON) e para
//   limite_ok('entrar', 10)      → nas páginas: true/false, a página decide o que mostrar
// Limites por minuto; ajuste o número em cada chamada.

function limite_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'); // não confia em X-Forwarded-For (qualquer um forja)
}

/** Conta mais uma requisição de $nome para este IP. Retorna quantos segundos esperar (0 = liberado). */
function limite_contar(string $nome, int $max, int $janela = 60): int
{
    $dir = sys_get_temp_dir() . '/potepolitico-limite';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        return 0; // sem onde guardar: não bloqueia ninguém
    }
    $agora = time();
    $inicio = $agora - $agora % $janela;
    $arq = $dir . '/' . hash('sha256', $nome . '|' . limite_ip()) . '.txt';
    $f = @fopen($arq, 'c+');
    if (!$f) {
        return 0;
    }
    flock($f, LOCK_EX);
    [$ini, $n] = array_map('intval', explode(' ', trim((string) stream_get_contents($f))) + [0, 0]);
    $n = $ini === $inicio ? $n + 1 : 1;
    ftruncate($f, 0);
    rewind($f);
    fwrite($f, "$inicio $n");
    flock($f, LOCK_UN);
    fclose($f);
    if (random_int(1, 500) === 1) { // de vez em quando, apaga contadores velhos
        foreach (glob("$dir/*.txt") ?: [] as $velho) {
            if (@filemtime($velho) < $agora - 3600) {
                @unlink($velho);
            }
        }
    }
    if ($n === $max + 1 && function_exists('logar')) { // registra só o primeiro bloqueio da janela (um ataque não vira milhares de linhas)
        logar('seguranca', 'limite', 'limite_excedido', "Rate limit: $nome passou de $max em {$janela}s", ['limite' => $nome, 'max' => $max, 'janela' => $janela], null, false, 429);
    }
    return $n > $max ? $inicio + $janela - $agora : 0;
}

/** Para páginas: true se ainda está dentro do limite. */
function limite_ok(string $nome, int $max, int $janela = 60): bool
{
    return limite_contar($nome, $max, $janela) === 0;
}

/** Para as APIs: passou do limite, responde 429 em JSON e encerra. */
function limite_api(string $nome, int $max, int $janela = 60): void
{
    $espera = limite_contar($nome, $max, $janela);
    if ($espera > 0) {
        http_response_code(429);
        header('Retry-After: ' . $espera);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erro' => "Muitas ações seguidas. Espere $espera segundos e tente de novo."], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
