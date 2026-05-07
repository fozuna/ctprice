<?php
/**
 * Detalhes da Frente de Serviço
 * Sistema BDO - Controle de Maquinários
 */

require_once 'config/config.php';

// Verificar se foi passado o ID da frente
$front_id = $_GET['id'] ?? null;
if (!$front_id || !is_numeric($front_id)) {
    header('Location: fronts.php');
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
$frontModel = new Front($db);
$equipmentModel = new Equipment($db);
$goalModel = new Goal($db);
$occurrenceModel = new Occurrence($db);

// Buscar dados da frente
$front = $frontModel->findById($front_id);
if (!$front) {
    header('Location: fronts.php');
    exit;
}

// Filtros
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // Primeiro dia do mês atual
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Hoje
$month_filter = $_GET['month'] ?? date('Y-m'); // Mês atual para metas

// Buscar equipamentos da frente
$equipments = $equipmentModel->findWithFronts(['f.id' => $front_id]);

// Buscar metas da frente para o período
$year = date('Y', strtotime($month_filter));
$month = date('m', strtotime($month_filter));
$goals = $goalModel->findAllWithDetails(['front_id' => $front_id]);

// Filtrar metas por mês se especificado
$monthlyGoals = [];
foreach ($goals as $goal) {
    $goalStart = new DateTime($goal['start_date']);
    $goalEnd = new DateTime($goal['end_date']);
    $filterDate = new DateTime($month_filter . '-01');
    
    // Verificar se a meta está ativa no mês filtrado
    if ($goalStart <= $filterDate && $goalEnd >= $filterDate) {
        $monthlyGoals[] = $goal;
    }
}

// Buscar metas diárias para o mês
$monthStart = $month_filter . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));
$dailyGoals = $goalModel->getDailyGoals($monthStart, $monthEnd, $front_id);

// Calcular estatísticas dos equipamentos
$equipmentStats = [
    'total_equipments' => count($equipments),
    'active_equipments' => 0,
    'total_hours' => 0,
    'operation_hours' => 0,
    'maintenance_hours' => 0,
    'downtime_hours' => 0
];

foreach ($equipments as $equipment) {
    if ($equipment['active']) {
        $equipmentStats['active_equipments']++;
    }
    
    // Buscar ocorrências do equipamento no período
    $occurrences = $occurrenceModel->findByEquipment($equipment['id'], $date_from, $date_to);
    
    foreach ($occurrences as $occurrence) {
        $duration = $occurrence['duration_minutes'] / 60;
        $equipmentStats['total_hours'] += $duration;
        
        $category = $occurrence['category'] ?? 'OPERACAO';
        switch ($category) {
            case 'OPERACAO':
                $equipmentStats['operation_hours'] += $duration;
                break;
            case 'MANUTENCAO':
                $equipmentStats['maintenance_hours'] += $duration;
                break;
            case 'PARADA':
                $equipmentStats['downtime_hours'] += $duration;
                break;
        }
    }
}

// Calcular estatísticas das metas
$goalStats = [
    'total_goals' => count($monthlyGoals),
    'total_goal_value' => 0,
    'daily_goals_count' => count($dailyGoals),
    'daily_goal_value' => 0
];

foreach ($monthlyGoals as $goal) {
    $goalStats['total_goal_value'] += $goal['total_goal'];
}

foreach ($dailyGoals as $dailyGoal) {
    $goalStats['daily_goal_value'] += $dailyGoal['daily_goal'];
}

