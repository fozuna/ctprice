<?php
if (file_exists(__DIR__ . '/config/autoload.php')) {
    require_once __DIR__ . '/config/autoload.php';
}
require_once 'config/config.php';
require_once 'classes/Auth.php';
require_once 'models/Goal.php';
require_once 'models/Delivery.php';
require_once 'models/Front.php';
require_once 'models/Client.php';

$database = Database::getInstance();
$db = $database->getConnection();
$auth = new Auth($db);
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$goalModel = new Goal($db);
$deliveryModel = new Delivery($db);
$frontModel = new Front($db);
$clientModel = new Client($db);

$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : intval(date('n'));

$monthStart = sprintf('%04d-%02d-01', $selectedYear, $selectedMonth);
$monthEnd = date('Y-m-t', strtotime($monthStart));
$isCurrentMonth = ($selectedYear == intval(date('Y')) && $selectedMonth == intval(date('n')));
$periodEnd = $isCurrentMonth ? date('Y-m-d') : $monthEnd;
$daysInMonth = intval(date('t', strtotime($monthStart)));
$daysPassed = intval(date('j', strtotime($periodEnd)));
$daysRemaining = max(0, $daysInMonth - $daysPassed);

$monthlySummaryDelivery = $goalModel->getMonthlyGoalsSummary($selectedYear, $selectedMonth, null, null, 'delivery');
$monthlyGoal = 0;
foreach ($monthlySummaryDelivery as $row) {
    $monthlyGoal += floatval($row['monthly_total'] ?? 0);
}

$effRows = $deliveryModel->getDailyEfficiencyWithGoals($monthStart, $periodEnd, null, null);
$expectedTotalToDate = 0;
$deliveredTotalToDate = 0;
$dailySeriesDays = [];
$dailySeriesExpected = [];
$dailySeriesDelivered = [];
$frontDeliveredMap = [];
$clientDeliveredMap = [];
$todayDelivered = 0;
$todayExpected = 0;
foreach ($effRows as $r) {
    $day = intval(date('j', strtotime($r['goal_date'])));
    $expected = floatval($r['daily_goal'] ?? 0);
    $delivered = floatval($r['delivered_value'] ?? 0);
    if (!isset($dailySeriesExpected[$day])) $dailySeriesExpected[$day] = 0;
    if (!isset($dailySeriesDelivered[$day])) $dailySeriesDelivered[$day] = 0;
    $dailySeriesExpected[$day] += $expected;
    $dailySeriesDelivered[$day] += $delivered;
    $expectedTotalToDate += $expected;
    $deliveredTotalToDate += $delivered;
    $fid = $r['front_id'];
    $fname = $r['front_name'] ?? ('Frente ' . $fid);
    if (!isset($frontDeliveredMap[$fid])) $frontDeliveredMap[$fid] = ['front_name' => $fname, 'total' => 0];
    $frontDeliveredMap[$fid]['total'] += $delivered;
    $cid = $r['client_id'];
    $cname = $r['client_name'] ?? ('Cliente ' . $cid);
    if (!isset($clientDeliveredMap[$cid])) $clientDeliveredMap[$cid] = ['client_name' => $cname, 'total' => 0];
    $clientDeliveredMap[$cid]['total'] += $delivered;
    if (date('Y-m-d', strtotime($r['goal_date'])) === $periodEnd) {
        $todayExpected += $expected;
        $todayDelivered += $delivered;
    }
}
for ($d = 1; $d <= $daysInMonth; $d++) {
    $dailySeriesDays[] = $d;
    $dailySeriesExpected[$d] = floatval($dailySeriesExpected[$d] ?? 0);
    $dailySeriesDelivered[$d] = floatval($dailySeriesDelivered[$d] ?? 0);
}
$percentAchieved = ($expectedTotalToDate > 0) ? round(($deliveredTotalToDate / $expectedTotalToDate) * 100, 2) : 0;
$projectionMonthly = ($daysPassed > 0) ? round(($deliveredTotalToDate / $daysPassed) * $daysInMonth, 2) : 0;
$projectionDelta = round($projectionMonthly - $monthlyGoal, 2);
$projectionPercent = ($monthlyGoal > 0) ? round(($projectionMonthly / $monthlyGoal) * 100, 2) : 0;
$necessaryDaily = ($daysRemaining > 0) ? max(0, round(($monthlyGoal - $deliveredTotalToDate) / $daysRemaining, 2)) : 0;

