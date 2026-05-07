## Especificação CSS (pixel-focused) — Seção do Formulário

### Dimensões e layout

- Wrapper máximo: `max-width: 1024px`
- Altura mínima do bloco: `min-height: 440px`
- Colunas (>=768px): `grid-template-columns: 330px 1fr`

### Painel direito (fundo)

- Fundo: `#062B31` (flat)
- Padding vertical interno: `38px` (top) / `24px` (bottom)

### Heading

- Cor: `#FFFFFF`
- Linha 1:
  - `font-size: 16px`
  - `line-height: 20px`
  - `text-align: center`
  - Destaque “CT Price”: `font-weight: 600`
- Linha 2:
  - `font-size: 14px`
  - `line-height: 18px`
  - `margin-top: 12px`

### Form container

- Largura: `max-width: 360px`
- Espaçamento após heading: `margin-bottom: 28px`

### Labels

- Cor: `#28C76F`
- `font-size: 12px`
- `font-weight: 600`
- `margin-bottom: 8px`

### Inputs

- Fundo: `#FFFFFF`
- Borda: `1px solid #E0E0E0` (erro: `#E57373`)
- Raio: `4px`
- Altura: `40px`
- Padding: `10px 12px`
- Cor do texto: `#212121`
- Placeholder: `#9AA0A6`
- Focus:
  - `border-color: #28C76F`
  - `box-shadow: 0 0 0 2px rgba(40, 199, 111, 0.25)`

### Textarea

- Altura: `110px`
- `resize: none`
- Placeholder: “Informe como podemos te ajudar”

### Botão

- Label: “Enviar”
- Fundo: `#2ECC71`
- Texto: `#FFFFFF`
- `font-size: 14px`
- `font-weight: 600`
- Tamanho: `width: 110px; height: 40px`
- Raio: `5px`
- Alinhamento: centralizado (`margin: 0 auto`)
- Hover: `filter: brightness(0.95)`
- Active: `transform: translateY(1px)`

### Decoração (linhas verdes)

- Stroke/fill do outline: `rgba(30, 208, 122, 1)`
- Espessura: `2px`
- Retângulo: `98x78px` com borda 2px
- SVG curva: `270x300px`

### Botões flutuantes

- WhatsApp (painel esquerdo)
  - `52x52px`, `border-radius: 9999px`
  - Fundo: `#25D366`
  - Ícone: branco
  - Posicionamento: `left: 16px; bottom: 16px`
- Acessibilidade (painel direito)
  - `52x52px`, `border-radius: 9999px`
  - Fundo: `#2962FF`
  - Posicionamento: `right: 16px; bottom: 16px`

### Validação visual (lado a lado)

- Abra `http://127.0.0.1:8085/fale-conosco` e a imagem de referência.
- No DevTools:
  - Emulate device width 1024
  - Compare: largura do wrapper (1024), coluna esquerda (330), altura do bloco (440), largura do form (360), altura dos inputs (40), textarea (110), botão (110x40).
