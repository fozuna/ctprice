<?php
require_once 'config/config.php';
require_once 'classes/Auth.php';
require_once 'models/User.php';
require_once 'models/Occurrence.php';
require_once 'models/Equipment.php';
require_once 'models/Front.php';

// Verificar autenticação
$database = Database::getInstance();
$db = $database->getConnection();
$auth = new Auth($db);
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Instanciar modelos
$userModel = new User($db);
$occurrenceModel = new Occurrence($db);
$equipmentModel = new Equipment($db);
$frontModel = new Front($db);

// Filtros
$operator_filter = $_GET['operator_id'] ?? '';
$front_filter = $_GET['front_id'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // Primeiro dia do mês atual
$date_to = $_GET['date_to'] ?? date('Y-m-t'); // Último dia do mês atual
$report_type = $_GET['report_type'] ?? 'performance';

// Buscar dados para filtros
$operators = $userModel->findAll();
$fronts = $frontModel->findAll();

// Preparar condições para relatórios
$conditions = [];
$dateRange = [];

if (!empty($operator_filter)) {
    $conditions['operator_id'] = $operator_filter;
}

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

// Buscar operadores baseado nos filtros
$operatorConditions = ['active' => 1];
if (!empty($operator_filter)) {
    $operatorConditions['id'] = $operator_filter;
}
if (!empty($front_filter)) {
    $operatorConditions['front_id'] = $front_filter;
}

$operators = $userModel->findAllWithDetails($operatorConditions);

// Gerar dados simulados para demonstração (em produção, estes dados viriam de consultas reais)
foreach ($operators as $operator) {
    $operatorData = [
        'operator_name' => $operator['name'],
        'name' => $operator['name'],
        'front_name' => $operator['front_name'] ?? 'N/A'
    ];
    
    switch ($report_type) {
        case 'performance':
            $operatorData['performance_score'] = rand(60, 100) / 10; // Score de 6.0 a 10.0
            $operatorData['total_hours'] = rand(160, 200);
            $operatorData['occurrence_count'] = rand(0, 15);
            break;
        
        case 'hours':
            $operatorData['total_hours'] = rand(160, 200);
            $operatorData['overtime_hours'] = rand(0, 20);
            $operatorData['efficiency'] = rand(75, 95);
            $operatorData['status'] = 'active';
            break;
        
        case 'occurrences':
            $operatorData['occurrence_count'] = rand(0, 15);
            $operatorData['open_occurrences'] = rand(0, 5);
            $operatorData['closed_occurrences'] = $operatorData['occurrence_count'] - $operatorData['open_occurrences'];
            break;
        
        case 'productivity':
            $operatorData['productivity'] = rand(70, 100);
            $operatorData['target'] = 85;
            break;
    }
    
    $reportData[] = $operatorData;
}

// Preparar dados para gráficos
if (!empty($reportData)) {
    foreach ($reportData as $item) {
        $chartData['labels'][] = $item['operator_name'] ?? $item['name'] ?? 'N/A';
        $chartData['data'][] = $item['performance_score'] ?? $item['total_hours'] ?? $item['occurrence_count'] ?? $item['productivity'] ?? 0;
    }
}

$pageTitle = 'Relatórios de Operadores';
include 'includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Cabeçalho -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-paynes-gray">Relatórios de Operadores</h1>
            <p class="mt-2 text-gray-600">Análise de performance e produtividade dos operadores</p>
        </div>

        <!-- Filtros -->
        <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6 mb-8">
            <form method="GET" class="space-y-4">
                <!-- Primeira linha: Tipo de relatório, Operador e Frente -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-1">Tipo de Relatório</label>
                        <select name="report_type" class="w-full px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                            <option value="performance" <?php echo $report_type === 'performance' ? 'selected' : ''; ?>>Performance</option>
                            <option value="hours" <?php echo $report_type === 'hours' ? 'selected' : ''; ?>>Horas Trabalhadas</option>
                            <option value="occurrences" <?php echo $report_type === 'occurrences' ? 'selected' : ''; ?>>Ocorrências</option>
                            <option value="productivity" <?php echo $report_type === 'productivity' ? 'selected' : ''; ?>>Produtividade</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-1">Operador</label>
                        <select name="operator_id" class="w-full px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                            <option value="">Todos os operadores</option>
                            <?php foreach ($operators as $operator): ?>
                                <option value="<?php echo $operator['id']; ?>" <?php echo $operator_filter == $operator['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($operator['name']); ?>
                                </option>
                            <?php endforeach; ?>
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
                        <i class="fas fa-user-chart mr-2"></i>Gerar Relatório
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
                        <i class="fas fa-users text-2xl text-blue-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total de Operadores</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo count($reportData); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-star text-2xl text-yellow-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Performance Média</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?php 
                            $avgPerformance = array_sum(array_column($reportData, 'performance_score')) / count($reportData);
                            echo number_format($avgPerformance, 1); 
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-clock text-2xl text-green-600"></i>
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
            
            <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-2xl text-red-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Ocorrências</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?php 
                            $totalOccurrences = array_sum(array_column($reportData, 'occurrence_count'));
                            echo $totalOccurrences; 
                            ?>
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
                    case 'performance': echo 'Performance por Operador'; break;
                    case 'hours': echo 'Horas Trabalhadas por Operador'; break;
                    case 'occurrences': echo 'Ocorrências por Operador'; break;
                    case 'productivity': echo 'Produtividade por Operador'; break;
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
                            <?php if ($report_type === 'performance'): ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Operador</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Horas</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ocorrências</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Classificação</th>
                            <?php elseif ($report_type === 'hours'): ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Operador</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Horas Trabalhadas</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Horas Extras</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Eficiência</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <?php elseif ($report_type === 'occurrences'): ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Operador</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Abertas</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fechadas</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Taxa Resolução</th>
                            <?php elseif ($report_type === 'productivity'): ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Operador</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produtividade</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Meta</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Variação</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ranking</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($reportData as $index => $row): ?>
                        <tr class="hover:bg-gray-50">
                            <?php if ($report_type === 'performance'): ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($row['operator_name'] ?? $row['name'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($row['performance_score'] ?? 0, 1); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($row['total_hours'] ?? 0, 1); ?>h
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $row['occurrence_count'] ?? 0; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php 
                                    $score = $row['performance_score'] ?? 0;
                                    if ($score >= 8.5) {
                                        $classClass = 'bg-green-100 text-green-800';
                                        $classText = 'Excelente';
                                    } elseif ($score >= 7.0) {
                                        $classClass = 'bg-yellow-100 text-yellow-800';
                                        $classText = 'Bom';
                                    } else {
                                        $classClass = 'bg-red-100 text-red-800';
                                        $classText = 'Regular';
                                    }
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $classClass; ?>">
                                        <?php echo $classText; ?>
                                    </span>
                                </td>
                            <?php elseif ($report_type === 'hours'): ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($row['operator_name'] ?? $row['name'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($row['total_hours'] ?? 0, 1); ?>h
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($row['overtime_hours'] ?? 0, 1); ?>h
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($row['efficiency'] ?? 0, 1); ?>%
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo ($row['status'] ?? 'active') === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo ($row['status'] ?? 'active') === 'active' ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </td>
                            <?php elseif ($report_type === 'occurrences'): ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($row['operator_name'] ?? $row['name'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $row['occurrence_count'] ?? 0; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $row['open_occurrences'] ?? 0; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $row['closed_occurrences'] ?? 0; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php 
                                    $total = $row['occurrence_count'] ?? 0;
                                    $closed = $row['closed_occurrences'] ?? 0;
                                    $rate = $total > 0 ? ($closed / $total) * 100 : 0;
                                    echo number_format($rate, 1); 
                                    ?>%
                                </td>
                            <?php elseif ($report_type === 'productivity'): ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($row['operator_name'] ?? $row['name'] ?? 'N/A'); ?>
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
            <i class="fas fa-user-chart text-6xl text-gray-300 mb-4"></i>
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
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chartData['labels'] ?? []); ?>,
        datasets: [{
            label: '<?php 
                switch ($report_type) {
                    case 'performance': echo 'Score de Performance'; break;
                    case 'hours': echo 'Horas Trabalhadas'; break;
                    case 'occurrences': echo 'Ocorrências'; break;
                    case 'productivity': echo 'Produtividade (%)'; break;
                }
            ?>',
            data: <?php echo json_encode($chartData['data'] ?? []); ?>,
            backgroundColor: 'rgba(75, 192, 192, 0.6)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        },
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