$clientsGoalMap = [];
foreach ($monthlySummaryDelivery as $row) {
    $cid = $row['client_id'] ?? 0;
    $cname = $row['client_name'] ?? 'Cliente';
    if (!isset($clientsGoalMap[$cid])) $clientsGoalMap[$cid] = ['client_name' => $cname, 'goal' => 0];
    $clientsGoalMap[$cid]['goal'] += floatval($row['monthly_total'] ?? 0);
}
$frontsGoalMap = [];
foreach ($monthlySummaryDelivery as $row) {
    $fid = $row['front_id'] ?? 0;
    $fname = $row['front_name'] ?? 'Frente';
    if (!isset($frontsGoalMap[$fid])) $frontsGoalMap[$fid] = ['front_name' => $fname, 'goal' => 0];
    $frontsGoalMap[$fid]['goal'] += floatval($row['monthly_total'] ?? 0);
}
$allClients = $clientModel->findAllActive();
$clientsCombined = [];
foreach ($allClients as $clientRow) {
    $cid = intval($clientRow['id']);
    $name = $clientRow['name'];
    $goal = floatval($clientsGoalMap[$cid]['goal'] ?? 0);
    $del = floatval($clientDeliveredMap[$cid]['total'] ?? 0);
    $clientsCombined[] = ['client_name' => $name, 'goal' => $goal, 'delivered' => $del];
}
usort($clientsCombined, function($a, $b){ return ($b['delivered'] <=> $a['delivered']); });
$frontIds = array_unique(array_merge(array_keys($frontDeliveredMap), array_keys($frontsGoalMap)));
$frontsCombined = [];
foreach ($frontIds as $fid) {
    $fname = $frontsGoalMap[$fid]['front_name'] ?? ($frontDeliveredMap[$fid]['front_name'] ?? ('Frente '.$fid));
    $goal = floatval($frontsGoalMap[$fid]['goal'] ?? 0);
    $del = floatval($frontDeliveredMap[$fid]['total'] ?? 0);
    $frontsCombined[] = ['front_name' => $fname, 'goal' => $goal, 'delivered' => $del];
}
usort($frontsCombined, function($a, $b){ return ($b['delivered'] <=> $a['delivered']); });
$totalDeliveredMonth = $deliveredTotalToDate;

