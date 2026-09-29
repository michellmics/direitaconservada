// Notificações do app (api/push.php + sw.js). Não pede permissão ao entrar (quem é perguntado de cara bloqueia):
//   - depois de votar numa enquete (evento "dc:votou" do app.js) aparece um convite: "Quer saber da próxima?"
//   - no rodapé, o botão [data-push] ativa / desativa os avisos a qualquer momento
// iPhone: só funciona com o app instalado na tela de início (iOS 16.4+); no Safari comum o botão explica isso.
(() => {
  'use strict';
  const eu = document.currentScript;
  const API = eu.src.replace(/assets\/js\/push\.js.*$/, 'api/push');
  const LADO = eu.dataset.lado || '';
  const CONTA = eu.dataset.conta || '0'; // id de quem está logado (0 = visitante)
  const suporta = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  const ios = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  const CHAVE_DISPENSA = 'push-convite-dispensado';
  const CHAVE_SYNC = 'push-sincronizado';
  const botao = () => document.querySelector('[data-push]');

  const guardar = (k, v) => { try { localStorage.setItem(k, v); } catch {} };
  const ler = (k) => { try { return localStorage.getItem(k); } catch { return null; } };
  const post = (dados) => fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify(dados) });

  const inscricaoAtual = async () => {
    if (!suporta) return null;
    const reg = await navigator.serviceWorker.getRegistration();
    return reg ? reg.pushManager.getSubscription() : null;
  };

  async function ativar() {
    const perm = await Notification.requestPermission();
    if (perm !== 'granted') {
      aviso(perm === 'denied' ? 'Os avisos estão bloqueados no navegador. Libere nas configurações do site para ativar.' : 'Tudo bem, fica para depois.');
      return atualizarBotao();
    }
    try {
      const reg = await navigator.serviceWorker.ready;
      const { chave } = await (await fetch(API, { credentials: 'same-origin' })).json();
      const b = atob(chave.replace(/-/g, '+').replace(/_/g, '/'));
      const sub = (await reg.pushManager.getSubscription())
        || await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: Uint8Array.from(b, (c) => c.charCodeAt(0)) });
      const r = await post({ acao: 'inscrever', endpoint: sub.endpoint, lado: LADO });
      if (!r.ok) throw new Error();
      guardar(CHAVE_SYNC, `${CONTA}|${Date.now()}`);
      aviso('🔔 Pronto! Você vai saber das enquetes novas e das viradas no placar.');
    } catch {
      aviso('Não deu para ativar os avisos agora. Tente de novo mais tarde.');
    }
    atualizarBotao();
  }

  async function desativar() {
    const sub = await inscricaoAtual();
    if (sub) {
      post({ acao: 'sair', endpoint: sub.endpoint }).catch(() => {});
      await sub.unsubscribe().catch(() => {});
    }
    aviso('Avisos desativados.');
    atualizarBotao();
  }

  async function atualizarBotao() {
    const b = botao();
    if (!b) return;
    if (!suporta) { // iPhone fora do app instalado: explica como fazer
      b.hidden = !ios;
      b.dataset.push = 'ios';
      b.textContent = '🔔 Receber avisos';
      return;
    }
    const sub = await inscricaoAtual().catch(() => null);
    b.hidden = Notification.permission === 'denied' && !sub;
    b.dataset.push = sub ? 'on' : 'off';
    b.textContent = sub ? '🔕 Desativar avisos' : '🔔 Receber avisos';
  }

  // mensagem curta embaixo (usa o #toast do site quando existe)
  function aviso(msg) {
    const t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    t.hidden = false;
    clearTimeout(aviso.timer);
    aviso.timer = setTimeout(() => { t.hidden = true; }, 4500);
  }

  // ---------- convite (depois de votar) ----------
  function convite() {
    if (document.querySelector('.push-convite')) return;
    const caixa = document.createElement('div');
    caixa.className = 'push-convite';
    caixa.setAttribute('role', 'dialog');
    caixa.setAttribute('aria-label', 'Receber avisos');
    caixa.innerHTML = `
      <span class="push-convite-sino" aria-hidden="true">🔔</span>
      <div><b>Quer saber quando sair a próxima enquete?</b>
      <small>E se o outro pote passar o seu no placar. Poucos avisos, prometemos.</small></div>
      <div class="push-convite-acoes">
        <button type="button" class="btn btn-gold btn-sm" data-push-sim>Ativar avisos</button>
        <button type="button" class="push-convite-nao" data-push-nao>Agora não</button>
      </div>`;
    caixa.addEventListener('click', (e) => {
      if (e.target.closest('[data-push-sim]')) { fechar(); ativar(); }
      if (e.target.closest('[data-push-nao]')) { guardar(CHAVE_DISPENSA, String(Date.now())); fechar(); }
    });
    document.body.appendChild(caixa);
    requestAnimationFrame(() => caixa.classList.add('is-aberto'));
    function fechar() { caixa.classList.remove('is-aberto'); setTimeout(() => caixa.remove(), 300); }
  }

  document.addEventListener('dc:votou', async () => {
    if (!suporta || Notification.permission !== 'default') return;
    if (Date.now() - Number(ler(CHAVE_DISPENSA) || 0) < 14 * 864e5) return; // "agora não" vale 14 dias
    if (await inscricaoAtual().catch(() => null)) return;
    setTimeout(convite, 1200); // deixa a pessoa ver o resultado primeiro
  });

  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-push]');
    if (!b) return;
    if (b.dataset.push === 'ios') { aviso('No iPhone: toque em Compartilhar → "Adicionar à Tela de Início", abra o app instalado e ative os avisos por lá.'); return; }
    if (b.dataset.push === 'on') desativar(); else ativar();
  });

  // já inscrito: confirma a inscrição no servidor 1 vez por dia e na hora em que a pessoa entra na conta
  // (a conta liga o aparelho aos avisos pessoais: comentário na publicação dela e citação)
  (async () => {
    await atualizarBotao();
    const sub = await inscricaoAtual().catch(() => null);
    const [contaSync, quando] = String(ler(CHAVE_SYNC) || '0|0').split('|');
    const contaNova = CONTA !== '0' && CONTA !== contaSync;
    if (sub && (contaNova || Date.now() - Number(quando || 0) > 864e5)) {
      post({ acao: 'inscrever', endpoint: sub.endpoint, lado: LADO }).then(() => guardar(CHAVE_SYNC, `${CONTA}|${Date.now()}`)).catch(() => {});
    }
  })();
})();
