<?php
// Aviso ao cliente: "sua azeitona/pimenta vence em X dias" (cron/alertas; tabela item_avisos_vencimento, migration 021).
//   Faixas: 30, 15, 10, 5 e 1 dia(s) antes do valido_ate. Cada faixa sai uma vez por item e validade (renovou, a
//   validade muda e os avisos recomeçam no vencimento seguinte). Se a cron falhar um dia, sai a faixa em que o item está.
//   Não avisa: presente ainda não resgatado, item com renovação já aguardando pagamento, conta bloqueada, contas de teste (.local).
//   Só entre VENCIMENTO_HORA_INICIO e VENCIMENTO_HORA_FIM (ninguém recebe e-mail de madrugada).
//   Vários itens da mesma pessoa, no mesmo pote e na mesma faixa: um e-mail só.
require_once __DIR__ . '/db.php';

const VENCIMENTO_FAIXAS      = [1, 5, 10, 15, 30];
const VENCIMENTO_HORA_INICIO = 9;
const VENCIMENTO_HORA_FIM    = 20;

/** Manda os avisos que estão na hora. Retorna quantos e-mails saíram. */
function vencimentos_avisar(bool $forcar = false): int
{
    $h = (int) date('G');
    if (!$forcar && ($h < VENCIMENTO_HORA_INICIO || $h > VENCIMENTO_HORA_FIM)) {
        return 0;
    }
    if (!is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
        return 0;
    }
    $pdo = db();
    $st = $pdo->query("SELECT i.id, i.lado, i.numero, i.nome, i.desde, i.valido_ate, i.usuario_id, DATEDIFF(i.valido_ate, CURDATE()) AS dias,
                              t.nome AS tipo_nome, t.preco_centavos, u.nome AS usuario_nome, u.email
                       FROM itens i JOIN usuarios u ON u.id = i.usuario_id JOIN item_tipos t ON t.id = i.item_tipo_id
                       WHERE i.status = 'ativo' AND u.status = 'ativo'
                         AND i.valido_ate BETWEEN CURDATE() AND CURDATE() + INTERVAL " . max(VENCIMENTO_FAIXAS) . " DAY
                         AND NOT (i.presente_token IS NOT NULL AND i.presente_resgatado_em IS NULL)
                         AND NOT EXISTS (SELECT 1 FROM pedido_itens pi JOIN pedidos p ON p.id = pi.pedido_id
                                         WHERE pi.renova_item_id = i.id AND p.status = 'pendente')
                       ORDER BY i.usuario_id, i.lado, i.numero LIMIT 500");
    $jaFoi = $pdo->prepare('SELECT 1 FROM item_avisos_vencimento WHERE item_id = ? AND faixa = ? AND valido_ate = ?');
    $grupos = [];
    foreach ($st->fetchAll() as $i) {
        if (str_ends_with(strtolower($i['email']), '.local')) {
            continue;
        }
        $dias = (int) $i['dias'];
        $faixa = min(array_filter(VENCIMENTO_FAIXAS, fn($f) => $f >= max($dias, 1)));
        $jaFoi->execute([$i['id'], $faixa, $i['valido_ate']]);
        if ($jaFoi->fetchColumn()) {
            continue;
        }
        $grupos[$i['usuario_id'] . '|' . $i['lado'] . '|' . $faixa][] = $i + ['faixa' => $faixa];
    }

    require_once __DIR__ . '/mailer.php';
    $marca = $pdo->prepare('INSERT IGNORE INTO item_avisos_vencimento (item_id, faixa, valido_ate) VALUES (?, ?, ?)');
    $desfaz = $pdo->prepare('DELETE FROM item_avisos_vencimento WHERE item_id = ? AND faixa = ? AND valido_ate = ?');
    $enviados = 0;
    foreach (array_slice($grupos, 0, 100) as $itens) {
        // marca antes de mandar: duas crons ao mesmo tempo não mandam o mesmo aviso duas vezes
        $novos = [];
        foreach ($itens as $i) {
            $marca->execute([$i['id'], $i['faixa'], $i['valido_ate']]);
            if ($marca->rowCount() === 1) {
                $novos[] = $i;
            }
        }
        if (!$novos) {
            continue;
        }
        $u = $novos[0];
        $S = side($u['lado']);
        [$assunto, $html, $texto] = email_vencimento($S, nome_proprio($u['usuario_nome']), $novos);
        if (enviar_email($u['email'], nome_proprio($u['usuario_nome']), $assunto, $html, $texto) === null) {
            $enviados++;
            logar('info', 'email', 'aviso_vencimento', "Aviso de vencimento ({$u['dias']} dias) para {$u['email']}",
                ['itens' => array_column($novos, 'numero'), 'lado' => $u['lado'], 'faixa' => $u['faixa']], (int) $u['usuario_id']);
        } else {
            foreach ($novos as $i) { // falhou: tenta de novo na próxima cron
                $desfaz->execute([$i['id'], $i['faixa'], $i['valido_ate']]);
            }
        }
    }
    return $enviados;
}

/** E-mail do aviso de vencimento: [assunto, html, texto]. $itens = da mesma pessoa, pote e faixa. */
function email_vencimento(array $S, string $nome, array $itens): array
{
    $t = $S['theme'];
    $primeiro = e(explode(' ', $nome)[0]);
    $dias = (int) min(array_column($itens, 'dias'));
    $quando = $dias <= 0 ? 'vence hoje' : ($dias === 1 ? 'vence amanhã' : "vence em {$dias} dias");
    $varios = count($itens) > 1;
    $oque = $varios ? 'Suas ' . count($itens) . ' ' . $S['items'] : 'Sua ' . $S['item'];
    $verbo = $varios ? str_replace('vence', 'vencem', $quando) : $quando;

    $lista = '';
    $listaTexto = '';
    foreach ($itens as $i) {
        $num = '#' . str_pad((string) $i['numero'], 4, '0', STR_PAD_LEFT);
        $validade = date('d/m/Y', strtotime($i['valido_ate']));
        $preco = 'R$ ' . number_format((int) $i['preco_centavos'] / 100, 2, ',', '.');
        $lista .= '<div style="margin:0 0 10px;padding:12px 14px;background:#fff;border-radius:12px;border:1px solid #e4d9bd;">'
            . '<b>' . e($S['Item'] . ' ' . $i['tipo_nome'] . ' ' . $num) . '</b> · ' . e($i['nome'])
            . '<br><span style="font-size:13px;color:#6b6a55;">Válida até <b style="color:' . $t['ink'] . ';">' . $validade . '</b> · renovação por ' . $preco . '/ano</span></div>';
        $listaTexto .= "- {$S['Item']} {$i['tipo_nome']} {$num} ({$i['nome']}): válida até {$validade}, renovação por {$preco}/ano\n";
    }
    $desde = date('d/m/Y', strtotime(min(array_column($itens, 'desde'))));
    $link = url_absoluta('perfil', ['lado' => $S['slug'], 'id' => (int) $itens[0]['numero']]);

    $corpo = "<p style=\"margin:0 0 12px;\">Olá, {$primeiro}!</p>
          <p style=\"margin:0 0 16px;\"><b>" . e("$oque $verbo") . ".</b> Renove para continuar no pote, com sua frase no mural e o direito de publicar e comentar.</p>
          {$lista}
          <p style=\"margin:12px 0 20px;font-size:14px;\">Renovando <b>em dia</b>, você mantém o <b>“" . e($S['cert_since']) . " {$desde}”</b> e o anel de tempo (prata no 2º ano, ouro no 3º). Se deixar vencer, a data recomeça do zero.</p>
          " . email_botao($S, $link, $varios ? 'Renovar minhas ' . $S['items'] : 'Renovar minha ' . $S['item']) . "
          <p style=\"margin:0;font-size:13px;color:#6b6a55;\">No seu perfil, toque em “Renovar” e pague pelo Pix. Já renovou? É só ignorar este e-mail.</p>";
    $html = email_moldura($S, $S['emoji'], $dias <= 1 ? 'Último aviso' : 'Aviso de vencimento', $corpo);
    $texto = "Olá, {$primeiro}!\n\n{$oque} {$verbo}.\n\n{$listaTexto}\nRenovando em dia, você mantém o \"{$S['cert_since']} {$desde}\" e o anel de tempo. "
        . "Se deixar vencer, a data recomeça do zero.\n\nRenove pelo seu perfil: {$link}\n";
    $assunto = $S['emoji'] . ' ' . $oque . ' ' . $verbo . ($dias <= 1 ? ' ⏰' : '');
    return [$assunto, $html, $texto];
}
