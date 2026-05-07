<?php
if (file_exists(__DIR__ . '/config/autoload.php')) {
    require_once __DIR__ . '/config/autoload.php';
}
require_once 'config/config.php';
require_once 'classes/Auth.php';

// Verificar autenticação
$database = Database::getInstance();
$db = $database->getConnection();
$auth = new Auth($db);
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Inicializar modelos
$goalModel = new Goal($db);
$clientModel = new Client($db);
$frontModel = new Front($db);

// Obter ano selecionado (padrão: ano atual)
$selectedYear = $_GET['year'] ?? date('Y');
$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : 1;

// Buscar dados para o dashboard
$yearlyStats = $goalModel->getYearlyStats($selectedYear);
$monthlyGoals = $goalModel->getMonthlyGoalsForYear($selectedYear);
$monthlyGoalsProd = $goalModel->getMonthlyGoalsForYearByType($selectedYear, 'production');
$monthlyGoalsDel = $goalModel->getMonthlyGoalsForYearByType($selectedYear, 'delivery');
$goalsByFrontProduction = $goalModel->getGoalsByFrontForYearByType($selectedYear, 'production');
$goalsByFrontDelivery = $goalModel->getGoalsByFrontForYearByType($selectedYear, 'delivery');
$goalsByClient = $goalModel->getGoalsByClientForYear($selectedYear);
$topGoals = $goalModel->getTopGoalsForYear($selectedYear, 5);
$availableYears = $goalModel->getAvailableYears();

// Comparação com ano anterior
$previousYear = $selectedYear - 1;
$yearComparison = $goalModel->getYearComparison($selectedYear, $previousYear);
$monthlyGoalsPrev = $goalModel->getMonthlyGoalsForYear($previousYear);
$monthlyGoalsProdPrev = $goalModel->getMonthlyGoalsForYearByType($previousYear, 'production');
$monthlyGoalsDelPrev = $goalModel->getMonthlyGoalsForYearByType($previousYear, 'delivery');

// Mapear totais mensais para comparação mês a mês
$monthlyTotalsCurrent = array_fill(1, 12, 0);
$monthlyTotalsProd = array_fill(1, 12, 0);
$monthlyTotalsDel = array_fill(1, 12, 0);
foreach ($monthlyGoals as $item) {
    $m = intval($item['month']);
    $monthlyTotalsCurrent[$m] = floatval($item['total_monthly_goal'] ?? 0);
}
$prodMap = [];
foreach ($monthlyGoalsProd as $item) {
    $m = intval($item['month']);
    $monthlyTotalsProd[$m] = floatval($item['total_monthly_goal'] ?? 0);
}
$delMap = [];
foreach ($monthlyGoalsDel as $item) {
    $m = intval($item['month']);
    $monthlyTotalsDel[$m] = floatval($item['total_monthly_goal'] ?? 0);
}
$monthlyTotalsPrev = array_fill(1, 12, 0);
$monthlyTotalsProdPrev = array_fill(1, 12, 0);
$monthlyTotalsDelPrev = array_fill(1, 12, 0);
foreach ($monthlyGoalsPrev as $item) {
    $m = intval($item['month']);
    $monthlyTotalsPrev[$m] = floatval($item['total_monthly_goal'] ?? 0);
}
$prodPrevMap = [];
foreach ($monthlyGoalsProdPrev as $item) {
    $m = intval($item['month']);
    $monthlyTotalsProdPrev[$m] = floatval($item['total_monthly_goal'] ?? 0);
}
$delPrevMap = [];
foreach ($monthlyGoalsDelPrev as $item) {
    $m = intval($item['month']);
    $monthlyTotalsDelPrev[$m] = floatval($item['total_monthly_goal'] ?? 0);
}

$monthlyTotalCurrent = $monthlyTotalsCurrent[$selectedMonth] ?? 0;
$monthlyTotalPrev = $monthlyTotalsPrev[$selectedMonth] ?? 0;
$monthlyValueVariation = ($monthlyTotalPrev > 0) ? (($monthlyTotalCurrent - $monthlyTotalPrev) / $monthlyTotalPrev) * 100 : 0;
$monthlyTotalProd = $monthlyTotalsProd[$selectedMonth] ?? 0;
$monthlyTotalDel = $monthlyTotalsDel[$selectedMonth] ?? 0;
$monthlyProdVariation = ($monthlyTotalsProdPrev[$selectedMonth] ?? 0) > 0 ? (($monthlyTotalProd - $monthlyTotalsProdPrev[$selectedMonth]) / $monthlyTotalsProdPrev[$selectedMonth]) * 100 : 0;
$monthlyDelVariation = ($monthlyTotalsDelPrev[$selectedMonth] ?? 0) > 0 ? (($monthlyTotalDel - $monthlyTotalsDelPrev[$selectedMonth]) / $monthlyTotalsDelPrev[$selectedMonth]) * 100 : 0;

