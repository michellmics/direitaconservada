# PotePolitico.com.br — Direita Conservada × Pimenta da Resistência

Dois potes num site só. Na entrada a pessoa escolhe o lado:

- **Direita Conservada**: pote de azeitonas, certificado "Direita conservada desde <data>".
- **Pimenta da Resistência**: pote de pimentas (tema vermelho), certificado "Na resistência desde <data>".

Cada pote tem seu mural, ranking e certificado. **Qualquer conta (cadastro grátis) publica e comenta** nos murais dos dois lados
(migration 027: `posts`/`comentarios` guardam `usuario_id`; `item_id` NULL = conta sem item, aparece com o nome da conta, sem perfil
nem nível). Desafiar e ser desafiado no Tretódromo exige azeitona ou pimenta ativa; votar só exige login.

**Estado atual:** tudo no banco (MySQL). Compras por Pix conferidas à mão no painel; itens, posts, comentários, curtidas e
pedidos são da conta de quem está logado (nada de dados só no navegador).

## Rodar localmente

```bash
php -S localhost:8080 router.php
```

Abra http://localhost:8080

> O servidor embutido do PHP atende **um pedido por vez** e, no Windows, corta arquivos acima de ~64 KB. Por isso o
> `router.php` entrega CSS e JS comprimidos (gzip). Em produção (Apache/Nginx) nada disso acontece.

## Banco de dados (MySQL 8.0.16+)

1. Copie `.env.example` para `.env` (se ainda não existir) e preencha `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD`.
2. Rode as migrations — o script cria o banco se não existir:

```bash
php database/migrate.php            # roda o que falta
php database/migrate.php --status   # mostra o que já rodou
```

As migrations ficam em `database/migrations/` e rodam uma vez cada (registro na tabela `migrations`).
O site lê e grava tudo no banco (`includes/db.php` → `db()`). Banco vazio = potes vazios.

## Estrutura

```
index.php               entrada: escolha do pote
pote.php                página do pote: /pote?c=… (lado cifrado)
perfil.php              perfil de quem está no pote: /perfil?c=… (lado + id cifrados)
includes/sides.php      TUDO que muda entre os lados: textos, tipos e preços, selos, cores, desenho dos itens
includes/config.php     constantes gerais (capacidade, UFs) e helpers (e(), money(), num())
includes/env.php        lê o .env
includes/db.php         conexão PDO com o MySQL: db()
database/migrate.php    roda as migrations
database/migrations/    arquivos .sql (001_criar_tabelas.sql = estrutura completa)
includes/header.php     <head> (cores do lado via variáveis CSS) e barra do topo
includes/footer.php     rodapé + dados para o JS (window.DC)
partials/jar.php        o pote em SVG (usado no pote e na página de entrada)
partials/sprites.php    gradientes dos itens dos dois lados + selo da bandeira
partials/               seções: hero, mural, ranking, como-funciona, modals
data/mock.php           mock_items()/mock_comments() (leem do banco) e o gerador de gente de mentira do seed
assets/css/style.css
assets/js/app.js        pote interativo, compra, certificado, compartilhar, mural e comentários
```

Link direto para um item: `/pote?c=…#azeitona-42` (o `c` é gerado por `url()` em `includes/rotas.php`).

## Painel do administrador (`/cozinha/`)

1. Defina `ADMIN_PASSWORD` no `.env` (vazio = painel desativado).
2. Acesse http://localhost:8080/cozinha/ e entre com essa senha.

No painel você cria **enquetes** — só uma fica no ar por vez (publicar uma nova encerra a atual).
Cada enquete pode aparecer nos dois potes (**duelo**, com resultado separado por lado) ou em um só.
Os votos vão para o banco (`enquete_votos`), um por navegador, até existir login.

## Pagamento por Pix (sem gateway, migration 012)

- O gerador de QR code também está na `vendor/` (no git). No `.env`: `PIX_CHAVE`, `PIX_NOME`, `PIX_CIDADE`.
- Na compra, a pessoa informa o **nome do titular da conta que vai pagar**; o site gera o Pix copia e cola + QR com o
  valor exato, direto para a sua chave (`includes/pix.php`).
