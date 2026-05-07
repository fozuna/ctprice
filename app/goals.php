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

/**
 * Gerar semanas dinamicamente para um mês específico
 */
function generateMonthWeeks($startDate, $endDate) {
    $weeks = [];
    $current = new DateTime($startDate);
    $end = new DateTime($endDate);
    
    while ($current <= $end) {
        $weekStart = clone $current;
        $dayOfWeekN = (int)$weekStart->format('N'); // 1 = segunda, 7 = domingo
        $daysToSubtract = $dayOfWeekN - 1;
        if ($daysToSubtract > 0) {
            $weekStart->sub(new DateInterval("P{$daysToSubtract}D"));
        }
        $weekEnd = clone $weekStart;
        $weekEnd->add(new DateInterval('P6D')); // segunda até domingo
        
        // Ajustar para os limites do mês
        if ($weekStart < $current) $weekStart = clone $current;
        if ($weekEnd > $end) $weekEnd = clone $end;
        
        $weeks[] = [
            'start' => clone $weekStart,
            'end' => clone $weekEnd
        ];
        
        // Avançar para a próxima semana
        $current = clone $weekEnd;
        $current->add(new DateInterval('P1D'));
    }
    
    return $weeks;
}

/**
 * Calcular valor semanal baseado nos períodos específicos das metas
 */
function calculateWeeklyValueFromGoals($goalsWithPeriods, $frontName, $clientName, $weekStart, $weekEnd, $monthStartDate, $monthEndDate) {
    $weekStartDate = new DateTime($weekStart);
    $weekEndDate = new DateTime($weekEnd);
    $totalValue = 0;
    
    foreach ($goalsWithPeriods as $goal) {
        // Verificar se a meta corresponde à frente e cliente
        if ($goal['front_name'] !== $frontName || $goal['client_name'] !== $clientName) {
            continue;
        }
        
        $goalStartDate = new DateTime($goal['start_date']);
        $goalEndDate = new DateTime($goal['end_date']);
        $goalValue = floatval($goal['total_goal']);
        
        // Verificar se há interseção entre o período da meta e a semana
        $intersectionStart = max($weekStartDate, $goalStartDate);
        $intersectionEnd = min($weekEndDate, $goalEndDate);
        
        if ($intersectionStart <= $intersectionEnd) {
            // Calcular total de dias da meta completa (período total da meta)
            $totalGoalDays = $goalStartDate->diff($goalEndDate)->days + 1;
            
            // Calcular dias da meta que estão na semana atual
            $daysInCurrentWeek = $intersectionStart->diff($intersectionEnd)->days + 1;
            
            // Calcular valor proporcional para a semana atual
            $weekValue = ($goalValue * $daysInCurrentWeek) / $totalGoalDays;
            $totalValue += $weekValue;
        }
    }
    
    return round($totalValue, 0);
}

/**
 * Calcular total semanal para todas as metas
 */
function calculateWeeklyTotalFromGoals($goalsWithPeriods, $weekStart, $weekEnd, $monthStartDate, $monthEndDate) {
    $weekStartDate = new DateTime($weekStart);
    $weekEndDate = new DateTime($weekEnd);
    $totalValue = 0;
    
    foreach ($goalsWithPeriods as $goal) {
        $goalStartDate = new DateTime($goal['start_date']);
        $goalEndDate = new DateTime($goal['end_date']);
        
        // Verificar se há interseção entre o período da meta e a semana
        $intersectionStart = max($weekStartDate, $goalStartDate);
        $intersectionEnd = min($weekEndDate, $goalEndDate);
        
        if ($intersectionStart <= $intersectionEnd) {
            // Gerar semanas dinamicamente para o mês
            $monthWeeks = generateMonthWeeks($monthStartDate, $monthEndDate);
            
            // Calcular total de dias da meta que estão dentro do mês
            $totalDaysInMonth = 0;
            foreach ($monthWeeks as $monthWeek) {
                if ($monthWeek['end'] >= $goalStartDate && $monthWeek['start'] <= $goalEndDate) {
                    $weekIntersectionStart = max($monthWeek['start'], $goalStartDate);
                    $weekIntersectionEnd = min($monthWeek['end'], $goalEndDate);
                    if ($weekIntersectionStart <= $weekIntersectionEnd) {
                        $totalDaysInMonth += $weekIntersectionStart->diff($weekIntersectionEnd)->days + 1;
                    }
                }
            }
            
            // Calcular quantos dias da meta estão nesta semana específica
            $daysInWeek = $intersectionStart->diff($intersectionEnd)->days + 1;
            
            // Calcular valor proporcional para esta semana
            if ($totalDaysInMonth > 0) {
                $weekValue = ($daysInWeek / $totalDaysInMonth) * $goal['total_goal'];
                $totalValue += $weekValue;
            }
        }
    }
    
    return round($totalValue, 0);
}

