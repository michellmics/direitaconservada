// Pote interativo: segurar e arrastar o pote para os lados chacoalha o que tem dentro dele (por inércia),
// e ao soltar ele volta balançando. No celular, dá para chacoalhar o próprio aparelho (pede permissão do
// sensor de movimento), com som e vibração. Só mexe no que está dentro de cada pote: nada muda de lugar
// de verdade. O "boiando na água" é CSS (.flutua); aqui é só o chacoalhão (.agito).
(() => {
  'use strict';

  const potes = [...document.querySelectorAll('svg.jar')];
  if (!potes.length || matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  const limitar = (v, max) => Math.max(-max, Math.min(max, v));
  const estado = new Map(); // svg → { ang, vel, alvo, arrastando, itens }
  let rodando = false;

  // cada item com um "peso" diferente: uns balançam mais que outros
  const itensDe = (svg) => [...svg.querySelectorAll('.agito')].map((el, i) => ({
    el, x: 0, y: 0, r: 0, vx: 0, vy: 0, vr: 0, m: 0.6 + ((i * 7919) % 100) / 100,
  }));

  function estadoDe(svg) {
    if (!estado.has(svg)) estado.set(svg, { ang: 0, vel: 0, alvo: 0, arrastando: false, itens: [] });
    return estado.get(svg);
  }

  function iniciar() {
    if (rodando) return;
    rodando = true;
    requestAnimationFrame(passo);
  }

  // física simples: o pote é uma mola até o alvo (ângulo do arraste; solto = 0);
  // o conteúdo vai para o lado contrário da aceleração do pote, bate e volta (mola + atrito)
  function passo() {
    let ativo = false;
    estado.forEach((s, svg) => {
      const acel = (s.alvo - s.ang) * 0.12 - s.vel * 0.16;
      s.vel += acel;
      s.ang += s.vel;
      const balancando = Math.abs(s.ang) > 0.01;
      svg.style.transform = balancando ? `rotate(${s.ang.toFixed(2)}deg)` : '';
      svg.style.transition = balancando || s.arrastando ? 'none' : ''; // a transição do CSS (hover) deixaria o balanço atrasado
      s.itens.forEach((it) => {
        it.vx = (it.vx - acel * 0.9 * it.m - it.x * 0.08) * 0.86;
        it.vy = (it.vy + Math.abs(acel) * it.m * (Math.random() - 0.45) * 1.4 - it.y * 0.09) * 0.86;
        it.vr = (it.vr - acel * 3.2 * it.m - it.r * 0.06) * 0.85;
        it.x = limitar(it.x + it.vx, 7);
        it.y = limitar(it.y + it.vy, 6);
        it.r = limitar(it.r + it.vr, 45);
        const mexendo = Math.abs(it.x) + Math.abs(it.y) + Math.abs(it.r) / 8 + Math.abs(it.vx) + Math.abs(it.vr) / 8 > 0.03;
        it.el.setAttribute('transform', mexendo ? `translate(${it.x.toFixed(2)} ${it.y.toFixed(2)}) rotate(${it.r.toFixed(1)})` : '');
        if (mexendo) ativo = true;
      });
      if (s.arrastando || Math.abs(s.ang) + Math.abs(s.vel) > 0.02) ativo = true;
    });
    if (ativo) requestAnimationFrame(passo);
    else rodando = false;
  }

  // chacoalhão de fora (celular): um empurrão aleatório no pote e no conteúdo
  function impulso(svg, forca) {
    const s = estadoDe(svg);
    if (!s.arrastando) s.itens = itensDe(svg);
    s.vel += forca * (Math.random() > 0.5 ? 1 : -1);
    s.itens.forEach((it) => {
      it.vx += (Math.random() - 0.5) * forca * 2;
      it.vy += (Math.random() - 0.5) * forca * 2;
      it.vr += (Math.random() - 0.5) * forca * 8;
    });
    iniciar();
  }

  // ---------- segurar e arrastar (mouse ou dedo) ----------
  potes.forEach((svg) => {
    let x0 = null;
    let ponteiro = null;
    let arrastou = false;
    // página inicial: o pote fica dentro de um link; sem isso, arrastar puxaria o link em vez de chacoalhar
    svg.closest('a')?.setAttribute('draggable', 'false');
    svg.addEventListener('dragstart', (e) => e.preventDefault());

    svg.addEventListener('pointerdown', (e) => {
      if (e.button > 0) return;
      x0 = e.clientX;
      ponteiro = e.pointerId;
      arrastou = false;
    });
    svg.addEventListener('pointermove', (e) => {
      if (x0 === null || e.pointerId !== ponteiro) return;
      const dx = e.clientX - x0;
      if (!arrastou) {
        if (Math.abs(dx) < 6) return; // um toque rápido continua sendo clique
        arrastou = true;
        try { svg.setPointerCapture(ponteiro); } catch { /* sem captura, segue chacoalhando enquanto estiver em cima */ }
        svg.classList.add('agitando');
        const s = estadoDe(svg);
        s.itens = itensDe(svg);
        s.arrastando = true;
        iniciar();
      }
      estadoDe(svg).alvo = limitar(dx * 0.25, 28);
    });
    const soltar = () => {
      if (x0 === null) return;
      x0 = null;
      const s = estadoDe(svg);
      if (s.arrastando) {
        s.arrastando = false;
        s.alvo = 0; // volta balançando
        svg.classList.remove('agitando');
      }
    };
    svg.addEventListener('pointerup', soltar);
    svg.addEventListener('pointercancel', soltar);
    // quem arrastou não "clicou": não abre o certificado nem segue o link do pote (página inicial).
    // O clique depois de arrastar pode cair no link em volta (e não no pote), por isso escuta no link.
    (svg.closest('a') || svg.parentElement).addEventListener('click', (e) => {
      if (arrastou) {
        e.preventDefault();
        e.stopPropagation();
        arrastou = false;
      }
    }, true);
  });

  // ---------- chacoalhar o celular: sensor de movimento + som ----------
  const comSensor = 'DeviceMotionEvent' in window && matchMedia('(hover: none)').matches;
  if (!comSensor) return;

  let audio = null;
  let ruido = null;
  function prepararSom() {
    const Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx || audio) return;
    audio = new Ctx();
    // 60 ms de ruído branco: vira o "chocalho" depois de filtrado
    ruido = audio.createBuffer(1, Math.floor(audio.sampleRate * 0.06), audio.sampleRate);
    const dados = ruido.getChannelData(0);
    for (let i = 0; i < dados.length; i++) dados[i] = Math.random() * 2 - 1;
  }

  // chocalho: batidinhas de ruído filtrado; o pote de azeitona (com salmoura) ainda faz um "glub"
  function tocarChocalho(forca, comAgua) {
    if (!audio) return;
    const t0 = audio.currentTime;
    const volume = Math.min(0.35, 0.08 + forca * 0.05);
    const batidas = 3 + Math.round(Math.min(forca, 4) * 1.5);
    for (let i = 0; i < batidas; i++) {
      const t = t0 + i * 0.035 + Math.random() * 0.02;
      const src = audio.createBufferSource();
      src.buffer = ruido;
      const filtro = audio.createBiquadFilter();
      filtro.type = 'bandpass';
      filtro.frequency.value = 2200 + Math.random() * 2800;
      filtro.Q.value = 2.5;
      const ganho = audio.createGain();
      ganho.gain.setValueAtTime(0.0001, t);
      ganho.gain.exponentialRampToValueAtTime(volume, t + 0.004);
      ganho.gain.exponentialRampToValueAtTime(0.0001, t + 0.05);
      src.connect(filtro).connect(ganho).connect(audio.destination);
      src.start(t);
      src.stop(t + 0.06);
    }
    if (comAgua) {
      const osc = audio.createOscillator();
      const ganho = audio.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(260, t0);
      osc.frequency.exponentialRampToValueAtTime(110, t0 + 0.18);
      ganho.gain.setValueAtTime(0.0001, t0);
      ganho.gain.exponentialRampToValueAtTime(volume * 0.9, t0 + 0.02);
      ganho.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.22);
      osc.connect(ganho).connect(audio.destination);
      osc.start(t0);
      osc.stop(t0 + 0.24);
    }
  }

  // chacoalha os potes que estão na tela
  let ultimo = 0;
  function aoMexer(e) {
    const a = e.acceleration?.x != null ? e.acceleration : e.accelerationIncludingGravity;
    if (!a) return;
    const intensidade = Math.hypot(a.x || 0, a.y || 0, a.z || 0) - (a === e.acceleration ? 0 : 9.8);
    const agora = performance.now();
    if (intensidade < 11 || agora - ultimo < 220) return;
    ultimo = agora;
    const forca = Math.min(intensidade / 6, 5);
    const visiveis = potes.filter((svg) => {
      const r = svg.getBoundingClientRect();
      return r.bottom > 0 && r.top < innerHeight;
    });
    visiveis.forEach((svg) => impulso(svg, forca));
    if (visiveis.length) {
      tocarChocalho(forca, visiveis.some((svg) => svg.classList.contains('jar-pot')));
      navigator.vibrate?.(25);
    }
  }

  // botão logo abaixo do pote (no pote e no perfil; na página inicial não tem)
  const lugar = document.querySelector('.jar-wrap .jar-capacity') || document.querySelector('.profile-jar-caption');
  if (!lugar) return;
  const botao = document.createElement('button');
  botao.type = 'button';
  botao.className = 'agito-sensor';
  botao.innerHTML = '📳 Chacoalhar com o celular';
  lugar.after(botao);

  botao.addEventListener('click', async () => {
    if (botao.classList.contains('is-on')) return;
    try {
      // iPhone: o navegador pede permissão do sensor de movimento
      if (typeof DeviceMotionEvent.requestPermission === 'function' && (await DeviceMotionEvent.requestPermission()) !== 'granted') {
        botao.textContent = 'Sem permissão do sensor 😕';
        return;
      }
    } catch {
      botao.textContent = 'Sensor indisponível 😕';
      return;
    }
    prepararSom();
    audio?.resume();
    window.addEventListener('devicemotion', aoMexer);
    botao.classList.add('is-on');
    botao.textContent = '📳 Chacoalhe o celular!';
    tocarChocalho(2, potes.some((svg) => svg.classList.contains('jar-pot')));
    potes.forEach((svg) => impulso(svg, 2.5)); // mostra que funciona
  });
})();
