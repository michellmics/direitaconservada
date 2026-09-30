<?php
// Lê do banco os itens, posts e comentários, no formato que o JS usa
// (itens do pote em pote_js.php, mural em posts.php e comentários em comentarios.php usam as funções daqui).
// Público = itens 'ativo'. Quem está logado vê também o que é seu e ainda está 'pendente' (pagamento em
// conferência): o item, os posts e os comentários feitos com ele. Ninguém mais vê até o painel aprovar.
// Para popular um banco de desenvolvimento com gente de mentira: php database/seed.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/** O banco responde? (uma consulta por página). Fora do ar, o site mostra os potes vazios. */
function banco_ativo(): bool
{
    static $ativo = null;
    if ($ativo === null) {
        try {
            db()->query('SELECT 1 FROM itens LIMIT 1');
            $ativo = true;
        } catch (Throwable $e) {
            $ativo = false;
        }
    }
    return $ativo;
}

/** Quem está vendo a página (0 = visitante): enxerga também os próprios itens pendentes. */
function banco_viewer(): int
{
    static $id = null;
    return $id ??= (int) (current_user()['id'] ?? 0);
}

/** Linha do banco (itens + tipo + post da frase) → formato do JS. */
function banco_item_js(array $r): array
{
    $item = [
        'id'         => (int) $r['id'],
        'side'       => $r['side'],
        'nome'       => $r['nome'],
        'foto'       => $r['foto'],
        'cidade'     => $r['cidade'],
        'uf'         => $r['uf'],
        'tipo'       => $r['tipo'],
        'frase'      => $r['frase'],
        'desde'      => $r['desde'],
        'likes'      => (int) $r['likes'],
        'selo'       => $r['selo'],
        'valido_ate' => $r['valido_ate'],
    ];
    if ($r['video_id']) {
        $item['video'] = ['provider' => $r['video_provider'], 'id' => $r['video_id'], 'vertical' => (bool) $r['video_vertical']];
    }
    return $item;
}

const BANCO_ITEM_CAMPOS = "i.numero AS id, i.lado AS side, i.nome, i.foto_path AS foto, i.cidade, i.uf, t.slug AS tipo,
                           i.frase, i.desde, i.valido_ate, i.selo_valor AS selo, i.usuario_id, i.status, i.criado_em, i.presente_token,
                           COALESCE(p.curtidas_count, 0) AS likes, p.video_provider, p.video_id, p.video_vertical";
const BANCO_ITEM_JOINS = "JOIN item_tipos t ON t.id = i.item_tipo_id
                          LEFT JOIN posts p ON p.item_id = i.id AND p.is_frase_compra = 1 AND p.status = 'publicado'";

/** Quem é a "pessoa" de um item: a conta; um presente ainda não resgatado é uma pessoa à parte. */
function banco_pessoa(array $r): string
{
    return $r['presente_token'] !== null ? 'i' . $r['id'] : 'u' . $r['usuario_id'];
}

