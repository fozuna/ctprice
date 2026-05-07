<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/models/JobCandidate.php';
$db = Database::getInstance()->getConnection();
$auth = new Auth($db);
if (!$auth->isLoggedIn() || !in_array($_SESSION['user_role'] ?? null, [ROLE_ADMIN, ROLE_COORD_RH], true)) { header('Location: login.php'); exit; }
$pageTitle = 'Agendar Entrevista';
include __DIR__ . '/includes/header.php';
// Carregar candidatos elegíveis
$candModel = new JobCandidate($db);
$stmt = $db->prepare("SELECT c.id, c.name, v.title AS vacancy_title
                        FROM job_candidates c
                        JOIN stages s ON s.id = c.stage_id
                   LEFT JOIN job_vacancies v ON v.id = c.vacancy_id
                       WHERE s.code IN ('RECEBIDO','IA','RH','REPROV_PESQ')
                    ORDER BY c.created_at DESC
                       LIMIT 100");
$stmt->execute();
$cands = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="max-w-4xl mx-auto">
  <div class="bg-white border rounded-lg p-6 space-y-4">
    <h1 class="text-xl font-semibold">Agendar Entrevista</h1>
    <form id="fInterview" class="space-y-4">
      <div>
        <label class="form-label">Candidato</label>
        <select id="iCand" class="form-select" required>
          <option value="">Selecione...</option>
          <?php foreach ($cands as $c) { echo '<option value="'.(int)$c['id'].'">'.htmlspecialchars($c['name']).' — '.htmlspecialchars($c['vacancy_title'] ?? '-').'</option>'; } ?>
        </select>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="form-label">Data</label>
          <input type="date" id="iDate" class="form-input" required>
        </div>
        <div>
          <label class="form-label">Hora</label>
          <input type="time" id="iTime" class="form-input" required>
        </div>
      </div>
      <div>
        <label class="form-label">Tipo de entrevista</label>
        <select id="iType" class="form-select">
          <option value="presencial">Presencial</option>
          <option value="online">Online</option>
        </select>
      </div>
      <div>
        <label class="form-label">Local (presencial) ou observações</label>
        <input type="text" id="iLocal" class="form-input" placeholder="Sala/Endereço (presencial)">
      </div>
      <div>
        <label class="form-label">Link da entrevista (apenas online)</label>
        <input type="url" id="iLink" class="form-input" placeholder="https://meet.example.com/..." disabled>
        <p class="text-xs text-paynes-gray">Habilitado quando tipo = online. Será exibido no histórico como link clicável.</p>
      </div>
      <div>
        <label class="form-label">Notificar (emails separados por vírgula)</label>
        <input type="text" id="iNotify" class="form-input" placeholder="ex: entrevistador@empresa.com, gestor@empresa.com">
      </div>
      <div class="text-right">
        <button type="button" id="btnSchedule" class="btn-primary">Agendar</button>
      </div>
    </form>
  </div>
</div>
<script>
document.getElementById('iType').addEventListener('change', function(){
  const online = this.value === 'online';
  const link = document.getElementById('iLink');
  link.disabled = !online;
  if (!online) { link.value=''; }
});
function pad2(n){ return (n<10?'0':'')+n; }
function toBrazilDatetimeStr(dStr, tStr){
  if(!dStr || !tStr) return '';
  const [y,m,d] = dStr.split('-');
  const [hh,mm] = tStr.split(':');
  return pad2(d)+'/'+pad2(m)+'/'+y+' '+pad2(hh)+':'+pad2(mm);
}
document.getElementById('btnSchedule').addEventListener('click', function(){
  const id = parseInt(document.getElementById('iCand').value||'0',10);
  const date = document.getElementById('iDate').value;
  const time = document.getElementById('iTime').value;
  const type = document.getElementById('iType').value;
  const link = document.getElementById('iLink').value.trim();
  const notify = document.getElementById('iNotify').value.trim();
  if(!id || !date || !time){ alert('Selecione candidato, data e hora.'); return; }
  const form = new FormData();
  form.append('candidate_id', id);
  form.append('date', date);
  form.append('time', time);
  form.append('type', type);
  form.append('link', link);
  form.append('notify_emails', notify);
  fetch('rh-kanban.php?action=schedule_interview', { method:'POST', body: form })
    .then(r=>r.json()).then(j=>{
      if(!j.ok){ alert(j.message||'Falha ao agendar'); return; }
      alert('Entrevista '+ (j.stage==='ENT_CONFIRMADA'?'confirmada':'agendada') +'!'); window.location.href = 'rh-kanban.php';
    }).catch(()=>alert('Erro de comunicação'));
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
