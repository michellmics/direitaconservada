<?php
// Enquetes: leitura, votação e administração (tabelas da migration 002).
require_once __DIR__ . '/db.php';

const ENQUETE_MIN_OPCOES = 2;
const ENQUETE_MAX_OPCOES = 6;

// Encerra a enquete ativa cujo prazo (termina_em) já passou
function enquete_expirar(): void
{
    db()->exec("UPDATE enquetes SET status = 'encerrada', encerrada_em = termina_em
                WHERE status = 'ativa' AND termina_em IS NOT NULL AND termina_em <= NOW()");
}

function enquete_buscar(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM enquetes WHERE id = ?');
    $st->execute([$id]);
    $e = $st->fetch();
    return $e ? enquete_completar($e) : null;
}

// A enquete ativa (se houver) — opcionalmente só se aparecer no pote informado
function enquete_ativa(?string $lado = null): ?array
{
    enquete_expirar();
    $e = db()->query("SELECT * FROM enquetes WHERE status = 'ativa' LIMIT 1")->fetch();
    if (!$e || ($lado !== null && $e['lado'] !== null && $e['lado'] !== $lado)) {
        return null;
    }
    return enquete_completar($e);
}

// Anexa opções e contagem de votos (total e por pote)
function enquete_completar(array $e): array
{
    $st = db()->prepare('SELECT id, texto FROM enquete_opcoes WHERE enquete_id = ? ORDER BY ordem, id');
    $st->execute([$e['id']]);
    $opcoes = [];
    foreach ($st as $o) {
        $opcoes[$o['id']] = $o + ['votos' => 0, 'por_lado' => []];
    }
    $st = db()->prepare('SELECT opcao_id, lado, COUNT(*) AS n FROM enquete_votos WHERE enquete_id = ? GROUP BY opcao_id, lado');
    $st->execute([$e['id']]);
    $total = 0;
    $porLado = [];
    foreach ($st as $v) {
        if (!isset($opcoes[$v['opcao_id']])) {
            continue;
        }
        $n = (int) $v['n'];
        $opcoes[$v['opcao_id']]['votos'] += $n;
        $opcoes[$v['opcao_id']]['por_lado'][$v['lado']] = $n;
        $porLado[$v['lado']] = ($porLado[$v['lado']] ?? 0) + $n;
        $total += $n;
    }
    $e['opcoes'] = array_values($opcoes);
    $e['total_votos'] = $total;
    $e['votos_por_lado'] = $porLado;
    return $e;
}

// Só quem está logado vota: um voto por pessoa (vale em qualquer aparelho)
function votante_de(int $usuarioId): string
{
    return hash('sha256', 'u|' . $usuarioId);
}

function enquete_voto_de(int $enqueteId, ?int $usuarioId): ?int
{
    if ($usuarioId === null) {
        return null;
    }
    $st = db()->prepare('SELECT opcao_id FROM enquete_votos WHERE enquete_id = ? AND votante = ?');
    $st->execute([$enqueteId, votante_de($usuarioId)]);
    $v = $st->fetchColumn();
    return $v === false ? null : (int) $v;
}

/** Registra o voto. Retorna null se deu certo ou a mensagem de erro. */
function enquete_votar(int $enqueteId, int $opcaoId, string $lado, int $usuarioId): ?string
{
    $votante = votante_de($usuarioId);
    $e = enquete_ativa();
    if (!$e || (int) $e['id'] !== $enqueteId) {
        return 'Esta enquete não está mais aberta.';
    }
    if ($e['lado'] !== null && $e['lado'] !== $lado) {
        return 'Esta enquete é de outro pote.';
    }
    if (!in_array($opcaoId, array_map('intval', array_column($e['opcoes'], 'id')), true)) {
        return 'Opção inválida.';
    }
    try {
        db()->prepare('INSERT INTO enquete_votos (enquete_id, opcao_id, lado, usuario_id, votante) VALUES (?, ?, ?, ?, ?)')
            ->execute([$enqueteId, $opcaoId, $lado, $usuarioId, $votante]);
    } catch (PDOException $ex) {
        if ($ex->errorInfo[1] === 1062) { // chave duplicada
            return 'Você já votou nesta enquete.';
        }
        throw $ex;
    }
    return null;
}

// Pode mostrar o resultado para quem está vendo?
function enquete_mostra_resultado(array $e, bool $jaVotou): bool
{
    return $e['status'] === 'encerrada'
        || $e['resultado'] === 'sempre'
        || ($e['resultado'] === 'apos_votar' && $jaVotou);
}

// Dados públicos para o JS (sem contagem quando o resultado ainda está oculto)
function enquete_para_js(array $e, ?int $meuVoto): array
{
    $mostra = enquete_mostra_resultado($e, $meuVoto !== null);
    return [
        'id'        => (int) $e['id'],
        'pergunta'  => $e['pergunta'],
        'descricao' => $e['descricao'],
        'duelo'     => $e['lado'] === null,
        'resultado' => $e['resultado'],
        'termina'   => $e['termina_em'],
        'meuVoto'   => $meuVoto,
        'mostra'    => $mostra,
        'total'     => $mostra ? $e['total_votos'] : null,
        'porLado'   => $mostra ? $e['votos_por_lado'] : null,
        'opcoes'    => array_map(fn($o) => [
            'id'      => (int) $o['id'],
            'texto'   => $o['texto'],
            'votos'   => $mostra ? $o['votos'] : null,
            'porLado' => $mostra ? $o['por_lado'] : null,
        ], $e['opcoes']),
    ];
}

// ---------- administração ----------

function enquetes_listar(): array
{
    enquete_expirar();
    $rows = db()->query("SELECT * FROM enquetes ORDER BY FIELD(status, 'ativa', 'rascunho', 'encerrada'), criado_em DESC")->fetchAll();
    return array_map('enquete_completar', $rows);
}

/** Cria a enquete. $publicar = true encerra a ativa atual e publica esta. Retorna o id. */
function enquete_criar(array $d, bool $publicar): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($publicar) {
            enquete_encerrar_ativa();
        }
        $pdo->prepare('INSERT INTO enquetes (pergunta, descricao, lado, resultado, termina_em, status, publicada_em)
                       VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $d['pergunta'], $d['descricao'] ?: null, $d['lado'] ?: null, $d['resultado'],
                $d['termina_em'] ?: null, $publicar ? 'ativa' : 'rascunho', $publicar ? date('Y-m-d H:i:s') : null,
            ]);
        $id = (int) $pdo->lastInsertId();
        $st = $pdo->prepare('INSERT INTO enquete_opcoes (enquete_id, texto, ordem) VALUES (?, ?, ?)');
        foreach (array_values($d['opcoes']) as $i => $texto) {
            $st->execute([$id, $texto, $i]);
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

function enquete_encerrar_ativa(): void
{
    db()->exec("UPDATE enquetes SET status = 'encerrada', encerrada_em = NOW() WHERE status = 'ativa'");
}

// Publica (ou reabre) uma enquete: a que estiver ativa é encerrada antes
function enquete_publicar(int $id): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        enquete_encerrar_ativa();
        $pdo->prepare("UPDATE enquetes SET status = 'ativa', encerrada_em = NULL,
                         publicada_em = COALESCE(publicada_em, NOW()),
                         termina_em = IF(termina_em <= NOW(), NULL, termina_em)
                       WHERE id = ?")->execute([$id]);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

function enquete_encerrar(int $id): void
{
    db()->prepare("UPDATE enquetes SET status = 'encerrada', encerrada_em = NOW() WHERE id = ? AND status = 'ativa'")->execute([$id]);
}

function enquete_excluir(int $id): void
{
    db()->prepare('DELETE FROM enquetes WHERE id = ?')->execute([$id]);
}

/** Valida o formulário do painel. Retorna [dados, erros]. */
function enquete_validar(array $in): array
{
    $erros = [];
    $pergunta = trim((string) ($in['pergunta'] ?? ''));
    $descricao = trim((string) ($in['descricao'] ?? ''));
    $opcoes = array_values(array_filter(array_map(fn($o) => trim((string) $o), (array) ($in['opcoes'] ?? [])), 'strlen'));
    $lado = (string) ($in['lado'] ?? '');
    $resultado = (string) ($in['resultado'] ?? 'apos_votar');
    $termina = trim((string) ($in['termina_em'] ?? ''));

    if (!mb_check_encoding(implode('', [$pergunta, $descricao, ...$opcoes]), 'UTF-8')) {
        return [[], ['O texto tem caracteres inválidos. Digite de novo (sem colar de outro programa).']];
    }
    if ($pergunta === '' || mb_strlen($pergunta) > 200) {
        $erros[] = 'Escreva a pergunta (até 200 caracteres).';
    }
    if (mb_strlen($descricao) > 500) {
        $erros[] = 'A descrição pode ter até 500 caracteres.';
    }
    if (count($opcoes) < ENQUETE_MIN_OPCOES || count($opcoes) > ENQUETE_MAX_OPCOES) {
        $erros[] = 'Coloque de ' . ENQUETE_MIN_OPCOES . ' a ' . ENQUETE_MAX_OPCOES . ' opções.';
    }
    foreach ($opcoes as $o) {
        if (mb_strlen($o) > 120) {
            $erros[] = 'Cada opção pode ter até 120 caracteres.';
            break;
        }
    }
    if (count(array_unique(array_map('mb_strtolower', $opcoes))) !== count($opcoes)) {
        $erros[] = 'Há opções repetidas.';
    }
    if ($lado !== '' && !isset(SIDES[$lado])) {
        $erros[] = 'Pote inválido.';
    }
    if (!in_array($resultado, ['sempre', 'apos_votar', 'apos_encerrar'], true)) {
        $erros[] = 'Opção de resultado inválida.';
    }
    $terminaSql = null;
    if ($termina !== '') {
        $ts = strtotime($termina);
        if (!$ts || $ts <= time()) {
            $erros[] = 'O encerramento automático precisa ser no futuro.';
        } else {
            $terminaSql = date('Y-m-d H:i:s', $ts);
        }
    }
    return [[
        'pergunta' => $pergunta, 'descricao' => $descricao, 'opcoes' => $opcoes,
        'lado' => $lado, 'resultado' => $resultado, 'termina_em' => $terminaSql,
    ], $erros];
}
