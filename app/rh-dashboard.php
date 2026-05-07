<?php
require_once __DIR__ . '/config/config.php';
if (file_exists(__DIR__ . '/config/Database.php')) {
    require_once __DIR__ . '/config/Database.php';
}
if (file_exists(__DIR__ . '/classes/Auth.php')) {
    require_once __DIR__ . '/classes/Auth.php';
}
if (file_exists(__DIR__ . '/includes/rh_dashboard_metrics.php')) {
    require_once __DIR__ . '/includes/rh_dashboard_metrics.php';
}

$database = Database::getInstance();
$db = $database->getConnection();
$auth = new Auth($db);

function hasRhAccess($auth) {
    $role = $_SESSION['user_role'] ?? null;
    return $auth->isLoggedIn() && in_array($role, [ROLE_ADMIN, ROLE_COORD_RH], true);
}

if (($_GET['action'] ?? '') === 'metrics') {
    header('Content-Type: application/json; charset=utf-8');
    $t0 = microtime(true);
    if (!hasRhAccess($auth)) {
        echo json_encode(['ok' => false, 'message' => 'Sem permissão']); exit;
    }
    $periodFrom = $_GET['from'] ?? date('Y-m-01');
    $periodTo = $_GET['to'] ?? date('Y-m-d');
    $department = trim($_GET['department'] ?? '');
    $contractType = trim($_GET['contract_type'] ?? '');

    $metrics = rhBuildDashboardMetrics($db, $periodFrom, $periodTo, $department, $contractType);
    $t1 = microtime(true);
    echo json_encode(['ok' => true, 'metrics' => $metrics, 'timing_ms' => (int)round(($t1 - $t0) * 1000)]);
    exit;
}

