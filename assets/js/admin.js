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

  document.addEventListener('submit', (e) => {
    const msg = e.target.dataset.confirm;
    if (msg && !window.confirm(msg)) e.preventDefault();
  });
})();
