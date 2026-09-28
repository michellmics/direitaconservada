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
  // id de cada comentário deste navegador (para citar e apagar); os antigos ganham um agora
  if (myComments.some((c) => !c.id)) {
    myComments.forEach((c, i) => { c.id ??= `l${Date.now()}-${i}`; });
    store.set('comments', myComments);
  }
  // apagado continua na lista (aparece "comentário apagado"), mas não conta para nada
  const comentariosAtivos = () => myComments.filter((c) => !c.apagado);

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
  // nome e cidade sempre com só as iniciais maiúsculas: "SÃO PAULO" / "são paulo" → "São Paulo"
  olives.forEach((o) => { o.nome = nomeProprio(o.nome); o.cidade = nomeProprio(o.cidade); });
  myComments.forEach((c) => { c.autor.nome = nomeProprio(c.autor.nome); });
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
  // Links de perfil já cifrados pelo servidor (DC.links; a chave não vem para o navegador).
  // Os das compras feitas neste navegador vêm de /api/link e ficam guardados (garantirLinksLocais).
  const linksLocais = store.get('links', {});
  const linkPerfilExato = (side, id) => DC.links?.perfil?.[side]?.[id] || linksLocais[`${side}:${id}`] || null;
  const profileUrl = (side, id) => linkPerfilExato(side, id) || DC.links?.pote?.[side] || './'; // sem link: vai para o pote
  const poteUrl = (side) => DC.links?.pote?.[side] || './';
  // link que vai para o compartilhamento: o perfil da pessoa
  const oliveUrl = (o) => new URL(profileUrl(o.side || SIDE, o.id), location.href).href;
  const typeLabel = (tipo, side = SIDE) => DC.sides[side].types[tipo]?.label || tipo;
  const money = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const scaleOf = (o) => S.scales[o.tipo] || 1;

  // ---------- tempo de assinatura (mesma conta de ano_de_assinatura()/anel_de_tempo() no PHP) ----------
  const addYear = (iso, n = 1) => {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(Date.UTC(y + n, m - 1, d)).toISOString().slice(0, 10); // 29/02 + 1 ano → 01/03, como no PHP
  };
  const anoDe = (o) => {
    const [y, m, d] = o.desde.split('-').map(Number);
    const [ty, tm, td] = todayIso().split('-').map(Number);
    return ty - y - (tm < m || (tm === m && td < d) ? 1 : 0) + 1;
  };
  const anelDe = (o) => { const a = anoDe(o); return a >= 3 ? 'ouro' : a === 2 ? 'prata' : ''; };
  const ANEL = { prata: '🥈', ouro: '🥇' };
  const tempoTexto = (o) => `${anelDe(o) ? ANEL[anelDe(o)] + ' ' : ''}${anoDe(o)}º ano no pote`;
  const validade = (o) => o.valido_ate || addYear(o.desde);
  const vencido = (o) => validade(o) < todayIso();
  const noPote = () => olives.filter((o) => !vencido(o)); // vencidos saem do pote e do mural
  const diasAte = (iso) => Math.round((new Date(iso + 'T12:00:00') - new Date(todayIso() + 'T12:00:00')) / 86400000);

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

  function toast(msg, ms = 2600) {
    const t = $('#toast');
    t.textContent = msg;
    t.hidden = false;
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => { t.hidden = true; }, ms);
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
  const jarOffset = () => Math.max(0, noPote().length - MAX_IN_JAR);
  const slotOf = (o) => noPote().indexOf(o) - jarOffset();

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
    const inJar = noPote().slice(jarOffset()).map((o, i) => [o, i]);
    // as maiores são desenhadas por último para ficarem por cima das vizinhas
    const ordered = [...inJar.filter(([o]) => scaleOf(o) <= 1), ...inJar.filter(([o]) => scaleOf(o) > 1)];
    ordered.forEach(([o, i]) => layer.appendChild(oliveNode(o, i, false)));
  }

  function dropOlive(o) {
    if (noPote().length > MAX_IN_JAR) {
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
        `<div class="tip-head">${avatarWithSelo(o, 'tip-photo')}<div><b>${esc(o.nome)}${nivelTag(SIDE, o.id)}</b><small>${numero(o.id)} · ${esc(typeLabel(o.tipo))} · ${esc(o.cidade)}/${esc(o.uf)}<br>desde ${fmtDate(o.desde)} · ${tempoTexto(o)}</small>${provocadorTag(o.id)}</div></div>` +
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
    set('#stat-total', noPote().length);
    set('#stat-hoje', noPote().filter((o) => o.desde === todayIso()).length);
    set('#jar-count', noPote().length);
    const meter = $('#jar-meter');
    if (meter) meter.style.width = Math.max(0.5, (noPote().length / DC.capacity) * 100) + '%';
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
      <div class="cert-tempo${anelDe(o) ? " cert-tempo-" + anelDe(o) : ""}">${tempoTexto(o)}</div>
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
    updateTotal(); // mostra o aviso de nível já na abertura
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
      cidade: nomeProprio(f.get('cidade')),
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
    const total = totalNow();
    $$('[data-price-total]', buyModal).forEach((el) => { el.textContent = money(total); });
    $('#nivel-nudge').innerHTML = nivelNudge(total);
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
  buyForm.elements.cidade.addEventListener('blur', (e) => { e.target.value = nomeProprio(e.target.value); });
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
    const nivelAntes = meuNivel(SIDE);
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
    limparTempero();
    const nivelNovo = meuNivel(SIDE);
    const nv = nivelNovo > nivelAntes ? nivelInfo(SIDE, nivelNovo) : null;
    $('#nivel-up').hidden = !nv;
    if (nv) {
      avisarPorEmail(SIDE);
      $('#nivel-up').innerHTML = `<span class="nivel-up-icone">${nv.icone}</span><span>${nivelAntes ? 'Você subiu para o' : 'Você entrou no'} nível <b>${esc(nv.nome)}</b>!<small>Seu nome agora aparece assim no pote, no mural e nos comentários.</small></span>`;
    }
    updateStats();
    renderFeed();
    updateComposer();
    updateMyProfileLink();
    renderNewest();
    renderMapa(); // a compra conta no estado da pessoa
    garantirLinksLocais(); // link cifrado do perfil das novas

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

  function renderFakeQr(sel = '#qr') {
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
    $(sel).innerHTML = html;
  }

  // ---------- vídeos (YouTube / TikTok) ----------
  const TEXT_MAX = 180;
  const COMENTARIO_MAX = 200; // letras do comentário (o link do vídeo não conta)
  const DICA_COMENTARIO = 'Cole um link do YouTube/TikTok para responder com vídeo.';
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

  // "box" = caixa de vídeo de um post (.post-video) ou de um comentário (.comment-video), com data-video
  function stopVideo(box) {
    box.innerHTML = videoCoverHtml(JSON.parse(box.dataset.video));
    box.classList.remove('is-playing');
    const post = box.closest('.post');
    if (post && !post.querySelector('[data-video].is-playing')) post.classList.remove('is-playing');
  }

  function playVideo(box) {
    $$('[data-video].is-playing').forEach(stopVideo); // um vídeo por vez
    const v = JSON.parse(box.dataset.video);
    const post = box.closest('.post');
    box.classList.add('is-playing');
    post?.classList.add('is-playing'); // o quadro do post alarga, também para vídeo de comentário
    box.innerHTML =
      `<div class="video-frame${v.vertical ? ' vertical' : ''}">
        <iframe src="${videoEmbedSrc(v)}" title="Vídeo do ${providerName(v)}" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
      </div>
      <button class="video-close" data-stop>Fechar vídeo</button>`;
    // espera o quadro expandir antes de rolar até ele
    requestAnimationFrame(() => box.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
  }

  const videoBoxHtml = (cls, v) => `<div class="${cls}" data-video="${esc(JSON.stringify(v))}">${videoCoverHtml(v)}</div>`;

  // ---------- mural ----------
  // Os posts vêm do servidor, 12 por vez: a 1ª página já vem na página (DC.feed) e "Carregar mais" busca
  // as próximas em /api/posts, na ordem da aba. Os publicados neste navegador aparecem no topo.
  let sort = 'recentes';
  const feed = { posts: DC.feed?.posts || [], mais: !!DC.feed?.mais, carregando: false, erro: false, pedido: 0 };

  // posts deste navegador (ainda não estão no servidor): publicados aqui + a frase de quem comprou aqui
  function postsLocais() {
    const frases = mine.filter((o) => !vencido(o) && !o.semPost)
      .map((o) => ({ id: `${SIDE}-o${o.id}`, oliveId: o.id, text: o.frase, date: o.desde, likes: o.likes || 0, video: o.video || null, isNew: true }));
    let list = [...myPosts.map((p) => ({ ...p, isNew: true })), ...frases];
    if (DC.profileId) list = list.filter((p) => p.oliveId === DC.profileId); // página de perfil
    return list.sort((a, b) => b.date.localeCompare(a.date) || String(b.id).localeCompare(String(a.id)));
  }
  const postsNaTela = () => [...postsLocais(), ...feed.posts];

  // busca a próxima página (ou, com reset, a 1ª de novo — ao trocar de aba); respostas atrasadas são ignoradas
  async function carregarPosts(reset = false) {
    if (feed.carregando && !reset) return;
    const pedido = ++feed.pedido;
    Object.assign(feed, { carregando: true, erro: false }, reset ? { posts: [], mais: false } : {});
    renderFeed();
    try {
      const res = await fetch('api/posts', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lado: SIDE, ordem: sort, offset: feed.posts.length, perfil: DC.profileId || null }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.erro);
      if (pedido !== feed.pedido) return;
      feed.posts = [...feed.posts, ...data.posts.filter((p) => !feed.posts.some((q) => q.id === p.id))];
      feed.mais = data.mais;
    } catch {
      if (pedido !== feed.pedido) return;
      feed.erro = true;
    }
    feed.carregando = false;
    renderFeed();
  }

  // todos os posts da pessoa no perfil (estatísticas): itens do pote + publicados aqui
  function allPosts() {
    const fromOlives = noPote().filter((o) => !o.semPost).map((o) => ({ id: `${SIDE}-o${o.id}`, oliveId: o.id, text: o.frase, date: o.desde, likes: o.likes, video: o.video || null }));
    const extra = myPosts.map((p) => ({ ...p, isNew: true }));
    let list = [...extra, ...fromOlives];
    if (DC.profileId) list = list.filter((p) => p.oliveId === DC.profileId); // página de perfil
    if (sort === 'top') return list.sort((a, b) => likesOf(b) - likesOf(a));
    if (sort === 'debate') return list.sort((a, b) => totalComentarios(b.id) - totalComentarios(a.id));
    return list.sort((a, b) => (b.date + b.id).localeCompare(a.date + a.id));
  }
  const likesOf = (p) => p.likes + (liked.has(p.id) ? 1 : 0);

  // ---------- comentários (os dois lados podem comentar) ----------
  // Os do servidor vêm de /api/comentarios, 10 por vez (os mais recentes; "ver anteriores" busca mais).
  // A página traz só as contagens (DC.comentarios.contagem); os feitos neste navegador somam por cima.
  const openComments = new Set();
  const CONTAGEM = DC.comentarios?.contagem || {};
  const locaisDoPost = (postId) => myComments.filter((c) => c.post === postId); // inclui os apagados (viram aviso)
  const totalComentarios = (postId) => (CONTAGEM[postId]?.total || 0) + locaisDoPost(postId).filter((c) => !c.apagado).length;
  const visitantesDoPost = (postId) => (CONTAGEM[postId]?.visitantes || 0)
    + locaisDoPost(postId).filter((c) => !c.apagado && c.autor.side !== SIDE).length;
  // ---------- Provocador(a) do mês: quem mais recebeu comentários do OUTRO pote neste mês ----------
  // (mesma regra de provocadores_do_mes() no PHP)
  const mesNome = () => new Date().toLocaleDateString('pt-BR', { month: 'long' });
  // item dono do post: "lado-o42" é a frase do item 42; "lado-p…" é uma publicação (deste navegador ou do banco)
  function donoDoPost(postId) {
    const m = postId.match(new RegExp(`^${SIDE}-o(\\d+)$`));
    if (m) return Number(m[1]);
    return myPosts.find((p) => p.id === postId)?.oliveId ?? feed.posts.find((p) => p.id === postId)?.oliveId ?? null;
  }
  let provocadoresCache = null;
  function provocadores() {
    if (provocadoresCache) return provocadoresCache;
    const mes = todayIso().slice(0, 7);
    // contagem do servidor (comentarios_provocadores) + os comentários feitos neste navegador
    const cont = new Map((DC.comentarios?.provocadores || []).map((p) => [p.id, p.n]));
    for (const c of comentariosAtivos()) {
      if (c.autor.side === SIDE || !c.post.startsWith(SIDE + '-') || (c.data || '').slice(0, 7) !== mes) continue;
      const dono = donoDoPost(c.post);
      if (dono !== null) cont.set(dono, (cont.get(dono) || 0) + 1);
    }
    return (provocadoresCache = [...cont]
      .filter(([id]) => byId(id) && !vencido(byId(id)))
      .sort((a, b) => b[1] - a[1])
      .map(([id, n]) => ({ o: byId(id), n })));
  }
  const provocadorTag = (id) => (provocadores()[0]?.o.id === id
    ? `<span class="provoc-tag" title="Quem mais recebeu comentários do outro pote em ${mesNome()}">🔥 Provocador(a) do mês</span>`
    : '');

  function renderProvocadores() {
    const box = $('#provocadores');
    if (!box) return;
    const top = provocadores().slice(0, 3);
    $('#provocadores-mes').textContent = mesNome();
    box.innerHTML = top.length
      ? top.map(({ o, n }, i) => `<li${i === 0 ? ' class="first"' : ''}>
          <span class="prov-pos">${i === 0 ? '🔥' : `${i + 1}º`}</span>
          ${avatarWithSelo(o, 'prov-avatar')}
          <span class="prov-name"><a class="name-link" href="${profileUrl(SIDE, o.id)}">${esc(o.nome)}${nivelTag(SIDE, o.id)}</a>
            <small>${OTHER.emoji} ${n} ${n === 1 ? 'comentário' : 'comentários'} de quem é ${esc(OTHER.name)}</small></span>
        </li>`).join('')
      : `<li class="prov-empty">Ninguém provocou o outro lado este mês… ainda.</li>`;
  }

  // ---------- nível ao lado do nome (tempero): mesma conta de includes/tempero.php ----------
  // Por pessoa e por pote: R$ em itens ativos + meses no pote + participação dos últimos 12 meses.
  // Os fatos dos dados de exemplo vêm prontos do PHP (DC.tempero); o que se faz neste navegador soma aqui.
  const TP = DC.tempero;
  const R = TP.regras;
  const TOPO = R.faixas.length;
  const temperoCache = new Map();
  const idsCache = new Map();
  const limparTempero = () => { temperoCache.clear(); idsCache.clear(); };

  const idsMeus = (side) => {
    if (!idsCache.has(side)) idsCache.set(side, new Set(store.get(`${side}_mine`, []).map((x) => x.id)));
    return idsCache.get(side);
  };
  // "eu" = itens comprados neste navegador; nos de exemplo, o dono é o 1º item da pessoa
  const donoDe = (side, id) => (idsMeus(side).has(id) ? 'eu' : TP.donos[side]?.[id] ?? id);
  const inicioJanela = () => {
    const d = new Date();
    d.setMonth(d.getMonth() - R.janela_meses);
    return d.toISOString().slice(0, 10);
  };
  const mesesDesde = (iso) => {
    const [y, m, d] = iso.split('-').map(Number);
    const [ty, tm, td] = todayIso().split('-').map(Number);
    return Math.max(0, (ty - y) * 12 + tm - m - (td < d ? 1 : 0));
  };
  const fatosVazios = () => ({ reais: 0, meses: 0, posts: 0, comentarios: 0, recebidos: 0, recebidos_outro: 0, curtidas: 0 });

  // com texto de 10+ letras (ou vídeo), no máximo 5 por dia
  function contarComentarios(list) {
    const porDia = new Map();
    list.forEach((c) => {
      if (!c.video && (c.texto || '').trim().length < R.comentario_min) return;
      porDia.set(c.data, Math.min(R.comentarios_por_dia, (porDia.get(c.data) || 0) + 1));
    });
    return [...porDia.values()].reduce((a, b) => a + b, 0);
  }

  // post "lado-o42" (frase da compra) ou "lado-p…" (publicado neste navegador) → [lado, dono]
  function donoDoPostEm(postId) {
    const m = postId.match(/^(\w+)-o(\d+)$/);
    if (m) return [m[1], donoDe(m[1], Number(m[2]))];
    const p = postId.match(/^(\w+)-p/);
    if (!p) return [null, null];
    // publicação: minha (deste navegador) ou de alguém do banco (está no mural carregado)
    const doBanco = p[1] === SIDE ? feed.posts.find((x) => x.id === postId) : null;
    return [p[1], doBanco ? donoDe(p[1], doBanco.oliveId) : 'eu'];
  }

  // fatos de quem comprou neste navegador (semItem: simula o nível sem um dos itens, ex.: se vencer)
  function fatosMeus(side, semItem = null) {
    const f = fatosVazios();
    const itens = store.get(`${side}_mine`, []).filter((o) => !vencido(o) && o.id !== semItem);
    if (!itens.length) return f;
    const janela = inicioJanela();
    const ids = idsMeus(side);
    itens.forEach((o) => { f.reais += DC.sides[side].types[o.tipo].price; });
    f.meses = mesesDesde(itens.map((o) => o.desde).sort()[0]);
    f.posts = store.get(`${side}_posts`, []).filter((p) => p.date >= janela).length;
    f.comentarios = contarComentarios(comentariosAtivos().filter((c) => c.autor.side === side && ids.has(c.autor.id)
      && c.data >= janela && donoDoPostEm(c.post).join() !== `${side},eu`));
    return f;
  }

  function fatosDe(side, dono) {
    if (dono === 'eu') return fatosMeus(side);
    const base = TP.fatos[side]?.[dono];
    if (!base) return fatosVazios();
    const f = { ...base };
    // o que se fez neste navegador também conta: comentários e curtidas nos posts da pessoa
    const janela = inicioJanela();
    comentariosAtivos().forEach((c) => {
      const [lado, d] = donoDoPostEm(c.post);
      if (lado === side && d === dono && c.data >= janela) f[c.autor.side === side ? 'recebidos' : 'recebidos_outro']++;
    });
    liked.forEach((postId) => { const [lado, d] = donoDoPostEm(postId); if (lado === side && d === dono) f.curtidas++; });
    return f;
  }

  // R$ → pontos de compra, em centavos (igual a tempero_pontos_compra() no PHP)
  const pontosCompra = (reais) => Math.floor((Math.round(reais * 100) * R.por_real) / 100);

  function pontosDe(f) {
    const compra = pontosCompra(f.reais);
    const tempo = f.meses * R.por_mes;
    const participacao = Math.floor(f.posts * R.post + f.comentarios * R.comentario + f.recebidos * R.recebido
      + f.recebidos_outro * R.recebido_outro + f.curtidas * R.curtida);
    return { compra, tempo, participacao, total: compra + tempo + participacao };
  }

  function nivelDe(p) {
    const n = R.faixas.filter((min) => p.total >= min).length;
    return n === TOPO && p.compra < R.topo_compra ? TOPO - 1 : n; // o topo exige compra
  }

  // pontos que faltam para o nível n (comprando: soma no total e na compra)
  const faltaPara = (p, n) => Math.max(R.faixas[n - 1] - p.total, n === TOPO ? R.topo_compra - p.compra : 0);

  // { n, p, dono } do dono do item (n = 0: sem item ativo)
  function tempero(side, id) {
    const dono = donoDe(side, id);
    const key = `${side}:${dono}`;
    if (!temperoCache.has(key)) {
      const p = pontosDe(fatosDe(side, dono));
      temperoCache.set(key, { n: nivelDe(p), p, dono });
    }
    return temperoCache.get(key);
  }

  const nivelInfo = (side, n) => DC.sides[side].tempero.niveis[n - 1];
  const fmtPontos = (side, pts) => `${(pts * DC.sides[side].tempero.fator).toLocaleString('pt-BR')} ${DC.sides[side].tempero.unidade}`;

  function nivelTag(side, id) {
    const t = tempero(side, id);
    if (!t.n) return '';
    const nv = nivelInfo(side, t.n);
    const label = `Nível ${nv.nome} · ${fmtPontos(side, t.p.total)}`;
    return `<span class="nivel nivel-${t.n}${t.n === TOPO ? ' nivel-topo' : ''}" role="img" aria-label="${esc(label)}" title="${esc(label)}">${nv.icone}</span>`;
  }

  // meu nível neste pote, antes/depois de algo que eu fiz: avisa quando sobe
  const meuNivel = (side) => nivelDe(pontosDe(fatosMeus(side)));
  function avisaSubida(side, antes) {
    limparTempero();
    const depois = meuNivel(side);
    if (depois <= antes) return null;
    const nv = nivelInfo(side, depois);
    toast(`${nv.icone} Você subiu para o nível ${nv.nome}!`, 4000);
    avisarPorEmail(side);
    return nv;
  }

  // logado: o servidor manda o e-mail "você subiu de nível" (uma vez por nível; /api/nivel)
  function avisarPorEmail(side) {
    if (!DC.logado) return;
    const { compra, tempo, participacao } = pontosDe(fatosMeus(side));
    fetch('api/nivel', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ lado: side, pontos: { compra, tempo, participacao } }),
    }).catch(() => {}); // sem banco/e-mail: o aviso na tela já basta
  }

  // "2 pimentas Dedo-de-moça": quantas do tipo mais barato cobrem os pontos que faltam
  function quantasFaltam(falta) {
    const barato = Object.values(S.types).reduce((a, b) => (b.price < a.price ? b : a));
    const qtd = Math.max(1, Math.ceil(falta / pontosCompra(barato.price)));
    return `${qtd} ${qtd > 1 ? S.items : S.item} ${esc(barato.label)}`;
  }

  // no pedido: "com esse pedido você sobe para…" ou "falta pouco para…"
  function nivelNudge(reais) {
    const f = fatosMeus(SIDE);
    const antes = nivelDe(pontosDe(f));
    f.reais += reais;
    const p = pontosDe(f);
    const depois = nivelDe(p);
    const nome = (n) => `<b>${esc(nivelInfo(SIDE, n).nome)}</b> ${nivelInfo(SIDE, n).icone}`;
    if (depois > antes) {
      return antes ? `Com esse pedido você sobe para o nível ${nome(depois)}` : `Você entra no pote no nível ${nome(depois)}`;
    }
    if (depois >= TOPO) return `Você está no nível máximo: ${nome(depois)}`;
    const falta = faltaPara(p, depois + 1);
    return `Faltam ${fmtPontos(SIDE, falta)} para o nível ${nome(depois + 1)}: com mais ${quantasFaltam(falta)} você chega lá.`;
  }

  const themeVars = (sd) => Object.entries(sd.theme).map(([k, v]) => `--${k}:${v}`).join(';');

  function commentsButton(postId) {
    const total = totalComentarios(postId);
    const visitors = visitantesDoPost(postId);
    return `💬 ${total}` + (visitors ? ` <span class="visitors" title="comentários de quem é do outro pote">· ${OTHER.emoji} ${visitors}</span>` : '');
  }

  // comentários já carregados do servidor, por post: { lista (mais antigo primeiro), mais, carregando, erro }
  const carregados = new Map();
  const comentarioPorId = (id) => myComments.find((c) => c.id === id)
    || [...carregados.values()].flatMap((st) => st.lista).find((c) => c.id === id) || null;
  // só o dono apaga: comentário feito neste navegador, por um item que é daqui
  const souDono = (c) => myComments.includes(c) && idsMeus(c.autor.side).has(c.autor.id);
  const trechoDe = (c) => {
    const t = c.texto || (c.video ? '🎬 vídeo' : '');
    return t.length > 90 ? t.slice(0, 90).trimEnd() + '…' : t;
  };

  function commentHtml(c) {
    if (c.apagado) {
      return `<div class="comment is-apagado" data-comment-id="${esc(c.id)}">
        <span class="comment-avatar comment-avatar-apagado" aria-hidden="true">🗑️</span>
        <div class="comment-body"><p>Comentário apagado pelo autor.</p></div>
      </div>`;
    }
    const a = c.autor;
    const sd = DC.sides[a.side];
    const visitor = a.side !== SIDE;
    const link = c.link || profileUrl(a.side, a.id);
    // a citação mostra o trecho guardado; se o original foi apagado, avisa
    const cita = c.cita ? `<button type="button" class="comment-cita" data-ir-comentario="${esc(c.cita.id)}">
        <b>↩ ${esc(c.cita.nome)}</b><span>${comentarioPorId(c.cita.id)?.apagado ? 'comentário apagado' : esc(c.cita.texto)}</span>
      </button>` : '';
    // quem vem do outro pote aparece com as cores do lado dele
    return `<div class="comment${visitor ? ' is-visitor' : ''}" data-comment-id="${esc(c.id)}"${visitor ? ` style="${themeVars(sd)}"` : ''}>
      ${avatarWithSelo(a, 'comment-avatar')}
      <div class="comment-body">
        <div class="comment-head">${a.id ? `<a class="name-link" href="${esc(link)}">${esc(a.nome)}</a>${nivelTag(a.side, a.id)}` : `<b>${esc(a.nome)}</b>`}${visitor ? `<span class="side-tag">${sd.emoji} ${esc(sd.name)}</span>` : ''}</div>
        ${cita}
        ${c.texto ? `<p>${esc(c.texto)}</p>` : ''}
        ${c.video ? videoBoxHtml('comment-video', c.video) : ''}
        <div class="comment-actions">
          ${me() ? `<button type="button" data-citar="${esc(c.id)}">↩ Citar</button>` : ''}
          ${souDono(c) ? `<button type="button" class="comment-apagar" data-apagar="${esc(c.id)}">Apagar</button>` : ''}
        </div>
      </div>
    </div>`;
  }

  // lista do mais recente para o mais antigo: os feitos neste navegador, depois os do servidor;
  // no fim (logo acima da caixa de escrever) o "ver mais", que traz os 10 anteriores ali mesmo
  function listaComentariosHtml(postId) {
    const st = carregados.get(postId);
    const itens = [...locaisDoPost(postId)].reverse().concat([...(st?.lista || [])].reverse());
    let fim = '';
    if (st?.carregando) fim = '<p class="comment-loading">Carregando comentários…</p>';
    else if (st?.erro) fim = `<button type="button" class="comment-more" data-mais-comentarios="${postId}">Não deu para carregar. Tentar de novo</button>`;
    else if (st?.mais) fim = `<button type="button" class="comment-more" data-mais-comentarios="${postId}">Ver mais comentários</button>`;
    if (!itens.length && !fim) return '<p class="comment-empty">Ninguém comentou ainda. Os dois lados podem comentar.</p>';
    return itens.map(commentHtml).join('') + fim;
  }

  function refreshListaComentarios(postId) {
    const post = $(`.post[data-post-id="${postId}"]`);
    if (!post) return;
    $('[data-toggle-comments]', post).innerHTML = commentsButton(postId);
    const lista = $('.comment-list', post);
    if (lista) lista.innerHTML = listaComentariosHtml(postId);
  }

  // busca no servidor: os 10 mais recentes ou, com "anteriores", os 10 antes do mais antigo já mostrado
  async function carregarComentarios(postId, anteriores = false) {
    const st = carregados.get(postId) || { lista: [], mais: false };
    if (st.carregando) return;
    Object.assign(st, { carregando: true, erro: false });
    carregados.set(postId, st);
    refreshListaComentarios(postId);
    try {
      const res = await fetch('api/comentarios', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post: postId, antes: anteriores ? st.lista[0]?.id ?? null : null }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.erro);
      data.comentarios.forEach((c) => { c.autor.nome = nomeProprio(c.autor.nome); });
      st.lista = anteriores ? [...data.comentarios, ...st.lista] : data.comentarios;
      st.mais = data.mais;
    } catch {
      st.erro = true;
    }
    st.carregando = false;
    refreshListaComentarios(postId);
  }

  // ao abrir os comentários de um post: busca a 1ª página, se o servidor tiver comentários dele
  function abrirComentarios(postId) {
    if (!carregados.has(postId) && (CONTAGEM[postId]?.total || 0) > 0) carregarComentarios(postId);
  }

  // citar: aparece "Respondendo a Fulano" em cima da caixa (dá para cancelar)
  const citando = new Map(); // postId → { id, nome, texto }
  function mostrarCitacao(form) {
    $('.comment-citando', form)?.remove();
    const cita = citando.get(form.dataset.post);
    if (!cita) return;
    $('.comment-box', form).insertAdjacentHTML('afterbegin', `<div class="comment-citando">
      <span>↩ Respondendo a <b>${esc(cita.nome)}</b>: “${esc(cita.texto)}”</span>
      <button type="button" data-cancelar-cita aria-label="Cancelar citação">×</button>
    </div>`);
  }

  function commentFormHtml(postId) {
    const eu = me();
    if (!eu) {
      return `<p class="comment-cta">Para comentar, garanta sua ${S.item} ${S.emoji}
        <button class="btn btn-gold btn-sm" type="button" data-open-buy>Garantir</button>
        ou uma ${OTHER.item} ${OTHER.emoji} <a href="${esc(poteUrl(OTHER.slug))}">no outro pote</a>.</p>`;
    }
    const sd = DC.sides[eu.side];
    return `<form class="comment-form" data-post="${postId}">
      ${avatarWithSelo(eu, 'comment-avatar')}
      <div class="comment-box">
        <textarea name="texto" rows="3" maxlength="400" aria-label="Seu comentário"
          placeholder="Comentar como ${esc(eu.nome.split(' ')[0])} (${sd.emoji} ${esc(sd.name)})… ou cole um link de vídeo"></textarea>
        <div class="comment-box-foot">
          <small class="comment-hint">${DICA_COMENTARIO}</small>
          <span class="comment-count">0/${COMENTARIO_MAX}</span>
          <button class="comment-send" type="submit" disabled aria-label="Enviar comentário" title="Enviar">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7Z"/></svg>
          </button>
        </div>
      </div>
    </form>`;
  }

  function commentsSectionHtml(postId) {
    return `<div class="comment-list">${listaComentariosHtml(postId)}</div>${commentFormHtml(postId)}`;
  }

  function refreshComments(postId) {
    const post = $(`.post[data-post-id="${postId}"]`);
    if (!post) return;
    $('[data-toggle-comments]', post).innerHTML = commentsButton(postId);
    $('.comments', post).innerHTML = commentsSectionHtml(postId);
  }

  function postHtml(p) {
    const o = byId(p.oliveId);
    const open = openComments.has(p.id);
    return `<article class="post${p.isNew ? ' is-new' : ''}${p.video ? ' has-video' : ''}" data-post-id="${p.id}">
      <div class="post-head">
        ${avatarWithSelo(o, 'post-avatar' + (anelDe(o) ? ' tempo-' + anelDe(o) : ''))}
        <div><a class="name-link" href="${profileUrl(SIDE, o.id)}">${esc(o.nome)}${nivelTag(SIDE, o.id)}</a>${provocadorTag(o.id)}<small>${esc(o.cidade)}/${esc(o.uf)} · ${esc(S.since)} ${fmtDate(o.desde)}</small></div>
      </div>
      ${p.text ? `<p>${esc(p.text)}</p>` : ''}
      ${p.video ? videoBoxHtml('post-video', p.video) : ''}
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
    const posts = postsNaTela();
    $('#feed').innerHTML = posts.map(postHtml).join('')
      || (feed.carregando ? '' : '<p class="comment-empty">Nenhuma publicação ainda.</p>');
    // "Carregar mais": só quando o servidor tem mais (ou para tentar de novo se falhou)
    const mais = $('#feed-more');
    mais.hidden = !feed.mais && !feed.erro && !feed.carregando;
    mais.disabled = feed.carregando;
    mais.textContent = feed.carregando ? 'Carregando…' : feed.erro ? 'Não deu para carregar. Tentar de novo' : 'Carregar mais';
    openComments.forEach(abrirComentarios); // comentários que ficaram abertos: carrega se ainda não veio
  }

  // caixa de comentário: contador, botão enviar só com conteúdo, e link colado vira resposta em vídeo
  $('#feed').addEventListener('input', (e) => {
    const campo = e.target.closest('.comment-form textarea[name="texto"]');
    if (!campo) return;
    const form = campo.closest('.comment-form');
    const { video, text } = extractVideo(campo.value);
    const hint = $('.comment-hint', form);
    hint.classList.toggle('on', !!video);
    hint.textContent = video ? `▶ Resposta em vídeo do ${providerName(video)}${text ? ' + texto' : ''}` : DICA_COMENTARIO;
    const cont = $('.comment-count', form);
    cont.textContent = `${text.length}/${COMENTARIO_MAX}`;
    cont.classList.toggle('over', text.length > COMENTARIO_MAX);
    $('.comment-send', form).disabled = (!text && !video) || text.length > COMENTARIO_MAX;
  });

  // no computador: Enter envia, Shift+Enter pula linha (no celular o Enter pula linha e envia pelo botão)
  $('#feed').addEventListener('keydown', (e) => {
    const campo = e.target.closest('.comment-form textarea[name="texto"]');
    if (!campo || e.key !== 'Enter' || e.shiftKey || e.isComposing || matchMedia('(hover: none)').matches) return;
    e.preventDefault();
    const enviar = $('.comment-send', campo.closest('.comment-form'));
    if (!enviar.disabled) campo.form.requestSubmit(enviar);
  });

  $('#feed').addEventListener('submit', (e) => {
    const form = e.target.closest('.comment-form');
    if (!form) return;
    e.preventDefault();
    const eu = me();
    const { video, text } = extractVideo(form.elements.texto.value); // link de vídeo vira resposta em vídeo
    if (!eu || (!text && !video)) return;
    if (text.length > COMENTARIO_MAX) { toast(`Comentário: até ${COMENTARIO_MAX} caracteres (sem contar o link do vídeo)`); return; }
    const nivelAntes = meuNivel(eu.side);
    myComments.push({
      id: `l${Date.now()}`,
      post: form.dataset.post,
      autor: { id: eu.id, side: eu.side, nome: eu.nome, foto: eu.foto, tipo: eu.tipo, selo: eu.selo },
      texto: text || null,
      video,
      cita: citando.get(form.dataset.post) || null, // guarda o trecho: continua legível mesmo se o original sumir
      data: todayIso(),
    });
    citando.delete(form.dataset.post);
    store.set('comments', myComments);
    provocadoresCache = null; // o ranking do mês pode mudar
    avisaSubida(eu.side, nivelAntes); // comentar também sobe o nível (e o de quem recebeu)
    refreshComments(form.dataset.post);
    renderProvocadores();
    // o novo vai para o topo da lista: rola até ele e dá um destaque, para ver que publicou
    const novo = $(`.post[data-post-id="${form.dataset.post}"] .comment-list .comment`);
    if (novo) {
      novo.scrollIntoView({ behavior: 'smooth', block: 'center' });
      novo.classList.add('destaque');
    }
  });

  $$('.tab').forEach((t) => t.addEventListener('click', () => {
    $$('.tab').forEach((x) => x.classList.toggle('active', x === t));
    if (sort === t.dataset.sort) return;
    sort = t.dataset.sort;
    carregarPosts(true); // outra ordem: busca a 1ª página de novo no servidor
  }));
  $('#feed-more').addEventListener('click', () => carregarPosts());

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
      if (open) {
        abrirComentarios(id);
        $('textarea', box)?.focus({ preventScroll: true });
      }
    }

    // "ver comentários anteriores" (ou tentar de novo, se falhou)
    const mais = e.target.closest('[data-mais-comentarios]');
    if (mais) {
      const id = mais.dataset.maisComentarios;
      carregarComentarios(id, (carregados.get(id)?.lista.length || 0) > 0);
    }

    // citar: guarda quem e o trecho, mostra em cima da caixa e leva o cursor para lá
    const citar = e.target.closest('[data-citar]');
    if (citar) {
      const c = comentarioPorId(citar.dataset.citar);
      const form = $('.comment-form', citar.closest('.post'));
      if (c && form) {
        citando.set(form.dataset.post, { id: c.id, nome: c.autor.nome, texto: trechoDe(c) });
        mostrarCitacao(form);
        form.elements.texto.focus();
      }
    }
    if (e.target.closest('[data-cancelar-cita]')) {
      const form = e.target.closest('.comment-form');
      citando.delete(form.dataset.post);
      mostrarCitacao(form);
    }

    // clicar na citação leva ao comentário original (se ele já estiver na tela)
    const ir = e.target.closest('[data-ir-comentario]');
    if (ir) {
      const alvo = $(`[data-comment-id="${CSS.escape(ir.dataset.irComentario)}"]`);
      if (alvo && !alvo.contains(ir)) {
        alvo.scrollIntoView({ behavior: 'smooth', block: 'center' });
        alvo.classList.remove('destaque');
        requestAnimationFrame(() => alvo.classList.add('destaque'));
      } else {
        toast('Esse comentário é mais antigo: clique em "Ver mais comentários".');
      }
    }

    // apagar (só o dono): 1º clique pede confirmação, 2º apaga — fica o aviso "comentário apagado"
    const apagar = e.target.closest('[data-apagar]');
    if (apagar) {
      const c = myComments.find((x) => x.id === apagar.dataset.apagar);
      if (!c || !souDono(c)) return;
      if (!apagar.classList.contains('confirmar')) {
        apagar.classList.add('confirmar');
        apagar.textContent = 'Apagar mesmo?';
        setTimeout(() => { apagar.classList.remove('confirmar'); apagar.textContent = 'Apagar'; }, 4000);
        return;
      }
      Object.assign(c, { apagado: true, texto: null, video: null, cita: null });
      store.set('comments', myComments);
      provocadoresCache = null; // deixa de contar para provocadores e níveis
      limparTempero();
      refreshListaComentarios(c.post);
      renderProvocadores();
      toast('Comentário apagado.');
    }
    if (e.target.closest('[data-play]')) playVideo(e.target.closest('[data-video]'));
    if (e.target.closest('[data-stop]')) stopVideo(e.target.closest('[data-video]'));
    if (like) {
      const id = like.dataset.like;
      liked.has(id) ? liked.delete(id) : liked.add(id);
      store.set('likes', [...liked]);
      limparTempero(); // curtida recebida conta no nível do autor
      const p = postsNaTela().find((x) => x.id === id);
      like.classList.toggle('liked', liked.has(id));
      like.textContent = `${S.emoji} ${likesOf(p)}`;
    }
    if (share) {
      const p = postsNaTela().find((x) => x.id === share.dataset.sharePost);
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
    if (!eu) { goStep(1); openModal('#buy-modal'); updateTotal(); return; }
    const { video, text } = extractVideo(composerText.value);
    if (!text && !video) return;
    if (text.length > TEXT_MAX) { toast(`Máximo de ${TEXT_MAX} caracteres (sem contar o link)`); return; }
    const nivelAntes = meuNivel(SIDE);
    myPosts.unshift({ id: `${SIDE}-p${Date.now()}`, oliveId: eu.id, text, video, date: todayIso(), likes: 0 });
    store.set(`${SIDE}_posts`, myPosts);
    if (video) refreshOlive(eu); // aparece o selo de play no item
    composerText.value = '';
    updateComposerPreview();
    $$('.tab').forEach((x) => x.classList.toggle('active', x.dataset.sort === 'recentes'));
    const subiu = avisaSubida(SIDE, nivelAntes);
    // o post novo aparece no topo; se estava em outra aba, volta para "Recentes" (busca de novo no servidor)
    if (sort !== 'recentes') {
      sort = 'recentes';
      carregarPosts(true);
    } else {
      renderFeed();
    }
    if (!subiu) toast('Publicado no mural!');
  });

  // ranking: recém-chegados ao pote (inclui quem acabou de comprar neste navegador)
  function quandoEntrou(iso) {
    const d = -diasAte(iso);
    return d <= 0 ? 'entrou hoje' : d === 1 ? 'entrou ontem' : `entrou há ${d} dias`;
  }

  function renderNewest() {
    const box = $('#newest');
    if (!box) return;
    const recentes = [...noPote()]
      .sort((a, b) => b.desde.localeCompare(a.desde) || b.id - a.id)
      .slice(0, 3);
    box.innerHTML = recentes.map((o) => `<li>
      <a href="${profileUrl(SIDE, o.id)}">
        <span class="num">${numero(o.id)}</span>
        ${avatarWithSelo(o, 'post-avatar')}
        <span><b>${esc(o.nome)}${nivelTag(SIDE, o.id)}</b><small>${esc(o.cidade)}/${esc(o.uf)} · <span class="when">${quandoEntrou(o.desde)}</span></small></span>
      </a>
    </li>`).join('');
  }

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
      ${!DC.logado && !voted ? `<p class="poll-login">🔒 Só quem está logado pode votar.</p>` : ''}
      <p class="poll-foot">${p.total !== null ? `${p.total.toLocaleString('pt-BR')} ${p.total === 1 ? 'voto' : 'votos'}` : 'Resultado oculto até o fim'}${fim}</p>`;
  }

  $('#poll')?.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-vote]');
    if (!btn || DC.enquete.meuVoto !== null) return;
    if (!DC.logado) { location.href = DC.loginUrl; return; } // volta para a enquete depois de entrar
    $$('[data-vote]').forEach((b) => { b.disabled = true; });
    try {
      const res = await fetch('api/enquete', {
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

  // ---------- mapa da guerra dos potes (partials/mapa.php) ----------
  // Cada estado com a cor de quem tem mais itens lá; o mesmo mapa nos dois potes, textos de S.mapa.
  const MAPA_NEUTRO = '#3b3a31';
  const preencher = (tpl, vars) => tpl.replace(/\{(\w+)\}/g, (_, k) => vars[k] ?? '');
  const qtdItens = (n, sd = S) => `${n} ${n === 1 ? sd.item : sd.items}`;

  // do neutro (t = 0) até a cor cheia (t = 1)
  function misturar(hex, t) {
    const rgb = (h) => [1, 3, 5].map((i) => parseInt(h.slice(i, i + 2), 16));
    const a = rgb(MAPA_NEUTRO);
    const b = rgb(hex);
    return '#' + a.map((v, i) => Math.round(v + (b[i] - v) * t).toString(16).padStart(2, '0')).join('');
  }

  // uf → { esquerda: n, direita: n }: dados do pote + compras deste navegador
  function contagemMapa() {
    const c = {};
    const soma = (uf, s, n) => { (c[uf] ??= Object.fromEntries(DUEL_ORDER.map((x) => [x, 0])))[s] += n; };
    DUEL_ORDER.forEach((s) => {
      Object.entries(DC.mapa?.contagem?.[s] || {}).forEach(([uf, n]) => soma(uf, s, n));
      store.get(`${s}_mine`, []).filter((o) => !vencido(o)).forEach((o) => soma(o.uf, s, 1));
    });
    return c;
  }

  // m = meus, d = deles (do ponto de vista deste pote)
  function estadoMapa(c, uf) {
    const m = c[uf]?.[SIDE] || 0;
    const d = c[uf]?.[S.other] || 0;
    return { m, d, dono: m > d ? SIDE : d > m ? S.other : m ? 'empate' : null };
  }

  function textoEstado(uf, e) {
    const M = S.mapa;
    if (e.dono === SIDE) return preencher(M.estado_meu, { dif: qtdItens(e.m - e.d) });
    if (e.dono === S.other) return preencher(M.estado_deles, { falta: e.d - e.m + 1, itens: e.d - e.m + 1 === 1 ? S.item : S.items });
    return e.dono === 'empate' ? M.estado_empate : M.estado_vazio;
  }

  // ---------- quem manda no estado (👑) ----------
  // A pessoa do pote que domina o estado com mais valor em itens ali (item mais caro pesa mais).
  // Empate entre os potes ou entre os dois primeiros: ninguém manda.
  const centavos = (tipo, s) => Math.round(DC.sides[s].types[tipo].price * 100);

  function candidatosUf(uf, s) {
    const lista = (DC.mapa?.reis?.[s]?.[uf] || []).map((r) => ({ ...r, side: s }));
    const meus = store.get(`${s}_mine`, []).filter((o) => !vencido(o) && o.uf === uf);
    if (meus.length) {
      const itens = {};
      meus.forEach((o) => { itens[o.tipo] = (itens[o.tipo] || 0) + 1; });
      const cara = meus[0];
      lista.push({ dono: 'eu', side: s, id: cara.id, nome: nomeProprio(cara.nome), foto: cara.foto, tipo: cara.tipo, selo: cara.selo,
        valor: meus.reduce((v, o) => v + centavos(o.tipo, s), 0), itens });
    }
    return lista.sort((a, b) => b.valor - a.valor);
  }

  function reiDe(uf, e) {
    if (!e.dono || e.dono === 'empate') return null;
    const [rei, vice] = candidatosUf(uf, e.dono);
    if (!rei || (vice && vice.valor === rei.valor)) return null;
    return { rei };
  }

  function reiHtml(uf, r) {
    const { rei } = r;
    const sd = DC.sides[rei.side];
    const itens = Object.entries(rei.itens).map(([tipo, n]) =>
      `<span class="rei-item">${itemSvg(tipo, rei.side)}${n}× ${esc(typeLabel(tipo, rei.side))}</span>`).join('');
    // o recado depende de quem olha: a coroa é minha, do meu time ou do outro pote.
    // Nunca mostra quanto alguém gastou: a diferença vira "mais N itens" (do tipo mais barato).
    let recado;
    if (rei.dono === 'eu' && rei.side === SIDE) {
      recado = S.mapa.rei_eu;
    } else if (rei.side === SIDE) {
      const meu = candidatosUf(uf, SIDE).find((c) => c.dono === 'eu')?.valor || 0;
      const barato = Object.values(S.types).reduce((a, b) => (b.price < a.price ? b : a));
      const qtd = Math.max(1, Math.ceil((rei.valor - meu + 1) / Math.round(barato.price * 100)));
      recado = preencher(S.mapa.rei_time, { falta: `${qtd} ${qtd > 1 ? S.items : S.item} ${barato.label}` });
    } else {
      recado = S.mapa.rei_deles;
    }
    return `<div class="mapa-rei" style="${themeVars(sd)}">
        <span class="rei-coroa" aria-hidden="true">👑</span>
        ${avatarWithSelo({ ...rei, nome: rei.nome || '?' }, 'rei-avatar')}
        <div class="rei-info">
          <small>Quem manda em ${uf}</small>
          <a class="name-link" href="${profileUrl(rei.side, rei.id)}">${esc(rei.dono === 'eu' ? `${rei.nome} (você)` : rei.nome)}${nivelTag(rei.side, rei.id)}</a>
          <span class="rei-itens">${itens}</span>
        </div>
      </div>
      <p class="rei-recado">${esc(recado)}</p>`;
  }

  let mapaAtivo = null;
  function mostrarEstado(uf) {
    const box = $('#mapa-estado');
    const path = $(`#mapa .uf[data-uf="${uf}"]`);
    if (!box || !path) return;
    mapaAtivo = uf;
    $$('#mapa .uf.is-ativo').forEach((p) => p.classList.remove('is-ativo'));
    path.classList.add('is-ativo');
    path.parentNode.appendChild(path); // borda por cima das vizinhas
    const e = estadoMapa(contagemMapa(), uf);
    const r = reiDe(uf, e);
    const cont =(s) => `<span style="color:${DC.sides[s].mapa.cor}">${DC.sides[s].emoji} <b>${(e[s === SIDE ? 'm' : 'd']).toLocaleString('pt-BR')}</b></span>`;
    box.innerHTML = `<div class="mapa-estado-head"><b>${esc(path.dataset.nome)}</b><span class="mapa-estado-placar">${DUEL_ORDER.map(cont).join(' × ')}</span></div>
      <p>${esc(textoEstado(uf, e))}</p>
      ${r ? reiHtml(uf, r) : ''}
      ${e.dono === SIDE && r?.rei.dono === 'eu' ? '' : `<button class="btn btn-gold btn-sm" type="button" data-conquistar="${uf}">${e.dono === SIDE ? `Quero a coroa de ${uf} 👑` : `Conquistar ${uf} ${S.emoji}`}</button>`}`;
  }

  // onde falta menos para virar o estado (os disputados primeiro; terra de ninguém completa a lista)
  function estadosPorUmFio(c, max = 3) {
    const todos = $$('#mapa .uf').map((p) => ({ uf: p.dataset.uf, nome: p.dataset.nome, ...estadoMapa(c, p.dataset.uf) }))
      .filter((e) => e.dono !== SIDE)
      .map((e) => ({ ...e, falta: e.d - e.m + 1 }))
      .sort((a, b) => a.falta - b.falta || (b.m + b.d) - (a.m + a.d) || a.nome.localeCompare(b.nome));
    const disputados = todos.filter((e) => e.dono).slice(0, max - 1);
    return [...disputados, ...todos.filter((e) => !e.dono)].slice(0, max);
  }

  function renderMapa() {
    const box = $('#mapa');
    if (!box) return;
    const c = contagemMapa();
    const estados = Object.fromEntries(DUEL_ORDER.map((s) => [s, 0]));
    const totais = Object.fromEntries(DUEL_ORDER.map((s) => [s, 0]));
    $$('.uf', box).forEach((p) => {
      const e = estadoMapa(c, p.dataset.uf);
      DUEL_ORDER.forEach((s) => { totais[s] += c[p.dataset.uf]?.[s] || 0; });
      let fill = MAPA_NEUTRO;
      if (e.dono === 'empate') fill = 'url(#mapa-empate)';
      else if (e.dono) {
        estados[e.dono]++;
        // vantagem apertada = cor mais fraca; só um lado no estado = cor cheia
        fill = misturar(DC.sides[e.dono].mapa.cor, Math.max(e.m, e.d) / (e.m + e.d));
      }
      p.style.fill = fill;
      p.classList.toggle('is-meu', e.dono === SIDE);
      // 👑 acima da sigla quando alguém manda no estado (a minha coroa brilha)
      const rotulo = $(`.mapa-rotulos text[data-uf="${p.dataset.uf}"]`, box);
      const r = reiDe(p.dataset.uf, e);
      let coroa = $(`.mapa-coroas [data-uf="${p.dataset.uf}"]`, box);
      if (r && rotulo) {
        if (!coroa) {
          coroa = document.createElementNS(SVG, 'text');
          coroa.dataset.uf = p.dataset.uf;
          coroa.setAttribute('x', rotulo.getAttribute('x'));
          coroa.setAttribute('y', Number(rotulo.getAttribute('y')) - 22);
          coroa.textContent = '👑';
          $('.mapa-coroas', box).appendChild(coroa);
        }
        coroa.classList.toggle('is-minha', r.rei.dono === 'eu');
      } else coroa?.remove();
    });

    const meus = estados[SIDE];
    const deles = estados[S.other];
    $('#mapa-manchete').textContent = preencher(meus > deles ? S.mapa.ganhando : meus < deles ? S.mapa.perdendo : S.mapa.empate, { meus, deles });

    const total = Math.max(1, estados.esquerda + estados.direita);
    $('#mapa-placar').innerHTML = `
      <div class="mapa-placar-nums">${DUEL_ORDER.map((s) => `<div class="${s === SIDE ? 'is-meu' : ''}" style="--cor:${DC.sides[s].mapa.cor}">
          <b>${estados[s]}</b><small>${DC.sides[s].emoji} ${estados[s] === 1 ? 'estado' : 'estados'}</small><span>${qtdItens(totais[s], DC.sides[s])}</span>
        </div>`).join('<em>×</em>')}</div>
      <div class="mapa-placar-barra">${DUEL_ORDER.map((s) => `<i style="width:${((estados[s] / total) * 100).toFixed(1)}%;background:${DC.sides[s].mapa.cor}"></i>`).join('')}</div>`;

    $('#mapa-fio').innerHTML = estadosPorUmFio(c).map((e) => `<li>
        <span class="fio-uf">${e.uf}</span>
        <span class="fio-nome"><b>${esc(e.nome)}</b><small>${e.dono ? `faltam ${qtdItens(e.falta)}` : 'ninguém chegou: 1 já leva'}</small></span>
        <button class="btn btn-ghost btn-sm" type="button" data-conquistar="${e.uf}">Conquistar</button>
      </li>`).join('') || `<li class="prov-empty">Todos os estados são seus. Por enquanto. ${S.emoji}</li>`;

    if (mapaAtivo) mostrarEstado(mapaAtivo);
    else $('#mapa-estado').innerHTML = `<p class="mapa-dica">Passe o mouse (ou toque) num estado para ver quem manda lá.</p>`;
  }

  if ($('#mapa')) {
    const svg = $('#mapa .mapa');
    // só redesenha quando muda de estado (trazer o estado para frente dispara o mouseover de novo)
    const escolher = (e) => { const p = e.target.closest('.uf'); if (p && p.dataset.uf !== mapaAtivo) mostrarEstado(p.dataset.uf); };
    svg.addEventListener('mouseover', escolher);
    svg.addEventListener('focusin', escolher);
    svg.addEventListener('click', escolher);

    // "Conquistar SP": abre a compra com o estado já escolhido
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-conquistar]');
      if (!btn) return;
      goStep(1);
      openModal('#buy-modal');
      buyForm.elements.uf.value = btn.dataset.conquistar;
      updateTotal();
      buyForm.elements.nome.focus();
    });

    // chamar reforço: compartilha citando o estado mais disputado
    $('#mapa-indicar').addEventListener('click', () => {
      const alvo = estadosPorUmFio(contagemMapa())[0];
      const text = preencher(S.mapa.indicar, { uf: alvo ? alvo.nome : 'o Brasil' });
      const url = new URL(`${poteUrl(SIDE)}#mapa${alvo ? '-' + alvo.uf : ''}`, location.href).href; // abre já no estado
      if (navigator.share) navigator.share({ title: S.name, text, url }).catch(() => {});
      else window.open(`https://wa.me/?text=${encodeURIComponent(text + url)}`, '_blank', 'noopener');
    });
  }

  // links cifrados das compras deste navegador (o servidor gera; ficam guardados) — depois redesenha os links
  let pedindoLinks = null;
  function garantirLinksLocais() {
    const faltam = Object.keys(DC.sides)
      .flatMap((s) => store.get(`${s}_mine`, []).map((o) => ({ lado: s, id: o.id })))
      .filter((x) => !linkPerfilExato(x.lado, x.id));
    if (!faltam.length) return Promise.resolve();
    pedindoLinks ??= fetch('api/link', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ itens: faltam }),
    })
      .then((res) => res.json())
      .then(({ links }) => {
        Object.entries(links || {}).forEach(([s, porId]) => Object.entries(porId).forEach(([id, u]) => { linksLocais[`${s}:${id}`] = u; }));
        store.set('links', linksLocais);
        renderFeed();
        renderNewest();
        renderProvocadores();
        updateMyProfileLink();
      })
      .catch(() => {}) // sem servidor: os links desses itens levam ao pote
      .finally(() => { pedindoLinks = null; });
    return pedindoLinks;
  }

  // ---------- perfil (perfil.php) ----------

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

  // nível no perfil: pontos, barra até o próximo, de onde vêm os pontos e (para a própria pessoa) o que falta
  function nivelCardHtml(o, isMine) {
    const t = tempero(SIDE, o.id);
    if (!t.n) return '';
    const nv = nivelInfo(SIDE, t.n);
    const topo = t.n >= TOPO;
    const de = R.faixas[t.n - 1];
    const pct = topo ? 100 : Math.min(100, Math.max(3, ((t.p.total - de) / (R.faixas[t.n] - de)) * 100));
    let prox = `Nível máximo do pote. ${isMine ? 'Você' : esc(o.nome.split(' ')[0])} está no topo!`;
    if (!topo) {
      const pn = nivelInfo(SIDE, t.n + 1);
      const falta = faltaPara(t.p, t.n + 1);
      // já tem os pontos, mas o topo exige também compra
      prox = t.p.total >= R.faixas[t.n]
        ? `O nível <b>${esc(pn.nome)}</b> ${pn.icone} exige também ${money(R.topo_compra / R.por_real)} em ${S.items} no pote: faltam ${money(falta / R.por_real)}.`
        : `Faltam <b>${fmtPontos(SIDE, falta)}</b> para o nível <b>${esc(pn.nome)}</b> ${pn.icone}.`;
      if (isMine) {
        prox += ` Com mais ${quantasFaltam(falta)} você chega lá${t.n + 1 < TOPO ? ' — ou publicando e comentando no mural' : ''}.`;
      }
    }
    const parte = (label, v) => `<span>${label} <b>${fmtPontos(SIDE, v)}</b></span>`;
    return `<div class="profile-nivel${topo ? ' is-topo' : ''}">
      <div class="profile-nivel-head">
        <span class="nivel-icone" aria-hidden="true">${nv.icone}</span>
        <span class="nivel-nome"><small>${esc(S.tempero.titulo)}</small><b>${esc(nv.nome)}</b></span>
        <span class="nivel-pontos">${fmtPontos(SIDE, t.p.total)}</span>
      </div>
      <span class="nivel-bar"><i style="width:${pct.toFixed(0)}%"></i></span>
      <p class="nivel-prox">${prox}</p>
      <p class="nivel-partes">${parte(`${S.Item}s no pote`, t.p.compra)}${parte('Tempo no pote', t.p.tempo)}${parte('Participação', t.p.participacao)}</p>
    </div>`;
  }

  // perto de vencer: "se esta vencer, você cai para…"
  function nivelPerdaHtml(o) {
    const agora = meuNivel(SIDE);
    const sem = nivelDe(pontosDe(fatosMeus(SIDE, o.id)));
    if (sem >= agora) return '';
    return `<span class="nivel-perda">⚠ Se ela vencer, você ${sem ? `cai para o nível <b>${esc(nivelInfo(SIDE, sem).nome)}</b> ${nivelInfo(SIDE, sem).icone}` : 'perde seu nível'}.</span>`;
  }

  function renderProfile() {
    const card = $('#profile-card');
    // "Ver meu perfil" (perfil com meu=1): vai para o item de quem comprou neste navegador
    if (DC.meu) {
      const eu = me();
      if (eu) {
        const ir = () => { const u = linkPerfilExato(eu.side, eu.id); if (u) location.replace(u); };
        linkPerfilExato(eu.side, eu.id) ? ir() : garantirLinksLocais().then(ir); // link ainda não pedido ao servidor
        return;
      }
      card.innerHTML = `<div class="profile-missing">
        <h1>Seu perfil ainda está vazio</h1>
        <p>O perfil aparece assim que você tem uma ${S.item} no pote: com certificado, nível, publicações e comentários.</p>
        <button class="btn btn-gold" type="button" data-open-buy>Quero minha ${S.item} ${S.emoji}</button>
      </div>`;
      $$('.profile-side, .profile-stats-wrap, #mural, .profile-page > .section.alt').forEach((el) => { el.hidden = true; });
      return;
    }
    const o = byId(DC.profileId);
    if (!o) {
      card.innerHTML = `<div class="profile-missing">
        <h1>Perfil não encontrado</h1>
        <p>Essa ${S.item} não está no pote (ou o link está errado).</p>
        <a class="btn btn-gold" href="${esc(poteUrl(SIDE))}">Voltar ao pote ${S.emoji}</a>
      </div>`;
      $$('.profile-side, .profile-stats-wrap, #mural, .profile-page > .section.alt').forEach((el) => { el.hidden = true; });
      return;
    }

    const isMine = mine.some((m) => m.id === o.id);
    const posts = allPosts();
    const received = posts.reduce((n, p) => n + totalComentarios(p.id), 0);
    const visitors = posts.reduce((n, p) => n + visitantesDoPost(p.id), 0);
    // o que a pessoa comentou (servidor: DC.comentarios.feitos) + o que foi feito neste navegador; apagados não entram
    const made = [...(DC.comentarios?.feitos || []), ...comentariosAtivos()].filter((c) => c.autor.side === SIDE && c.autor.id === o.id);
    const madeOther = made.filter((c) => !c.post.startsWith(SIDE + '-'));
    const likes = posts.reduce((n, p) => n + likesOf(p), 0);
    const vale = validade(o);
    const venceu = vencido(o);
    const faltam = diasAte(vale);
    const anel = anelDe(o);
    const preco = money(S.types[o.tipo].price);

    const badges = [
      isMine && ['voce', '★ Este é você'],
      venceu ? ['vencido', '⏳ Fora do pote (venceu)'] : [`tempo-${anel || 'normal'}`, tempoTexto(o)],
      provocadores()[0]?.o.id === o.id && ['provoc', `🔥 Provocador(a) do mês de ${mesNome()}`],
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
        <div class="profile-avatar">${avatarWithSelo(o, 'profile-photo' + (anel && !venceu ? ' tempo-' + anel : ''))}<span class="profile-item" title="${esc(S.Item)} ${esc(typeLabel(o.tipo))}">${itemSvg(o.tipo, SIDE, Math.min(scaleOf(o), 1.2))}</span></div>
        <div class="profile-id">
          <h1>${esc(o.nome)}${nivelTag(SIDE, o.id)}</h1>
          <p class="profile-meta">📍 ${esc(o.cidade)}/${esc(o.uf)} · ${esc(S.Item)} ${esc(typeLabel(o.tipo))}</p>
        </div>
        <div class="profile-since">
          <small>${esc(S.cert_since)}</small>
          <b>${fmtDate(o.desde)}</b>
        </div>
        <blockquote class="profile-quote">“${esc(o.frase)}”</blockquote>
        ${badges.length ? `<ul class="profile-badges">${badges.map(([k, t]) => `<li class="badge-${k}">${esc(t)}</li>`).join('')}</ul>` : ''}
        ${nivelCardHtml(o, isMine)}
        ${isMine ? (venceu
          ? `<div class="profile-validity is-expired">
              <span>⏳ Sua ${S.item} venceu em <b>${fmtDate(vale)}</b> e saiu do pote.</span>
              <span>Renovando agora ela volta, mas a data <b>recomeça</b>: “${esc(S.cert_since)} ${fmtDate(todayIso())}”.</span>
              <button class="btn btn-gold btn-sm" type="button" data-renew="${o.id}">Voltar ao pote · ${preco}</button>
            </div>`
          : `<div class="profile-validity${faltam <= 30 ? ' is-soon' : ''}">
              <span>Sua ${S.item} fica no pote até <b>${fmtDate(vale)}</b>${faltam <= 30 ? ` — <b>faltam ${faltam} ${faltam === 1 ? 'dia' : 'dias'}</b>` : ''}.</span>
              <span class="validity-bar"><i style="width:${Math.min(100, Math.max(3, (1 - faltam / 365) * 100)).toFixed(0)}%"></i></span>
              <span class="validity-rule">Renovando antes de vencer, continua <b>“${esc(S.cert_since)} ${fmtDate(o.desde)}”</b>${anel ? ` e com o anel de ${anel}` : ''}. Se vencer, a data recomeça do zero.</span>
              ${faltam <= 30 ? nivelPerdaHtml(o) : ''}
              <button class="btn btn-ghost btn-sm" type="button" data-renew="${o.id}">Renovar por +1 ano · ${preco}</button>
            </div>`) : ''}
        <div class="profile-actions">
          <button class="btn btn-gold" type="button" data-view="${o.id}">Ver certificado</button>
          <button class="btn btn-ghost" type="button" data-share-profile>Compartilhar perfil</button>
          ${isMine ? `<button class="btn btn-ghost" type="button" data-open-buy>+ Mais ${S.items}</button>` : ''}
          ${isMine && DC.logado ? `<form class="profile-logout" method="post" action="sair">
              <input type="hidden" name="csrf" value="${esc(DC.csrf)}">
              <input type="hidden" name="r" value="${esc(DC.pagina)}">
              <button class="btn btn-link">Sair da conta</button>
            </form>` : ''}
        </div>
      </div>`;

    $('#profile-stats').innerHTML = [
      [S.emoji, likes, 'curtidas recebidas'],
      ['📝', posts.length, posts.length === 1 ? 'publicação' : 'publicações'],
      ['💬', received, 'comentários recebidos'],
      [OTHER.emoji, visitors, `comentários de quem é ${OTHER.name}`],
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
          ${c.texto ? `<p>${esc(c.texto)}</p>` : ''}${c.video ? `<span class="made-video">🎬 Respondeu com vídeo do ${providerName(c.video)}</span>` : ''}
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

  // ---------- renovação (mesma regra de renovar_item() no PHP) ----------
  // Em dia: +1 ano a partir do vencimento, mantendo o "desde". Vencido: recomeça hoje.
  let renewing = null;
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-renew]');
    if (!btn) return;
    const o = mine.find((m) => m.id === Number(btn.dataset.renew));
    if (!o) return;
    renewing = o;
    const emDia = !vencido(o);
    const novaValidade = emDia ? addYear(validade(o)) : addYear(todayIso());
    $('#renew-body').innerHTML = `
      <div class="renew-item">${itemSvg(o.tipo)}<span><b>${esc(o.nome)}</b><small>${esc(S.Item)} ${esc(typeLabel(o.tipo))} · ${numero(o.id)}</small></span></div>
      ${emDia
        ? `<p class="renew-ok">✓ Continua <b>“${esc(S.cert_since)} ${fmtDate(o.desde)}”</b>${anelDe(o) ? ` e com o anel de ${anelDe(o)}` : ''}.<br>Nova validade: <b>${fmtDate(novaValidade)}</b>.</p>`
        : `<p class="renew-warn">⚠ Venceu em ${fmtDate(validade(o))}, então a data recomeça: <b>“${esc(S.cert_since)} ${fmtDate(todayIso())}”</b>.<br>Validade: <b>${fmtDate(novaValidade)}</b>.</p>`}`;
    $('#renew-price').innerHTML = `${money(S.types[o.tipo].price)}<small>/ano</small>`;
    renderFakeQr('#renew-qr');
    openModal('#renew-modal');
  });

  $('#renew-pay')?.addEventListener('click', () => {
    const o = renewing;
    if (!o) return;
    const manteve = !vencido(o);
    if (manteve) {
      o.valido_ate = addYear(validade(o));
    } else {
      o.desde = todayIso();
      o.valido_ate = addYear(todayIso());
    }
    store.set(`${SIDE}_mine`, mine);
    limparTempero(); // item vencido que volta ao pote devolve o nível
    closeModals();
    toast(manteve ? `Renovada! Continua desde ${fmtDate(o.desde)}.` : `De volta ao pote! Agora desde ${fmtDate(o.desde)}.`);
    if ($('#jar')) renderJar();
    updateStats();
    renderFeed();
    renderNewest();
    renderMapa(); // item vencido que volta pinta o estado de novo
    if (DC.profileId !== undefined) renderProfile();
  });

  document.addEventListener('click', async (e) => {
    if (!e.target.closest('[data-share-profile]')) return;
    const o = byId(DC.profileId);
    const url = oliveUrl(o);
    const text = `${S.emoji} ${o.nome} · ${S.cert_since.toLowerCase()} ${fmtDate(o.desde)}`;
    if (navigator.share) { navigator.share({ title: S.name, text, url }).catch(() => {}); return; }
    try { await navigator.clipboard.writeText(url); toast('Link do perfil copiado!'); } catch { toast(url); }
  });

  // botão "Meu perfil" no topo (qualquer item comprado, em qualquer pote)
  // logado: o botão "Meu perfil" (canto direito) é o menu da conta; o link para o perfil fica dentro dele
  function updateMyProfileLink() {
    const eu = me();
    const menu = $('#my-profile-menu');
    const exato = eu && linkPerfilExato(eu.side, eu.id);
    if (exato && menu) menu.href = exato; // sem item (ou sem link ainda), fica o "meu perfil" do servidor
    // o botão da conta mostra a foto da pessoa (a do item comprado), no lugar da inicial
    const ini = $('.user-menu summary .user-initial');
    if (eu?.foto && ini) ini.outerHTML = avatarHtml(eu, 'user-initial');
  }

  // menus do topo (conta e sanduíche): fecham ao tocar fora, num link ou com Esc; só um aberto por vez
  const menusTopo = $$('.topo-menu');
  document.addEventListener('click', (e) => {
    menusTopo.forEach((m) => { if (m.open && (!m.contains(e.target) || e.target.closest('.topo-menu-box a'))) m.open = false; });
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') menusTopo.forEach((m) => { m.open = false; }); });
  menusTopo.forEach((m) => m.addEventListener('toggle', () => {
    if (m.open) menusTopo.forEach((o) => { if (o !== m) o.open = false; });
  }));

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
  renderProvocadores();
  renderNewest();
  renderMapa();
  garantirLinksLocais(); // links cifrados das compras feitas neste navegador
  // link para um estado: pote?c=…#mapa-SP (usado no "Chamar reforço")
  const hashUf = location.hash.match(/^#mapa-([A-Z]{2})$/);
  if (hashUf && $(`#mapa .uf[data-uf="${hashUf[1]}"]`)) {
    mostrarEstado(hashUf[1]);
    $('#mapa').scrollIntoView();
  }
  if (DC.profileId !== undefined) renderProfile();

  // link compartilhado: pote?c=…#azeitona-97 abre direto o certificado
  const m = location.hash.match(new RegExp(`^#${S.item}-(\\d+)$`));
  if (m) openCert(byId(Number(m[1])));
})();
