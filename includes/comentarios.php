<?php
// Comentários do mural, direto do banco com SQL paginado (os 10 últimos e "ver anteriores").
// 'removido' aparece como "comentário apagado". Comentar/apagar: api/comentarios (includes/mural.php).
// Quem comenta com item pendente (Pix em conferência) só aparece para si mesmo.
// Espera config.php e data/mock.php (banco_dados.php) carregados.

const COMENTARIOS_POR_PAGINA = 10;
const PERFIL_MAX_COMENTARIOS = 20; // no perfil: só os 20 últimos comentários que a pessoa fez

/** Condição SQL "o post é este" pelo id do JS ("direita-o12" = frase do item 12; "direita-p7" = publicação 7). */
function comentarios_post_sql(string $postId): ?array
{
    if (preg_match('/^(\w+)-o(\d+)$/', $postId, $m) && isset(SIDES[$m[1]])) {
        return ['p.lado = ? AND p.is_frase_compra = 1 AND dono.numero = ?', [$m[1], (int) $m[2]]];
    }
    if (preg_match('/^(\w+)-p(\d+)$/', $postId, $m) && isset(SIDES[$m[1]])) {
        return ['p.lado = ? AND p.id = ?', [$m[1], (int) $m[2]]];
    }
    return null;
}

/**
 * Uma página de comentários de um post: os $limite mais recentes antes de $antes (id de comentário; null = do fim).
 * Retorna ['comentarios' => [...] (mais antigo primeiro), 'mais' => há mais antigos?].
 */
function comentarios_pagina(string $postId, ?string $antes = null, int $limite = COMENTARIOS_POR_PAGINA): array
{
    $post = comentarios_post_sql($postId);
    if (!$post || !banco_ativo()) {
        return ['comentarios' => [], 'mais' => false];
    }
    [$cond, $params] = $post;
    if ($antes !== null && preg_match('/-c(\d+)$/', $antes, $m)) {
        $cond .= ' AND c.id < ?';
        $params[] = (int) $m[1];
    }
    $st = db()->prepare(BANCO_COMENTARIO_SQL . "AND $cond ORDER BY c.id DESC LIMIT " . ($limite + 1));
    $st->execute(array_merge([banco_viewer()], $params));
    $rows = $st->fetchAll();
    $mais = count($rows) > $limite;
    $pagina = array_reverse(array_slice($rows, 0, $limite));
    return ['comentarios' => array_map(fn($r) => comentario_para_js(banco_comentario_js('', $r)), $pagina), 'mais' => $mais];
}

/** Formato enviado ao navegador: com o link (cifrado) do perfil de quem comentou. */
function comentario_para_js(array $c): array
{
    return $c + ['link' => url('perfil', ['lado' => $c['autor']['side'], 'id' => $c['autor']['id']]), 'cita' => null];
}

/** Provocadores do mês (todos, não só o top): quem mais recebeu comentários do outro pote → [['id', 'n'], …]. */
function comentarios_provocadores(string $lado): array
{
    if (!banco_ativo()) {
        return [];
    }
    $st = db()->prepare("SELECT dono.numero AS id, COUNT(*) AS n
                         FROM comentarios c
                         JOIN posts p    ON p.id = c.post_id AND p.lado = ? AND p.status = 'publicado'
                         JOIN itens dono ON dono.id = p.item_id
                         JOIN itens quem ON quem.id = c.item_id AND quem.lado <> p.lado
                          AND (quem.status IN ('ativo', 'vencido') OR (quem.status = 'pendente' AND quem.usuario_id = ?))
                         WHERE c.status = 'publicado' AND c.criado_em >= ?
                         GROUP BY dono.numero ORDER BY n DESC, MIN(c.criado_em)");
    $st->execute([$lado, banco_viewer(), date('Y-m-01')]);
    return array_map(fn($r) => ['id' => (int) $r['id'], 'n' => (int) $r['n']], $st->fetchAll());
}

/** Comentários que uma pessoa fez (com qualquer uma das azeitonas dela: $itens = números), nos dois potes — para o perfil. */
function comentarios_feitos(string $lado, array $itens): array
{
    $itens = array_values(array_filter(array_map('intval', $itens)));
    if (!$itens || !banco_ativo()) {
        return [];
    }
    $st = db()->prepare(BANCO_COMENTARIO_SQL . "AND c.status = 'publicado' AND quem.lado = ? AND quem.numero IN (" . implode(',', $itens) . ')
                         ORDER BY c.criado_em DESC, c.id DESC LIMIT ' . PERFIL_MAX_COMENTARIOS); // os mais recentes primeiro
    $st->execute([banco_viewer(), $lado]);
    return array_map(fn($r) => comentario_para_js(banco_comentario_js('', $r)), $st->fetchAll());
}
