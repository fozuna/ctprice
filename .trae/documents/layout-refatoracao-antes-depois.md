# Refatoração de Layout — Antes/Depois (Aprovação)

## Objetivo
Reduzir drasticamente o uso excessivo de espaço horizontal, centralizando o conteúdo e aplicando regras globais de largura, padding, breakpoints, gap e tipografia, **sem alterar fluxo/arquitetura**.

## Regras aplicadas
- Container (padrão Elementor boxed): **1140px (desktop)**, **1024px (tablet)** e **767px (mobile)** via `.ct-container`.
- Gutters: `padding-left/right: 10px` via `.ct-container`.
- Widgets spacing: **20px** (usando `gap-5` e utilitário `.ct-gap-20`).
- Hero: `.ct-hero` com **min-height 640px** e `.ct-hero-inner` com padding **50px (desktop)** / **30px (mobile)**.
- Tipografia base: mantida em **16px/18px (mobile)** e **18px/22px (desktop)**.
- Seções: padding vertical padrão via `.ct-section` em **35px**.

## Mudanças técnicas (onde)
- Tokens globais e utilitários: `app/Views/layouts/main.php`
- Containers do header/footer: `app/Views/partials/header.php`, `app/Views/partials/footer.php`
- Containers/gaps/seções: `app/Views/home/index.php` e `app/Views/pages/*.php`

## Exemplos de Antes/Depois

### 1) Container/padding
**Antes**
```html
<div class="container mx-auto px-4">
  ...
</div>
```

**Depois**
```html
<div class="ct-container">
  ...
</div>
```

Resultado esperado:
- Conteúdo sempre centralizado.
- Desktop: **max-width 1140px**.
- Tablet (<=1024): **max-width 1024px**.
- Mobile (<=767): **max-width 767px**.
- Gutters fixos de **10px**.

### 2) Espaçamento entre itens (gap)
**Antes**
```html
<div class="grid gap-8">
```

**Depois**
```html
<div class="grid gap-5">
```

Resultado esperado:
- Redução de “vazios” horizontais e verticais em grids/listas.
- Mais densidade visual sem perder legibilidade.

### 3) Compactação vertical entre seções
**Antes**
```html
<section class="py-20 md:py-28">
```

**Depois**
```html
<section class="ct-section">
```

Resultado esperado:
- Entre seções internas, o “respiro” vertical fica mais compacto.
- Hero mantém escala original (não foi comprimido por `.ct-section`).

### 4) Breakpoints
**Antes**
- Spacing/containers não seguiam o padrão Elementor.

**Depois**
- Containers respondem a **<=767** e **<=1024** conforme o modelo.

### 5) Hero (full-bleed)
**Antes**
```html
<section class="... min-h-[45vh] ...">
  <div class="... py-20">...</div>
</section>
```

**Depois**
```html
<section class="ct-hero ...">
  <div class="ct-container ct-hero-inner">...</div>
</section>
```

Resultado esperado:
- Background/hero ocupando **100% da largura da viewport**.
- Conteúdo interno com padding **50px** (desktop) e **30px** (mobile) e altura mínima **640px**.

Resultado esperado:
- Regras de empilhamento e layout começam a responder já em 480px.

## Checklist de validação
- Abrir em **767px**: container limita em **767px** com gutters 10px.
- Abrir em **1024px**: container limita em **1024px** com gutters 10px.
- Abrir em **>=1025px**: container limita em **1140px**.
- Confirmar que os grids principais usam `gap-5` (20px).
- Confirmar ausência de erros no console.

## Evidências (screenshots)
Não consigo capturar screenshots automaticamente neste ambiente.
Para anexar evidências pixel-perfect, use:
- DevTools → Toggle device toolbar → larguras **767**, **1024** e **1440**
- DevTools → “Capture full size screenshot”
