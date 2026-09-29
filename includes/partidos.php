<?php
// Apoio partidário (migrations 009/010): todos os partidos aparecem nos dois potes; cada pessoa apoia até 3
// e o ranking soma os apoios de todo mundo. Os partidos ficam na tabela "partidos" (ordem = posição:
// os PARTIDOS_DESTAQUE primeiros ficam na linha de cima).
require_once __DIR__ . '/db.php';

const PARTIDOS_MAX = 3;
const PARTIDOS_DESTAQUE = 4;  // 1ª linha: PT, PL, Missão, PSOL (botões retangulares)
const PARTIDOS_VISIVEIS = 11; // os demais aparecem no "Exibir mais"

/** Partidos ativos, na ordem dos quadrados. */
function partidos_todos(): array
{
    return db()->query('SELECT sigla, nome, lado, cor, cor_texto FROM partidos WHERE ativo = 1 ORDER BY ordem, sigla')->fetchAll();
}

/** Ranking de apoio: [['sigla', 'n'], …] do mais apoiado ao menos (inclui os com zero). */
function partidos_ranking(): array
{
    $rows = db()->query("SELECT p.sigla, COUNT(a.usuario_id) AS n
                         FROM partidos p LEFT JOIN apoios_partido a ON a.sigla = p.sigla
                         WHERE p.ativo = 1
                         GROUP BY p.sigla, p.ordem
                         ORDER BY n DESC, p.ordem")->fetchAll();
    return array_map(fn($r) => ['sigla' => $r['sigla'], 'n' => (int) $r['n']], $rows);
}

/** Siglas que a pessoa apoia. */
function partidos_da_pessoa(int $usuarioId): array
{
    $st = db()->prepare('SELECT sigla FROM apoios_partido WHERE usuario_id = ? ORDER BY criado_em, sigla');
    $st->execute([$usuarioId]);
    return $st->fetchAll(PDO::FETCH_COLUMN);
}

/** Troca o apoio da pessoa pelas siglas escolhidas (até 3). Retorna null se deu certo ou a mensagem de erro. */
function partidos_apoiar(int $usuarioId, array $siglas): ?string
{
    $siglas = array_values(array_unique(array_map('strval', $siglas)));
    if (count($siglas) > PARTIDOS_MAX) {
        return 'Escolha no máximo ' . PARTIDOS_MAX . ' partidos.';
    }
    if (array_diff($siglas, array_column(partidos_todos(), 'sigla'))) {
        return 'Partido inválido.';
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM apoios_partido WHERE usuario_id = ?')->execute([$usuarioId]);
        $ins = $pdo->prepare('INSERT INTO apoios_partido (usuario_id, sigla) VALUES (?, ?)');
        foreach ($siglas as $s) {
            $ins->execute([$usuarioId, $s]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return null;
}

/** O que vai para o JS (DC.partidos). null = recurso fora do ar (sem banco / sem as migrations). */
function partidos_para_js(?array $usuario): ?array
{
    try {
        $lista = partidos_todos();
        if (!$lista) {
            return null;
        }
        return [
            'lista'    => $lista,
            'ranking'  => partidos_ranking(),
            'meus'     => $usuario ? partidos_da_pessoa((int) $usuario['id']) : [],
            'max'      => PARTIDOS_MAX,
            'destaque' => PARTIDOS_DESTAQUE,
            'visiveis' => PARTIDOS_VISIVEIS,
        ];
    } catch (Throwable $e) {
        return null;
    }
}
