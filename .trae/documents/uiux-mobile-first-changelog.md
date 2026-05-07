# Changelog UI/UX — Mobile-first

## Objetivo
Aplicar otimizações globais de UI/UX seguindo mobile-first: gutters mínimos, tipografia legível, alvos de toque 44px, motion 200–300ms com `prefers-reduced-motion` e foco visível consistente.

## Arquivos alterados

- `app/Views/layouts/main.php`
  - Breakpoints Tailwind (CDN): `sm=480`, `md=768`, `lg=1024`, `xl=1280`, `2xl=1440`
  - Tipografia base: `font-size: 16px; line-height: 24px` (mobile) e `18px/28px` (>=768)
  - Gutters do container: `16px` (mobile), `20px` (>=768), `24px` (>=1024)
  - Seções: `padding-top/bottom: 32px` (mobile) e `48px` (>=768)
  - Motion padrão: `transition-duration: 200ms` (links/botões/inputs)
  - Acessibilidade:
    - Focus visível: `outline: 2px solid rgba(16, 227, 107, 0.65)`
    - `prefers-reduced-motion: reduce` reduz animações/transições e desativa scroll-smooth
  - Utilitário de hit-area: `.ct-hit { min-width/min-height: 44px; display:inline-flex; align-items:center; justify-content:center; }`

- `app/Views/partials/header.php`
  - Menu mobile: padding horizontal atualizado para `px-4` (16px)
  - Itens do menu mobile: `min-h-[44px]` e alinhamento `flex items-center`
  - Botão do menu: `ct-hit` + `aria-label`
  - Ícones sociais (topbar): `ct-hit` + `aria-label`
  - CTA WhatsApp: `min-h-[44px]`

- `app/Views/partials/footer.php`
  - Ícones sociais: de `32x32` para `44x44`
  - CTA WhatsApp: `min-h-[44px]`

## Sistema de espaçamentos (aplicado)

- Gutters mínimos no mobile: **16px** (`.ct-container`)
- Escala recomendada: **4/8/12/16/24/32/48/64px** (documentos em `.trae/documents`)
- Seções padrão: **32px** (mobile) / **48px** (>=768)

## Motion (200–300ms)

- Padrão global aplicado: **200ms** em links, botões e inputs
- `prefers-reduced-motion`: reduz animações/transições e desativa scroll suave

## Checklist de validação (manual)

### Responsividade (larguras)
- 320, 375, 414, 768, 1024, 1440

### Itens a conferir
- Não existe overflow horizontal.
- Links/ícones/botões principais têm área de toque >= 44x44.
- Texto corpo >= 16px e line-height confortável no mobile.
- Foco visível ao navegar por teclado (Tab/Shift+Tab).
- Em `prefers-reduced-motion`, animações/transições ficam minimizadas.

### Evidências (screenshots)
Não consigo capturar screenshots automaticamente neste ambiente.
Para gerar antes/depois:
- DevTools → Toggle device toolbar → larguras 320/375/414/768/1024/1440
- DevTools → “Capture full size screenshot” (salvar em uma pasta local e anexar ao relatório interno)

