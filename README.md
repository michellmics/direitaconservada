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

## Estrutura

```
index.php               entrada: escolha do pote
pote.php                página do pote (?lado=direita | ?lado=esquerda)
includes/sides.php      TUDO que muda entre os lados: textos, tipos e preços, selos, cores, desenho dos itens
includes/config.php     constantes gerais (capacidade, UFs) e helpers (e(), money(), num())
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
