<?php
/**
 * Detalhes das Ocorrências por Equipamento
 * Sistema BDO - Controle de Maquinários
 */

require_once 'config/config.php';

// Verificar se foi passado o ID do equipamento
$equipment_id = $_GET['id'] ?? null;
if (!$equipment_id || !is_numeric($equipment_id)) {
    header('Location: reports-equipment.php');
    exit;
}

// Conectar ao banco de dados
$database = Database::getInstance();
$db = $database->getConnection();

// Verificar autenticação
$auth = new Auth($db);
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Instanciar modelos
$equipmentModel = new Equipment($db);
$occurrenceModel = new Occurrence($db);

// Buscar dados do equipamento
$equipment = $equipmentModel->findById($equipment_id);
if (!$equipment) {
    header('Location: reports-equipment.php');
    exit;
}

// Buscar dados do equipamento com frente e fase
$equipmentWithFront = $equipmentModel->findWithFronts(['e.id' => $equipment_id]);
$equipmentData = $equipmentWithFront[0] ?? $equipment;

// Filtros
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // Primeiro dia do mês atual
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Hoje
$occurrence_type = $_GET['occurrence_type'] ?? '';

// Buscar ocorrências do equipamento
$occurrences = $occurrenceModel->findByEquipment($equipment_id, $date_from, $date_to, $occurrence_type);

// Calcular estatísticas
$stats = [
    'total_occurrences' => count($occurrences),
    'total_hours' => 0,
    'operation_hours' => 0,
    'maintenance_hours' => 0,
    'downtime_hours' => 0,
    'by_type' => []
];

foreach ($occurrences as $occurrence) {
    $duration = $occurrence['duration_minutes'] / 60; // Converter para horas
    $stats['total_hours'] += $duration;
    
    $category = $occurrence['category'] ?? 'OPERACAO';
    switch ($category) {
        case 'OPERACAO':
            $stats['operation_hours'] += $duration;
            break;
        case 'MANUTENCAO':
            $stats['maintenance_hours'] += $duration;
            break;
        case 'PARADA':
            $stats['downtime_hours'] += $duration;
            break;
    }
    
    $type = $occurrence['occurrence_type_description'] ?? 'N/A';
    if (!isset($stats['by_type'][$type])) {
        $stats['by_type'][$type] = ['count' => 0, 'hours' => 0];
    }
    $stats['by_type'][$type]['count']++;
    $stats['by_type'][$type]['hours'] += $duration;
}

// Buscar tipos de ocorrência para filtro
$occurrenceTypeModel = new OccurrenceType($db);
$occurrenceTypes = $occurrenceTypeModel->findActive();

// Resolver frente ativa no intervalo filtrado a partir das alocações
$frontAlloc = $equipmentModel->getFrontByRange($equipment_id, $date_from, $date_to);
if (is_array($frontAlloc)) {
    $equipmentData['front_name'] = $frontAlloc['current_front_name'] ?? ($equipmentData['front_name'] ?? null);
}

