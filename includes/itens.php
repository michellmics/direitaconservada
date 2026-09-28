<?php
// Regras dos itens no pote (banco): renovação anual, vencimento e ranking de provocadores.
require_once __DIR__ . '/db.php';

/**
 * Renova um item por mais 1 ano (chamar quando o pagamento da renovação for confirmado).
 *
 * - Em dia (valido_ate >= hoje): estende 1 ano a partir do vencimento atual e
 *   MANTÉM o "desde" — é o que preserva "Conservado desde 2024" e o anel de tempo.
 * - Vencido: recomeça do zero — "desde" vira hoje e vale por 1 ano.
 *
 * Retorna true se manteve a data original, false se recomeçou.
 */
function renovar_item(int $itemId): bool
{
    $pdo = db();
    $st = $pdo->prepare('SELECT valido_ate >= CURDATE() AS em_dia FROM itens WHERE id = ? FOR UPDATE');
    $st->execute([$itemId]);
    $emDia = (bool) $st->fetchColumn();
    // no MySQL o SET é avaliado da esquerda para a direita: "desde" usa o valido_ate antigo
    $pdo->prepare("UPDATE itens SET
                     desde      = IF(valido_ate >= CURDATE(), desde, CURDATE()),
                     valido_ate = IF(valido_ate >= CURDATE(), valido_ate + INTERVAL 1 YEAR, CURDATE() + INTERVAL 1 YEAR),
                     status     = 'ativo'
                   WHERE id = ?")->execute([$itemId]);
    return $emDia;
}

/** Marca como vencidos os itens que passaram da validade (rodar 1x por dia, ex.: cron). */
function expirar_itens(): int
{
    return db()->exec("UPDATE itens SET status = 'vencido' WHERE status = 'ativo' AND valido_ate < CURDATE()");
}

/**
 * Provocadores do mês: quem mais recebeu comentários de gente do OUTRO pote
 * nos posts publicados no mural do próprio pote. O 1º ganha o selo "Provocador(a) do mês".
 */
function provocadores_do_mes(string $lado, ?string $mes = null, int $limite = 5): array
{
    $mes = $mes ?? date('Y-m');
    $st = db()->prepare("SELECT autor.id, autor.nome, autor.foto_path, autor.numero, COUNT(*) AS comentarios_do_outro_lado
                         FROM comentarios c
                         JOIN posts p        ON p.id = c.post_id AND p.lado = ?
                         JOIN itens autor    ON autor.id = p.item_id
                         JOIN itens quem     ON quem.id = c.item_id AND quem.lado <> p.lado
                         WHERE c.status = 'publicado'
                           AND c.criado_em >= ? AND c.criado_em < ? + INTERVAL 1 MONTH
                         GROUP BY autor.id, autor.nome, autor.foto_path, autor.numero
                         ORDER BY comentarios_do_outro_lado DESC, MIN(c.criado_em)
                         LIMIT " . (int) $limite);
    $inicio = $mes . '-01';
    $st->execute([$lado, $inicio, $inicio]);
    return $st->fetchAll();
}
