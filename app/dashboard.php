<?php
require_once __DIR__ . '/config/config.php';
if (file_exists(__DIR__ . '/config/autoload.php')) {
    require_once __DIR__ . '/config/autoload.php';
}
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    if (defined('DEBUG_MODE') && DEBUG_MODE && function_exists('error_log')) {
        $sid = session_id();
        $hasUser = isset($_SESSION['user_id']) ? 'yes' : 'no';
        error_log("[dashboard] Sessão inválida: logged_in=" . (isset($_SESSION['logged_in']) ? json_encode($_SESSION['logged_in']) : 'unset') . " user_id_present={$hasUser} sid={$sid}");
    }
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
    $redir = ($base === '' || $base === '/') ? '/login.php' : ($base . '/login.php');
    header('Location: ' . $redir);
    exit();
}

// Sistema de versionamento automático
require_once 'config/version-hooks.php';
// Incrementar versão automaticamente se houver mudanças
checkAndIncrementVersion();

// Verificar autenticação
$database = Database::getInstance();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn()) {
    if (defined('DEBUG_MODE') && DEBUG_MODE && function_exists('error_log')) {
        $sid = session_id();
        $hasUser = isset($_SESSION['user_id']) ? 'yes' : 'no';
        error_log("[dashboard] auth->isLoggedIn()=false user_id_present={$hasUser} sid={$sid}");
    }
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
    $redir = ($base === '' || $base === '/') ? '/login.php' : ($base . '/login.php');
    header('Location: ' . $redir);
    exit();
}

$pageTitle = 'Dashboard';

// Instanciar modelos
$equipmentModel = new Equipment($db);
$occurrenceModel = new Occurrence($db);
$frontModel = new Front($db);
$userModel = new User($db);

// Obter estatísticas gerais
$totalEquipments = $equipmentModel->count(['active' => 1]);
$totalFronts = $frontModel->count(['active' => 1]);
$totalUsers = $userModel->count(['active' => 1]);

// Ocorrências abertas
$openOccurrences = $occurrenceModel->count(['end_datetime' => null]);

// Estatísticas do dia atual
$today = date('Y-m-d');
$todayOccurrences = $occurrenceModel->countByDate($today);

// Equipamentos em operação (com ocorrências abertas)
$equipmentsInOperation = $occurrenceModel->getEquipmentsInOperation();

// Últimas ocorrências
$recentOccurrences = $occurrenceModel->findAllWithDetails([], 'o.start_datetime DESC', 10);

// Estatísticas por categoria (últimos 7 dias)
$weekAgo = date('Y-m-d', strtotime('-7 days'));
$categoryStats = $occurrenceModel->getStatsByCategory($weekAgo, $today);

// Dados para melhorias do dashboard
$yesterday = date('Y-m-d', strtotime('-1 day'));
$comparisonData = $occurrenceModel->getDashboardComparison($today, $yesterday);

// Processar dados de comparação
$currentData = null;
$previousData = null;
foreach ($comparisonData as $data) {
    if ($data['period'] === 'current') {
        $currentData = $data;
    } else {
        $previousData = $data;
    }
}

// Calcular variações percentuais
function calculatePercentageChange($current, $previous) {
    $c = is_numeric($current) ? (float)$current : 0.0;
    $p = is_numeric($previous) ? (float)$previous : 0.0;
    if ($p == 0.0) {
        if ($c == 0.0) return 0.0;
        return $c > 0 ? 100.0 : -100.0;
    }
    return round((($c - $p) / $p) * 100, 1);
}

$todayChange = calculatePercentageChange(
    $currentData['total_occurrences'] ?? 0, 
    $previousData['total_occurrences'] ?? 0
);

$openChange = calculatePercentageChange(
    $openOccurrences, 
    $previousData['open_occurrences'] ?? 0
);

$equipmentChange = calculatePercentageChange(
    count($equipmentsInOperation), 
    $previousData['active_equipments'] ?? 0
);

// Alertas críticos
$criticalAlerts = $occurrenceModel->getCriticalAlerts();

