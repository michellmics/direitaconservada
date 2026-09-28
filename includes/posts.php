<?php
// Posts do mural, lidos no servidor: 12 por vez ("Carregar mais" busca os próximos 12 em /api/posts).
// Por enquanto vêm de data/mock.php (a frase de cada item é o post dele); com o banco, troque o corpo destas
// funções por consultas na tabela posts (status = 'publicado', item não vencido).
// Espera config.php, data/mock.php e includes/comentarios.php carregados.

const POSTS_POR_PAGINA = 12;
const POSTS_ORDENS = ['recentes', 'top', 'debate'];

/** Todos os posts de um pote (do banco; sem banco, a frase de cada item no pote), no formato do JS. */
function posts_do_lado(string $lado): array
{
    if (($doBanco = banco_posts($lado)) !== null) {
        return $doBanco;
    }
    $hoje = date('Y-m-d');
    $out = [];
    foreach (mock_items($lado) as $o) {
        if (($o['valido_ate'] ?? $hoje) < $hoje) {
            continue;
        }
        $out[] = [
            'id'      => "$lado-o{$o['id']}",
            'oliveId' => $o['id'],
            'text'    => $o['frase'],
            'date'    => $o['desde'],
            'likes'   => $o['likes'],
            'video'   => $o['video'] ?? null,
        ];
    }
    return $out;
}

/**
 * Uma página de posts: $offset = quantos já foram mostrados. $perfil = só os posts desse item (página de perfil).
 * Ordens: recentes (data), top (curtidas), debate (comentários). Retorna ['posts' => [...], 'mais' => bool].
 */
function posts_pagina(string $lado, string $ordem = 'recentes', int $offset = 0, ?int $perfil = null, int $limite = POSTS_POR_PAGINA): array
{
    $lista = posts_do_lado($lado);
    if ($perfil) {
        $lista = array_values(array_filter($lista, fn($p) => $p['oliveId'] === $perfil));
    }
    $recente = fn($a, $b) => [$b['hora'] ?? $b['date'], $b['oliveId']] <=> [$a['hora'] ?? $a['date'], $a['oliveId']];
    if ($ordem === 'top') {
        usort($lista, fn($a, $b) => ($b['likes'] <=> $a['likes']) ?: $recente($a, $b));
    } elseif ($ordem === 'debate') {
        $cont = comentarios_contagem($lado);
        usort($lista, fn($a, $b) => (($cont[$b['id']]['total'] ?? 0) <=> ($cont[$a['id']]['total'] ?? 0)) ?: $recente($a, $b));
    } else {
        usort($lista, $recente);
    }
    $offset = max(0, $offset);
    return ['posts' => array_slice($lista, $offset, $limite), 'mais' => $offset + $limite < count($lista)];
}
