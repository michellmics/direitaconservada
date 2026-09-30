<?php
// Posts do mural, direto do banco com SQL paginado: 12 por vez ("Carregar mais" busca os próximos em /api/posts).
// Frases das compras + publicações, de itens ativos (e os pendentes de quem vê). Publicar: api/posts (acao "publicar").
// Espera config.php e data/mock.php (banco_dados.php) carregados.

const POSTS_POR_PAGINA = 12;
const POSTS_ORDENS = ['recentes', 'top', 'debate'];
const PERFIL_MAX_POSTS = 20; // no perfil: só as últimas 20 publicações da pessoa

/**
 * Uma página de posts: $offset = quantos já foram mostrados. $perfil = números das azeitonas da pessoa (página de perfil:
 * os posts de todas elas, no máximo PERFIL_MAX_POSTS).
 * Ordens: recentes (data), top (curtidas), debate (comentários).
 * Retorna ['posts' => [...], 'mais' => bool, 'contagem' => [id do post => ['total', 'visitantes']]] — a contagem de
 * comentários só dos posts desta página (o botão 💬).
 */
function posts_pagina(string $lado, string $ordem = 'recentes', int $offset = 0, ?array $perfil = null, int $limite = POSTS_POR_PAGINA): array
{
    $vazio = ['posts' => [], 'mais' => false, 'contagem' => []];
    $offset = max(0, $offset);
    if ($perfil !== null) {
        $perfil = array_values(array_filter(array_map('intval', $perfil)));
        $limite = min($limite, PERFIL_MAX_POSTS - $offset);
        if (!$perfil || $limite <= 0) {
            return $vazio;
        }
    }
    if (!banco_ativo()) {
        return $vazio;
    }
    $viewer = banco_viewer();
    // comentário conta se quem comentou está no pote (ou é a própria pessoa, com item pendente)
    // (conta sem item: q.id NULL, sempre visível; conta como do pote do post)
    $visivel = "(q.id IS NULL OR q.status IN ('ativo', 'vencido') OR (q.status = 'pendente' AND q.usuario_id = $viewer))";
    $ordemSql = [
        'top'    => 'p.curtidas_count DESC, p.criado_em DESC, i.numero DESC',
        'debate' => 'n_total DESC, p.criado_em DESC, i.numero DESC',
    ][$ordem] ?? 'p.criado_em DESC, i.numero DESC';
    $st = db()->prepare("SELECT p.id AS post_id, p.is_frase_compra, p.texto, p.video_provider, p.video_id, p.video_vertical,
                                p.curtidas_count, p.criado_em, i.numero, u.nome AS autor_nome,
                                (SELECT COUNT(*) FROM comentarios c LEFT JOIN itens q ON q.id = c.item_id
                                  WHERE c.post_id = p.id AND c.status = 'publicado' AND $visivel) AS n_total,
                                (SELECT COUNT(*) FROM comentarios c JOIN itens q ON q.id = c.item_id
                                  WHERE c.post_id = p.id AND c.status = 'publicado' AND $visivel AND q.lado <> p.lado) AS n_visitantes
                         FROM posts p
                         JOIN usuarios u ON u.id = p.usuario_id
                         LEFT JOIN itens i ON i.id = p.item_id
                         WHERE p.lado = ? AND p.status = 'publicado'
                           AND (i.id IS NULL OR i.status = 'ativo' OR (i.status = 'pendente' AND i.usuario_id = ?))"
                         . ($perfil ? ' AND i.numero IN (' . implode(',', $perfil) . ')' : '') . "
                         ORDER BY $ordemSql LIMIT " . ($limite + 1) . " OFFSET $offset");
    $st->execute([$lado, $viewer]);
    $rows = $st->fetchAll();
    $out = $vazio;
    $out['mais'] = count($rows) > $limite;
    foreach (array_slice($rows, 0, $limite) as $r) {
        $p = banco_post_js($lado, $r);
        $out['posts'][] = $p;
        if ($r['n_total']) {
            $out['contagem'][$p['id']] = ['total' => (int) $r['n_total'], 'visitantes' => (int) $r['n_visitantes']];
        }
    }
    return $out;
}