- **Conta:** sem login, ela digita o e-mail. E-mail novo → a conta nasce e ela já entra. E-mail que já tem conta → recebe
  um código de acesso por e-mail (ninguém entra na conta dos outros digitando o e-mail deles).
- **A compra é o cadastro:** na próxima, nome, cidade/UF, foto, selo e frase vêm da última compra ("Alterar" / "Para outra pessoa").
- Os itens nascem no banco como **`pendente`** (migration 013): só a dona vê, e pode publicar e comentar com eles. A cobrança
  fica em "Pagamento aguardando confirmação" (no pote e no perfil), com o botão "Ver Pix".
- Você confere o extrato em **`/cozinha/pedidos`** (nome do titular + valor + horário) e **aprova** (itens `ativo`: aparecem
  para todo mundo, com o que ela publicou/comentou) ou **nega** (itens `removido`: somem com tudo). Pendente há mais de
  20 min fica em destaque; em 24 h expira sozinho (ainda dá para aprovar, se o Pix cair atrasado). Na próxima visita ela
  vê o aviso do que aconteceu. Renovação segue o mesmo caminho (a nova validade vale ao aprovar).
- Fotos e selos enviados ficam em `uploads/pedidos/` (fora do git; só imagem é servida).

## Perfil por pessoa e presentes (migration 015)

- **O perfil é da pessoa** (a conta, em cada pote): o link é de uma azeitona, mas a página mostra todas as dela, com
  as publicações (as 20 últimas) e os comentários que fez (os 20 últimos). `banco_pessoa_numeros()` monta o grupo.
- **Certificado só da dona:** no próprio perfil, cada azeitona tem "Certificado" e "Renovar". No mural não tem mais botão
  de certificado, e clicar numa azeitona do pote abre o perfil da pessoa.
- **Editar perfil** (`/api/perfil`): foto (vale nos dois potes) e frase (vale no pote; troca também a frase no mural).
- **🎁 Presente:** na compra, "É presente" (ou o botão no cartão do cadastro). Depois do pagamento aprovado, quem comprou
  vê em "Presentes para entregar" o link com botão do WhatsApp. Quem abre `/presente?c=…` e toca em "Resgatar" (entrando
  com o e-mail: novo cria a conta, existente recebe o código de acesso) vira dona: a azeitona vai para a conta dela.
  Até o resgate, o presente é uma pessoa à parte no pote (não entra no perfil, no nível nem no cadastro de quem deu).
  `includes/presentes.php`; colunas `itens.presente_token`, `presente_de`, `presente_resgatado_em`.

## Logs do sistema (migration 016) e segurança do painel

- O painel fica em **`/cozinha/`** (não mais `/admin/`). Login: bloqueio **por IP**, 5 tentativas a cada 15 minutos.
- Tabela `logs` (`includes/log.php` → `logar()`): logins do painel e do site (sucesso, falha, bloqueio), ações do painel,
  pedidos, presentes, e-mails, rate limit, tudo o que as APIs respondem e os erros do PHP. Nível: debug, info, aviso,
  erro, segurança. Guardados por 180 dias.
- Consultar em **`/cozinha/logs`**: mais recentes primeiro, 50 por página, filtros e atalhos (logins do painel, segurança, erros).

## Contador de visitas (migration 024)

- `assets/js/visitas.js` (em todas as páginas públicas) → `api/visita.php`: conta cada página vista na tabela `visitas`
  e manda um "ainda estou aqui" a cada 30 s para `visitas_online`. Visitante = cookie anônimo `dc_vid` (no banco, só o hash).
  Robôs e o navegador com o painel aberto não contam.
- Consultar em **`/cozinha/visitas`**: online agora (ao vivo), hoje × ontem, período × período anterior, tendência
  semanal, gráficos por dia, mês (com projeção) e hora, mapa de calor dia × hora, páginas, origens e aparelhos.

