<?php
// Comentários do mural, lidos no servidor (paginados: os 10 últimos e "ver anteriores").
// Por enquanto vêm de data/mock.php; com o banco, troque o corpo destas funções por consultas na tabela comentarios
// (status = 'publicado' | 'removido' — o removido aparece como "comentário apagado").
// Espera config.php e data/mock.php carregados.

const COMENTARIOS_POR_PAGINA = 10;

/** Todos os comentários feitos nos posts de um pote, em ordem de data (mais antigos primeiro). */
function comentarios_do_lado(string $lado): array
{
    static $cache = [];
    if (!isset($cache[$lado])) {
        $lista = mock_comments($lado, mock_items($lado));
        $ordem = array_flip(array_column($lista, 'id'));
        usort($lista, fn($a, $b) => [$a['data'], $ordem[$a['id']]] <=> [$b['data'], $ordem[$b['id']]]);
        $cache[$lado] = $lista;
    }
    return $cache[$lado];
}

/** Lado dono do post pelo id ("direita-o12" / "direita-p…"). */
function comentarios_lado_do_post(string $postId): ?string
{
    $lado = explode('-', $postId, 2)[0];
    return isset(SIDES[$lado]) ? $lado : null;
}

/** Por post: total e quantos vieram do outro pote (para o botão 💬 e a ordem "mais debatidas"). */
function comentarios_contagem(string $lado): array
{
    $out = [];
    foreach (comentarios_do_lado($lado) as $c) {
        if (!empty($c['apagado'])) {
            continue; // o aviso "comentário apagado" aparece, mas não conta
        }
        $out[$c['post']]['total'] = ($out[$c['post']]['total'] ?? 0) + 1;
        $out[$c['post']]['visitantes'] = ($out[$c['post']]['visitantes'] ?? 0) + ($c['autor']['side'] !== $lado ? 1 : 0);
    }
    return $out;
}

/**
 * Uma página de comentários de um post: os $limite mais recentes antes de $antes (id de comentário; null = do fim).
 * Retorna ['comentarios' => [...] (mais antigo primeiro), 'mais' => há mais antigos?].
 */
function comentarios_pagina(string $postId, ?string $antes = null, int $limite = COMENTARIOS_POR_PAGINA): array
{
    $lado = comentarios_lado_do_post($postId);
    if (!$lado) {
        return ['comentarios' => [], 'mais' => false];
    }
    $lista = array_values(array_filter(comentarios_do_lado($lado), fn($c) => $c['post'] === $postId));
    if ($antes !== null) {
        $pos = array_search($antes, array_column($lista, 'id'), true);
        $lista = $pos === false ? [] : array_slice($lista, 0, $pos);
    }
    $pagina = array_slice($lista, -$limite);
    return ['comentarios' => array_map('comentario_para_js', $pagina), 'mais' => count($lista) > count($pagina)];
}

/** Formato enviado ao navegador: com o link (cifrado) do perfil de quem comentou. */
function comentario_para_js(array $c): array
{
    return $c + ['link' => url('perfil', ['lado' => $c['autor']['side'], 'id' => $c['autor']['id']]), 'cita' => null];
}

/** Provocadores do mês (todos, não só o top): quem mais recebeu comentários do outro pote → [['id', 'n'], …]. */
function comentarios_provocadores(string $lado): array
{
    $mes = date('Y-m');
    $cont = [];
    foreach (comentarios_do_lado($lado) as $c) {
        // post_item = item dono do post (vale para a frase da compra e para as outras publicações)
        if (empty($c['apagado']) && $c['autor']['side'] !== $lado && str_starts_with($c['data'], $mes)) {
            $cont[$c['post_item']] = ($cont[$c['post_item']] ?? 0) + 1;
        }
    }
    arsort($cont);
    return array_map(fn($id, $n) => ['id' => $id, 'n' => $n], array_keys($cont), $cont);
}

/** Comentários que uma pessoa (item) fez, nos dois potes — para o perfil. */
function comentarios_feitos(string $lado, int $itemId): array
{
    $out = [];
    foreach (array_keys(SIDES) as $s) {
        foreach (comentarios_do_lado($s) as $c) {
            if (empty($c['apagado']) && $c['autor']['side'] === $lado && (int) $c['autor']['id'] === $itemId) {
                $out[] = comentario_para_js($c);
            }
        }
    }
    return $out;
}
