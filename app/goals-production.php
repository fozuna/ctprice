<?php
require_once 'config/config.php';

// Configurar conexão com banco
$database = Database::getInstance();
$db = $database->getConnection();

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Instanciar modelos
$goalModel = new Goal($db);
$frontModel = new Front($db);
$productionModel = new Production($db);
$deliveryModel = new Delivery($db);
$inventoryModel = new Inventory($db);

function translateMonth($monthDate) {
    $monthsInPortuguese = [
        'January' => 'Janeiro',
        'February' => 'Fevereiro',
        'March' => 'Março',
        'April' => 'Abril',
        'May' => 'Maio',
        'June' => 'Junho',
        'July' => 'Julho',
        'August' => 'Agosto',
        'September' => 'Setembro',
        'October' => 'Outubro',
        'November' => 'Novembro',
        'December' => 'Dezembro'
    ];
    $englishMonth = $monthDate->format('F');
    $year = $monthDate->format('Y');
    return $monthsInPortuguese[$englishMonth] . '/' . $year;
}

// Obter parâmetros de filtro
$currentMonth = $_GET['month'] ?? date('Y-m');
$frontFilter = $_GET['front_id'] ?? '';
$clientFilter = '';

// Calcular datas do mês
$monthDate = new DateTime($currentMonth . '-01');
$startDate = $monthDate->format('Y-m-01');
$endDate = $monthDate->format('Y-m-t');

// Dados filtrados somente para metas de produção
$monthlySummary = $goalModel->getMonthlyGoalsSummary($monthDate->format('Y'), $monthDate->format('n'), $frontFilter, null, 'production');
$goalsWithPeriods = $goalModel->getGoalsWithPeriods($monthDate->format('Y'), $monthDate->format('n'), $frontFilter, null, 'production');
$dailyGoals = $goalModel->getDailyGoals($startDate, $endDate, $frontFilter, null, 'production');
$efficiencyRows = $productionModel->getDailyEfficiencyWithGoals($startDate, $endDate, $frontFilter ?: null, null);
// Mapear eficiência por chave data+frente+cliente
$efficiencyMap = [];
foreach ($efficiencyRows as $r) {
    $key = $r['goal_date'].'|'.$r['front_id'].'|'.$r['client_id'];
    $efficiencyMap[$key] = $r;
}
$fronts = $goalModel->getFrontsWithGoalsInMonth($monthDate->format('Y'), $monthDate->format('n'), 'production');
$clients = [];

// Totais
$weeklyTotals = [];
$monthlyTotal = 0;
foreach ($monthlySummary as $summary) {
    $monthlyTotal += $summary['monthly_total'];
}
foreach ($dailyGoals as $goal) {
    $weekKey = $goal['week_start_date'];
    if (!isset($weeklyTotals[$weekKey])) {
        $weeklyTotals[$weekKey] = 0;
    }
    $weeklyTotals[$weekKey] += $goal['weekly_goal'];
}

$daysInMonth = intval($monthDate->format('t'));
$sundays = 0; 
$cursor = new DateTime($startDate);
while ($cursor <= new DateTime($endDate)) {
    $dow = intval($cursor->format('w'));
    if ($dow === 0) { $sundays++; }
    $cursor->add(new DateInterval('P1D'));
}
// produção agora considera todos os dias
$workingDays = $daysInMonth;

// Agregações por dia para Produção e Meta
$productionRows = $productionModel->getProductionByDateRange($startDate, $endDate, $frontFilter ?: null, $clientFilter ?: null);
$productionByDate = [];
foreach ($productionRows as $row) {
    $d = $row['production_date'];
    if (!isset($productionByDate[$d])) { $productionByDate[$d] = 0; }
    $productionByDate[$d] += floatval($row['produced_value']);
}

$deliveriesRows = $deliveryModel->getDeliveryByDateRange($startDate, $endDate, $frontFilter ?: null, null);
$deliveriesByDate = [];
foreach ($deliveriesRows as $row) {
    $d = $row['delivery_date'];
    if (!isset($deliveriesByDate[$d])) { $deliveriesByDate[$d] = 0; }
    $deliveriesByDate[$d] += floatval($row['delivered_value']);
}

