<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/classes/Auth.php';
$db = Database::getInstance()->getConnection();
$auth = new Auth($db);
if (!$auth->isLoggedIn() || !$auth->hasPermission(ROLE_COMPRAS)) {
    header('Location: login.php', true, 302);
    exit;
}
$pageTitle = 'Dashboard de Compras';
include __DIR__ . '/includes/header.php';
?>
<div class="space-y-6">
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="bg-white rounded-lg shadow p-4">
      <div class="text-sm text-gray-500">Cotações Abertas</div>
      <div id="m-open" class="text-3xl font-bold">-</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
      <div class="text-sm text-gray-500">Cotações Respondidas</div>
      <div id="m-responded" class="text-3xl font-bold">-</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
      <div class="text-sm text-gray-500">Fornecedores Pendentes</div>
      <div id="m-pending" class="text-3xl font-bold">-</div>
    </div>
  </div>

  <div class="bg-white rounded-lg shadow p-4">
    <div class="flex items-center justify-between mb-3">
      <h3 class="text-lg font-semibold">Solicitações de Cotação</h3>
      <button id="btnNewRfq" class="btn-primary">Nova Cotação</button>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-3 py-2 text-left">Título</th>
            <th class="px-3 py-2 text-left">Prazo</th>
            <th class="px-3 py-2 text-left">Status</th>
            <th class="px-3 py-2 text-left">Respostas</th>
            <th class="px-3 py-2 text-left">Ações</th>
          </tr>
        </thead>
        <tbody id="rfqBody"></tbody>
      </table>
    </div>
  </div>
</div>

<template id="tplRfqRow">
  <tr class="border-t">
    <td class="px-3 py-2 rfq-title"></td>
    <td class="px-3 py-2 rfq-deadline"></td>
    <td class="px-3 py-2 rfq-status"></td>
    <td class="px-3 py-2 rfq-answers"></td>
    <td class="px-3 py-2 space-x-2">
      <button class="px-3 py-1 bg-blue-600 text-white rounded rfq-invite">Convidar</button>
      <button class="px-3 py-1 bg-green-600 text-white rounded rfq-compare">Comparar</button>
    </td>
  </tr>
  </template>

<!-- Modal Nova RFQ -->
<div id="modalRfq" class="modal-overlay hidden" aria-hidden="true">
  <div class="modal-panel" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="rfqTitle">
    <div class="px-6 pt-6">
      <h3 id="rfqTitle" class="text-lg font-semibold">Nova Solicitação de Cotação</h3>
    </div>
    <div class="modal-body">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="form-label">Título</label>
        <input id="rfqTitulo" class="form-input"/>
      </div>
      <div>
        <label class="form-label">Prazo de Resposta</label>
        <input id="rfqPrazo" type="datetime-local" class="form-input"/>
      </div>
      <div class="md:col-span-2">
        <label class="form-label">Descrição</label>
        <textarea id="rfqDesc" class="form-textarea"></textarea>
      </div>
      </div>
      <div class="mt-4">
      <h4 class="font-medium mb-2">Itens</h4>
      <div id="itens" class="space-y-2"></div>
      <button id="addItem" class="btn-secondary mt-2">Adicionar Item</button>
      </div>
    </div>
    <div class="modal-actions flex justify-end space-x-2">
      <button id="closeModal" class="btn-secondary">Cancelar</button>
      <button id="saveRfq" class="btn-primary">Salvar</button>
    </div>
  </div>
  </div>

<!-- Modal Convites -->
<div id="modalInvite" class="modal-overlay hidden" aria-hidden="true">
  <div class="modal-panel" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="inviteTitle">
    <div class="px-6 pt-6">
      <h3 id="inviteTitle" class="text-lg font-semibold">Convidar Fornecedores</h3>
    </div>
    <div class="modal-body">
      <div id="inviteList" class="max-h-80 overflow-y-auto border rounded p-2"></div>
    </div>
    <div class="modal-actions flex justify-end space-x-2">
      <button id="closeInvite" class="btn-secondary">Fechar</button>
      <button id="doInvite" class="btn-primary">Enviar Convites</button>
    </div>
  </div>
</div>

