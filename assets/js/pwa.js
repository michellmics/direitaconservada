// App (PWA): registra o service worker, cuida do botão "Instalar o app" (qualquer elemento com data-instalar-app)
// e, no celular, abre sozinho um convite animado ensinando a instalar.
//   Android com instalador (Chrome, Edge…): o convite tem o botão "Instalar agora", que abre o instalador.
//   Android sem instalador / iPhone / iPad: o convite mostra o passo a passo animado
//   (menu ⋮ → "Instalar app", ou Compartilhar → "Adicionar à Tela de Início").
//   Já aberto como app: nada aparece.
(() => {
  'use strict';
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('sw.js').catch(() => {}));
  }

  const instalado = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  const ios = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  const android = /android/i.test(navigator.userAgent);
  const celular = ios || android || matchMedia('(pointer: coarse) and (max-width: 820px)').matches;
  const botoes = () => [...document.querySelectorAll('[data-instalar-app]')];
  // data-instalar-app="celular": botão que só aparece no celular (ex.: o do topo da página do pote)
  // data-sem-instalar: o que aparece no lugar dele quando não há o que instalar (ex.: "Ver o mural")
  const mostrar = (sim) => {
    botoes().forEach((b) => { b.hidden = !sim || (b.dataset.instalarApp === 'celular' && !celular); });
    const noCelular = sim && celular;
    document.querySelectorAll('[data-sem-instalar]').forEach((el) => { el.hidden = noCelular; });
  };
  let pedido = null; // o evento "dá para instalar" (Android / PC)

  if (instalado) return mostrar(false);
  if (ios || android) mostrar(true);

  // "Agora não" vale por 7 dias (só conveniência de tela; sem storage, o convite aparece de novo, e tudo bem)
  const CHAVE = 'pwa-convite-dispensado';
  const ESPERA_DIAS = 7;
  const dispensadoRecente = () => {
    try { return Date.now() - Number(localStorage.getItem(CHAVE) || 0) < ESPERA_DIAS * 864e5; } catch { return false; }
  };
  const dispensar = () => { try { localStorage.setItem(CHAVE, String(Date.now())); } catch {} };

  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault(); // guarda para quando a pessoa tocar no botão
    pedido = e;
    mostrar(true);
    const aberto = document.querySelector('.pwa-convite');
    if (aberto) aberto.classList.add('tem-instalador');
  });
  window.addEventListener('appinstalled', () => { pedido = null; mostrar(false); fechar(); });

  async function instalarAgora() {
    if (!pedido) return false;
    pedido.prompt();
    const escolha = await pedido.userChoice.catch(() => null);
    pedido = null;
    if (escolha?.outcome === 'accepted') { mostrar(false); fechar(); }
    return true;
  }

  // ---------- o convite ----------
  const iconeCompartilhar = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7.5 7.5 12 3l4.5 4.5M6 11H5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1h-1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  const iconeMais = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="4" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 8v8M8 12h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
  const iconeMenu = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="5" r="2" fill="currentColor"/><circle cx="12" cy="12" r="2" fill="currentColor"/><circle cx="12" cy="19" r="2" fill="currentColor"/></svg>';
  const icone = 'assets/img/icon-192.png';

  function roteiro() {
    if (ios) {
      return {
        passos: [
          `Toque em <b>Compartilhar</b> <i class="pwa-ic">${iconeCompartilhar}</i> na barra do Safari`,
          `Escolha <b>Adicionar à Tela de Início</b> <i class="pwa-ic">${iconeMais}</i>`,
          'Pronto! O pote vira app na sua tela',
        ],
        nota: 'No iPhone, abra o site pelo Safari para instalar.',
        barra: `<div class="pwa-tela-barra is-baixo"><span></span><span class="pwa-alvo">${iconeCompartilhar}</span><span></span></div>`,
        menu: `<div class="pwa-tela-menu is-baixo">
            <div>Copiar</div><div>Adicionar aos Favoritos</div>
            <div class="pwa-alvo">${iconeMais} Adicionar à Tela de Início</div>
          </div>`,
      };
    }
    return {
      passos: [
        `Toque no menu <i class="pwa-ic">${iconeMenu}</i> do navegador`,
        'Escolha <b>Instalar app</b> ou <b>Adicionar à tela inicial</b>',
        'Pronto! O pote vira app na sua tela',
      ],
      nota: 'Funciona no Chrome, Edge e Samsung Internet.',
      barra: `<div class="pwa-tela-barra is-cima"><span class="pwa-url">potepolitico.com.br</span><span class="pwa-alvo">${iconeMenu}</span></div>`,
      menu: `<div class="pwa-tela-menu is-cima">
          <div>Nova guia</div><div>Favoritos</div>
          <div class="pwa-alvo">${iconeMais} Instalar app</div>
        </div>`,
    };
  }

  function abrir() {
    if (document.querySelector('.pwa-convite')) return;
    const r = roteiro();
    const apps = Array.from({ length: 4 }, () => '<i></i>').join('');
    const caixa = document.createElement('div');
    caixa.className = 'pwa-convite' + (pedido ? ' tem-instalador' : '') + (ios ? ' is-ios' : ' is-android');
    caixa.innerHTML = `
      <div class="pwa-convite-card" role="dialog" aria-modal="true" aria-labelledby="pwa-convite-titulo">
        <button type="button" class="pwa-convite-x" data-fechar-pwa aria-label="Fechar">×</button>
        <div class="pwa-convite-topo">
          <span class="pwa-convite-brilho" aria-hidden="true"></span>
          <img class="pwa-convite-icone" src="${icone}" alt="">
        </div>
        <h2 id="pwa-convite-titulo">Leve o pote no bolso</h2>
        <p class="pwa-convite-sub">Instale o <b>Pote Político</b>: abre num toque, em tela cheia, sem ocupar espaço.</p>

        <div class="pwa-convite-rapido">
          <button type="button" class="btn btn-gold btn-block pwa-convite-instalar" data-instalar-agora>📲 Instalar agora</button>
          <small>É grátis e leva 2 segundos.</small>
        </div>

        <div class="pwa-convite-guia">
          <div class="pwa-celular" aria-hidden="true">
            <div class="pwa-tela">
              <div class="pwa-cena c1">${r.barra}<span class="pwa-dedo"></span></div>
              <div class="pwa-cena c2">${r.barra}${r.menu}<span class="pwa-dedo"></span></div>
              <div class="pwa-cena c3"><div class="pwa-home">${apps}<b><img src="${icone}" alt=""><small>Pote</small></b></div></div>
            </div>
          </div>
          <ol class="pwa-passos">
            ${r.passos.map((p, i) => `<li class="p${i + 1}"><span>${i + 1}</span><div>${p}</div></li>`).join('')}
          </ol>
        </div>
        <p class="pwa-convite-nota">${r.nota}</p>

        <button type="button" class="pwa-convite-depois" data-fechar-pwa>Agora não</button>
      </div>`;
    caixa.addEventListener('click', async (e) => {
      if (e.target.closest('[data-instalar-agora]')) { if (!(await instalarAgora())) caixa.classList.remove('tem-instalador'); return; }
      if (e.target === caixa || e.target.closest('[data-fechar-pwa]')) { dispensar(); fechar(); }
    });
    document.addEventListener('keydown', esc);
    document.body.appendChild(caixa);
    document.documentElement.classList.add('pwa-travado');
    requestAnimationFrame(() => caixa.classList.add('is-aberto'));
    caixa.querySelector('.pwa-convite-card').focus?.();
  }

  function esc(e) { if (e.key === 'Escape') { dispensar(); fechar(); } }

  function fechar() {
    const caixa = document.querySelector('.pwa-convite');
    document.removeEventListener('keydown', esc);
    document.documentElement.classList.remove('pwa-travado');
    if (!caixa) return;
    caixa.classList.remove('is-aberto');
    caixa.classList.add('is-saindo');
    setTimeout(() => caixa.remove(), 350);
  }

  document.addEventListener('click', async (e) => {
    if (!e.target.closest('[data-instalar-app]')) return;
    e.preventDefault();
    if (celular) abrir(); // no celular, o convite explica (e tem o "Instalar agora" quando dá)
    else if (!(await instalarAgora())) abrir();
  });

  // No celular, o convite aparece sozinho depois de uns segundos no site (se não foi dispensado há pouco
  // e se não tem outra janela aberta por cima).
  if (celular && !dispensadoRecente()) {
    setTimeout(function tentar() {
      if (dispensadoRecente() || document.querySelector('.pwa-convite')) return;
      const ocupado = [...document.querySelectorAll('dialog[open], [aria-modal="true"]')].some((el) => el.getClientRects().length);
      if (ocupado) return setTimeout(tentar, 5000);
      abrir();
    }, 7000);
  }
})();
