// Painel: opções dinâmicas da enquete e confirmação das ações destrutivas
(() => {
  'use strict';

  const list = document.getElementById('adm-options');
  const addBtn = document.getElementById('adm-add-option');

  if (list && addBtn) {
    const max = Number(list.dataset.max);
    const min = Number(list.dataset.min);

    const sync = () => {
      const items = [...list.children];
      items.forEach((li, i) => {
        const input = li.querySelector('input');
        input.placeholder = `Opção ${i + 1}`;
        input.required = i < min;
        li.querySelector('.adm-remove').hidden = items.length <= min;
      });
      addBtn.hidden = items.length >= max;
    };

    addBtn.addEventListener('click', () => {
      const li = list.firstElementChild.cloneNode(true);
      li.querySelector('input').value = '';
      list.appendChild(li);
      sync();
      li.querySelector('input').focus();
    });

    list.addEventListener('click', (e) => {
      const rm = e.target.closest('.adm-remove');
      if (!rm || list.children.length <= min) return;
      rm.parentElement.remove();
      sync();
    });

    sync();
  }

  // app do painel (PWA "Cozinha"): service worker próprio, com escopo /cozinha/
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('sw.js', { scope: './' }).catch(() => {}));
  }

  // menu sanduíche (celular): abre/fecha a lista de páginas; fecha ao escolher, ao tocar fora ou com Esc
  const topo = document.querySelector('.adm-topbar');
  const menuBtn = topo?.querySelector('[data-adm-menu]');
  if (menuBtn) {
    const abrir = (sim) => {
      topo.classList.toggle('menu-aberto', sim);
      menuBtn.setAttribute('aria-expanded', String(sim));
      menuBtn.setAttribute('aria-label', sim ? 'Fechar menu' : 'Abrir menu');
    };
    menuBtn.addEventListener('click', () => abrir(!topo.classList.contains('menu-aberto')));
    document.addEventListener('click', (e) => {
      if (!topo.classList.contains('menu-aberto')) return;
      if (e.target.closest('#adm-nav a') || !topo.contains(e.target)) abrir(false);
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') abrir(false); });
  }

  document.addEventListener('submit', (e) => {
    const msg = e.target.dataset.confirm;
    if (msg && !window.confirm(msg)) e.preventDefault();
  });
})();
