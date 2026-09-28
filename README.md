# Direita Conservada

Um pote de azeitonas: cada pessoa compra a sua e recebe um certificado "Conservada desde <data>", deixa uma frase no mural e compartilha.

**Estado atual:** protótipo visual. Não tem banco nem pagamento de verdade; os dados vêm de `data/mock.php` e o que o visitante faz fica salvo no `localStorage` do navegador.

## Rodar localmente

```bash
php -S localhost:8080
```

Abra http://localhost:8080

## Estrutura

```
index.php               página principal (monta as partes)
includes/config.php     constantes (preço, UFs) e helpers (e(), money(), num())
includes/header.php     <head> e barra do topo
includes/footer.php     rodapé + dados para o JS (window.DC)
partials/               seções: hero (pote), mural, ranking, como-funciona, modals
data/mock.php           dados fictícios (trocar por consultas ao banco depois)
assets/css/style.css
assets/js/app.js        pote interativo, compra, certificado, compartilhar, mural
```

Link direto para uma azeitona: `/#azeitona-42` abre o certificado dela.
