# Direita Conservada × Pimenta da Resistência

Dois potes num site só. Na entrada a pessoa escolhe o lado:

- **Direita Conservada**: pote de azeitonas, certificado "Direita conservada desde <data>".
- **Pimenta da Resistência**: pote de pimentas (tema vermelho), certificado "Na resistência desde <data>".

Cada pote tem seu mural, ranking e certificado. Quem tem item em qualquer um dos potes pode **comentar nos posts dos dois lados**.

**Estado atual:** protótipo visual. Não tem banco nem pagamento de verdade; os dados vêm de `data/mock.php` e o que o visitante faz fica salvo no `localStorage` do navegador.

## Rodar localmente

```bash
php -S localhost:8080
```

Abra http://localhost:8080

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
pote.php                página do pote (?lado=direita | ?lado=esquerda)
perfil.php              perfil de quem está no pote (?lado=direita&id=42)
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

Link direto para um item: `pote.php?lado=direita#azeitona-42` ou `pote.php?lado=esquerda#pimenta-42`.

## Painel do administrador (`/admin/`)

1. Defina `ADMIN_PASSWORD` no `.env` (vazio = painel desativado).
2. Acesse http://localhost:8080/admin/ e entre com essa senha.

No painel você cria **enquetes** — só uma fica no ar por vez (publicar uma nova encerra a atual).
Cada enquete pode aparecer nos dois potes (**duelo**, com resultado separado por lado) ou em um só.
Os votos vão para o banco (`enquete_votos`), um por navegador, até existir login.