$pageTitle = 'Detalhes do Equipamento - ' . ($equipmentData['tag'] ?? 'N/A');
include 'includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-2">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Cabeçalho -->
        <div class="mb-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">
                        Detalhes do Equipamento
                    </h1>
                    <p class="mt-1 text-gray-600">
                        Análise detalhada das ocorrências e desempenho
                    </p>
                </div>
                <button onclick="history.back()" class="bg-silver-lake-blue text-white px-4 py-2 rounded-lg hover:bg-paynes-gray transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Voltar
                </button>
            </div>
        </div>

        <!-- Informações do Equipamento -->
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden mb-6">
            <!-- Header do Card -->
            <div class="bg-gradient-to-r from-silver-lake-blue to-paynes-gray px-6 py-3">
                <h2 class="text-lg font-bold text-white flex items-center">
                    <i class="fas fa-cog mr-3 text-xl"></i>
                    Informações do Equipamento
                </h2>
            </div>
            
            <!-- Conteúdo do Card -->
            <div class="p-4">
                <!-- Descrição em destaque - PRIMEIRO -->
                <div class="mb-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-4 border border-blue-200 shadow-sm">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 mr-4">
                            <div class="w-14 h-14 bg-blue-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-info-circle text-white text-xl"></i>
                            </div>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-xs font-semibold text-blue-800 uppercase tracking-wider mb-1">Descrição</h3>
                            <p class="text-lg font-bold text-blue-900"><?php echo htmlspecialchars($equipmentData['description'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Tag -->
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4 border-l-4 border-silver-lake-blue shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mr-4">
                                <div class="w-12 h-12 bg-silver-lake-blue rounded-lg flex items-center justify-center">
                                    <i class="fas fa-tag text-white text-xl"></i>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1">Tag</h3>
                                <p class="text-2xl font-bold text-gray-900 truncate"><?php echo htmlspecialchars($equipmentData['tag'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Modelo -->
                    <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-4 border-l-4 border-green-500 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mr-4">
                                <div class="w-12 h-12 bg-green-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-industry text-white text-xl"></i>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1">Modelo</h3>
                                <p class="text-lg font-bold text-gray-900 truncate"><?php echo htmlspecialchars($equipmentData['model'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Frente -->
                    <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-xl p-4 border-l-4 border-yellow-500 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mr-4">
                                <div class="w-12 h-12 bg-yellow-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-map-marker-alt text-white text-xl"></i>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1">Frente</h3>
                                <p class="text-sm font-bold text-gray-900 leading-tight"><?php echo htmlspecialchars($equipmentData['front_name'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Fase -->
                    <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-4 border-l-4 border-purple-500 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mr-4">
                                <div class="w-12 h-12 bg-purple-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-tasks text-white text-xl"></i>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1">Fase</h3>
                                <span class="inline-block px-3 py-1 text-sm font-bold rounded-full bg-purple-200 text-purple-800 border border-purple-300">
                                    <?php echo htmlspecialchars($equipmentData['fase_name'] ?? 'N/A'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6 mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="hidden" name="id" value="<?php echo $equipment_id; ?>">
                
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-2">Data Inicial</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo $date_from; ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-silver-lake-blue">
                </div>
                
                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700 mb-2">Data Final</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo $date_to; ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-silver-lake-blue">
                </div>
                
                <div>
                    <label for="occurrence_type" class="block text-sm font-medium text-gray-700 mb-2">Tipo de Ocorrência</label>
                    <select id="occurrence_type" name="occurrence_type" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-silver-lake-blue">
                        <option value="">Todos os tipos</option>
                        <?php foreach ($occurrenceTypes as $type): ?>
                            <option value="<?php echo $type['id']; ?>" <?php echo $occurrence_type == $type['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['description']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-silver-lake-blue text-white px-4 py-2 rounded-md hover:bg-paynes-gray transition-colors">
                        <i class="fas fa-filter mr-2"></i>
                        Filtrar
                    </button>
                </div>
            </form>
        </div>

        <!-- Estatísticas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-list-alt text-2xl text-silver-lake-blue"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total de Ocorrências</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_occurrences']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-clock text-2xl text-green-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Horas de Operação</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['operation_hours'], 1); ?>h</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-tools text-2xl text-yellow-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Horas de Manutenção</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['maintenance_hours'], 1); ?>h</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-pause-circle text-2xl text-red-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Horas de Parada</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['downtime_hours'], 1); ?>h</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabela de Ocorrências -->
        <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Histórico de Ocorrências</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Período: <?php echo date('d/m/Y', strtotime($date_from)); ?> a <?php echo date('d/m/Y', strtotime($date_to)); ?>
                </p>
            </div>
            
            <?php if (!empty($occurrences)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data/Hora</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Operador</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duração</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Horímetro</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Observações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($occurrences as $occurrence): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo date('d/m/Y H:i', strtotime($occurrence['start_datetime'])); ?>
                                <?php if ($occurrence['end_datetime']): ?>
                                    <br><small class="text-gray-500">até <?php echo date('d/m/Y H:i', strtotime($occurrence['end_datetime'])); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" 
                                      style="background-color: <?php echo $occurrence['color'] ?? '#3e5c76'; ?>20; color: <?php echo $occurrence['color'] ?? '#3e5c76'; ?>">
                                    <?php echo htmlspecialchars($occurrence['occurrence_type_description'] ?? 'N/A'); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($occurrence['operator_name'] ?? 'N/A'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo number_format(($occurrence['duration_minutes'] ?? 0) / 60, 1); ?>h
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php if ($occurrence['start_hours']): ?>
                                    <?php echo number_format($occurrence['start_hours'], 1); ?>h
                                    <?php if ($occurrence['end_hours']): ?>
                                        → <?php echo number_format($occurrence['end_hours'], 1); ?>h
                                    <?php endif; ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $occurrence['end_datetime'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo $occurrence['end_datetime'] ? 'Finalizada' : 'Em Andamento'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900 max-w-xs truncate">
                                <?php echo htmlspecialchars($occurrence['observations'] ?? ''); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="p-8 text-center">
                <i class="fas fa-clipboard-list text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhuma ocorrência encontrada</h3>
                <p class="text-gray-500">Não há ocorrências registradas para este equipamento no período selecionado.</p>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>
