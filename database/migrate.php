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
require_once dirname(__DIR__) . '/includes/migracoes.php'; // a mesma lógica do botão "Atualizar sistema" do painel

echo "MySQL $version · banco `$name`\n";

if (in_array('--status', $argv, true)) {
    migracoes_tabela($pdo);
    $pendentes = array_map('basename', migracoes_pendentes($pdo));
    $arquivos = glob(__DIR__ . '/migrations/*.sql');
    sort($arquivos);
    foreach ($arquivos as $f) {
        echo (in_array(basename($f), $pendentes, true) ? '  [pendente] ' : '  [ok]       ') . basename($f) . "\n";
    }
    exit;
}

if (!migracoes_pendentes($pdo)) {
    exit("Nada a fazer: tudo em dia.\n");
}
$r = migracoes_aplicar($pdo);
foreach ($r['aplicadas'] as $nome) {
    echo "→ $nome … ok\n";
}
if ($r['erro']) {
    echo "ERRO\n{$r['erro']}\n";
    exit(1);
}
echo "Pronto.\n";