$dailyGoalByDate = [];
foreach ($dailyGoals as $g) {
    $d = $g['goal_date'];
    if (!isset($dailyGoalByDate[$d])) { $dailyGoalByDate[$d] = 0; }
    $dailyGoalByDate[$d] += floatval($g['daily_goal']);
}

$totalProduced = 0; foreach ($productionByDate as $v) { $totalProduced += $v; }
$monthlyGoalPlanned = $monthlyTotal;
$avgDailyGoal = $workingDays > 0 ? ($monthlyGoalPlanned / $workingDays) : 0;

// Métricas de ritmo e indicadores
$today = new DateTime(date('Y-m-d'));
$periodStart = new DateTime($startDate);
$periodEnd = new DateTime($endDate);
$observedEnd = $today < $periodEnd ? $today : $periodEnd;
$passedDays = 0; $remainingDays = 0;
$cursor = clone $periodStart;
while ($cursor <= $periodEnd) {
    if ($cursor <= $observedEnd) { $passedDays++; } else { $remainingDays++; }
    $cursor->add(new DateInterval('P1D'));
}
$dayRate = $passedDays > 0 ? ($totalProduced / $passedDays) : 0;
$massToProduce = max($monthlyGoalPlanned - $totalProduced, 0);
$monthlyPace = $remainingDays > 0 ? ($massToProduce / $remainingDays) : 0;
$daysWithProduction = 0; foreach ($productionByDate as $d => $v) { if ($v > 0) { $daysWithProduction++; } }
$availabilityPct = $daysInMonth > 0 ? round(($daysWithProduction / $daysInMonth) * 100, 2) : 0;
$utilizationPct = $monthlyGoalPlanned > 0 ? round(($totalProduced / $monthlyGoalPlanned) * 100, 2) : 0;

$calcLog = [];
try {
    $stmtHoliday = $db->prepare("SELECT holiday_date FROM holidays WHERE holiday_date BETWEEN :s AND :e");
    $stmtHoliday->bindValue(':s', $startDate);
    $stmtHoliday->bindValue(':e', $endDate);
    $stmtHoliday->execute();
    $holidayRows = $stmtHoliday->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $holidayRows = [];
}
$holidayMap = [];
foreach ($holidayRows as $hd) { $holidayMap[$hd] = true; }
$workingDatesMonSat = [];
$cursorBiz = new DateTime($startDate);
while ($cursorBiz <= new DateTime($endDate)) {
    $dow = intval($cursorBiz->format('w'));
    $dStr = $cursorBiz->format('Y-m-d');
    if ($dow >= 1 && $dow <= 6 && !isset($holidayMap[$dStr])) {
        $workingDatesMonSat[] = $dStr;
    }
    $cursorBiz->add(new DateInterval('P1D'));
}
$bizDaysCount = count($workingDatesMonSat);
if ($bizDaysCount === 0) {
    $calcLog[] = 'Dias úteis no mês: 0 — não é possível dividir a meta.';
    $dailyGoalBusiness = 0;
} else {
    $totalCents = (int)round($monthlyGoalPlanned * 100);
    $baseCents = intdiv($totalCents, $bizDaysCount);
    $remainder = $totalCents - ($baseCents * $bizDaysCount);
    $distribution = [];
    $sumDistCents = 0;
    for ($i = 0; $i < $bizDaysCount; $i++) {
        $add = ($i < $remainder) ? 1 : 0;
        $cents = $baseCents + $add;
        $distribution[] = ['date' => $workingDatesMonSat[$i], 'value' => $cents / 100.0];
        $sumDistCents += $cents;
    }
    $dailyGoalBusiness = round(($sumDistCents / $bizDaysCount) / 100.0, 2);
    $calcLog[] = 'Meta mensal: ' . number_format($monthlyGoalPlanned, 2, ',', '.');
    $calcLog[] = 'Domingos: ' . $sundays;
    $calcLog[] = 'Feriados: ' . count($holidayRows);
    $calcLog[] = 'Dias úteis (Seg-Sáb): ' . $bizDaysCount;
    $calcLog[] = 'Base por dia: ' . number_format($baseCents / 100.0, 2, ',', '.');
    $calcLog[] = 'Dias com +0,01: ' . $remainder;
    $calcLog[] = 'Soma distribuída: ' . number_format($sumDistCents / 100.0, 2, ',', '.');
}

