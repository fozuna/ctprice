<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/classes/Auth.php';
$db = Database::getInstance()->getConnection();
$auth = new Auth($db);
if (!$auth->isLoggedIn() || !in_array($_SESSION['user_role'] ?? null, [ROLE_ADMIN, ROLE_COORD_RH], true)) { header('Location: login.php'); exit; }
$pageTitle = 'Dados Manuais RH';
include __DIR__ . '/includes/header.php';
?>
<div class="max-w-7xl mx-auto space-y-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-rich-black">Dados Manuais do RH</h1>
    <div class="space-x-2">
      <a href="rh-dashboard.php" class="px-3 py-1.5 rounded bg-eggshell border border-silver-lake-blue text-paynes-gray hover:bg-silver-lake-blue hover:text-white">Voltar ao Dashboard</a>
      <a href="rh-data-api.php?action=export_csv&type=hires" class="px-3 py-1.5 rounded bg-paynes-gray text-white hover:bg-prussian-blue">Exportar Contratações CSV</a>
      <a href="rh-data-api.php?action=export_csv&type=terms" class="px-3 py-1.5 rounded bg-paynes-gray text-white hover:bg-prussian-blue">Exportar Demissões CSV</a>
      <a href="rh-data-api.php?action=export_csv&type=counts" class="px-3 py-1.5 rounded bg-paynes-gray text-white hover:bg-prussian-blue">Exportar Ativos CSV</a>
      <a href="rh-data-api.php?action=export_csv&type=cc" class="px-3 py-1.5 rounded bg-paynes-gray text-white hover:bg-prussian-blue">Exportar Centros CSV</a>
    </div>
  </div>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <section class="bg-white border rounded-lg p-4">
      <h2 class="text-lg font-semibold mb-3">Registrar Contratação</h2>
      <form id="fHire" class="space-y-3">
        <input class="form-input" placeholder="Nome do colaborador" id="hireName" required>
        <input class="form-input" type="date" placeholder="Data de admissão" id="hireDate" required>
        <input class="form-input" placeholder="Cargo" id="hireRole" required>
        <select class="form-select" id="hireContract" required>
          <option value="">Tipo de contrato</option>
          <option>CLT</option><option>PJ</option><option>INTEGRAL</option><option>MEIO_PERIODO</option>
        </select>
        <div class="grid grid-cols-3 gap-2">
          <input class="form-input col-span-2" placeholder="Centro de custo (ID)" id="hireCC" required>
          <input class="form-input" placeholder="Fonte (opcional)" id="hireSrc">
        </div>
        <textarea class="form-textarea" placeholder="Observações (opcional)" id="hireNotes"></textarea>
        <div class="text-right"><button type="button" id="btnHire" class="btn-primary">Salvar</button></div>
      </form>
      <div class="mt-4">
        <h3 class="font-medium mb-2">Últimas contratações</h3>
        <div id="listHires" class="space-y-2 text-sm"></div>
      </div>
    </section>
    <section class="bg-white border rounded-lg p-4">
      <h2 class="text-lg font-semibold mb-3">Registrar Demissão</h2>
      <form id="fTerm" class="space-y-3">
        <input class="form-input" placeholder="Nome do colaborador" id="termName" required>
        <input class="form-input" type="date" placeholder="Data de desligamento" id="termDate" required>
        <input class="form-input" placeholder="Centro de custo (ID)" id="termCC" required>
        <input class="form-input" placeholder="Motivo" id="termReason" required>
        <textarea class="form-textarea" placeholder="Observações (opcional)" id="termNotes"></textarea>
        <div class="text-right"><button type="button" id="btnTerm" class="btn-primary">Salvar</button></div>
      </form>
      <div class="mt-4">
        <h3 class="font-medium mb-2">Últimas demissões</h3>
        <div id="listTerms" class="space-y-2 text-sm"></div>
      </div>
    </section>
    <section class="bg-white border rounded-lg p-4">
      <h2 class="text-lg font-semibold mb-3">Atualizar Total de Ativos</h2>
      <form id="fCount" class="space-y-3">
        <input class="form-input" type="date" id="countDate" value="<?php echo date('Y-m-d'); ?>" required>
        <input class="form-input" type="number" id="countTotal" placeholder="Total de ativos" required>
        <input class="form-input" id="countDept" placeholder="Departamento (opcional)">
        <input class="form-input" id="countReason" placeholder="Motivo (opcional)">
        <div class="text-right"><button type="button" id="btnCount" class="btn-primary">Salvar</button></div>
      </form>
      <div class="mt-4">
        <h3 class="font-medium mb-2">Histórico</h3>
        <div id="listCounts" class="space-y-2 text-sm"></div>
      </div>
    </section>
    <section class="bg-white border rounded-lg p-4">
      <h2 class="text-lg font-semibold mb-3">Centros de Custo</h2>
      <form id="fCC" class="space-y-3">
        <input class="form-input" id="ccName" placeholder="Nome" required>
        <input class="form-input" id="ccCode" placeholder="Código (opcional)">
        <input class="form-input" id="ccParent" placeholder="Parent ID (opcional)">
        <input class="form-input" id="ccDept" placeholder="Departamento (opcional)">
        <select class="form-select" id="ccActive">
          <option value="1">Ativo</option><option value="0">Inativo</option>
        </select>
        <div class="text-right"><button type="button" id="btnCC" class="btn-primary">Salvar</button></div>
      </form>
      <div class="mt-4">
        <h3 class="font-medium mb-2">Lista</h3>
        <div id="listCC" class="space-y-2 text-sm"></div>
      </div>
    </section>
  </div>