// Eficiência dos equipamentos (últimos 7 dias)
$equipmentEfficiency = $occurrenceModel->getEquipmentEfficiency($weekAgo, $today);

// Filtros de período (se fornecidos via GET)
$filterPeriod = $_GET['period'] ?? '7days';
$customDateFrom = $_GET['date_from'] ?? '';
$customDateTo = $_GET['date_to'] ?? '';

// Ajustar período baseado no filtro
switch ($filterPeriod) {
    case '1day':
        $filterStart = date('Y-m-d');
        $filterEnd = date('Y-m-d');
        break;
    case '7days':
        $filterStart = date('Y-m-d', strtotime('-7 days'));
        $filterEnd = date('Y-m-d');
        break;
    case '30days':
        $filterStart = date('Y-m-d', strtotime('-30 days'));
        $filterEnd = date('Y-m-d');
        break;
    case 'custom':
        $filterStart = $customDateFrom ?: date('Y-m-d', strtotime('-7 days'));
        $filterEnd = $customDateTo ?: date('Y-m-d');
        break;
    default:
        $filterStart = date('Y-m-d', strtotime('-7 days'));
        $filterEnd = date('Y-m-d');
}

// Recarregar estatísticas com período filtrado se necessário
if ($filterPeriod !== '7days' || $customDateFrom || $customDateTo) {
    $categoryStats = $occurrenceModel->getStatsByCategory($filterStart, $filterEnd);
    $equipmentEfficiency = $occurrenceModel->getEquipmentEfficiency($filterStart, $filterEnd);
}

include 'includes/header.php';
?>

<style>
    /* Animações customizadas para o dashboard */
    @keyframes pulse-slow {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    .animate-pulse-slow {
        animation: pulse-slow 2s infinite;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .fade-in-up {
        animation: fadeInUp 0.6s ease-out;
    }
    
    /* Hover effects melhorados */
    .card-hover {
        transition: all 0.3s ease;
    }
    
    .card-hover:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }
    
    /* Indicadores de status com glow */
    .status-indicator {
        box-shadow: 0 0 10px rgba(34, 197, 94, 0.3);
    }
    
    .status-indicator.critical {
        box-shadow: 0 0 10px rgba(239, 68, 68, 0.3);
    }
    
    /* Gradientes customizados */
    .gradient-success {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }
    
    .gradient-warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }
    
    .gradient-danger {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }
    
    /* Responsividade melhorada */
    @media (max-width: 768px) {
        .mobile-stack {
            flex-direction: column;
        }
        
        .mobile-full {
            width: 100%;
        }
    }
</style>

