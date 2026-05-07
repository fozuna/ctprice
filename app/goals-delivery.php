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
$clientModel = new Client($db);
$deliveryModel = new Delivery($db);

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
$clientFilter = $_GET['client_id'] ?? '';

// Calcular datas do mês
$monthDate = new DateTime($currentMonth . '-01');
$startDate = $monthDate->format('Y-m-01');
$endDate = $monthDate->format('Y-m-t');

// Dados filtrados somente para metas de entrega
$monthlySummary = $goalModel->getMonthlyGoalsSummary($monthDate->format('Y'), $monthDate->format('n'), $frontFilter, $clientFilter, 'delivery');
$goalsWithPeriods = $goalModel->getGoalsWithPeriods($monthDate->format('Y'), $monthDate->format('n'), $frontFilter, $clientFilter, 'delivery');
$dailyGoals = $goalModel->getDailyGoals($startDate, $endDate, $frontFilter, $clientFilter, 'delivery');
$efficiencyRows = $deliveryModel->getDailyEfficiencyWithGoals($startDate, $endDate, $frontFilter ?: null, $clientFilter ?: null);
// Mapear eficiência por chave data+frente+cliente
$efficiencyMap = [];
foreach ($efficiencyRows as $r) {
    $key = $r['goal_date'].'|'.$r['front_id'].'|'.$r['client_id'];
    $efficiencyMap[$key] = $r;
}
$fronts = $goalModel->getFrontsWithGoalsInMonth($monthDate->format('Y'), $monthDate->format('n'), 'delivery');
$clients = $clientModel->findAll(['active' => 1]);

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

