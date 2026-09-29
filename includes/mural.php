<?php
// Gravações do mural no banco: publicar, comentar, apagar comentário e curtir (as leituras estão em banco_dados.php).
// Sempre de quem está logado, com um item seu: publica com o item mais recente DESTE pote; comenta com o item
// mais recente de qualquer pote (os dois lados comentam). Item pendente (Pix em conferência) também vale:
// o que ele publica só aparece para os outros quando o pagamento for aprovado.
// Espera config.php e banco_dados.php carregados.

const MURAL_POSTS_POR_DIA       = 20;
const MURAL_COMENTARIOS_POR_DIA = 100;
const MURAL_POST_MAX            = 180; // letras (o link do vídeo não conta), igual a TEXT_MAX no JS
const MURAL_COMENTARIO_MAX      = 200; // igual a COMENTARIO_MAX no JS

/** Item com que a pessoa publica/comenta: o mais recente (neste pote, ou em qualquer um). */
function mural_meu_item(int $usuarioId, ?string $lado): ?array
{
    $sql = "SELECT id, numero, lado FROM itens WHERE usuario_id = ? AND presente_token IS NULL AND status IN ('pendente', 'ativo')"
         . ($lado ? ' AND lado = ?' : '') . ' ORDER BY criado_em DESC, id DESC LIMIT 1';
    $st = db()->prepare($sql);
    $st->execute($lado ? [$usuarioId, $lado] : [$usuarioId]);
    return $st->fetch() ?: null;
}

/** Vídeo vindo do JS ({ provider, id, vertical }) validado, ou null. */
function mural_video($v): ?array
{
    if (!is_array($v)) {
        return null;
    }
    $prov = (string) ($v['provider'] ?? '');
    $id = (string) ($v['id'] ?? '');
    $ok = ($prov === 'youtube' && preg_match('/^[\w-]{11}$/', $id)) || ($prov === 'tiktok' && preg_match('/^\d{8,25}$/', $id));
    return $ok ? ['provider' => $prov, 'id' => $id, 'vertical' => !empty($v['vertical']) || $prov === 'tiktok'] : null;
}

