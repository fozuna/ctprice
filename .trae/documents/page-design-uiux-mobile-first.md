# Especificação de Page Design — UI/UX Mobile-first (Projeto inteiro)

## Global Styles (tokens e regras)
- **Breakpoints (mobile-first)**: base <768; `md` ≥768; `lg` ≥1024; `xl` ≥1280.
- **Spacing scale** (uso padrão): 4, 8, 12, 16, 24, 32, 40, 48, 64px.
  - 4/8: micro espaçamentos (ícones, chips)
  - 12/16: inputs, cards, listas
  - 24/32: seções, blocos e grids
  - 40/48/64: heros e separação de macro-seções
- **Touch targets (mín. 44px)**: qualquer elemento clicável deve ter `min-height`/`min-width` ≥44px (inclui ícones no header/footer e botões em cards).
- **Contraste**: texto/ícones e estados devem manter **≥4.5:1** (inclui placeholder e disabled quando houver texto relevante).
- **Motion**: transições **200–300ms** (hover/focus/expand/collapse) com easing consistente; respeitar `prefers-reduced-motion`.
- **Estados**:
  - Focus: sempre visível com `:focus-visible` (outline/box-shadow claro).
  - Hover: apenas onde faz sentido (desktop), sem depender dele para descobrir ação.
  - Active: feedback em toque (ex.: leve “press”).

## Padrão de Meta Information (por página)
- Title: “{Página} | CT Price”
- Description: 1 frase objetiva (120–160 chars)
- Open Graph: `og:title`, `og:description`, `og:type=website`, `og:image` (quando existir)

---

## Página Tipo A — Institucional (Home + internas de conteúdo)
### Layout
- **Mobile-first com stack**: seções empilhadas; grids viram 1 coluna no mobile e sobem para 2–4 colunas em `md+`.
- **Sistema**: CSS Grid (cards/listas) + Flex (alinhamentos no header e CTAs).
- **Gutters**: padding lateral consistente; evitar overflow horizontal.

### Page Structure
1) Header (logo + navegação + CTA)
2) Hero (título, subtítulo, CTA)
3) Seções de conteúdo (cards, lista, destaques)
4) Footer (links e contatos)

### Sections & Components
- **Header**: botão de menu no mobile (área clicável 44px); links com espaçamento suficiente entre si.
- **Cards/Grids**: `gap` baseado na escala (geralmente 16/24); botões no card com área 44px.
- **Tipografia**: títulos quebram bem no mobile (sem truncar); linhas 1.4–1.6 para parágrafos.
- **Imagens**: manter proporções; usar `aspect-ratio` quando possível.

---

## Página Tipo B — Formulários (Fale Conosco, Login, Cadastros)
### Layout
- Coluna única no mobile; duas colunas apenas em `md+` quando necessário.
- Espaçamento entre campos: 12–16px; entre blocos: 24–32px.

### Sections & Components
- **Campo (label + input)**:
  - Label sempre visível; toque no label foca o campo.
  - Input/Select/Textarea com altura mínima confortável (preferência: ≥44px para inputs no mobile).
- **Validação**:
  - Mensagem de erro curta e objetiva;
  - Borda/outline + texto com contraste ≥4.5:1.
- **Botões**:
  - Primário com min 44px de altura; estado loading; disabled sem “sumir”.
- **Acessibilidade**:
  - Ordem de tab natural; foco visível; `aria-invalid` e associação label/campo.

---

## Página Tipo C — Área interna (Dashboards, Tabelas, Kanban)
### Layout
- Mobile-first: tabelas viram **cards/linhas empilhadas** no mobile; tabela completa apenas em `md+`.
- Ações primárias ficam acessíveis sem precisão (targets 44px) e sem depender de hover.

### Sections & Components
- **Topbar**: ações principais como botões; ícones com hit-area 44px.
- **Filtros**: colapsáveis no mobile (expand/collapse com 200–300ms).
- **Listas/Tabelas**: evitar scroll horizontal; quando inevitável, indicar claramente e manter header sticky.
- **Kanban**: handles e botões de ação grandes; feedback visual de drag/seleção; reduzir motion se `prefers-reduced-motion`.

---

## Plano de testes (operacional)
- **Responsividade**: 360/390/414/768/1024/1280+ (retr./paisagem onde fizer sentido).
- **Interação**: toque (alvos 44px), teclado (tab + enter/space), foco visível.
- **A11y/contraste**: checar contraste (≥4.5:1) e rodar verificação automatizada quando disponível.
- **Evidência antes/depois**: capturar screenshots por rota e largura, anexar checklist e decisão (aprovado/pendente).
