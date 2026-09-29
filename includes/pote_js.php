<?php
// O pote sem carregar o pote inteiro: tudo aqui é consulta SQL direta (COUNT, GROUP BY, LIMIT).
//   pote_itens_js()  → para o navegador: os do vidro (os mais recentes) + os pedidos (autores na tela, a pessoa do perfil…)
//   pote_totais(), ranking_uf(), contagem_por_uf(), reis_por_uf() → as contas que precisam de todos
//   tempero_para_js() / pote_extras_js() → níveis, itens e links só de quem aparece
// Espera config.php, data/mock.php (banco_dados.php) e includes/tempero.php carregados.

/** Itens ativos de um pote no formato do JS (com "dono" = 1ª azeitona ativa da pessoa; presente não resgatado = ele mesmo). */
function pote_itens_sql(string $lado, string $cond = '1', array $params = [], string $fim = ''): array
{
    if (!banco_ativo()) {
        return [];
    }
    $st = db()->prepare('SELECT ' . BANCO_ITEM_CAMPOS . ",
                                IF(i.presente_token IS NULL,
                                   (SELECT MIN(j.numero) FROM itens j WHERE j.lado = i.lado AND j.usuario_id = i.usuario_id
                                      AND j.status = 'ativo' AND j.presente_token IS NULL),
                                   i.numero) AS dono
                         FROM itens i " . BANCO_ITEM_JOINS . "
                         WHERE i.lado = ? AND i.status = 'ativo' AND ($cond) $fim");
    $st->execute(array_merge([$lado], $params));
    return array_map(fn($r) => banco_item_js($r) + ['dono' => (int) $r['dono']], $st->fetchAll());
}

/** Itens pelos números (vazio se não pedir nenhum). */
function pote_itens_numeros(string $lado, array $numeros): array
{
    $numeros = array_values(array_unique(array_filter(array_map('intval', $numeros))));
    return $numeros ? pote_itens_sql($lado, 'i.numero IN (' . implode(',', $numeros) . ')') : [];
}

/** Itens deste pote para o JS: os do vidro + os de $extras (números). Ordenados pelo número. */
function pote_itens_js(string $lado, array $extras = []): array
{
    $vidro = array_reverse(pote_itens_sql($lado, '1', [], 'ORDER BY i.numero DESC LIMIT ' . jar_slots(SIDES[$lado]['jar'])));
    $out = array_column($vidro, null, 'id');
    foreach (pote_itens_numeros($lado, array_diff(array_map('intval', $extras), array_keys($out))) as $o) {
        $out[$o['id']] = $o;
    }
    ksort($out);
    return array_values($out);
}

/** Totais do pote: itens ativos e quantos entraram hoje. */
function pote_totais(string $lado): array
{
    static $cache = [];
    if (!isset($cache[$lado])) {
        $cache[$lado] = ['total' => 0, 'hoje' => 0];
        if (banco_ativo()) {
            $st = db()->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(desde = CURDATE()), 0) AS hoje FROM itens WHERE lado = ? AND status = 'ativo'");
            $st->execute([$lado]);
            $cache[$lado] = array_map('intval', $st->fetch());
        }
    }
    return $cache[$lado];
}

