// Painel → Visitas: gráficos (Chart.js) e o "online agora", que se atualiza sozinho a cada 10 s.
(() => {
  'use strict';
  const D = window.VISITAS;
  if (!D) return;

  const css = getComputedStyle(document.documentElement);
  const cor = (v) => css.getPropertyValue(v).trim();
  const C = { v1: cor('--vis-1'), v2: cor('--vis-2'), v3: cor('--vis-3'), texto: cor('--muted'), grade: cor('--line'), fundo: cor('--surface') };
  const nf = new Intl.NumberFormat('pt-BR');
  const semana = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
  const graficos = {};

  // média móvel de 7 dias (a tendência sem o sobe-e-desce do dia a dia)
  const media7 = (xs) => xs.map((_, i) => {
    if (i < 6) return null;
    const janela = xs.slice(i - 6, i + 1);
    return Math.round(janela.reduce((a, b) => a + b, 0) / 7 * 10) / 10;
  });

  const desenhar = () => {
    const Chart = window.Chart;
    Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
    Chart.defaults.color = C.texto;
    Chart.defaults.borderColor = C.grade;
    const base = {
      responsive: true, maintainAspectRatio: false, animation: { duration: 500 },
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: 'rgba(15,18,8,.95)', borderColor: C.grade, borderWidth: 1, padding: 10, cornerRadius: 10,
          titleColor: '#f4ecd8', bodyColor: '#f4ecd8', boxPadding: 4, usePointStyle: true,
          callbacks: { label: (c) => ` ${c.dataset.label}: ${c.parsed.y === null ? '—' : nf.format(c.parsed.y)}` },
        },
      },
      scales: {
        x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 14 } },
        y: { beginAtZero: true, grid: { color: C.grade + '66' }, border: { display: false }, ticks: { precision: 0, callback: (v) => nf.format(v) } },
      },
    };
    const linha = (cor, extra = {}) => ({
      borderColor: cor, backgroundColor: cor, borderWidth: 2, tension: 0.3,
      pointRadius: 0, pointHoverRadius: 5, pointHoverBorderColor: C.fundo, pointHoverBorderWidth: 2, ...extra,
    });

    // ---- por dia ----
    const dias = D.dias;
    const vis = dias.map((d) => d.v);
    const rotulo = (d) => { const [a, m, dd] = d.split('-'); return `${dd}/${m}`; };
    const ctxDias = document.getElementById('g-dias').getContext('2d');
    const degrade = ctxDias.createLinearGradient(0, 0, 0, 320);
    degrade.addColorStop(0, C.v1 + '55');
    degrade.addColorStop(1, C.v1 + '00');
    graficos.dias = new Chart(ctxDias, {
      type: 'line',
      data: {
        labels: dias.map((d) => rotulo(d.d)),
        datasets: [
          linha(C.v1, { label: 'Visitantes', data: vis, fill: true, backgroundColor: degrade, pointRadius: dias.length <= 31 ? 3 : 0, order: 1 }),
          linha(C.v1, { label: 'Média de 7 dias', data: media7(vis), borderDash: [6, 5], borderWidth: 2, borderColor: '#f4ecd8aa', order: 0 }),
          linha(C.v2, { label: 'Páginas vistas', data: dias.map((d) => d.pv), order: 2 }),
        ],
      },
      options: {
        ...base,
        plugins: {
          ...base.plugins,
          tooltip: {
            ...base.plugins.tooltip,
            callbacks: {
              ...base.plugins.tooltip.callbacks,
              title: (it) => { const d = dias[it[0].dataIndex].d; return `${semana[new Date(d + 'T12:00').getDay()]}, ${rotulo(d)}`; },
              afterBody: (it) => { const d = dias[it[0].dataIndex]; return d.n ? `  ${nf.format(d.n)} novos` : ''; },
            },
          },
        },
      },
    });

    // ---- por mês (o mês atual ganha a projeção em barra tracejada) ----
    const meses = D.meses;
    const proj = meses.map((m, i) => (i === meses.length - 1 && D.projecao ? Math.max(0, D.projecao - m.v) : null));
    graficos.meses = new Chart(document.getElementById('g-meses'), {
      type: 'bar',
      data: {
        labels: meses.map((m) => m.m),
        datasets: [
          { label: 'Visitantes', data: meses.map((m) => m.v), backgroundColor: C.v1, borderRadius: 4, borderSkipped: 'bottom', stack: 's', maxBarThickness: 34 },
          { label: 'Projeção (a mais até o fim do mês)', data: proj, backgroundColor: C.v1 + '33', borderColor: C.v1, borderWidth: 1.5, borderDash: [4, 3], borderRadius: 4, borderSkipped: 'bottom', stack: 's', maxBarThickness: 34 },
        ],
      },
      options: {
        ...base,
        scales: { ...base.scales, x: { ...base.scales.x, stacked: true }, y: { ...base.scales.y, stacked: true } },
        plugins: {
          ...base.plugins,
          tooltip: {
            ...base.plugins.tooltip,
            filter: (c) => c.parsed.y !== null,
            callbacks: {
              ...base.plugins.tooltip.callbacks,
              afterBody: (it) => (it[0].dataIndex === meses.length - 1 && D.projecao ? `  Projeção do mês: ~${nf.format(D.projecao)}` : ''),
            },
          },
        },
      },
    });

    // ---- por hora: hoje × ontem (hoje só até a hora atual) ----
    graficos.horas = new Chart(document.getElementById('g-horas'), {
      type: 'line',
      data: {
        labels: Array.from({ length: 24 }, (_, h) => `${h}h`),
        datasets: [
          linha(C.v1, { label: 'Hoje', data: D.horas.hoje.map((v, h) => (h <= D.horaAgora ? v : null)), pointRadius: 2 }),
          linha(C.v3, { label: 'Ontem', data: D.horas.ontem, borderDash: [5, 4] }),
        ],
      },
      options: base,
    });

    // ---- ao vivo (vai somando pontos enquanto a página está aberta) ----
    graficos.aovivo = new Chart(document.getElementById('g-aovivo'), {
      type: 'line',
      data: { labels: [agora()], datasets: [linha(C.v1, { label: 'Online', data: [D.online], fill: true, backgroundColor: C.v1 + '22', stepped: true, tension: 0 })] },
      options: {
        ...base, animation: false,
        scales: { x: { display: false }, y: { display: false, beginAtZero: true, suggestedMax: 3 } },
      },
    });
  };

  function agora() {
    return new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  }

  // ---- online agora ----
  const caixa = document.getElementById('vis-aovivo');
  const campo = (k) => caixa.querySelector(`[data-on="${k}"]`);
  const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  let anterior = D.online;

  const atualizar = async () => {
    try {
      const r = await fetch('visitas?online=1', { cache: 'no-store', credentials: 'same-origin' });
      if (r.status === 401) { location.href = './'; return; }
      if (!r.ok) return;
      const o = await r.json();
      campo('agora').textContent = nf.format(o.agora);
      campo('meia_hora').textContent = nf.format(o.meia_hora);
      campo('hora').textContent = o.hora;
      const pags = Object.entries(o.paginas);
      campo('paginas').innerHTML = pags.length
        ? pags.map(([p, c]) => `<li><span>${esc(p)}</span><b>${c}</b></li>`).join('')
        : '<li class="vis-vazio">Ninguém no site agora.</li>';
      if (o.agora !== anterior) {
        caixa.classList.remove('is-mudou');
        void caixa.offsetWidth;
        caixa.classList.add('is-mudou');
        anterior = o.agora;
      }
      const g = graficos.aovivo;
      if (g) {
        g.data.labels.push(agora());
        g.data.datasets[0].data.push(o.agora);
        if (g.data.labels.length > 90) { g.data.labels.shift(); g.data.datasets[0].data.shift(); } // últimos 15 min
        g.update();
      }
    } catch { /* sem rede: tenta de novo no próximo ciclo */ }
  };

  let timer = null;
  const ciclo = () => {
    clearInterval(timer);
    if (document.visibilityState === 'visible') timer = setInterval(atualizar, 10000);
  };
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') atualizar(); ciclo(); });
  ciclo();

  let tentativas = 0; // o Chart.js vem do CDN: se não carregar em ~10 s, fica só com os números
  const iniciar = () => (window.Chart ? desenhar() : ++tentativas < 200 && setTimeout(iniciar, 50));
  iniciar();
})();
