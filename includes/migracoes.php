<?php
// Migrations (database/migrations/*.sql, cada uma roda uma vez; registro na tabela migrations).
// Usado pelo terminal (php database/migrate.php) e pelo painel (Atualizar sistema).

function migracoes_tabela(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
        arquivo    VARCHAR(190) NOT NULL PRIMARY KEY,
        rodado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

/** Arquivos .sql que ainda não rodaram, em ordem. */
function migracoes_pendentes(PDO $pdo): array
{
    migracoes_tabela($pdo);
    $feitas = $pdo->query('SELECT arquivo FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
    $arquivos = glob(dirname(__DIR__) . '/database/migrations/*.sql');
    sort($arquivos);
    return array_values(array_filter($arquivos, fn($f) => !in_array(basename($f), $feitas, true)));
}

/**
 * Roda as pendentes. Para na primeira que falhar.
 * Retorna ['aplicadas' => [nomes], 'erro' => null | "arquivo: mensagem"].
 * Obs.: CREATE TABLE no MySQL confirma a transação sozinho; se falhar no meio, corrija o banco antes de rodar de novo.
 */
function migracoes_aplicar(PDO $pdo): array
{
    $aplicadas = [];
    foreach (migracoes_pendentes($pdo) as $arquivo) {
        try {
            foreach (split_sql(file_get_contents($arquivo)) as $comando) {
                $pdo->exec($comando);
            }
            $pdo->prepare('INSERT INTO migrations (arquivo) VALUES (?)')->execute([basename($arquivo)]);
            $aplicadas[] = basename($arquivo);
        } catch (PDOException $e) {
            return ['aplicadas' => $aplicadas, 'erro' => basename($arquivo) . ': ' . $e->getMessage()];
        }
    }
    return ['aplicadas' => $aplicadas, 'erro' => null];
}

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