</div>
<script>
async function api(action, method='GET', body){ const opt={method, credentials:'same-origin'}; if(body){ opt.body=JSON.stringify(body); } const r=await fetch('rh-data-api.php?action='+encodeURIComponent(action), opt); const t=await r.text(); try{ return JSON.parse(t);}catch{return {ok:false,message:'Resposta inválida',raw:t};}}
function esc(s){ return (s==null?'':String(s)).replace(/[&<>]/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[m]));}
async function loadLists(){
  let j = await api('list_hires'); const h = document.getElementById('listHires'); h.innerHTML='';
  if(j.ok){ j.data.rows.forEach(r=>{ h.innerHTML += '<div class="border border-silver-lake-blue rounded p-2">'+esc(r.employee_name)+' • '+esc(r.admission_date)+' • CC '+esc(r.cost_center_id)+' • '+esc(r.contract_type)+'</div>'; }); }
  j = await api('list_terms'); const t = document.getElementById('listTerms'); t.innerHTML='';
  if(j.ok){ j.data.rows.forEach(r=>{ t.innerHTML += '<div class="border border-silver-lake-blue rounded p-2">'+esc(r.employee_name)+' • '+esc(r.termination_date)+' • CC '+esc(r.cost_center_id)+' • '+esc(r.reason)+'</div>'; }); }
  j = await api('list_counts'); const c = document.getElementById('listCounts'); c.innerHTML='';
  if(j.ok){ j.data.rows.forEach(r=>{ c.innerHTML += '<div class="border border-silver-lake-blue rounded p-2">'+esc(r.recorded_at)+' • Total: '+esc(r.total_active)+(r.department?(' • '+esc(r.department)):'')+'</div>'; }); }
  j = await api('list_cc'); const cc = document.getElementById('listCC'); cc.innerHTML='';
  if(j.ok){ j.data.rows.forEach(r=>{ cc.innerHTML += '<div class="border border-silver-lake-blue rounded p-2">'+esc(r.name)+' '+(r.code?('('+esc(r.code)+')'):'')+(r.department?(' • '+esc(r.department)):'')+'</div>'; }); }
}
document.getElementById('btnHire').addEventListener('click', async ()=>{
  const payload = { employee_name: document.getElementById('hireName').value, admission_date: document.getElementById('hireDate').value, cost_center_id: parseInt(document.getElementById('hireCC').value||'0',10), role_title: document.getElementById('hireRole').value, contract_type: document.getElementById('hireContract').value, source: document.getElementById('hireSrc').value, notes: document.getElementById('hireNotes').value };
  const j = await api('create_hire','POST',payload); if(!j.ok){ alert(j.message);} else { loadLists(); }
});
document.getElementById('btnTerm').addEventListener('click', async ()=>{
  const payload = { employee_name: document.getElementById('termName').value, termination_date: document.getElementById('termDate').value, cost_center_id: parseInt(document.getElementById('termCC').value||'0',10), reason: document.getElementById('termReason').value, notes: document.getElementById('termNotes').value };
  const j = await api('create_term','POST',payload); if(!j.ok){ alert(j.message);} else { loadLists(); }
});
document.getElementById('btnCount').addEventListener('click', async ()=>{
  const payload = { recorded_at: document.getElementById('countDate').value, total_active: parseInt(document.getElementById('countTotal').value||'0',10), department: document.getElementById('countDept').value, reason: document.getElementById('countReason').value };
  const j = await api('create_count','POST',payload); if(!j.ok){ alert(j.message);} else { loadLists(); }
});
document.getElementById('btnCC').addEventListener('click', async ()=>{
  const payload = { name: document.getElementById('ccName').value, code: document.getElementById('ccCode').value, parent_id: parseInt(document.getElementById('ccParent').value||'0',10)||null, department: document.getElementById('ccDept').value, active: parseInt(document.getElementById('ccActive').value,10) };
  const j = await api('create_cc','POST',payload); if(!j.ok){ alert(j.message);} else { loadLists(); }
});
loadLists();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
