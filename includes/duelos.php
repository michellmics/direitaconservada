<?php
// Tretódromo: duelos 1×1 (migration 026). Páginas: /tretodromo (arena) e /duelo?n=ID. API: api/duelo.php.
//   Quem pode: logado e com um item ativo (azeitona ou pimenta). Sempre contra alguém do OUTRO pote.
//   a = quem desafiou (manda o argumento de abertura junto), b = quem foi desafiado.
//   Aceite em DUELO_HORAS h; depois rodadas 1 a 3 (a, depois b), DUELO_HORAS h por resposta (senão perde por W.O.);
//   após o 6º argumento, DUELO_HORAS h de votação. Votar: qualquer conta logada, menos os dois duelistas
//   (vale durante o duelo todo; dá para mudar o voto). Empate nos votos = empate.
//   Prazos vencidos são resolvidos por duelos_atualizar() (ao abrir as páginas e pela cron).
// Avisos (push + e-mail): desafio recebido, sua vez, desafio aceito/recusado e resultado.
require_once __DIR__ . '/db.php';

const DUELO_HORAS = 24;
const DUELO_TEMA_MIN = 8;
const DUELO_TEMA_MAX = 140;
const DUELO_ARG_MIN = 10;
const DUELO_ARG_MAX = 500;
const DUELO_ABERTOS_MAX = 3;   // desafios aguardando aceite, por pessoa
const DUELO_POR_DIA = 5;       // desafios lançados por pessoa em 24 h
const DUELO_RODADAS = ['Abertura', 'Réplica', 'Tréplica'];

// dados do duelo com os dois itens (nome, pote, número, estado, dono)
const DUELO_SQL = "SELECT d.*,
        ia.nome AS a_nome, ia.lado AS a_lado, ia.numero AS a_numero, ia.uf AS a_uf, ia.usuario_id AS a_uid,
        ib.nome AS b_nome, ib.lado AS b_lado, ib.numero AS b_numero, ib.uf AS b_uf, ib.usuario_id AS b_uid
    FROM duelos d JOIN itens ia ON ia.id = d.item_a JOIN itens ib ON ib.id = d.item_b";

