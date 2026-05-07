<?php
require_once 'config/config.php';
require_once 'classes/Auth.php';
require_once 'models/Equipment.php';
require_once 'models/Occurrence.php';
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
$equipmentModel = new Equipment($db);
$occurrenceModel = new Occurrence($db);
$frontModel = new Front($db);

// Filtros
$equipment_filter = $_GET['equipment_id'] ?? '';
$front_filter = $_GET['front_id'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // Primeiro dia do mês atual
$date_to = $_GET['date_to'] ?? date('Y-m-t'); // Último dia do mês atual
$report_type = $_GET['report_type'] ?? 'summary';

// Buscar dados para filtros
$equipments = $equipmentModel->findAll();
$fronts = $frontModel->findAll();

// Preparar condições para relatórios
$conditions = [];
$dateRange = [];

if (!empty($equipment_filter)) {
    $conditions['equipment_id'] = $equipment_filter;
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

// Buscar equipamentos baseado nos filtros
$equipmentConditions = ['active' => 1];
if (!empty($equipment_filter)) {
    $equipmentConditions['id'] = $equipment_filter;
}
if (!empty($front_filter)) {
    $equipmentConditions['front_id'] = $front_filter;
}

$equipments = $equipmentModel->findAllWithDetails($equipmentConditions);

// Construir dados de relatório com base em campos reais do equipamento
foreach ($equipments as $equipment) {
    // Ajustar frente exibida com base nas alocações sobrepostas ao período filtrado
    try {
        $frontAlloc = (new Equipment($db))->getFrontByRange($equipment['id'], $date_from, $date_to);
        if (is_array($frontAlloc) && !empty($frontAlloc['current_front_name'])) {
            $equipment['front_name'] = $frontAlloc['current_front_name'];
        }
    } catch (Exception $e) {
        // Se falhar, mantém a frente padrão
    }
    $equipmentData = [
        'id' => $equipment['id'],
        'equipment_id' => $equipment['id'],
        'equipment_name' => $equipment['description'],
        'name' => $equipment['description'],
        'equipment_tag' => $equipment['tag'] ?? 'N/A',
        'tag' => $equipment['tag'] ?? 'N/A',
        'front_name' => $equipment['front_name'] ?? 'N/A',
        'model' => $equipment['model'] ?? 'N/A',
        'current_hours' => $equipment['current_hour_meter'] ?? ($equipment['current_hours'] ?? 0),
        'active' => $equipment['active'] ?? 1
    ];
    switch ($report_type) {
        case 'summary':
            // Exibir as horas trabalhadas a partir do horímetro atual
            $equipmentData['total_hours'] = (float)$equipmentData['current_hours'];
            $equipmentData['hours_month'] = null;
            $equipmentData['efficiency'] = null;
            break;
        
        case 'hours':
            $equipmentData['total_hours'] = (float)$equipmentData['current_hours'];
            $equipmentData['hours_today'] = null;
            $equipmentData['hours_week'] = null;
            $equipmentData['hours_month'] = null;
            break;
        
        case 'occurrences':
            $equipmentData['occurrence_count'] = 0;
            $equipmentData['open_occurrences'] = 0;
            $equipmentData['closed_occurrences'] = 0;
            $equipmentData['last_occurrence'] = null;
            break;
        
        case 'maintenance':
            $equipmentData['maintenance_count'] = 0;
            $equipmentData['last_maintenance'] = null;
            $equipmentData['next_maintenance'] = null;
            $equipmentData['maintenance_cost'] = 0;
            break;
    }
    
    $reportData[] = $equipmentData;
}

// Preparar dados para gráficos
if (!empty($reportData)) {
    foreach ($reportData as $item) {
        $chartData['labels'][] = $item['equipment_tag'] ?? $item['tag'] ?? 'N/A';
        $chartData['data'][] = $item['total_hours'] ?? $item['occurrence_count'] ?? $item['value'] ?? 0;
    }
}

$pageTitle = 'Relatórios de Equipamentos';
include 'includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Cabeçalho -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-paynes-gray">Relatórios de Equipamentos</h1>
            <p class="mt-2 text-gray-600">Análise detalhada do desempenho e utilização dos equipamentos</p>
        </div>

        <!-- Filtros -->
        <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-4 sm:p-6 mb-8">
            <form method="GET" class="space-y-4">
                <!-- Primeira linha: Tipo de Relatório, Equipamento e Frente de Trabalho -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="col-span-1">
                        <label class="block text-sm font-medium text-paynes-gray mb-1">Tipo de Relatório</label>
                        <select name="report_type" class="w-full px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                            <option value="summary" <?php echo $report_type === 'summary' ? 'selected' : ''; ?>>Resumo Geral</option>
                            <option value="hours" <?php echo $report_type === 'hours' ? 'selected' : ''; ?>>Horas Trabalhadas</option>
                            <option value="occurrences" <?php echo $report_type === 'occurrences' ? 'selected' : ''; ?>>Ocorrências</option>
                            <option value="maintenance" <?php echo $report_type === 'maintenance' ? 'selected' : ''; ?>>Manutenções</option>
                        </select>
                    </div>
                    
                    <div class="col-span-1">
                        <label class="block text-sm font-medium text-paynes-gray mb-1">Equipamento</label>
                        <select name="equipment_id" class="w-full px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                            <option value="">Todos os equipamentos</option>
                            <?php foreach ($equipments as $equipment): ?>
                                <option value="<?php echo $equipment['id']; ?>" <?php echo $equipment_filter == $equipment['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($equipment['tag']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-span-1">
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
                </div>
                
                <!-- Segunda linha: Período e Botões -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-end">
                    <div class="col-span-1">
                        <label class="block text-sm font-medium text-paynes-gray mb-1">Período</label>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" 
                                       class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray text-sm"
                                       placeholder="Data inicial">
                            </div>
                            <div>
                                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" 
                                       class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray text-sm"
                                       placeholder="Data final">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Botões de ação na mesma linha -->
                    <div class="col-span-1">
                        <div class="flex flex-col sm:flex-row gap-2 h-full items-end">
                            <button type="submit" class="flex-1 px-4 py-2 bg-paynes-gray text-white rounded-lg hover:bg-prussian-blue transition-colors text-sm">
                                <i class="fas fa-chart-bar mr-1"></i>Gerar Relatório
                            </button>
                            <button type="button" onclick="exportToPDF()" class="px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm">
                                <i class="fas fa-file-pdf mr-1"></i>PDF
                            </button>
                            <button type="button" onclick="exportToExcel()" class="px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm">
                                <i class="fas fa-file-excel mr-1"></i>Excel
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <?php if (!empty($reportData)): ?>
        <!-- Gráfico -->
        <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6 mb-8">
            <h2 class="text-xl font-semibold text-paynes-gray mb-4">
                <?php 
                switch ($report_type) {
                    case 'summary': echo 'Resumo Geral dos Equipamentos'; break;
                    case 'hours': echo 'Horas Trabalhadas por Equipamento'; break;
                    case 'occurrences': echo 'Ocorrências por Equipamento'; break;
                    case 'maintenance': echo 'Manutenções por Equipamento'; break;
                }
                ?>
            </h2>
            <div class="h-96">
                <canvas id="reportChart"></canvas>
            </div>
        </div>

        <!-- Tabela de Dados -->
        <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue overflow-hidden">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg sm:text-xl font-semibold text-paynes-gray">Dados Detalhados</h2>
                    <?php if ($report_type === 'summary'): ?>
                    <button type="button" onclick="resetDisplayedHours()" class="px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm">
                        <i class="fas fa-rotate-left mr-1"></i>Zerar Horas (lista exibida)
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Container com scroll horizontal responsivo -->
            <div class="overflow-x-auto scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-gray-100">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <?php if ($report_type === 'summary'): ?>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[120px]">Equipamento</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[100px]">Frente</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[120px]">Horas Trabalhadas</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[100px]">Ocorrências</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[80px]">Status</th>
                            <?php elseif ($report_type === 'hours'): ?>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[120px]">Equipamento</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[100px]">Data</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[100px]">Horas Iniciais</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[100px]">Horas Finais</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[120px]">Horas Trabalhadas</th>
                            <?php elseif ($report_type === 'occurrences'): ?>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[120px]">Equipamento</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[80px]">Tipo</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[100px]">Quantidade</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[100px]">Tempo Médio</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[80px]">Status</th>
                            <?php elseif ($report_type === 'maintenance'): ?>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[120px]">Equipamento</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[100px]">Data</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[120px]">Tipo Manutenção</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[100px]">Duração</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[80px]">Custo</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($reportData as $row): ?>
                        <tr class="hover:bg-gray-50 eq-row" data-eid="<?php echo (int)($row['id'] ?? 0); ?>">
                            <?php if ($report_type === 'summary'): ?>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="equipment-details.php?id=<?php echo $row['id'] ?? 0; ?>" 
                                       class="text-silver-lake-blue hover:text-paynes-gray hover:underline font-medium transition-colors">
                                        <?php echo htmlspecialchars($row['equipment_tag'] ?? $row['tag'] ?? 'N/A'); ?>
                                    </a>
                                </td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($row['front_name'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900 eq-hours">
                                    <?php echo number_format((float)($row['total_hours'] ?? 0), 1); ?>h
                                </td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $row['occurrence_count'] ?? 0; ?>
                                </td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo ($row['active'] ?? 1) == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo ($row['active'] ?? 1) == 1 ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </td>
                            <?php elseif ($report_type === 'hours'): ?>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="equipment-details.php?id=<?php echo $row['id'] ?? 0; ?>" 
                                       class="text-silver-lake-blue hover:text-paynes-gray hover:underline font-medium transition-colors">
                                        <?php echo htmlspecialchars($row['equipment_tag'] ?? $row['tag'] ?? 'N/A'); ?>
                                    </a>
                                </td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('d/m/Y', strtotime($row['date'] ?? 'now')); ?>
                                </td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($row['initial_hours'] ?? 0, 1); ?>h
                                </td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($row['final_hours'] ?? 0, 1); ?>h
                                </td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-semibold">
                                    <?php echo number_format($row['worked_hours'] ?? 0, 1); ?>h
                                </td>
                            <?php elseif ($report_type === 'occurrences'): ?>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="equipment-details.php?id=<?php echo $row['id'] ?? 0; ?>" 
                                       class="text-silver-lake-blue hover:text-paynes-gray hover:underline font-medium transition-colors">
                                        <?php echo htmlspecialchars($row['equipment_tag'] ?? $row['tag'] ?? 'N/A'); ?>
                                    </a>
                                </td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($row['occurrence_type'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $row['occurrence_count'] ?? 0; ?>
                                </td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($row['avg_duration'] ?? 0, 1); ?>h
                                </td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo ($row['status'] ?? 'open') === 'open' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'; ?>">
                                        <?php echo ($row['status'] ?? 'open') === 'open' ? 'Em Andamento' : 'Finalizada'; ?>
                                    </span>
                                </td>
                            <?php elseif ($report_type === 'maintenance'): ?>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="equipment-details.php?id=<?php echo $row['id'] ?? 0; ?>" 
                                       class="text-silver-lake-blue hover:text-paynes-gray hover:underline font-medium transition-colors">
                                        <?php echo htmlspecialchars($row['equipment_tag'] ?? $row['tag'] ?? 'N/A'); ?>
                                    </a>
                                </td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('d/m/Y', strtotime($row['date'] ?? 'now')); ?>
                                </td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($row['maintenance_type'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($row['duration'] ?? 0, 1); ?>h
                                </td>
                                <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    R$ <?php echo number_format($row['cost'] ?? 0, 2, ',', '.'); ?>
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
            <i class="fas fa-chart-bar text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhum dado encontrado</h3>
            <p class="text-gray-500">Ajuste os filtros e tente novamente para visualizar os relatórios.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Estilos CSS customizados para responsividade -->
<style>
/* Melhorias no scroll horizontal */
.scrollbar-thin {
    scrollbar-width: thin;
}

.scrollbar-thumb-gray-300::-webkit-scrollbar-thumb {
    background-color: #d1d5db;
    border-radius: 6px;
}

.scrollbar-track-gray-100::-webkit-scrollbar-track {
    background-color: #f3f4f6;
}

.scrollbar-thin::-webkit-scrollbar {
    height: 8px;
}

/* Responsividade adicional para tabelas */
@media (max-width: 640px) {
    .min-w-full {
        min-width: 600px; /* Garante largura mínima para scroll horizontal */
    }
    
    /* Ajustes nos cabeçalhos da tabela */
    th {
        font-size: 0.75rem !important;
        padding: 0.5rem !important;
    }
    
    /* Ajustes nas células da tabela */
    td {
        font-size: 0.875rem !important;
        padding: 0.5rem !important;
    }
    
    /* Melhor visualização dos badges de status */
    .rounded-full {
        font-size: 0.75rem !important;
        padding: 0.25rem 0.5rem !important;
    }
}

/* Melhorias no gráfico para mobile */
@media (max-width: 768px) {
    .h-96 {
        height: 16rem !important; /* Reduz altura do gráfico em mobile */
    }
}

/* Indicador visual de scroll horizontal */
.overflow-x-auto::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    width: 20px;
    background: linear-gradient(to left, rgba(255,255,255,0.8), transparent);
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.overflow-x-auto:hover::after {
    opacity: 1;
}

/* Melhor espaçamento em dispositivos pequenos */
@media (max-width: 480px) {
    .grid-cols-1 {
        gap: 0.75rem !important;
    }
    
    .p-4 {
        padding: 0.75rem !important;
    }
}
</style>

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
                    case 'summary': echo 'Horas Trabalhadas'; break;
                    case 'hours': echo 'Horas'; break;
                    case 'occurrences': echo 'Ocorrências'; break;
                    case 'maintenance': echo 'Manutenções'; break;
                }
            ?>',
            data: <?php echo json_encode($chartData['data'] ?? []); ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.6)',
            borderColor: 'rgba(54, 162, 235, 1)',
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

async function resetDisplayedHours() {
    if (!confirm('Tem certeza que deseja zerar as horas dos equipamentos exibidos? Esta ação não pode ser desfeita.')) return;
    const rows = Array.from(document.querySelectorAll('tr.eq-row'));
    const ids = rows.map(r => parseInt(r.dataset.eid || '0', 10)).filter(v => v > 0);
    if (ids.length === 0) {
        alert('Nenhum equipamento na lista atual.');
        return;
    }
    try {
        const resp = await fetch('equipments-api.php?action=reset_hours', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids })
        });
        const text = await resp.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseErr) {
            const snippet = text.replace(/<[^>]*>/g, '').trim().slice(0, 200);
            throw new Error('Resposta inválida do servidor: ' + (snippet || 'não foi possível ler a mensagem'));
        }
        if (!data.ok) throw new Error(data.message || 'Falha ao zerar horas');
        rows.forEach(r => {
            const cell = r.querySelector('.eq-hours');
            if (cell) cell.textContent = '0.0h';
        });
        alert('Horas zeradas com sucesso para ' + data.updated + ' equipamento(s).');
    } catch (e) {
        alert('Erro: ' + e.message);
    }
}
</script>

<?php include 'includes/footer.php'; ?>
