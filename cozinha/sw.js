// Service worker do app do painel (PWA "Cozinha", escopo /cozinha/). Separado do sw.js do site:
// por ter o escopo mais específico, é ele quem cuida do painel.
// - Nada é guardado em cache: o painel sempre vem da rede (pagamentos e logs mudam o tempo todo).
// - Sem internet: mostra uma página simples de "sem conexão" em vez do erro do navegador.
// Mudou este arquivo? Troque a VERSAO para os aparelhos pegarem a nova versão.
const VERSAO = 'cozinha-v1';

const SEM_REDE = `<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>Sem conexão · Cozinha</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#121212;color:#f1ede6;font:16px system-ui,sans-serif;text-align:center;padding:16px}
button{margin-top:16px;padding:.7em 1.4em;border:0;border-radius:999px;background:#c9a227;color:#231a00;font-weight:700;font-size:1rem}</style></head>
<body><div><p style="font-size:3rem;margin:0">📡</p><h1 style="font-size:1.4rem">Sem conexão</h1>
<p style="color:#a8a29a">O painel precisa de internet. Confira a conexão e tente de novo.</p>
<button onclick="location.reload()">Tentar de novo</button></div></body></html>`;

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));

self.addEventListener('fetch', (e) => {
  if (e.request.mode !== 'navigate') return;
  e.respondWith(fetch(e.request).catch(() => new Response(SEM_REDE, {
    headers: { 'Content-Type': 'text/html; charset=utf-8', 'X-Versao': VERSAO },
  })));
});
