# Menu Lateral Responsivo

## Objetivo
Fixar o menu na lateral, manter visível durante a rolagem e habilitar scroll interno quando o conteúdo exceder a altura da viewport.

## Classes CSS
- `sidebar-fixed`: posição fixa, altura total da viewport e layout em coluna.
  - `position: fixed; top: 0; left: 0; width: 16rem; height: 100vh; display: flex; flex-direction: column; overflow: hidden;`
- `sidebar-scroll`: área rolável interna do menu.
  - `flex: 1 1 auto; overflow-y: auto; overscroll-behavior: contain;`
- `main-with-sidebar`: margem esquerda para o conteúdo principal não sobrepor o menu.
  - `margin-left: 16rem;` e `@media (max-width: 768px) { margin-left: 0; }`

## Exemplo de Uso (HTML)

```html
<div class="sidebar-fixed bg-rich-black text-white">
  <div class="p-4">Logo e título</div>
  <nav class="sidebar-scroll mt-8">
    <!-- itens do menu -->
  </nav>
  <div class="p-4 border-t border-prussian-blue">
    <!-- usuário -->
  </div>
</div>

<div class="main-with-sidebar">
  <!-- conteúdo principal -->
</div>
```

## Responsividade
- Em telas pequenas (`max-width: 768px`), a margem do conteúdo principal é removida e o menu pode ser aberto/fechado.
- O menu permanece visível e com scroll interno em telas maiores.

## Notas de Implementação
- O menu utiliza Tailwind para cores/espacamentos; as classes acima garantem posicionamento e comportamento de rolagem.
- Em mobile, a abertura/fechamento é feita com Alpine.js alternando `translate-x-0`/`-translate-x-full`.