## Tretódromo: duelos 1×1 (migration 026)

- **`/tretodromo`** (arena: ao vivo, aguardando, encerrados, gladiadores do mês e "Lançar desafio") e **`/duelo?n=ID`**
  (o duelo, com endereço fixo que vai para o Google). Lógica em `includes/duelos.php`, API em `api/duelo.php`.
- Grátis: para desafiar ou aceitar basta ter uma azeitona ou pimenta ativa; sempre contra alguém do outro pote.
  Aceite em 24 h → 3 rodadas (24 h por resposta; sem resposta = W.O.) → 24 h de votação (qualquer conta logada,
  menos os duelistas). Prazos vencidos: `duelos_atualizar()` ao abrir as páginas e na cron.
- Arena leve: cada aba mostra 24 duelos ("Carregar mais" traz os próximos); desafio expirado/recusado some da tela
  na hora e sai do banco depois de 30 dias (`duelos_limpar()`, cron).
- Avisos por push (e e-mail no desafio recebido). Card de vitória em PNG gerado no navegador (`assets/js/duelo.js`).

## Alertas para o administrador (migration 020)

- Destinatários em `ENV_EMAIL_ALERTAS` (separados por vírgula). `includes/alertas.php`.
- **Abriu o QR Code para compra** (ao gerar o pedido e em cada "Ver Pix") e **copiou o código Pix**: e-mail com cliente,
  e-mail, titular, itens, valor, horários, histórico da pessoa e IP/aparelho. Mesmo evento do mesmo pedido: no máximo
  1 e-mail a cada 5 minutos (as repetições são contadas).
- **Cron do cPanel a cada 15 min** chamando `/cron/alertas?chave=<ENV_CRON_CHAVE>` (ou `php cron/alertas.php`), migration 021:
  - pedido pendente há mais de 15 min e pedido que expirou (1 e-mail por pedido); expira os vencidos;
  - painel (entrou, senha errada, IP bloqueado, cron com chave errada), erros do sistema (agrupados) e moderação
    (rate limit estourado, quem publica/comenta demais em 15 min, denúncias novas) — lidos da tabela `logs`;
  - presentes pagos há 3+ dias sem resgate (repete a cada 7 dias);
  - resumo diário a partir das 8h (vendas, conversão, faturamento do mês, contas, mural, vencimentos, saúde);
    `?resumo=1` manda na hora;
  - banco fora do ar: e-mail direto, no máximo 1 por hora.
- **Clientes** (`includes/vencimentos.php`): "sua azeitona vence em X dias" 30, 15, 10, 5 e 1 dia(s) antes, das 9h às 20h,
  com o botão para renovar no perfil. Não avisa presente não resgatado nem item com renovação já aguardando pagamento.

## Login (código por e-mail, migration 019)

- O PHPMailer fica em `vendor/`, que vai para o git (o cPanel não tem composer). Para atualizar: `composer install --prefer-dist --no-dev`.
- Configure no `.env`: `APP_URL` (endereço público do site), `ENV_KEY` e `ENV_SMTP_HOST/PORT/USER/PASS`.
- `/entrar`: a pessoa digita o e-mail, recebe um **código de 6 dígitos** (vale 15 min, uso único) e digita o código na
  mesma página. Pedir um código novo invalida o anterior; 5 códigos errados e ele deixa de valer. No banco fica só o
  HMAC do código (com a `ENV_KEY`).
- Sessão de 30 dias (tabela `sessoes`), renovada enquanto a pessoa usa o site. Sair: `/sair` (POST).
- **Enquetes: só vota quem está logado**, um voto por pessoa (em qualquer aparelho).
- Limite: 3 códigos por e-mail a cada 15 minutos (e 10 tentativas por minuto por IP).

> Antivírus com "proteção de e-mail" (ex.: Norton Mail Shield) interceptam o SMTP com certificado próprio e o PHP
> recusa a conexão. Em produção isso não acontece; localmente, desative a verificação de e-mail do antivírus para o PHP
> ou use `ENV_SMTP_VERIFICAR=0` no `.env` (só vale com `APP_ENV=local` e `APP_URL` em localhost).

