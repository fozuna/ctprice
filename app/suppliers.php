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
$pageTitle = 'Fornecedores';
include __DIR__ . '/includes/header.php';
?>
<div class="bg-white rounded-lg shadow p-4">
  <div class="flex items-center justify-between mb-3">
    <h3 class="text-lg font-semibold">Fornecedores</h3>
    <button id="btnNew" class="btn-primary">Novo Fornecedor</button>
  </div>
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-3 py-2 text-left">Razão Social</th>
          <th class="px-3 py-2 text-left">CNPJ</th>
          <th class="px-3 py-2 text-left">Email</th>
          <th class="px-3 py-2 text-left">Telefone</th>
          <th class="px-3 py-2 text-left">Contato</th>
          <th class="px-3 py-2 text-left">Status</th>
          <th class="px-3 py-2 text-left">Ações</th>
        </tr>
      </thead>
      <tbody id="tbody"></tbody>
    </table>
  </div>
</div>

<div id="modal" class="modal-overlay hidden" aria-hidden="true">
  <div class="modal-panel" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="supplierTitle">
    <div class="px-6 pt-6">
      <h3 id="supplierTitle" class="text-lg font-semibold">Fornecedor</h3>
    </div>
    <div class="modal-body">
    <input type="hidden" id="fId">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div><label class="form-label">Razão Social</label><input id="fRazao" class="form-input"></div>
      <div><label class="form-label">CNPJ</label><input id="fCnpj" class="form-input"></div>
      <div><label class="form-label">Email</label><input id="fEmail" class="form-input"></div>
      <div><label class="form-label">Telefone</label><input id="fTel" class="form-input"></div>
      <div><label class="form-label">Contato</label><input id="fContato" class="form-input"></div>
      <div>
        <label class="form-label">Status</label>
        <select id="fStatus" class="form-select"><option value="1">Ativo</option><option value="0">Inativo</option></select>
      </div>
      <div class="md:col-span-2"><label class="form-label">Senha (Portal do Fornecedor)</label><input id="fPass" type="password" class="form-input"></div>
    </div>
    </div>
    <div class="modal-actions flex justify-end space-x-2">
      <button id="cancel" class="btn-secondary">Cancelar</button>
      <button id="save" class="btn-primary">Salvar</button>
    </div>
  </div>
</div>

<template id="tpl">
  <tr class="border-t">
    <td class="px-3 py-2 razao"></td>
    <td class="px-3 py-2 cnpj"></td>
    <td class="px-3 py-2 email"></td>
    <td class="px-3 py-2 tel"></td>
    <td class="px-3 py-2 contato"></td>
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
  const r = await api('?action=suppliers_list');
  const body = document.getElementById('tbody'); body.innerHTML='';
  if (r.ok) {
    r.data.suppliers.forEach(s => {
      const t = document.getElementById('tpl').content.cloneNode(true);
      t.querySelector('.razao').textContent = s.razao_social;
      t.querySelector('.cnpj').textContent = s.cnpj;
      t.querySelector('.email').textContent = s.email;
      t.querySelector('.tel').textContent = s.telefone || '-';
      t.querySelector('.contato').textContent = s.nome_contato || '-';
      t.querySelector('.status').textContent = s.status ? 'Ativo' : 'Inativo';
      t.querySelector('.edit').addEventListener('click', () => openModal(s));
      body.appendChild(t);
    });
  }
}
function openModal(data={}) {
  modalOpen('modal');
  document.getElementById('fId').value = data.id || '';
  document.getElementById('fRazao').value = data.razao_social || '';
  document.getElementById('fCnpj').value = data.cnpj || '';
  document.getElementById('fEmail').value = data.email || '';
  document.getElementById('fTel').value = data.telefone || '';
  document.getElementById('fContato').value = data.nome_contato || '';
  document.getElementById('fStatus').value = (data.status!=null? String(data.status):'1');
  document.getElementById('fPass').value = '';
}
function closeModal(){ modalClose('modal'); }
async function save() {
  const payload = {
    id: document.getElementById('fId').value || undefined,
    razao_social: document.getElementById('fRazao').value,
    cnpj: document.getElementById('fCnpj').value,
    email: document.getElementById('fEmail').value,
    telefone: document.getElementById('fTel').value,
    nome_contato: document.getElementById('fContato').value,
    status: parseInt(document.getElementById('fStatus').value, 10),
    password: document.getElementById('fPass').value || undefined
  };
  const r = await api('?action=suppliers_save', { method:'POST', body: JSON.stringify(payload) });
  if (r.ok) { closeModal(); loadList(); } else { alert(r.message); }
}
document.getElementById('btnNew').addEventListener('click', ()=>openModal({}));
document.getElementById('cancel').addEventListener('click', closeModal);
document.getElementById('save').addEventListener('click', save);
loadList();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
