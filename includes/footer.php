  <footer class="footer">
    <p><b><?= e($S['name']) ?></b> · <a href="./">Trocar de pote</a> · Protótipo visual — nenhum pagamento é real.</p>
  </footer>

  <div class="toast" id="toast" hidden></div>

  <?php
  // o que o JS precisa saber sobre os dois lados (textos, tipos, desenhos)
  $sidesForJs = [];
  foreach (SIDES as $slug => $sd) {
      $sidesForJs[$slug] = array_intersect_key($sd, array_flip([
          'slug', 'name', 'name_a', 'name_b', 'other', 'emoji', 'item', 'items', 'Item', 'since',
          'cert_title', 'cert_since', 'members', 'types', 'scales', 'shapes', 'defs', 'theme',
      ]));
  }
  ?>
  <script>
    window.DC = <?= json_encode([
        'side'     => $S['slug'],
        'sides'    => $sidesForJs,
        'items'    => $items,
        'comments' => $comments,
        'capacity' => JAR_CAPACITY,
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  </script>
  <script src="assets/js/app.js"></script>
</body>
</html>
