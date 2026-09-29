// Puxar para atualizar no app instalado (PWA). Aberto como app, o Chrome e o Safari desligam o gesto nativo;
// no navegador comum ele já existe, então aqui só age em modo app (standalone).
// No topo da página, puxar para baixo mostra a bolinha ↻; soltou depois do ponto → recarrega a página.
// Não atrapalha: janela aberta, campo de texto, pote (arrastar chacoalha), vídeo, rolagem interna ou gesto de lado.
(() => {
  'use strict';
  const app = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  if (!app || !('ontouchstart' in window)) return;

  const PONTO = 70; // px puxados (já com a resistência) para recarregar
  const MAX = 110;
  let bola = null;
  let y0 = null;
  let x0 = 0;
  let puxado = 0;
  let decidido = false; // já sabe se o gesto é vertical (nosso) ou lateral (ignora)
  let recarregando = false;

  const criarBola = () => {
    bola = document.createElement('div');
    bola.className = 'puxar-bola';
    bola.setAttribute('aria-hidden', 'true');
    bola.innerHTML = '<span>↻</span>';
    document.body.appendChild(bola);
  };

  // algum pedaço rolável entre o dedo e a página, que ainda não está no topo? então a rolagem é dele
  const rolagemInterna = (el) => {
    for (; el && el !== document.body; el = el.parentElement) {
      if (el.scrollTop > 0 && /(auto|scroll)/.test(getComputedStyle(el).overflowY)) return true;
    }
    return false;
  };

  const ignorar = (alvo) => window.scrollY > 0
    || document.body.style.overflow === 'hidden' // janela (modal) aberta
    || document.querySelector('.modal:not([hidden])')
    || alvo.closest('input, textarea, select, iframe, video, svg.jar, [contenteditable], [data-sem-puxar]')
    || rolagemInterna(alvo);

  const mostrar = (px, animar) => {
    if (!bola) criarBola();
    bola.classList.toggle('animar', animar);
    bola.classList.toggle('pronto', px >= PONTO);
    bola.style.transform = `translate(-50%, ${px - 50}px)`;
    bola.style.opacity = String(Math.min(1, px / PONTO));
    bola.firstChild.style.transform = `rotate(${px * 3}deg)`;
  };

  window.addEventListener('touchstart', (e) => {
    if (recarregando || e.touches.length !== 1 || ignorar(e.target)) { y0 = null; return; }
    y0 = e.touches[0].clientY;
    x0 = e.touches[0].clientX;
    puxado = 0;
    decidido = false;
  }, { passive: true });

  window.addEventListener('touchmove', (e) => {
    if (y0 === null) return;
    const dy = e.touches[0].clientY - y0;
    const dx = e.touches[0].clientX - x0;
    if (!decidido) {
      if (Math.abs(dy) < 8 && Math.abs(dx) < 8) return;
      decidido = true;
      if (dy <= 0 || Math.abs(dx) > Math.abs(dy)) { y0 = null; return; } // subindo ou de lado: não é com a gente
    }
    if (window.scrollY > 0) { y0 = null; return; }
    e.preventDefault(); // segura o "quique" da página enquanto puxa
    puxado = Math.min(MAX, Math.max(0, dy) * 0.5); // resistência: o dedo anda mais que a bolinha
    mostrar(puxado, false);
  }, { passive: false });

  const soltar = () => {
    if (y0 === null) return;
    y0 = null;
    if (puxado >= PONTO) {
      recarregando = true;
      mostrar(PONTO, true);
      bola.classList.add('girando');
      location.reload();
    } else if (bola) {
      mostrar(0, true);
    }
    puxado = 0;
  };
  window.addEventListener('touchend', soltar, { passive: true });
  window.addEventListener('touchcancel', soltar, { passive: true });
})();