## Assinatura anual, anéis de tempo e provocadores

- **Tempo de assinatura**: 1º ano normal, 2º ano **prata**, 3º ano ou mais **ouro** — aparece no balão do pote, na foto do mural, no certificado e no perfil (no pote em si, sem contorno).
  Conta pelo `desde` do item (`ano_de_assinatura()` / `anel_de_tempo()` em `includes/config.php`).
- **"Desde" preservado**: `renovar_item()` (`includes/itens.php`) — renovando **em dia**, soma 1 ano à validade e mantém o `desde`;
  **vencido**, recomeça (`desde` = hoje). `expirar_itens()` tira do pote quem passou da validade (rodar 1x por dia).
- **Provocador(a) do mês**: `provocadores_do_mes()` — quem mais recebeu comentários de gente do outro pote no mês. O 1º ganha o selo 🔥.
- **Resposta em vídeo**: colar um link do YouTube/TikTok no comentário vira resposta em vídeo (migration 004).

## Nível ao lado do nome (tempero)

Cada pessoa tem um nível por pote, que aparece como ícones ao lado do nome (pote, mural, comentários, placares e perfil):

- **Pimenta** (pontos em SHU): Pimenta-de-cheiro 🌶️ → Jalapeño → Cumari → Murupi → Habanero 🌶️×5 → Carolina Reaper 🔥
- **Azeitona**: Em Salmoura 🫒 → Curtida → Reserva → Azeite Virgem → Extra-Virgem 🫒×5 → Oliveira Centenária 🫒👑

Pontos (`includes/tempero.php`, espelhado em `app.js`): R$ 1 em itens **ativos** = 5 (itens de R$ 2,90 a 9,90) · cada mês no pote = 1 ·
post no mural = 3 · comentário = 1 (máx. 5/dia, 10+ letras ou vídeo) · comentário recebido = 1 (do outro pote = 2) ·
10 curtidas recebidas = 1. Participação conta pelos últimos 12 meses. Faixas: 10 / 50 / 120 / 250 / 500 / 1000;
o último nível exige também R$ 20 em itens (só comentando, o máximo é o penúltimo).
**E-mail "você subiu de nível"** (migration 005, tabela `usuario_niveis`): quem está logado recebe um e-mail a cada nível
novo, uma vez só (cair e voltar não repete). O navegador chama `api/nivel.php`; quem já tem itens no banco tem o nível
recalculado lá. Nos fluxos do servidor (pagamento confirmado, post, comentário) chame `tempero_recalcular($usuarioId, $lado)`.
Nomes e ícones ficam em `includes/sides.php` (`tempero`). Com o banco: `tempero_fatos($usuarioId, $lado)` → `tempero_pontos()` → `tempero_nivel()`.

## Frases engraçadas (tabela `frases`, migration 006)

Frases sarcásticas sorteadas a cada visita: mural, janela de compra, rodapé e página de entrada.
Cada frase tem o **lugar** e o **pote** onde aparece (direita, esquerda ou os dois). Edite em **/cozinha/frases**
(mesma senha do painel): adicionar, editar, desativar e excluir. Código: `includes/frases.php`.

## Rotas sem ".php" e links criptografados

- As páginas são rotas: `/pote`, `/perfil`, `/entrar`, `/sair`, `/cozinha/frases`, `/api/nivel`… Quem abre um endereço com `.php`
  é redirecionado. Em produção (Apache/Hostinger) isso vem do **`.htaccess`**; localmente, do **`router.php`**
  (`php -S localhost:8080 router.php`). Os dois também bloqueiam `.env`, `includes/`, `data/`, `database/` e `vendor/`.
- Os parâmetros vão num só `?c=…`, criptografado com **AES-256-GCM** usando a **`ENV_KEY`** do `.env`
  (obrigatória). Token alterado é recusado. Links antigos com parâmetros abertos (`?lado=direita`, `?token=…` de e-mails já
  enviados) são redirecionados para a versão cifrada.
