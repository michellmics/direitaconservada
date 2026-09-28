# Direita Conservada × Pimenta da Resistência

Dois potes num site só. Na entrada a pessoa escolhe o lado:

- **Direita Conservada**: pote de azeitonas, certificado "Direita conservada desde <data>".
- **Pimenta da Resistência**: pote de pimentas (tema vermelho), certificado "Na resistência desde <data>".

Cada pote tem seu mural, ranking e certificado. Quem tem item em qualquer um dos potes pode **comentar nos posts dos dois lados**.

**Estado atual:** protótipo visual. Não tem banco nem pagamento de verdade; os dados vêm de `data/mock.php` e o que o visitante faz fica salvo no `localStorage` do navegador.

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
O site ainda usa os dados de exemplo de `data/mock.php`; a conexão está pronta em `includes/db.php` (`db()`).

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
data/mock.php           dados fictícios dos dois lados, com comentários cruzados
assets/css/style.css
assets/js/app.js        pote interativo, compra, certificado, compartilhar, mural e comentários
```

Link direto para um item: `/pote?c=…#azeitona-42` (o `c` é gerado por `url()` em `includes/rotas.php`).

## Painel do administrador (`/admin/`)

1. Defina `ADMIN_PASSWORD` no `.env` (vazio = painel desativado).
2. Acesse http://localhost:8080/admin/ e entre com essa senha.

No painel você cria **enquetes** — só uma fica no ar por vez (publicar uma nova encerra a atual).
Cada enquete pode aparecer nos dois potes (**duelo**, com resultado separado por lado) ou em um só.
Os votos vão para o banco (`enquete_votos`), um por navegador, até existir login.

## Login (link mágico por e-mail)

- `composer install` (instala o PHPMailer em `vendor/`, que não vai para o git).
- Configure no `.env`: `APP_URL` (endereço público do site — vai no link do e-mail) e `ENV_SMTP_HOST/PORT/USER/PASS`.
- `/entrar`: a pessoa digita o e-mail e recebe um link (vale 20 min, uso único). Abrir o link mostra o botão
  "Entrar" — assim os filtros de e-mail que abrem links sozinhos não gastam o acesso.
- Sessão de 30 dias (tabela `sessoes`), renovada enquanto a pessoa usa o site. Sair: `/sair` (POST).
- **Enquetes: só vota quem está logado**, um voto por pessoa (em qualquer aparelho).
- Limite: 3 links por e-mail a cada 15 minutos.

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
Cada frase tem o **lugar** e o **pote** onde aparece (direita, esquerda ou os dois). Edite em **/admin/frases**
(mesma senha do painel): adicionar, editar, desativar e excluir. Código: `includes/frases.php`.

## Rotas sem ".php" e links criptografados

- As páginas são rotas: `/pote`, `/perfil`, `/entrar`, `/sair`, `/admin/frases`, `/api/nivel`… Quem abre um endereço com `.php`
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
  Código: `includes/comentarios.php` (hoje lê de `data/mock.php`; com o banco, troque pelas consultas na tabela `comentarios`).
- **Citar**: "↩ Citar" mostra "Respondendo a Fulano" em cima da caixa; o comentário guarda o trecho citado e, clicando nele,
  leva ao original. Se o original for apagado, a citação avisa.
- **Apagar**: só o dono (botão pede confirmação). Fica o aviso "Comentário apagado pelo autor." e ele deixa de contar para
  níveis e provocadores. No protótipo, os comentários feitos pelo visitante ficam no navegador (como as compras).

## Dados no banco (seed) e mural paginado

- `php database/seed.php` popula o banco com gente de mentira: ~164 cadastros (e-mail `@seed.local`), 222 pimentas e
  azeitonas, a frase de cada um como post, 40 publicações provocativas, milhares de curtidas e **100 comentários**
  (2 apagados). Rodar de novo apaga só o que o seed criou; cadastros reais não são tocados.
- O site lê tudo do banco (`includes/banco_dados.php`): itens, posts, comentários, curtidas, mapa, níveis e placar.
  Sem banco (ou vazio), usa os dados gerados em `data/mock.php`.
- **Mural:** 12 posts por vez (`/api/posts`, `includes/posts.php`); "Carregar mais" traz os próximos 12 e trocar de aba
  (Recentes / Mais curtidas / Mais debatidas) busca de novo do começo. A 1ª página já vem na página.
- Compras, publicações e comentários feitos pelo visitante no protótipo ainda ficam no navegador (sem pagamento real).