$monthlySummaryDelivery = $goalModel->getMonthlyGoalsSummary($selectedYear, $selectedMonth, null, null, 'delivery');
$frontsMonthlyDeliveryMap = [];
foreach ($monthlySummaryDelivery as $row) {
    $key = ($row['front_name'] ?? 'Sem frente') . '|' . ($row['front_code'] ?? '');
    if (!isset($frontsMonthlyDeliveryMap[$key])) {
        $frontsMonthlyDeliveryMap[$key] = [
            'front_name' => $row['front_name'] ?? 'Sem frente',
            'front_code' => $row['front_code'] ?? null,
            'total_value' => 0
        ];
    }
    $frontsMonthlyDeliveryMap[$key]['total_value'] += floatval($row['monthly_total'] ?? 0);
}
$frontsMonthlyDelivery = array_values($frontsMonthlyDeliveryMap);
usort($frontsMonthlyDelivery, function($a, $b) { return $b['total_value'] <=> $a['total_value']; });

$clientsMonthlyDeliveryMap = [];
foreach ($monthlySummaryDelivery as $row) {
    $key = ($row['client_name'] ?? 'Sem cliente') . '|' . ($row['client_code'] ?? '');
    if (!isset($clientsMonthlyDeliveryMap[$key])) {
        $clientsMonthlyDeliveryMap[$key] = [
            'client_name' => $row['client_name'] ?? 'Sem cliente',
            'client_code' => $row['client_code'] ?? null,
            'total_value' => 0
        ];
    }
    $clientsMonthlyDeliveryMap[$key]['total_value'] += floatval($row['monthly_total'] ?? 0);
}
$clientsMonthlyDelivery = array_values($clientsMonthlyDeliveryMap);
usort($clientsMonthlyDelivery, function($a, $b) { return $b['total_value'] <=> $a['total_value']; });

// Nome do mês em pt-BR
$monthNames = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];

// Processar dados de comparação
$currentYearData = null;
$previousYearData = null;
foreach ($yearComparison as $data) {
    if ($data['period'] === 'current') {
        $currentYearData = $data;
    } else {
        $previousYearData = $data;
    }
}

// Calcular variações percentuais
$totalValueVariation = 0;
$totalGoalsVariation = 0;
if ($previousYearData && $previousYearData['total_value'] > 0) {
    $totalValueVariation = (($currentYearData['total_value'] - $previousYearData['total_value']) / $previousYearData['total_value']) * 100;
}
if ($previousYearData && $previousYearData['total_goals'] > 0) {
    $totalGoalsVariation = (($currentYearData['total_goals'] - $previousYearData['total_goals']) / $previousYearData['total_goals']) * 100;
}

$pageTitle = 'Dashboard de Metas';
include 'includes/header.php';
?>