$projection = $totalProduced + ($remainingDays * $dayRate);
$projectionPct = $monthlyGoalPlanned > 0 ? round(($projection / $monthlyGoalPlanned) * 100, 2) : 0;
$projectionGap = max($monthlyGoalPlanned - $projection, 0);
$projectionGapPct = $monthlyGoalPlanned > 0 ? round(($projectionGap / $monthlyGoalPlanned) * 100, 2) : 0;

$labelsDays = []; $prodSeries = []; $goalSeries = []; $delSeries = [];
$c = new DateTime($startDate); $limit = new DateTime($endDate);
while ($c <= $limit) {
    $d = $c->format('Y-m-d');
    $labelsDays[] = date('d/m', strtotime($d));
    $prodSeries[] = isset($productionByDate[$d]) ? round((float)$productionByDate[$d], 2) : 0;
    $goalSeries[] = isset($dailyGoalByDate[$d]) ? round((float)$dailyGoalByDate[$d], 2) : 0;
    $delSeries[] = isset($deliveriesByDate[$d]) ? round((float)$deliveriesByDate[$d], 2) : 0;
    $c->add(new DateInterval('P1D'));
}

$openingBalanceVal = !empty($frontFilter) ? $inventoryModel->getOpeningBalanceForFront($frontFilter, $startDate) : 0;
$productionAccumulatedToDate = 0; $deliveriesAccumulatedToDate = 0;
$cursor2 = new DateTime($startDate);
while ($cursor2 <= $observedEnd) {
    $d2 = $cursor2->format('Y-m-d');
    $productionAccumulatedToDate += ($productionByDate[$d2] ?? 0);
    $deliveriesAccumulatedToDate += ($deliveriesByDate[$d2] ?? 0);
    $cursor2->add(new DateInterval('P1D'));
}
$stockMovedVal = $openingBalanceVal + $productionAccumulatedToDate - $deliveriesAccumulatedToDate;
$projectionBelowGoal = ($projection < $monthlyGoalPlanned);
$projHighlightClass = $projectionBelowGoal 
    ? 'bg-amber-100 border-amber-300 ring-2 ring-amber-400 text-amber-800' 
    : 'bg-green-100 border-green-300 ring-2 ring-green-400 text-green-800';
