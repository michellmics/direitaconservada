// Contador de visitas (api/visita.php): conta a página vista e avisa "ainda estou aqui" a cada 30 s
// enquanto a aba está visível (é isso que alimenta o "online agora" do painel → Visitas).
(() => {
  'use strict';
  const eu = document.currentScript;
  const api = eu.src.replace(/assets\/js\/visitas\.js.*$/, 'api/visita');
  const base = { p: location.pathname.replace(/\.php$/, '').split('/').pop(), l: eu.dataset.lado || '' };
  const enviar = (t, extra = {}) => fetch(api, {
    method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', keepalive: true,
    body: JSON.stringify({ t, ...base, ...extra }),
  }).catch(() => {});

  enviar('ver', { r: document.referrer, u: new URLSearchParams(location.search).get('utm_source') || '' });

  let timer = null;
  const pingar = () => {
    clearInterval(timer);
    timer = document.visibilityState === 'visible' ? setInterval(() => enviar('ping'), 30000) : null;
  };
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') enviar('ping');
    pingar();
  });
  window.addEventListener('pagehide', () => {
    navigator.sendBeacon?.(api, new Blob([JSON.stringify({ t: 'sai' })], { type: 'application/json' }));
  });
  window.addEventListener('pageshow', (e) => { if (e.persisted) enviar('ping'); });
  pingar();
})();
