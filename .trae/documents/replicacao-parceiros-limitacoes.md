## Replicação da página Parceiros (WP → projeto local)

### O que foi feito

- Foi criada a rota `/parceiros` no projeto local, reutilizando o layout e componentes já existentes.
- A página foi implementada com hero, blocos de “Ferramentas” e “Parceiros”, e mapa embed, seguindo o padrão visual do site atual.

### Limitações (por design e compliance)

- O projeto local não carrega o runtime do WordPress/Elementor (CSS/JS/plugins). Portanto, não é possível reproduzir 1:1 todos os comportamentos interativos do Elementor sem importar essas dependências.
- Os textos e assets do WP podem estar sujeitos a direitos autorais/licenças; por isso, a implementação local usa estrutura equivalente e imagens externas já públicas, sem copiar o HTML/CSS/JS do WP.

### Para atingir equivalência 1:1

- Fornecer confirmação de propriedade/licença do conteúdo e assets.
- Disponibilizar os arquivos de imagem originais (ou autorizar download/espelhamento).
- Definir quais comportamentos do Elementor precisam ser reproduzidos (animações, carrosséis, etc.) no stack atual.