$pageTitle = 'Metas de Entrega';
include 'includes/header.php';
?>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
        <div class="p-6 border-b border-silver-lake-blue">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                        <i class="fas fa-truck-loading text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-rich-black">Gestão de Metas de Entrega</h1>
                        <p class="text-paynes-gray">Eficiência por dia de entrega • <?= translateMonth($monthDate) ?></p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <a href="goals.php" class="inline-flex items-center px-4 py-2 border border-silver-lake-blue text-paynes-gray rounded-lg hover:bg-eggshell transition-colors">
                        <i class="fas fa-list mr-2"></i>Todas as Metas
                    </a>
                    <a href="new-goal.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Nova Meta
                    </a>
                    <a href="delivery-entry.php" class="inline-flex items-center px-4 py-2 border border-silver-lake-blue text-paynes-gray rounded-lg hover:bg-eggshell transition-colors">
                        <i class="fas fa-clipboard-list mr-2"></i>Registrar Entrega
                    </a>
                    <a href="goals-production.php" class="inline-flex items-center px-4 py-2 border border-silver-lake-blue text-paynes-gray rounded-lg hover:bg-eggshell transition-colors">
                        <i class="fas fa-industry mr-2"></i>Metas de Produção
                    </a>
                </div>
            </div>
        </div>
        <div class="p-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
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
                <div>
                    <label for="client_id" class="block text-sm font-medium text-paynes-gray mb-2">Cliente</label>
                    <select class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="client_id" name="client_id">
                        <option value="">Todos os clientes</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>" <?= $clientFilter == $client['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($client['name']) ?> (<?= htmlspecialchars($client['code']) ?>)
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
            <div class="mt-3">
                <form method="POST" action="goal-action.php" class="inline">
                    <input type="hidden" name="action" value="recalc_month">
                    <input type="hidden" name="origin" value="delivery">
                    <input type="hidden" name="month" value="<?= htmlspecialchars($currentMonth) ?>">
                    <input type="hidden" name="front_id" value="<?= htmlspecialchars($frontFilter) ?>">
                    <input type="hidden" name="client_id" value="<?= htmlspecialchars($clientFilter) ?>">
                    <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors" onclick="return confirm('Recalcular metas diárias para o mês filtrado?')">
                        <i class="fas fa-sync-alt mr-2"></i>Recalcular mês
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Resumo Mensal -->
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
        <div class="p-6 border-b border-silver-lake-blue">
            <h3 class="text-lg font-semibold text-rich-black">Resumo Mensal de Metas de Entrega</h3>
        </div>
        <div class="p-6">
            <?php if (empty($monthlySummary)) : ?>
                <div class="text-center py-10">
                    <h5 class="text-lg font-medium text-paynes-gray mb-2">Nenhum dado de resumo para entrega</h5>
                    <p class="text-paynes-gray">Crie uma meta de entrega para este período.</p>
                </div>
            <?php else : ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-paynes-gray text-white">
                                <th class="px-4 py-3 text-left font-semibold">Frente</th>
                                <th class="px-4 py-3 text-left font-semibold">Cliente</th>
                                <th class="px-4 py-3 text-right font-semibold">Meta Mensal</th>
                                <th class="px-4 py-3 text-right font-semibold">Meta Semanal (média)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthlySummary as $summary): ?>
                                <tr class="border-b border-eggshell">
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-rich-black"><?= htmlspecialchars($summary['front_name']) ?></div>
                                        <div class="text-sm text-paynes-gray"><?= htmlspecialchars($summary['front_code']) ?></div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-rich-black"><?= htmlspecialchars($summary['client_name']) ?></div>
                                        <div class="text-sm text-paynes-gray"><?= htmlspecialchars($summary['client_code']) ?></div>
                                    </td>
                                    <td class="px-4 py-3 text-right"><span class="font-semibold text-blue-600"><?= number_format($summary['monthly_total'], 2, ',', '.') ?></span></td>
                                    <td class="px-4 py-3 text-right"><span class="font-semibold text-indigo-600"><?= number_format($summary['weekly_total'], 2, ',', '.') ?></span></td>
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
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-rich-black">Metas Diárias de Entrega</h3>
                <div class="text-xs px-3 py-1 rounded-full bg-amber-100 text-amber-700 border border-amber-200">
                    Domingos excluídos da meta (desde 03/2026)
                </div>
            </div>
        </div>
        <div class="p-6">
            <?php if (empty($dailyGoals)) : ?>
                <div class="text-center py-10">
                    <h5 class="text-lg font-medium text-paynes-gray mb-2">Nenhuma meta diária de entrega</h5>
                    <p class="text-paynes-gray">Crie uma meta de entrega.</p>
                </div>
            <?php else : ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-paynes-gray text-white">
                                <th class="px-4 py-3 text-left font-semibold">Data</th>
                                <th class="px-4 py-3 text-left font-semibold">Frente</th>
                                <th class="px-4 py-3 text-left font-semibold">Cliente</th>
                                <th class="px-4 py-3 text-right font-semibold">Meta Diária</th>
                                <th class="px-4 py-3 text-right font-semibold">Entregue</th>
                                <th class="px-4 py-3 text-right font-semibold">Eficiência</th>
                                <th class="px-4 py-3 text-right font-semibold">Meta Semanal</th>
                                <th class="px-4 py-3 text-right font-semibold">Meta Mensal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dailyGoals as $goal): ?>
                                <?php 
                                    $key = $goal['goal_date'].'|'.$goal['front_id'].'|'.$goal['client_id'];
                                    $eff = $efficiencyMap[$key] ?? null;
                                    $delivered = $eff ? $eff['delivered_value'] : 0;
                                    $effPct = $eff ? $eff['efficiency_percentage'] : 0;
                                    $isSunday = intval(date('w', strtotime($goal['goal_date']))) === 0;
                                ?>
                                <tr class="border-b border-eggshell <?= $isSunday ? 'bg-eggshell' : '' ?>">
                                    <td class="px-4 py-3">
                                        <div><?= date('d/m/Y', strtotime($goal['goal_date'])) ?></div>
                                        <?php if ($isSunday): ?>
                                            <span class="inline-block mt-1 text-xs px-2 py-1 rounded-full bg-rose-100 text-rose-700 border border-rose-200">
                                                Domingo — excluído
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-rich-black"><?= htmlspecialchars($goal['front_name']) ?></div>
                                        <div class="text-sm text-paynes-gray"><?= htmlspecialchars($goal['front_code']) ?></div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-rich-black"><?= htmlspecialchars($goal['client_name']) ?></div>
                                        <div class="text-sm text-paynes-gray"><?= htmlspecialchars($goal['client_code']) ?></div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="font-semibold <?= $isSunday ? 'text-paynes-gray line-through' : 'text-blue-600' ?>">
                                            <?= number_format($goal['daily_goal'], 2, ',', '.') ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="font-semibold text-indigo-600"><?= number_format($delivered, 2, ',', '.') ?></span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="font-semibold <?= ($effPct >= 100 ? 'text-green-600' : ($effPct >= 80 ? 'text-amber-600' : 'text-red-600')) ?>"><?= number_format($effPct, 2, ',', '.') ?>%</span>
                                    </td>
                                    <td class="px-4 py-3 text-right"><span class="text-paynes-gray"><?= number_format($goal['weekly_goal'], 2, ',', '.') ?></span></td>
                                    <td class="px-4 py-3 text-right"><span class="text-paynes-gray"><?= number_format($goal['monthly_goal'], 2, ',', '.') ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.getElementById('month').addEventListener('change', function() { this.form.submit(); });
document.getElementById('front_id').addEventListener('change', function() { this.form.submit(); });
document.getElementById('client_id').addEventListener('change', function() { this.form.submit(); });
</script>

<?php include 'includes/footer.php'; ?>