<script>
async function api(path, opts={}) {
  const res = await fetch('procurement-api.php' + path, { credentials: 'same-origin', ...opts });
  const txt = await res.text();
  try { return JSON.parse(txt); } catch { return { ok:false, message:'Resposta inválida', raw:txt }; }
}
async function loadMetrics() {
  const r = await api('?action=dashboard_counts');
  if (r.ok) {
    document.getElementById('m-open').textContent = r.data.abertas;
    document.getElementById('m-responded').textContent = r.data.respondidas;
    document.getElementById('m-pending').textContent = r.data.pendentes;
  }
}
async function loadRfqs() {
  const r = await api('?action=rfq_list');
  const body = document.getElementById('rfqBody');
  body.innerHTML = '';
  if (r.ok) {
    r.data.rfqs.forEach(row => {
      const t = document.getElementById('tplRfqRow').content.cloneNode(true);
      t.querySelector('.rfq-title').textContent = row.titulo;
      t.querySelector('.rfq-deadline').textContent = row.prazo_resposta;
      t.querySelector('.rfq-status').textContent = row.status;
      t.querySelector('.rfq-answers').textContent = row.respostas + '/' + row.convidados;
      t.querySelector('.rfq-invite').addEventListener('click', () => openInvite(row.id));
      t.querySelector('.rfq-compare').addEventListener('click', () => openCompare(row.id));
      body.appendChild(t);
    });
  }
}
function itemRow() {
  const div = document.createElement('div');
  div.className = 'grid grid-cols-12 gap-2';
  div.innerHTML = `
    <input class="form-input col-span-6 item-prod" placeholder="Produto ID"/>
    <input class="form-input col-span-3 item-qty" type="number" step="0.001" placeholder="Qtd"/>
    <input class="form-input col-span-3 item-obs" placeholder="Obs. (opcional)"/>
  `;
  return div;
}
function openModal() {
  document.getElementById('modalRfq').classList.remove('hidden');
  if (!document.querySelector('#itens .grid')) document.getElementById('itens').appendChild(itemRow());
}
function closeModal() {
  document.getElementById('modalRfq').classList.add('hidden');
  document.getElementById('rfqTitulo').value = '';
  document.getElementById('rfqPrazo').value = '';
  document.getElementById('rfqDesc').value = '';
  document.getElementById('itens').innerHTML = '';
}
async function saveRfq() {
  const itens = Array.from(document.querySelectorAll('#itens .grid')).map(el => ({
    produto_id: parseInt(el.querySelector('.item-prod').value, 10),
    quantidade: parseFloat(el.querySelector('.item-qty').value || '0'),
    observacoes: el.querySelector('.item-obs').value || null
  })).filter(i => i.produto_id && i.quantidade > 0);
  const payload = {
    titulo: document.getElementById('rfqTitulo').value,
    descricao: document.getElementById('rfqDesc').value,
    prazo_resposta: document.getElementById('rfqPrazo').value,
    itens
  };
  const r = await api('?action=rfq_create', { method:'POST', body: JSON.stringify(payload) });
  if (r.ok) {
    closeModal(); loadRfqs(); loadMetrics();
  } else { alert(r.message); }
}
async function openInvite(rfqId) {
  const cont = document.getElementById('inviteList'); cont.innerHTML = 'Carregando...';
  const r = await api('?action=suppliers_list');
  if (!r.ok) { alert(r.message); return; }
  cont.innerHTML = '';
  r.data.suppliers.forEach(s => {
    const div = document.createElement('label'); div.className = 'flex items-center gap-2 py-1';
    const cb = document.createElement('input'); cb.type = 'checkbox'; cb.value = s.id;
    div.appendChild(cb); div.appendChild(document.createTextNode(s.razao_social + ' (' + s.email + ')'));
    cont.appendChild(div);
  });
  document.getElementById('modalInvite').dataset.rfqId = rfqId;
  document.getElementById('modalInvite').classList.remove('hidden');
}
function closeInvite(){ document.getElementById('modalInvite').classList.add('hidden'); }
async function doInvite() {
  const rfqId = parseInt(document.getElementById('modalInvite').dataset.rfqId, 10);
  const ids = Array.from(document.querySelectorAll('#inviteList input[type=checkbox]:checked')).map(i => parseInt(i.value,10));
  const r = await api('?action=rfq_invite', { method:'POST', body: JSON.stringify({ rfq_id: rfqId, suppliers: ids }) });
  if (r.ok) { closeInvite(); loadRfqs(); loadMetrics(); } else { alert(r.message); }
}
async function openCompare(rfqId) {
  const r = await api('?action=rfq_compare&rfq_id=' + rfqId);
  if (!r.ok) { alert(r.message); return; }
  const { suppliers, items, totals, best_supplier } = r.data;
  const cols = ['Produto'].concat(suppliers.map(s => s.razao_social)).concat(['Melhor Preço']);
  let html = '<div class=\"overflow-auto\"><table class=\"min-w-full text-xs\"><thead class=\"bg-gray-50\"><tr>' + cols.map(c=>'<th class=\"px-2 py-1 text-left\">'+c+'</th>').join('') + '</tr></thead><tbody>';
  items.forEach(it => {
    html += '<tr class=\"border-t\"><td class=\"px-2 py-1\">'+it.nome+' ('+it.quantidade+' '+it.unidade+')</td>';
    suppliers.forEach(s => {
      const v = it.precos[s.id] ?? '-';
      const cls = (it.melhor_fornecedor===s.id)?'bg-green-100 font-semibold':'';
      html += '<td class=\"px-2 py-1 '+cls+'\">'+(v==='-'?'-':('R$ '+v.toFixed(4)))+'</td>';
    });
    html += '<td class=\"px-2 py-1\">'+(it.melhor_preco?('R$ '+it.melhor_preco.toFixed(4)):'-')+'</td></tr>';
  });
  html += '<tr class=\"border-t font-semibold\"><td class=\"px-2 py-1\">Totais</td>';
  suppliers.forEach(s => {
    const t = totals[String(s.id)] ?? totals[s.id] ?? 0;
    const cls = (best_supplier===s.id)?'bg-green-200':''; 
    html += '<td class=\"px-2 py-1 '+cls+'\">R$ '+(t? t.toFixed(2):'0.00')+'</td>';
  });
  html += '<td class=\"px-2 py-1\">&nbsp;</td></tr></tbody></table></div>';
  const w = window.open('', '_blank');
  w.document.write('<title>Comparação de Preços</title>' + html);
  w.document.close();
}
document.getElementById('btnNewRfq').addEventListener('click', () => modalOpen('modalRfq'));
document.getElementById('closeModal').addEventListener('click', () => modalClose('modalRfq'));
document.getElementById('addItem').addEventListener('click', () => document.getElementById('itens').appendChild(itemRow()));
document.getElementById('saveRfq').addEventListener('click', saveRfq);
document.getElementById('closeInvite').addEventListener('click', () => modalClose('modalInvite'));
document.getElementById('doInvite').addEventListener('click', doInvite);
loadMetrics(); loadRfqs();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
