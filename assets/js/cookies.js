// Aviso de cookies (LGPD), partials/legal.php.
// A escolha fica no cookie dc_consentimento (1 ano): "p1" = aceita publicidade personalizada, "p0" = só os necessários.
// Sem permissão, o Google mostra anúncios não personalizados.
(() => {
  const COOKIE = 'dc_consentimento';
  const aviso = document.getElementById('cookie-aviso');
  if (!aviso) return;
  const opcoes = document.getElementById('cookie-opcoes');
  const publicidade = document.getElementById('cookie-publicidade');
  const botao = (id) => document.getElementById(id);

  function lerEscolha() {
    const m = document.cookie.match(/(?:^|;\s*)dc_consentimento=(p[01])/);
    return m ? { publicidade: m[1] === 'p1' } : null;
  }

  function salvar(aceitaPublicidade) {
    const seguro = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${COOKIE}=${aceitaPublicidade ? 'p1' : 'p0'}; Max-Age=31536000; Path=/; SameSite=Lax${seguro}`;
    aviso.hidden = true;
    // recarrega para os anúncios respeitarem a nova escolha
    if (document.querySelector('ins.adsbygoogle')) location.reload();
  }

  function abrir(comOpcoes) {
    publicidade.checked = Boolean(lerEscolha()?.publicidade);
    opcoes.hidden = !comOpcoes;
    botao('cookie-salvar').hidden = !comOpcoes;
    botao('cookie-personalizar').hidden = comOpcoes;
    aviso.hidden = false;
  }

  botao('cookie-aceitar').addEventListener('click', () => salvar(true));
  botao('cookie-necessarios').addEventListener('click', () => salvar(false));
  botao('cookie-personalizar').addEventListener('click', () => abrir(true));
  botao('cookie-salvar').addEventListener('click', () => salvar(publicidade.checked));
  botao('cookie-preferencias')?.addEventListener('click', () => abrir(true));
  if (!lerEscolha()) abrir(false);

  // anúncios do Google: sem permissão, só não personalizados
  if (document.querySelector('ins.adsbygoogle') && !lerEscolha()?.publicidade) {
    window.adsbygoogle = window.adsbygoogle || [];
    window.adsbygoogle.requestNonPersonalizedAds = 1;
  }
})();
