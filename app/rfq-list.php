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
$pageTitle = 'Solicitações de Cotação';
include __DIR__ . '/includes/header.php';
?>
<div class="bg-white rounded-lg shadow p-4">
  <div class="flex items-center justify-between mb-3">
    <h3 class="text-lg font-semibold">Solicitações</h3>
    <a href="purchases-dashboard.php" class="btn-secondary">Voltar ao Dashboard</a>
  </div>
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-3 py-2 text-left">Título</th>
          <th class="px-3 py-2 text-left">Criada em</th>
          <th class="px-3 py-2 text-left">Prazo</th>
          <th class="px-3 py-2 text-left">Status</th>
          <th class="px-3 py-2 text-left">Respostas</th>
          <th class="px-3 py-2 text-left">Ações</th>
        </tr>
      </thead>
      <tbody id="tbody"></tbody>
    </table>
  </div>
</div>
<template id="tpl">
  <tr class="border-t">
    <td class="px-3 py-2 titulo"></td>
    <td class="px-3 py-2 criada"></td>
    <td class="px-3 py-2 prazo"></td>
    <td class="px-3 py-2 status"></td>
    <td class="px-3 py-2 respostas"></td>
    <td class="px-3 py-2 space-x-1">
      <button class="px-3 py-1 bg-blue-600 text-white rounded invite">Convidar</button>
      <button class="px-3 py-1 bg-green-600 text-white rounded compare">Comparar</button>
    </td>
  </tr>
</template>
<script>
async function api(path, opts={}) {
  const res = await fetch('procurement-api.php' + path, { credentials:'same-origin', ...opts });
  const txt = await res.text(); try { return JSON.parse(txt); } catch { return { ok:false, message:'Resposta inválida', raw:txt }; }
}
async function loadList() {
  const r = await api('?action=rfq_list');
  const body = document.getElementById('tbody'); body.innerHTML='';
  if (r.ok) {
    r.data.rfqs.forEach(row => {
      const t = document.getElementById('tpl').content.cloneNode(true);
      t.querySelector('.titulo').textContent = row.titulo;
      t.querySelector('.criada').textContent = row.created_at;
      t.querySelector('.prazo').textContent = row.prazo_resposta;
      t.querySelector('.status').textContent = row.status;
      t.querySelector('.respostas').textContent = row.respostas + '/' + row.convidados;
      t.querySelector('.invite').addEventListener('click', () => window.location.href='purchases-dashboard.php');
      t.querySelector('.compare').addEventListener('click', () => window.open('purchases-dashboard.php','_self')); // reaproveita compare lá
      body.appendChild(t);
    });
  }
}
loadList();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
