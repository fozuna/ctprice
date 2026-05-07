## Auditoria técnica (alto nível) — https://ctprice.com.br/wp/sobre-nos/

### Escopo e limitações

- Esta auditoria descreve estrutura, dependências e medidas de layout observáveis.
- Não inclui cópia literal do HTML/CSS/JS nem do conteúdo textual integral.

### Metadados

- `title`: "Sobre nós – CT Price"
- `canonical`: `https://ctprice.com.br/wp/sobre-nos/`
- Contagem de `<meta>` no HTML: 6

### Dependências de CSS (26)

- Elementor / Elementor Pro (frontend + widgets + swiper)
- Tema `hello-elementor` (reset, theme, header-footer)
- Plugins: `cookie-notice`, `pojo-accessibility`, `mask-form-elementor`
- CSS por página (Elementor): `uploads/elementor/css/post-377.css` e kit `post-43.css`

### Dependências de JavaScript (19)

- `jquery` + `jquery-migrate` + `jquery-ui/core`
- Elementor runtime + módulos + handlers (Elementor Pro)
- Swiper v8
- `cookie-notice`
- `gtranslate`
- `pojo-accessibility` (a11y widget)
- `mask-form-elementor` (input mask)

### Estrutura de conteúdo (blocos)

- Hero em full width com container “boxed” interno.
- Seção institucional (texto + imagem).
- Blocos “Missão / Visão / Valores”.
- Seção de prova social com grande volume de logos/imagens.
- Mapa (Google Maps embed) via `<iframe>`.

### Mídia

- `<img>` no HTML: 89 (88 `src` únicos)
- `<iframe>`: 1 (Google Maps embed)

### Medidas de layout observáveis (CSS)

- Container boxed (Elementor): `max-width: 1140px`.
- Tablet: `max-width: 1024px`.
- Mobile: `max-width: 767px`.
- Spacing comum de widgets/colunas: 20px (10px por lado em colunas).
- Hero/slider (página inicial do WP) usa `min-height: 640px` e padding interno 50px (desktop) / 30px (mobile).

### Checklist de equivalência (para validação manual)

- Verificar `max-width` efetivo do container interno em 1140/1024/767.
- Conferir gutters (padding horizontal) e gap padrão (20px).
- Confirmar que o hero permanece full-bleed (background 100% viewport) e só o conteúdo recebe padding.
- Confirmir carregamento lazy de imagens e iframe.

