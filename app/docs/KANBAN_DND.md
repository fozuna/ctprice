# Kanban: Drag-and-Drop (Chrome) — Diagnóstico e Correção

## Sintomas
- No Chrome, arrastar e soltar cards pode falhar quando o usuário tenta soltar **em cima de outro card**.
- Em alguns casos, arrastar “parece não funcionar” quando o usuário inicia o drag por cima de links dentro do card (ex.: link de arquivo), porque o Chrome prioriza o drag do link.
- A reordenação pode não persistir corretamente se a ordenação anterior ficar “residual” no banco.

## Causa raiz
1. **Alvo do drop**: quando o usuário solta em cima de outro card, o evento pode cair no próprio card (ou em elementos internos), e não no container da coluna. Se a lógica assume `currentTarget` sempre como coluna, `stage` pode ficar ausente e o drop é ignorado.
2. **dataTransfer**: em DnD nativo, alguns fluxos no Chrome são mais estáveis quando `dataTransfer.setData(...)` é definido no `dragstart` e recuperado no `drop`.
3. **Links dentro do card**: `<a>` é tipicamente “draggable” no Chrome; ao iniciar o arraste em cima do link, o navegador pode tentar arrastar o URL em vez do card.
4. **Persistência de ordem**: ao aplicar uma nova ordem parcial, `sort_order` antigo pode continuar existindo e interferir na ordenação após reload.

## Solução aplicada
### Frontend
- Mantém `draggable="true"` no card.
- Define `dataTransfer.setData('text/plain', <id>)` no `dragstart` e usa fallback `getData('text/plain')` no `drop`.
- Trata o destino do drop via `closest('[data-stage]')` para suportar drop sobre qualquer elemento dentro da coluna.
- Adiciona `draggable="false"` em botões/links dentro do card para evitar que o Chrome “arraste o link” ao invés do card.
- Adiciona `ondragenter` com `preventDefault()` como reforço de compatibilidade.

### Backend
- Centraliza a lógica de `sort_order` em `includes/kanban_sort_order.php`.
- Ao persistir uma nova ordem de uma etapa, limpa `sort_order` daquela etapa antes de gravar a nova sequência.

## Como reproduzir e validar
1. Abrir `rh-kanban.php` no Chrome.
2. Em uma coluna com vários cards:
   - Arrastar um card e soltar **no topo**.
   - Arrastar um card e soltar **no meio** (sobre outro card).
   - Arrastar um card e soltar **no fim**.
3. Recarregar a página e confirmar que a ordem se mantém.

## Debug
- Ativar logs no console: abrir `rh-kanban.php?kanban_debug=1`.
- O console imprime eventos `dragstart/dragenter/dragleave/dragover/drop/dragend` com:
  - `card_id`, `stage`, `dragId`, `dataTransfer.types`.

## Testes
### Unitário (CLI)
- `php tests/kanban_sort_order_test.php`

### Funcional (browser)
- Abrir `tests/kanban_dnd_functional_test.html` no navegador.
- A página executa cenários de drop no início/meio/fim, move entre colunas e reversão em falha.

