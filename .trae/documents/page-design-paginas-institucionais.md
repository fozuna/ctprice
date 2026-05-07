# Especificação de Design (desktop-first) — Páginas Institucionais

## Global Styles (tokens existentes + ajustes desta refatoração)
- Tema/identidade: manter classes e paleta já usada (ex.: bg-primary, bg-secondary, bg-accent, bg-primary-dark).
- Tipografia (base): **16px** com **line-height 18px** para texto corrido; manter hierarquia visual atual de títulos (font-display/headings), apenas reequilibrando tamanhos caso necessário para caber na nova largura.
- Espaçamento (base): usar **20px** como padrão entre itens de grid/lista e blocos relacionados (cards, colunas, linhas de formulário, etc.).
- Botões/links: manter estados hover atuais (ex.: hover:text-secondary, hover:bg-accent-hover, shadow-card/soft).
- Container/largura: seguir o padrão do site modelo (Elementor boxed):
  - Desktop: **max-width 1140px**.
  - Tablet (<=1024): **max-width 1024px**.
  - Mobile (<=767): **max-width 767px**.
- Gutters (padding horizontal do container): **10px**.
- Hero: mantém **100% da largura da viewport** (background “full-bleed”), com conteúdo interno seguindo o padding do modelo: **50px** (desktop) e **30px** (mobile), e **min-height 640px**.

## Padrão de Layout (todas as páginas)
- Layout: Flexbox + CSS Grid via Tailwind.
- Estrutura: Top Bar (desktop) → Header (sticky) → Conteúdo (seções) → CTA (contato) → Footer.
- Breakpoints do modelo:
  - Mobile: **<=767px**
  - Tablet: **<=1024px**
  - Desktop: **>=1025px**
- Responsividade: desktop-first (decisões começam em 768+) com adaptações para 480 e abaixo, sem alterar o fluxo/navegação entre páginas.

---

## Página: Home (/)
### Meta Information
- Title: “CT Price — Organização Contábil”
- Description: texto curto institucional (o mesmo da hero/subtítulo atual)

### Estrutura e Componentes
- Header/Nav: trocar links de âncora (#about etc.) por rotas (/sobre, /servicos, /clientes, /depoimentos, /noticias); botão “Fale Conosco” mantém ação (WhatsApp/E-mail ou seção existente).
- Hero: manter visual; ajustar CTA “Nossos Serviços” para /servicos.
- Seções-resumo: manter as seções atuais, adicionando links “Ver mais” apontando para as páginas correspondentes.

---

## Página: Sobre (/sobre)
### Meta Information
- Title: “Sobre — CT Price”
- Description: resumo de proposta/valores.

### Estrutura e Componentes
- Hero simples (opcional): título + subtítulo, sem alterar estilos.
- Blocos: reutilizar layout e cards do trecho “Sobre a CT Price” da Home.
- CTA: repetir bloco de contato/WhatsApp/E-mail com mesma linguagem visual.

---

## Página: Serviços (/servicos)
### Meta Information
- Title: “Serviços — CT Price”
- Description: foco em contabilidade, planejamento e consultoria.

### Estrutura e Componentes
- Seção principal: grid de cards idêntico ao da Home; mostrar todos os serviços ativos.
- Interação: cada card mantém “Saiba mais”; quando link do serviço estiver vazio/#, direcionar para CTA de contato.

---

## Página: Clientes (/clientes)
### Meta Information
- Title: “Clientes — CT Price”
- Description: prova social via parceiros/clientes.

### Estrutura e Componentes
- Grid de logos: manter o mesmo componente da Home (mesmas dimensões, grayscale hover, cards).
- “Carregar mais”: manter botão e comportamento atual de rotação.

---

## Página: Depoimentos (/depoimentos)
### Meta Information
- Title: “Depoimentos — CT Price”
- Description: histórias de clientes.

### Estrutura e Componentes
- Grid de depoimentos: manter cards e thumbnail de vídeo.
- Modal de vídeo: reutilizar overlay/YouTube+MP4 já existente.

---

## Página: Notícias (/noticias)
### Meta Information
- Title: “Notícias — CT Price”
- Description: atualizações e conteúdos.

### Estrutura e Componentes
- Lista em cards: imagem (cover), título, excerpt, data; manter estilo premium (shadow, hover, bordas).
- Leitura do conteúdo: ao clicar, expandir na própria página (ex.: área abaixo do card) mantendo tipografia e espaçamento atuais.
