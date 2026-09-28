<section class="hero" id="pote">
  <div class="hero-text">
    <span class="tag">Em salmoura desde sempre</span>
    <h1>Valores não têm<br>prazo de <em>validade</em>.</h1>
    <p>Garanta sua azeitona no maior pote conservador do Brasil. Você recebe um certificado dizendo <b>“Conservado desde”</b> a data de hoje, deixa sua frase registrada e participa do mural com ideias, opiniões, notícias e vídeos da direita.</p>
    <div class="stats">
      <div><strong id="stat-total"><?= num(count($olives)) ?></strong><span>azeitonas no pote</span></div>
      <div><strong id="stat-hoje"><?= num($hoje) ?></strong><span>conservadas hoje</span></div>
      <div><strong><?= e((string) array_key_first($ranking)) ?></strong><span>estado mais conservado</span></div>
    </div>
    <div class="hero-cta">
      <button class="btn btn-gold" data-open-buy>Entrar no pote · a partir de <?= money(min_price()) ?>/ano</button>
      <a class="btn btn-ghost" href="#mural">Ver o mural</a>
    </div>
    <p class="hint">Passe o mouse (ou toque) numa azeitona para ver de quem ela é.</p>
  </div>

  <div class="jar-wrap">
    <svg class="jar" id="jar" viewBox="0 0 400 540" role="img" aria-label="Pote de azeitonas">
      <defs>
        <clipPath id="jar-inside">
          <path d="M70 118 Q70 108 80 108 H320 Q330 108 330 118 V140 Q352 150 352 190 V470 Q352 512 310 512 H90 Q48 512 48 470 V190 Q48 150 70 140 Z"/>
        </clipPath>
        <linearGradient id="glass" x1="0" x2="1">
          <stop offset="0" stop-color="#fff" stop-opacity=".28"/>
          <stop offset=".18" stop-color="#fff" stop-opacity=".05"/>
          <stop offset=".8" stop-color="#fff" stop-opacity=".02"/>
          <stop offset="1" stop-color="#fff" stop-opacity=".18"/>
        </linearGradient>
        <linearGradient id="brine" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stop-color="#d9d98a" stop-opacity=".25"/>
          <stop offset="1" stop-color="#8c9a3c" stop-opacity=".45"/>
        </linearGradient>
        <linearGradient id="lid" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stop-color="#e7c54d"/>
          <stop offset=".5" stop-color="#b8901c"/>
          <stop offset="1" stop-color="#8a6a10"/>
        </linearGradient>
        <radialGradient id="olive-verde" cx=".35" cy=".35" r=".8">
          <stop offset="0" stop-color="#b5c25a"/><stop offset=".6" stop-color="#7d8c2f"/><stop offset="1" stop-color="#4f5a18"/>
        </radialGradient>
        <!-- selos pequenos das azeitonas -->
        <clipPath id="selo-clip" clipPathUnits="objectBoundingBox"><circle cx=".5" cy=".5" r=".5"/></clipPath>
        <symbol id="selo-br" viewBox="-10 -10 20 20">
          <clipPath id="selo-br-clip"><circle r="10"/></clipPath>
          <g clip-path="url(#selo-br-clip)">
            <rect x="-10" y="-10" width="20" height="20" fill="#009c3b"/>
            <path d="M0 -7.6 L9.6 0 L0 7.6 L-9.6 0 Z" fill="#ffdf00"/>
            <circle r="4.3" fill="#002776"/>
            <path d="M-4.2 -.6 Q0 -2.4 4.2 .8" stroke="#fff" stroke-width="1" fill="none"/>
          </g>
        </symbol>
        <radialGradient id="olive-grande" cx=".35" cy=".35" r=".8">
          <stop offset="0" stop-color="#d2de6e"/><stop offset=".55" stop-color="#8fa532"/><stop offset="1" stop-color="#55661a"/>
        </radialGradient>
        <radialGradient id="olive-preta" cx=".35" cy=".35" r=".8">
          <stop offset="0" stop-color="#6a5a6e"/><stop offset=".6" stop-color="#2e2530"/><stop offset="1" stop-color="#140f15"/>
        </radialGradient>
      </defs>

      <!-- tampa -->
      <rect x="62" y="30" width="276" height="62" rx="12" fill="url(#lid)"/>
      <g stroke="#7a5c0c" stroke-width="3" opacity=".5">
        <?php for ($x = 90; $x <= 300; $x += 30): ?><line x1="<?= $x ?>" y1="36" x2="<?= $x ?>" y2="86"/><?php endfor; ?>
      </g>
      <rect x="62" y="30" width="276" height="10" rx="5" fill="#fff" opacity=".25"/>

      <!-- vidro -->
      <path d="M70 100 H330 V140 Q360 150 360 190 V470 Q360 520 310 520 H90 Q40 520 40 470 V190 Q40 150 70 140 Z" fill="#0f140a" fill-opacity=".35" stroke="#e9f0d0" stroke-opacity=".5" stroke-width="4"/>

      <g clip-path="url(#jar-inside)">
        <rect x="40" y="150" width="320" height="380" fill="url(#brine)"/>
        <g id="olives"></g>
        <g class="bubbles">
          <circle cx="110" cy="480" r="4"/><circle cx="250" cy="500" r="3"/><circle cx="300" cy="470" r="5"/><circle cx="170" cy="505" r="3"/>
        </g>
      </g>

      <!-- rótulo -->
      <g class="label">
        <rect x="92" y="268" width="216" height="130" rx="14" fill="#f4ecd8" stroke="#c9a227" stroke-width="4"/>
        <rect x="100" y="276" width="200" height="114" rx="10" fill="none" stroke="#3d4a1f" stroke-width="1.5" stroke-dasharray="4 3"/>
        <text x="200" y="304" text-anchor="middle" class="label-small">★ DESDE SEMPRE ★</text>
        <text x="200" y="336" text-anchor="middle" class="label-big">DIREITA</text>
        <text x="200" y="364" text-anchor="middle" class="label-big">CONSERVADA</text>
        <text x="200" y="382" text-anchor="middle" class="label-cap">CAP. <?= num(JAR_CAPACITY) ?> AZEITONAS</text>
      </g>

      <path d="M60 180 Q56 330 64 470" stroke="#fff" stroke-opacity=".35" stroke-width="10" stroke-linecap="round" fill="none" pointer-events="none"/>
      <path d="M70 100 H330 V140 Q360 150 360 190 V470 Q360 520 310 520 H90 Q40 520 40 470 V190 Q40 150 70 140 Z" fill="url(#glass)" pointer-events="none" opacity=".6"/>
    </svg>
    <div class="olive-tip" id="olive-tip" hidden></div>
    <p class="jar-capacity">
      <span class="jar-meter"><i id="jar-meter" style="width: <?= max(0.5, count($olives) / JAR_CAPACITY * 100) ?>%"></i></span>
      <span><b id="jar-count"><?= num(count($olives)) ?></b> de <?= num(JAR_CAPACITY) ?> lugares ocupados</span>
    </p>
  </div>
</section>
