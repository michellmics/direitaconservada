  <?php require_once __DIR__ . '/frases.php'; ?>
  <footer class="footer">
    <?php if ($fraseRodape = frase('rodape', $S['slug'])): ?><p class="rodape-piada"><?= e($fraseRodape) ?></p><?php endif; ?>
    <button type="button" class="pwa-instalar" data-instalar-app hidden>📲 Instalar o app</button>
    <button type="button" class="push-botao" data-push hidden>🔔 Receber avisos</button>
    <p><b><?= e($S['name']) ?></b> · <a href="./">Trocar de pote</a> · © <?= date('Y') ?> <?= e(SITE_NAME) ?></p>
  </footer>

  <div class="toast" id="toast" hidden></div>
  <script src="<?= asset('assets/js/visitas.js') ?>" data-lado="<?= e($S['slug']) ?>" defer></script>
  <script src="<?= asset('assets/js/push.js') ?>" data-lado="<?= e($S['slug']) ?>" data-conta="<?= (int) ($U['id'] ?? 0) ?>" defer></script>

  <?php if ($comCompra): // só as páginas de pote e perfil usam o app.js ?>
  <?php
  // o que o JS precisa saber sobre os dois lados (textos, tipos, desenhos)
  $sidesForJs = [];
  foreach (SIDES as $slug => $sd) {
      $sidesForJs[$slug] = array_intersect_key($sd, array_flip([
          'slug', 'name', 'name_a', 'name_b', 'other', 'emoji', 'item', 'items', 'Item', 'since',
          'cert_title', 'cert_since', 'members', 'types', 'scales', 'shapes', 'defs', 'theme', 'jar', 'tempero', 'mapa', 'partidos',
      ]));
  }
  // a conta de quem está vendo (tudo do banco): itens nos dois potes (inclui os pendentes, que só ela vê),
  // pagamentos aguardando conferência, avisos do que o painel resolveu e as curtidas que deu
  $conta = ['meus' => array_fill_keys(array_keys(SIDES), []), 'pedidos' => ['pendentes' => [], 'avisos' => [], 'titular' => null], 'curtidas' => [], 'presentes' => []];
  if ($U) {
      try {
          require_once __DIR__ . '/pedidos.php';
          $conta = ['meus' => banco_meus_itens((int) $U['id']), 'pedidos' => pedidos_da_conta((int) $U['id']), 'curtidas' => banco_minhas_curtidas((int) $U['id']),
                    'presentes' => banco_meus_presentes((int) $U['id'])]; // presentes que deu e ninguém resgatou
      } catch (Throwable $ex) {
          // banco fora do ar: segue como visitante
      }
  }
  ?>
  <script>
    window.DC = <?= json_encode([
        'side'     => $S['slug'],
        'sides'    => $sidesForJs,
        'items'    => $items,
        'capacity' => JAR_CAPACITY,
        'logado'   => $U !== null,
        'email'    => $U['email'] ?? null,
        'meus'     => $conta['meus'],
        'pedidos'  => $conta['pedidos'],
        'curtidas' => $conta['curtidas'],
        'presentes' => $conta['presentes'],
        'csrf'     => $U ? csrf_publico() : null, // para o "Sair" do perfil (sair.php)
        'pagina'   => destino_seguro($paginaAtual),
        'loginUrl' => url('entrar', ['lado' => $S['slug'], 'r' => destino_seguro($paginaAtual . '#enquete')]),
        'loginPartidos' => url('entrar', ['lado' => $S['slug'], 'r' => destino_seguro($paginaAtual . '#partidos')]),
        // links já cifrados (a chave não vai para o navegador); os de compras deste navegador vêm de /api/link
        'links'    => links_para_js($S['slug'], $items, $extraJs['comentarios']['feitos'] ?? [], ($extraJs ?? []) + ['meus' => $conta['meus'], 'presentes' => $conta['presentes']]),
    ] + ($extraJs ?? []), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  </script>
  <script src="<?= asset('assets/js/app.js') ?>"></script>
  <?php endif; ?>
</body>
</html>