<div class="p-6 space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-rich-black flex items-center gap-2">
                <i class="fas fa-chart-line text-blue-600"></i>
                Dashboard de Metas - <?= $selectedYear ?>
            </h1>
            <p class="text-sm text-paynes-gray">
                Visão geral das metas • 
                <span class="font-semibold">
                    <?= number_format($yearlyStats['total_value'] ?? 0, 0, ',', '.') ?> toneladas
                </span>
            </p>
        </div>
        <div class="w-full md:w-1/2 xl:w-1/4">
            <div class="bg-white border border-silver-lake-blue rounded-lg shadow-sm p-3">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label for="yearFilter" class="text-sm font-medium text-blue-700 flex items-center gap-2 mb-1">
                            <i class="fas fa-calendar-alt"></i>
                            Ano
                        </label>
                        <select id="yearFilter" class="border border-blue-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full"
                                onchange="changeYear(this.value)">
                            <?php foreach ($availableYears as $year): ?>
                                <option value="<?= $year ?>" <?= $year == $selectedYear ? 'selected' : '' ?>>
                                    <?= $year ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="monthFilter" class="text-sm font-medium text-blue-700 flex items-center gap-2 mb-1">
                            <i class="fas fa-calendar"></i>
                            Mês
                        </label>
                        <select id="monthFilter" class="border border-blue-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full"
                                onchange="changeMonth(this.value)">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $m == $selectedMonth ? 'selected' : '' ?>>
                                    <?= $monthNames[$m] ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-white border border-silver-lake-blue rounded-lg shadow-sm p-4 flex items-center justify-between">
            <div>
                <div class="text-xs font-semibold text-green-700 uppercase">Metas de Produção (ton. no mês)</div>
                <div class="text-2xl font-bold text-rich-black">
                    <?= number_format($monthlyTotalProd, 0, ',', '.') ?>
                </div>
                <?php if ($monthlyProdVariation != 0): ?>
                <div class="text-xs <?= $monthlyProdVariation > 0 ? 'text-green-600' : 'text-red-600' ?>">
                    <i class="fas fa-arrow-<?= $monthlyProdVariation > 0 ? 'up' : 'down' ?>"></i>
                    <?= abs(round($monthlyProdVariation, 1)) ?>% vs <?= $monthNames[$selectedMonth] ?> <?= $previousYear ?>
                </div>
                <?php endif; ?>
            </div>
            <i class="fas fa-weight-hanging text-green-600/25 text-3xl"></i>
        </div>

        <div class="bg-white border border-silver-lake-blue rounded-lg shadow-sm p-4 flex items-center justify-between">
            <div>
                <div class="text-xs font-semibold text-blue-700 uppercase">Metas de Entregas (ton. no mês)</div>
                <div class="text-2xl font-bold text-rich-black">
                    <?= number_format($monthlyTotalDel, 0, ',', '.') ?>
                </div>
                <?php if ($monthlyDelVariation != 0): ?>
                <div class="text-xs <?= $monthlyDelVariation > 0 ? 'text-green-600' : 'text-red-600' ?>">
                    <i class="fas fa-arrow-<?= $monthlyDelVariation > 0 ? 'up' : 'down' ?>"></i>
                    <?= abs(round($monthlyDelVariation, 1)) ?>% vs <?= $monthNames[$selectedMonth] ?> <?= $previousYear ?>
                </div>
                <?php endif; ?>
            </div>
            <i class="fas fa-truck text-blue-600/25 text-3xl"></i>
        </div>

        <div class="bg-white border border-silver-lake-blue rounded-lg shadow-sm p-4 flex items-center justify-between">
            <div>
                <div class="text-xs font-semibold text-cyan-700 uppercase">Média por Meta</div>
                <div class="text-2xl font-bold text-rich-black">
                    <?= number_format($yearlyStats['avg_goal'] ?? 0, 1, ',', '.') ?>
                </div>
                <div class="text-xs text-paynes-gray">
                    Min: <?= number_format($yearlyStats['min_goal'] ?? 0, 0, ',', '.') ?> • Max: <?= number_format($yearlyStats['max_goal'] ?? 0, 0, ',', '.') ?>
                </div>
            </div>
            <i class="fas fa-chart-bar text-cyan-600/25 text-3xl"></i>
        </div>

        <div class="bg-white border border-silver-lake-blue rounded-lg shadow-sm p-4 flex items-center justify-between">
            <div>
                <div class="text-xs font-semibold text-amber-700 uppercase">Frentes / Clientes</div>
                <div class="text-2xl font-bold text-rich-black">
                    <?= $yearlyStats['total_fronts'] ?? 0 ?> / <?= $yearlyStats['total_clients'] ?? 0 ?>
                </div>
                <div class="text-xs text-paynes-gray">Frentes e clientes ativos</div>
            </div>
            <i class="fas fa-users text-amber-600/25 text-3xl"></i>
        </div>
    </div>

    <!-- Gráficos e Tabelas - Layout Otimizado -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
        <div class="xl:col-span-8 bg-white border border-silver-lake-blue rounded-lg shadow-sm">
            <div class="bg-blue-600 text-white rounded-t-lg px-4 py-2 flex items-center gap-2">
                <i class="fas fa-chart-line"></i>
                <span class="font-semibold">Metas por Mês - <?= $selectedYear ?></span>
            </div>
            <div class="p-4">
                <div class="h-72">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>
        <div class="xl:col-span-4 bg-white border border-silver-lake-blue rounded-lg shadow-sm">
            <div class="bg-green-600 text-white rounded-t-lg px-4 py-2 flex items-center gap-2">
                <i class="fas fa-trophy"></i>
                <span class="font-semibold">Top 5 Maiores Metas</span>
            </div>
            <div class="p-4">
                <?php if (empty($topGoals)): ?>
                    <div class="text-center text-paynes-gray py-8">
                        <i class="fas fa-info-circle text-2xl mb-2"></i>
                        <p class="text-sm">Nenhuma meta encontrada para <?= $selectedYear ?></p>
                    </div>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($topGoals as $index => $goal): ?>
                            <div class="flex items-center gap-3 p-2 bg-eggshell rounded">
                                <div class="bg-blue-600 text-white rounded-full w-7 h-7 flex items-center justify-center text-xs font-bold">
                                    <?= $index + 1 ?>
                                </div>
                                <div class="flex-1">
                                    <div class="font-semibold text-rich-black">
                                        <?= number_format($goal['total_goal'], 0, ',', '.') ?> ton
                                    </div>
                                    <div class="text-xs text-paynes-gray">
                                        <?= htmlspecialchars($goal['front_name'] ?? '') ?> - <?= htmlspecialchars($goal['client_name'] ?? '') ?>
                                    </div>
                                    <div class="text-xs text-paynes-gray">
                                        <?= date('d/m/Y', strtotime($goal['start_date'])) ?> - <?= date('d/m/Y', strtotime($goal['end_date'])) ?> (<?= $goal['duration_days'] ?> dias)
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Gráficos de Distribuição - Layout Melhorado -->
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="bg-white border border-silver-lake-blue rounded-lg shadow-sm">
            <div class="bg-cyan-600 text-white rounded-t-lg px-4 py-2 flex items-center gap-2">
                <i class="fas fa-chart-pie"></i>
                <span class="font-semibold">Metas de Entrega por Frentes</span>
            </div>
            <div class="p-4">
                <?php if (empty($frontsMonthlyDelivery)): ?>
                    <div class="text-center text-paynes-gray py-12">
                        <i class="fas fa-chart-pie text-3xl mb-3 opacity-50"></i>
                        <p>Nenhuma distribuição por frentes encontrada para <?= $selectedYear ?></p>
                    </div>
                <?php else: ?>
                    <div class="h-64">
                        <canvas id="frontsChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="bg-white border border-silver-lake-blue rounded-lg shadow-sm">
            <div class="bg-amber-500 text-white rounded-t-lg px-4 py-2 flex items-center gap-2">
                <i class="fas fa-chart-pie"></i>
                <span class="font-semibold">Distribuição por Clientes</span>
            </div>
            <div class="p-4">
                <?php if (empty($clientsMonthlyDelivery)): ?>
                    <div class="text-center text-paynes-gray py-12">
                        <i class="fas fa-chart-pie text-3xl mb-3 opacity-50"></i>
                        <p>Nenhuma distribuição por clientes encontrada para <?= $selectedYear ?></p>
                    </div>
                <?php else: ?>
                    <div class="h-64">
                        <canvas id="clientsChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tabelas de Análise -->
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="bg-white border border-silver-lake-blue rounded-lg shadow-sm">
            <div class="px-4 py-2 border-b border-silver-lake-blue">
                <span class="font-semibold text-blue-700">Metas por Frente</span>
            </div>
            <div class="p-4">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-eggshell text-paynes-gray">
                                <th class="px-3 py-2 text-left">Frente</th>
                                <th class="px-3 py-2 text-center">Clientes</th>
                                <th class="px-3 py-2 text-right">Total (ton)</th>
                                <th class="px-3 py-2 text-right">Média</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-silver-lake-blue">
                            <?php if (empty($goalsByFrontDelivery)): ?>
                                <tr>
                                    <td colspan="4" class="px-3 py-4 text-center text-paynes-gray">Nenhuma frente com metas em <?= $selectedYear ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($goalsByFrontDelivery as $front): ?>
                                    <tr>
                                        <td class="px-3 py-2">
                                            <span class="font-medium text-rich-black"><?= htmlspecialchars($front['front_name'] ?? '') ?></span>
                                            <span class="block text-xs text-paynes-gray"><?= htmlspecialchars($front['front_code'] ?? '') ?></span>
                                        </td>
                                        <td class="px-3 py-2 text-center"><?= $front['clients_count'] ?></td>
                                        <td class="px-3 py-2 text-right"><?= number_format($front['total_value'], 0, ',', '.') ?></td>
                                        <td class="px-3 py-2 text-right"><?= number_format($front['avg_goal'], 1, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="bg-white border border-silver-lake-blue rounded-lg shadow-sm">
            <div class="px-4 py-2 border-b border-silver-lake-blue">
                <span class="font-semibold text-blue-700">Metas por Cliente</span>
            </div>
            <div class="p-4">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-eggshell text-paynes-gray">
                                <th class="px-3 py-2 text-left">Cliente</th>
                                <th class="px-3 py-2 text-center">Frentes</th>
                                <th class="px-3 py-2 text-right">Total (ton)</th>
                                <th class="px-3 py-2 text-right">Média</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-silver-lake-blue">
                            <?php if (empty($goalsByClient)): ?>
                                <tr>
                                    <td colspan="4" class="px-3 py-4 text-center text-paynes-gray">Nenhum cliente com metas em <?= $selectedYear ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($goalsByClient as $client): ?>
                                    <tr>
                                        <td class="px-3 py-2">
                                            <span class="font-medium text-rich-black"><?= htmlspecialchars($client['client_name'] ?? '') ?></span>
                                            <span class="block text-xs text-paynes-gray"><?= htmlspecialchars($client['client_code'] ?? '') ?></span>
                                        </td>
                                        <td class="px-3 py-2 text-center"><?= $client['fronts_count'] ?></td>
                                        <td class="px-3 py-2 text-right"><?= number_format($client['total_value'], 0, ',', '.') ?></td>
                                        <td class="px-3 py-2 text-right"><?= number_format($client['avg_goal'], 1, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts para gráficos -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const monthlyDataProd = <?= json_encode($monthlyGoalsProd) ?>;
const monthlyDataDel = <?= json_encode($monthlyGoalsDel) ?>;

// Preparar dados do gráfico
const months = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
const monthlyValuesProd = new Array(12).fill(0);
const monthlyValuesDel = new Array(12).fill(0);

monthlyDataProd.forEach(item => {
    monthlyValuesProd[item.month - 1] = parseFloat(item.total_monthly_goal || 0);
});
monthlyDataDel.forEach(item => {
    monthlyValuesDel[item.month - 1] = parseFloat(item.total_monthly_goal || 0);
});

// Configurar gráfico mensal
const ctx = document.getElementById('monthlyChart').getContext('2d');
const monthlyChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: months,
        datasets: [
            {
                label: 'Produção (ton)',
                data: monthlyValuesProd,
                borderColor: 'rgb(16, 185, 129)',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            },
            {
                label: 'Entregas (ton)',
                data: monthlyValuesDel,
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString('pt-BR') + ' ton';
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + 
                               context.parsed.y.toLocaleString('pt-BR') + ' toneladas';
                    }
                }
            }
        }
    }
});

