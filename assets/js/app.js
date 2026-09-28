(() => {
  'use strict';

  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const SVG = 'http://www.w3.org/2000/svg';

  // ---------- armazenamento local (simula o banco enquanto ele não existe) ----------
  const store = {
    get(key, fallback) {
      try { return JSON.parse(localStorage.getItem('dc_' + key)) ?? fallback; } catch { return fallback; }
    },
    set(key, value) {
      try { localStorage.setItem('dc_' + key, JSON.stringify(value)); } catch { /* sem storage */ }
    },
  };

  // Lado atual (pote) e o outro. "olives" = itens do pote atual (azeitonas ou pimentas).
  const SIDE = DC.side;
  const S = DC.sides[SIDE];
  const OTHER = DC.sides[S.other];

  const mine = store.get(`${SIDE}_mine`, []);      // itens comprados neste navegador, neste pote
  const myPosts = store.get(`${SIDE}_posts`, []);  // posts do mural publicados neste navegador
  const liked = new Set(store.get('likes', []));
  const myComments = store.get('comments', []);  // comentários feitos neste navegador (qualquer pote)

  // Nome próprio: "JOÃO DA SILVA" / "joão da silva" → "João da Silva" (igual a nome_proprio() no PHP)
  const PARTICULAS = new Set(['da', 'de', 'do', 'das', 'dos', 'e', 'di', 'du']);
  function nomeProprio(s) {
    return String(s || '').trim().replace(/\s+/g, ' ').toLocaleLowerCase('pt-BR').split(' ')
      .map((w, i) => (i > 0 && PARTICULAS.has(w))
        ? w
        : w.replace(/(^|[-'’])(\p{L})/gu, (_, sep, ch) => sep + ch.toLocaleUpperCase('pt-BR')))
      .join(' ');
  }

  const olives = [...DC.items, ...mine];
  olives.forEach((o) => { o.nome = nomeProprio(o.nome); });
  [...DC.comments, ...myComments].forEach((c) => { c.autor.nome = nomeProprio(c.autor.nome); });
  const byId = (id) => olives.find((o) => o.id === id);

  // Quem está comentando: o item comprado mais recente, em qualquer um dos potes
  function me() {
    const all = Object.keys(DC.sides).flatMap((s) => store.get(`${s}_mine`, []).map((o) => ({ ...o, side: s, nome: nomeProprio(o.nome) })));
    return all.sort((a, b) => (b.criado || 0) - (a.criado || 0))[0] || null;
  }

  // ---------- utilidades ----------
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const fmtDate = (iso) => iso.split('-').reverse().join('/');
  const todayIso = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  };
  const numero = (id) => '#' + String(id).padStart(4, '0');
  const initials = (n) => n.split(/\s+/).map((p) => p[0]).join('').slice(0, 2).toUpperCase();
  const rand = (seed) => { const x = Math.sin(seed * 9301 + 49297) * 233280; return x - Math.floor(x); };
  const profileUrl = (side, id) => `perfil.php?lado=${side}&id=${id}`;
  // link que vai para o compartilhamento: o perfil da pessoa
  const oliveUrl = (o) => new URL(profileUrl(o.side || SIDE, o.id), location.href).href;
  const typeLabel = (tipo, side = SIDE) => DC.sides[side].types[tipo]?.label || tipo;
  const money = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const scaleOf = (o) => S.scales[o.tipo] || 1;

  // desenho do item como <svg> avulso (certificado, etc.)
  const itemSvg = (tipo, side = SIDE, scale = 1) =>
    `<svg viewBox="-24 -17 48 34" aria-hidden="true"><g transform="scale(${scale})">${DC.sides[side].shapes[tipo] || ''}</g></svg>`;

  // Foto da pessoa; sem foto (ou se falhar ao carregar) mostra as iniciais
  const initialsClass = (o, cls) => `${cls} avatar-${o.side || SIDE}-${o.tipo}`;
  const initialsAvatar = (o, cls) => `<div class="${initialsClass(o, cls)}">${esc(initials(o.nome))}</div>`;
  const avatarHtml = (o, cls) => o.foto
    ? `<img class="${cls} avatar-photo" src="${esc(o.foto)}" alt="Foto de ${esc(o.nome)}" data-fallback-class="${esc(initialsClass(o, cls))}" data-initials="${esc(initials(o.nome))}">`
    : initialsAvatar(o, cls);

  // Selo: 'br' (bandeira do Brasil), um emoji ou uma imagem enviada (data:...)
  const isImageSelo = (s) => /^(data:image\/|https?:\/\/)/.test(s);
  function seloHtml(selo) {
    if (selo === 'br') return '<svg viewBox="-10 -10 20 20"><use href="#selo-br" x="-10" y="-10" width="20" height="20"/></svg>';
    if (isImageSelo(selo)) return `<img src="${esc(selo)}" alt="">`;
    return `<span>${esc(selo)}</span>`;
  }
  // selo pequeno dentro do pote (fica fora da rotação da azeitona)
  function seloSvg(selo) {
    let inner;
    if (selo === 'br') inner = '<use href="#selo-br" x="-5" y="-5" width="10" height="10"/>';
    else if (isImageSelo(selo)) inner = `<image href="${esc(selo)}" x="-5" y="-5" width="10" height="10" preserveAspectRatio="xMidYMid slice" clip-path="url(#selo-clip)"/>`;
    else inner = `<text y=".6" font-size="7" text-anchor="middle" dominant-baseline="central">${esc(selo)}</text>`;
    return `<g class="olive-selo" transform="translate(-10 -6)"><circle r="6"/>${inner}</g>`;
  }
  // avatar com o selo no canto
  const avatarWithSelo = (o, cls) => o.selo
    ? `<span class="avatar-wrap">${avatarHtml(o, cls)}<span class="selo-badge">${seloHtml(o.selo)}</span></span>`
    : avatarHtml(o, cls);

  document.addEventListener('error', (e) => {
    const img = e.target;
    if (img.tagName !== 'IMG' || !img.dataset.fallbackClass) return;
    img.outerHTML = `<div class="${img.dataset.fallbackClass}">${img.dataset.initials}</div>`;
  }, true);

  function toast(msg) {
    const t = $('#toast');
    t.textContent = msg;
    t.hidden = false;
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => { t.hidden = true; }, 2600);
  }

  // ---------- pote ----------
  // arrumação dos itens no pote (vem de includes/sides.php; mesma conta do PHP em jar_position)
  const L = S.jar;
  const MAX_IN_JAR = L.perRow * L.rows;

  function olivePosition(i) {
    const row = Math.floor(i / L.perRow);
    const col = i % L.perRow;
    return {
      x: L.x0 + col * L.dx + (row % 2 ? L.dx / 2 : 0) + (rand(i) - 0.5) * 6,
      y: L.y0 - row * L.dy + (rand(i + 7) - 0.5) * 4,
      r: L.rot + (rand(i + 3) - 0.5) * L.rotJitter,
    };
  }

  // quando o pote lota, mostra os mais recentes (quem acabou de comprar sempre aparece)
  const jarOffset = () => Math.max(0, olives.length - MAX_IN_JAR);
  const slotOf = (o) => olives.indexOf(o) - jarOffset();

  function oliveNode(o, i, animate) {
    const { x, y } = olivePosition(i);
    const pos = document.createElementNS(SVG, 'g');
    pos.setAttribute('transform', `translate(${x.toFixed(1)} ${y.toFixed(1)}) scale(${scaleOf(o) * L.itemScale})`);
    const g = document.createElementNS(SVG, 'g');
    g.setAttribute('class', 'olive' + (animate ? ' drop' : '') + (mine.some((m) => m.id === o.id) ? ' is-mine' : ''));
    g.dataset.id = o.id;
    g.innerHTML = oliveInner(o);
    pos.appendChild(g);
    return pos;
  }

  function oliveInner(o) {
    return `<g transform="rotate(${olivePosition(slotOf(o)).r.toFixed(0)})">${S.shapes[o.tipo] || ''}</g>` +
      (o.selo ? seloSvg(o.selo) : '') +
      // selo de play (fora da rotação, para o triângulo ficar sempre de pé)
      (videoOf(o) ? '<g class="olive-play" transform="translate(9 -7)"><circle r="5.5"/><path d="M-1.8 -2.8 L3 0 L-1.8 2.8 Z"/></g>' : '');
  }

  // vídeo mais recente que a pessoa postou (ou o da própria azeitona)
  const videoOf = (o) => myPosts.find((p) => p.oliveId === o.id && p.video)?.video || o.video || null;

  function refreshOlive(o) {
    const el = $(`.olive[data-id="${o.id}"]`);
    if (el) el.innerHTML = oliveInner(o);
  }

  function renderJar() {
    const layer = $('#jar-items');
    layer.innerHTML = '';
    const inJar = olives.slice(jarOffset()).map((o, i) => [o, i]);
    // as maiores são desenhadas por último para ficarem por cima das vizinhas
    const ordered = [...inJar.filter(([o]) => scaleOf(o) <= 1), ...inJar.filter(([o]) => scaleOf(o) > 1)];
    ordered.forEach(([o, i]) => layer.appendChild(oliveNode(o, i, false)));
  }

  function dropOlive(o) {
    if (olives.length > MAX_IN_JAR) {
      // pote cheio: todos andam uma casa e o novo cai no topo
      renderJar();
      $(`.olive[data-id="${o.id}"]`)?.classList.add('drop');
      return;
    }
    $('#jar-items').appendChild(oliveNode(o, slotOf(o), true));
  }

  function setupJarTooltip() {
    const jar = $('#jar');
    const tip = $('#olive-tip');
    const wrap = $('.jar-wrap');
    const isTouch = matchMedia('(hover: none)').matches;
    let active = null;      // azeitona do balão aberto
    let playing = false;    // vídeo tocando dentro do balão
    let reachedTip = false; // o mouse já entrou no balão desde que ele abriu
    let hideTimer = null;
    let switchTimer = null;

    // posiciona acima da azeitona; se não couber na tela, abre para baixo
    const place = () => {
      if (!active) return;
      const box = active.getBoundingClientRect();
      const base = wrap.getBoundingClientRect();
      const half = tip.offsetWidth / 2 + 8;
      tip.style.left = Math.min(Math.max(box.left + box.width / 2 - base.left, half), base.width - half) + 'px';
      const below = box.top - tip.offsetHeight - 14 < 70;
      tip.classList.toggle('below', below);
      tip.style.top = (below ? box.bottom : box.top) - base.top + 'px';
    };

    const show = (el) => {
      const o = byId(Number(el.dataset.id));
      if (!o) return;
      clearTimers();
      playing = false;
      reachedTip = false;
      active?.classList.remove('is-active');
      active = el;
      el.classList.add('is-active');
      const v = videoOf(o);
      tip.innerHTML =
        `<div class="tip-head">${avatarWithSelo(o, 'tip-photo')}<div><b>${esc(o.nome)}</b><small>${numero(o.id)} · ${esc(typeLabel(o.tipo))} · ${esc(o.cidade)}/${esc(o.uf)}<br>desde ${fmtDate(o.desde)}</small></div></div>` +
        `<q>${esc(o.frase)}</q>` +
        (DC.profileId === o.id ? '' : `<a class="tip-profile" href="${profileUrl(SIDE, o.id)}">Ver perfil →</a>`) +
        (v ? `<div class="tip-video">${videoCoverHtml(v)}</div>` : '');
      tip.classList.toggle('has-video', !!v);
      tip.classList.remove('is-playing');
      tip.hidden = false;
      place();
    };

    function clearTimers() { clearTimeout(hideTimer); clearTimeout(switchTimer); }

    // fecha o balão (e para o vídeo, se estiver tocando)
    const close = () => {
      clearTimers();
      tip.innerHTML = '';
      tip.hidden = true;
      playing = false;
      active?.classList.remove('is-active');
      active = null;
    };

    // "A caminho do balão" = balão com vídeo aberto e o mouse ainda não entrou nele.
    // Só nesse caso damos tempo extra, para o mouse poder passar por outras azeitonas.
    const onTheWay = () => active && !reachedTip && tip.classList.contains('has-video');

    const hideSoon = () => {
      clearTimers();
      hideTimer = setTimeout(close, onTheWay() ? 500 : 200);
    };

    // a azeitona está no trajeto entre a azeitona aberta e o balão?
    const betweenOliveAndTip = (el) => {
      const t = tip.getBoundingClientRect();
      const a = active.getBoundingClientRect();
      const b = el.getBoundingClientRect();
      const cx = b.left + b.width / 2;
      const cy = b.top + b.height / 2;
      const ay = a.top + a.height / 2;
      if (cx < t.left || cx > t.right) return false;
      return tip.classList.contains('below') ? cy > ay && cy < t.top + 20 : cy < ay && cy > t.bottom - 20;
    };

    const hoverOlive = (el) => {
      clearTimers();
      if (el === active) return;
      if (onTheWay() && betweenOliveAndTip(el)) switchTimer = setTimeout(() => show(el), 450);
      else show(el); // indo para outro lado (ou já saiu do balão): troca na hora
    };

    jar.addEventListener('mouseover', (e) => {
      if (isTouch) return;
      const el = e.target.closest('.olive');
      el ? hoverOlive(el) : active && hideSoon();
    });
    jar.addEventListener('mouseleave', () => { if (!isTouch && active) hideSoon(); });

    // dentro do balão (ou da "ponte" invisível até ele): mantém aberto
    const inTip = () => { reachedTip = true; clearTimers(); };
    tip.addEventListener('mouseenter', inTip);
    tip.addEventListener('mousemove', inTip);
    // saiu do balão: fecha, mesmo com vídeo tocando
    tip.addEventListener('mouseleave', () => { if (!isTouch) hideSoon(); });

    tip.addEventListener('click', (e) => {
      e.stopPropagation(); // o botão de play é substituído; sem isso o clique contaria como "fora"
      if (e.target.closest('[data-tip-close]')) { close(); return; }
      if (!e.target.closest('[data-play]')) return;
      const v = videoOf(byId(Number(active.dataset.id)));
      playing = true;
      tip.classList.add('is-playing');
      tip.classList.toggle('vertical', v.vertical);
      $('.tip-video', tip).innerHTML =
        `<div class="video-frame${v.vertical ? ' vertical' : ''}"><iframe src="${videoEmbedSrc(v)}" title="Vídeo do ${providerName(v)}" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe></div>`;
      tip.insertAdjacentHTML('afterbegin', '<button class="tip-close" data-tip-close aria-label="Fechar vídeo">×</button>');
      place();
    });

    // clicar fora ou apertar Esc fecha o balão (no celular é o único jeito)
    document.addEventListener('click', (e) => { if (active && !jar.contains(e.target)) close(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && active) close(); });
    window.addEventListener('resize', place);

    jar.addEventListener('click', (e) => {
      const el = e.target.closest('.olive');
      if (!el) { if (isTouch) close(); return; }
      // no toque: primeiro toque mostra o balão, segundo abre o certificado
      if (isTouch && active !== el) { show(el); return; }
      if (playing) close();
      openCert(byId(Number(el.dataset.id)));
    });
  }

  function updateStats() {
    const set = (sel, v) => { const el = $(sel); if (el) el.textContent = v.toLocaleString('pt-BR'); };
    set('#stat-total', olives.length);
    set('#stat-hoje', olives.filter((o) => o.desde === todayIso()).length);
    set('#jar-count', olives.length);
    const meter = $('#jar-meter');
    if (meter) meter.style.width = Math.max(0.5, (olives.length / DC.capacity) * 100) + '%';
  }

  // ---------- modais ----------
  function openModal(id) {
    const m = $(id);
    m.hidden = false;
    document.body.style.overflow = 'hidden';
    return m;
  }
  function closeModals() {
    $$('.modal').forEach((m) => { m.hidden = true; });
    document.body.style.overflow = '';
    if (location.hash.startsWith(`#${S.item}-`)) history.replaceState(null, '', location.pathname + location.search);
  }
  document.addEventListener('click', (e) => { if (e.target.closest('[data-close]')) closeModals(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModals(); });

  // ---------- certificado ----------
  function certHtml(o) {
    return `<div class="cert">
      <small>${esc(S.cert_title)}</small>
      <div class="cert-photo">${avatarWithSelo(o, 'cert-avatar')}${itemSvg(o.tipo, SIDE, Math.min(scaleOf(o), 1.2))}</div>
      <div class="cert-name">${esc(o.nome)}</div>
      <div class="cert-since">${esc(S.cert_since)}<b>${fmtDate(o.desde)}</b></div>
      <q>${esc(o.frase)}</q>
      <div class="cert-foot"><span>${esc(S.Item)} ${numero(o.id)} · ${esc(typeLabel(o.tipo))}</span><span>${esc(o.cidade)}/${esc(o.uf)}</span></div>
    </div>`;
  }

  function shareText(o) {
    return `${S.emoji} Sou ${S.name} desde ${fmtDate(o.desde)}! ${S.Item} ${numero(o.id)}.\n"${o.frase}"\nGaranta a sua:`;
  }

  function shareButtons(o) {
    const text = shareText(o);
    const url = oliveUrl(o);
    const wa = `https://wa.me/?text=${encodeURIComponent(text + ' ' + url)}`;
    const x = `https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}&url=${encodeURIComponent(url)}`;
    const fb = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
    return `
      <a class="btn btn-gold" href="${wa}" target="_blank" rel="noopener">WhatsApp</a>
      <a class="btn btn-ghost" href="${x}" target="_blank" rel="noopener">Postar no X</a>
      <a class="btn btn-ghost" href="${fb}" target="_blank" rel="noopener">Facebook</a>
      <button class="btn btn-ghost" data-copy="${esc(url)}">Copiar link</button>
      <button class="btn btn-ghost wide" data-download="${o.id}">Baixar imagem (Stories / Status)</button>
      ${DC.profileId === o.id ? '' : `<a class="btn btn-ghost wide" href="${profileUrl(SIDE, o.id)}">Ver perfil</a>`}
      ${navigator.share ? `<button class="btn btn-link wide" data-native-share="${o.id}">Mais opções…</button>` : ''}`;
  }

  function openCert(o) {
    if (!o) return;
    $('#cert-view').innerHTML = certHtml(o);
    $('#share-view').innerHTML = shareButtons(o);
    openModal('#cert-modal');
    history.replaceState(null, '', `${location.pathname}${location.search}#${S.item}-${o.id}`);
  }

  document.addEventListener('click', async (e) => {
    const copy = e.target.closest('[data-copy]');
    const dl = e.target.closest('[data-download]');
    const ns = e.target.closest('[data-native-share]');
    if (copy) {
      try { await navigator.clipboard.writeText(copy.dataset.copy); toast('Link copiado!'); } catch { toast('Não foi possível copiar'); }
    }
    if (dl) downloadCert(byId(Number(dl.dataset.download)));
    if (ns) {
      const o = byId(Number(ns.dataset.nativeShare));
      navigator.share({ title: S.name, text: shareText(o), url: oliveUrl(o) }).catch(() => {});
    }
  });

  // Gera o certificado como PNG 1080x1920 (formato Stories/Status)
  async function downloadCert(o) {
    const photo = o.foto ? await loadImage(o.foto).catch(() => null) : null;
    const seloImg = o.selo && isImageSelo(o.selo) ? await loadImage(o.selo).catch(() => null) : null;
    const itemImg = await loadImage(itemDataUrl(o.tipo)).catch(() => null);
    const T = S.theme;
    const W = 1080, H = 1920;
    const c = document.createElement('canvas');
    c.width = W; c.height = H;
    const g = c.getContext('2d');

    g.fillStyle = T.bg; g.fillRect(0, 0, W, H);
    const glow = g.createRadialGradient(W / 2, 300, 50, W / 2, 300, 900);
    glow.addColorStop(0, T.glow); glow.addColorStop(1, 'rgba(0,0,0,0)');
    g.fillStyle = glow; g.fillRect(0, 0, W, H);

    const cx = 90, cy = 330, cw = W - 180, ch = 1180;
    roundRect(g, cx, cy, cw, ch, 36); g.fillStyle = '#f4ecd8'; g.fill();
    roundRect(g, cx + 22, cy + 22, cw - 44, ch - 44, 24); g.strokeStyle = T.gold; g.lineWidth = 8; g.stroke();
    roundRect(g, cx + 40, cy + 40, cw - 80, ch - 80, 18); g.strokeStyle = T.dark; g.lineWidth = 2; g.setLineDash([10, 8]); g.stroke(); g.setLineDash([]);

    g.textAlign = 'center';
    g.fillStyle = '#f4ecd8'; g.font = '800 72px Fraunces, Georgia, serif';
    g.fillText(S.name_a, W / 2, 170);
    g.fillStyle = T['gold-2']; g.fillText(S.name_b, W / 2, 250);

    g.fillStyle = T.dark; g.font = '700 30px Inter, sans-serif';
    // letras espaçadas: "C E R T I F I C A D O   D E   …"
    g.fillText(S.cert_title.split(' ').map((w) => w.split('').join(' ')).join('   '), W / 2, cy + 130);

    // foto (se houver) com o item como selo; sem foto, item grande no centro
    const px = W / 2, py = cy + 290;
    if (photo) {
      g.save();
      g.beginPath(); g.arc(px, py, 130, 0, Math.PI * 2); g.clip();
      g.drawImage(photo, px - 130, py - 130, 260, 260);
      g.restore();
      g.beginPath(); g.arc(px, py, 130, 0, Math.PI * 2); g.strokeStyle = T.gold; g.lineWidth = 10; g.stroke();
      drawItem(g, itemImg, px + 125, py + 100, 150);
      if (o.selo) drawSelo(g, o.selo, seloImg, px - 105, py + 95, 42);
    } else {
      drawItem(g, itemImg, px, py, 330 * Math.min(scaleOf(o), 1.2));
      if (o.selo) drawSelo(g, o.selo, seloImg, px - 150, py + 75, 42);
    }

    g.fillStyle = T.ink; g.font = '800 84px Fraunces, Georgia, serif';
    fitText(g, o.nome, W / 2, cy + 520, cw - 140);
    g.font = '600 38px Inter, sans-serif'; g.fillText(S.cert_since, W / 2, cy + 610);
    g.fillStyle = T.dark; g.font = '800 96px Fraunces, Georgia, serif'; g.fillText(fmtDate(o.desde), W / 2, cy + 710);

    g.fillStyle = T.ink; g.font = 'italic 600 46px Fraunces, Georgia, serif';
    wrapText(g, `“${o.frase}”`, W / 2, cy + 820, cw - 180, 60);

    g.strokeStyle = '#b9b69a'; g.setLineDash([8, 8]); g.beginPath(); g.moveTo(cx + 80, cy + ch - 130); g.lineTo(cx + cw - 80, cy + ch - 130); g.stroke(); g.setLineDash([]);
    g.fillStyle = '#6b6a55'; g.font = '600 32px Inter, sans-serif';
    g.textAlign = 'left'; g.fillText(`${S.Item} ${numero(o.id)} · ${typeLabel(o.tipo)}`, cx + 80, cy + ch - 75);
    g.textAlign = 'right'; g.fillText(`${o.cidade}/${o.uf}`, cx + cw - 80, cy + ch - 75);

    g.textAlign = 'center'; g.fillStyle = '#f4ecd8'; g.font = '600 40px Inter, sans-serif';
    g.fillText(`Garanta sua ${S.item} no pote`, W / 2, H - 230);
    g.fillStyle = T['gold-2']; g.font = '800 48px Inter, sans-serif';
    g.fillText(location.host || 'direitaconservada.com.br', W / 2, H - 160);

    const a = document.createElement('a');
    a.download = `${S.slug}-${o.id}.png`;
    a.href = c.toDataURL('image/png');
    a.click();
  }

  // crossOrigin evita "sujar" o canvas com fotos de outro domínio (senão toDataURL falha)
  function loadImage(src) {
    return new Promise((resolve, reject) => {
      const img = new Image();
      img.crossOrigin = 'anonymous';
      img.onload = () => resolve(img);
      img.onerror = reject;
      img.src = src;
    });
  }

  // o mesmo desenho do pote, como imagem SVG autônoma, para desenhar no canvas
  const itemDataUrl = (tipo) => 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="-24 -17 48 34" width="480" height="340"><defs>${S.defs}</defs>${S.shapes[tipo]}</svg>`);

  function drawItem(g, img, x, y, w) {
    if (!img) return;
    const h = w * 34 / 48;
    g.drawImage(img, x - w / 2, y - h / 2, w, h);
  }

  function drawSelo(g, selo, img, x, y, r) {
    if (isImageSelo(selo) && !img) return;
    g.save();
    g.beginPath(); g.arc(x, y, r, 0, Math.PI * 2);
    g.fillStyle = '#f4ecd8'; g.fill();
    g.clip();
    if (selo === 'br') {
      g.fillStyle = '#009c3b'; g.fillRect(x - r, y - r, r * 2, r * 2);
      g.beginPath(); g.moveTo(x, y - r * .76); g.lineTo(x + r * .96, y); g.lineTo(x, y + r * .76); g.lineTo(x - r * .96, y); g.closePath();
      g.fillStyle = '#ffdf00'; g.fill();
      g.beginPath(); g.arc(x, y, r * .43, 0, Math.PI * 2); g.fillStyle = '#002776'; g.fill();
    } else if (img) {
      g.drawImage(img, x - r, y - r, r * 2, r * 2);
    } else {
      g.font = `${Math.round(r * 1.1)}px "Segoe UI Emoji", "Apple Color Emoji", "Noto Color Emoji", sans-serif`;
      g.textAlign = 'center'; g.textBaseline = 'middle';
      g.fillText(selo, x, y + r * .06);
    }
    g.restore();
    g.beginPath(); g.arc(x, y, r, 0, Math.PI * 2); g.strokeStyle = '#c9a227'; g.lineWidth = 6; g.stroke();
    g.textBaseline = 'alphabetic';
  }

  function roundRect(g, x, y, w, h, r) {
    g.beginPath(); g.moveTo(x + r, y); g.arcTo(x + w, y, x + w, y + h, r); g.arcTo(x + w, y + h, x, y + h, r);
    g.arcTo(x, y + h, x, y, r); g.arcTo(x, y, x + w, y, r); g.closePath();
  }
  function fitText(g, text, x, y, max) {
    let size = parseInt(g.font.match(/(\d+)px/)[1], 10);
    while (g.measureText(text).width > max && size > 30) { size -= 4; g.font = g.font.replace(/\d+px/, size + 'px'); }
    g.fillText(text, x, y);
  }
  function wrapText(g, text, x, y, max, lh) {
    let line = '';
    for (const word of text.split(' ')) {
      const test = line ? line + ' ' + word : word;
      if (g.measureText(test).width > max && line) { g.fillText(line, x, y); line = word; y += lh; } else line = test;
    }
    g.fillText(line, x, y);
  }

  // ---------- compra ----------
  const buyModal = $('#buy-modal');
  const buyForm = $('#buy-form');
  let pending = null;

  function goStep(n) { $$('.step-pane', buyModal).forEach((p) => { p.hidden = p.dataset.step !== String(n); }); }

  document.addEventListener('click', (e) => {
    if (!e.target.closest('[data-open-buy]')) return;
    goStep(1);
    openModal('#buy-modal');
    buyForm.elements.nome.focus();
  });
  // ---------- carrinho: várias azeitonas/pimentas na mesma compra ----------
  const cart = []; // itens já adicionados: { tipo, nome, cidade, uf, frase, foto, selo, qtd }
  const qtyInput = $('#qty-input');
  const QTY_MAX = 50;

  const qtyValue = () => Math.min(QTY_MAX, Math.max(1, parseInt(qtyInput.value, 10) || 1));
  const entryPrice = (en) => S.types[en.tipo].price * en.qtd;
  // o formulário conta como item quando a pessoa começou a preencher (ou quando o carrinho está vazio)
  const formStarted = () => !!(buyForm.elements.nome.value.trim() || buyForm.elements.frase.value.trim());
  const formCounts = () => formStarted() || !cart.length;

  function readEntry() {
    const f = new FormData(buyForm);
    return {
      tipo: f.get('tipo'),
      nome: nomeProprio(f.get('nome')),
      cidade: f.get('cidade').trim(),
      uf: f.get('uf'),
      frase: f.get('frase').trim(),
      foto: photoData,
      selo: f.get('selo') === 'custom' ? seloData : f.get('selo') || null,
      qtd: qtyValue(),
    };
  }

  function totalNow() {
    const current = formCounts() ? S.types[buyForm.elements.tipo.value].price * qtyValue() : 0;
    return cart.reduce((sum, en) => sum + entryPrice(en), 0) + current;
  }

  function renderCart() {
    $('#cart').hidden = !cart.length;
    $('#cart-list').innerHTML = cart.map((en, i) => `<li>
      ${itemSvg(en.tipo)}
      <span class="cart-desc"><b>${en.qtd}× ${esc(typeLabel(en.tipo))}</b> · ${esc(en.nome)}</span>
      <span class="cart-price">${money(entryPrice(en))}</span>
      <button type="button" class="cart-remove" data-remove="${i}" aria-label="Remover">×</button>
    </li>`).join('');
    updateTotal();
  }

  function updateTotal() {
    $$('[data-price-total]', buyModal).forEach((el) => { el.textContent = money(totalNow()); });
  }

  // limpa só o que é do item (mantém cidade, UF e tipo para agilizar o próximo)
  function clearItemFields() {
    buyForm.elements.nome.value = '';
    buyForm.elements.frase.value = '';
    qtyInput.value = 1;
    buyForm.querySelector('input[name="selo"][value=""]').checked = true;
    resetPhoto();
    resetSelo();
  }

  function resetCart() {
    cart.length = 0;
    buyForm.reset();
    clearItemFields();
    renderCart();
  }

  buyForm.addEventListener('input', updateTotal);
  // corrige o nome ao sair do campo
  buyForm.elements.nome.addEventListener('blur', (e) => { e.target.value = nomeProprio(e.target.value); });
  buyForm.addEventListener('change', updateTotal);

  buyModal.addEventListener('click', (e) => {
    const step = e.target.closest('[data-qty]');
    if (step) { qtyInput.value = Math.min(QTY_MAX, Math.max(1, qtyValue() + Number(step.dataset.qty))); updateTotal(); }
    const rm = e.target.closest('[data-remove]');
    if (rm) { cart.splice(Number(rm.dataset.remove), 1); renderCart(); }
  });
  qtyInput.addEventListener('blur', () => { qtyInput.value = qtyValue(); updateTotal(); });

  $('#add-more').addEventListener('click', () => {
    if (!buyForm.reportValidity()) return;
    cart.push(readEntry());
    clearItemFields();
    renderCart();
    toast(`Adicionada ao pedido. Agora preencha a próxima ${S.item}.`);
    buyModal.querySelector('.modal-card').scrollTo({ top: 0, behavior: 'smooth' });
    buyForm.elements.nome.focus({ preventScroll: true });
  });

  $$('.chip', buyForm).forEach((c) => c.addEventListener('click', () => { buyForm.elements.frase.value = c.dataset.phrase; }));
  $('[data-back]', buyModal).addEventListener('click', () => goStep(1));

  // foto: recorta em quadrado e reduz para 256px antes de guardar
  let photoData = null;
  const photoPreview = $('#photo-preview');
  const photoPlaceholder = photoPreview.innerHTML;

  $('#photo-input').addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) { toast('Escolha um arquivo de imagem'); return; }
    try {
      photoData = await resizePhoto(file, 256);
      photoPreview.innerHTML = `<img src="${photoData}" alt="">`;
    } catch {
      toast('Não foi possível ler essa imagem');
    }
  });

  // recorta no centro em quadrado; PNG mantém transparência (logos), JPEG ganha fundo branco
  function resizePhoto(file, size, type = 'image/jpeg') {
    return new Promise((resolve, reject) => {
      const img = new Image();
      const url = URL.createObjectURL(file);
      img.onload = () => {
        const side = Math.min(img.width, img.height);
        const c = document.createElement('canvas');
        c.width = c.height = size;
        const g = c.getContext('2d');
        if (type === 'image/jpeg') { g.fillStyle = '#fff'; g.fillRect(0, 0, size, size); }
        g.drawImage(img, (img.width - side) / 2, (img.height - side) / 2, side, side, 0, 0, size, size);
        URL.revokeObjectURL(url);
        resolve(c.toDataURL(type, 0.85));
      };
      img.onerror = () => { URL.revokeObjectURL(url); reject(); };
      img.src = url;
    });
  }

  function resetPhoto() {
    photoData = null;
    photoPreview.innerHTML = photoPlaceholder;
  }

  // selo: símbolo pronto ou imagem enviada (ex.: bandeira do partido)
  let seloData = null;
  const seloUpload = $('#selo-upload-preview');
  const seloCustom = $('#selo-custom');

  $('#selo-input').addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) { toast('Escolha um arquivo de imagem'); return; }
    try {
      seloData = await resizePhoto(file, 96, 'image/png');
      seloUpload.innerHTML = `<img src="${seloData}" alt="">`;
      seloCustom.checked = true;
      syncSeloUpload();
    } catch {
      toast('Não foi possível ler essa imagem');
    }
  });

  function syncSeloUpload() { seloUpload.classList.toggle('is-checked', seloCustom.checked); }
  $$('input[name="selo"]', buyForm).forEach((r) => r.addEventListener('change', syncSeloUpload));
  // imagem já enviada: clicar de novo no "+" só seleciona (a troca continua possível pelo seletor)
  seloUpload.addEventListener('click', (e) => {
    if (seloData && !seloCustom.checked) { e.preventDefault(); seloCustom.checked = true; syncSeloUpload(); }
  });

  function resetSelo() {
    seloData = null;
    seloUpload.textContent = '+';
    syncSeloUpload();
  }

  // Continuar: o que está no formulário entra no pedido (se foi preenchido) e vai para o Pix
  buyForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const entries = [...cart];
    if (formCounts()) {
      if (!buyForm.reportValidity()) return;
      entries.push(readEntry());
    }
    pending = entries;
    const count = entries.reduce((n, en) => n + en.qtd, 0);
    $('#pix-summary').textContent = `${count} ${count > 1 ? S.items : S.item}`;
    $$('[data-price-total]', buyModal).forEach((el) => { el.textContent = money(entries.reduce((s, en) => s + entryPrice(en), 0)); });
    renderFakeQr();
    goStep(2);
  });
  $('[data-back]', buyModal).addEventListener('click', updateTotal);

  $('#copy-pix').addEventListener('click', () => toast('Código Pix copiado (de mentirinha)'));

  $('#simulate-pay').addEventListener('click', () => {
    let nextId = Math.max(...olives.map((x) => x.id)) + 1;
    const bought = [];
    pending.forEach((en) => {
      const { qtd, ...data } = en;
      for (let k = 0; k < qtd; k++) {
        // cópias do mesmo item não repetem a frase no mural
        bought.push({ ...data, id: nextId++, side: SIDE, desde: todayIso(), likes: 0, criado: Date.now() + bought.length, semPost: k > 0 });
      }
    });
    bought.forEach((o, k) => {
      olives.push(o);
      mine.push(o);
      setTimeout(() => dropOlive(o), k * 160); // caem uma após a outra
    });
    store.set(`${SIDE}_mine`, mine);
    updateStats();
    renderFeed();
    updateComposer();
    updateMyProfileLink();

    const showCert = (o) => {
      $('#cert-slot').innerHTML = certHtml(o);
      $('#share-buttons').innerHTML = shareButtons(o);
      $$('#bought-list li').forEach((li) => li.classList.toggle('active', Number(li.dataset.id) === o.id));
    };
    const many = bought.length > 1;
    $('#bought-summary').hidden = !many;
    $('#bought-list').hidden = !many;
    $('#bought-summary').textContent = `${bought.length} ${S.items} entraram no pote. Veja o certificado de cada uma:`;
    $('#bought-list').innerHTML = bought.map((o) => `<li data-id="${o.id}">
      <button type="button">${itemSvg(o.tipo)}<span>${numero(o.id)} · ${esc(typeLabel(o.tipo))} · ${esc(o.nome)}</span></button>
    </li>`).join('');
    $$('#bought-list li').forEach((li) => li.addEventListener('click', () => showCert(byId(Number(li.dataset.id)))));
    showCert(bought[0]);
    goStep(3);
    resetCart();
  });

  function renderFakeQr() {
    // QR decorativo: 3 "olhos" nos cantos + ruído aleatório
    const N = 21;
    const corners = [[0, 0], [0, N - 7], [N - 7, 0]];
    const cell = (r, c) => {
      for (const [fr, fc] of corners) {
        const y = r - fr, x = c - fc;
        if (y >= -1 && x >= -1 && y <= 7 && x <= 7) {
          if (y < 0 || x < 0 || y > 6 || x > 6) return false;
          return y === 0 || y === 6 || x === 0 || x === 6 || (y >= 2 && y <= 4 && x >= 2 && x <= 4);
        }
      }
      return Math.random() > 0.5;
    };
    let html = '';
    for (let r = 0; r < N; r++) for (let c = 0; c < N; c++) html += `<i class="${cell(r, c) ? '' : 'o'}"></i>`;
    $('#qr').innerHTML = html;
  }

  // ---------- vídeos (YouTube / TikTok) ----------
  const TEXT_MAX = 180;
  const VIDEO_RE = [
    { provider: 'youtube', vertical: true, re: /(?:https?:\/\/)?(?:www\.|m\.)?youtube\.com\/shorts\/([\w-]{11})\S*/i },
    { provider: 'youtube', vertical: false, re: /(?:https?:\/\/)?(?:www\.|m\.)?youtube\.com\/(?:watch\?\S*?v=|live\/|embed\/)([\w-]{11})\S*/i },
    { provider: 'youtube', vertical: false, re: /(?:https?:\/\/)?youtu\.be\/([\w-]{11})\S*/i },
    { provider: 'tiktok', vertical: true, re: /(?:https?:\/\/)?(?:www\.|m\.)?tiktok\.com\/@[\w.-]+\/video\/(\d{8,25})\S*/i },
  ];
  const TIKTOK_SHORT_RE = /(?:https?:\/\/)?(?:vm|vt)\.tiktok\.com\/\S+|tiktok\.com\/t\/\S+/i;

  // devolve { video, text } com o link removido do texto
  function extractVideo(raw) {
    for (const { provider, vertical, re } of VIDEO_RE) {
      const m = raw.match(re);
      if (m) return { video: { provider, id: m[1], vertical }, text: raw.replace(m[0], '').replace(/\s{2,}/g, ' ').trim() };
    }
    return { video: null, text: raw.trim() };
  }

  const providerName = (v) => (v.provider === 'tiktok' ? 'TikTok' : v.vertical ? 'YouTube Shorts' : 'YouTube');

  function videoCoverHtml(v) {
    const thumb = v.provider === 'youtube'
      ? `<img src="https://i.ytimg.com/vi/${v.id}/hqdefault.jpg" alt="" loading="lazy">`
      : '<span class="tiktok-cover" aria-hidden="true">♪</span>';
    return `<button class="video-cover ${v.provider}" data-play aria-label="Assistir vídeo do ${providerName(v)}">
      ${thumb}<span class="play-btn"></span><span class="video-badge">${providerName(v)}</span>
    </button>`;
  }

  function videoEmbedSrc(v) {
    return v.provider === 'tiktok'
      ? `https://www.tiktok.com/player/v1/${v.id}?autoplay=1&rel=0`
      : `https://www.youtube-nocookie.com/embed/${v.id}?autoplay=1&rel=0&playsinline=1`;
  }

  function stopVideo(post) {
    const v = JSON.parse(post.dataset.video);
    $('.post-video', post).innerHTML = videoCoverHtml(v);
    post.classList.remove('is-playing');
  }

  function playVideo(post) {
    $$('.post.is-playing').forEach(stopVideo); // um vídeo por vez
    const v = JSON.parse(post.dataset.video);
    post.classList.add('is-playing');
    $('.post-video', post).innerHTML =
      `<div class="video-frame${v.vertical ? ' vertical' : ''}">
        <iframe src="${videoEmbedSrc(v)}" title="Vídeo do ${providerName(v)}" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
      </div>
      <button class="video-close" data-stop>Fechar vídeo</button>`;
    // espera o quadro expandir antes de rolar até ele
    requestAnimationFrame(() => post.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
  }

  // ---------- mural ----------
  const PAGE = 12;
  let sort = 'recentes';
  let shown = PAGE;

  function allPosts() {
    const fromOlives = olives.filter((o) => !o.semPost).map((o) => ({ id: `${SIDE}-o${o.id}`, oliveId: o.id, text: o.frase, date: o.desde, likes: o.likes, video: o.video || null }));
    const extra = myPosts.map((p) => ({ ...p, isNew: true }));
    let list = [...extra, ...fromOlives];
    if (DC.profileId) list = list.filter((p) => p.oliveId === DC.profileId); // página de perfil
    if (sort === 'top') return list.sort((a, b) => likesOf(b) - likesOf(a));
    if (sort === 'debate') return list.sort((a, b) => commentsOf(b.id).length - commentsOf(a.id).length);
    return list.sort((a, b) => (b.date + b.id).localeCompare(a.date + a.id));
  }
  const likesOf = (p) => p.likes + (liked.has(p.id) ? 1 : 0);

  // ---------- comentários (os dois lados podem comentar) ----------
  const openComments = new Set();
  const commentsOf = (postId) => [...DC.comments, ...myComments].filter((c) => c.post === postId);
  const themeVars = (sd) => Object.entries(sd.theme).map(([k, v]) => `--${k}:${v}`).join(';');

  function commentsButton(postId) {
    const list = commentsOf(postId);
    const visitors = list.filter((c) => c.autor.side !== SIDE).length;
    return `💬 ${list.length}` + (visitors ? ` <span class="visitors" title="comentários de quem é do outro pote">· ${OTHER.emoji} ${visitors}</span>` : '');
  }

  function commentHtml(c) {
    const a = c.autor;
    const sd = DC.sides[a.side];
    const visitor = a.side !== SIDE;
    // quem vem do outro pote aparece com as cores do lado dele
    return `<div class="comment${visitor ? ' is-visitor' : ''}"${visitor ? ` style="${themeVars(sd)}"` : ''}>
      ${avatarWithSelo(a, 'comment-avatar')}
      <div class="comment-body">
        <div class="comment-head">${a.id ? `<a class="name-link" href="${profileUrl(a.side, a.id)}">${esc(a.nome)}</a>` : `<b>${esc(a.nome)}</b>`}${visitor ? `<span class="side-tag">${sd.emoji} ${esc(sd.name)}</span>` : ''}</div>
        <p>${esc(c.texto)}</p>
      </div>
    </div>`;
  }

  function commentFormHtml(postId) {
    const eu = me();
    if (!eu) {
      return `<p class="comment-cta">Para comentar, garanta sua ${S.item} ${S.emoji}
        <button class="btn btn-gold btn-sm" type="button" data-open-buy>Garantir</button>
        ou uma ${OTHER.item} ${OTHER.emoji} <a href="pote.php?lado=${OTHER.slug}">no outro pote</a>.</p>`;
    }
    const sd = DC.sides[eu.side];
    return `<form class="comment-form" data-post="${postId}">
      ${avatarWithSelo(eu, 'comment-avatar')}
      <input name="texto" maxlength="200" required autocomplete="off"
        placeholder="Comentar como ${esc(eu.nome.split(' ')[0])} (${sd.emoji} ${esc(sd.name)})…">
      <button class="btn btn-gold btn-sm">Enviar</button>
    </form>`;
  }

  function commentsSectionHtml(postId) {
    const list = commentsOf(postId);
    return `<div class="comment-list">${list.length ? list.map(commentHtml).join('') : '<p class="comment-empty">Ninguém comentou ainda. Os dois lados podem comentar.</p>'}</div>
      ${commentFormHtml(postId)}`;
  }

  function refreshComments(postId) {
    const post = $(`.post[data-post-id="${postId}"]`);
    if (!post) return;
    $('[data-toggle-comments]', post).innerHTML = commentsButton(postId);
    $('.comments', post).innerHTML = commentsSectionHtml(postId);
  }

  function postHtml(p) {
    const o = byId(p.oliveId);
    const video = p.video ? ` data-video="${esc(JSON.stringify(p.video))}"` : '';
    const open = openComments.has(p.id);
    return `<article class="post${p.isNew ? ' is-new' : ''}${p.video ? ' has-video' : ''}" data-post-id="${p.id}"${video}>
      <div class="post-head">
        ${avatarWithSelo(o, 'post-avatar')}
        <div><a class="name-link" href="${profileUrl(SIDE, o.id)}">${esc(o.nome)}</a><small>${esc(o.cidade)}/${esc(o.uf)} · ${esc(S.since)} ${fmtDate(o.desde)}</small></div>
      </div>
      ${p.text ? `<p>${esc(p.text)}</p>` : ''}
      ${p.video ? `<div class="post-video">${videoCoverHtml(p.video)}</div>` : ''}
      <div class="post-actions">
        <button data-like="${p.id}" class="${liked.has(p.id) ? 'liked' : ''}">${S.emoji} ${likesOf(p)}</button>
        <button data-toggle-comments="${p.id}" class="${open ? 'active' : ''}">${commentsButton(p.id)}</button>
        <button data-share-post="${p.id}">Compartilhar</button>
        <button data-view="${o.id}">Certificado</button>
      </div>
      <div class="comments"${open ? '' : ' hidden'}>${open ? commentsSectionHtml(p.id) : ''}</div>
    </article>`;
  }

  function renderFeed() {
    const posts = allPosts();
    $('#feed').innerHTML = posts.slice(0, shown).map(postHtml).join('');
    $('#feed-more').hidden = shown >= posts.length;
  }

  $('#feed').addEventListener('submit', (e) => {
    const form = e.target.closest('.comment-form');
    if (!form) return;
    e.preventDefault();
    const eu = me();
    const texto = form.elements.texto.value.trim();
    if (!eu || !texto) return;
    myComments.push({
      post: form.dataset.post,
      autor: { id: eu.id, side: eu.side, nome: eu.nome, foto: eu.foto, tipo: eu.tipo, selo: eu.selo },
      texto,
      data: todayIso(),
    });
    store.set('comments', myComments);
    refreshComments(form.dataset.post);
    $(`.post[data-post-id="${form.dataset.post}"] .comment-form input`)?.focus();
  });

  $$('.tab').forEach((t) => t.addEventListener('click', () => {
    $$('.tab').forEach((x) => x.classList.toggle('active', x === t));
    sort = t.dataset.sort;
    shown = PAGE;
    renderFeed();
  }));
  $('#feed-more').addEventListener('click', () => { shown += PAGE; renderFeed(); });

  $('#feed').addEventListener('click', (e) => {
    const like = e.target.closest('[data-like]');
    const share = e.target.closest('[data-share-post]');
    const view = e.target.closest('[data-view]');
    const toggle = e.target.closest('[data-toggle-comments]');
    if (toggle) {
      const id = toggle.dataset.toggleComments;
      const box = $('.comments', toggle.closest('.post'));
      const open = !openComments.has(id);
      open ? openComments.add(id) : openComments.delete(id);
      toggle.classList.toggle('active', open);
      box.innerHTML = open ? commentsSectionHtml(id) : '';
      box.hidden = !open;
      if (open) $('input', box)?.focus({ preventScroll: true });
    }
    if (e.target.closest('[data-play]')) playVideo(e.target.closest('.post'));
    if (e.target.closest('[data-stop]')) stopVideo(e.target.closest('.post'));
    if (like) {
      const id = like.dataset.like;
      liked.has(id) ? liked.delete(id) : liked.add(id);
      store.set('likes', [...liked]);
      const p = allPosts().find((x) => x.id === id);
      like.classList.toggle('liked', liked.has(id));
      like.textContent = `${S.emoji} ${likesOf(p)}`;
    }
    if (share) {
      const p = allPosts().find((x) => x.id === share.dataset.sharePost);
      const o = byId(p.oliveId);
      const text = `"${p.text || 'Olha esse vídeo'}" — ${o.nome}, ${S.since} ${fmtDate(o.desde)} ${S.emoji}`;
      if (navigator.share) navigator.share({ title: S.name, text, url: oliveUrl(o) }).catch(() => {});
      else window.open(`https://wa.me/?text=${encodeURIComponent(text + ' ' + oliveUrl(o))}`, '_blank', 'noopener');
    }
    if (view) openCert(byId(Number(view.dataset.view)));
  });

  // composer: só quem tem item deste pote publica (comentar vale para os dois lados)
  const composer = $('#composer');
  const composerText = $('#composer-text');

  function updateComposer() {
    if (!composer) return; // página sem mural para publicar (ex.: perfil)
    const eu = mine[mine.length - 1];
    if (eu) {
      $('#composer-avatar').outerHTML = avatarHtml(eu, 'composer-avatar').replace(/^<(\w+) /, '<$1 id="composer-avatar" ');
      composerText.placeholder = `O que você quer compartilhar, ${eu.nome.split(' ')[0]}?`;
    }
  }

  // o link do vídeo não conta no limite de caracteres
  function updateComposerPreview() {
    const { video, text } = extractVideo(composerText.value);
    const count = $('#composer-count');
    count.textContent = `${text.length}/${TEXT_MAX}`;
    count.classList.toggle('over', text.length > TEXT_MAX);
    const box = $('#composer-video');
    if (video) {
      box.innerHTML = `<span>▶ Vídeo do ${providerName(video)} anexado</span>`;
      box.hidden = false;
    } else if (TIKTOK_SHORT_RE.test(composerText.value)) {
      box.innerHTML = '<span class="warn">Link curto do TikTok não funciona: abra o vídeo e copie o link completo (tiktok.com/@usuario/video/…)</span>';
      box.hidden = false;
    } else {
      box.hidden = true;
    }
  }
  composerText?.addEventListener('input', updateComposerPreview);
  composerText?.addEventListener('focus', () => {
    if (!mine.length) { toast(`Só ${S.members} podem publicar aqui. Garanta sua ${S.item}!`); }
  });
  composer?.addEventListener('submit', (e) => {
    e.preventDefault();
    const eu = mine[mine.length - 1];
    if (!eu) { goStep(1); openModal('#buy-modal'); return; }
    const { video, text } = extractVideo(composerText.value);
    if (!text && !video) return;
    if (text.length > TEXT_MAX) { toast(`Máximo de ${TEXT_MAX} caracteres (sem contar o link)`); return; }
    myPosts.unshift({ id: `${SIDE}-p${Date.now()}`, oliveId: eu.id, text, video, date: todayIso(), likes: 0 });
    store.set(`${SIDE}_posts`, myPosts);
    if (video) refreshOlive(eu); // aparece o selo de play no item
    composerText.value = '';
    updateComposerPreview();
    sort = 'recentes';
    $$('.tab').forEach((x) => x.classList.toggle('active', x.dataset.sort === 'recentes'));
    renderFeed();
    toast('Publicado no mural!');
  });

  // ranking: "mais antigos" abre o certificado
  $$('[data-olive-id]').forEach((li) => li.addEventListener('click', () => openCert(byId(Number(li.dataset.oliveId)))));

  // ---------- enquete (uma por vez, criada no painel /admin/) ----------
  const DUEL_ORDER = ['esquerda', 'direita'];

  function pollResultsHtml(p) {
    const total = Math.max(1, p.total);
    const rows = p.opcoes.map((o) => {
      const pct = Math.round((o.votos / total) * 100);
      const bar = p.duelo
        ? DUEL_ORDER.map((s) => `<i style="width:${(((o.porLado?.[s] || 0) / total) * 100).toFixed(1)}%;background:${DC.sides[s].theme.gold}"></i>`).join('')
        : `<i style="width:${pct}%"></i>`;
      return `<li class="${p.meuVoto === o.id ? 'mine' : ''}">
        <div class="poll-row"><span>${p.meuVoto === o.id ? '✓ ' : ''}${esc(o.texto)}</span><b>${pct}%</b></div>
        <div class="poll-bar">${bar}</div>
      </li>`;
    }).join('');
    const legend = p.duelo
      ? `<p class="poll-legend">${DUEL_ORDER.map((s) => `<span><i style="background:${DC.sides[s].theme.gold}"></i>${DC.sides[s].emoji} ${esc(DC.sides[s].name)}: <b>${(p.porLado?.[s] || 0).toLocaleString('pt-BR')}</b></span>`).join('')}</p>`
      : '';
    return `<ul class="poll-results">${rows}</ul>${legend}`;
  }

  function renderPoll() {
    const box = $('#poll');
    const p = DC.enquete;
    if (!box) return;
    $('#enquete').hidden = !p;
    if (!p) return;

    const voted = p.meuVoto !== null;
    let body;
    if (p.mostra) {
      body = pollResultsHtml(p);
    } else if (voted) {
      body = '<p class="poll-note">✓ Voto registrado! O resultado aparece quando a enquete encerrar.</p>';
    } else {
      body = `<div class="poll-options">${p.opcoes.map((o) => `<button type="button" class="poll-option" data-vote="${o.id}">${esc(o.texto)}</button>`).join('')}</div>`;
    }
    // resultado visível antes de votar ("sempre"): mostra as barras e também os botões
    if (p.mostra && !voted) {
      body = `<div class="poll-options">${p.opcoes.map((o) => `<button type="button" class="poll-option" data-vote="${o.id}">${esc(o.texto)}</button>`).join('')}</div>` + body;
    }

    const fim = p.termina ? ` · encerra em ${fmtDate(p.termina.slice(0, 10))} às ${p.termina.slice(11, 16)}` : '';
    box.innerHTML = `
      <div class="poll-head">
        <span class="tag">${p.duelo ? `Duelo entre os potes ${DC.sides.esquerda.emoji} × ${DC.sides.direita.emoji}` : 'Enquete'}</span>
        <h2>${esc(p.pergunta)}</h2>
        ${p.descricao ? `<p class="poll-desc">${esc(p.descricao)}</p>` : ''}
        ${p.duelo && !voted ? `<p class="poll-desc">Seu voto conta para o time ${S.emoji} ${esc(S.name)}.</p>` : ''}
      </div>
      ${body}
      ${!DC.logado && !voted ? `<p class="poll-login">🔒 Só quem está logado vota — um voto por pessoa. <a href="${esc(DC.loginUrl)}">Entrar para votar</a> (sem senha, pelo e-mail).</p>` : ''}
      <p class="poll-foot">${p.total !== null ? `${p.total.toLocaleString('pt-BR')} ${p.total === 1 ? 'voto' : 'votos'}` : 'Resultado oculto até o fim'}${fim}</p>`;
  }

  $('#poll')?.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-vote]');
    if (!btn || DC.enquete.meuVoto !== null) return;
    if (!DC.logado) { location.href = DC.loginUrl; return; } // volta para a enquete depois de entrar
    $$('[data-vote]').forEach((b) => { b.disabled = true; });
    try {
      const res = await fetch('api/enquete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ enquete: DC.enquete.id, opcao: Number(btn.dataset.vote), lado: SIDE }),
      });
      const data = await res.json();
      if (data.login) { location.href = DC.loginUrl; return; } // sessão expirou
      if (data.erro) toast(data.erro);
      DC.enquete = data.enquete || (data.erro ? DC.enquete : data);
      if (!data.erro) toast('Voto registrado!');
    } catch {
      toast('Não foi possível votar agora. Tente de novo.');
    }
    renderPoll();
  });

  // ---------- perfil (perfil.php) ----------
  const addYear = (iso) => { const [y, m, d] = iso.split('-'); return `${Number(y) + 1}-${m}-${d}`; };

  // capa com itens do pote espalhados
  function coverPattern() {
    const tipos = Object.keys(S.types);
    let out = '';
    for (let i = 0; i < 39; i++) {
      const x = (i % 13) * 31 + rand(i * 3) * 18;
      const y = Math.floor(i / 13) * 34 + 10 + rand(i * 5) * 18;
      out += `<g transform="translate(${x.toFixed(1)} ${y.toFixed(1)}) rotate(${(rand(i * 7) * 360).toFixed(0)}) scale(.55)">${S.shapes[tipos[i % tipos.length]]}</g>`;
    }
    return `<svg class="profile-cover-art" viewBox="0 0 400 100" preserveAspectRatio="xMidYMid slice" aria-hidden="true">${out}</svg>`;
  }

  // de onde veio o post comentado (pode ser do outro pote)
  function postContext(postId) {
    const m = postId.match(/^(\w+)-o(\d+)$/);
    if (m) {
      const list = m[1] === SIDE ? olives : DC.otherItems || [];
      const o = list.find((x) => x.id === Number(m[2]));
      return o ? { side: m[1], o, text: o.frase } : null;
    }
    const side = postId.split('-')[0];
    const p = store.get(`${side}_posts`, []).find((x) => x.id === postId);
    if (!p) return null;
    const owner = store.get(`${side}_mine`, []).find((x) => x.id === p.oliveId);
    return owner ? { side, o: owner, text: p.text || '(vídeo)' } : null;
  }

  function renderProfile() {
    const card = $('#profile-card');
    const o = byId(DC.profileId);
    if (!o) {
      card.innerHTML = `<div class="profile-missing">
        <h1>Perfil não encontrado</h1>
        <p>Essa ${S.item} não está no pote (ou o link está errado).</p>
        <a class="btn btn-gold" href="pote.php?lado=${SIDE}">Voltar ao pote ${S.emoji}</a>
      </div>`;
      $$('.profile-side, .profile-stats-wrap, #mural, .profile-page > .section.alt').forEach((el) => { el.hidden = true; });
      return;
    }

    const isMine = mine.some((m) => m.id === o.id);
    const posts = allPosts();
    const received = posts.flatMap((p) => commentsOf(p.id));
    const visitors = received.filter((c) => c.autor.side !== SIDE);
    const made = [...DC.comments, ...myComments].filter((c) => c.autor.side === SIDE && c.autor.id === o.id);
    const madeOther = made.filter((c) => !c.post.startsWith(SIDE + '-'));
    const likes = posts.reduce((n, p) => n + likesOf(p), 0);
    const validade = o.valido_ate || addYear(o.desde);

    const badges = [
      isMine && ['voce', '★ Este é você'],
      o.id <= 100 && ['fundador', `🏅 ${o.id <= 10 ? 'Fundador(a) top 10' : 'Fundador(a)'}`],
      scaleOf(o) > 1 && ['grande', `${S.emoji} ${S.Item} ${typeLabel(o.tipo)}`],
      videoOf(o) && ['video', '🎬 Publica vídeos'],
      madeOther.length && ['debate', `${OTHER.emoji} Debate com o outro lado`],
      likes >= 300 && ['popular', '🔥 Popular no mural'],
    ].filter(Boolean);

    document.title = `${o.nome} · ${S.name}`;
    card.innerHTML = `
      <div class="profile-cover">${coverPattern()}<span class="profile-number">${numero(o.id)}</span></div>
      <div class="profile-main">
        <div class="profile-avatar">${avatarWithSelo(o, 'profile-photo')}<span class="profile-item" title="${esc(S.Item)} ${esc(typeLabel(o.tipo))}">${itemSvg(o.tipo, SIDE, Math.min(scaleOf(o), 1.2))}</span></div>
        <div class="profile-id">
          <h1>${esc(o.nome)}</h1>
          <p class="profile-meta">📍 ${esc(o.cidade)}/${esc(o.uf)} · ${esc(S.Item)} ${esc(typeLabel(o.tipo))}</p>
        </div>
        <div class="profile-since">
          <small>${esc(S.cert_since)}</small>
          <b>${fmtDate(o.desde)}</b>
        </div>
        <blockquote class="profile-quote">“${esc(o.frase)}”</blockquote>
        ${badges.length ? `<ul class="profile-badges">${badges.map(([k, t]) => `<li class="badge-${k}">${esc(t)}</li>`).join('')}</ul>` : ''}
        ${isMine ? `<div class="profile-validity">
          <span>Sua ${S.item} fica no pote até <b>${fmtDate(validade)}</b></span>
          <span class="validity-bar"><i style="width:${Math.min(100, Math.max(3, ((Date.now() - new Date(o.desde)) / (new Date(validade) - new Date(o.desde))) * 100)).toFixed(0)}%"></i></span>
        </div>` : ''}
        <div class="profile-actions">
          <button class="btn btn-gold" type="button" data-view="${o.id}">Ver certificado</button>
          <button class="btn btn-ghost" type="button" data-share-profile>Compartilhar perfil</button>
          ${isMine ? `<button class="btn btn-ghost" type="button" data-open-buy>+ Mais ${S.items}</button>` : ''}
        </div>
      </div>`;

    $('#profile-stats').innerHTML = [
      [S.emoji, likes, 'curtidas recebidas'],
      ['📝', posts.length, posts.length === 1 ? 'publicação' : 'publicações'],
      ['💬', received.length, 'comentários recebidos'],
      [OTHER.emoji, visitors.length, `comentários de quem é ${OTHER.name}`],
      ['🗣️', made.length, 'comentários feitos'],
    ].map(([icon, n, label]) => `<div class="stat-card"><span>${icon}</span><b>${n.toLocaleString('pt-BR')}</b><small>${esc(label)}</small></div>`).join('');

    // comentários que a pessoa fez (neste pote e no outro)
    $('#made-comments').innerHTML = made.length
      ? made.map((c) => {
        const ctx = postContext(c.post);
        const sd = DC.sides[ctx?.side || SIDE];
        const away = ctx && ctx.side !== SIDE;
        return `<article class="made-comment${away ? ' is-away' : ''}"${away ? ` style="${themeVars(sd)}"` : ''}>
          <div class="made-where">
            ${away ? `<span class="side-tag">${sd.emoji} no pote ${esc(sd.name)}</span>` : '<span class="made-here">neste pote</span>'}
            ${ctx ? `em resposta a <a class="name-link" href="${profileUrl(ctx.side, ctx.o.id)}">${esc(nomeProprio(ctx.o.nome))}</a>: <q>${esc(ctx.text)}</q>` : ''}
          </div>
          <p>${esc(c.texto)}</p>
        </article>`;
      }).join('')
      : `<p class="comment-empty">${esc(o.nome.split(' ')[0])} ainda não comentou em nenhum post.</p>`;

    if (!posts.length) $('#feed').innerHTML = `<p class="comment-empty">Nenhuma publicação ainda.</p>`;

    // no pote: destaca a azeitona/pimenta da pessoa
    const inJar = $(`#jar-items .olive[data-id="${o.id}"]`);
    if (inJar) {
      inJar.classList.add('is-profile');
      inJar.parentNode.parentNode.appendChild(inJar.parentNode); // traz para frente
    }
    $('#profile-jar-caption').textContent = inJar
      ? `${S.Item} ${numero(o.id)} está brilhando no pote.`
      : `${S.Item} ${numero(o.id)} está no fundo do pote (o vidro mostra as mais recentes).`;

    // só a própria pessoa vê: todos os itens dela nos dois potes
    if (isMine) {
      const all = Object.keys(DC.sides).flatMap((s) => store.get(`${s}_mine`, []).map((x) => ({ ...x, side: s })));
      $('#my-items-section').hidden = false;
      $('#my-items').innerHTML = all.map((x) => {
        const sd = DC.sides[x.side];
        return `<a class="my-item${x.side === SIDE && x.id === o.id ? ' current' : ''}" href="${profileUrl(x.side, x.id)}" style="${themeVars(sd)}">
          ${itemSvg(x.tipo, x.side)}
          <span><b>${esc(nomeProprio(x.nome))}</b><small>${esc(sd.Item)} ${esc(typeLabel(x.tipo, x.side))} · ${numero(x.id)}</small></span>
        </a>`;
      }).join('');
    }
  }

  document.addEventListener('click', async (e) => {
    if (!e.target.closest('[data-share-profile]')) return;
    const o = byId(DC.profileId);
    const url = oliveUrl(o);
    const text = `${S.emoji} ${o.nome} · ${S.cert_since.toLowerCase()} ${fmtDate(o.desde)}`;
    if (navigator.share) { navigator.share({ title: S.name, text, url }).catch(() => {}); return; }
    try { await navigator.clipboard.writeText(url); toast('Link do perfil copiado!'); } catch { toast(url); }
  });

  // botão "Meu perfil" no topo (qualquer item comprado, em qualquer pote)
  function updateMyProfileLink() {
    const eu = me();
    const link = $('#my-profile');
    if (!eu || !link) return;
    link.href = profileUrl(eu.side, eu.id);
    link.innerHTML = `${avatarHtml(eu, 'my-profile-avatar')}<span>Meu perfil</span>`;
    link.hidden = false;
  }

  // ---------- início ----------
  if ($('#jar')) {
    renderJar();
    setupJarTooltip();
    // pré-carrega as fotos para o balão abrir já com a imagem
    (window.requestIdleCallback || setTimeout)(() => olives.forEach((o) => { if (o.foto) new Image().src = o.foto; }));
  }
  updateStats();
  renderFeed();
  updateComposer();
  updateMyProfileLink();
  renderPoll();
  if (DC.profileId !== undefined) renderProfile();

  // link compartilhado: pote.php?lado=direita#azeitona-97 abre direto o certificado
  const m = location.hash.match(new RegExp(`^#${S.item}-(\\d+)$`));
  if (m) openCert(byId(Number(m[1])));
})();
