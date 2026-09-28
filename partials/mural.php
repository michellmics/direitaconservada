<section class="section" id="mural">
  <div class="section-head">
    <div>
      <span class="tag">Mural conservador</span>
      <h2>Direto do pote</h2>
    </div>
    <div class="tabs" role="tablist">
      <button class="tab active" data-sort="recentes">Recentes</button>
      <button class="tab" data-sort="top">Mais azeitonadas</button>
    </div>
  </div>

  <form class="composer" id="composer">
    <div class="composer-avatar" id="composer-avatar">?</div>
    <div class="composer-body">
      <textarea id="composer-text" maxlength="400" placeholder="Compartilhe uma ideia, opinião, notícia ou vídeo… (só conservados podem publicar)"></textarea>
      <div class="composer-video" id="composer-video" hidden></div>
      <div class="composer-foot">
        <span><span id="composer-count">0/180</span> · cole um link do YouTube ou TikTok para anexar vídeo</span>
        <button class="btn btn-gold btn-sm" type="submit">Publicar</button>
      </div>
    </div>
  </form>

  <div class="feed" id="feed"></div>
  <button class="btn btn-ghost btn-more" id="feed-more">Carregar mais</button>
</section>
