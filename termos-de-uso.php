<?php
// Termos de uso: /termos-de-uso (?lado=… só escolhe as cores; o endereço oficial é sem ele).
// Dados do responsável vêm do .env (SITE_OWNER_*, CONTACT_EMAIL); vazio = "[preencher: …]" em amarelo.
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/auth.php';

$S = side(isset(SIDES[$_GET['lado'] ?? '']) ? $_GET['lado'] : 'direita');
$items = [];
$pageTitle = 'Termos de uso · ' . SITE_NAME;
$pageDesc = 'Regras de uso do ' . SITE_NAME . ': contas, publicações, moderação e responsabilidades.';
$canonica = url_base() . 'termos-de-uso';
$faltaDono = trim((string) env('SITE_OWNER_NAME', '')) === '' || trim((string) env('CONTACT_EMAIL', '')) === '';
require __DIR__ . '/includes/header.php';
?>
<main class="legal">
  <h1>Termos de Uso</h1>
  <p class="legal-data">Última atualização: 01/10/2026</p>
  <?php if ($faltaDono): ?><p class="legal-aviso">Os trechos em amarelo devem ser preenchidos no arquivo .env (SITE_OWNER_NAME, SITE_OWNER_DOCUMENT, CONTACT_EMAIL, SITE_OWNER_CITY). Recomenda-se a revisão deste texto por um advogado.</p><?php endif; ?>

  <h2>1. Aceitação</h2>
  <p>Estes Termos de Uso regulam o acesso e a utilização do site <?= e(SITE_NAME) ?> (o "Site"), mantido por <?= dono_info('SITE_OWNER_NAME', 'nome completo ou razão social') ?>, inscrito no <?= dono_info('SITE_OWNER_DOCUMENT', 'CPF ou CNPJ') ?> (o "Responsável"). Ao acessar ou utilizar o Site, você declara que leu, entendeu e concorda com estes Termos e com a <a href="privacidade">Política de Privacidade</a>. Se não concordar, não utilize o Site.</p>

  <h2>2. O que é o <?= e(SITE_NAME) ?></h2>
  <p>O Site é um espaço <b>gratuito e de humor</b> com dois potes, "Direita Conservada" e "Pimenta da Resistência", onde cada pessoa pega a sua azeitona ou pimenta, recebe um certificado simbólico, publica no mural, comenta, vota em enquetes e participa do Tretódromo. O Site é mantido com a exibição de anúncios.</p>
  <p>Os itens, certificados, níveis e rankings são <b>simbólicos</b>, não têm valor econômico e não representam filiação, apoio ou vínculo com qualquer partido, candidato, movimento ou órgão público. As frases e textos do Site têm caráter humorístico e satírico.</p>

  <h2>3. Conta</h2>
  <p>Para publicar, comentar e votar é preciso ter uma conta, criada com nome e e-mail. Você é responsável pelas informações que informa e por tudo o que é feito na sua conta; não compartilhe o código de acesso enviado ao seu e-mail. O Site é destinado a maiores de 18 anos.</p>

  <h2>4. O que você publica</h2>
  <p>Você é o único responsável pelos textos, fotos, selos, comentários e argumentos que publicar, que ficam públicos no Site. Ao publicar, você declara ter os direitos sobre o conteúdo e autoriza o Site a exibi-lo, sem custo, enquanto ele estiver publicado.</p>
  <p>É proibido publicar conteúdo que: seja ilegal; incite violência, ódio ou discriminação; contenha ameaças, assédio, calúnia, injúria ou difamação; exponha dados pessoais de terceiros; seja pornográfico; viole direitos autorais ou de imagem; seja propaganda, spam ou golpe; ou se passe por outra pessoa. Debate político é bem-vindo; ataque pessoal, não.</p>

  <h2>5. Moderação</h2>
  <p>Qualquer pessoa pode denunciar um conteúdo. O Responsável pode, a qualquer momento e sem aviso prévio, remover conteúdos, itens ou fotos e suspender ou bloquear contas que descumpram estes Termos ou a lei. Conteúdos ilícitos podem ser preservados e entregues às autoridades quando houver ordem judicial ou obrigação legal, nos termos do Marco Civil da Internet.</p>

  <h2>6. Uso adequado</h2>
  <p>Ao utilizar o Site, você se compromete a não tentar invadir, sobrecarregar ou prejudicar o seu funcionamento; não criar contas em massa nem usar robôs para votar, curtir, publicar ou gerar acessos; e não gerar cliques artificiais em anúncios.</p>

  <h2>7. Ausência de garantias e limitação de responsabilidade</h2>
  <p>O Site é fornecido "no estado em que se encontra" e "conforme disponível", sem garantia de funcionamento ininterrupto ou livre de falhas. As opiniões publicadas pelos usuários são de responsabilidade de quem as publicou e não representam a opinião do Responsável. Na máxima extensão permitida pela legislação, o Responsável não se responsabiliza por danos decorrentes do uso ou da impossibilidade de uso do Site, nem pelo conteúdo publicado por usuários, salvo nos casos previstos em lei.</p>

  <h2>8. Anúncios e links de terceiros</h2>
  <p>O Site exibe anúncios fornecidos por terceiros, como o Google AdSense, e pode conter links para sites externos. O Responsável não controla e não se responsabiliza pelo conteúdo, produtos, serviços ou práticas de privacidade desses terceiros.</p>

  <h2>9. Propriedade intelectual</h2>
  <p>A marca, o layout, os desenhos dos potes e itens, os textos e os códigos do Site pertencem ao Responsável ou são usados com autorização. É permitido compartilhar links e certificados do Site. A reprodução total ou parcial sem autorização prévia é proibida.</p>

  <h2>10. Alterações</h2>
  <p>O Responsável pode alterar, suspender ou encerrar funções do Site e alterar estes Termos a qualquer momento. A versão vigente estará sempre nesta página, com a data da última atualização. O uso continuado do Site após alterações significa concordância com a nova versão.</p>

  <h2>11. Legislação e foro</h2>
  <p>Estes Termos são regidos pelas leis da República Federativa do Brasil. Fica eleito o foro da comarca de <?= dono_info('SITE_OWNER_CITY', 'cidade/UF') ?> para dirimir eventuais controvérsias, ressalvado o direito do consumidor de ajuizar ação no foro de seu domicílio.</p>

  <h2>12. Contato</h2>
  <p>Dúvidas, denúncias ou pedidos sobre estes Termos: <?= dono_info('CONTACT_EMAIL', 'e-mail de contato') ?>.</p>
</main>
<?php
require __DIR__ . '/includes/footer.php';