if (($_GET['action'] ?? '') === 'lists') {
    header('Content-Type: application/json; charset=utf-8');
    $t0 = microtime(true);
    if (!hasRhAccess($auth)) { echo json_encode(['ok' => false, 'message' => 'Sem permissão']); exit; }
    $periodFrom = $_GET['from'] ?? date('Y-m-01');
    $periodTo = $_GET['to'] ?? date('Y-m-d');
    $department = trim($_GET['department'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $q = trim($_GET['q'] ?? '');
    $limit = max(5, min(30, (int)($_GET['limit'] ?? 20)));

    $data = ['vacancies' => [], 'candidates' => [], 'internships' => [], 'charts' => []];

    // Vacancies with candidates count
    try {
        $wh = ["v.status IN ('ATIVA','PAUSADA','ENCERRADA')"];
        $pa = [];
        if ($department !== '') { $wh[] = "v.department = :dept"; $pa[':dept'] = $department; }
        if ($status !== '') { $wh[] = "v.status = :st"; $pa[':st'] = $status; }
        if ($q !== '') { $wh[] = "(v.title LIKE :q OR v.department LIKE :q)"; $pa[':q'] = '%' . $q . '%'; }
        $sql = "SELECT v.id, v.title, v.department, v.status, v.created_at,
                       COALESCE(COUNT(c.id),0) AS candidates_count
                  FROM job_vacancies v
             LEFT JOIN job_candidates c ON c.vacancy_id = v.id
                 WHERE " . implode(' AND ', $wh) . "
              GROUP BY v.id, v.title, v.department, v.status, v.created_at
              ORDER BY v.created_at DESC
                 LIMIT $limit";
        $st = $db->prepare($sql);
        $st->execute($pa);
        $data['vacancies'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        // Chart: vacancies by status
        $st = $db->prepare("SELECT status, COUNT(*) qty FROM job_vacancies GROUP BY status");
        $st->execute();
        $data['charts']['vacancies_by_status'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { $data['vacancies'] = []; }

    // Candidates with stage/score and last update
    try {
        $wh = ["c.created_at BETWEEN :from AND :to"];
        $pa = [':from' => $periodFrom . ' 00:00:00', ':to' => $periodTo . ' 23:59:59'];
        if ($department !== '') { $wh[] = "v.department = :dept"; $pa[':dept'] = $department; }
        if ($status !== '') { $wh[] = "v.status = :st"; $pa[':st'] = $status; }
        if ($q !== '') { $wh[] = "(c.name LIKE :q OR v.title LIKE :q)"; $pa[':q'] = '%' . $q . '%'; }
        $sql = "SELECT c.id, c.name, s.code AS stage, s.name AS stage_name, c.updated_at, c.created_at,
                       v.title AS vacancy_title, v.department,
                       c.notes
                  FROM job_candidates c
                  JOIN stages s ON s.id = c.stage_id
             LEFT JOIN job_vacancies v ON v.id = c.vacancy_id
                 WHERE " . implode(' AND ', $wh) . "
              ORDER BY c.updated_at DESC, c.created_at DESC
                 LIMIT $limit";
        $st = $db->prepare($sql);
        $st->execute($pa);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$r) {
            $score = null;
            if (!empty($r['notes'])) {
                $jn = json_decode($r['notes'], true);
                if (is_array($jn) && isset($jn['score'])) { $score = $jn['score']; }
            }
            $r['score'] = $score;
            unset($r['notes']);
        }
        $data['candidates'] = $rows;
        // Chart: candidates by stage
        $st = $db->prepare("SELECT s.code AS stage, s.name AS stage_name, COUNT(*) qty
                              FROM job_candidates c
                              JOIN stages s ON s.id = c.stage_id
                          GROUP BY s.id, s.code, s.name
                          ORDER BY qty DESC");
        $st->execute();
        $data['charts']['candidates_by_stage'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { $data['candidates'] = []; }

    // Internships (estágios) summary
    try {
        $wh = ["i.start_date <= :to"];
        $pa = [':to' => $periodTo];
        if ($department !== '') { $wh[] = "i.department = :dept"; $pa[':dept'] = $department; }
        if ($q !== '') { $wh[] = "(i.intern_name LIKE :q OR i.supervisor LIKE :q)"; $pa[':q'] = '%' . $q . '%'; }
        $sql = "SELECT i.id, i.intern_name, i.supervisor, i.department,
                       i.start_date, i.end_date, i.progress_pct
                  FROM internships i
                 WHERE " . implode(' AND ', $wh) . "
              ORDER BY (i.end_date IS NULL) DESC, i.end_date ASC, i.start_date DESC
                 LIMIT $limit";
        $st = $db->prepare($sql);
        $st->execute($pa);
        $data['internships'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        // Chart: internships by department
        $st = $db->prepare("SELECT department, COUNT(*) qty FROM internships GROUP BY department");
        $st->execute();
        $data['charts']['internships_by_department'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { $data['internships'] = []; }

    $t1 = microtime(true);
    echo json_encode(['ok' => true, 'data' => $data, 'timing_ms' => (int)round(($t1 - $t0) * 1000)]);
    exit;
}

if (!hasRhAccess($auth)) {
    header('Location: dashboard.php'); exit;
}

$pageTitle = 'Dashboard RH';
include __DIR__ . '/includes/header.php';
?>
<main id="main-content" class="p-6">
  <div class="max-w-7xl mx-auto space-y-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-rich-black">Dashboard de RH</h1>
      <form id="rhFilters" class="flex flex-wrap gap-2 items-end">
        <div>
          <label class="form-label">De</label>
          <input type="date" id="fFrom" class="form-input" value="<?php echo date('Y-m-01'); ?>">
        </div>
        <div>
          <label class="form-label">Até</label>
          <input type="date" id="fTo" class="form-input" value="<?php echo date('Y-m-d'); ?>">
        </div>
        <div>
          <label class="form-label">Departamento</label>
          <input type="text" id="fDept" class="form-input" placeholder="Ex.: Operações">
        </div>
        <div>
          <label class="form-label">Tipo de Contrato</label>
          <input type="text" id="fContract" class="form-input" placeholder="Ex.: CLT">
        </div>
        <button type="button" id="btnApply" class="btn-primary">Aplicar</button>
      </form>
    </div>

    <div class="flex items-center justify-end">
      <button id="toggleBadges" class="text-xs px-3 py-1 rounded bg-eggshell border border-silver-lake-blue text-paynes-gray hover:bg-silver-lake-blue hover:text-white">Mostrar/ocultar explicações</button>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
      <div class="bg-white border rounded-lg p-4">
        <div class="text-xs text-paynes-gray flex items-center gap-2">Vagas Abertas <span class="text-[10px] px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 border border-blue-200" title="Número de vagas com status ATIVA no período">i</span></div>
        <div id="mOpenVac" class="text-3xl font-bold">0</div>
      </div>
      <div class="bg-white border rounded-lg p-4">
        <div class="text-xs text-paynes-gray flex items-center gap-2">Candidatos em Processo <span class="text-[10px] px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 border border-blue-200" title="Contagem de candidatos entre RECEBIDO e ENTREVISTA no período">i</span></div>
        <div id="mCandProc" class="text-3xl font-bold">0</div>
      </div>
      <div class="bg-white border rounded-lg p-4">
        <div class="flex items-center justify-between gap-3">
          <div class="text-xs text-paynes-gray flex items-center gap-2">Candidatos Contratados <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 border border-emerald-200" title="Total atual de candidatos na coluna Contratados do Kanban">i</span></div>
          <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-700" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="h-4 w-4 fill-current"><path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0 2c-4.418 0-8 2.239-8 5v1h16v-1c0-2.761-3.582-5-8-5Zm6.707-6.707-3 3a1 1 0 0 1-1.414 0l-1.5-1.5 1.414-1.414 0.793 0.793 2.293-2.293Z"/></svg>
          </span>
        </div>
        <div id="mTotalHired" class="text-3xl font-bold text-emerald-700 mt-2">0</div>
      </div>
      <div class="bg-white border rounded-lg p-4">
        <div class="text-xs text-paynes-gray flex items-center gap-2">Tempo Médio de Contratação (dias)
          <button type="button" class="text-[10px] px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 border border-blue-200 hover:bg-blue-200" aria-controls="expAvgHire" aria-expanded="false" onclick="toggleBadge('expAvgHire')" title="Explicação detalhada">?</button>
        </div>
        <div id="mAvgHire" class="text-3xl font-bold">0</div>
        <div id="expAvgHire" class="hidden mt-2 text-xs border-t pt-2 space-y-1">
          <div><span class="font-semibold">Fórmula:</span> Média dos dias entre a criação do candidato e a marcação como CONTRATADO, dentro do período selecionado.</div>
          <div><span class="font-semibold">Expressão:</span> (Σ DATEDIFF(data_contratado, data_criacao)) ÷ N</div>
          <div><span class="font-semibold">Dados de entrada:</span> data_criacao, data_contratado (updated_at quando estágio = CONTRATADO)</div>
          <div><span class="font-semibold">Período:</span> <span id="expAvgHirePeriod">De ... até ...</span></div>
          <div><span class="font-semibold">Critérios:</span> exclui candidatos fora do período; considera somente estágio CONTRATADO.</div>
          <div><span class="font-semibold">Exemplo:</span> 3 contratações com 4, 5 e 6 dias ⇒ (4+5+6) ÷ 3 = 5 dias.</div>
        </div>
      </div>
      <div class="bg-white border rounded-lg p-4">
        <div class="text-xs text-paynes-gray flex items-center gap-2">Colaboradores Ativos <span class="text-[10px] px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 border border-blue-200" title="Total de colaboradores ativos">i</span></div>
        <div id="mActiveEmp" class="text-3xl font-bold">0</div>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="bg-white border rounded-lg p-4">
        <div class="text-xs text-paynes-gray mb-1 flex items-center gap-2">Turnover (%)
          <button type="button" class="text-[10px] px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 border border-blue-200 hover:bg-blue-200" aria-controls="expTurnover" aria-expanded="false" onclick="toggleBadge('expTurnover')" title="Explicação detalhada">?</button>
        </div>
        <div id="mTurnover" class="text-2xl font-semibold">0</div>
        <div id="expTurnover" class="hidden mt-2 text-xs border-t pt-2 space-y-1">
          <div><span class="font-semibold">Fórmula:</span> (Saídas no período ÷ Média de colaboradores no período) × 100</div>
          <div><span class="font-semibold">Dados de entrada:</span> desligamentos, colaboradores ativos por mês</div>
          <div><span class="font-semibold">Período:</span> <span id="expTurnoverPeriod">De ... até ...</span></div>
          <div><span class="font-semibold">Critérios:</span> exclui transferências internas; considera desligamentos voluntários e involuntários conforme política.</div>
          <div><span class="font-semibold">Exemplo:</span> 3 saídas e média de 50 colaboradores ⇒ (3/50)×100 = 6%</div>
          <div class="text-[10px] text-paynes-gray">Termos: “média de colaboradores” é a média aritmética do quadro por mês.</div>
        </div>
      </div>
      <div class="bg-white border rounded-lg p-4">
        <div class="text-xs text-paynes-gray mb-1 flex items-center gap-2">Absenteísmo (%)
          <button type="button" class="text-[10px] px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 border border-blue-200 hover:bg-blue-200" aria-controls="expAbs" aria-expanded="false" onclick="toggleBadge('expAbs')" title="Explicação detalhada">?</button>
        </div>
        <div id="mAbs" class="text-2xl font-semibold">0</div>
        <div id="expAbs" class="hidden mt-2 text-xs border-t pt-2 space-y-1">
          <div><span class="font-semibold">Fórmula:</span> (Horas ausentes ÷ Horas planejadas) × 100</div>
          <div><span class="font-semibold">Dados de entrada:</span> horas de ausência (faltas, atestados, licenças) e horas planejadas</div>
          <div><span class="font-semibold">Período:</span> <span id="expAbsPeriod">De ... até ...</span></div>
          <div><span class="font-semibold">Critérios:</span> exclui férias; atestados podem ter ponderação específica.</div>
          <div><span class="font-semibold">Exemplo:</span> 40 horas ausentes em 160 horas planejadas ⇒ (40/160)×100 = 25%</div>
          <div class="text-[10px] text-paynes-gray">Termos: “horas planejadas” é a carga horária contratual no período.</div>
        </div>
      </div>
      <div class="bg-white border rounded-lg p-4">
        <div class="text-xs text-paynes-gray mb-1">Clima Organizacional</div>
        <div id="mClimate" class="text-2xl font-semibold">0</div>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="bg-white border rounded-lg p-4">
        <div class="text-xs text-paynes-gray mb-1">Cursos em andamento</div>
        <div id="mCourses" class="text-2xl font-semibold">0</div>
      </div>
      <div class="bg-white border rounded-lg p-4">
        <div class="text-xs text-paynes-gray mb-1">Colaboradores certificados</div>
        <div id="mCerts" class="text-2xl font-semibold">0</div>
      </div>
    </div>

    <div class="text-xs text-paynes-gray" id="lastUpdate"></div>

    <!-- Área destacada: Vagas | Candidatos | Estágios -->
    <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-4">
      <section class="bg-white border rounded-lg p-4">
        <div class="flex items-center justify-between mb-2">
          <h2 class="text-lg font-semibold">Vagas abertas</h2>
          <input id="qVac" class="form-input w-40" placeholder="Buscar...">
        </div>
        <div id="cardsVac" class="space-y-2"></div>
        <canvas id="chartVac" height="140"></canvas>
      </section>
      <section class="bg-white border rounded-lg p-4">
        <div class="flex items-center justify-between mb-2">
          <h2 class="text-lg font-semibold">Candidatos em processo</h2>
          <input id="qCand" class="form-input w-40" placeholder="Buscar...">
        </div>
        <div id="cardsCand" class="space-y-2"></div>
        <canvas id="chartCand" height="140"></canvas>
      </section>
      <section class="bg-white border rounded-lg p-4">
        <div class="flex items-center justify-between mb-2">
          <h2 class="text-lg font-semibold">Estágios em andamento</h2>
          <input id="qInt" class="form-input w-40" placeholder="Buscar...">
        </div>
        <div id="cardsInt" class="space-y-2"></div>
        <canvas id="chartInt" height="140"></canvas>
      </section>
    </div>
    
    <!-- Candidatos Inscritos -->
    <section class="mt-6 bg-white border rounded-lg p-4">
      <div class="flex items-center justify-between mb-2">
        <h2 class="text-lg font-semibold">Candidatos Inscritos</h2>
        <div class="flex flex-wrap gap-2">
          <input id="fCandVac" class="form-input w-40" placeholder="Vaga">
          <input id="fCandDept" class="form-input w-40" placeholder="Departamento">
          <select id="fCandStage" class="form-select w-40">
            <option value="">Estágio</option>
            <option>RECEBIDO</option><option>IA</option><option>RH</option><option>ENTREVISTA</option><option>DISPENSADO</option><option>TALENTOS</option><option>CONTRATADO</option>
          </select>
          <input id="fCandDateFrom" type="date" class="form-input">
          <input id="fCandDateTo" type="date" class="form-input">
          <button id="btnCandApply" class="btn-secondary">Filtrar</button>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 text-left cursor-pointer" data-sort="name">Nome</th>
              <th class="px-3 py-2 text-left cursor-pointer" data-sort="created_at">Data de Envio</th>
              <th class="px-3 py-2 text-left cursor-pointer" data-sort="vacancy_title">Vaga Pretendida</th>
              <th class="px-3 py-2 text-left cursor-pointer" data-sort="department">Departamento</th>
              <th class="px-3 py-2 text-left cursor-pointer" data-sort="stage">Estágio</th>
              <th class="px-3 py-2 text-left">Tempo no Estágio</th>
              <th class="px-3 py-2 text-left">Nota</th>
              <th class="px-3 py-2 text-left">Ações</th>
            </tr>
          </thead>
          <tbody id="candTableBody"></tbody>
        </table>
      </div>
      <div class="flex items-center justify-between mt-2">
        <div class="text-xs text-paynes-gray" id="candTableInfo"></div>
        <div class="flex gap-2">
          <button id="candPrev" class="btn-secondary">Anterior</button>
          <button id="candNext" class="btn-secondary">Próxima</button>
        </div>
      </div>
    </section>
  </div>
</main>
<script>
function qs(id){ return document.getElementById(id); }
const rhNumberFmt = new Intl.NumberFormat('pt-BR');
function loadMetrics(){
  const p = new URLSearchParams();
  p.set('action','metrics');
  p.set('from', qs('fFrom').value);
  p.set('to', qs('fTo').value);
  p.set('department', qs('fDept').value);
  p.set('contract_type', qs('fContract').value);
  const t0 = performance.now();
  return fetch('rh-dashboard.php?'+p.toString(),{cache:'no-store'})
    .then(r=>r.json())
    .then(j=>{
      if(!j.ok) throw new Error(j.message||'Falha ao carregar');
      const m=j.metrics;
      qs('mOpenVac').textContent = rhNumberFmt.format(m.recruitment.open_vacancies || 0);
      qs('mCandProc').textContent = rhNumberFmt.format(m.recruitment.candidates_in_process || 0);
      qs('mTotalHired').textContent = rhNumberFmt.format(m.recruitment.total_hired || 0);
      qs('mAvgHire').textContent = rhNumberFmt.format(m.recruitment.avg_hire_time_days || 0);
      qs('mActiveEmp').textContent = rhNumberFmt.format(m.workforce.active_employees || 0);
      qs('mTurnover').textContent = rhNumberFmt.format(m.workforce.turnover_pct || 0);
      qs('mAbs').textContent = rhNumberFmt.format(m.workforce.absenteeism_pct || 0);
      qs('mClimate').textContent = rhNumberFmt.format(m.satisfaction.climate_score || 0);
      qs('mCourses').textContent = rhNumberFmt.format(m.training.courses_running || 0);
      qs('mCerts').textContent = rhNumberFmt.format(m.training.certified_employees || 0);
      const t = Math.round(performance.now()-t0);
      qs('lastUpdate').textContent = 'Atualizado agora ('+t+' ms)';
    }).catch(e=>{
      qs('lastUpdate').textContent = 'Erro: '+e.message;
    });
}
qs('btnApply').addEventListener('click', loadMetrics);
loadMetrics();
setInterval(loadMetrics, 30000);
window.addEventListener('focus', loadMetrics);
document.addEventListener('visibilitychange', ()=>{ if(!document.hidden) loadMetrics(); });

// Cards + gráficos (2s target)
function urgency(dateStr, daysThreshold){
  if(!dateStr) return false;
  const d=new Date(dateStr.replace(' ','T')), now=new Date();
  const diff=(d-now)/(1000*60*60*24);
  return diff>=0 && diff<=daysThreshold;
}
function esc(s){ return (s==null?'':String(s)).replace(/[&<>]/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[m])); }
function colorByStage(stage){
  const map = { 'DISPENSADO':'#ef4444', 'CONTRATADO':'#10b981', 'RECEBIDO':'#6b7280', 'IA':'#3b82f6', 'RH':'#6366f1', 'ENTREVISTA':'#f59e0b', 'TALENTOS':'#14b8a6' };
  return map[stage] || '#3e5c76';
}

async function loadLists(extraQ){
  const p = new URLSearchParams();
  p.set('action','lists');
  p.set('from', qs('fFrom').value);
  p.set('to', qs('fTo').value);
  p.set('department', qs('fDept').value);
  p.set('status', qs('fContract').value); // reutilizando campo para status/contrato
  p.set('q', extraQ || '');

  const t0=performance.now();
  const j = await fetch('rh-dashboard.php?'+p.toString(), {cache:'no-store'}).then(r=>r.json());
  if(!j.ok) throw new Error(j.message || 'Falha nas listas');
  const t=performance.now()-t0;
  // Vagas
  const wrapVac = qs('cardsVac');
  wrapVac.innerHTML = (j.data.vacancies||[]).map(v => {
    const warn = urgency(v.created_at, 7) ? 'border-yellow-400' : 'border-silver-lake-blue';
    return '<div class="border '+warn+' rounded p-2 flex justify-between items-start">'+
      '<div>'+
      '<div class="font-semibold">'+esc(v.title)+'</div>'+
      '<div class="text-xs text-paynes-gray">'+esc(v.department || '-')+' • Abertura: '+esc(v.created_at||'-')+'</div>'+
      '</div>'+
      '<div class="text-right">'+
      '<div class="text-xs">Candidatos: <span class="font-semibold">'+(v.candidates_count||0)+'</span></div>'+
      '<div class="text-xs">Status: '+esc(v.status)+'</div>'+
      '</div>'+
      '</div>';
  }).join('');
  // Candidatos
  const wrapCand = qs('cardsCand');
  wrapCand.innerHTML = (j.data.candidates||[]).map(c => {
    const warn = urgency(c.updated_at, 2) ? 'bg-yellow-50' : 'bg-white';
    return '<div class="border border-silver-lake-blue rounded p-2 '+warn+'">'+
      '<div class="flex justify-between">'+
      '<div class="font-semibold">'+esc(c.name)+'</div>'+
      '<span class="text-[10px] text-white px-2 py-0.5 rounded" style="background-color:'+colorByStage(c.stage)+'">'+esc(c.stage_name||c.stage)+'</span>'+
      '</div>'+
      '<div class="text-xs text-paynes-gray">Vaga: '+esc(c.vacancy_title || '-')+' • Atualização: '+esc(c.updated_at || '-')+'</div>'+
      (c.score!=null ? '<div class="text-xs">Pontuação: <span class="font-semibold">'+esc(c.score)+'</span></div>' : '')+
      '</div>';
  }).join('');
  // Estágios
  const wrapInt = qs('cardsInt');
  wrapInt.innerHTML = (j.data.internships||[]).map(i => {
    const urgent = urgency(i.end_date, 7);
    const bar = '<div class="h-2 w-full bg-gray-200 rounded"><div class="h-2 bg-emerald-600 rounded" style="width:'+(i.progress_pct||0)+'%"></div></div>';
    return '<div class="border '+(urgent?'border-red-400':'border-silver-lake-blue')+' rounded p-2">'+
      '<div class="flex justify-between items-start">'+
      '<div><div class="font-semibold">'+esc(i.intern_name)+'</div>'+
      '<div class="text-xs text-paynes-gray">'+esc(i.department || '-')+' • Supervisor: '+esc(i.supervisor || '-')+'</div></div>'+
      '<div class="text-xs text-paynes-gray text-right">'+
      'Início: '+esc(i.start_date || '-')+'<br>Fim: '+esc(i.end_date || '-')+
      '</div></div>'+
      '<div class="mt-2 text-xs mb-1">Progresso: '+(i.progress_pct||0)+'%</div>'+bar+
      '</div>';
  }).join('');

  // Charts (Chart.js via CDN leve)
  if (!window.Chart) {
    await new Promise(res=>{
      const s=document.createElement('script');
      s.src='https://cdn.jsdelivr.net/npm/chart.js';
      s.onload=res; document.head.appendChild(s);
    });
  }
  const cv = (j.data.charts.vacancies_by_status||[]);
  const cc = (j.data.charts.candidates_by_stage||[]);
  const ci = (j.data.charts.internships_by_department||[]);
  const ctx1 = document.getElementById('chartVac').getContext('2d');
  const ctx2 = document.getElementById('chartCand').getContext('2d');
  const ctx3 = document.getElementById('chartInt').getContext('2d');
  window.__ch1 && __ch1.destroy(); window.__ch2 && __ch2.destroy(); window.__ch3 && __ch3.destroy();
  window.__ch1 = new Chart(ctx1,{type:'doughnut',data:{labels:cv.map(x=>x.status||'N/A'),datasets:[{data:cv.map(x=>x.qty||0),backgroundColor:['#3e5c76','#748cab','#f59e0b','#ef4444']}]},options:{plugins:{legend:{display:false}}}});
  window.__ch2 = new Chart(ctx2,{type:'bar',data:{labels:cc.map(x=>(x.stage_name||x.stage||'N/A')),datasets:[{data:cc.map(x=>x.qty||0),backgroundColor:cc.map(x=>colorByStage(x.stage))}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}});
  window.__ch3 = new Chart(ctx3,{type:'bar',data:{labels:ci.map(x=>x.department||'N/A'),datasets:[{data:ci.map(x=>x.qty||0),backgroundColor:'#10b981'}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}});

  // Busca em tempo real
  const filterCards = () => {
    const qv = qs('qVac').value.toLowerCase();
    const qc = qs('qCand').value.toLowerCase();
    const qi = qs('qInt').value.toLowerCase();
    Array.from(wrapVac.children).forEach(c=>c.style.display = c.textContent.toLowerCase().includes(qv)?'':'none');
    Array.from(wrapCand.children).forEach(c=>c.style.display = c.textContent.toLowerCase().includes(qc)?'':'none');
    Array.from(wrapInt.children).forEach(c=>c.style.display = c.textContent.toLowerCase().includes(qi)?'':'none');
  };
  ['qVac','qCand','qInt'].forEach(id=>qs(id).oninput = filterCards);

  // SLA
  if (t > 2000) console.warn('lists took', Math.round(t), 'ms');
}
loadLists();
qs('btnApply').addEventListener('click', () => loadLists());

// Badges helpers
function toggleBadge(id){
  const el = document.getElementById(id);
  if(!el) return;
  const v = el.classList.toggle('hidden');
  const btn = document.querySelector('[aria-controls="'+id+'"]');
  if(btn) btn.setAttribute('aria-expanded', String(!v));
  updatePeriods();
}
function updatePeriods(){
  const from = qs('fFrom').value, to = qs('fTo').value;
  const ids = ['expAvgHirePeriod','expTurnoverPeriod','expAbsPeriod'];
  ids.forEach(i => { const el = document.getElementById(i); if(el) el.textContent = 'De '+from+' até '+to; });
}
document.getElementById('toggleBadges').addEventListener('click', ()=>{
  const panels = document.querySelectorAll('#expAvgHire,#expTurnover,#expAbs');
  const anyHidden = Array.from(panels).some(p=>p.classList.contains('hidden'));
  panels.forEach(p => { p.classList.toggle('hidden', !anyHidden); });
  ['expAvgHire','expTurnover','expAbs'].forEach(id=>{
    const btn = document.querySelector('[aria-controls="'+id+'"]');
    if(btn) btn.setAttribute('aria-expanded', String(anyHidden));
  });
  updatePeriods();
});
updatePeriods();

// Candidatos Inscritos table
let candData = []; let candPage = 0; let candSort = {key:'created_at', asc:false};
function daysInStage(updatedAt){
  if(!updatedAt) return 0;
  const d=new Date(updatedAt.replace(' ','T')); const now=new Date();
  return Math.floor((now - d)/(1000*60*60*24));
}
function renderCandTable(){
  const pageSize = 10;
  const start = candPage * pageSize;
  const rows = candData.slice().sort((a,b)=>{
    const k=candSort.key; const av=(a[k]||''); const bv=(b[k]||'');
    return (candSort.asc?1:-1) * (String(av).localeCompare(String(bv)));
  }).slice(start, start+pageSize);
  const body = qs('candTableBody');
  body.innerHTML = rows.map(r=>{
    const d = r.created_at ? new Date(r.created_at.replace(' ','T')) : null;
    const dstr = d ? d.toLocaleString() : '-';
    const days = daysInStage(r.updated_at);
    const warnClass = days > 30 ? 'bg-red-50' : '';
    return '<tr class="border-t '+warnClass+'">'+
      '<td class="px-3 py-2">'+esc(r.name||'-')+'</td>'+
      '<td class="px-3 py-2">'+esc(dstr)+'</td>'+
      '<td class="px-3 py-2">'+esc(r.vacancy_title||'-')+'</td>'+
      '<td class="px-3 py-2">'+esc(r.department||'-')+'</td>'+
      '<td class="px-3 py-2">'+esc(r.stage_name||r.stage||'-')+'</td>'+
      '<td class="px-3 py-2">'+days+' dias</td>'+
      '<td class="px-3 py-2">'+(r.score!=null?esc(r.score):'-')+'</td>'+
      '<td class="px-3 py-2"><button class="px-2 py-1 bg-paynes-gray text-white rounded text-xs" onclick="dashViewCV('+r.id+')">Ver</button></td>'+
      '</tr>';
  }).join('');
  qs('candTableInfo').textContent = 'Página '+(candPage+1)+' de '+Math.max(1, Math.ceil(candData.length/10));
}
function loadCandTable(){
  const p = new URLSearchParams();
  p.set('action','lists');
  p.set('from', qs('fCandDateFrom').value || qs('fFrom').value);
  p.set('to', qs('fCandDateTo').value || qs('fTo').value);
  p.set('department', qs('fCandDept').value || '');
  p.set('q', qs('fCandVac').value || '');
  fetch('rh-dashboard.php?'+p.toString(), {cache:'no-store'})
    .then(r=>r.json())
    .then(j=>{
      candData = (j.ok && j.data && Array.isArray(j.data.candidates)) ? j.data.candidates : [];
      candPage = 0; renderCandTable();
    });
}
document.getElementById('btnCandApply').addEventListener('click', loadCandTable);
document.getElementById('candPrev').addEventListener('click', ()=>{ candPage = Math.max(0, candPage-1); renderCandTable(); });
document.getElementById('candNext').addEventListener('click', ()=>{ const max = Math.max(0, Math.ceil(candData.length/10)-1); candPage = Math.min(max, candPage+1); renderCandTable(); });
document.querySelectorAll('th[data-sort]').forEach(th=>{
  th.addEventListener('click', ()=>{ const k=th.dataset.sort; if(candSort.key===k){ candSort.asc=!candSort.asc; } else { candSort={key:k,asc:true}; } renderCandTable(); });
});
loadCandTable();

function dashViewCV(id){
  fetch('rh-kanban.php?action=view&id=' + encodeURIComponent(id))
    .then(r => r.json())
    .then(j => {
      if (!j.ok) { alert(j.message || 'Erro ao carregar currículo'); return; }
      const d = j.data || {};
      const modalId = 'dashCvModal';
      let modal = document.getElementById(modalId);
      if (!modal) {
        const tpl = document.createElement('div');
        tpl.id = modalId; tpl.className = 'modal-overlay hidden'; tpl.setAttribute('aria-hidden','true');
        tpl.innerHTML = '<div class="modal-panel" tabindex="-1" role="dialog" aria-modal="true"><div class="px-4 py-3 border-b flex items-center justify-between"><h3 class="text-lg font-semibold text-rich-black">Dados do Candidato</h3><button class="text-paynes-gray hover:text-rich-black" onclick="modalClose(\''+modalId+'\')" aria-label="Fechar">✕</button></div><div id="dashCvBody" class="modal-body"></div><div class="modal-actions text-right"><button class="px-4 py-2 bg-paynes-gray text-white rounded hover:bg-prussian-blue" onclick="modalClose(\''+modalId+'\')">Fechar</button></div></div>';
        document.body.appendChild(tpl); modal = tpl;
      }
      const body = document.getElementById('dashCvBody');
      const lines = [];
      lines.push('<div class="space-y-2">');
      lines.push('<div><span class="font-semibold">Nome:</span> ' + esc(d.name||'-') + '</div>');
      lines.push('<div><span class="font-semibold">CPF:</span> ' + esc(d.cpf||'-') + '</div>');
      lines.push('<div><span class="font-semibold">Telefone:</span> ' + esc(d.phone||'-') + '</div>');
      lines.push('<div><span class="font-semibold">Email:</span> ' + esc(d.email||'-') + '</div>');
      lines.push('<div><span class="font-semibold">Cargo pretendido:</span> ' + esc(d.desired_role||'-') + '</div>');
      lines.push('<div><span class="font-semibold">Data de cadastro:</span> ' + esc(d.created_at||'-') + '</div>');
      lines.push('<div><span class="font-semibold">Etapa:</span> ' + esc(d.stage_name||d.stage||'-') + '</div>');
      lines.push('</div>');
      body.innerHTML = lines.join('');
      modalOpen(modalId);
    })
    .catch(() => alert('Erro de comunicação ao carregar candidato'));
}
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