- No PHP: `url('perfil', ['lado' => 'direita', 'id' => 42])`, `url_absoluta(...)` para e-mails e `rota_params([...])` para ler.
  A chave nunca vai para o navegador: o JS recebe os links prontos (`DC.links`) e pede os das compras do protótipo em `/api/link`.
- Trocar a `ENV_KEY` invalida os links já compartilhados.

## Comentários do mural

- Abrir os comentários de um post busca no servidor os **10 mais recentes** (`/api/comentarios`); "Ver comentários
  anteriores" traz mais 10. A página só leva as contagens (`comentarios_contagem`) e os provocadores do mês.
  Código: `includes/comentarios.php` (leitura) e `includes/mural.php` (comentar, apagar, publicar, curtir).
- **Citar**: "↩ Citar" mostra "Respondendo a Fulano" em cima da caixa; o comentário guarda qual foi citado (`cita_id`,
  migration 014) e, clicando no trecho, leva ao original. Se o original for apagado, a citação avisa.
- **Apagar**: só o dono (botão pede confirmação). Fica o aviso "Comentário apagado pelo autor." e ele deixa de contar para
  níveis e provocadores.

## Dados no banco (seed) e mural paginado

- `php database/seed.php` popula o banco com gente de mentira: ~164 cadastros (e-mail `@seed.local`), 222 pimentas e
  azeitonas, a frase de cada um como post, 40 publicações provocativas, milhares de curtidas e **100 comentários**
  (2 apagados). Rodar de novo apaga só o que o seed criou; cadastros reais não são tocados.
- O site lê tudo do banco (`includes/banco_dados.php`): itens, posts, comentários, curtidas, mapa, níveis e placar.
  Sem banco (ou vazio), os potes ficam vazios: nunca aparece gente inventada em produção.
- **Mural:** 12 posts por vez (`/api/posts`, `includes/posts.php`); "Carregar mais" traz os próximos 12 e trocar de aba
  (Recentes / Mais curtidas / Mais debatidas) busca de novo do começo. A 1ª página já vem na página.
- Publicar, comentar, apagar e curtir exigem login e gravam no banco (`/api/posts` e `/api/comentarios`, ações
  `publicar`, `curtir`, `comentar`, `apagar`), com limite por dia.

## Apoio partidário (migration 009)

- Logo abaixo do hero: quadrados com as siglas dos partidos **do lado do pote** (pimenta = esquerda, azeitona = direita).
  A pessoa escolhe **até 3**; o ranking de apoio do pote aparece ao lado e atualiza na hora.
- Só conta quem está logado (como nas enquetes). Quem não entrou pode escolher: a escolha fica guardada e é
  registrada sozinha depois do login.
- Tabelas `partidos` (sigla, nome, lado, cores, ordem, ativo — edite direto no banco) e `apoios_partido`.
  Código: `includes/partidos.php`, `api/partidos.php`, `partials/partidos.php`. O seed faz cada cadastro apoiar
  1 a 3 partidos do próprio lado.

## Pote interativo (`assets/js/pote-agito.js`)

- O conteúdo dos potes fica **boiando devagar**, como na água (CSS `.flutua`, cada item com ritmo próprio).
- **Segurar e arrastar** o pote para os lados chacoalha o que tem dentro (inércia + mola); ao soltar, ele volta
  balançando. Um toque rápido continua abrindo o balão/certificado (na página inicial, continua indo para o pote).
- **Celular**: o botão "📳 Chacoalhar com o celular" pede a permissão do sensor de movimento (iPhone) e, a partir daí,
  sacudir o aparelho chacoalha o pote com som (gerado na hora pelo navegador: chocalho; na azeitona, com "glub" de
  salmoura) e uma vibração curta. Nada muda de lugar de verdade e nada passa de um pote para o outro.
- Quem ativou "reduzir movimento" no aparelho não vê as animações.
