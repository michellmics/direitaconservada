<?php
// Presentes (migration 015): na compra, "🎁 É presente". Depois do pagamento aprovado, quem comprou recebe um link
// (/presente?c=…) para mandar no WhatsApp. Quem abre e toca em "Resgatar" vira dona do item (perfil, posts, renovação).
// Quem abrir o link primeiro e resgatar fica com ele.
require_once __DIR__ . '/pedidos.php';

/** O presente pelo token do link (null = não existe ou já foi resgatado). */
function presente_buscar(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{24}$/', $token)) {
        return null;
    }
    $st = db()->prepare("SELECT i.id AS item_id, i.numero, i.lado, i.nome, i.cidade, i.uf, i.frase, i.foto_path, i.selo_valor,
                                i.status, i.usuario_id, t.slug AS tipo, t.nome AS tipo_nome, u.nome AS quem_deu
                         FROM itens i JOIN item_tipos t ON t.id = i.item_tipo_id JOIN usuarios u ON u.id = i.usuario_id
                         WHERE i.presente_token = ? AND i.status IN ('pendente', 'ativo')");
    $st->execute([$token]);
    return $st->fetch() ?: null;
}

/** Passa o presente para a conta de quem resgatou. Retorna ['lado','numero'] ou ['erro' => …]. */
function presente_resgatar(string $token, int $usuarioId): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT id, lado, numero, usuario_id, status FROM itens WHERE presente_token = ? FOR UPDATE');
        $st->execute([$token]);
        $item = $st->fetch();
        if (!$item) {
            $pdo->rollBack();
            return ['erro' => 'Esse presente já foi resgatado (ou o link está errado).'];
        }
        if ($item['status'] !== 'ativo') {
            $pdo->rollBack();
            return ['erro' => 'Esse presente ainda não foi liberado. Tente de novo daqui a pouco.'];
        }
        if ((int) $item['usuario_id'] === $usuarioId) {
            $pdo->rollBack();
            return ['erro' => 'Esse é o presente que você deu: mande o link para a pessoa resgatar.'];
        }
        // no MySQL o SET vai da esquerda para a direita: presente_de guarda quem deu ANTES de trocar o dono
        $pdo->prepare('UPDATE itens SET presente_de = usuario_id, usuario_id = ?, presente_token = NULL, presente_resgatado_em = NOW()
                       WHERE id = ?')->execute([$usuarioId, $item['id']]);
        // a frase do presente no mural passa a ser de quem ganhou
        $pdo->prepare('UPDATE posts SET usuario_id = ? WHERE item_id = ?')->execute([$usuarioId, $item['id']]);
        $pdo->prepare('UPDATE comentarios SET usuario_id = ? WHERE item_id = ?')->execute([$usuarioId, $item['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    try {
        require_once __DIR__ . '/tempero.php';
        tempero_recalcular($usuarioId, $item['lado']); // o presente conta no nível de quem ganhou
    } catch (Throwable $e) {
    }
    logar('info', 'presente', 'presente_resgatado', "Presente " . $item['lado'] . " #" . $item['numero'] . " resgatado", ['de' => (int) $item['usuario_id'], 'item' => (int) $item['id']], $usuarioId);
    return ['lado' => $item['lado'], 'numero' => (int) $item['numero']];
}