/** Itens no pote (ativos). "id" = número do item no pote (#0042); "dono" = 1º item da mesma pessoa neste pote. */
function banco_itens(string $lado): ?array
{
    if (!banco_ativo()) {
        return null;
    }
    $st = db()->prepare('SELECT ' . BANCO_ITEM_CAMPOS . ' FROM itens i ' . BANCO_ITEM_JOINS . "
                         WHERE i.lado = ? AND i.status = 'ativo' ORDER BY i.numero");
    $st->execute([$lado]);
    $out = [];
    $primeiro = [];
    foreach ($st->fetchAll() as $r) {
        $primeiro[banco_pessoa($r)] ??= (int) $r['id'];
        $out[] = banco_item_js($r) + ['dono' => $primeiro[banco_pessoa($r)]];
    }
    return $out;
}

/**
 * Os itens de uma pessoa nos dois potes (ativos, vencidos e pendentes; sem os presentes que ela deu) → [lado => [...]].
 * "pendente" = pagamento em conferência (só ela vê); "criado" = ordem das compras (a mais recente é o cadastro).
 */
function banco_meus_itens(int $usuarioId): array
{
    $out = array_fill_keys(array_keys(SIDES), []);
    if (!$usuarioId || !banco_ativo()) {
        return $out;
    }
    $st = db()->prepare('SELECT ' . BANCO_ITEM_CAMPOS . ' FROM itens i ' . BANCO_ITEM_JOINS . "
                         WHERE i.usuario_id = ? AND i.presente_token IS NULL AND i.status IN ('pendente', 'ativo', 'vencido')
                         ORDER BY i.criado_em, i.numero");
    $st->execute([$usuarioId]);
    foreach ($st->fetchAll() as $r) {
        $out[$r['side']][] = banco_item_js($r) + [
            'pendente' => $r['status'] === 'pendente',
            'criado'   => strtotime($r['criado_em']) * 1000 + (int) $r['id'] % 1000,
        ];
    }
    return $out;
}

/** Id do post como o JS conhece: a frase da compra é "lado-o<número do item>"; os outros, "lado-p<id do post>". */
function banco_id_post(string $lado, array $r): string
{
    return $r['is_frase_compra'] ? "$lado-o{$r['numero']}" : "$lado-p{$r['post_id']}";
}

/** Post (linha com p.* e i.numero) → formato do JS. Conta sem item: oliveId null e "autor" com o nome da conta. */
function banco_post_js(string $lado, array $r): array
{
    return [
        'id'      => banco_id_post($lado, $r),
        'oliveId' => $r['numero'] !== null ? (int) $r['numero'] : null,
        'autor'   => $r['numero'] === null ? ['nome' => (string) ($r['autor_nome'] ?? '')] : null,
        'text'    => (string) $r['texto'],
        'date'    => substr($r['criado_em'], 0, 10),
        'hora'    => $r['criado_em'], // desempate na ordem "recentes"
        'likes'   => (int) $r['curtidas_count'],
        'video'   => $r['video_id'] ? ['provider' => $r['video_provider'], 'id' => $r['video_id'], 'vertical' => (bool) $r['video_vertical']] : null,
    ];
}

/** Comentário (linha da consulta de banco_comentarios) → formato do JS. */
function banco_comentario_js(string $lado, array $r): array
{
    $lado = $r['post_lado'] ?? $lado; // o pote do post (o comentário pode ser de quem é do outro)
    $apagado = $r['status'] === 'removido';
    $c = [
        'id'        => "$lado-c{$r['id']}",
        'post'      => banco_id_post($lado, $r),
        'post_item' => (int) $r['numero'],
        // conta sem item: id 0 (sem perfil nem nível), no pote do post, com o nome da conta
        'autor'     => ['id' => (int) $r['autor_id'], 'side' => $r['autor_lado'] ?? $lado, 'nome' => $r['autor_nome'],
                        'foto' => $r['autor_foto'], 'tipo' => $r['autor_tipo'], 'selo' => $r['autor_selo']],
        'meu'       => banco_viewer() > 0 && (int) $r['autor_uid'] === banco_viewer(), // só quem escreveu apaga
        'texto'     => $apagado ? null : $r['texto'],
        'data'      => substr($r['criado_em'], 0, 10),
    ];
    if (!$apagado && $r['video_id']) {
        $c['video'] = ['provider' => $r['video_provider'], 'id' => $r['video_id'], 'vertical' => (bool) $r['video_vertical']];
    }
    if ($apagado) {
        $c['apagado'] = true;
    } elseif ($r['cita_id']) {
        // "↩ Respondendo a Fulano": o trecho do citado (ou o aviso, se ele foi apagado)
        $trecho = $r['cita_status'] === 'removido' ? 'comentário apagado' : ((string) $r['cita_texto'] !== '' ? $r['cita_texto'] : '🎬 vídeo');
        $c['cita'] = ['id' => "{$r['cita_lado']}-c{$r['cita_id']}", 'nome' => $r['cita_nome'],
                      'texto' => mb_strlen($trecho) > 90 ? rtrim(mb_substr($trecho, 0, 90)) . '…' : $trecho];
    }
    return $c;
}

const BANCO_COMENTARIO_SQL = "SELECT c.id, c.texto, c.video_provider, c.video_id, c.video_vertical, c.status, c.criado_em,
                                     p.id AS post_id, p.lado AS post_lado, p.is_frase_compra, dono.numero,
                                     COALESCE(quem.numero, 0) AS autor_id, quem.lado AS autor_lado, COALESCE(quem.nome, qu.nome) AS autor_nome,
                                     quem.foto_path AS autor_foto, t.slug AS autor_tipo, quem.selo_valor AS autor_selo, c.usuario_id AS autor_uid,
                                     c.cita_id, cc.texto AS cita_texto, cc.status AS cita_status, COALESCE(ci.nome, cu.nome) AS cita_nome, cp.lado AS cita_lado
                              FROM comentarios c
                              LEFT JOIN comentarios cc ON cc.id = c.cita_id
                              LEFT JOIN itens ci       ON ci.id = cc.item_id
                              LEFT JOIN usuarios cu    ON cu.id = cc.usuario_id
                              LEFT JOIN posts cp       ON cp.id = cc.post_id
                              JOIN posts p     ON p.id = c.post_id AND p.status = 'publicado'
                              LEFT JOIN itens dono ON dono.id = p.item_id
                              JOIN usuarios qu ON qu.id = c.usuario_id
                              LEFT JOIN itens quem ON quem.id = c.item_id
                              LEFT JOIN item_tipos t ON t.id = quem.item_tipo_id
                              WHERE c.status IN ('publicado', 'removido')
                                AND (quem.id IS NULL OR quem.status IN ('ativo', 'vencido') OR (quem.status = 'pendente' AND quem.usuario_id = ?)) ";

/** Posts que a pessoa curtiu (ids no formato do JS). */
function banco_minhas_curtidas(int $usuarioId): array
{
    if (!$usuarioId || !banco_ativo()) {
        return [];
    }
    $st = db()->prepare('SELECT p.id AS post_id, p.lado, p.is_frase_compra, i.numero
                         FROM curtidas cu JOIN posts p ON p.id = cu.post_id LEFT JOIN itens i ON i.id = p.item_id
                         WHERE cu.usuario_id = ?');
    $st->execute([$usuarioId]);
    return array_map(fn($r) => banco_id_post($r['lado'], $r), $st->fetchAll());
}

/**
 * Presentes que a pessoa deu e ninguém resgatou ainda (nos dois potes). "link" só depois do pagamento aprovado
 * (antes disso não dá para entregar algo que ainda não foi pago).
 */
function banco_meus_presentes(int $usuarioId): array
{
    if (!$usuarioId || !banco_ativo()) {
        return [];
    }
    $st = db()->prepare('SELECT ' . BANCO_ITEM_CAMPOS . ' FROM itens i ' . BANCO_ITEM_JOINS . "
                         WHERE i.usuario_id = ? AND i.presente_token IS NOT NULL AND i.status IN ('pendente', 'ativo')
                         ORDER BY i.criado_em, i.numero");
    $st->execute([$usuarioId]);
    return array_map(fn($r) => banco_item_js($r) + [
        'dono'     => (int) $r['id'],
        'pendente' => $r['status'] === 'pendente',
        'link'     => $r['status'] === 'ativo' ? url_absoluta('presente', ['t' => $r['presente_token']]) : null,
    ], $st->fetchAll());
}

/**
 * Os números (neste pote) de todas as azeitonas da pessoa dona do item $numero: o perfil é da pessoa.
 * Presente ainda não resgatado = só ele. Quem vê o próprio perfil enxerga também os pendentes e os vencidos.
 */
function banco_pessoa_numeros(string $lado, int $numero): array
{
    if (!banco_ativo()) {
        return [$numero];
    }
    $st = db()->prepare('SELECT usuario_id, presente_token FROM itens WHERE lado = ? AND numero = ?');
    $st->execute([$lado, $numero]);
    $item = $st->fetch();
    if (!$item || $item['presente_token'] !== null) {
        return [$numero];
    }
    $st = db()->prepare("SELECT numero FROM itens WHERE lado = ? AND usuario_id = ? AND presente_token IS NULL
                           AND (status = 'ativo' OR (status IN ('pendente', 'vencido') AND usuario_id = ?))
                         ORDER BY numero");
    $st->execute([$lado, $item['usuario_id'], banco_viewer()]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)) ?: [$numero];
}

const PERFIL_ITENS_POR_PAGINA = 5;

/**
 * A lista de azeitonas do perfil, 5 por vez (as mais recentes primeiro): "Carregar mais" busca as próximas.
 * Mesmas regras de banco_pessoa_numeros(): a dona vê também as pendentes e as vencidas.
 * Retorna ['itens' => [...], 'mais' => bool, 'total' => n].
 */
function banco_pessoa_itens(string $lado, int $numero, int $offset = 0, int $limite = PERFIL_ITENS_POR_PAGINA): array
{
    $numeros = banco_pessoa_numeros($lado, $numero);
    if (!banco_ativo()) {
        return ['itens' => [], 'mais' => false, 'total' => 0];
    }
    $offset = max(0, $offset);
    $st = db()->prepare('SELECT ' . BANCO_ITEM_CAMPOS . ' FROM itens i ' . BANCO_ITEM_JOINS . '
                         WHERE i.lado = ? AND i.numero IN (' . implode(',', array_map('intval', $numeros)) . ')
                         ORDER BY i.criado_em DESC, i.numero DESC LIMIT ' . (int) $limite . ' OFFSET ' . $offset);
    $st->execute([$lado]);
    $itens = array_map(fn($r) => banco_item_js($r) + ['pendente' => $r['status'] === 'pendente'], $st->fetchAll());
    return ['itens' => $itens, 'mais' => $offset + count($itens) < count($numeros), 'total' => count($numeros)];
}
