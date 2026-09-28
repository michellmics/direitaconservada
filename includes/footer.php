  <footer class="footer">
    <p><b><?= e(SITE_NAME) ?></b> · Pote aberto desde 2026 · Protótipo visual — nenhum pagamento é real.</p>
  </footer>

  <div class="toast" id="toast" hidden></div>

  <script>
    window.DC = <?= json_encode([
        'olives' => $olives,
        'types'  => OLIVE_TYPES,
        'capacity' => JAR_CAPACITY,
        'phrases'=> mock_phrases(),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  </script>
  <script src="assets/js/app.js"></script>
</body>
</html>
