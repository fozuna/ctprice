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
$pageTitle = 'Catálogo de Produtos';
include __DIR__ . '/includes/header.php';
?>
<div class="bg-white rounded-lg shadow p-4">
  <div class="flex items-center justify-between mb-3">
    <h3 class="text-lg font-semibold">Produtos</h3>
    <button id="btnNew" class="btn-primary">Novo Produto</button>
  </div>
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-3 py-2 text-left">Nome</th>
          <th class="px-3 py-2 text-left">Unidade</th>
          <th class="px-3 py-2 text-left">Categoria</th>
          <th class="px-3 py-2 text-left">Status</th>
          <th class="px-3 py-2 text-left">Ações</th>
        </tr>
      </thead>
      <tbody id="tbody"></tbody>
    </table>
  </div>
</div>

<div id="modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center">
  <div class="bg-white w-full max-w-xl p-6 rounded-lg shadow space-y-4">
    <h3 class="text-lg font-semibold">Produto</h3>
    <input type="hidden" id="pId">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="md:col-span-2"><label class="form-label">Nome</label><input id="pNome" class="form-input"></div>
      <div><label class="form-label">Unidade de Medida</label><input id="pUM" class="form-input"></div>
      <div><label class="form-label">Categoria</label><input id="pCat" class="form-input"></div>
      <div class="md:col-span-2"><label class="form-label">Descrição</label><textarea id="pDesc" class="form-textarea"></textarea></div>
      <div>
        <label class="form-label">Status</label>
        <select id="pStatus" class="form-select"><option value="1">Ativo</option><option value="0">Inativo</option></select>
      </div>
    </div>
    <div class="flex justify-end space-x-2">
      <button id="cancel" class="btn-secondary">Cancelar</button>
      <button id="save" class="btn-primary">Salvar</button>
    </div>
  </div>
</div>

<template id="tpl">
  <tr class="border-t">
    <td class="px-3 py-2 nome"></td>
    <td class="px-3 py-2 um"></td>
    <td class="px-3 py-2 cat"></td>
    <td class="px-3 py-2 status"></td>
    <td class="px-3 py-2"><button class="px-3 py-1 bg-blue-600 text-white rounded edit">Editar</button></td>
  </tr>
</template>

<script>
async function api(path, opts={}) {
  const res = await fetch('procurement-api.php' + path, { credentials:'same-origin', ...opts });
  const txt = await res.text(); try { return JSON.parse(txt); } catch { return { ok:false, message:'Resposta inválida', raw:txt }; }
}
async function loadList() {
  const r = await api('?action=products_list');
  const body = document.getElementById('tbody'); body.innerHTML='';
  if (r.ok) {
    r.data.products.forEach(p => {
      const t = document.getElementById('tpl').content.cloneNode(true);
      t.querySelector('.nome').textContent = p.nome;
      t.querySelector('.um').textContent = p.unidade_medida;
      t.querySelector('.cat').textContent = p.categoria || '-';
      t.querySelector('.status').textContent = p.status ? 'Ativo' : 'Inativo';
      t.querySelector('.edit').addEventListener('click', () => openModal(p));
      body.appendChild(t);
    });
  }
}
function openModal(data={}) {
  document.getElementById('modal').classList.remove('hidden');
  document.getElementById('pId').value = data.id || '';
  document.getElementById('pNome').value = data.nome || '';
  document.getElementById('pUM').value = data.unidade_medida || '';
  document.getElementById('pCat').value = data.categoria || '';
  document.getElementById('pDesc').value = data.descricao || '';
  document.getElementById('pStatus').value = (data.status!=null? String(data.status):'1');
}
function closeModal(){ document.getElementById('modal').classList.add('hidden'); }
async function save() {
  const payload = {
    id: document.getElementById('pId').value || undefined,
    nome: document.getElementById('pNome').value,
    unidade_medida: document.getElementById('pUM').value,
    categoria: document.getElementById('pCat').value,
    descricao: document.getElementById('pDesc').value,
    status: parseInt(document.getElementById('pStatus').value, 10)
  };
  const r = await api('?action=products_save', { method:'POST', body: JSON.stringify(payload) });
  if (r.ok) { closeModal(); loadList(); } else { alert(r.message); }
}
document.getElementById('btnNew').addEventListener('click', ()=>openModal({}));
document.getElementById('cancel').addEventListener('click', closeModal);
document.getElementById('save').addEventListener('click', save);
loadList();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