/**
 * Traduzir nome do mês para português
 */
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

// Buscar dados para o resumo mensal
$monthlySummary = $goalModel->getMonthlyGoalsSummary($monthDate->format('Y'), $monthDate->format('n'), $frontFilter, $clientFilter, 'delivery');

// Buscar metas com períodos específicos
$goalsWithPeriods = $goalModel->getGoalsWithPeriods($monthDate->format('Y'), $monthDate->format('n'), $frontFilter, $clientFilter, 'delivery');

// Buscar metas diárias
$dailyGoals = $goalModel->getDailyGoals($startDate, $endDate, $frontFilter, $clientFilter, 'delivery');

// Buscar frentes e clientes para filtros - apenas aqueles com metas no mês selecionado
$fronts = $goalModel->getFrontsWithGoalsInMonth($monthDate->format('Y'), $monthDate->format('n'), 'delivery');
$clients = $clientModel->findAll(['active' => 1]);

// Configuração da visualização de exclusão
$deleteView = isset($_GET['delete_view']) && $_GET['delete_view'] === '1';
$deleteStart = $_GET['delete_start'] ?? $startDate;
$deleteEnd = $_GET['delete_end'] ?? $endDate;
$goalsForDelete = $deleteView ? $goalModel->findByPeriod($deleteStart, $deleteEnd, $frontFilter, $clientFilter) : [];
if ($deleteView && !empty($goalsForDelete)) {
    $goalsForDelete = array_values(array_filter($goalsForDelete, function($g) {
        return ($g['goal_type'] ?? 'production') === 'delivery';
    }));
}

// Calcular totais por período
$weeklyTotals = [];
$monthlyTotal = 0;

foreach ($monthlySummary as $summary) {
    $monthlyTotal += $summary['monthly_total'];
}

// Agrupar metas diárias por semana
foreach ($dailyGoals as $goal) {
    $weekKey = $goal['week_start_date'];
    if (!isset($weeklyTotals[$weekKey])) {
        $weeklyTotals[$weekKey] = 0;
    }
    $weeklyTotals[$weekKey] += $goal['weekly_goal'];
}

$pageTitle = 'Gestão de Metas';
include 'includes/header.php';
?>

