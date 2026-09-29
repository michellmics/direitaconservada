// Service worker do app (PWA): permite instalar e mostra uma página própria quando estiver sem internet.
// - Páginas: sempre da rede (o conteúdo é pessoal e muda o tempo todo); sem rede → offline.html.
// - CSS, JS, imagens e fontes: do cache na hora e atualiza em segundo plano (o ?v= muda quando o arquivo muda).
// - API, painel (/cozinha/) e qualquer coisa que não seja GET: nunca passam pelo cache.
// Mudou este arquivo? Troque a VERSAO para os aparelhos pegarem a nova versão.
const VERSAO = 'pote-v1';
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