/** Itens da pessoa que podem duelar (ativos, não presente pendente). */
function duelo_meus_itens(int $usuarioId): array
{
    $st = db()->prepare("SELECT id, lado, numero, nome, uf FROM itens
                         WHERE usuario_id = ? AND status = 'ativo' AND presente_token IS NULL ORDER BY lado, numero");
    $st->execute([$usuarioId]);
    return $st->fetchAll();
}

function duelo_buscar(int $id): ?array
{
    $st = db()->prepare(DUELO_SQL . ' WHERE d.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Argumentos do duelo: [rodada][a|b] => ['texto', 'criado_em']. */
function duelo_argumentos(int $id): array
{
    $st = db()->prepare('SELECT rodada, lado, texto, criado_em FROM duelo_argumentos WHERE duelo_id = ? ORDER BY rodada, lado');
    $st->execute([$id]);
    $out = [];
    foreach ($st as $r) {
        $out[(int) $r['rodada']][$r['lado']] = $r;
    }
    return $out;
}

/** Vitórias e derrotas de um item (duelos encerrados). */
function duelo_historico(int $itemId): array
{
    $st = db()->prepare("SELECT
            SUM((item_a = ? AND vencedor = 'a') OR (item_b = ? AND vencedor = 'b')) AS v,
            SUM((item_a = ? AND vencedor = 'b') OR (item_b = ? AND vencedor = 'a')) AS d
        FROM duelos WHERE status = 'encerrado' AND (item_a = ? OR item_b = ?)");
    $st->execute(array_fill(0, 6, $itemId));
    $r = $st->fetch();
    return ['v' => (int) $r['v'], 'd' => (int) $r['d']];
}

/** Qual lado do duelo a pessoa é ('a', 'b') ou null (plateia). */
function duelo_meu_lado(array $d, ?int $usuarioId): ?string
{
    return !$usuarioId ? null : ((int) $d['a_uid'] === $usuarioId ? 'a' : ((int) $d['b_uid'] === $usuarioId ? 'b' : null));
}

function duelo_link(int $id, bool $absoluto = false): string
{
    return ($absoluto ? url_base() : '') . 'duelo?n=' . $id;
}

/** Resolve prazos vencidos: desafio não aceito expira, quem não respondeu perde por W.O., votação encerrada apura. */
function duelos_atualizar(): int
{
    $pdo = db();
    $n = $pdo->exec("UPDATE duelos SET status = 'expirado', encerrado_em = NOW() WHERE status = 'aguardando' AND prazo_em < NOW()");
    $fim = $pdo->query("SELECT id FROM duelos WHERE status IN ('andamento', 'votacao') AND prazo_em < NOW() LIMIT 50")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($fim as $id) {
        duelo_encerrar((int) $id);
        $n++;
    }
    return $n;
}

/** Encerra (W.O. de quem estava na vez, ou apuração da votação) e avisa os dois. */
function duelo_encerrar(int $id): void
{
    $pdo = db();
    $d = duelo_buscar($id);
    if (!$d || !in_array($d['status'], ['andamento', 'votacao'], true)) {
        return;
    }
    if ($d['status'] === 'andamento') {
        $vencedor = $d['vez'] === 'a' ? 'b' : 'a';
        $wo = 1;
    } else {
        $vencedor = $d['votos_a'] > $d['votos_b'] ? 'a' : ($d['votos_b'] > $d['votos_a'] ? 'b' : 'empate');
        $wo = 0;
    }
    $st = $pdo->prepare("UPDATE duelos SET status = 'encerrado', vencedor = ?, wo = ?, vez = NULL, encerrado_em = NOW()
                         WHERE id = ? AND status = ?");
    $st->execute([$vencedor, $wo, $id, $d['status']]);
    if ($st->rowCount() !== 1) {
        return; // outro processo encerrou junto
    }
    logar('info', 'duelo', 'duelo_encerrado', "Duelo #$id encerrado: " . ($wo ? 'W.O.' : $vencedor), ['duelo' => $id, 'vencedor' => $vencedor, 'wo' => $wo]);
    foreach (['a', 'b'] as $l) {
        $outro = $l === 'a' ? 'b' : 'a';
        [$titulo, $texto] = match (true) {
            $vencedor === 'empate' => ['🤝 Duelo empatado!', 'Terminou empatado na votação: "' . $d['tema'] . '".'],
            $vencedor === $l => ['🏆 Você venceu o duelo!', ($wo ? nome_proprio($d[$outro . '_nome']) . ' não respondeu a tempo: vitória por W.O. ' : 'A plateia escolheu você! ')
                . 'Pegue o card de vitória e mostre para todo mundo.'],
            default => ['😤 Você perdeu o duelo', ($wo ? 'O prazo para responder acabou. ' : 'A plateia preferiu ' . nome_proprio($d[$outro . '_nome']) . '. ')
                . 'Quer revanche? Lance um novo desafio.'],
        };
        duelo_avisar((int) $d[$l . '_uid'], $d[$l . '_lado'], $titulo, $texto, $id);
    }
}

/**
 * Lança o desafio: meu item (id) × item do outro pote (id), tema e o argumento de abertura.
 * Retorna ['duelo' => id] ou ['erro' => mensagem].
 */
function duelo_criar(int $usuarioId, int $meuItem, int $itemOponente, string $tema, string $argumento): array
{
    $tema = trim(preg_replace('/\s+/u', ' ', $tema));
    $argumento = trim($argumento);
    if (mb_strlen($tema) < DUELO_TEMA_MIN || mb_strlen($tema) > DUELO_TEMA_MAX) {
        return ['erro' => 'O tema precisa ter de ' . DUELO_TEMA_MIN . ' a ' . DUELO_TEMA_MAX . ' caracteres.'];
    }
    if (mb_strlen($argumento) < DUELO_ARG_MIN || mb_strlen($argumento) > DUELO_ARG_MAX) {
        return ['erro' => 'O argumento precisa ter de ' . DUELO_ARG_MIN . ' a ' . DUELO_ARG_MAX . ' caracteres.'];
    }
    $meus = array_column(duelo_meus_itens($usuarioId), null, 'id');
    if (!$meus) {
        return ['erro' => 'Para duelar, garanta sua azeitona ou pimenta em um dos potes.'];
    }
    if (!isset($meus[$meuItem])) {
        return ['erro' => 'Escolha com qual dos seus itens você vai duelar.'];
    }
    $pdo = db();
    $st = $pdo->prepare("SELECT id, lado, usuario_id, nome FROM itens WHERE id = ? AND status = 'ativo' AND presente_token IS NULL");
    $st->execute([$itemOponente]);
    $op = $st->fetch();
    if (!$op || (int) $op['usuario_id'] === $usuarioId) {
        return ['erro' => 'Escolha um oponente válido.'];
    }
    if ($op['lado'] === $meus[$meuItem]['lado']) {
        return ['erro' => 'No Tretódromo o duelo é contra o outro pote.'];
    }
    $st = $pdo->prepare("SELECT
            SUM(d.status = 'aguardando' AND ia.usuario_id = ?) AS abertos,
            SUM(d.criado_em > NOW() - INTERVAL 1 DAY AND ia.usuario_id = ?) AS hoje,
            SUM(d.status IN ('aguardando', 'andamento', 'votacao') AND ((d.item_a = ? AND d.item_b = ?) OR (d.item_a = ? AND d.item_b = ?))) AS mesmo
        FROM duelos d JOIN itens ia ON ia.id = d.item_a");
    $st->execute([$usuarioId, $usuarioId, $meuItem, $itemOponente, $itemOponente, $meuItem]);
    $lim = array_map('intval', $st->fetch());
    if ($lim['mesmo'] > 0) {
        return ['erro' => 'Vocês dois já têm um duelo em aberto. Termine esse primeiro!'];
    }
    if ($lim['abertos'] >= DUELO_ABERTOS_MAX) {
        return ['erro' => 'Você já tem ' . DUELO_ABERTOS_MAX . ' desafios esperando resposta. Espere algum ser aceito.'];
    }
    if ($lim['hoje'] >= DUELO_POR_DIA) {
        return ['erro' => 'Você já lançou ' . DUELO_POR_DIA . ' desafios hoje. Volte amanhã!'];
    }
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO duelos (tema, item_a, item_b, prazo_em) VALUES (?, ?, ?, NOW() + INTERVAL ' . DUELO_HORAS . ' HOUR)')
        ->execute([$tema, $meuItem, $itemOponente]);
    $id = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO duelo_argumentos (duelo_id, rodada, lado, texto) VALUES (?, 1, 'a', ?)")->execute([$id, $argumento]);
    $pdo->commit();
    logar('info', 'duelo', 'duelo_criado', "Duelo #$id: $tema", ['duelo' => $id, 'a' => $meuItem, 'b' => $itemOponente], $usuarioId);
    duelo_avisar((int) $op['usuario_id'], $op['lado'], '⚔️ ' . nome_proprio($meus[$meuItem]['nome']) . ' te desafiou para um duelo!',
        'Tema: "' . $tema . '". Você tem ' . DUELO_HORAS . ' h para aceitar. Vai amarelar?', $id, true);
    return ['duelo' => $id];
}

/** Desafiado aceita ou recusa. */
function duelo_responder_desafio(int $usuarioId, int $id, bool $aceitar): array
{
    $d = duelo_buscar($id);
    if (!$d || duelo_meu_lado($d, $usuarioId) !== 'b') {
        return ['erro' => 'Esse desafio não é para você.'];
    }
    if ($d['status'] !== 'aguardando' || strtotime($d['prazo_em']) < time()) {
        return ['erro' => 'Esse desafio não está mais esperando resposta.'];
    }
    $st = db()->prepare($aceitar
        ? "UPDATE duelos SET status = 'andamento', vez = 'b', rodada = 1, prazo_em = NOW() + INTERVAL " . DUELO_HORAS . " HOUR WHERE id = ? AND status = 'aguardando'"
        : "UPDATE duelos SET status = 'recusado', encerrado_em = NOW() WHERE id = ? AND status = 'aguardando'");
    $st->execute([$id]);
    if ($st->rowCount() !== 1) {
        return ['erro' => 'Esse desafio não está mais esperando resposta.'];
    }
    logar('info', 'duelo', $aceitar ? 'duelo_aceito' : 'duelo_recusado', "Duelo #$id " . ($aceitar ? 'aceito' : 'recusado'), ['duelo' => $id], $usuarioId);
    $bNome = nome_proprio($d['b_nome']);
    duelo_avisar((int) $d['a_uid'], $d['a_lado'],
        $aceitar ? "🔥 $bNome aceitou o seu desafio!" : "🐔 $bNome recusou o desafio",
        $aceitar ? "O duelo começou. Agora é a vez de $bNome responder à sua abertura." :'Amarelou! Desafie outra pessoa no Tretódromo.', $id);
    return ['ok' => true];
}

/** Argumento de quem está na vez. Depois do 6º, abre a votação. */
function duelo_argumentar(int $usuarioId, int $id, string $texto): array
{
    $texto = trim($texto);
    if (mb_strlen($texto) < DUELO_ARG_MIN || mb_strlen($texto) > DUELO_ARG_MAX) {
        return ['erro' => 'O argumento precisa ter de ' . DUELO_ARG_MIN . ' a ' . DUELO_ARG_MAX . ' caracteres.'];
    }
    $d = duelo_buscar($id);
    $lado = $d ? duelo_meu_lado($d, $usuarioId) : null;
    if (!$d || $d['status'] !== 'andamento' || !$lado || $d['vez'] !== $lado) {
        return ['erro' => 'Não é a sua vez neste duelo.'];
    }
    if (strtotime($d['prazo_em']) < time()) {
        return ['erro' => 'O prazo para responder acabou.'];
    }
    $rodada = (int) $d['rodada'];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO duelo_argumentos (duelo_id, rodada, lado, texto) VALUES (?, ?, ?, ?)')->execute([$id, $rodada, $lado, $texto]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        return ['erro' => 'Esse argumento já foi enviado.'];
    }
    $ultimo = $lado === 'b' && $rodada >= count(DUELO_RODADAS);
    if ($ultimo) {
        $pdo->prepare("UPDATE duelos SET status = 'votacao', vez = NULL, prazo_em = NOW() + INTERVAL " . DUELO_HORAS . ' HOUR WHERE id = ?')->execute([$id]);
    } else {
        $pdo->prepare('UPDATE duelos SET vez = ?, rodada = ?, prazo_em = NOW() + INTERVAL ' . DUELO_HORAS . ' HOUR WHERE id = ?')
            ->execute([$lado === 'a' ? 'b' : 'a', $lado === 'b' ? $rodada + 1 : $rodada, $id]);
    }
    $pdo->commit();
    $outro = $lado === 'a' ? 'b' : 'a';
    $nome = nome_proprio($d[$lado . '_nome']);
    if ($ultimo) {
        foreach (['a', 'b'] as $l) {
            duelo_avisar((int) $d[$l . '_uid'], $d[$l . '_lado'], '🗳️ Duelo na votação!', 'Os argumentos acabaram. A plateia tem ' . DUELO_HORAS . ' h para votar: chame a sua torcida!', $id);
        }
    } else {
        duelo_avisar((int) $d[$outro . '_uid'], $d[$outro . '_lado'], "⏰ Sua vez! $nome respondeu no duelo",
            '"' . mb_strimwidth($texto, 0, 120, '…') . '" Você tem ' . DUELO_HORAS . ' h para responder, senão perde por W.O.', $id);
    }
    return ['ok' => true];
}

/** Voto da plateia (muda se votar de novo). */
function duelo_votar(int $usuarioId, int $id, string $voto): array
{
    if (!in_array($voto, ['a', 'b'], true)) {
        return ['erro' => 'Voto inválido.'];
    }
    $d = duelo_buscar($id);
    if (!$d || !in_array($d['status'], ['andamento', 'votacao'], true)) {
        return ['erro' => 'Esse duelo não está aberto para votos.'];
    }
    if (duelo_meu_lado($d, $usuarioId)) {
        return ['erro' => 'Duelista não vota no próprio duelo!'];
    }
    $pdo = db();
    $pdo->prepare('INSERT INTO duelo_votos (duelo_id, usuario_id, voto) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE voto = VALUES(voto)')
        ->execute([$id, $usuarioId, $voto]);
    $pdo->prepare("UPDATE duelos SET votos_a = (SELECT COUNT(*) FROM duelo_votos WHERE duelo_id = ? AND voto = 'a'),
                                     votos_b = (SELECT COUNT(*) FROM duelo_votos WHERE duelo_id = ? AND voto = 'b') WHERE id = ?")
        ->execute([$id, $id, $id]);
    $st = $pdo->prepare('SELECT votos_a, votos_b FROM duelos WHERE id = ?');
    $st->execute([$id]);
    return ['ok' => true, 'votos' => array_map('intval', $st->fetch())];
}

function duelo_meu_voto(int $id, ?int $usuarioId): ?string
{
    if (!$usuarioId) {
        return null;
    }
    $st = db()->prepare('SELECT voto FROM duelo_votos WHERE duelo_id = ? AND usuario_id = ?');
    $st->execute([$id, $usuarioId]);
    return $st->fetchColumn() ?: null;
}

/** Busca oponentes (itens ativos do pote $lado) pelo nome. */
function duelo_buscar_oponentes(string $lado, string $q, int $usuarioId): array
{
    $q = trim($q);
    if (mb_strlen($q) < 2) {
        return [];
    }
    $st = db()->prepare("SELECT id, nome, numero, uf, lado FROM itens
                         WHERE lado = ? AND status = 'ativo' AND presente_token IS NULL AND usuario_id <> ? AND nome LIKE ?
                         ORDER BY nome LIMIT 8");
    $st->execute([$lado, $usuarioId, '%' . addcslashes($q, '%_\\') . '%']);
    return array_map(fn($r) => ['id' => (int) $r['id'], 'nome' => nome_proprio($r['nome']), 'numero' => (int) $r['numero'], 'uf' => $r['uf'], 'lado' => $r['lado']], $st->fetchAll());
}

/** Lista para a arena: 'aovivo' (andamento + votação), 'aguardando' ou 'encerrados'. */
function duelos_listar(string $filtro, int $limite = 24): array
{
    $onde = match ($filtro) {
        'aguardando' => "d.status = 'aguardando'",
        'encerrados' => "d.status = 'encerrado'",
        default      => "d.status IN ('andamento', 'votacao')",
    };
    $ordem = $filtro === 'encerrados' ? 'd.encerrado_em DESC' : '(d.votos_a + d.votos_b) DESC, d.criado_em DESC';
    return db()->query(DUELO_SQL . " WHERE $onde ORDER BY $ordem LIMIT " . (int) $limite)->fetchAll();
}

/** Duelos da pessoa que pedem atenção (desafio para aceitar, vez de responder) e os em aberto. */
function duelos_da_pessoa(int $usuarioId): array
{
    $st = db()->prepare(DUELO_SQL . " WHERE (ia.usuario_id = ? OR ib.usuario_id = ?) AND d.status IN ('aguardando', 'andamento', 'votacao')
                                      ORDER BY d.prazo_em");
    $st->execute([$usuarioId, $usuarioId]);
    return $st->fetchAll();
}

/** Quantos duelos esperam uma ação da pessoa (aceitar ou responder): o selo no link do Tretódromo. */
function duelos_pendentes(int $usuarioId): int
{
    $st = db()->prepare("SELECT COUNT(*) FROM duelos d JOIN itens ia ON ia.id = d.item_a JOIN itens ib ON ib.id = d.item_b
                         WHERE d.prazo_em > NOW() AND ((d.status = 'aguardando' AND ib.usuario_id = ?)
                            OR (d.status = 'andamento' AND ((d.vez = 'a' AND ia.usuario_id = ?) OR (d.vez = 'b' AND ib.usuario_id = ?))))");
    $st->execute([$usuarioId, $usuarioId, $usuarioId]);
    return (int) $st->fetchColumn();
}

/** Gladiadores do mês (mais vitórias nos últimos 30 dias) e placar dos potes no mês. */
function duelos_ranking(): array
{
    $gladiadores = db()->query("SELECT i.id, i.nome, i.lado, i.numero, COUNT(*) AS v FROM duelos d
            JOIN itens i ON i.id = IF(d.vencedor = 'a', d.item_a, d.item_b)
            WHERE d.status = 'encerrado' AND d.vencedor IN ('a', 'b') AND d.encerrado_em > NOW() - INTERVAL 30 DAY
            GROUP BY i.id, i.nome, i.lado, i.numero ORDER BY v DESC, MAX(d.encerrado_em) DESC LIMIT 5")->fetchAll();
    $potes = db()->query("SELECT i.lado, COUNT(*) FROM duelos d JOIN itens i ON i.id = IF(d.vencedor = 'a', d.item_a, d.item_b)
            WHERE d.status = 'encerrado' AND d.vencedor IN ('a', 'b') AND d.encerrado_em > NOW() - INTERVAL 30 DAY GROUP BY i.lado")->fetchAll(PDO::FETCH_KEY_PAIR);
    return ['gladiadores' => $gladiadores, 'potes' => $potes];
}

/** Tempo que falta até $quando: "18h 42min", "12 min", "encerrado". */
function duelo_falta(string $quando): string
{
    $s = strtotime($quando) - time();
    if ($s <= 0) {
        return 'encerrado';
    }
    $h = intdiv($s, 3600);
    $m = intdiv($s % 3600, 60);
    return $h ? "{$h}h " . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . 'min' : max(1, $m) . ' min';
}

/**
 * Avisa um duelista: notificação do app (push) e e-mail (só no desafio recebido e quando a pessoa não desligou
 * os avisos por e-mail). Roda depois da resposta ir para o navegador; falhar não afeta o duelo.
 */
function duelo_avisar(int $usuarioId, string $lado, string $titulo, string $texto, int $dueloId, bool $email = false): void
{
    register_shutdown_function(function () use ($usuarioId, $lado, $titulo, $texto, $dueloId, $email) {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }
        ignore_user_abort(true);
        try {
            require_once __DIR__ . '/push.php';
            push_avisar('duelo', $titulo, $texto, duelo_link($dueloId), null, $usuarioId);
        } catch (Throwable $e) {
            // push indisponível (migration 025) — segue para o e-mail
        }
        if (!$email || !is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
            return;
        }
        try {
            $st = db()->prepare("SELECT nome, email, avisos_email FROM usuarios WHERE id = ? AND status = 'ativo'");
            $st->execute([$usuarioId]);
            $u = $st->fetch();
            if (!$u || !$u['avisos_email'] || str_ends_with(strtolower($u['email']), '.local')) {
                return;
            }
            require_once __DIR__ . '/mailer.php';
            $S = side($lado);
            $link = duelo_link($dueloId, true);
            $corpo = '<p style="margin:0 0 12px;">Olá, ' . e(explode(' ', nome_proprio($u['nome']))[0]) . '!</p>'
                . '<p style="margin:0 0 20px;font-size:18px;"><b>' . e($titulo) . '</b></p>'
                . '<p style="margin:0 0 24px;">' . e($texto) . '</p>' . email_botao($S, $link, 'Ir para o duelo ⚔️');
            enviar_email($u['email'], nome_proprio($u['nome']), $titulo, email_moldura($S, '⚔️', 'Tretódromo', $corpo), "$titulo\n\n$texto\n\n$link");
        } catch (Throwable $e) {
            logar('erro', 'email', 'duelo_email_falha', 'Aviso de duelo: ' . $e->getMessage(), ['duelo' => $dueloId]);
        }
    });
}
