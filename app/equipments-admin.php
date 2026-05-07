<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/EquipmentService.php';
require_once __DIR__ . '/classes/AuditLog.php';
require_once __DIR__ . '/models/Front.php';

$db = Database::getInstance()->getConnection();
$auth = new Auth($db);
if (!$auth->isLoggedIn() || !in_array($_SESSION['user_role'] ?? null, [ROLE_ADMIN, ROLE_SUPERVISOR])) {
    header('Location: login.php');
    exit();
}
$pageTitle = 'Administração de Equipamentos';
$frontModel = new Front($db);
$fronts = $frontModel->findAll([], 'name ASC');
include __DIR__ . '/includes/header.php';
?>
<main id="main-content" class="p-6">
    <div class="max-w-5xl mx-auto space-y-6">
        <h1 class="text-2xl font-bold text-rich-black">Administração de Equipamentos</h1>
        <div class="bg-white border border-silver-lake-blue rounded-lg p-4">
            <h2 class="text-lg font-semibold text-paynes-gray mb-2">Limpeza de dados de horas trabalhadas</h2>
            <p class="text-sm text-paynes-gray mb-2">Efetua backup e exclui ocorrências e produção diária.</p>
            <button id="btnCleanup" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Executar limpeza</button>
            <pre id="cleanupResult" class="mt-3 text-xs text-paynes-gray whitespace-pre-wrap"></pre>
        </div>
        <div class="bg-white border border-silver-lake-blue rounded-lg p-4">
            <h2 class="text-lg font-semibold text-paynes-gray mb-2">Cadastro de horímetro atual</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <div class="relative">
                    <input type="text" id="hmSearch" class="px-3 py-2 border rounded w-full" placeholder="Tag do equipamento (digite para buscar)">
                    <div id="hmSuggest" class="absolute z-10 bg-white border rounded shadow hidden max-h-40 overflow-y-auto w-full"></div>
                    <input type="hidden" id="hmEquipmentId">
                </div>
                <input type="number" step="0.01" id="hmValue" class="px-3 py-2 border rounded" placeholder="Horímetro">
                <button id="btnSetHM" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Salvar</button>
            </div>
            <pre id="hmResult" class="mt-3 text-xs text-paynes-gray whitespace-pre-wrap"></pre>
        </div>
        <div class="bg-white border border-silver-lake-blue rounded-lg p-4">
            <h2 class="text-lg font-semibold text-paynes-gray mb-2">Produção por hora</h2>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-2">
                <div class="relative">
                    <input type="text" id="prSearch" class="px-3 py-2 border rounded w-full" placeholder="Tag do equipamento (digite para buscar)">
                    <div id="prSuggest" class="absolute z-10 bg-white border rounded shadow hidden max-h-40 overflow-y-auto w-full"></div>
                    <input type="hidden" id="prEquipmentId">
                </div>
                <?php $isProd = !DEBUG_MODE; ?>
                <input type="text" id="prUnit" class="px-3 py-2 border rounded" placeholder="Unidade (ex. ton/h)" value="<?php echo $isProd ? 'ton/h' : ''; ?>" <?php echo $isProd ? 'readonly' : ''; ?>>
                <input type="number" step="0.001" id="prRate" class="px-3 py-2 border rounded" placeholder="Valor">
                <button id="btnSetPR" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Salvar</button>
            </div>
            <pre id="prResult" class="mt-3 text-xs text-paynes-gray whitespace-pre-wrap"></pre>
        </div>
        <div class="bg-white border border-silver-lake-blue rounded-lg p-4">
            <h2 class="text-lg font-semibold text-paynes-gray mb-2">Alocação por escala</h2>
            <div class="grid grid-cols-1 sm:grid-cols-6 gap-2">
                <div class="relative">
                    <input type="text" id="alSearch" class="px-3 py-2 border rounded w-full" placeholder="Tag do equipamento (digite para buscar)">
                    <div id="alSuggest" class="absolute z-10 bg-white border rounded shadow hidden max-h-40 overflow-y-auto w-full"></div>
                    <input type="hidden" id="alEquipmentId">
                </div>
                <select id="alFrontId" class="px-3 py-2 border rounded w-full">
                    <option value="">Selecione a frente</option>
                    <?php foreach ($fronts as $f): ?>
                        <option value="<?php echo (int)$f['id']; ?>">
                            <?php
                                $code = $f['code'] ?? '';
                                $name = $f['name'] ?? '';
                                echo htmlspecialchars(trim(($code ? $code . ' — ' : '') . $name));
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="datetime-local" id="alStart" class="px-3 py-2 border rounded">
                <input type="datetime-local" id="alEnd" class="px-3 py-2 border rounded">
                <input type="number" step="0.1" id="alDailyHours" class="px-3 py-2 border rounded" placeholder="Horas produtivas/dia">
                <button id="btnAlloc" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Alocar</button>
            </div>
            <pre id="alResult" class="mt-3 text-xs text-paynes-gray whitespace-pre-wrap"></pre>
            <div id="allocCard" class="hidden mt-3 bg-white border border-silver-lake-blue rounded-lg overflow-hidden">
                <div class="px-4 py-3 flex flex-col sm:flex-row sm:items-baseline sm:justify-between">
                    <div id="allocCardTitle" class="text-xl sm:text-2xl font-semibold text-rich-black"></div>
                    <div id="allocCardMeta" class="text-sm text-paynes-gray mt-1 sm:mt-0"></div>
                </div>
                <div class="px-4 py-3 bg-eggshell border-t border-silver-lake-blue grid grid-cols-1 sm:grid-cols-3 gap-2 text-sm">
                    <div><span class="font-medium">Início:</span> <span id="allocStart"></span></div>
                    <div><span class="font-medium">Término:</span> <span id="allocEnd"></span></div>
                    <div><span class="font-medium">Atualizado:</span> <span id="allocUpdated"></span></div>
                </div>
            </div>
        </div>
    </div>
