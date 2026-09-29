// Service worker do app (PWA): permite instalar e mostra uma página própria quando estiver sem internet.
// - Páginas: sempre da rede (o conteúdo é pessoal e muda o tempo todo); sem rede → offline.html.
// - CSS, JS, imagens e fontes: do cache na hora e atualiza em segundo plano (o ?v= muda quando o arquivo muda).
// - API, painel (/cozinha/) e qualquer coisa que não seja GET: nunca passam pelo cache.
// Mudou este arquivo? Troque a VERSAO para os aparelhos pegarem a nova versão.
const VERSAO = 'pote-v2';
const OFFLINE = 'offline.html';
const PRECACHE = [OFFLINE, 'assets/img/icon-192.png', 'assets/img/logo-vs.png'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSAO).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((nomes) => Promise.all(nomes.filter((n) => n !== VERSAO).map((n) => caches.delete(n))))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  const escopo = new URL(self.registration.scope);
  const mesmoSite = url.origin === escopo.origin;
  const caminho = url.pathname.slice(escopo.pathname.length);
  if (mesmoSite && (caminho.startsWith('api/') || caminho.startsWith('cozinha/'))) return;

  // páginas: rede; sem internet, a página "você está sem conexão"
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
    return;
  }

  // arquivos estáticos do site e fontes do Google: cache primeiro, atualizando por trás
  const estatico = (mesmoSite && /\.(css|js|png|jpg|jpeg|svg|webp|woff2?)$/i.test(url.pathname))
    || url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com';
  if (!estatico) return;
  e.respondWith(
    caches.open(VERSAO).then((cache) => cache.match(req).then((guardado) => {
      const daRede = fetch(req).then((res) => {
        if (res.ok || res.type === 'opaque') cache.put(req, res.clone());
        return res;
      }).catch(() => guardado);
      return guardado || daRede;
    })),
  );
});

// ---------- notificações (Web Push, includes/push.php) ----------
// O push chega sem conteúdo: o service worker pergunta a api/push qual é o aviso deste aparelho e mostra.
// Sem rede na hora, mostra um aviso genérico (o navegador exige mostrar algo a cada push).
const api = (acao, dados) => fetch(new URL('api/push', self.registration.scope), {
  method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ acao, ...dados }),
});

self.addEventListener('push', (e) => {
  e.waitUntil((async () => {
    let a = { titulo: 'Pote Político', texto: 'Tem novidade no pote. Venha ver!', url: './', aviso: 0 };
    try {
      const inscricao = await self.registration.pushManager.getSubscription();
      const r = await api('ver', { endpoint: inscricao?.endpoint || '' });
      if (r.ok) a = await r.json();
    } catch { /* fica o aviso genérico */ }
    await self.registration.showNotification(a.titulo, {
      body: a.texto,
      icon: 'assets/img/icon-192.png',
      badge: 'assets/img/favicon-32.png',
      tag: 'pote-aviso', // um aviso novo substitui o anterior (não empilha)
      data: { url: new URL(a.url, self.registration.scope).href, aviso: a.aviso },
    });
  })());
});

self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const { url, aviso } = e.notification.data || {};
  e.waitUntil((async () => {
    if (aviso) api('clique', { aviso }).catch(() => {});
    const abertas = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    const aba = abertas.find((c) => c.url.startsWith(self.registration.scope));
    if (aba) { await aba.focus(); return aba.navigate(url).catch(() => self.clients.openWindow(url)); }
    return self.clients.openWindow(url || self.registration.scope);
  })());
});

// o navegador trocou a inscrição sozinho (raro): inscreve de novo com a mesma chave
self.addEventListener('pushsubscriptionchange', (e) => {
  e.waitUntil((async () => {
    const r = await fetch(new URL('api/push', self.registration.scope));
    const { chave } = await r.json();
    const b = atob(chave.replace(/-/g, '+').replace(/_/g, '/'));
    const nova = await self.registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: Uint8Array.from(b, (c) => c.charCodeAt(0)) });
    await api('inscrever', { endpoint: nova.endpoint });
  })().catch(() => {}));
});