$projIcon = $projectionBelowGoal ? 'fa-exclamation-triangle' : 'fa-chart-line';
$pageTitle = 'Metas de Produção';
include 'includes/header.php';
?>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
        <div class="p-6 border-b border-silver-lake-blue">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                        <i class="fas fa-industry text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-rich-black">Gestão de Metas de Produção</h1>
                        <p class="text-paynes-gray">Considera todos os dias da semana • <?= translateMonth($monthDate) ?></p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <a href="goals.php" class="inline-flex items-center px-4 py-2 border border-silver-lake-blue text-paynes-gray rounded-lg hover:bg-eggshell transition-colors">
                        <i class="fas fa-list mr-2"></i>Todas as Metas
                    </a>
                    <a href="new-production-goal.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Nova Meta de Produção
                    </a>
                    <a href="production-entry.php" class="inline-flex items-center px-4 py-2 border border-silver-lake-blue text-paynes-gray rounded-lg hover:bg-eggshell transition-colors">
                        <i class="fas fa-clipboard-check mr-2"></i>Registrar Produção
                    </a>
                    <a href="goals-delivery.php" class="inline-flex items-center px-4 py-2 border border-silver-lake-blue text-paynes-gray rounded-lg hover:bg-eggshell transition-colors">
                        <i class="fas fa-truck mr-2"></i>Metas de Entrega
                    </a>
                </div>
            </div>
        </div>
        <div class="p-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="month" class="block text-sm font-medium text-paynes-gray mb-2">Mês/Ano</label>
                    <input type="month" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="month" name="month" value="<?= $currentMonth ?>">
                </div>
                <div>
                    <label for="front_id" class="block text-sm font-medium text-paynes-gray mb-2">Frente</label>
                    <select class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="front_id" name="front_id">
                        <option value="">Todas as frentes</option>
                        <?php foreach ($fronts as $front): ?>
                            <option value="<?= $front['id'] ?>" <?= $frontFilter == $front['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($front['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-filter mr-2"></i>Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Painéis de Métricas -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
            <div class="px-4 py-3 border-b border-silver-lake-blue">
                <h3 class="text-sm font-semibold text-white bg-green-700 inline-block px-3 py-1 rounded">Mês</h3>
            </div>
            <div class="p-4 grid grid-cols-2 gap-3 text-sm">
                <div>
                    <div class="text-paynes-gray">Mês</div>
                    <div class="font-semibold text-rich-black uppercase"><?= strtoupper($monthDate->format('M Y')) ?></div>
                </div>
                <div>
                    <div class="text-paynes-gray">Dias no mês</div>
                    <div class="font-semibold text-rich-black"><?= $daysInMonth ?></div>
                </div>
                <div>
                    <div class="text-paynes-gray">Meta mensal</div>
                    <div class="font-semibold text-green-700"><?= number_format($monthlyGoalPlanned, 2, ',', '.') ?></div>
                </div>
                <div>
                    <div class="text-paynes-gray">Domingos no mês</div>
                    <div class="font-semibold text-rich-black"><?= $sundays ?></div>
                </div>
                <div>
                    <div class="text-paynes-gray">Meta diária (dias úteis)</div>
                    <div class="font-semibold text-blue-700"><?= number_format($dailyGoalBusiness ?? 0, 2, ',', '.') ?></div>
                </div>
                <div>
                    <div class="text-paynes-gray">Produzido no mês</div>
                    <div class="font-semibold text-indigo-700"><?= number_format($totalProduced, 2, ',', '.') ?></div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
            <div class="px-4 py-3 border-b border-silver-lake-blue">
                <h3 class="text-sm font-semibold text-white bg-green-700 inline-block px-3 py-1 rounded">Projeção de Produção</h3>
            </div>
            <div class="p-4 grid grid-cols-2 gap-3 text-sm">
                <div>
                    <div class="text-paynes-gray">Média diária observada</div>
                    <div class="font-semibold text-rich-black"><?= number_format($dayRate, 2, ',', '.') ?></div>
                </div>
                <div>
                    <div class="text-paynes-gray">Dias restantes</div>
                    <div class="font-semibold text-rich-black"><?= $remainingDays ?></div>
                </div>
                <div class="md:col-span-2">
                    <div class="text-paynes-gray mb-2">Projeção fim do mês</div>
                    <div class="flex items-center gap-3 p-4 rounded-lg border <?= $projHighlightClass ?>">
                        <div class="p-3 rounded-full <?= $projectionBelowGoal ? 'bg-amber-200' : 'bg-green-200' ?>">
                            <i class="fas <?= $projIcon ?> text-xl"></i>
                        </div>
                        <div>
                            <div class="text-2xl font-bold"><?= number_format($projection, 2, ',', '.') ?></div>
                            <div class="text-xs">Meta: <?= number_format($monthlyGoalPlanned, 2, ',', '.') ?> • Atingimento: <?= number_format($projectionPct, 2, ',', '.') ?>%</div>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="text-paynes-gray">Atingimento estimado</div>
                    <div class="font-semibold text-green-700"><?= number_format($projectionPct, 2, ',', '.') ?>%</div>
                </div>
                <div>
                    <div class="text-paynes-gray">Diferença para meta</div>
                    <div class="font-semibold text-amber-700"><?= number_format($projectionGap, 2, ',', '.') ?></div>
                </div>
                <div>
                    <div class="text-paynes-gray">Diferença (%)</div>
                    <div class="font-semibold text-amber-700"><?= number_format($projectionGapPct, 2, ',', '.') ?>%</div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
            <div class="px-4 py-3 border-b border-silver-lake-blue">
                <h3 class="text-sm font-semibold text-white bg-green-700 inline-block px-3 py-1 rounded">Estoque Movimentado</h3>
            </div>
            <div class="p-4 text-sm">
                <?php if (empty($frontFilter)): ?>
                    <div class="p-3 rounded bg-amber-50 text-amber-700 border border-amber-200">
                        <i class="fas fa-info-circle mr-2"></i>Selecione uma frente para visualizar o estoque movimentado.
                    </div>
                <?php else: ?>
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded border border-silver-lake-blue p-3">
                        <div class="text-paynes-gray">Estoque Inicial</div>
                        <div id="stockOpening" class="font-semibold text-rich-black"><?= number_format($openingBalanceVal, 2, ',', '.') ?></div>
                    </div>
                    <div class="rounded border border-silver-lake-blue p-3">
                        <div class="text-paynes-gray">Produção acumulada</div>
                        <div id="stockProdAccum" class="font-semibold text-green-700"><?= number_format($productionAccumulatedToDate, 2, ',', '.') ?></div>
                    </div>
                    <div class="rounded border border-silver-lake-blue p-3">
                        <div class="text-paynes-gray">Cargas retiradas</div>
                        <div id="stockDelAccum" class="font-semibold text-blue-700"><?= number_format($deliveriesAccumulatedToDate, 2, ',', '.') ?></div>
                    </div>
                    <div class="rounded border border-silver-lake-blue p-3 <?= $stockMovedVal < 0 ? 'bg-amber-50 border-amber-200' : '' ?>">
                        <div class="text-paynes-gray">Estoque movimentado</div>
                        <div id="stockMoved" class="font-bold text-indigo-700"><?= number_format($stockMovedVal, 2, ',', '.') ?></div>
                    </div>
                </div>
                <div class="mt-3 text-xs text-paynes-gray">Fórmula: Estoque Inicial + Produção acumulada − Cargas retiradas</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Gráficos de Produção -->
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
        <div class="p-6 border-b border-silver-lake-blue">
            <h3 class="text-lg font-semibold text-rich-black">Resumo Gráfico de Produção</h3>
        </div>
        <div class="p-6">
            <?php $hasChartData = !empty($labelsDays) && (array_sum($prodSeries) > 0 || array_sum($goalSeries) > 0); ?>
            <?php if (!$hasChartData): ?>
                <div class="text-center py-10">
                    <h5 class="text-lg font-medium text-paynes-gray mb-2">Sem dados suficientes para gráficos</h5>
                    <p class="text-paynes-gray">Cadastre metas e produções para este período.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white rounded-lg border border-silver-lake-blue p-4">
                        <h4 class="text-sm font-semibold text-paynes-gray mb-3">Produção Diária</h4>
                        <canvas id="dailyProdChart" height="140"></canvas>
                    </div>
                    <div class="bg-white rounded-lg border border-silver-lake-blue p-4">
                        <h4 class="text-sm font-semibold text-paynes-gray mb-3">Meta x Produção (Diária)</h4>
                        <canvas id="goalVsProdChart" height="140"></canvas>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Distribuição da Meta pelos Dias Úteis -->
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
        <div class="p-6 border-b border-silver-lake-blue">
            <h3 class="text-lg font-semibold text-rich-black">Distribuição da Meta pelos Dias Úteis</h3>
        </div>
        <div class="p-6">
            <?php if (empty($bizDaysCount) || $bizDaysCount === 0): ?>
                <div class="p-3 rounded bg-red-50 text-red-700 border border-red-200">Não há dias úteis disponíveis para o cálculo neste mês.</div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-paynes-gray text-white">
                                <th class="px-4 py-3 text-left font-semibold">Data</th>
                                <th class="px-4 py-3 text-right font-semibold">Meta do dia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($distribution as $row): ?>
                                <tr class="border-b border-eggshell">
                                    <td class="px-4 py-3"><?= date('d/m/Y', strtotime($row['date'])) ?></td>
                                    <td class="px-4 py-3 text-right"><?= number_format($row['value'], 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td class="px-4 py-3 font-semibold">Total</td>
                                <td class="px-4 py-3 text-right font-semibold"><?= number_format($monthlyGoalPlanned, 2, ',', '.') ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    <div class="text-sm font-semibold text-paynes-gray mb-2">Logs do cálculo</div>
                    <ul class="list-disc pl-6 text-sm text-paynes-gray">
                        <?php foreach ($calcLog as $l): ?>
                            <li><?= htmlspecialchars($l) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Metas Cadastradas (Produção) -->
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
        <div class="p-6 border-b border-silver-lake-blue">
            <h3 class="text-lg font-semibold text-rich-black">Metas Cadastradas (Produção)</h3>
        </div>
        <div class="p-6">
            <?php 
                $productionGoals = $goalModel->getGoalsWithPeriods($monthDate->format('Y'), $monthDate->format('n'), $frontFilter, null, 'production');
            ?>
            <?php if (empty($productionGoals)) : ?>
                <div class="text-center py-6 text-paynes-gray">Nenhuma meta de produção cadastrada para este período.</div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-paynes-gray text-white">
                                <th class="px-4 py-3 text-left font-semibold">Frente</th>
                                <th class="px-4 py-3 text-left font-semibold">Cliente</th>
                                <th class="px-4 py-3 text-left font-semibold">Início</th>
                                <th class="px-4 py-3 text-left font-semibold">Fim</th>
                                <th class="px-4 py-3 text-right font-semibold">Meta Total</th>
                                <th class="px-4 py-3 text-right font-semibold">Meta Mensal</th>
                                <th class="px-4 py-3 text-center font-semibold">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productionGoals as $g): ?>
                                <?php $monthlyVal = $goalModel->computeMonthlyValueForGoalRow($monthDate, $g); ?>
                                <tr class="border-b border-eggshell">
                                    <td class="px-4 py-3"><?= htmlspecialchars($g['front_name']) ?></td>
                                    <td class="px-4 py-3"><?= htmlspecialchars($g['client_name'] ?? '-') ?></td>
                                    <td class="px-4 py-3"><?= date('d/m/Y', strtotime($g['start_date'])) ?></td>
                                    <td class="px-4 py-3"><?= date('d/m/Y', strtotime($g['end_date'])) ?></td>
                                    <td class="px-4 py-3 text-right"><?= number_format($g['total_goal'], 2, ',', '.') ?></td>
                                    <td class="px-4 py-3 text-right"><?= number_format($monthlyVal, 2, ',', '.') ?></td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="inline-flex items-center justify-center space-x-2">
                                            <a href="edit-goal.php?id=<?= $g['id'] ?>" 
                                               class="inline-flex items-center px-2 py-1 border border-blue-300 text-blue-600 rounded hover:bg-blue-50 transition-colors" 
                                               title="Editar Meta">
                                                <i class="fas fa-edit text-sm"></i>
                                            </a>
                                            <button type="button" 
                                                    class="inline-flex items-center px-2 py-1 border border-red-300 text-red-600 rounded hover:bg-red-50 transition-colors" 
                                                    onclick="confirmDelete(<?= $g['id'] ?>)" 
                                                    title="Excluir Meta">
                                                <i class="fas fa-trash text-sm"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Metas Diárias -->
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
        <div class="p-6 border-b border-silver-lake-blue">
            <h3 class="text-lg font-semibold text-rich-black">Metas Diárias (Produção)</h3>
        </div>
        <div class="p-6">
            <?php if (empty($frontFilter)) : ?>
                <div class="text-center py-8">
                    <h5 class="text-lg font-medium text-paynes-gray mb-2">Selecione uma frente para visualizar o detalhamento por dia.</h5>
                    <p class="text-paynes-gray">Sem filtro de frente, exibimos apenas o total mensal.</p>
                </div>
            <?php elseif (empty($dailyGoals)) : ?>
                <div class="text-center py-10">
                    <h5 class="text-lg font-medium text-paynes-gray mb-2">Nenhuma meta diária de produção encontrada</h5>
                    <p class="text-paynes-gray mb-6">Crie uma nova meta para visualizar as metas diárias de produção.</p>
                    <a href="new-goal.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Criar Nova Meta
                    </a>
                </div>
            <?php else: ?>
                <?php
                    function dayNamePt($ymd) {
                        $days = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
                        $w = intval(date('w', strtotime($ymd))); return strtolower($days[$w]);
                    }
                    $firstHalfDays = []; $secondHalfDays = [];
                    for ($d = 1; $d <= $daysInMonth; $d++) {
                        $dateStr = $monthDate->format('Y-m-') . str_pad($d, 2, '0', STR_PAD_LEFT);
                        if ($d <= 15) { $firstHalfDays[] = $dateStr; } else { $secondHalfDays[] = $dateStr; }
                    }
                    function renderHalfTable($days, $dailyGoalByDate, $productionByDate, $deliveriesByDate, $openingBalance) {
                        $cumulative = 0;
                        echo '<div class="mb-6">';
                        echo '<div class="px-4 py-2 bg-green-700 text-white font-semibold rounded-t">'.($days[0] && intval(substr($days[0],8,2))<=15 ? 'PRIMEIRA QUINZENA' : 'SEGUNDA QUINZENA').'</div>';
                        echo '<div class="overflow-x-auto">';
                        echo '<table class="min-w-full text-sm border border-green-700">';
                        // Linha de datas
                        echo '<thead class="bg-white"><tr>'; echo '<th class="px-3 py-2 text-left bg-green-700 text-white">DATA</th>';
                        foreach ($days as $d) { echo '<th class="px-3 py-2 text-center">'.date('d/m', strtotime($d)).'<div class="text-xs text-paynes-gray">'.dayNamePt($d).'</div></th>'; }
                        echo '<th class="px-3 py-2 text-center bg-green-700 text-white">TOTAL</th>';
                        echo '</tr></thead>';
                        echo '<tbody>';
                        // Meta diária
                        echo '<tr class="bg-eggshell"><td class="px-3 py-2 font-semibold bg-green-700 text-white">META DIÁRIA</td>';
                        $sumGoal = 0; foreach ($days as $d) { $v = $dailyGoalByDate[$d] ?? 0; $sumGoal += $v; echo '<td class="px-3 py-2 text-center">'.number_format($v, 2, ',', '.').'</td>'; }
                        echo '<td class="px-3 py-2 text-center font-semibold">'.number_format($sumGoal, 2, ',', '.').'</td></tr>';
                        // Ton produzido
                        echo '<tr><td class="px-3 py-2 font-semibold bg-green-700 text-white">TON PRODUZIDO</td>';
                        $sumProd = 0; foreach ($days as $d) { $p = $productionByDate[$d] ?? 0; $sumProd += $p; $cumulative += $p; echo '<td class="px-3 py-2 text-center">'.($p>0?number_format($p, 2, ',', '.'):'-').'</td>'; }
                        echo '<td class="px-3 py-2 text-center font-semibold">'.number_format($sumProd, 2, ',', '.').'</td></tr>';
                        // Entregas (retirada)
                        echo '<tr><td class="px-3 py-2 font-semibold bg-green-700 text-white">ENTREGAS (RETIRADA)</td>';
                        $sumDel = 0; foreach ($days as $d) { $v = $deliveriesByDate[$d] ?? 0; $sumDel += $v; echo '<td class="px-3 py-2 text-center">'.($v>0?number_format($v, 2, ',', '.'):'-').'</td>'; }
                        echo '<td class="px-3 py-2 text-center font-semibold">'.number_format($sumDel, 2, ',', '.').'</td></tr>';
                        // Variação
                        echo '<tr class="bg-eggshell"><td class="px-3 py-2 font-semibold bg-green-700 text-white">VARIAÇÃO</td>';
                        foreach ($days as $d) { $g = $dailyGoalByDate[$d] ?? 0; $p = $productionByDate[$d] ?? 0; $pct = ($g>0? round(($p/$g)*100,2):0); echo '<td class="px-3 py-2 text-center">'.($g>0? $pct.'%':'-').'</td>'; }
                        echo '<td class="px-3 py-2 text-center font-semibold">'.($sumGoal>0? round(($sumProd/$sumGoal)*100,2).'%' : '-').'</td></tr>';
                        // Estoque (saldo inicial + produção - entregas)
                        echo '<tr><td class="px-3 py-2 font-semibold bg-green-700 text-white">ESTOQUE</td>';
                        $running = floatval($openingBalance);
                        foreach ($days as $d) { 
                            $running += ($productionByDate[$d] ?? 0) - ($deliveriesByDate[$d] ?? 0); 
                            echo '<td class="px-3 py-2 text-center">'.number_format($running, 2, ',', '.').'</td>'; 
                        }
                        echo '<td class="px-3 py-2 text-center font-semibold">'.number_format($running, 2, ',', '.').'</td></tr>';
                        echo '</tbody></table></div></div>';
                    }
                ?>
                <?php 
                    $openingBalance = $openingBalanceVal;
                    renderHalfTable($firstHalfDays, $dailyGoalByDate, $productionByDate, $deliveriesByDate, $openingBalance); 
                    renderHalfTable($secondHalfDays, $dailyGoalByDate, $productionByDate, $deliveriesByDate, $openingBalance); 
                ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
var labels = <?= json_encode($labelsDays) ?>;
var prod = <?= json_encode($prodSeries) ?>;
var goal = <?= json_encode($goalSeries) ?>;
var del = <?= json_encode($delSeries) ?>;
var openingBalanceJs = <?= json_encode($openingBalanceVal) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function updateStockCard() {
    try {
        var todayIndex = new Date().getDate() - 1;
        if (todayIndex < 0) { todayIndex = 0; }
        var prodAccum = prod.slice(0, todayIndex + 1).reduce((a,b)=>a+(parseFloat(b)||0), 0);
        var delAccum = del.slice(0, todayIndex + 1).reduce((a,b)=>a+(parseFloat(b)||0), 0);
        var stockMoved = (parseFloat(openingBalanceJs)||0) + prodAccum - delAccum;
        var elOpen = document.getElementById('stockOpening');
        var elProd = document.getElementById('stockProdAccum');
        var elDel = document.getElementById('stockDelAccum');
        var elMoved = document.getElementById('stockMoved');
        if (elOpen) elOpen.textContent = (parseFloat(openingBalanceJs)||0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
        if (elProd) elProd.textContent = prodAccum.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
        if (elDel) elDel.textContent = delAccum.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
        if (elMoved) elMoved.textContent = stockMoved.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
    } catch(e) {}
}
updateStockCard();
setInterval(updateStockCard, 5000);
if (document.getElementById('dailyProdChart')) {
    var ctx1 = document.getElementById('dailyProdChart').getContext('2d');
    new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Produção (ton)',
                data: prod,
                backgroundColor: 'rgba(34, 197, 94, 0.4)',
                borderColor: 'rgba(34, 197, 94, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}
if (document.getElementById('goalVsProdChart')) {
    var ctx2 = document.getElementById('goalVsProdChart').getContext('2d');
    new Chart(ctx2, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Meta diária',
                    data: goal,
                    borderColor: 'rgba(59, 130, 246, 1)',
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    tension: 0.2
                },
                {
                    label: 'Produção diária',
                    data: prod,
                    borderColor: 'rgba(34, 197, 94, 1)',
                    backgroundColor: 'rgba(34, 197, 94, 0.2)',
                    tension: 0.2
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}
// Auto-submit dos filtros
document.getElementById('month').addEventListener('change', function() { this.form.submit(); });
document.getElementById('front_id').addEventListener('change', function() { this.form.submit(); });
var clientSelect = document.getElementById('client_id'); 
if (clientSelect) { clientSelect.addEventListener('change', function() { this.form.submit(); }); }

// Modal de exclusão reutilizado da página original
function confirmDelete(goalId) {
    if (confirm('Tem certeza que deseja excluir esta meta?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'goal-action.php';
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete';
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = goalId;
        const originInput = document.createElement('input');
        originInput.type = 'hidden';
        originInput.name = 'origin';
        originInput.value = 'production';
        form.appendChild(actionInput);
        form.appendChild(idInput);
        form.appendChild(originInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
