<?php
// Nível ao lado do nome ("tempero"): soma compra, tempo no pote e participação.
// Os nomes e ícones dos níveis de cada pote ficam em includes/sides.php ('tempero').
// A mesma conta existe no JS (assets/js/app.js → "nível"): mantenha as duas em sincronia.
//
// A pontuação é por pessoa e por pote (quem tem itens nos dois potes tem um nível em cada).
//   compra        = R$ em itens ATIVOS (vencer faz perder; renovar mantém)
//   tempo         = meses desde o item ativo mais antigo
//   participação  = últimos 12 meses: posts, comentários feitos (máx. 5/dia, com texto
//                   de 10+ letras ou vídeo), comentários recebidos (do outro pote valem 2)
//                   e curtidas recebidas
// O último nível exige também compra: só participação leva no máximo ao penúltimo.
require_once __DIR__ . '/db.php';

const TEMPERO = [
    'por_real'            => 5,     // pontos por R$ 1 em itens ativos (itens de R$ 2,90 a 9,90)
    'por_mes'             => 1,     // cada mês no pote
    'post'                => 3,     // publicação no mural (a frase da compra não conta)
    'comentario'          => 1,
    'comentarios_por_dia' => 5,
    'comentario_min'      => 10,    // letras mínimas (resposta em vídeo sempre vale)
    'recebido'            => 1,     // comentário recebido de alguém do mesmo pote
    'recebido_outro'      => 2,     // ...de alguém do outro pote
    'curtida'             => 0.1,
    'janela_meses'        => 12,
    'faixas'              => [10, 50, 120, 250, 500, 1000], // pontos para entrar em cada nível (o item mais barato já dá o 1º)
    'topo_compra'         => 100,   // pontos de compra exigidos para o último nível
];

/** R$ em itens → pontos de compra. Conta em centavos para o PHP e o JS (pontosCompra) darem sempre o mesmo número. */
function tempero_pontos_compra(float $reais): int
{
    return intdiv((int) round($reais * 100) * TEMPERO['por_real'], 100);
}

/** Pontos a partir dos fatos: ['reais','meses','posts','comentarios','recebidos','recebidos_outro','curtidas']. */
function tempero_pontos(array $f): array
{
    $T = TEMPERO;
    $compra = tempero_pontos_compra($f['reais'] ?? 0);
    $tempo = (int) ($f['meses'] ?? 0) * $T['por_mes'];
    $part = (int) floor(($f['posts'] ?? 0) * $T['post'] + ($f['comentarios'] ?? 0) * $T['comentario']
        + ($f['recebidos'] ?? 0) * $T['recebido'] + ($f['recebidos_outro'] ?? 0) * $T['recebido_outro']
        + ($f['curtidas'] ?? 0) * $T['curtida']);
    return ['compra' => $compra, 'tempo' => $tempo, 'participacao' => $part, 'total' => $compra + $tempo + $part];
}

/** Nível 1..6 (0 = sem item ativo / abaixo da 1ª faixa). */
function tempero_nivel(array $pontos): int
{
    $n = 0;
    foreach (TEMPERO['faixas'] as $i => $min) {
        if ($pontos['total'] >= $min) {
            $n = $i + 1;
        }
    }
    $topo = count(TEMPERO['faixas']);
    return $n === $topo && $pontos['compra'] < TEMPERO['topo_compra'] ? $topo - 1 : $n;
}

/** Pontos que faltam para o nível $n (comprando: soma no total e na compra). Igual a faltaPara() no JS. */
function tempero_falta(array $pontos, int $n): int
{
    $topo = count(TEMPERO['faixas']);
    return max(TEMPERO['faixas'][$n - 1] - $pontos['total'], $n === $topo ? TEMPERO['topo_compra'] - $pontos['compra'] : 0);
}

/**
 * Guarda o nível e, se for o maior que a pessoa já alcançou neste pote, manda o e-mail "você subiu de nível".
 * Cair e voltar ao mesmo nível não repete o e-mail. Retorna true se mandou.
 */
