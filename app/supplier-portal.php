<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/includes/helpers.php';
$db = Database::getInstance()->getConnection();
if (!isset($_SESSION['supplier_id'])) {
    header('Location: supplier-login.php', true, 302);
    exit;
}
$pageTitle = 'Portal do Fornecedor';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $pageTitle; ?></title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
</head>
<body class="bg-gray-100 min-h-screen">
  <div class="max-w-5xl mx-auto p-4 space-y-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Minhas Cotações</h1>
      <button id="logout" class="btn-secondary">Sair</button>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 text-left">Título</th>
              <th class="px-3 py-2 text-left">Prazo</th>
              <th class="px-3 py-2 text-left">Status</th>
              <th class="px-3 py-2 text-left">Ações</th>
            </tr>
          </thead>
          <tbody id="tbody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <div id="modalQuote" class="modal-overlay hidden" aria-hidden="true">
    <div class="modal-panel" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="quoteTitle">
      <div class="px-6 pt-6">
        <h3 id="quoteTitle" class="text-lg font-semibold">Responder Cotação</h3>
      </div>
      <div class="modal-body">
      <input type="hidden" id="rfqId">
      <div id="items" class="space-y-2"></div>
      </div>
      <div class="modal-actions flex justify-end space-x-2">
        <button id="closeQuote" class="btn-secondary">Cancelar</button>
        <button id="sendQuote" class="btn-primary">Enviar</button>
      </div>
    </div>
  </div>

  <template id="tplRow">
    <tr class="border-t">
      <td class="px-3 py-2 titulo"></td>
      <td class="px-3 py-2 prazo"></td>
      <td class="px-3 py-2 status"></td>
      <td class="px-3 py-2"><button class="px-3 py-1 bg-blue-600 text-white rounded responder">Responder</button></td>
    </tr>
  </template>

  <script>
  async function api(path, opts={}) {
    const res = await fetch('procurement-api.php' + path, { credentials:'same-origin', ...opts });
    const txt = await res.text(); try { return JSON.parse(txt); } catch { return { ok:false, message:'Resposta inválida', raw:txt }; }
  }
  async function loadList() {
    const r = await api('?action=supplier_rfq_list');
    const body = document.getElementById('tbody'); body.innerHTML = '';
    if (r.ok) {
      r.data.rfqs.forEach(row => {
        const t = document.getElementById('tplRow').content.cloneNode(true);
        t.querySelector('.titulo').textContent = row.titulo;
        t.querySelector('.prazo').textContent = row.prazo_resposta;
        t.querySelector('.status').textContent = row.status;
        t.querySelector('.responder').addEventListener('click', () => openQuote(row.id));
        body.appendChild(t);
      });
    }
  }
  async function openQuote(rfqId) {
    document.getElementById('rfqId').value = rfqId;
    const r = await api('?action=supplier_rfq_items&rfq_id=' + rfqId);
    const cont = document.getElementById('items'); cont.innerHTML = '';
    if (r.ok) {
      r.data.items.forEach(it => {
        const div = document.createElement('div'); div.className = 'grid grid-cols-12 gap-2';
        div.innerHTML = `
          <div class="col-span-5"><div class="text-sm font-medium">${it.nome}</div><div class="text-xs text-gray-500">Qtd: ${it.quantidade} ${it.unidade_medida||it.unidade}</div></div>
          <div class="col-span-3"><input data-prod="${it.produto_id}" class="form-input price" type="number" step="0.0001" placeholder="Preço Unitário"></div>
          <div class="col-span-2"><input class="form-input lead" type="number" step="1" placeholder="Prazo (dias)"></div>
          <div class="col-span-2"><input class="form-input note" placeholder="Obs."></div>
        `;
        cont.appendChild(div);
      });
      document.getElementById('modalQuote').classList.remove('hidden');
    } else { alert(r.message); }
  }
  function closeQuote(){ modalClose('modalQuote'); }
  async function sendQuote() {
    const rfqId = parseInt(document.getElementById('rfqId').value, 10);
    const itens = Array.from(document.querySelectorAll('#items .grid')).map(el => ({
      produto_id: parseInt(el.querySelector('.price').dataset.prod, 10),
      preco_unitario: parseFloat(el.querySelector('.price').value || '0'),
      prazo_entrega: parseInt(el.querySelector('.lead').value || '0', 10),
      observacoes: el.querySelector('.note').value || null
    })).filter(i => i.preco_unitario > 0);
    const r = await api('?action=supplier_quote_submit', { method:'POST', body: JSON.stringify({ rfq_id: rfqId, itens }) });
    if (r.ok) { closeQuote(); loadList(); } else { alert(r.message); }
  }
  document.getElementById('logout').addEventListener('click', async () => { await api('?action=supplier_logout'); window.location.href='supplier-login.php'; });
  document.getElementById('closeQuote').addEventListener('click', closeQuote);
  document.getElementById('sendQuote').addEventListener('click', sendQuote);
  loadList();
  </script>
</body>
</html>
