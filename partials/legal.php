<?php
// Rodapé: links de Privacidade, Termos e "Preferências de cookies" + aviso de cookies (LGPD).
// Vai dentro do <footer> de todas as páginas (includes/footer.php, index.php e partials/arena.php).
// A escolha fica no cookie dc_consentimento (assets/js/cookies.js); anúncio personalizado só com permissão.
?>
    <p class="rodape-legal">
      <?php $ladoLegal = isset($S) ? "?lado=" . $S["slug"] : ""; // a página abre com as cores do pote atual ?>
      <a href="privacidade<?= $ladoLegal ?>">Política de privacidade</a> · <a href="termos-de-uso<?= $ladoLegal ?>">Termos de uso</a> ·
      <button type="button" class="rodape-link" id="cookie-preferencias">Preferências de cookies</button>
    </p>
  </footer>

  <section class="cookie-aviso" id="cookie-aviso" role="dialog" aria-labelledby="cookie-titulo" hidden>
    <div class="cookie-dentro">
      <div class="cookie-texto">
        <h2 id="cookie-titulo">Este site usa cookies 🍪</h2>
        <p>Usamos cookies necessários para o site funcionar (login e segurança), um contador de visitas próprio e anônimo e, com a sua permissão, cookies de publicidade personalizada (Google AdSense) para manter o <?= e(SITE_NAME) ?> gratuito. Você pode mudar sua escolha quando quiser em "Preferências de cookies", no rodapé. <a href="privacidade">Saiba mais</a>.</p>
        <div class="cookie-opcoes" id="cookie-opcoes" hidden>
          <label><input type="checkbox" checked disabled> <span><b>Necessários</b> · login, segurança e contagem anônima de visitas (sempre ativos)</span></label>
          <label><input type="checkbox" id="cookie-publicidade"> <span><b>Publicidade personalizada</b> · anúncios do Google de acordo com seus interesses</span></label>
        </div>
      </div>
      <div class="cookie-botoes">
        <button type="button" class="btn btn-gold btn-sm" id="cookie-aceitar">Aceitar todos</button>
        <button type="button" class="btn btn-ghost btn-sm" id="cookie-necessarios">Só os necessários</button>
        <button type="button" class="btn btn-ghost btn-sm" id="cookie-personalizar">Personalizar</button>
        <button type="button" class="btn btn-gold btn-sm" id="cookie-salvar" hidden>Salvar escolha</button>
      </div>
    </div>
  </section>
  <script src="<?= asset('assets/js/cookies.js') ?>" defer></script>
