<?php
// Roda as migrations pendentes de database/migrations/ (em ordem de nome).
//
//   php database/migrate.php            cria o banco (se não existir) e roda o que falta
//   php database/migrate.php --status   só mostra o que já rodou e o que falta
//
// Cada arquivo roda uma única vez; o registro fica na tabela "migrations".

if (PHP_SAPI !== 'cli') {
    exit("Rode pelo terminal: php database/migrate.php\n");
}

require dirname(__DIR__) . '/includes/env.php';

$host = env('DB_HOST', '127.0.0.1');
$port = env('DB_PORT', '3306');
$name = env('DB_DATABASE', 'direitaconservada');
$user = env('DB_USERNAME', 'root');
$pass = env('DB_PASSWORD', '');

if (!preg_match('/^\w+$/', $name)) {
    exit("DB_DATABASE inválido: use só letras, números e _\n");
}

try {
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    exit("Não conectou no MySQL ($user@$host:$port): {$e->getMessage()}\nConfira o .env.\n");
}

$version = $pdo->query('SELECT VERSION()')->fetchColumn();
if (!str_contains(strtolower($version), 'mariadb') && version_compare($version, '8.0.16', '<')) {
    exit("MySQL $version é antigo demais: precisa do 8.0.16 ou mais novo (CHECK constraints).\n");
}

$pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$name`");
$pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
    arquivo    VARCHAR(190) NOT NULL PRIMARY KEY,
    rodado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$done  = $pdo->query('SELECT arquivo FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
$files = glob(__DIR__ . '/migrations/*.sql');
sort($files);

echo "MySQL $version · banco `$name`\n";

$pending = array_filter($files, fn($f) => !in_array(basename($f), $done, true));

if (in_array('--status', $argv, true)) {
    foreach ($files as $f) {
        echo (in_array(basename($f), $done, true) ? '  [ok]       ' : '  [pendente] ') . basename($f) . "\n";
    }
    exit;
}

if (!$pending) {
    exit("Nada a fazer: tudo em dia.\n");
}

foreach ($pending as $file) {
    $sql = file_get_contents($file);
    echo '→ ' . basename($file) . ' … ';
    try {
        // Obs.: CREATE TABLE no MySQL confirma a transação sozinho; se falhar no meio,
        // apague as tabelas criadas (ou o banco) antes de rodar de novo.
        foreach (split_sql($sql) as $statement) {
            $pdo->exec($statement);
        }
        $pdo->prepare('INSERT INTO migrations (arquivo) VALUES (?)')->execute([basename($file)]);
        echo "ok\n";
    } catch (PDOException $e) {
        echo "ERRO\n{$e->getMessage()}\n";
        exit(1);
    }
}

echo "Pronto.\n";

// Divide o arquivo em comandos pelo ";" no fim da linha, ignorando comentários "--".
function split_sql(string $sql): array
{
    $lines = array_filter(
        preg_split('/\R/', $sql),
        fn($l) => !preg_match('/^\s*--/', $l)
    );
    $parts = preg_split('/;\s*$/m', implode("\n", $lines));
    return array_values(array_filter(array_map('trim', $parts), fn($s) => $s !== ''));
}
