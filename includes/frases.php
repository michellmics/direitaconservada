<?php
// Frases engraçadas espalhadas pelo site (tabela frases, migration 006; edite em /cozinha/frases).
// Sorteadas a cada visita. Sem banco, o site segue normal, só sem as frases.
require_once __DIR__ . '/db.php';

// lugar => onde aparece (usado no painel)
const FRASE_LUGARES = [
    'faixa'   => 'Faixa do topo (não aparece no site no momento)',
    'mapa'    => 'Mapa (não aparece no site no momento)',
    'estado'  => 'Piada do estado (não aparece no site no momento)',
    'mural'   => 'Mural',
    'compra'  => 'Janela de compra',
    'rodape'  => 'Rodapé',
    'entrada' => 'Página de entrada (escolha do pote)',
    'email_oposicao' => 'E-mail: comentário do outro pote (provocação; pote = de quem recebe)',
    'convite_botao'  => 'Convite pra treta: texto do botão embaixo do pote (bem curto)',
    'convite_whats'  => 'Convite pra treta: mensagem do WhatsApp (pote = de quem convida; o link vai no fim)',
];

/**
 * Frases ativas de um pote (as do lado + as de 'ambos'), agrupadas: [lugar => [['texto','uf'], ...]].
 * $lado null = só as de 'ambos' (ex.: página de entrada). Uma consulta por página.
 */
function frases_carregar(?string $lado): array
{
    static $cache = [];
    $chave = $lado ?? '-';
    if (isset($cache[$chave])) {
        return $cache[$chave];
    }
    $out = [];
    try {
        $st = db()->prepare("SELECT lugar, uf, texto FROM frases WHERE ativo = 1 AND (lado = 'ambos' OR lado = ?)");
        $st->execute([$lado ?? 'ambos']);
        foreach ($st->fetchAll() as $f) {
            $out[$f['lugar']][] = ['texto' => $f['texto'], 'uf' => $f['uf']];
        }
    } catch (PDOException $e) {
        // banco fora do ar ou migration 006 não rodada
    }
    return $cache[$chave] = $out;
}

/** Uma frase sorteada do lugar (ou null se não houver). */
function frase(string $lugar, ?string $lado): ?string
{
    $lista = frases_carregar($lado)[$lugar] ?? [];
    return $lista ? $lista[array_rand($lista)]['texto'] : null;
}

// ---------- painel ----------

function frases_listar(?string $lugar = null, ?string $lado = null): array
{
    $where = [];
    $args = [];
    if ($lugar) {
        $where[] = 'lugar = ?';
        $args[] = $lugar;
    }
    if ($lado) {
        $where[] = 'lado = ?';
        $args[] = $lado;
    }
    $st = db()->prepare('SELECT * FROM frases' . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY lugar, lado, uf, id DESC');
    $st->execute($args);
    return $st->fetchAll();
}

/** Valida o formulário do painel. Retorna [dados, erros]. */
function frase_validar(array $in): array
{
    $d = [
        'lugar' => (string) ($in['lugar'] ?? ''),
        'lado'  => (string) ($in['lado'] ?? 'ambos'),
        'uf'    => strtoupper(trim((string) ($in['uf'] ?? ''))) ?: null,
        'texto' => trim(preg_replace('/\s+/u', ' ', (string) ($in['texto'] ?? ''))),
        'ativo' => !empty($in['ativo']) ? 1 : 0,
    ];
    $erros = [];
    if (!isset(FRASE_LUGARES[$d['lugar']])) {
        $erros[] = 'Escolha onde a frase aparece.';
    }
    if (!in_array($d['lado'], ['direita', 'esquerda', 'ambos'], true)) {
        $erros[] = 'Escolha o pote.';
    }
    if ($d['lugar'] === 'estado') {
        if (!in_array($d['uf'], UFS, true)) {
            $erros[] = 'Piada de estado precisa de uma UF.';
        }
    } else {
        $d['uf'] = null;
    }
    $n = mb_strlen($d['texto']);
    if ($n < 3 || $n > 300) {
        $erros[] = 'A frase precisa ter entre 3 e 300 caracteres.';
    }
    return [$d, $erros];
}

function frase_salvar(array $d, ?int $id = null): void
{
    if ($id) {
        db()->prepare('UPDATE frases SET lugar = ?, lado = ?, uf = ?, texto = ?, ativo = ? WHERE id = ?')
            ->execute([$d['lugar'], $d['lado'], $d['uf'], $d['texto'], $d['ativo'], $id]);
    } else {
        db()->prepare('INSERT INTO frases (lugar, lado, uf, texto, ativo) VALUES (?, ?, ?, ?, ?)')
            ->execute([$d['lugar'], $d['lado'], $d['uf'], $d['texto'], $d['ativo']]);
    }
}

function frase_alternar(int $id): void
{
    db()->prepare('UPDATE frases SET ativo = 1 - ativo WHERE id = ?')->execute([$id]);
}

function frase_excluir(int $id): void
{
    db()->prepare('DELETE FROM frases WHERE id = ?')->execute([$id]);
}