const frontsData = <?= json_encode($frontsMonthlyDelivery) ?>;
console.log('Dados das frentes:', frontsData);

if (frontsData && frontsData.length > 0) {
    const frontsLabels = frontsData.map(item => item.front_name || 'Sem nome');
    const frontsValues = frontsData.map(item => parseFloat(item.total_value || item.total_goal || 0));
    const frontsColors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#5a5c69', '#fd7e14', '#6f42c1'];

    const frontsCtx = document.getElementById('frontsChart');
    if (frontsCtx) {
        new Chart(frontsCtx, {
            type: 'doughnut',
            data: {
                labels: frontsLabels,
                datasets: [{
                    data: frontsValues,
                    backgroundColor: frontsColors.slice(0, frontsLabels.length),
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '50%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            font: {
                                size: 11
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label;
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0';
                                return label + ': ' + value.toLocaleString('pt-BR') + ' ton (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }
}

// Gráfico de Distribuição por Clientes
const clientsData = <?= json_encode($clientsMonthlyDelivery) ?>;
console.log('Dados dos clientes:', clientsData);

if (clientsData && clientsData.length > 0) {
    const clientsLabels = clientsData.map(item => item.client_name || 'Sem nome');
    const clientsValues = clientsData.map(item => parseFloat(item.total_value || item.total_goal || 0));
    const clientsColors = ['#17a2b8', '#28a745', '#ffc107', '#dc3545', '#6c757d', '#007bff', '#6f42c1', '#fd7e14', '#20c997'];

    const clientsCtx = document.getElementById('clientsChart');
    if (clientsCtx) {
        new Chart(clientsCtx, {
            type: 'doughnut',
            data: {
                labels: clientsLabels,
                datasets: [{
                    data: clientsValues,
                    backgroundColor: clientsColors.slice(0, clientsLabels.length),
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '50%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            font: {
                                size: 11
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label;
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0';
                                return label + ': ' + value.toLocaleString('pt-BR') + ' ton (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }
}

// Função para mudar ano
function changeYear(year) {
    window.location.href = '?year=' + year;
}
</script>

<?php include 'includes/footer.php'; ?>
