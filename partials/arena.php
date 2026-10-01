<?php
// Moldura das páginas do Tretódromo (tretodromo.php e duelo.php): <head> com SEO, topo próprio da arena e o fim da página.
// Visual neutro (fundo da página inicial): cada duelista usa as cores do seu pote.
require_once dirname(__DIR__) . '/includes/auth.php';

function arena_inicio(string $titulo, string $descricao, string $canonica, array $jsonLd = [], string $voltar = '', bool $indexar = true): void
{
    $U = current_user();
    $pendentes = 0;
    $lado = 'direita';
    if ($U) {
        try {
            $pendentes = duelos_pendentes((int) $U['id']);
            $lado = duelo_meus_itens((int) $U['id'])[0]['lado'] ?? 'direita';
        } catch (Throwable $e) {
        }
    }
    $aqui = rota_atual() . (rota_atual() === 'duelo' && isset($_GET['n']) ? '?n=' . (int) $_GET['n'] : '');
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo) ?></title>
  <meta name="description" content="<?= e($descricao) ?>">
  <?= seo_tags($indexar ? $canonica : null, $jsonLd) ?>
  <?= og_tags($titulo, $descricao, 'og-inicio.png', $canonica) ?>
  <meta name="theme-color" content="#120e0a">
  <?= pwa_tags() ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,800;9..144,900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body class="choose-page arena-page">
  <header class="arena-topo">
    <a class="arena-marca" href="tretodromo"><?= arena_icone('espadas') ?><span>Tretódromo</span></a>
    <?php if ($voltar): ?><a class="arena-voltar" href="tretodromo">‹ Arena</a><?php endif; ?>
    <nav class="arena-nav">
      <a href="direita">🫒 Direita</a>
      <a href="esquerda">🌶️ Esquerda</a>
    </nav>
    <?php if ($U): ?>
      <a class="arena-conta" href="<?= e(url('perfil', ['lado' => $lado, 'meu' => 1])) ?>" title="Meu perfil">
        <?= e(explode(' ', nome_proprio($U['nome']))[0]) ?><?php if ($pendentes): ?> <b class="arena-selo" title="Duelos esperando você"><?= $pendentes ?></b><?php endif; ?>
      </a>
    <?php else: ?>
      <a class="arena-conta" href="<?= e(url('entrar', ['lado' => 'direita', 'r' => destino_seguro($aqui)])) ?>">Entrar</a>
    <?php endif; ?>
  </header>
    <?php
}

function arena_fim(): void
{
    ?>
  <footer class="footer">
    <p><a href="./">Pote Político</a> · <a href="tretodromo">Tretódromo</a> · © <?= date('Y') ?> <?= e(SITE_NAME) ?></p>
  <?php require __DIR__ . '/../partials/legal.php'; ?>
  <div class="toast" id="toast" hidden></div>
  <script src="<?= asset('assets/js/duelo.js') ?>" defer></script>
  <script src="<?= asset('assets/js/visitas.js') ?>" defer></script>
  <script src="<?= asset('assets/js/push.js') ?>" data-conta="<?= (int) (current_user()['id'] ?? 0) ?>" defer></script>
</body>
</html>
    <?php
}

/** Ícones da arena (SVG em traço, herdam a cor do texto). */
function arena_icone(string $nome, int $tam = 22): string
{
    $p = match ($nome) {
        'espadas' => '<polyline points="14.5 17.5 3 6 3 3 6 3 17.5 14.5"/><line x1="13" y1="19" x2="19" y2="13"/><line x1="16" y1="16" x2="20" y2="20"/><line x1="19" y1="21" x2="21" y2="19"/><polyline points="14.5 6.5 18 3 21 3 21 6 17.5 9.5"/><line x1="5" y1="14" x2="9" y2="18"/><line x1="7" y1="17" x2="4" y2="20"/><line x1="3" y1="19" x2="5" y2="21"/>',
        'trofeu'  => '<path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>',
        'relogio' => '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/>',
        default   => '',
    };
    return '<svg class="arena-ic" width="' . $tam . '" height="' . $tam . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

/** Desenho do item do duelista (azeitona recheada ou pimenta). */
function arena_item(string $lado, int $tam = 40): string
{
    return $lado === 'esquerda'
        ? '<svg width="' . $tam . '" height="' . $tam . '" viewBox="0 0 40 40" aria-hidden="true"><path d="M14 11 C 9 19, 12 31, 27 34 C 31 35, 33 31, 29.5 28.5 C 22.5 24, 22 17, 20.5 11 Z" fill="#e0402f"/><path d="M14 11 C 14.5 6.5, 19.5 5.5, 20.5 11" stroke="#4f9a3c" stroke-width="3" fill="none" stroke-linecap="round"/><path d="M16 15 C 14.5 20, 16 25, 20 28" stroke="#ff8a70" stroke-width="2" fill="none" stroke-linecap="round"/></svg>'
        : '<svg width="' . $tam . '" height="' . $tam . '" viewBox="0 0 40 40" aria-hidden="true"><ellipse cx="20" cy="21" rx="12" ry="15" fill="#7d8c2f"/><ellipse cx="15.5" cy="14" rx="2.6" ry="4.5" fill="#a9b85a"/><circle cx="20" cy="22" r="4.2" fill="#c0392b"/></svg>';
}

/** Card de duelo para as listas da arena. */
function arena_card(array $d): string
{
    $total = max(1, $d['votos_a'] + $d['votos_b']);
    $pa = (int) round($d['votos_a'] / $total * 100);
    $sem = $d['votos_a'] + $d['votos_b'] === 0;
    [$selo, $cls] = match ($d['status']) {
        'aguardando' => ['Aguardando aceite · ' . duelo_falta($d['prazo_em']), 'is-espera'],
        'andamento'  => ['Ao vivo · rodada ' . $d['rodada'], 'is-vivo'],
        'votacao'    => ['Votação · ' . duelo_falta($d['prazo_em']), 'is-vivo'],
        'encerrado'  => [$d['vencedor'] === 'empate' ? 'Empate' : 'Venceu: ' . nome_proprio($d[$d['vencedor'] . '_nome']) . ($d['wo'] ? ' (W.O.)' : ''), 'is-fim'],
        default      => [ucfirst($d['status']), 'is-fim'],
    };
    $nome = fn(string $l) => e(nome_proprio($d[$l . '_nome']));
    return '<a class="arena-card lado-a-' . e($d['a_lado']) . '" href="' . e(duelo_link((int) $d['id'])) . '">'
        . '<span class="arena-card-selo ' . $cls . '">' . e($selo) . '</span>'
        . '<b class="arena-card-tema">' . e($d['tema']) . '</b>'
        . '<span class="arena-card-nomes"><span class="c-' . e($d['a_lado']) . '">' . $nome('a') . ($sem ? '' : ' · ' . $pa . '%') . '</span>'
        . '<span class="c-' . e($d['b_lado']) . '">' . ($sem ? '' : (100 - $pa) . '% · ') . $nome('b') . '</span></span>'
        . '<span class="arena-barra"><i class="bg-' . e($d['a_lado']) . '" style="width:' . ($sem ? 50 : $pa) . '%"></i><i class="bg-' . e($d['b_lado']) . '"></i></span>'
        . '</a>';
}