$monthNames = [1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];
$pageTitle = 'Dashboard de Entregas';
include 'includes/header.php';
?>
<style>
    .dash-bg { background-color: #0c1722; }
    .dash-panel { background-color: rgba(17, 28, 40, 0.9); border: 2px solid #f59e0b; border-radius: 8px; color: #f1f5f9; }
    .dash-title { color: #e2e8f0; }
    .accent { color: #22c55e; }
    .accent-red { color: #ef4444; }
    .panel-muted { background-color: rgba(24, 35, 49, 0.8); border: 2px solid #1f2937; }
    .gauge-wrapper { position: relative; height: 160px; }
    .gauge-center { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; }
    .gauge-center .big { font-size: 28px; font-weight: 700; }
    .gauge-center .small { font-size: 12px; opacity: 0.8; }
    .dark-select { background-color: #0f1b29; color: #ffffff; border-color: #748cab; }
    .dark-select:focus { outline: 2px solid #3b82f6; }
    .dark-select option { background-color: #0c1722; color: #ffffff; }
</style>
<div class="dash-bg -m-6 p-6 min-h-[80vh]">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold text-white">CONTROLE DE ENTREGA DE CAVACO</h1>
        <div class="flex items-center gap-3">
            <div class="relative" x-data="{ open: false, selected: <?= json_encode($selectedYear) ?>, options: <?= json_encode($goalModel->getAvailableYears()) ?> }">
                <button @click="open = !open" class="bg-rich-black text-white border border-gray-500 px-3 py-2 rounded min-w-[90px] flex items-center justify-between">
                    <span x-text="selected"></span>
                    <span class="ml-2">▾</span>
                </button>
                <ul x-show="open" @click.away="open=false" class="absolute mt-1 w-full bg-rich-black text-white border border-silver-lake-blue rounded shadow-lg z-50">
                    <template x-for="y in options" :key="y">
                        <li @click="selected=y; open=false; changeYear(y)" class="px-3 py-2 hover:bg-paynes-gray cursor-pointer" x-text="y"></li>
                    </template>
                </ul>
            </div>
            <div class="relative" x-data="{ open: false, selectedIndex: <?= json_encode($selectedMonth-1) ?>, labels: ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'] }">
                <button @click="open = !open" class="bg-rich-black text-white border border-gray-500 px-3 py-2 rounded min-w-[130px] flex items-center justify-between">
                    <span x-text="labels[selectedIndex]"></span>
                    <span class="ml-2">▾</span>
                </button>
                <ul x-show="open" @click.outside="open=false" class="absolute mt-1 w-full bg-rich-black text-white border border-silver-lake-blue rounded shadow-lg z-50">
                    <template x-for="(label, idx) in labels" :key="idx">
                        <li @click="selectedIndex=idx; open=false; changeMonth(idx+1)" class="px-3 py-2 hover:bg-paynes-gray cursor-pointer" x-text="label"></li>
                    </template>
                </ul>
            </div>
        </div>
    </div>
    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-2 panel-muted p-4">
            <div class="mb-4">
                <div class="text-white font-semibold mb-2">Total Entregue</div>
                <div class="bg-white/10 text-white rounded p-3">
                    <div class="flex items-center justify-between">
                        <div class="text-2xl font-bold"><?= number_format($deliveredTotalToDate, 0, ',', '.') ?></div>
                        <div class="text-sm <?= $percentAchieved >= 100 ? 'text-green-400' : 'text-white/70' ?>"><?= number_format($percentAchieved, 2, ',', '.') ?>%</div>
                    </div>
                </div>
            </div>
            <div class="mb-4">
                <div class="text-white font-semibold mb-2">SEMANAS</div>
                <div class="space-y-2 text-sm">
                    <?php for ($w=1;$w<=5;$w++): ?>
                        <label class="flex items-center gap-2 text-white/80"><input type="checkbox" class="form-checkbox rounded" onchange="toggleWeek(<?= $w ?>)"> Semana <?= $w ?></label>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="space-y-3">
                <div class="bg-white/10 text-white rounded p-3">
                    <div class="text-xs">Ritmo Diário (t)</div>
                    <div class="text-xl font-bold"><?= number_format($todayDelivered, 0, ',', '.') ?></div>
                </div>
                <div class="bg-white/10 text-white rounded p-3">
                    <div class="text-xs">Ritmo Esperado (t)</div>
                    <div class="text-xl font-bold"><?= number_format($todayExpected, 0, ',', '.') ?></div>
                </div>
                <div class="bg-orange-600 text-white rounded p-3">
                    <div class="text-xs">Necess. Meta</div>
                    <div class="text-xl font-bold"><?= number_format($necessaryDaily, 0, ',', '.') ?></div>
                </div>
                <div class="bg-white/10 text-white rounded p-3">
                    <div class="text-xs">Entregas</div>
                    <div class="text-xl font-bold"><?= number_format($todayDelivered, 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-span-10 grid grid-cols-12 gap-4">
            <div class="col-span-4 dash-panel p-4">
                <div class="text-white font-semibold">Meta de <?= ucfirst($monthNames[$selectedMonth]) ?></div>
                <div class="text-4xl font-bold mt-2"><?= number_format($monthlyGoal, 0, ',', '.') ?></div>
                <div class="gauge-wrapper mt-3">
                    <canvas id="gaugeChart"></canvas>
                    <div class="gauge-center">
                        <div class="big"><?= number_format($deliveredTotalToDate, 0, ',', '.') ?></div>
                        <div class="small"><?= number_format($monthlyGoal, 0, ',', '.') ?></div>
                    </div>
                </div>
            </div>
            <div class="col-span-4 dash-panel p-4">
                <div class="text-white font-semibold">Total Esperado</div>
                <div class="text-3xl font-bold mt-2"><?= number_format($expectedTotalToDate, 0, ',', '.') ?></div>
                <div class="mt-3 h-28">
                    <canvas id="lineChart"></canvas>
                </div>
            </div>
            <div class="col-span-4 dash-panel p-4">
                <div class="text-white font-semibold mb-2">Projeção Mensal</div>
                <div class="mt-1 p-3 rounded-lg border border-white/30 bg-white/10 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="p-3 rounded-full <?= $projectionDelta >= 0 ? 'bg-green-500' : 'bg-red-500' ?>">
                            <i class="fas <?= $projectionDelta >= 0 ? 'fa-chart-line' : 'fa-exclamation-triangle' ?> text-xl text-white"></i>
                        </div>
                        <div>
                            <div class="text-3xl font-bold text-white"><?= number_format($projectionMonthly, 0, ',', '.') ?></div>
                            <div class="text-xs text-white/80">Meta: <?= number_format($monthlyGoal, 0, ',', '.') ?> • Projeção: <?= number_format($projectionPercent, 2, ',', '.') ?>%</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="<?= $projectionDelta >= 0 ? 'accent' : 'accent-red' ?> text-xl font-bold">
                            <?= ($projectionDelta >= 0 ? '+' : '') . number_format($projectionDelta, 2, ',', '.') ?>
                        </div>
                        <div class="text-xs text-white/70">Delta (ton)</div>
                    </div>
                </div>
                <div class="mt-3 text-sm text-white/80">Projeção(%): <?= number_format($projectionPercent, 2, ',', '.') ?>%</div>
                <div class="mt-3 h-4 bg-white/10 rounded">
                    <div style="width: <?= min(100, max(0, $projectionPercent)) ?>%;" class="h-4 bg-green-500 rounded"></div>
                </div>
            </div>
            <div class="col-span-6 dash-panel p-4">
                <div class="text-white font-semibold">Entregas por Frentes</div>
                <div class="mt-2 h-64">
                    <canvas id="frontsBar"></canvas>
                </div>
            </div>
            <div class="col-span-6 dash-panel p-4">
                <div class="text-white font-semibold">Meta e Total Entregue por Clientes</div>
                <div class="mt-2 h-64">
                    <canvas id="clientsGrouped"></canvas>
                </div>
            </div>
            <div class="col-span-6 dash-panel p-4">
                <div class="text-white font-semibold">Frentes</div>
                <div class="mt-2">
                    <table class="w-full text-sm text-white/90">
                        <thead>
                            <tr>
                                <th class="text-left p-2">Frente</th>
                                <th class="text-right p-2">Peso Total</th>
                                <th class="text-right p-2">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($frontsCombined as $f): ?>
                                <tr class="border-b border-white/10">
                                    <td class="p-2"><?= htmlspecialchars($f['front_name']) ?></td>
                                    <td class="p-2 text-right"><?= number_format($f['delivered'], 0, ',', '.') ?></td>
                                    <td class="p-2 text-right"><?= $totalDeliveredMonth > 0 ? number_format(($f['delivered']/$totalDeliveredMonth)*100, 2, ',', '.') : '0,00' ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td class="p-2 font-semibold">Total</td>
                                <td class="p-2 text-right font-semibold"><?= number_format($totalDeliveredMonth, 0, ',', '.') ?></td>
                                <td class="p-2 text-right font-semibold"><?= $expectedTotalToDate > 0 ? number_format(($deliveredTotalToDate/$expectedTotalToDate)*100, 2, ',', '.') : '0,00' ?>%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-span-6 dash-panel p-4">
                <div class="text-white font-semibold">Clientes</div>
                <div class="mt-2">
                    <table class="w-full text-sm text-white/90">
                        <thead>
                            <tr>
                                <th class="text-left p-2">Cliente</th>
                                <th class="text-right p-2">Peso Total</th>
                                <th class="text-right p-2">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientsCombined as $c): ?>
                                <tr class="border-b border-white/10">
                                    <td class="p-2"><?= htmlspecialchars($c['client_name']) ?></td>
                                    <td class="p-2 text-right"><?= number_format($c['delivered'], 0, ',', '.') ?></td>
                                    <td class="p-2 text-right"><?= $totalDeliveredMonth > 0 ? number_format(($c['delivered']/$totalDeliveredMonth)*100, 2, ',', '.') : '0,00' ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td class="p-2 font-semibold">Total</td>
                                <td class="p-2 text-right font-semibold"><?= number_format($totalDeliveredMonth, 0, ',', '.') ?></td>
                                <td class="p-2 text-right font-semibold"><?= $expectedTotalToDate > 0 ? number_format(($deliveredTotalToDate/$expectedTotalToDate)*100, 2, ',', '.') : '0,00' ?>%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function changeYear(year){ const params = new URLSearchParams(window.location.search); params.set('year', year); window.location.search = params.toString(); }
function changeMonth(month){ const params = new URLSearchParams(window.location.search); params.set('month', month); window.location.search = params.toString(); }
function toggleWeek(w){}
const days = <?= json_encode($dailySeriesDays) ?>;
const expected = <?= json_encode(array_values($dailySeriesExpected)) ?>;
const delivered = <?= json_encode(array_values($dailySeriesDelivered)) ?>;
const ctxLine = document.getElementById('lineChart').getContext('2d');
new Chart(ctxLine, {
    type: 'line',
    data: { labels: days, datasets: [
        { label: 'Esperado', data: expected, borderColor: '#f59e0b', backgroundColor: 'rgba(245, 158, 11, 0.2)', tension: 0.3, fill: true },
        { label: 'Entregue', data: delivered, borderColor: '#22c55e', backgroundColor: 'rgba(34, 197, 94, 0.2)', tension: 0.3, fill: true }
    ]},
    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
});
const gaugeCtx = document.getElementById('gaugeChart').getContext('2d');
new Chart(gaugeCtx, {
    type: 'doughnut',
    data: { labels: ['Entregue','Restante'], datasets: [{ data: [<?= json_encode($deliveredTotalToDate) ?>, Math.max(0, <?= json_encode($monthlyGoal) ?> - <?= json_encode($deliveredTotalToDate) ?>)], backgroundColor: ['#22c55e','#1f2937'], borderWidth: 0 }] },
    options: { rotation: -90, circumference: 180, cutout: '70%', plugins: { legend: { display: false } } }
});
const frontsData = <?= json_encode($frontsCombined) ?>;
const frontsLabels = frontsData.map(i => i.front_name);
const frontsGoals = frontsData.map(i => i.goal);
const frontsDelivered = frontsData.map(i => i.delivered);
const frontsCtx = document.getElementById('frontsBar').getContext('2d');
new Chart(frontsCtx, {
    type: 'bar',
    data: { labels: frontsLabels, datasets: [
        { label: 'Meta', data: frontsGoals, backgroundColor: '#f59e0b' },
        { label: 'Entregue', data: frontsDelivered, backgroundColor: '#22c55e' }
    ] },
    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, scales: { x: { beginAtZero: true } } }
});
const clientsData = <?= json_encode($clientsCombined) ?>;
const clientsLabels = clientsData.map(i => i.client_name);
const clientsGoal = clientsData.map(i => i.goal);
const clientsDelivered = clientsData.map(i => i.delivered);
const clientsCtx = document.getElementById('clientsGrouped').getContext('2d');
new Chart(clientsCtx, {
    type: 'bar',
    data: { labels: clientsLabels, datasets: [
        { label: 'Meta', data: clientsGoal, backgroundColor: '#f59e0b' },
        { label: 'Entregue', data: clientsDelivered, backgroundColor: '#22c55e' }
    ]},
    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
});
</script>
<?php include 'includes/footer.php'; ?>