</main>
<script>
function post(action, body) {
    return fetch('equipments-api.php?action='+action, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body||{}) }).then(r=>r.json());
}
function attachSearch(inputId, suggestId, hiddenId, action='search', labelBuilder) {
    const input = document.getElementById(inputId);
    const suggest = document.getElementById(suggestId);
    const hidden = document.getElementById(hiddenId);
    let timer = null;
    input.addEventListener('input', () => {
        const q = input.value.trim();
        clearTimeout(timer);
        if (q.length < 2) { suggest.classList.add('hidden'); return; }
        timer = setTimeout(async () => {
            suggest.innerHTML = '<div class=\"px-3 py-2 text-center\"><span class=\"inline-block h-4 w-4 border-2 border-gray-300 border-t-blue-600 rounded-full animate-spin align-middle mr-2\"></span> Carregando...</div>';
            suggest.classList.remove('hidden');
            const res = await post(action, { q });
            suggest.innerHTML = '';
            if (!res.ok || !Array.isArray(res.data) || res.data.length === 0) {
                suggest.classList.add('hidden');
                return;
            }
            res.data.forEach(row => {
                const div = document.createElement('div');
                div.className = 'px-3 py-2 hover:bg-gray-100 cursor-pointer';
                const label = labelBuilder ? labelBuilder(row) : (row.tag + ' — ' + (row.description || ''));
                div.textContent = label;
                div.addEventListener('click', () => {
                    hidden.value = row.id;
                    // armazenar campos auxiliares para cards
                    if (row.tag) { hidden.dataset.tag = row.tag; hidden.dataset.description = row.description || ''; }
                    if (row.name) { hidden.dataset.name = row.name; hidden.dataset.code = row.code || ''; }
                    input.value = label;
                    suggest.classList.add('hidden');
                });
                suggest.appendChild(div);
            });
            suggest.classList.remove('hidden');
        }, 300);
    });
    document.addEventListener('click', (e) => {
        if (!suggest.contains(e.target) && e.target !== input) {
            suggest.classList.add('hidden');
        }
    });
}
document.getElementById('btnCleanup').addEventListener('click', async () => {
    const r = await post('cleanup_hours', { note: 'Limpeza manual' });
    document.getElementById('cleanupResult').textContent = JSON.stringify(r, null, 2);
});
document.getElementById('btnSetHM').addEventListener('click', async () => {
    const id = parseInt(document.getElementById('hmEquipmentId').value||'0',10);
    const val = parseFloat(document.getElementById('hmValue').value||'-1');
    const r = await post('set_hour_meter', { equipment_id: id, hour_meter: val });
    document.getElementById('hmResult').textContent = JSON.stringify(r, null, 2);
});
document.getElementById('btnSetPR').addEventListener('click', async () => {
    const id = parseInt(document.getElementById('prEquipmentId').value||'0',10);
    const unit = document.getElementById('prUnit').value||'';
    const rate = parseFloat(document.getElementById('prRate').value||'-1');
    const r = await post('set_production_rate', { equipment_id: id, unit, rate });
    document.getElementById('prResult').textContent = JSON.stringify(r, null, 2);
});
document.getElementById('btnAlloc').addEventListener('click', async () => {
    const id = parseInt(document.getElementById('alEquipmentId').value||'0',10);
    const frontSelect = document.getElementById('alFrontId');
    const fr = parseInt(frontSelect.value||'0',10);
    const s = document.getElementById('alStart').value;
    const e = document.getElementById('alEnd').value;
    const h = parseFloat(document.getElementById('alDailyHours').value||'0');
    const r = await post('allocate', { equipment_id: id, front_id: fr, start: s, end: e, priority: 0, daily_hours: h });
    document.getElementById('alResult').textContent = JSON.stringify(r, null, 2);
    if (r.ok && r.data) {
        const equipHidden = document.getElementById('alEquipmentId');
        const equipLabel = (equipHidden.dataset.tag || '') + (equipHidden.dataset.description ? ' — ' + equipHidden.dataset.description : '');
        const frontLabel = frontSelect.options[frontSelect.selectedIndex]?.text || '';
        document.getElementById('allocCardTitle').textContent = equipLabel + ' • ' + frontLabel;
        document.getElementById('allocCardMeta').textContent = `Previsão: ${Number(r.data.predicted).toFixed(2)} ${r.data.unit} • Horas: ${Number(r.data.hours).toFixed(1)} • Eficiência: ${Number(r.data.efficiency).toFixed(2)} • Sobreposição: ${r.data.overlaps ? 'Sim' : 'Não'}`;
        document.getElementById('allocStart').textContent = new Date(s).toLocaleString();
        document.getElementById('allocEnd').textContent = new Date(e).toLocaleString();
        document.getElementById('allocUpdated').textContent = new Date().toLocaleString();
        document.getElementById('allocCard').classList.remove('hidden');
        document.getElementById('allocCard').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
});
attachSearch('hmSearch', 'hmSuggest', 'hmEquipmentId', 'search', (row) => row.tag + ' — ' + (row.description || ''));
attachSearch('prSearch', 'prSuggest', 'prEquipmentId', 'search', (row) => row.tag + ' — ' + (row.description || ''));
attachSearch('alSearch', 'alSuggest', 'alEquipmentId', 'search', (row) => row.tag + ' — ' + (row.description || ''));
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
