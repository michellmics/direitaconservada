<?php
// Lê do banco os itens, posts e comentários, no mesmo formato que data/mock.php usava
// (o site todo continua chamando mock_items() / mock_comments(), que tentam o banco primeiro).
// Retorna null quando o banco está fora do ar ou ainda sem itens: aí o site usa os dados gerados em data/mock.php.
// Para popular o banco com gente de mentira: php database/seed.php
require_once __DIR__ . '/db.php';

/** O banco tem itens? (uma consulta por página) */
function banco_ativo(): bool
{
    static $ativo = null;
    if ($ativo === null) {
        try {
            $ativo = (bool) db()->query("SELECT 1 FROM itens WHERE status = 'ativo' LIMIT 1")->fetchColumn();
        } catch (Throwable $e) {
            $ativo = false; // sem banco (ou sem as tabelas): segue com os dados gerados
        }
    }
    return $ativo;
}

/** Itens no pote (ativos). "id" = número do item no pote (#0042); "dono" = 1º item da mesma pessoa neste pote. */
function banco_itens(string $lado): ?array
{
    if (!banco_ativo()) {
        return null;
    }
    $st = db()->prepare("SELECT i.numero AS id, i.lado AS side, i.nome, i.foto_path AS foto, i.cidade, i.uf, t.slug AS tipo,
                                i.frase, i.desde, i.valido_ate, i.selo_valor AS selo, i.usuario_id,
                                COALESCE(p.curtidas_count, 0) AS likes, p.video_provider, p.video_id, p.video_vertical
                         FROM itens i
                         JOIN item_tipos t ON t.id = i.item_tipo_id
                         LEFT JOIN posts p ON p.item_id = i.id AND p.is_frase_compra = 1 AND p.status = 'publicado'
                         WHERE i.lado = ? AND i.status = 'ativo'
                         ORDER BY i.numero");
    $st->execute([$lado]);
    $out = [];
    $primeiro = [];
    foreach ($st->fetchAll() as $r) {
        $primeiro[$r['usuario_id']] ??= (int) $r['id'];
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
            'dono'       => $primeiro[$r['usuario_id']],
        ];
        if ($r['video_id']) {
            $item['video'] = ['provider' => $r['video_provider'], 'id' => $r['video_id'], 'vertical' => (bool) $r['video_vertical']];
        }
        $out[] = $item;
    }
    return $out;
}

/** Id do post como o JS conhece: a frase da compra é "lado-o<número do item>"; os outros, "lado-p<id do post>". */
function banco_id_post(string $lado, array $r): string
{
    return $r['is_frase_compra'] ? "$lado-o{$r['numero']}" : "$lado-p{$r['post_id']}";
}

/** Posts publicados de um pote (frases das compras + publicações), de itens ativos. */
function banco_posts(string $lado): ?array
{
    if (!banco_ativo()) {
        return null;
    }
    $st = db()->prepare("SELECT p.id AS post_id, p.is_frase_compra, p.texto, p.video_provider, p.video_id, p.video_vertical,
                                p.curtidas_count, p.criado_em, i.numero
                         FROM posts p JOIN itens i ON i.id = p.item_id AND i.status = 'ativo'
                         WHERE p.lado = ? AND p.status = 'publicado'");
    $st->execute([$lado]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $out[] = [
            'id'      => banco_id_post($lado, $r),
            'oliveId' => (int) $r['numero'],
            'text'    => (string) $r['texto'],
            'date'    => substr($r['criado_em'], 0, 10),
            'hora'    => $r['criado_em'], // desempate na ordem "recentes"
            'likes'   => (int) $r['curtidas_count'],
            'video'   => $r['video_id'] ? ['provider' => $r['video_provider'], 'id' => $r['video_id'], 'vertical' => (bool) $r['video_vertical']] : null,
        ];
    }
    return $out;
}

/** Comentários feitos nos posts de um pote (inclui os apagados, que aparecem como "comentário apagado"). */
function banco_comentarios(string $lado): ?array
{
    if (!banco_ativo()) {
        return null;
    }
    $st = db()->prepare("SELECT c.id, c.texto, c.video_provider, c.video_id, c.video_vertical, c.status, c.criado_em,
                                p.id AS post_id, p.is_frase_compra, dono.numero,
                                quem.numero AS autor_id, quem.lado AS autor_lado, quem.nome AS autor_nome,
                                quem.foto_path AS autor_foto, t.slug AS autor_tipo, quem.selo_valor AS autor_selo
                         FROM comentarios c
                         JOIN posts p     ON p.id = c.post_id AND p.lado = ? AND p.status = 'publicado'
                         JOIN itens dono  ON dono.id = p.item_id
                         JOIN itens quem  ON quem.id = c.item_id
                         JOIN item_tipos t ON t.id = quem.item_tipo_id
                         WHERE c.status IN ('publicado', 'removido')
                         ORDER BY c.criado_em, c.id");
    $st->execute([$lado]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $apagado = $r['status'] === 'removido';
        $c = [
            'id'        => "$lado-c{$r['id']}",
            'post'      => banco_id_post($lado, $r),
            'post_item' => (int) $r['numero'],
            'autor'     => ['id' => (int) $r['autor_id'], 'side' => $r['autor_lado'], 'nome' => $r['autor_nome'],
                            'foto' => $r['autor_foto'], 'tipo' => $r['autor_tipo'], 'selo' => $r['autor_selo']],
            'texto'     => $apagado ? null : $r['texto'],
            'data'      => substr($r['criado_em'], 0, 10),
        ];
        if (!$apagado && $r['video_id']) {
            $c['video'] = ['provider' => $r['video_provider'], 'id' => $r['video_id'], 'vertical' => (bool) $r['video_vertical']];
        }
        if ($apagado) {
            $c['apagado'] = true;
        }
        $out[] = $c;
    }
    return $out;
}
