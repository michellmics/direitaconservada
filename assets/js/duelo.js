// Tretódromo (tretodromo.php e duelo.php → api/duelo.php): busca de oponente e formulário de desafio, aceitar/recusar,
// responder na vez, votar, copiar link e desenhar o card de vitória (PNG) para compartilhar.
(() => {
  'use strict';
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const DUELO = Number($('[data-duelo]')?.dataset.duelo || 0);

  function toast(msg) {
    const t = $('#toast');
    if (!t) return;
    t.textContent = msg;
    t.hidden = false;
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => { t.hidden = true; }, 4000);
  }

  async function api(dados) {
    try {
      const r = await fetch('api/duelo', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify(dados) });
      const j = await r.json();
      if (j.login) toast(j.erro);
      else if (j.erro) toast(j.erro);
      return j;
    } catch {
      toast('Sem conexão. Tente de novo.');
      return { erro: true };
    }
  }

  // contador de caracteres dos campos de argumento
  $$('[data-contador]').forEach((c) => {
    const campo = c.closest('label, form').querySelector('textarea');
    const max = campo.maxLength;
    const atualizar = () => { c.textContent = `${campo.value.length} / ${max}`; };
    campo.addEventListener('input', atualizar);
    atualizar();
  });

  // ---------- formulário "Lançar desafio" ----------
  const form = $('[data-desafio]');
  if (form) {
    const busca = $('[data-busca]', form);
    const lista = $('[data-resultados]', form);
    const escolhido = $('[data-oponente-escolhido]', form);
    const oponente = form.elements.oponente;
    const outroLado = () => (form.querySelector('input[name="meu"]:checked')?.dataset.lado === 'direita' ? 'esquerda' : 'direita');
    const poteNome = { esquerda: '🌶️ da Pimenta da Resistência', direita: '🫒 da Direita Conservada' }; // pelo pote do oponente
    const dizerPote = () => { $('[data-oponente-pote]', form).textContent = `(alguém ${poteNome[outroLado()]})`; };
    dizerPote();
    form.addEventListener('change', (e) => {
      if (e.target.name !== 'meu') return;
      dizerPote();
      oponente.value = '0'; // trocou de pote: o oponente precisa ser do outro lado
      escolhido.hidden = true;
      busca.value = '';
      lista.hidden = true;
    });

    let timer = null;
    busca.addEventListener('input', () => {
      clearTimeout(timer);
      const q = busca.value.trim();
      if (q.length < 2) { lista.hidden = true; return; }
      timer = setTimeout(async () => {
        const j = await api({ acao: 'buscar', lado: outroLado(), q });
        const itens = j.itens || [];
        lista.innerHTML = itens.length
          ? itens.map((it) => `<li><button type="button" data-escolher='${esc(JSON.stringify(it))}'>${it.lado === 'esquerda' ? '🌶️' : '🫒'} <b>${esc(it.nome)}</b> <small>${esc(it.uf)} · #${String(it.numero).padStart(4, '0')}</small></button></li>`).join('')
          : '<li class="arena-vazio">Ninguém com esse nome no outro pote.</li>';
        lista.hidden = false;
      }, 300);
    });
    lista.addEventListener('click', (e) => {
      const b = e.target.closest('[data-escolher]');
      if (!b) return;
      const it = JSON.parse(b.dataset.escolher);
      oponente.value = it.id;
      escolhido.innerHTML = `${it.lado === 'esquerda' ? '🌶️' : '🫒'} <b>${esc(it.nome)}</b> <small>${esc(it.uf)} · #${String(it.numero).padStart(4, '0')}</small>`;
      escolhido.hidden = false;
      lista.hidden = true;
      busca.value = '';
    });

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (oponente.value === '0') { toast('Escolha quem você vai enfrentar.'); busca.focus(); return; }
      const botao = form.querySelector('button[type="submit"]');
      botao.disabled = true;
      const j = await api({
        acao: 'desafiar', meu: Number(form.querySelector('input[name="meu"]:checked')?.value || 0), oponente: Number(oponente.value),
        tema: form.elements.tema.value, argumento: form.elements.argumento.value,
      });
      if (j.duelo) location.href = `duelo?n=${j.duelo}`;
      else botao.disabled = false;
    });
  }

  // ---------- página do duelo ----------
  document.addEventListener('click', async (e) => {
    const acao = e.target.closest('[data-acao]');
    if (acao && DUELO) {
      if (acao.dataset.confirmar && !window.confirm(acao.dataset.confirmar)) return;
      acao.disabled = true;
      const j = await api({ acao: acao.dataset.acao, duelo: DUELO });
      if (j.ok) location.reload(); else acao.disabled = false;
      return;
    }

    const voto = e.target.closest('[data-votar]');
    if (voto && DUELO) {
      $$('[data-votar]').forEach((b) => { b.disabled = true; });
      const j = await api({ acao: 'votar', duelo: DUELO, voto: voto.dataset.votar });
      $$('[data-votar]').forEach((b) => { b.disabled = false; });
      if (!j.ok) return;
      $$('[data-votar]').forEach((b) => {
        const meu = b === voto;
        b.classList.toggle('is-meu', meu);
        b.setAttribute('aria-pressed', String(meu));
        b.lastChild.textContent = b.lastChild.textContent.replace(/^\s*(Seu voto: |Votar em )/, meu ? ' Seu voto: ' : ' Votar em ');
      });
      const { votos_a: a, votos_b: b } = j.votos;
      const pa = Math.round((a / Math.max(1, a + b)) * 100);
      const plural = (n) => `${n.toLocaleString('pt-BR')} voto${n === 1 ? '' : 's'}`;
      $('[data-pct-a]').textContent = `${pa}%`;
      $('[data-pct-b]').textContent = `${100 - pa}%`;
      $('[data-votos-a]').textContent = plural(a);
      $('[data-votos-b]').textContent = plural(b);
      $('[data-barra-a]').style.width = `${pa}%`;
      toast('Voto registrado! Dá para mudar até o fim do duelo.');
      return;
    }

    const copiar = e.target.closest('[data-copiar]');
    if (copiar) {
      try { await navigator.clipboard.writeText(copiar.dataset.copiar); toast('Link copiado!'); } catch { toast(copiar.dataset.copiar); }
      return;
    }

    const card = e.target.closest('[data-card]');
    if (card) baixarCard(JSON.parse(card.dataset.card));
  });

  $('[data-argumentar]')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = e.target;
    const botao = f.querySelector('button');
    botao.disabled = true;
    const j = await api({ acao: 'argumentar', duelo: DUELO, texto: f.elements.texto.value });
    if (j.ok) location.reload(); else botao.disabled = false;
  });

  // ---------- card de vitória (PNG 1080×1350, no visual do Tretódromo) ----------
  function quebrar(g, texto, larg) {
    const linhas = [];
    let atual = '';
    for (const p of texto.split(' ')) {
      const t = atual ? `${atual} ${p}` : p;
      if (g.measureText(t).width > larg && atual) { linhas.push(atual); atual = p; } else atual = t;
    }
    if (atual) linhas.push(atual);
    return linhas;
  }

  async function baixarCard(c) {
    await document.fonts?.ready;
    const W = 1080, H = 1350;
    const cv = document.createElement('canvas');
    cv.width = W; cv.height = H;
    const g = cv.getContext('2d');
    const direita = c.lado !== 'esquerda';
    const fundo = direita ? '#161c0c' : '#1d0c0a', ouro = '#e7c54d', texto = '#f4ecd8', suave = direita ? '#b9b69a' : '#c9a9a0';
    g.fillStyle = fundo; g.fillRect(0, 0, W, H);
    g.strokeStyle = '#c9a227'; g.lineWidth = 20; g.strokeRect(10, 10, W - 20, H - 20);
    g.textAlign = 'center';

    g.fillStyle = ouro; g.font = '700 30px Inter, sans-serif';
    g.fillText(`TRETÓDROMO · DUELO #${String(c.id).padStart(4, '0')}`, W / 2, 110);

    // troféu
    g.save();
    g.translate(W / 2, 300);
    g.fillStyle = direita ? '#2f3c20' : '#441e1a';
    g.beginPath(); g.arc(0, 0, 130, 0, Math.PI * 2); g.fill();
    g.lineWidth = 8; g.strokeStyle = ouro; g.stroke();
    g.scale(8, 8); g.translate(-12, -12);
    g.lineWidth = 1.8; g.lineCap = 'round'; g.lineJoin = 'round';
    ['M6 9H4.5a2.5 2.5 0 0 1 0-5H6', 'M18 9h1.5a2.5 2.5 0 0 0 0-5H18', 'M4 22h16', 'M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22',
      'M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22', 'M18 2H6v7a6 6 0 0 0 12 0V2Z'].forEach((d) => g.stroke(new Path2D(d)));
    g.restore();

    g.fillStyle = ouro; g.font = '900 92px Fraunces, Georgia, serif';
    g.fillText('VENCEU O DUELO', W / 2, 560);
    g.fillStyle = texto; g.font = '800 70px Fraunces, Georgia, serif';
    g.fillText(c.vencedor, W / 2, 660);
    g.fillStyle = suave; g.font = '500 32px Inter, sans-serif';
    g.fillText(`${direita ? 'Azeitona' : 'Pimenta'} · ${c.pote} · ${c.uf}`, W / 2, 715);

    g.fillStyle = texto; g.font = 'italic 600 40px Fraunces, Georgia, serif';
    quebrar(g, `“${c.tema}”`, 860).slice(0, 3).forEach((l, i) => g.fillText(l, W / 2, 820 + i * 54));

    g.fillStyle = suave; g.font = '600 32px Inter, sans-serif';
    const placar = c.wo ? `contra ${c.perdedor} · vitória por W.O.` : `contra ${c.perdedor}${c.pct !== null ? ` · ${c.pct}% dos votos da plateia` : ''}`;
    g.fillText(placar, W / 2, 1040);
    if (!c.wo && c.pct !== null) {
      const x = 140, w = W - 280, y = 1080;
      g.fillStyle = '#c9a227'; g.beginPath(); g.roundRect(x, y, (w * c.pct) / 100 - 3, 26, [13, 4, 4, 13]); g.fill();
      g.fillStyle = direita ? '#e0563f' : '#7d8c2f'; g.beginPath(); g.roundRect(x + (w * c.pct) / 100 + 3, y, (w * (100 - c.pct)) / 100 - 3, 26, [4, 13, 13, 4]); g.fill();
    }
    g.fillStyle = ouro; g.font = '700 34px Inter, sans-serif';
    g.fillText(c.site, W / 2, 1250);

    cv.toBlob(async (blob) => {
      const arq = new File([blob], `tretodromo-duelo-${c.id}.png`, { type: 'image/png' });
      if (navigator.canShare?.({ files: [arq] })) {
        try { await navigator.share({ files: [arq], text: `🏆 ${c.vencedor} venceu o duelo no Tretódromo!` }); return; } catch { /* cancelou: baixa */ }
      }
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = arq.name;
      a.click();
      setTimeout(() => URL.revokeObjectURL(a.href), 2000);
      toast('Card baixado! Poste nas redes 🏆');
    }, 'image/png');
  }
})();