function tempero_registrar_nivel(int $usuarioId, string $lado, array $pontos): bool
{
    $nivel = tempero_nivel($pontos);
    if ($nivel < 1 || !isset(SIDES[$lado])) {
        return false;
    }
    $pdo = db();
    $pdo->prepare('INSERT IGNORE INTO usuario_niveis (usuario_id, lado) VALUES (?, ?)')->execute([$usuarioId, $lado]);
    // só uma requisição ganha cada subida (duas abas ao mesmo tempo não mandam dois e-mails)
    $st = $pdo->prepare('UPDATE usuario_niveis SET nivel_maximo = ?, pontos = ?, avisado_em = NOW()
                         WHERE usuario_id = ? AND lado = ? AND nivel_maximo < ?');
    $st->execute([$nivel, $pontos['total'], $usuarioId, $lado, $nivel]);
    if ($st->rowCount() !== 1) {
        return false;
    }

    $st = $pdo->prepare("SELECT nome, email FROM usuarios WHERE id = ? AND status = 'ativo'");
    $st->execute([$usuarioId]);
    $u = $st->fetch();
    if (!$u || !is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
        return false; // sem PHPMailer (composer install): guarda o nível, mas não manda
    }
    require_once __DIR__ . '/mailer.php';
    $link = url_absoluta('pote', ['lado' => $lado], 'niveis');
    $nome = nome_proprio($u['nome']);
    [$assunto, $html, $texto] = email_nivel_subiu(side($lado), $nome, $nivel, $pontos, $link);
    return enviar_email($u['email'], $nome, $assunto, $html, $texto) === null;
}

/** Recalcula pelo banco e avisa se subiu. Chamar depois de pagamento confirmado, renovação, post ou comentário. */
function tempero_recalcular(int $usuarioId, string $lado): int
{
    $pontos = tempero_pontos(tempero_fatos($usuarioId, $lado));
    tempero_registrar_nivel($usuarioId, $lado, $pontos);
    return tempero_nivel($pontos);
}

/** Comentários que contam: com texto de 10+ letras (ou vídeo), no máximo 5 por dia. Recebe [['data','texto','video'], ...]. */
function tempero_contar_comentarios(array $comentarios): int
{
    $porDia = [];
    foreach ($comentarios as $c) {
        if (empty($c['video']) && mb_strlen(trim((string) ($c['texto'] ?? ''))) < TEMPERO['comentario_min']) {
            continue;
        }
        $dia = substr((string) $c['data'], 0, 10);
        $porDia[$dia] = min(TEMPERO['comentarios_por_dia'], ($porDia[$dia] ?? 0) + 1);
    }
    return array_sum($porDia);
}

function tempero_meses_desde(?string $desde, ?string $hoje = null): int
{
    if (!$desde) {
        return 0;
    }
    $d = (new DateTimeImmutable($desde))->diff(new DateTimeImmutable($hoje ?? date('Y-m-d')));
    return $d->invert ? 0 : $d->y * 12 + $d->m;
}

/** Fatos de uma pessoa num pote, direto do banco. */
function tempero_fatos(int $usuarioId, string $lado): array
{
    $pdo = db();
    $janela = 'NOW() - INTERVAL ' . (int) TEMPERO['janela_meses'] . ' MONTH';

    $st = $pdo->prepare("SELECT COALESCE(SUM(t.preco_centavos), 0) / 100 AS reais, MIN(i.desde) AS desde
                         FROM itens i JOIN item_tipos t ON t.id = i.item_tipo_id
                         WHERE i.usuario_id = ? AND i.lado = ? AND i.status = 'ativo'");
    $st->execute([$usuarioId, $lado]);
    $compra = $st->fetch();

    $st = $pdo->prepare("SELECT COUNT(*) FROM posts p JOIN itens i ON i.id = p.item_id
                         WHERE i.usuario_id = ? AND i.lado = ? AND p.is_frase_compra = 0
                           AND p.status = 'publicado' AND p.criado_em >= $janela");
    $st->execute([$usuarioId, $lado]);
    $posts = (int) $st->fetchColumn();

    $st = $pdo->prepare("SELECT c.criado_em AS data, c.texto, c.video_id AS video
                         FROM comentarios c JOIN itens i ON i.id = c.item_id
                         WHERE i.usuario_id = ? AND i.lado = ? AND c.status = 'publicado' AND c.criado_em >= $janela");
    $st->execute([$usuarioId, $lado]);
    $comentarios = tempero_contar_comentarios($st->fetchAll());

    // recebidos nos posts da pessoa (os que ela mesma escreveu não contam)
    $st = $pdo->prepare("SELECT COALESCE(SUM(quem.lado = autor.lado), 0) AS mesmo, COALESCE(SUM(quem.lado <> autor.lado), 0) AS outro
                         FROM comentarios c
                         JOIN posts p     ON p.id = c.post_id AND p.status = 'publicado'
                         JOIN itens autor ON autor.id = p.item_id
                         JOIN itens quem  ON quem.id = c.item_id
                         WHERE autor.usuario_id = ? AND autor.lado = ? AND quem.usuario_id <> autor.usuario_id
                           AND c.status = 'publicado' AND c.criado_em >= $janela");
    $st->execute([$usuarioId, $lado]);
    $recebidos = $st->fetch();

    $st = $pdo->prepare("SELECT COUNT(*) FROM curtidas cu
                         JOIN posts p ON p.id = cu.post_id AND p.status = 'publicado'
                         JOIN itens i ON i.id = p.item_id
                         WHERE i.usuario_id = ? AND i.lado = ? AND cu.usuario_id <> ? AND cu.criado_em >= $janela");
    $st->execute([$usuarioId, $lado, $usuarioId]);
    $curtidas = (int) $st->fetchColumn();

    return [
        'reais'           => (float) $compra['reais'],
        'meses'           => tempero_meses_desde($compra['desde']),
        'posts'           => $posts,
        'comentarios'     => $comentarios,
        'recebidos'       => (int) $recebidos['mesmo'],
        'recebidos_outro' => (int) $recebidos['outro'],
        'curtidas'        => $curtidas,
    ];
}

/**
 * Mesmos fatos a partir de data/mock.php, para os dois potes (vão para o JS em DC.tempero).
 * Retorna ['regras' => TEMPERO, 'fatos' => [lado => [dono => fatos]], 'donos' => [lado => [item => dono]]].
 * "dono" = id do 1º item da pessoa; só vão em 'donos' os itens de quem comprou mais de um.
 */
function tempero_mock(): array
{
    $hoje = date('Y-m-d');
    $items = $comments = $fatos = $donos = [];
    foreach (array_keys(SIDES) as $lado) {
        $items[$lado] = array_column(mock_items($lado), null, 'id');
        $comments = array_merge($comments, mock_comments($lado, array_values($items[$lado])));
    }
    $donoDe = fn(string $lado, int $id) => $items[$lado][$id]['dono'] ?? $id;
    $vazio = ['reais' => 0, 'meses' => 0, 'posts' => 0, 'comentarios' => 0, 'recebidos' => 0, 'recebidos_outro' => 0, 'curtidas' => 0];

    // compra, tempo e curtidas (a frase da compra é o post do item; as curtidas são dele)
    $desde = [];
    foreach ($items as $lado => $lista) {
        foreach ($lista as $o) {
            if (($o['valido_ate'] ?? $hoje) < $hoje) {
                continue;
            }
            $d = $donoDe($lado, $o['id']);
            $fatos[$lado][$d] ??= $vazio;
            $fatos[$lado][$d]['reais'] += SIDES[$lado]['types'][$o['tipo']]['price'];
            $fatos[$lado][$d]['curtidas'] += $o['likes'];
            $desde[$lado][$d] = min($desde[$lado][$d] ?? $o['desde'], $o['desde']);
            if ($d !== $o['id']) {
                $donos[$lado][$o['id']] = $d;
            }
        }
        foreach ($desde[$lado] ?? [] as $d => $data) {
            $fatos[$lado][$d]['meses'] = tempero_meses_desde($data, $hoje);
        }
    }

    // comentários feitos (máx. por dia) e recebidos
    $feitos = [];
    foreach ($comments as $c) {
        if (!empty($c['apagado'])) {
            continue; // comentário apagado não conta
        }
        $a = $c['autor'];
        $quem = $donoDe($a['side'], $a['id']);
        $lado = explode('-', $c['post'], 2)[0];
        $autor = $donoDe($lado, (int) $c['post_item']); // item dono do post (frase da compra ou outra publicação)
        if ($a['side'] === $lado && $quem === $autor) {
            continue; // comentou no próprio post
        }
        $feitos[$a['side']][$quem][] = $c;
        if (isset($fatos[$lado][$autor])) {
            $fatos[$lado][$autor][$a['side'] === $lado ? 'recebidos' : 'recebidos_outro']++;
        }
    }
    foreach ($feitos as $lado => $porDono) {
        foreach ($porDono as $d => $lista) {
            if (isset($fatos[$lado][$d])) {
                $fatos[$lado][$d]['comentarios'] = tempero_contar_comentarios($lista);
            }
        }
    }

    return ['regras' => TEMPERO, 'fatos' => $fatos, 'donos' => $donos];
}
