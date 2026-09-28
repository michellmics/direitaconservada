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

  const mine = store.get('mine', []);          // azeitonas compradas neste navegador
  const myPosts = store.get('posts', []);      // posts do mural publicados neste navegador
  const liked = new Set(store.get('likes', []));

  const olives = [...DC.olives, ...mine];
  const byId = (id) => olives.find((o) => o.id === id);

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
  const oliveUrl = (o) => `${location.origin}${location.pathname}#azeitona-${o.id}`;
  const gradOf = (tipo) => ({ preta: 'olive-preta', grande: 'olive-grande' }[tipo] || 'olive-verde');
  const typeLabel = (tipo) => DC.types[tipo]?.label || tipo;
  const money = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

  // Foto da pessoa; sem foto (ou se falhar ao carregar) mostra as iniciais
  const initialsAvatar = (o, cls) => `<div class="${cls} avatar-${o.tipo}">${esc(initials(o.nome))}</div>`;
  const avatarHtml = (o, cls) => o.foto
    ? `<img class="${cls} avatar-photo" src="${esc(o.foto)}" alt="Foto de ${esc(o.nome)}" data-avatar-of="${o.id}" data-cls="${cls}">`
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
    if (img.tagName !== 'IMG' || !img.dataset.avatarOf) return;
    img.outerHTML = initialsAvatar(byId(Number(img.dataset.avatarOf)), img.dataset.cls);
  }, true);

  function toast(msg) {
    const t = $('#toast');
    t.textContent = msg;
    t.hidden = false;
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => { t.hidden = true; }, 2600);
  }

  // ---------- pote ----------
  const PER_ROW = 9;
  const MAX_IN_JAR = PER_ROW * 18;

  function olivePosition(i) {
    const row = Math.floor(i / PER_ROW);
    const col = i % PER_ROW;
    return {
      x: 66 + col * 33 + (row % 2 ? 16 : 0) + (rand(i) - 0.5) * 6,
      y: 494 - row * 21 + (rand(i + 7) - 0.5) * 4,
      r: (rand(i + 3) - 0.5) * 70,
    };
  }

  function oliveNode(o, i, animate) {
    const { x, y } = olivePosition(i);
    const pos = document.createElementNS(SVG, 'g');
    const scale = o.tipo === 'grande' ? 1.5 : 1;
    pos.setAttribute('transform', `translate(${x.toFixed(1)} ${y.toFixed(1)}) scale(${scale})`);
    const g = document.createElementNS(SVG, 'g');
    g.setAttribute('class', 'olive' + (animate ? ' drop' : '') + (mine.some((m) => m.id === o.id) ? ' is-mine' : ''));
    g.dataset.id = o.id;
    g.innerHTML = oliveInner(o);
    pos.appendChild(g);
    return pos;
  }

  function oliveInner(o) {
    return `<g transform="rotate(${olivePosition(olives.indexOf(o)).r.toFixed(0)})">` +
      `<ellipse rx="16" ry="11.5" fill="url(#${gradOf(o.tipo)})"/>` +
      (o.tipo === 'recheada' ? '<ellipse cx="14" rx="3.8" ry="4.6" fill="#c0392b"/>' : '') +
      '<ellipse cx="-6" cy="-5" rx="5" ry="2" fill="#fff" opacity=".35"/></g>' +
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
    const layer = $('#olives');
    layer.innerHTML = '';
    const inJar = olives.slice(0, MAX_IN_JAR).map((o, i) => [o, i]);
    // as grandes são desenhadas por último para ficarem por cima das vizinhas
    const ordered = [...inJar.filter(([o]) => o.tipo !== 'grande'), ...inJar.filter(([o]) => o.tipo === 'grande')];
    ordered.forEach(([o, i]) => layer.appendChild(oliveNode(o, i, false)));
  }

  function dropOlive(o) {
    const i = olives.length - 1;
    if (i >= MAX_IN_JAR) return;
    $('#olives').appendChild(oliveNode(o, i, true));
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
    $('#stat-total').textContent = olives.length.toLocaleString('pt-BR');
    $('#stat-hoje').textContent = olives.filter((o) => o.desde === todayIso()).length.toLocaleString('pt-BR');
    $('#jar-count').textContent = olives.length.toLocaleString('pt-BR');
    $('#jar-meter').style.width = Math.max(0.5, (olives.length / DC.capacity) * 100) + '%';
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
    if (location.hash.startsWith('#azeitona-')) history.replaceState(null, '', location.pathname);
  }
  document.addEventListener('click', (e) => { if (e.target.closest('[data-close]')) closeModals(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModals(); });

  // ---------- certificado ----------
  function oliveSvg(tipo) {
    const [rx, ry] = tipo === 'grande' ? [29, 21] : [24, 17];
    return `<svg viewBox="0 0 60 44"><ellipse cx="30" cy="22" rx="${rx}" ry="${ry}" fill="url(#${gradOf(tipo)})"/>` +
      (tipo === 'recheada' ? '<ellipse cx="50" cy="22" rx="6" ry="7" fill="#c0392b"/>' : '') +
      '<ellipse cx="22" cy="15" rx="7" ry="3" fill="#fff" opacity=".35"/></svg>';
  }

  function certHtml(o) {
    return `<div class="cert">
      <small>CERTIFICADO DE CONSERVAÇÃO</small>
      <div class="cert-photo">${avatarWithSelo(o, 'cert-avatar')}${oliveSvg(o.tipo)}</div>
      <div class="cert-name">${esc(o.nome)}</div>
      <div class="cert-since">Direita conservada desde<b>${fmtDate(o.desde)}</b></div>
      <q>${esc(o.frase)}</q>
      <div class="cert-foot"><span>Azeitona ${numero(o.id)} · ${esc(typeLabel(o.tipo))}</span><span>${esc(o.cidade)}/${esc(o.uf)}</span></div>
    </div>`;
  }

  function shareText(o) {
    return `🫒 Sou Direita Conservada desde ${fmtDate(o.desde)}! Azeitona ${numero(o.id)}.\n"${o.frase}"\nGaranta a sua:`;
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
      ${navigator.share ? `<button class="btn btn-link wide" data-native-share="${o.id}">Mais opções…</button>` : ''}`;
  }

  function openCert(o) {
    if (!o) return;
    $('#cert-view').innerHTML = certHtml(o);
    $('#share-view').innerHTML = shareButtons(o);
    openModal('#cert-modal');
    history.replaceState(null, '', '#azeitona-' + o.id);
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
      navigator.share({ title: 'Direita Conservada', text: shareText(o), url: oliveUrl(o) }).catch(() => {});
    }
  });

  // Gera o certificado como PNG 1080x1920 (formato Stories/Status)
  async function downloadCert(o) {
    const photo = o.foto ? await loadImage(o.foto).catch(() => null) : null;
    const seloImg = o.selo && isImageSelo(o.selo) ? await loadImage(o.selo).catch(() => null) : null;
    const W = 1080, H = 1920;
    const c = document.createElement('canvas');
    c.width = W; c.height = H;
    const g = c.getContext('2d');

    g.fillStyle = '#161c0c'; g.fillRect(0, 0, W, H);
    const glow = g.createRadialGradient(W / 2, 300, 50, W / 2, 300, 900);
    glow.addColorStop(0, '#3a4a1a'); glow.addColorStop(1, 'rgba(22,28,12,0)');
    g.fillStyle = glow; g.fillRect(0, 0, W, H);

    const cx = 90, cy = 330, cw = W - 180, ch = 1180;
    roundRect(g, cx, cy, cw, ch, 36); g.fillStyle = '#f4ecd8'; g.fill();
    roundRect(g, cx + 22, cy + 22, cw - 44, ch - 44, 24); g.strokeStyle = '#c9a227'; g.lineWidth = 8; g.stroke();
    roundRect(g, cx + 40, cy + 40, cw - 80, ch - 80, 18); g.strokeStyle = '#3d4a1f'; g.lineWidth = 2; g.setLineDash([10, 8]); g.stroke(); g.setLineDash([]);

    g.textAlign = 'center';
    g.fillStyle = '#f4ecd8'; g.font = '800 72px Fraunces, Georgia, serif';
    g.fillText('Direita', W / 2, 170);
    g.fillStyle = '#e7c54d'; g.fillText('Conservada', W / 2, 250);

    g.fillStyle = '#7a5c0c'; g.font = '700 30px Inter, sans-serif';
    g.fillText('C E R T I F I C A D O   D E   C O N S E R V A Ç Ã O', W / 2, cy + 130);

    // foto (se houver) com a azeitona como selo; sem foto, azeitona grande
    const px = W / 2, py = cy + 290;
    if (photo) {
      g.save();
      g.beginPath(); g.arc(px, py, 130, 0, Math.PI * 2); g.clip();
      g.drawImage(photo, px - 130, py - 130, 260, 260);
      g.restore();
      g.beginPath(); g.arc(px, py, 130, 0, Math.PI * 2); g.strokeStyle = '#c9a227'; g.lineWidth = 10; g.stroke();
      drawOlive(g, o.tipo, px + 120, py + 100, 0.45);
      if (o.selo) drawSelo(g, o.selo, seloImg, px - 105, py + 95, 42);
    } else {
      drawOlive(g, o.tipo, px, py, 1);
      if (o.selo) drawSelo(g, o.selo, seloImg, px - 150, py + 75, 42);
    }

    g.fillStyle = '#25300f'; g.font = '800 84px Fraunces, Georgia, serif';
    fitText(g, o.nome, W / 2, cy + 520, cw - 140);
    g.font = '600 38px Inter, sans-serif'; g.fillText('Direita conservada desde', W / 2, cy + 610);
    g.fillStyle = '#3d4a1f'; g.font = '800 96px Fraunces, Georgia, serif'; g.fillText(fmtDate(o.desde), W / 2, cy + 710);

    g.fillStyle = '#25300f'; g.font = 'italic 600 46px Fraunces, Georgia, serif';
    wrapText(g, `“${o.frase}”`, W / 2, cy + 820, cw - 180, 60);

    g.strokeStyle = '#b9b69a'; g.setLineDash([8, 8]); g.beginPath(); g.moveTo(cx + 80, cy + ch - 130); g.lineTo(cx + cw - 80, cy + ch - 130); g.stroke(); g.setLineDash([]);
    g.fillStyle = '#6b6a55'; g.font = '600 32px Inter, sans-serif';
    g.textAlign = 'left'; g.fillText(`Azeitona ${numero(o.id)} · ${typeLabel(o.tipo)}`, cx + 80, cy + ch - 75);
    g.textAlign = 'right'; g.fillText(`${o.cidade}/${o.uf}`, cx + cw - 80, cy + ch - 75);

    g.textAlign = 'center'; g.fillStyle = '#f4ecd8'; g.font = '600 40px Inter, sans-serif';
    g.fillText('Garanta sua azeitona no pote', W / 2, H - 230);
    g.fillStyle = '#e7c54d'; g.font = '800 48px Inter, sans-serif';
    g.fillText(location.host || 'direitaconservada.com.br', W / 2, H - 160);

    const a = document.createElement('a');
    a.download = `conservado-${o.id}.png`;
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

  function drawOlive(g, tipo, x, y, s) {
    if (tipo === 'grande') s *= 1.2;
    const og = g.createRadialGradient(x - 40 * s, y - 30 * s, 10 * s, x, y, 150 * s);
    if (tipo === 'preta') { og.addColorStop(0, '#6a5a6e'); og.addColorStop(.6, '#2e2530'); og.addColorStop(1, '#140f15'); }
    else if (tipo === 'grande') { og.addColorStop(0, '#d2de6e'); og.addColorStop(.55, '#8fa532'); og.addColorStop(1, '#55661a'); }
    else { og.addColorStop(0, '#b5c25a'); og.addColorStop(.6, '#7d8c2f'); og.addColorStop(1, '#4f5a18'); }
    g.beginPath(); g.ellipse(x, y, 150 * s, 105 * s, 0, 0, Math.PI * 2); g.fillStyle = og; g.fill();
    if (tipo === 'recheada') { g.beginPath(); g.ellipse(x + 128 * s, y, 34 * s, 42 * s, 0, 0, Math.PI * 2); g.fillStyle = '#c0392b'; g.fill(); }
    g.beginPath(); g.ellipse(x - 55 * s, y - 45 * s, 45 * s, 18 * s, -0.2, 0, Math.PI * 2); g.fillStyle = 'rgba(255,255,255,.35)'; g.fill();
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

  $$('[data-open-buy]').forEach((b) => b.addEventListener('click', () => { goStep(1); openModal('#buy-modal'); buyForm.nome.focus(); }));
  // preço acompanha o tipo escolhido
  function updateSelectedPrice() {
    const tipo = buyForm.tipo.value;
    $$('[data-price-selected]', buyModal).forEach((el) => { el.textContent = money(DC.types[tipo].price); });
    $$('[data-type-selected]', buyModal).forEach((el) => { el.textContent = typeLabel(tipo); });
  }
  $$('input[name="tipo"]', buyForm).forEach((r) => r.addEventListener('change', updateSelectedPrice));

  $$('.chip', buyForm).forEach((c) => c.addEventListener('click', () => { buyForm.frase.value = c.dataset.phrase; }));
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

  buyForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const f = new FormData(buyForm);
    pending = {
      nome: f.get('nome').trim(),
      cidade: f.get('cidade').trim(),
      uf: f.get('uf'),
      tipo: f.get('tipo'),
      frase: f.get('frase').trim(),
      foto: photoData,
      selo: f.get('selo') === 'custom' ? seloData : f.get('selo') || null,
    };
    renderFakeQr();
    goStep(2);
  });

  $('#copy-pix').addEventListener('click', () => toast('Código Pix copiado (de mentirinha)'));

  $('#simulate-pay').addEventListener('click', () => {
    const o = { ...pending, id: Math.max(...olives.map((x) => x.id)) + 1, desde: todayIso(), likes: 0 };
    olives.push(o);
    mine.push(o);
    store.set('mine', mine);
    dropOlive(o);
    updateStats();
    renderFeed();
    updateComposer();
    $('#cert-slot').innerHTML = certHtml(o);
    $('#share-buttons').innerHTML = shareButtons(o);
    goStep(3);
    buyForm.reset();
    resetPhoto();
    resetSelo();
    updateSelectedPrice();
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
    const fromOlives = olives.map((o) => ({ id: 'o' + o.id, oliveId: o.id, text: o.frase, date: o.desde, likes: o.likes, video: o.video || null }));
    const extra = myPosts.map((p) => ({ ...p, isNew: true }));
    const list = [...extra, ...fromOlives];
    return sort === 'top'
      ? list.sort((a, b) => likesOf(b) - likesOf(a))
      : list.sort((a, b) => (b.date + b.id).localeCompare(a.date + a.id));
  }
  const likesOf = (p) => p.likes + (liked.has(p.id) ? 1 : 0);

  function postHtml(p) {
    const o = byId(p.oliveId);
    const video = p.video ? ` data-video="${esc(JSON.stringify(p.video))}"` : '';
    return `<article class="post${p.isNew ? ' is-new' : ''}${p.video ? ' has-video' : ''}"${video}>
      <div class="post-head">
        ${avatarWithSelo(o, 'post-avatar')}
        <div><b>${esc(o.nome)}</b><small>${esc(o.cidade)}/${esc(o.uf)} · conservado desde ${fmtDate(o.desde)}</small></div>
      </div>
      ${p.text ? `<p>${esc(p.text)}</p>` : ''}
      ${p.video ? `<div class="post-video">${videoCoverHtml(p.video)}</div>` : ''}
      <div class="post-actions">
        <button data-like="${p.id}" class="${liked.has(p.id) ? 'liked' : ''}">🫒 ${likesOf(p)}</button>
        <button data-share-post="${p.id}">Compartilhar</button>
        <button data-view="${o.id}">Certificado</button>
      </div>
    </article>`;
  }

  function renderFeed() {
    const posts = allPosts();
    $('#feed').innerHTML = posts.slice(0, shown).map(postHtml).join('');
    $('#feed-more').hidden = shown >= posts.length;
  }

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
    if (e.target.closest('[data-play]')) playVideo(e.target.closest('.post'));
    if (e.target.closest('[data-stop]')) stopVideo(e.target.closest('.post'));
    if (like) {
      const id = like.dataset.like;
      liked.has(id) ? liked.delete(id) : liked.add(id);
      store.set('likes', [...liked]);
      const p = allPosts().find((x) => x.id === id);
      like.classList.toggle('liked', liked.has(id));
      like.textContent = `🫒 ${likesOf(p)}`;
    }
    if (share) {
      const p = allPosts().find((x) => x.id === share.dataset.sharePost);
      const o = byId(p.oliveId);
      const text = `"${p.text || 'Olha esse vídeo'}" — ${o.nome}, conservado desde ${fmtDate(o.desde)} 🫒`;
      if (navigator.share) navigator.share({ title: 'Direita Conservada', text, url: oliveUrl(o) }).catch(() => {});
      else window.open(`https://wa.me/?text=${encodeURIComponent(text + ' ' + oliveUrl(o))}`, '_blank', 'noopener');
    }
    if (view) openCert(byId(Number(view.dataset.view)));
  });

  // composer: só quem tem azeitona publica
  const composer = $('#composer');
  const composerText = $('#composer-text');

  function updateComposer() {
    const me = mine[mine.length - 1];
    if (me) {
      $('#composer-avatar').outerHTML = avatarHtml(me, 'composer-avatar').replace(/^<(\w+) /, '<$1 id="composer-avatar" ');
      composerText.placeholder = `O que você quer compartilhar, ${me.nome.split(' ')[0]}?`;
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
  composerText.addEventListener('input', updateComposerPreview);
  composerText.addEventListener('focus', () => {
    if (!mine.length) { toast('Só conservados podem publicar. Garanta sua azeitona!'); }
  });
  composer.addEventListener('submit', (e) => {
    e.preventDefault();
    const me = mine[mine.length - 1];
    if (!me) { goStep(1); openModal('#buy-modal'); return; }
    const { video, text } = extractVideo(composerText.value);
    if (!text && !video) return;
    if (text.length > TEXT_MAX) { toast(`Máximo de ${TEXT_MAX} caracteres (sem contar o link)`); return; }
    myPosts.unshift({ id: 'p' + Date.now(), oliveId: me.id, text, video, date: todayIso(), likes: 0 });
    store.set('posts', myPosts);
    if (video) refreshOlive(me); // aparece o selo de play na azeitona
    composerText.value = '';
    updateComposerPreview();
    sort = 'recentes';
    $$('.tab').forEach((x) => x.classList.toggle('active', x.dataset.sort === 'recentes'));
    renderFeed();
    toast('Publicado no mural!');
  });

  // ranking: "mais antigos" abre o certificado
  $$('[data-olive-id]').forEach((li) => li.addEventListener('click', () => openCert(byId(Number(li.dataset.oliveId)))));

  // ---------- início ----------
  renderJar();
  setupJarTooltip();
  // pré-carrega as fotos para o balão abrir já com a imagem
  (window.requestIdleCallback || setTimeout)(() => olives.forEach((o) => { if (o.foto) new Image().src = o.foto; }));
  updateStats();
  renderFeed();
  updateComposer();

  // link compartilhado: #azeitona-97 abre direto o certificado
  const m = location.hash.match(/^#azeitona-(\d+)$/);
  if (m) openCert(byId(Number(m[1])));
})();
