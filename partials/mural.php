<section class="section" id="mural">
  <div class="section-head">
    <div>
      <span class="tag"><?= e($S['mural_tag']) ?></span>
      <h2>Direto do pote</h2>
      <p class="section-sub">Os dois lados podem comentar: quem tem <?= e(side($S['other'])['item']) ?> <?= side($S['other'])['emoji'] ?> também comenta aqui.</p>
      <?php if ($fraseMural = frase('mural', $S['slug'])): ?><p class="piada">💬 <?= e($fraseMural) ?></p><?php endif; ?>
    </div>
    <div class="tabs" role="tablist">
      <button class="tab active" data-sort="recentes">Recentes</button>
      <button class="tab" data-sort="top"><?= e($S['sort_top']) ?></button>
      <button class="tab" data-sort="debate">Mais debatidas</button>
    </div>
  </div>

  <form class="composer" id="composer">
    <div class="composer-avatar" id="composer-avatar">?</div>
    <div class="composer-body">
      <textarea id="composer-text" maxlength="400" placeholder="Compartilhe uma ideia, opinião, notícia ou vídeo… (só <?= e($S['members']) ?> podem publicar)"></textarea>
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
