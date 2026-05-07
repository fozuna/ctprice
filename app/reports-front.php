<?php
require_once 'config/config.php';
require_once 'classes/Auth.php';
require_once 'models/Front.php';
require_once 'models/Equipment.php';
require_once 'models/Occurrence.php';

// Verificar autenticação
$database = Database::getInstance();
$db = $database->getConnection();
$auth = new Auth($db);
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Instanciar modelos
$frontModel = new Front($db);
$equipmentModel = new Equipment($db);
$occurrenceModel = new Occurrence($db);

// Filtros
$front_filter = $_GET['front_id'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // Primeiro dia do mês atual
$date_to = $_GET['date_to'] ?? date('Y-m-t'); // Último dia do mês atual
$report_type = $_GET['report_type'] ?? 'productivity';

// Buscar dados para filtros
$fronts = $frontModel->findAll();

// Preparar condições para relatórios
$conditions = [];
$dateRange = [];

if (!empty($front_filter)) {
    $conditions['front_id'] = $front_filter;
}

if (!empty($date_from)) {
    $dateRange['from'] = $date_from;
}

if (!empty($date_to)) {
    $dateRange['to'] = $date_to;
}

// Buscar dados do relatório
$reportData = [];
$chartData = [];

// Buscar frentes baseado nos filtros
$frontConditions = ['active' => 1];
if (!empty($front_filter)) {
    $frontConditions['id'] = $front_filter;
}

$fronts = $frontModel->findAll($frontConditions);

// Gerar dados simulados para demonstração (em produção, estes dados viriam de consultas reais)
foreach ($fronts as $front) {
    $frontData = [
        'front_name' => $front['name'],
        'name' => $front['name'],
        'location' => $front['location'] ?? 'N/A'
    ];
    
    switch ($report_type) {
        case 'productivity':
            $frontData['productivity'] = rand(70, 100);
            $frontData['target'] = 85;
            $frontData['variation'] = $frontData['productivity'] - $frontData['target'];
            $frontData['equipment_count'] = rand(5, 20);
            break;
        
        case 'equipment':
            $frontData['equipment_count'] = rand(5, 20);
            $frontData['active_equipment'] = rand(3, $frontData['equipment_count']);
            $frontData['maintenance_equipment'] = $frontData['equipment_count'] - $frontData['active_equipment'];
            $frontData['utilization'] = ($frontData['active_equipment'] / $frontData['equipment_count']) * 100;
            break;
        
        case 'efficiency':
            $frontData['efficiency'] = rand(75, 95);
            $frontData['planned_hours'] = rand(160, 200);
            $frontData['worked_hours'] = rand(140, $frontData['planned_hours']);
            $frontData['downtime'] = $frontData['planned_hours'] - $frontData['worked_hours'];
            break;
        
        case 'comparison':
            $frontData['value'] = rand(50, 100);
            $frontData['production'] = rand(100, 500);
            $frontData['cost'] = rand(10000, 50000);
            $frontData['roi'] = ($frontData['production'] / $frontData['cost']) * 100;
            break;
    }
    
    $reportData[] = $frontData;
}

// Preparar dados para gráficos
if (!empty($reportData)) {
    foreach ($reportData as $item) {
        $chartData['labels'][] = $item['front_name'] ?? $item['name'] ?? 'N/A';
        $chartData['data'][] = $item['productivity'] ?? $item['equipment_count'] ?? $item['efficiency'] ?? $item['value'] ?? 0;
    }
}

$pageTitle = 'Relatórios de Frentes de Trabalho';
include 'includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Cabeçalho -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-paynes-gray">Relatórios de Frentes de Trabalho</h1>
            <p class="mt-2 text-gray-600">Análise de produtividade e eficiência das frentes de trabalho</p>
        </div>

        <!-- Filtros -->
        <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6 mb-8">
            <form method="GET" class="space-y-4">
                <!-- Primeira linha: Tipo de relatório e Frente -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-1">Tipo de Relatório</label>
                        <select name="report_type" class="w-full px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                            <option value="productivity" <?php echo $report_type === 'productivity' ? 'selected' : ''; ?>>Produtividade</option>
                            <option value="equipment" <?php echo $report_type === 'equipment' ? 'selected' : ''; ?>>Equipamentos Alocados</option>
                            <option value="efficiency" <?php echo $report_type === 'efficiency' ? 'selected' : ''; ?>>Eficiência</option>
                            <option value="comparison" <?php echo $report_type === 'comparison' ? 'selected' : ''; ?>>Comparativo</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-1">Frente de Trabalho</label>
                        <select name="front_id" class="w-full px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                            <option value="">Todas as frentes</option>
                            <?php foreach ($fronts as $front): ?>
                                <option value="<?php echo $front['id']; ?>" <?php echo $front_filter == $front['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($front['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-1">Período</label>
                        <div class="flex space-x-2">
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" 
                                   class="flex-1 px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray text-sm">
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" 
                                   class="flex-1 px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray text-sm">
                        </div>
                    </div>
                </div>
                
                <!-- Botões de ação -->
                <div class="flex justify-between items-center">
                    <button type="submit" class="px-6 py-2 bg-paynes-gray text-white rounded-lg hover:bg-prussian-blue transition-colors">
                        <i class="fas fa-chart-line mr-2"></i>Gerar Relatório
                    </button>
                    
                    <div class="space-x-2">
                        <button type="button" onclick="exportToPDF()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                            <i class="fas fa-file-pdf mr-2"></i>PDF
                        </button>
                        <button type="button" onclick="exportToExcel()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-file-excel mr-2"></i>Excel
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <?php if (!empty($reportData)): ?>
        <!-- Cards de Resumo -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-industry text-2xl text-blue-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total de Frentes</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo count($reportData); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-chart-line text-2xl text-green-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Produtividade Média</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?php 
                            $avgProductivity = array_sum(array_column($reportData, 'productivity')) / count($reportData);
                            echo number_format($avgProductivity, 1); 
                            ?>%
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-tools text-2xl text-yellow-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Equipamentos Ativos</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?php 
                            $totalEquipments = array_sum(array_column($reportData, 'equipment_count'));
                            echo $totalEquipments; 
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-clock text-2xl text-purple-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Horas Trabalhadas</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?php 
                            $totalHours = array_sum(array_column($reportData, 'total_hours'));
                            echo number_format($totalHours, 0); 
                            ?>h
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráfico -->
        <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6 mb-8">
            <h2 class="text-xl font-semibold text-paynes-gray mb-4">
                <?php 
                switch ($report_type) {
                    case 'productivity': echo 'Produtividade por Frente'; break;
                    case 'equipment': echo 'Equipamentos por Frente'; break;
                    case 'efficiency': echo 'Eficiência por Frente'; break;
                    case 'comparison': echo 'Comparativo entre Frentes'; break;
                }
                ?>
            </h2>
            <div class="h-96">
                <canvas id="reportChart"></canvas>
            </div>
        </div>

        <!-- Tabela de Dados -->
        <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-paynes-gray">Dados Detalhados</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <?php if ($report_type === 'productivity'): ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Frente</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produtividade</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Meta</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Variação</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <?php elseif ($report_type === 'equipment'): ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Frente</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Equipamentos</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Horas Trabalhadas</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Utilização</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Eficiência</th>
                            <?php elseif ($report_type === 'efficiency'): ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Frente</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Eficiência</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tempo Produtivo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tempo Parado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Classificação</th>
                            <?php elseif ($report_type === 'comparison'): ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Frente</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produção</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Custo/Hora</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ROI</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ranking</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($reportData as $index => $row): ?>
                        <tr class="hover:bg-gray-50">
                            <?php if ($report_type === 'productivity'): ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($row['front_name'] ?? $row['name'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($row['productivity'] ?? 0, 1); ?>%
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo number_format($row['target'] ?? 85, 1); ?>%
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php 
                                    $variation = ($row['productivity'] ?? 0) - ($row['target'] ?? 85);
                                    $colorClass = $variation >= 0 ? 'text-green-600' : 'text-red-600';
                                    ?>
                                    <span class="<?php echo $colorClass; ?>">
                                        <?php echo ($variation >= 0 ? '+' : '') . number_format($variation, 1); ?>%
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php 
                                    $productivity = $row['productivity'] ?? 0;
                                    if ($productivity >= 90) {
                                        $statusClass = 'bg-green-100 text-green-800';
                                        $statusText = 'Excelente';
                                    } elseif ($productivity >= 75) {
                                        $statusClass = 'bg-yellow-100 text-yellow-800';
                                        $statusText = 'Bom';
                                    } else {
                                        $statusClass = 'bg-red-100 text-red-800';
                                        $statusText = 'Baixo';
                                    }
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                </td>
                            <?php elseif ($report_type === 'equipment'): ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($row['front_name'] ?? $row['name'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $row['equipment_count'] ?? 0; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($row['total_hours'] ?? 0, 1); ?>h
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($row['utilization'] ?? 0, 1); ?>%
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($row['efficiency'] ?? 0, 1); ?>%
                                </td>
                            <?php elseif ($report_type === 'efficiency'): ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($row['front_name'] ?? $row['name'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($row['efficiency'] ?? 0, 1); ?>%
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($row['productive_time'] ?? 0, 1); ?>h
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($row['downtime'] ?? 0, 1); ?>h
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php 
                                    $efficiency = $row['efficiency'] ?? 0;
                                    if ($efficiency >= 85) {
                                        $rankClass = 'bg-green-100 text-green-800';
                                        $rankText = 'A';
                                    } elseif ($efficiency >= 70) {
                                        $rankClass = 'bg-yellow-100 text-yellow-800';
                                        $rankText = 'B';
                                    } else {
                                        $rankClass = 'bg-red-100 text-red-800';
                                        $rankText = 'C';
                                    }
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $rankClass; ?>">
                                        <?php echo $rankText; ?>
                                    </span>
                                </td>
                            <?php elseif ($report_type === 'comparison'): ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($row['front_name'] ?? $row['name'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($row['production'] ?? 0, 0); ?> un
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    R$ <?php echo number_format($row['cost_per_hour'] ?? 0, 2, ',', '.'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($row['roi'] ?? 0, 1); ?>%
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-semibold">
                                    #<?php echo $index + 1; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <!-- Mensagem quando não há dados -->
        <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-8 text-center">
            <i class="fas fa-chart-line text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhum dado encontrado</h3>
            <p class="text-gray-500">Ajuste os filtros e tente novamente para visualizar os relatórios.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Configuração do gráfico
<?php if (!empty($chartData)): ?>
const ctx = document.getElementById('reportChart').getContext('2d');
const reportChart = new Chart(ctx, {
    type: '<?php echo $report_type === "comparison" ? "radar" : "bar"; ?>',
    data: {
        labels: <?php echo json_encode($chartData['labels'] ?? []); ?>,
        datasets: [{
            label: '<?php 
                switch ($report_type) {
                    case 'productivity': echo 'Produtividade (%)'; break;
                    case 'equipment': echo 'Equipamentos'; break;
                    case 'efficiency': echo 'Eficiência (%)'; break;
                    case 'comparison': echo 'Performance'; break;
                }
            ?>',
            data: <?php echo json_encode($chartData['data'] ?? []); ?>,
            backgroundColor: '<?php echo $report_type === "comparison" ? "rgba(54, 162, 235, 0.2)" : "rgba(54, 162, 235, 0.6)"; ?>',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: <?php echo $report_type === "comparison" ? "2" : "1"; ?>
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false<?php if ($report_type !== "comparison"): ?>,
        scales: {
            y: {
                beginAtZero: true
            }
        }<?php endif; ?>,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            }
        }
    }
});
<?php endif; ?>

// Funções de exportação
function exportToPDF() {
    window.print();
}

function exportToExcel() {
    // Implementar exportação para Excel
    alert('Funcionalidade de exportação para Excel será implementada em breve.');
}
</script>

<?php include 'includes/footer.php'; ?>