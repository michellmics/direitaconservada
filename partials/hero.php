<section class="hero" id="pote">
  <div class="hero-text">
    <span class="tag"><?= e($S['tag']) ?></span>
    <h1><?= $S['h1'] ?></h1>
    <p><?= $S['lead'] ?></p>
    <div class="stats">
      <div><strong id="stat-total"><?= num($totais['total']) ?></strong><span><?= e($S['items']) ?> no pote</span></div>
      <div><strong id="stat-hoje"><?= num($hoje) ?></strong><span>entraram hoje</span></div>
      <div><strong><?= e((string) array_key_first($ranking)) ?></strong><span><?= e($S['stat_uf']) ?></span></div>
    </div>
    <div class="hero-cta">
      <?php // cadastro é grátis e já libera o mural; a azeitona/pimenta fica para o pote e o Tretódromo ?>
      <?php if ($U): ?>
        <a class="btn btn-gold" href="#mural">Publicar no mural</a>
      <?php else: ?>
        <a class="btn btn-gold" href="<?= e(url('entrar', ['lado' => $S['slug'], 'r' => $S['slug']])) ?>">Cadastre-se grátis</a>
      <?php endif; ?>
      <?php // no celular sem o app, "Instalar o app" toma o lugar de "Ver o mural" (ver assets/js/pwa.js) 
      ?>
      <a class="btn btn-ghost" href="#mural" data-sem-instalar>Ver o mural</a>
      <button type="button" class="btn btn-ghost" data-instalar-app="celular" hidden>📲 Instalar o app</button>
    </div>
  </div>

  <div class="jar-wrap">
    <?php $jarItems = null;
    require __DIR__ . '/jar.php'; ?>
    <div class="olive-tip" id="olive-tip" hidden></div>
    <p class="jar-capacity">
      <span class="jar-meter"><i id="jar-meter" style="width: <?= max(0.5, $totais['total'] / JAR_CAPACITY * 100) ?>%"></i></span>
    </p>
    <?php // convite pro WhatsApp (frases convite_botao / convite_whats, sorteadas; editáveis em /cozinha/frases).
    // O link da inicial (onde a pessoa escolhe o pote) vai sozinho na última linha: puxa o banner na prévia da conversa.
    require_once dirname(__DIR__) . '/includes/frases.php';
    $convite = (frase('convite_whats', $S['slug']) ?? $S['emoji'] . ' Bora pra treta? Entra no pote:')
      . "\n\n" . url_absoluta(''); ?>
    <a class="btn btn-whats" href="https://wa.me/?text=<?= rawurlencode($convite) ?>" target="_blank" rel="noopener">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.4.8 3.2.6.5-.1 1.5-.6 1.8-1.2.2-.6.2-1.1.1-1.2l-.5-.1Z"/></svg>
      <?= e(frase('convite_botao', $S['slug']) ?? 'Chamar alguém pra treta') ?>
    </a>
  </div>
</section>