/** Estados com mais itens no pote: [UF => n], os $limit primeiros. */
function ranking_uf(string $lado, int $limit = 8): array
{
    if (!banco_ativo()) {
        return [];
    }
    $st = db()->prepare("SELECT uf, COUNT(*) AS n FROM itens WHERE lado = ? AND status = 'ativo'
                         GROUP BY uf ORDER BY n DESC, uf LIMIT " . (int) $limit);
    $st->execute([$lado]);
    return array_map('intval', array_column($st->fetchAll(), 'n', 'uf'));
}

/** Mapa da guerra dos potes: itens no pote (não vencidos) por estado, dos dois lados → [lado => [UF => n]]. */
function contagem_por_uf(): array
{
    $out = array_fill_keys(array_keys(SIDES), []);
    if (!banco_ativo()) {
        return $out;
    }
    foreach (db()->query("SELECT lado, uf, COUNT(*) AS n FROM itens WHERE status = 'ativo' AND valido_ate >= CURDATE() GROUP BY lado, uf") as $r) {
        $out[$r['lado']][$r['uf']] = (int) $r['n'];
    }
    return $out;
}

/**
 * Quem manda em cada estado: por pote, as $top pessoas com mais valor (R$) em itens ativos ali — item mais caro pesa mais.
 * → [lado => [UF => [['dono','id','nome','foto','tipo','selo','valor' (centavos),'itens' => [tipo => qtd]], …]]]
 */
function reis_por_uf(int $top = 2): array
{
    if (!banco_ativo()) {
        return [];
    }
    // uma linha por pote, estado, pessoa e tipo (a "pessoa" é a conta; presente não resgatado é à parte)
    $rows = db()->query("SELECT i.lado, i.uf, IF(i.presente_token IS NULL, CONCAT('u', i.usuario_id), CONCAT('i', i.id)) AS pessoa,
                                t.slug AS tipo, COUNT(*) AS qtd, SUM(t.preco_centavos) AS valor, MIN(i.numero) AS id
                         FROM itens i JOIN item_tipos t ON t.id = i.item_tipo_id
                         WHERE i.status = 'ativo' AND i.valido_ate >= CURDATE()
                         GROUP BY i.lado, i.uf, pessoa, t.slug")->fetchAll();
    $p = [];
    foreach ($rows as $r) {
        $x = &$p[$r['lado']][$r['uf']][$r['pessoa']];
        $x ??= ['id' => PHP_INT_MAX, 'valor' => 0, 'itens' => []];
        $x['valor'] += (int) $r['valor'];
        $x['id'] = min($x['id'], (int) $r['id']);
        $x['itens'][$r['tipo']] = (int) $r['qtd'];
        unset($x);
    }
    // os $top de cada estado; nome, foto, tipo e selo vêm da 1ª azeitona da pessoa ali
    $out = $precisa = [];
    foreach ($p as $lado => $porUf) {
        foreach ($porUf as $uf => $pessoas) {
            uasort($pessoas, fn($a, $b) => $b['valor'] <=> $a['valor']);
            $out[$lado][$uf] = array_values(array_slice($pessoas, 0, $top));
            foreach ($out[$lado][$uf] as $r) {
                $precisa[$lado][] = $r['id'];
            }
        }
    }
    foreach ($precisa as $lado => $ids) {
        $cara = array_column(pote_itens_numeros($lado, $ids), null, 'id');
        foreach ($out[$lado] as &$lista) {
            foreach ($lista as &$r) {
                $o = $cara[$r['id']] ?? null;
                $r = ['dono' => $o['dono'] ?? $r['id'], 'id' => $r['id'], 'nome' => $o['nome'] ?? '', 'foto' => $o['foto'] ?? null,
                      'tipo' => $o['tipo'] ?? '', 'selo' => $o['selo'] ?? null, 'valor' => $r['valor'], 'itens' => $r['itens']];
            }
            unset($r);
        }
        unset($lista);
    }
    return $out;
}

/**
 * Níveis (tempero) só de quem vai para o navegador: $itens = [lado => [números]].
 * → ['regras' => TEMPERO, 'fatos' => [lado => [dono => fatos]], 'donos' => [lado => [item => dono]]] (formato do JS).
 */
function tempero_para_js(array $itens): array
{
    $fatos = $donos = [];
    foreach ($itens as $lado => $numeros) {
        $numeros = array_values(array_unique(array_filter(array_map('intval', $numeros))));
        if (!$numeros || !banco_ativo() || !isset(SIDES[$lado])) {
            continue;
        }
        $st = db()->prepare("SELECT i.numero, i.usuario_id, i.presente_token IS NOT NULL AS presente, i.desde, t.preco_centavos,
                                    (SELECT MIN(j.numero) FROM itens j WHERE j.lado = i.lado AND j.usuario_id = i.usuario_id
                                       AND j.status = 'ativo' AND j.presente_token IS NULL) AS primeiro
                             FROM itens i JOIN item_tipos t ON t.id = i.item_tipo_id
                             WHERE i.lado = ? AND i.numero IN (" . implode(',', $numeros) . ')');
        $st->execute([$lado]);
        $pessoas = [];
        foreach ($st->fetchAll() as $r) {
            $n = (int) $r['numero'];
            if ($r['presente']) { // presente não resgatado: pessoa à parte, só a compra dele conta
                $fatos[$lado][$n] = ['reais' => $r['preco_centavos'] / 100, 'meses' => tempero_meses_desde($r['desde']), 'posts' => 0,
                                     'comentarios' => 0, 'recebidos' => 0, 'recebidos_outro' => 0, 'curtidas' => 0];
                continue;
            }
            $dono = $r['primeiro'] !== null ? (int) $r['primeiro'] : $n;
            if ($dono !== $n) {
                $donos[$lado][$n] = $dono;
            }
            $pessoas[(int) $r['usuario_id']] = $dono;
        }
        foreach (tempero_fatos_varios($lado, array_keys($pessoas)) as $u => $f) {
            if ($f['reais'] > 0) { // sem item ativo, sem nível
                $fatos[$lado][$pessoas[$u]] = $f;
            }
        }
    }
    return ['regras' => TEMPERO, 'fatos' => $fatos, 'donos' => $donos];
}

/**
 * Para as respostas da API (mais posts, comentários): os itens, níveis e links de perfil de quem apareceu agora.
 * $pares = [[lado, número], …]. Retorna ['itens' => [...], 'fatos', 'donos', 'links' => [lado => [id => url]]].
 */
function pote_extras_js(array $pares): array
{
    $porLado = [];
    foreach ($pares as [$lado, $id]) {
        if (isset(SIDES[$lado]) && $id > 0) {
            $porLado[$lado][(int) $id] = true;
        }
    }
    $itens = $links = [];
    foreach ($porLado as $lado => $ids) {
        array_push($itens, ...pote_itens_numeros($lado, array_keys($ids)));
        foreach (array_keys($ids) as $id) {
            $links[$lado][$id] = url('perfil', ['lado' => $lado, 'id' => $id]);
        }
    }
    $t = tempero_para_js(array_map('array_keys', $porLado));
    return ['itens' => $itens, 'fatos' => $t['fatos'], 'donos' => $t['donos'], 'links' => $links];
}