<div class="space-y-6">
    <!-- Cabeçalho Principal -->
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
        <div class="p-6 border-b border-silver-lake-blue">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                        <i class="fas fa-bullseye text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-rich-black">Gestão de Metas</h1>
                        <p class="text-paynes-gray">Demanda Contratada para <?= translateMonth($monthDate) ?></p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <a href="goals-production.php" class="inline-flex items-center px-4 py-2 border border-silver-lake-blue text-paynes-gray rounded-lg hover:bg-eggshell transition-colors">
                        <i class="fas fa-industry mr-2"></i>Metas de Produção
                    </a>
                    <a href="new-goal.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Nova Meta
                    </a>
                    <a href="clients.php" class="inline-flex items-center px-4 py-2 border border-silver-lake-blue text-paynes-gray rounded-lg hover:bg-eggshell transition-colors">
                        <i class="fas fa-users mr-2"></i>Clientes
                    </a>
                    <a href="goals.php?month=<?= urlencode($currentMonth) ?>&front_id=<?= urlencode($frontFilter) ?>&client_id=<?= urlencode($clientFilter) ?>&delete_view=1" class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                        <i class="fas fa-trash-alt mr-2"></i>Excluir Metas
                    </a>
                </div>
            </div>
        </div>
        <!-- Seção de Filtros -->
        <div class="p-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="month" class="block text-sm font-medium text-paynes-gray mb-2">Mês/Ano</label>
                    <input type="month" 
                           class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                           id="month" 
                           name="month" 
                           value="<?= $currentMonth ?>">
                </div>
                <div>
                    <label for="front_id" class="block text-sm font-medium text-paynes-gray mb-2">Frente</label>
                    <select class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                            id="front_id" 
                            name="front_id">
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
                    <select class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                            id="client_id" 
                            name="client_id">
                        <option value="">Todos os clientes</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>" <?= $clientFilter == $client['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($client['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" 
                            class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-filter mr-2"></i>Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($deleteView): ?>
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
        <div class="p-6 border-b border-silver-lake-blue">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-rich-black">Excluir Metas por Período</h3>
                <a href="goals.php?month=<?= urlencode($currentMonth) ?>&front_id=<?= urlencode($frontFilter) ?>&client_id=<?= urlencode($clientFilter) ?>" class="inline-flex items-center px-3 py-2 border border-silver-lake-blue text-paynes-gray rounded-lg hover:bg-eggshell transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar
                </a>
            </div>
        </div>
        <div class="p-6 space-y-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="hidden" name="delete_view" value="1">
                <div>
                    <label class="block text-sm font-medium text-paynes-gray mb-2">Início</label>
                    <input type="date" name="delete_start" value="<?= htmlspecialchars($deleteStart) ?>" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-paynes-gray mb-2">Fim</label>
                    <input type="date" name="delete_end" value="<?= htmlspecialchars($deleteEnd) ?>" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="flex items-end">
                        <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-filter mr-2"></i>Aplicar Período
                    </button>
                </div>
            </form>

            <form method="POST" action="goal-action.php" onsubmit="return confirm('Confirma a exclusão das metas selecionadas?');">
                <input type="hidden" name="action" value="delete_batch">
                    <input type="hidden" name="origin" value="delivery">
                <?php if (empty($goalsForDelete)): ?>
                    <div class="text-center py-12">
                        <div class="p-4 rounded-full bg-gray-100 text-gray-400 w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                            <i class="fas fa-list text-2xl"></i>
                        </div>
                        <h5 class="text-lg font-medium text-paynes-gray mb-2">Nenhuma meta encontrada para o período</h5>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-paynes-gray text-white">
                                    <th class="px-4 py-3 text-center font-semibold">
                                        <input type="checkbox" id="selectAll">
                                    </th>
                                    <th class="px-4 py-3 text-center font-semibold">Frente</th>
                                    <th class="px-4 py-3 text-center font-semibold">Cliente</th>
                                    <th class="px-4 py-3 text-center font-semibold">Período</th>
                                    <th class="px-4 py-3 text-center font-semibold">Tipo</th>
                                    <th class="px-4 py-3 text-center font-semibold">Total</th>
                                    <th class="px-4 py-3 text-center font-semibold">Unidade</th>
                                    <th class="px-4 py-3 text-center font-semibold">Descrição</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($goalsForDelete as $g): ?>
                                <tr class="hover:bg-eggshell">
                                    <td class="px-4 py-3 text-center">
                                        <input type="checkbox" name="ids[]" value="<?= $g['id'] ?>" class="row-check">
                                    </td>
                                    <td class="px-4 py-3 text-center"><?= htmlspecialchars($g['front_name']) ?></td>
                                    <td class="px-4 py-3 text-center"><?= htmlspecialchars($g['client_name']) ?></td>
                                    <td class="px-4 py-3 text-center"><?= date('d/m/Y', strtotime($g['start_date'])) ?> a <?= date('d/m/Y', strtotime($g['end_date'])) ?></td>
                                    <td class="px-4 py-3 text-center"><?= htmlspecialchars($g['goal_type'] ?? 'production') ?></td>
                                    <td class="px-4 py-3 text-center"><?= number_format($g['total_goal'], 2, ',', '.') ?></td>
                                    <td class="px-4 py-3 text-center"><?= htmlspecialchars($g['unit'] ?? 'toneladas') ?></td>
                                    <td class="px-4 py-3 text-center"><?= htmlspecialchars($g['description'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="flex items-center justify-end mt-4">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                            <i class="fas fa-trash-alt mr-2"></i>Excluir Selecionadas
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <script>
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.row-check').forEach(cb => cb.checked = selectAll.checked);
        });
    }
    </script>
    <?php endif; ?>

    <?php if (!$deleteView): ?>
    <!-- Resumo Mensal -->
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
        <div class="p-6 border-b border-silver-lake-blue">
            <h3 class="text-lg font-semibold text-rich-black">Resumo Mensal de Metas</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-paynes-gray text-white">
                        <th rowspan="2" class="px-4 py-3 text-center font-semibold border-r border-gray-300">ORIGEM</th>
                        <th rowspan="2" class="px-4 py-3 text-center font-semibold border-r border-gray-300">DESTINO</th>
                                    <?php
                                    // Gerar cabeçalhos das semanas usando a função dinâmica
                                    $weekHeaders = [];
                                    $weekDates = []; // Armazenar datas de início e fim de cada semana
                                    
                                    // Usar a função generateMonthWeeks para gerar semanas dinamicamente
                                    $monthWeeks = generateMonthWeeks($startDate, $endDate);
                                    
                                    foreach ($monthWeeks as $week) {
                                        $weekStart = $week['start'];
                                        $weekEnd = $week['end'];
                                        
                                        $weekKey = $weekStart->format('d/m') . ' à ' . $weekEnd->format('d/m');
                                        $weekHeaders[] = $weekKey;
                                        $weekDates[] = [
                                            'start' => $weekStart->format('Y-m-d'),
                                            'end' => $weekEnd->format('Y-m-d')
                                        ];
                                        echo "<th class='px-4 py-3 text-center font-semibold border-r border-gray-300'>{$weekKey}</th>";
                                    }
                                    ?>
                                    <th rowspan="2" class="px-4 py-3 text-center font-semibold">TOTAL</th>
                                </tr>
                            </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($monthlySummary)): ?>
                                    <tr>
                                        <td colspan="<?= count($weekHeaders) + 3 ?>" class="px-4 py-8 text-center text-paynes-gray">
                                            <i class="fas fa-info-circle mr-2"></i>
                                            Nenhuma meta encontrada para o período selecionado
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $groupedSummary = [];
                                    foreach ($monthlySummary as $summary) {
                                        $key = $summary['front_name'];
                                        if (!isset($groupedSummary[$key])) {
                                            $groupedSummary[$key] = [];
                                        }
                                        $groupedSummary[$key][] = $summary;
                                    }
                                    ?>
                                    
                                    <?php foreach ($groupedSummary as $frontName => $clients): ?>
                                        <?php $frontRowspan = count($clients); ?>
                                        <?php foreach ($clients as $index => $client): ?>
                                            <?php 
                                            // Adicionar classes de borda para separar as frentes
                                            $borderClasses = '';
                                            $borderStyle = '';
                                            if ($index === 0) {
                                                $borderClasses .= ' border-t-4'; // Borda superior na primeira linha da frente
                                                $borderStyle .= ' border-t-[#43647D]';
                                            }
                                            if ($index === count($clients) - 1) {
                                                $borderClasses .= ' border-b-4'; // Borda inferior na última linha da frente
                                                $borderStyle .= ' border-b-[#43647D]';
                                            }
                                            $allBorderClasses = $borderClasses . $borderStyle;
                                            ?>
                                            <tr class="hover:bg-eggshell<?= $allBorderClasses ?>">
                                                <?php if ($index === 0): ?>
                                                    <td rowspan="<?= $frontRowspan ?>" class="px-4 py-3 text-center font-semibold bg-eggshell border-r border-gray-200<?= $allBorderClasses ?>">
                                                        <?= htmlspecialchars($frontName) ?>
                                                    </td>
                                                <?php endif; ?>
                                                <td class="px-4 py-3 text-center border-r border-gray-200<?= $allBorderClasses ?>"><?= htmlspecialchars($client['client_name']) ?></td>
                                                
                                                <?php
                                                // Calcular valor semanal baseado nos períodos específicos das metas
                                                for ($i = 0; $i < count($weekHeaders); $i++):
                                                    $weeklyValue = calculateWeeklyValueFromGoals(
                                                        $goalsWithPeriods,
                                                        $frontName,
                                                        $client['client_name'],
                                                        $weekDates[$i]['start'],
                                                        $weekDates[$i]['end'],
                                                        $startDate,
                                                        $endDate
                                                    );
                                                ?>
                                                    <td class="px-4 py-3 text-center border-r border-gray-200 border-2 border-paynes-gray<?= $allBorderClasses ?>"><?= number_format($weeklyValue, 0, ',', '.') ?></td>
                                                <?php endfor; ?>
                                                
                                                <td class="px-4 py-3 text-center font-semibold<?= $allBorderClasses ?>"><?= number_format($client['monthly_total'], 0, ',', '.') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                    
                                    <!-- Linha de total -->
                                    <tr class="bg-silver-lake-blue text-white font-bold">
                                        <td colspan="2" class="px-4 py-3 text-center">TOTAL</td>
                                        <?php
                                        for ($i = 0; $i < count($weekHeaders); $i++):
                                            $weeklyTotal = calculateWeeklyTotalFromGoals(
                                                $goalsWithPeriods,
                                                $weekDates[$i]['start'],
                                                $weekDates[$i]['end'],
                                                $startDate,
                                                $endDate
                                            );
                                        ?>
                                            <td class="px-4 py-3 text-center border-r border-blue-400 border-2 border-paynes-gray"><?= number_format($weeklyTotal, 0, ',', '.') ?></td>
                                        <?php endfor; ?>
                                        <td class="px-4 py-3 text-center"><?= number_format($monthlyTotal, 0, ',', '.') ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
            </table>
        </div>
    </div>

    <!-- Listagem de Metas Diárias -->
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
        <div class="p-6 border-b border-silver-lake-blue">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="p-2 rounded-full bg-green-100 text-green-600 mr-3">
                        <i class="fas fa-calendar-day text-lg"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-rich-black">Metas Diárias - <?= translateMonth($monthDate) ?></h3>
                </div>
                <?php if (!empty($dailyGoals)): ?>
                <div class="flex items-center space-x-2">
                    <a href="export-daily-goals.php?month=<?= urlencode($currentMonth) ?>&front_id=<?= urlencode($frontFilter) ?>&client_id=<?= urlencode($clientFilter) ?>" 
                       class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
                       title="Exportar metas diárias para Excel">
                        <i class="fas fa-file-excel mr-2"></i>
                        Exportar Excel
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="p-6">
            <?php if (empty($dailyGoals)): ?>
                <div class="text-center py-12">
                    <div class="p-4 rounded-full bg-gray-100 text-gray-400 w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                        <i class="fas fa-calendar-times text-2xl"></i>
                    </div>
                    <h5 class="text-lg font-medium text-paynes-gray mb-2">Nenhuma meta diária encontrada</h5>
                    <p class="text-paynes-gray mb-6">Crie uma nova meta para visualizar as metas diárias.</p>
                    <a href="new-goal.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Criar Nova Meta
                    </a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-paynes-gray text-white">
                                <th class="px-4 py-3 text-left font-semibold">Data</th>
                                <th class="px-4 py-3 text-left font-semibold">Semana</th>
                                <th class="px-4 py-3 text-left font-semibold">Frente</th>
                                <th class="px-4 py-3 text-left font-semibold">Cliente</th>
                                <th class="px-4 py-3 text-right font-semibold">Meta Diária</th>
                                <th class="px-4 py-3 text-right font-semibold">Meta Semanal</th>
                                <th class="px-4 py-3 text-right font-semibold">Meta Mensal</th>
                                <th class="px-4 py-3 text-center font-semibold">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($dailyGoals as $goal): ?>
                                <tr class="hover:bg-eggshell">
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-rich-black"><?= date('d/m/Y', strtotime($goal['goal_date'])) ?></div>
                                        <div class="text-sm text-paynes-gray"><?php 
                                            $dayNames = [
                                                'Monday' => 'Segunda-feira',
                                                'Tuesday' => 'Terça-feira', 
                                                'Wednesday' => 'Quarta-feira',
                                                'Thursday' => 'Quinta-feira',
                                                'Friday' => 'Sexta-feira',
                                                'Saturday' => 'Sábado',
                                                'Sunday' => 'Domingo'
                                            ];
                                            $englishDay = date('l', strtotime($goal['goal_date']));
                                            echo $dayNames[$englishDay];
                                        ?></div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            Semana <?= $goal['week_number'] ?>
                                        </span>
                                        <div class="text-sm text-paynes-gray mt-1"><?= date('d/m', strtotime($goal['week_start_date'])) ?></div>
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
                                        <span class="font-semibold text-blue-600"><?= number_format($goal['daily_goal'], 2, ',', '.') ?></span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="font-semibold text-indigo-600"><?= number_format($goal['weekly_goal'], 2, ',', '.') ?></span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="font-semibold text-green-600"><?= number_format($goal['monthly_goal'], 2, ',', '.') ?></span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex items-center justify-center space-x-2">
                                            <a href="edit-goal.php?id=<?= $goal['goal_id'] ?>" 
                                               class="inline-flex items-center px-2 py-1 border border-blue-300 text-blue-600 rounded hover:bg-blue-50 transition-colors" 
                                               title="Editar Meta">
                                                <i class="fas fa-edit text-sm"></i>
                                            </a>
                                            <button type="button" 
                                                    class="inline-flex items-center px-2 py-1 border border-red-300 text-red-600 rounded hover:bg-red-50 transition-colors" 
                                                    onclick="confirmDelete(<?= $goal['goal_id'] ?>)" 
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
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal de confirmação de exclusão -->
<div class="fixed inset-0 bg-black bg-opacity-50 hidden z-50" id="deleteModal">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-rich-black">Confirmar Exclusão</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <p class="text-paynes-gray mb-4">Tem certeza que deseja excluir esta meta?</p>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="text-red-700">
                        <strong>Atenção:</strong> Esta ação também excluirá todas as metas diárias relacionadas e não pode ser desfeita.
                    </p>
                </div>
            </div>
            <div class="flex items-center justify-end space-x-3 p-6 border-t border-gray-200">
                <button type="button" 
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50 transition-colors" 
                        onclick="closeDeleteModal()">
                    Cancelar
                </button>
                <button type="button" 
                        class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition-colors" 
                        id="confirmDeleteBtn">
                    Excluir
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let deleteGoalId = null;

function confirmDelete(goalId) {
    deleteGoalId = goalId;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    deleteGoalId = null;
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (deleteGoalId) {
        window.location.href = `goal-action.php?action=delete&id=${deleteGoalId}&origin=delivery`;
    }
});

// Fechar modal ao clicar fora dele
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});

// Auto-submit do formulário quando mudar os filtros
document.getElementById('month').addEventListener('change', function() {
    this.form.submit();
});

document.getElementById('front_id').addEventListener('change', function() {
    this.form.submit();
});

document.getElementById('client_id').addEventListener('change', function() {
    this.form.submit();
});
</script>

<?php include 'includes/footer.php'; ?>