/** Post pelo id do JS ("lado-o42" = frase do item 42; "lado-p7" = publicação 7), se quem vê pode enxergá-lo. */
function mural_post(string $postId, int $viewer): ?array
{
    if (preg_match('/^(\w+)-o(\d+)$/', $postId, $m)) {
        $st = db()->prepare("SELECT p.* FROM posts p JOIN itens i ON i.id = p.item_id
                             WHERE p.lado = ? AND i.numero = ? AND p.is_frase_compra = 1 AND p.status = 'publicado'
                               AND (i.status = 'ativo' OR (i.status = 'pendente' AND i.usuario_id = ?))");
        $st->execute([$m[1], (int) $m[2], $viewer]);
    } elseif (preg_match('/^(\w+)-p(\d+)$/', $postId, $m)) {
        $st = db()->prepare("SELECT p.* FROM posts p JOIN itens i ON i.id = p.item_id
                             WHERE p.lado = ? AND p.id = ? AND p.status = 'publicado'
                               AND (i.status = 'ativo' OR (i.status = 'pendente' AND i.usuario_id = ?))");
        $st->execute([$m[1], (int) $m[2], $viewer]);
    } else {
        return null;
    }
    return $st->fetch() ?: null;
}

function mural_limite(string $sql, int $usuarioId, int $max): bool
{
    $st = db()->prepare($sql);
    $st->execute([$usuarioId]);
    return (int) $st->fetchColumn() < $max;
}

/** Publica no mural do pote. Retorna ['post' => formato do JS] ou ['erro' => …]. */
function mural_publicar(int $usuarioId, string $lado, string $texto, $video): array
{
    $texto = trim($texto);
    $video = mural_video($video);
    if ($texto === '' && !$video) {
        return ['erro' => 'Escreva algo ou cole um link de vídeo.'];
    }
    if (mb_strlen($texto) > MURAL_POST_MAX) {
        return ['erro' => 'Máximo de ' . MURAL_POST_MAX . ' caracteres (sem contar o link).'];
    }
    $item = mural_meu_item($usuarioId, $lado);
    if (!$item) {
        return ['erro' => 'Só quem tem ' . SIDES[$lado]['item'] . ' neste pote publica aqui.'];
    }
    if (!mural_limite("SELECT COUNT(*) FROM posts p JOIN itens i ON i.id = p.item_id
                       WHERE i.usuario_id = ? AND p.is_frase_compra = 0 AND p.criado_em > NOW() - INTERVAL 1 DAY", $usuarioId, MURAL_POSTS_POR_DIA)) {
        return ['erro' => 'Você já publicou bastante hoje. Volte amanhã!'];
    }
    $pdo = db();
    $pdo->prepare('INSERT INTO posts (lado, item_id, texto, video_provider, video_id, video_vertical) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$lado, $item['id'], $texto !== '' ? $texto : null, $video['provider'] ?? null, $video['id'] ?? null, (int) ($video['vertical'] ?? 0)]);
    $st = $pdo->prepare('SELECT p.id AS post_id, p.is_frase_compra, p.texto, p.video_provider, p.video_id, p.video_vertical,
                                p.curtidas_count, p.criado_em, ? AS numero FROM posts p WHERE p.id = ?');
    $st->execute([$item['numero'], $pdo->lastInsertId()]);
    return ['post' => banco_post_js($lado, $st->fetch())];
}

/** Comenta num post (de qualquer pote). $cita = id do JS do comentário citado ("lado-c12") ou null. */
function mural_comentar(int $usuarioId, string $postId, string $texto, $video, ?string $cita): array
{
    $texto = trim($texto);
    $video = mural_video($video);
    if ($texto === '' && !$video) {
        return ['erro' => 'Escreva algo ou cole um link de vídeo.'];
    }
    if (mb_strlen($texto) > MURAL_COMENTARIO_MAX) {
        return ['erro' => 'Comentário: até ' . MURAL_COMENTARIO_MAX . ' caracteres (sem contar o link do vídeo).'];
    }
    $post = mural_post($postId, $usuarioId);
    if (!$post) {
        return ['erro' => 'Essa publicação não está mais no mural.'];
    }
    $item = mural_meu_item($usuarioId, null);
    if (!$item) {
        return ['erro' => 'Para comentar, garanta seu item em um dos potes.'];
    }
    if (!mural_limite("SELECT COUNT(*) FROM comentarios c JOIN itens i ON i.id = c.item_id
                       WHERE i.usuario_id = ? AND c.criado_em > NOW() - INTERVAL 1 DAY", $usuarioId, MURAL_COMENTARIOS_POR_DIA)) {
        return ['erro' => 'Você já comentou bastante hoje. Volte amanhã!'];
    }
    $pdo = db();
    $citaId = null;
    if ($cita && preg_match('/^\w+-c(\d+)$/', $cita, $m)) {
        $st = $pdo->prepare('SELECT id FROM comentarios WHERE id = ? AND post_id = ?'); // só cita comentário do mesmo post
        $st->execute([(int) $m[1], $post['id']]);
        $citaId = $st->fetchColumn() ?: null;
    }
    $pdo->prepare('INSERT INTO comentarios (post_id, item_id, cita_id, texto, video_provider, video_id, video_vertical) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$post['id'], $item['id'], $citaId, $texto !== '' ? $texto : null, $video['provider'] ?? null, $video['id'] ?? null, (int) ($video['vertical'] ?? 0)]);
    $id = (int) $pdo->lastInsertId();
    $pdo->prepare('UPDATE posts SET comentarios_count = comentarios_count + 1 WHERE id = ?')->execute([$post['id']]);
    $st = $pdo->prepare(BANCO_COMENTARIO_SQL . 'AND c.id = ?');
    $st->execute([$usuarioId, $id]);
    return ['comentario' => comentario_para_js(banco_comentario_js($post['lado'], $st->fetch()))];
}

/** Apaga um comentário da própria pessoa (fica o aviso "comentário apagado"). */
function mural_apagar_comentario(int $usuarioId, string $comentarioId): array
{
    if (!preg_match('/^\w+-c(\d+)$/', $comentarioId, $m)) {
        return ['erro' => 'Comentário inválido.'];
    }
    $pdo = db();
    $st = $pdo->prepare("UPDATE comentarios c JOIN itens i ON i.id = c.item_id
                         SET c.status = 'removido' WHERE c.id = ? AND i.usuario_id = ? AND c.status = 'publicado'");
    $st->execute([(int) $m[1], $usuarioId]);
    if ($st->rowCount() !== 1) {
        return ['erro' => 'Não deu para apagar esse comentário.'];
    }
    $pdo->prepare('UPDATE posts p JOIN comentarios c ON c.post_id = p.id SET p.comentarios_count = GREATEST(p.comentarios_count, 1) - 1 WHERE c.id = ?')
        ->execute([(int) $m[1]]);
    return ['ok' => true];
}

/** Curte ou descurte. Retorna ['curtido' => bool, 'likes' => total]. */
function mural_curtir(int $usuarioId, string $postId, bool $curtir): array
{
    $post = mural_post($postId, $usuarioId);
    if (!$post) {
        return ['erro' => 'Essa publicação não está mais no mural.'];
    }
    $pdo = db();
    $st = $curtir
        ? $pdo->prepare('INSERT IGNORE INTO curtidas (post_id, usuario_id) VALUES (?, ?)')
        : $pdo->prepare('DELETE FROM curtidas WHERE post_id = ? AND usuario_id = ?');
    $st->execute([$post['id'], $usuarioId]);
    if ($st->rowCount()) { // só mexe no contador quando mudou de verdade (clique duplo não soma duas vezes)
        $pdo->prepare('UPDATE posts SET curtidas_count = GREATEST(curtidas_count + ?, 0) WHERE id = ?')->execute([$curtir ? 1 : -1, $post['id']]);
    }
    $st = $pdo->prepare('SELECT curtidas_count FROM posts WHERE id = ?');
    $st->execute([$post['id']]);
    return ['curtido' => $curtir, 'likes' => (int) $st->fetchColumn()];
}