$pageTitle = 'Detalhes da Frente - ' . ($front['name'] ?? 'N/A');
include 'includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Cabeçalho -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">
                        Detalhes da Frente de Serviço
                    </h1>
                    <p class="mt-2 text-gray-600">
                        Análise detalhada dos equipamentos e metas
                    </p>
                </div>
                <a href="fronts.php" class="bg-silver-lake-blue text-white px-4 py-2 rounded-lg hover:bg-paynes-gray transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Voltar às Frentes
                </a>
            </div>
        </div>

        <!-- Informações da Frente -->
        <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6 mb-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div>
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Código</h3>
                    <p class="mt-1 text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($front['code'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Nome</h3>
                    <p class="mt-1 text-lg text-gray-900"><?php echo htmlspecialchars($front['name'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Descrição</h3>
                    <p class="mt-1 text-lg text-gray-900"><?php echo htmlspecialchars($front['description'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Status</h3>
                    <span class="mt-1 inline-flex px-2 py-1 text-xs leading-5 font-semibold rounded-full <?php echo $front['active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo $front['active'] ? 'Ativo' : 'Inativo'; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6 mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="hidden" name="id" value="<?php echo $front_id; ?>">
                
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-2">Data Inicial (Equipamentos)</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo $date_from; ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-silver-lake-blue">
                </div>
                
                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700 mb-2">Data Final (Equipamentos)</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo $date_to; ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-silver-lake-blue">
                </div>
                
                <div>
                    <label for="month" class="block text-sm font-medium text-gray-700 mb-2">Mês (Metas)</label>
                    <input type="month" id="month" name="month" value="<?php echo $month_filter; ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-silver-lake-blue">
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-silver-lake-blue text-white px-4 py-2 rounded-md hover:bg-paynes-gray transition-colors">
                        <i class="fas fa-filter mr-2"></i>
                        Filtrar
                    </button>
                </div>
            </form>
        </div>

        <!-- Estatísticas Gerais -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-cogs text-2xl text-silver-lake-blue"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total de Equipamentos</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $equipmentStats['total_equipments']; ?></p>
                        <p class="text-xs text-gray-500"><?php echo $equipmentStats['active_equipments']; ?> ativos</p>
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
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($equipmentStats['operation_hours'], 1); ?>h</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-target text-2xl text-blue-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Metas do Mês</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $goalStats['total_goals']; ?></p>
                        <p class="text-xs text-gray-500"><?php echo number_format($goalStats['total_goal_value'], 0, ',', '.'); ?> ton</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-wrench text-2xl text-orange-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Horas de Manutenção</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($equipmentStats['maintenance_hours'], 1); ?>h</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Equipamentos da Frente -->
        <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-cogs mr-2 text-silver-lake-blue"></i>
                    Equipamentos da Frente
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tag</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Modelo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($equipments)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                    Nenhum equipamento encontrado nesta frente
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($equipments as $equipment): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($equipment['tag']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            <?php echo htmlspecialchars($equipment['description'] ?? 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo htmlspecialchars($equipment['model'] ?? 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $equipment['active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo $equipment['active'] ? 'Ativo' : 'Inativo'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="equipment-details.php?id=<?php echo $equipment['id']; ?>" 
                                           class="text-silver-lake-blue hover:text-paynes-gray">
                                            <i class="fas fa-eye mr-1"></i>
                                            Ver Detalhes
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Metas da Frente -->
        <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-target mr-2 text-silver-lake-blue"></i>
                    Metas para <?php echo date('m/Y', strtotime($month_filter)); ?>
                </h3>
            </div>
            <div class="p-6">
                <?php if (empty($monthlyGoals)): ?>
                    <div class="text-center py-8">
                        <div class="p-4 rounded-full bg-gray-100 text-gray-400 w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                            <i class="fas fa-target text-2xl"></i>
                        </div>
                        <p class="text-gray-500">Nenhuma meta encontrada para este período</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Período</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Meta Total</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unidade</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($monthlyGoals as $goal): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($goal['client_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($goal['client_code']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo date('d/m/Y', strtotime($goal['start_date'])); ?> - 
                                                <?php echo date('d/m/Y', strtotime($goal['end_date'])); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo number_format($goal['total_goal'], 0, ',', '.'); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo htmlspecialchars($goal['unit'] ?? 'toneladas'); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900">
                                                <?php echo htmlspecialchars($goal['description'] ?? 'N/A'); ?>
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
        <?php if (!empty($dailyGoals)): ?>
        <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-calendar-day mr-2 text-silver-lake-blue"></i>
                    Metas Diárias - <?php echo date('m/Y', strtotime($month_filter)); ?>
                </h3>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Meta Diária</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Meta Semanal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Meta Mensal</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($dailyGoals as $dailyGoal): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo date('d/m/Y', strtotime($dailyGoal['goal_date'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo htmlspecialchars($dailyGoal['client_name']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo number_format($dailyGoal['daily_goal'], 0, ',', '.'); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo number_format($dailyGoal['weekly_goal'], 0, ',', '.'); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo number_format($dailyGoal['monthly_goal'], 0, ',', '.'); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include 'includes/footer.php'; ?>