<div class="space-y-6">
    <!-- Filtros de Período -->
    <div class="bg-white rounded-lg shadow-sm p-4 border border-silver-lake-blue">
        <form method="GET" class="flex flex-wrap items-center gap-4">
            <div class="flex items-center space-x-2">
                <label class="text-sm font-medium text-paynes-gray">Período:</label>
                <select name="period" class="border border-gray-300 rounded-md px-3 py-1 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" onchange="toggleCustomDates(this.value)">
                    <option value="1day" <?php echo $filterPeriod === '1day' ? 'selected' : ''; ?>>Hoje</option>
                    <option value="7days" <?php echo $filterPeriod === '7days' ? 'selected' : ''; ?>>Últimos 7 dias</option>
                    <option value="30days" <?php echo $filterPeriod === '30days' ? 'selected' : ''; ?>>Últimos 30 dias</option>
                    <option value="custom" <?php echo $filterPeriod === 'custom' ? 'selected' : ''; ?>>Personalizado</option>
                </select>
            </div>
            
            <div id="custom-dates" class="flex items-center space-x-2" style="display: <?php echo $filterPeriod === 'custom' ? 'flex' : 'none'; ?>;">
                <label class="text-sm font-medium text-paynes-gray">De:</label>
                <input type="date" name="date_from" value="<?php echo $customDateFrom; ?>" class="border border-gray-300 rounded-md px-3 py-1 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <label class="text-sm font-medium text-paynes-gray">Até:</label>
                <input type="date" name="date_to" value="<?php echo $customDateTo; ?>" class="border border-gray-300 rounded-md px-3 py-1 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <button type="submit" class="bg-blue-600 text-white px-4 py-1 rounded-md text-sm hover:bg-blue-700 transition-colors">
                <i class="fas fa-filter mr-1"></i>
                Aplicar Filtro
            </button>
            
            <?php if ($filterPeriod !== '7days' || $customDateFrom || $customDateTo): ?>
            <a href="dashboard.php" class="bg-gray-500 text-white px-4 py-1 rounded-md text-sm hover:bg-gray-600 transition-colors">
                <i class="fas fa-times mr-1"></i>
                Limpar
            </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Alertas Críticos -->
    <?php if (!empty($criticalAlerts)): ?>
    <div class="bg-gradient-to-r from-red-50 to-orange-50 border-l-4 border-red-500 rounded-lg p-4 shadow-md">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-red-800 flex items-center">
                <div class="animate-pulse mr-3">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                Alertas Críticos
                <span class="ml-2 bg-red-600 text-white text-xs font-bold px-2 py-1 rounded-full">
                    <?php echo count($criticalAlerts); ?>
                </span>
            </h3>
            <div class="text-xs text-red-600 font-medium">
                <i class="fas fa-clock mr-1"></i>
                Atualizado agora
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($criticalAlerts as $alert): ?>
            <div class="bg-white border-l-4 border-red-500 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow duration-200">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center mb-2">
                            <div class="w-3 h-3 bg-red-500 rounded-full mr-2 animate-pulse"></div>
                            <p class="font-bold text-red-900 text-sm"><?php echo htmlspecialchars($alert['tag']); ?></p>
                        </div>
                        <div class="space-y-1">
                            <?php if ($alert['open_occurrences'] > 1): ?>
                            <div class="flex items-center text-xs text-red-700">
                                <i class="fas fa-exclamation-circle mr-2 text-red-500"></i>
                                <span class="font-medium"><?php echo $alert['open_occurrences']; ?> ocorrências abertas</span>
                            </div>
                            <?php endif; ?>
                            <?php if ($alert['hours_since_last'] > 24): ?>
                            <div class="flex items-center text-xs text-orange-700">
                                <i class="fas fa-clock mr-2 text-orange-500"></i>
                                <span class="font-medium"><?php echo round($alert['hours_since_last']); ?>h sem atividade</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="equipment-details.php?id=<?php echo $alert['id']; ?>" 
                       class="ml-3 bg-red-100 hover:bg-red-200 text-red-700 p-2 rounded-full transition-colors duration-200">
                        <i class="fas fa-arrow-right text-sm"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-4 text-center">
            <a href="occurrences.php?status=open" 
               class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors duration-200">
                <i class="fas fa-list mr-2"></i>
                Ver Todas as Ocorrências Abertas
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Cards de Estatísticas -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total de Equipamentos -->
        <div class="bg-white rounded-lg shadow-sm p-6 border border-silver-lake-blue card-hover fade-in-up">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                    <i class="fas fa-cogs text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-paynes-gray">Total de Equipamentos</p>
                    <p class="text-2xl font-semibold text-rich-black"><?php echo $totalEquipments; ?></p>
                </div>
            </div>
        </div>

        <!-- Equipamentos em Operação -->
        <div class="bg-white rounded-lg shadow-sm p-6 border border-silver-lake-blue card-hover fade-in-up">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600 status-indicator">
                        <i class="fas fa-play text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-paynes-gray">Em Operação</p>
                        <p class="text-2xl font-semibold text-rich-black"><?php echo count($equipmentsInOperation); ?></p>
                    </div>
                </div>
                <?php if ($equipmentChange != 0): ?>
                <div class="text-right">
                    <span class="text-xs font-medium <?php echo $equipmentChange > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                        <i class="fas fa-arrow-<?php echo $equipmentChange > 0 ? 'up' : 'down'; ?> mr-1"></i>
                        <?php echo abs($equipmentChange); ?>%
                    </span>
                    <p class="text-xs text-paynes-gray">vs ontem</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Ocorrências Abertas -->
        <div class="bg-white rounded-lg shadow-sm p-6 border border-silver-lake-blue card-hover fade-in-up">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <i class="fas fa-clock text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-paynes-gray">Ocorrências Abertas</p>
                        <p class="text-2xl font-semibold text-rich-black"><?php echo $openOccurrences; ?></p>
                    </div>
                </div>
                <?php if ($openChange != 0): ?>
                <div class="text-right">
                    <span class="text-xs font-medium <?php echo $openChange > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                        <i class="fas fa-arrow-<?php echo $openChange > 0 ? 'up' : 'down'; ?> mr-1"></i>
                        <?php echo abs($openChange); ?>%
                    </span>
                    <p class="text-xs text-paynes-gray">vs ontem</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Ocorrências Hoje -->
        <div class="bg-white rounded-lg shadow-sm p-6 border border-silver-lake-blue card-hover fade-in-up">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-calendar-day text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-paynes-gray">Ocorrências Hoje</p>
                        <p class="text-2xl font-semibold text-rich-black"><?php echo $todayOccurrences; ?></p>
                    </div>
                </div>
                <?php if ($todayChange != 0): ?>
                <div class="text-right">
                    <span class="text-xs font-medium <?php echo $todayChange > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                        <i class="fas fa-arrow-<?php echo $todayChange > 0 ? 'up' : 'down'; ?> mr-1"></i>
                        <?php echo abs($todayChange); ?>%
                    </span>
                    <p class="text-xs text-paynes-gray">vs ontem</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Equipamentos em Operação -->
        <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
            <div class="p-6 border-b border-silver-lake-blue">
                <h3 class="text-lg font-semibold text-rich-black">Equipamentos em Operação</h3>
            </div>
            <div class="p-6">
                <?php if (empty($equipmentsInOperation)): ?>
                    <p class="text-paynes-gray text-center py-4">Nenhum equipamento em operação no momento</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($equipmentsInOperation as $equipment): ?>
                            <div class="bg-gradient-to-r from-green-50 to-blue-50 border border-green-200 rounded-lg p-4 hover:shadow-md transition-shadow duration-200">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-2">
                                            <div class="w-3 h-3 bg-green-500 rounded-full mr-3 animate-pulse"></div>
                                            <h4 class="font-bold text-rich-black text-lg"><?php echo htmlspecialchars($equipment['tag']); ?></h4>
                                            <span class="ml-2 bg-green-100 text-green-800 text-xs font-medium px-2 py-1 rounded-full">
                                                ATIVO
                                            </span>
                                        </div>
                                        <p class="text-sm text-paynes-gray mb-3"><?php echo htmlspecialchars($equipment['description']); ?></p>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                                            <div class="flex items-center">
                                                <i class="fas fa-user text-blue-500 mr-2"></i>
                                                <div>
                                                    <p class="font-medium text-paynes-gray">Operador</p>
                                                    <p class="text-rich-black"><?php echo htmlspecialchars($equipment['operator_name'] ?? 'N/A'); ?></p>
                                                </div>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="fas fa-map-marker-alt text-orange-500 mr-2"></i>
                                                <div>
                                                    <p class="font-medium text-paynes-gray">Frente</p>
                                                    <p class="text-rich-black"><?php echo htmlspecialchars($equipment['front_name']); ?></p>
                                                </div>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="fas fa-clock text-purple-500 mr-2"></i>
                                                <div>
                                                    <p class="font-medium text-paynes-gray">Última Atividade</p>
                                                    <p class="text-rich-black">
                                                        <?php 
                                                        $lastActivity = new DateTime($equipment['start_time']);
                                                        $now = new DateTime();
                                                        $diff = $now->diff($lastActivity);
                                                        if ($diff->h > 0) {
                                                            echo $diff->h . 'h ' . $diff->i . 'm atrás';
                                                        } else {
                                                            echo $diff->i . 'm atrás';
                                                        }
                                                        ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex flex-col items-end space-y-2">
                                        <a href="equipment-details.php?id=<?php echo $equipment['equipment_id']; ?>" 
                                           class="bg-blue-100 hover:bg-blue-200 text-blue-700 p-2 rounded-full transition-colors duration-200">
                                            <i class="fas fa-eye text-sm"></i>
                                        </a>
                                        <div class="text-center">
                                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-cogs text-green-600 text-lg"></i>
                                            </div>
                                            <p class="text-xs text-green-600 font-medium mt-1">Operando</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 text-center">
                        <a href="equipment.php" 
                           class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors duration-200">
                            <i class="fas fa-list mr-2"></i>
                            Ver Todos os Equipamentos
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Últimas Ocorrências -->
        <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
            <div class="p-6 border-b border-silver-lake-blue">
                <h3 class="text-lg font-semibold text-rich-black">Últimas Ocorrências</h3>
            </div>
            <div class="p-6">
                <?php if (empty($recentOccurrences)): ?>
                    <p class="text-paynes-gray text-center py-4">Nenhuma ocorrência registrada</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($recentOccurrences as $occurrence): ?>
                            <div class="flex items-center justify-between p-3 bg-eggshell rounded-lg">
                                <div>
                                    <p class="font-medium text-rich-black"><?php echo htmlspecialchars($occurrence['equipment_tag']); ?></p>
                                    <p class="text-sm text-paynes-gray"><?php echo htmlspecialchars($occurrence['occurrence_type_description']); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium">
                                        <?php echo date('d/m H:i', strtotime($occurrence['start_datetime'])); ?>
                                    </p>
                                    <p class="text-xs text-paynes-gray">
                                        <?php if ($occurrence['end_datetime']): ?>
                                            <span class="text-green-600">Finalizada</span>
                                        <?php else: ?>
                                            <span class="text-yellow-600">Em andamento</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Estatísticas por Categoria -->
    <?php if (!empty($categoryStats)): ?>
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
        <div class="p-6 border-b border-silver-lake-blue">
            <h3 class="text-lg font-semibold text-rich-black">Estatísticas por Categoria (Últimos 7 dias)</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php foreach ($categoryStats as $stat): ?>
                    <div class="text-center p-4 bg-eggshell rounded-lg">
                        <p class="text-2xl font-bold text-rich-black"><?php echo $stat['total_occurrences']; ?></p>
                        <p class="text-sm font-medium text-paynes-gray"><?php echo htmlspecialchars($stat['category']); ?></p>
                        <p class="text-xs text-paynes-gray">
                            <?php echo number_format($stat['total_hours'], 1); ?>h trabalhadas
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Ações Rápidas -->
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
        <div class="p-6 border-b border-silver-lake-blue">
            <h3 class="text-lg font-semibold text-rich-black">Ações Rápidas</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="new-occurrence.php" class="flex items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                    <i class="fas fa-plus text-blue-600 mr-3"></i>
                    <span class="font-medium text-blue-800">Nova Ocorrência</span>
                </a>
                
                <a href="occurrences.php" class="flex items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                    <i class="fas fa-list text-green-600 mr-3"></i>
                    <span class="font-medium text-green-800">Ver Ocorrências</span>
                </a>
                
                <?php if ($auth->hasPermission(ROLE_SUPERVISOR)): ?>
                <a href="reports-equipment.php" class="flex items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                    <i class="fas fa-chart-bar text-purple-600 mr-3"></i>
                    <span class="font-medium text-purple-800">Relatórios</span>
                </a>
                <?php endif; ?>
                
                <?php if ($auth->hasPermission(ROLE_LIDER)): ?>
                <a href="equipments.php" class="flex items-center p-4 bg-yellow-50 rounded-lg hover:bg-yellow-100 transition-colors">
                    <i class="fas fa-cogs text-yellow-600 mr-3"></i>
                    <span class="font-medium text-yellow-800">Equipamentos</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh do dashboard a cada 5 minutos
function refreshData() {
    location.reload();
}

// Atualizar horário em tempo real
function updateTime() {
    const now = new Date();
    const timeString = now.toLocaleString('pt-BR');
    // Atualizar elementos de tempo se existirem
    const timeElements = document.querySelectorAll('.current-time');
    timeElements.forEach(el => el.textContent = timeString);
}

setInterval(updateTime, 1000);
</script>

<?php include 'includes/footer.php'; ?>
