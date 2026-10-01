<?php
// Política de privacidade: /privacidade (?lado=… só escolhe as cores; o endereço oficial é sem ele).
// Dados do responsável vêm do .env (SITE_OWNER_*, CONTACT_EMAIL); vazio = "[preencher: …]" em amarelo.
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/auth.php';

$S = side(isset(SIDES[$_GET['lado'] ?? '']) ? $_GET['lado'] : 'direita');
$items = [];
$pageTitle = 'Política de privacidade · ' . SITE_NAME;
$pageDesc = 'Como o ' . SITE_NAME . ' trata os seus dados pessoais e usa cookies, conforme a LGPD.';
$canonica = url_base() . 'privacidade';
$faltaDono = trim((string) env('SITE_OWNER_NAME', '')) === '' || trim((string) env('CONTACT_EMAIL', '')) === '';
require __DIR__ . '/includes/header.php';
?>
<main class="legal">
  <h1>Política de Privacidade</h1>
  <p class="legal-data">Última atualização: 01/10/2026</p>
  <?php if ($faltaDono): ?><p class="legal-aviso">Os trechos em amarelo devem ser preenchidos no arquivo .env (SITE_OWNER_NAME, SITE_OWNER_DOCUMENT, CONTACT_EMAIL, SITE_OWNER_CITY). Recomenda-se a revisão deste texto por um advogado.</p><?php endif; ?>

  <h2>1. Quem somos</h2>
  <p>Esta Política explica como o <?= e(SITE_NAME) ?> (o "Site") trata dados pessoais, em conformidade com a Lei Geral de Proteção de Dados Pessoais (Lei nº 13.709/2018, "LGPD"). O controlador dos dados é <?= dono_info('SITE_OWNER_NAME', 'nome completo ou razão social') ?>, inscrito no <?= dono_info('SITE_OWNER_DOCUMENT', 'CPF ou CNPJ') ?>, que pode ser contatado pelo e-mail <?= dono_info('CONTACT_EMAIL', 'e-mail de contato') ?>, também canal do encarregado de dados.</p>

  <h2>2. Dados da sua conta</h2>
  <p>Para entrar no Site você informa o seu <b>nome</b> e o seu <b>e-mail</b>. O acesso é feito por um código ou link enviado ao seu e-mail (não guardamos senha). Esses dados servem para identificar a sua conta, mostrar o seu nome nas publicações e enviar os e-mails do próprio Site, como o código de acesso e os avisos de comentário (que você pode desligar pelo link no rodapé de cada aviso).</p>

  <h2>3. O que você publica</h2>
  <p>Ao pegar uma azeitona ou pimenta você informa <b>nome para o certificado, cidade, UF, frase</b> e, se quiser, uma <b>foto</b> e um selo. Esses dados, assim como as suas <b>publicações, comentários, curtidas, apoios a partidos e participações no Tretódromo</b>, ficam <b>públicos</b> no Site, visíveis para qualquer visitante. Não publique dados pessoais seus ou de outras pessoas que você não queira tornar públicos. Os votos em enquetes e duelos aparecem apenas como totais.</p>

  <h2>4. Dados coletados automaticamente</h2>
  <p>Como na maioria dos sites, ao navegar são registrados dados técnicos, como endereço IP, navegador e dispositivo, páginas acessadas, data e hora. Eles são usados para manter o Site funcionando com segurança, evitar abusos (limite de tentativas, moderação) e cumprir a guarda de registros de acesso exigida por lei.</p>

  <h2>5. Cookies e anúncios</h2>
  <p>Cookies são pequenos arquivos gravados no seu navegador. O Site utiliza:</p>
  <ul>
    <li><b>Cookies necessários</b>: manter você conectado (dc_sessao), proteger os formulários (dc_csrf) e guardar a sua escolha sobre cookies (dc_consentimento);</li>
    <li><b>Contador de visitas próprio</b> (dc_vid): um identificador aleatório e anônimo para contar visitantes e páginas vistas. No banco guardamos apenas um código derivado dele, sem o seu IP, e esses números não são compartilhados;</li>
    <li><b>Cookies de publicidade</b>, utilizados pelo Google e por seus parceiros para exibir e medir anúncios.</li>
  </ul>
  <p>Os anúncios <b>personalizados</b> só são exibidos se você aceitar no aviso de cookies. Se escolher "Só os necessários", o Google exibe anúncios não personalizados, que ainda podem usar cookies para limitar a frequência, medir resultados e evitar fraudes. Você pode mudar a escolha a qualquer momento em "Preferências de cookies", no rodapé.</p>
  <p>O Site utiliza o <b>Google AdSense</b>. O Google, como fornecedor terceiro, usa cookies para exibir anúncios com base nas visitas do usuário a este e a outros sites. Você pode desativar a publicidade personalizada nas <a href="https://adssettings.google.com" target="_blank" rel="noopener">Configurações de anúncios do Google</a> e saber mais em <a href="https://policies.google.com/technologies/partner-sites" target="_blank" rel="noopener">Como o Google usa informações de sites que usam seus serviços</a>.</p>
  <p>Você também pode bloquear ou apagar cookies nas configurações do navegador. Sem os cookies necessários, não é possível entrar na conta.</p>

  <h2>6. Notificações</h2>
  <p>Se você ativar "Receber avisos", o navegador gera um endereço de entrega de notificações, que guardamos para enviar os avisos do Site. Você pode desativar a qualquer momento nas configurações do navegador ou do aparelho.</p>

  <h2>7. Finalidades e bases legais</h2>
  <p>Os dados são tratados para: oferecer a conta e as funções do Site (execução do serviço solicitado por você); manter o Site seguro, moderar conteúdo e evitar abusos (legítimo interesse); guardar registros de acesso (cumprimento de obrigação legal, Marco Civil da Internet); gerar estatísticas de audiência (legítimo interesse); e exibir anúncios personalizados (consentimento).</p>

  <h2>8. Compartilhamento</h2>
  <p>Os dados podem ser compartilhados com prestadores de serviço necessários ao funcionamento do Site (hospedagem, envio de e-mails, entrega de notificações e proteção da rede) e com parceiros de publicidade, como o Google. Também podem ser compartilhados por obrigação legal ou ordem de autoridade competente. O Site <b>não vende</b> dados pessoais. Alguns desses parceiros podem armazenar dados fora do Brasil, observadas as regras da LGPD para transferência internacional.</p>

  <h2>9. Por quanto tempo guardamos</h2>
  <p>Os dados da conta e o que você publica ficam guardados enquanto a conta existir ou até que você peça a exclusão. Os registros de acesso são guardados pelo prazo mínimo de 6 meses exigido pelo Marco Civil da Internet (Lei nº 12.965/2014). Os prazos dos cookies de terceiros são definidos por seus fornecedores.</p>

  <h2>10. Seus direitos</h2>
  <p>Nos termos do art. 18 da LGPD, você pode solicitar: confirmação da existência de tratamento; acesso aos dados; correção de dados incompletos, inexatos ou desatualizados; anonimização, bloqueio ou eliminação de dados desnecessários ou tratados em desconformidade; portabilidade; informação sobre compartilhamento; exclusão da conta; e revogação do consentimento. Para exercer esses direitos, escreva para <?= dono_info('CONTACT_EMAIL', 'e-mail de contato') ?>. Você também pode apresentar reclamação à Autoridade Nacional de Proteção de Dados (ANPD).</p>

  <h2>11. Segurança</h2>
  <p>Adotamos medidas técnicas e administrativas razoáveis para proteger os dados, como conexão segura (HTTPS), acesso sem senha armazenada e limite de tentativas. Nenhum sistema é totalmente imune a incidentes; caso ocorra algum incidente relevante, adotaremos as providências previstas na LGPD.</p>

  <h2>12. Crianças e adolescentes</h2>
  <p>O Site não é direcionado a menores de 18 anos e não coleta intencionalmente dados pessoais de crianças e adolescentes.</p>

  <h2>13. Alterações desta Política</h2>
  <p>Esta Política pode ser atualizada a qualquer momento. A versão vigente estará sempre nesta página, com a data da última atualização. Leia também os <a href="termos-de-uso">Termos de uso</a>.</p>
</main>
<?php
require __DIR__ . '/includes/footer.php';
