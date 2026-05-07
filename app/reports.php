<?php
/**
 * Sistema de Relatórios
 * Sistema BDO - Controle de Maquinários
 */

require_once 'config/config.php';

// Verificar autenticação
$database = Database::getInstance();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$pageTitle = 'Relatórios';
$occurrenceModel = new Occurrence($db);
$equipmentModel = new Equipment($db);
$occurrenceTypeModel = new OccurrenceType($db);
$userModel = new User($db);
$frontModel = new Front($db);
$clientModel = new Client($db);
$inventoryModel = new Inventory($db);
$productionModel = new Production($db);
$deliveryModel = new Delivery($db);

// Processar exportação
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $reportType = $_GET['report_type'] ?? 'occurrences';
    $filters = $_GET;
    unset($filters['export'], $filters['report_type']);
    
    exportToCSV($reportType, $filters);
    exit();
}

// Ações POST (saldo inicial)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upsert_opening_balance') {
    $frontId = intval($_POST['front_id'] ?? 0);
    $balanceDate = $_POST['balance_date'] ?? date('Y-m-01');
    $value = floatval($_POST['opening_value'] ?? 0);
    $notes = $_POST['notes'] ?? null;
    $userId = $_SESSION['user_id'] ?? 1;
    if ($frontId > 0) {
        try {
            $inventoryModel->upsertOpeningBalance($frontId, $balanceDate, $value, 'toneladas', $notes, $userId);
            $_SESSION['success'] = 'Saldo inicial atualizado com sucesso.';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Erro ao atualizar saldo inicial: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = 'Frente inválida para saldo inicial.';
    }
    // Redireciona mantendo filtros atuais
    $qs = $_POST;
    unset($qs['action'], $qs['opening_value'], $qs['notes']);
    $qs['report_type'] = 'stock';
    header('Location: reports.php?' . http_build_query($qs));
    exit();
}

// Buscar dados para filtros
$equipments = $equipmentModel->findActive();
$occurrenceTypes = $occurrenceTypeModel->findActive();
$operators = $userModel->findOperators();
$fronts = $frontModel->findActive();
$clients = $clientModel->findAllActive();

// Filtros padrão
$reportType = $_GET['report_type'] ?? 'occurrences';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$equipmentFilter = $_GET['equipment_id'] ?? '';
$typeFilter = $_GET['type_id'] ?? '';
$operatorFilter = $_GET['operator_id'] ?? '';
$frontFilter = $_GET['front_id'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$clientFilter = $_GET['client_id'] ?? '';

// Ajuste de período inválido
if (strtotime($dateFrom) !== false && strtotime($dateTo) !== false) {
    if (strtotime($dateFrom) > strtotime($dateTo)) {
        $tmp = $dateFrom;
        $dateFrom = $dateTo;
        $dateTo = $tmp;
    }
}

// Verificar permissões para filtrar por frente
if (!$auth->hasPermission(ROLE_ADMIN) && !$auth->hasPermission(ROLE_SUPERVISOR)) {
    $userFrontId = $_SESSION['user_front'] ?? null;
    if ($userFrontId) {
        $frontFilter = $userFrontId;
    }
}

$reportData = [];
$reportTitle = '';
$errorMessage = '';

try {
    switch ($reportType) {
        case 'occurrences':
            $reportData = generateOccurrencesReport();
            $reportTitle = 'Relatório de Ocorrências';
            break;
        case 'equipment_usage':
            $reportData = generateEquipmentUsageReport();
            $reportTitle = 'Relatório de Uso de Equipamentos';
            break;
        case 'productivity':
            $reportData = generateProductivityReport();
            $reportTitle = 'Relatório de Produtividade';
            break;
        case 'maintenance':
            $reportData = generateMaintenanceReport();
            $reportTitle = 'Relatório de Manutenção';
            break;
        case 'stock':
            $reportData = generateStockReport();
            $reportTitle = 'Relatório de Estoque (Saldo Inicial + Produção - Entregas)';
            break;
    }
} catch (Exception $e) {
    $errorMessage = (defined('DEBUG_MODE') && DEBUG_MODE) ? $e->getMessage() : 'Falha ao gerar o relatório. Tente novamente.';
    $reportData = [];
}

function generateOccurrencesReport() {
    global $occurrenceModel, $dateFrom, $dateTo, $equipmentFilter, $typeFilter, $operatorFilter, $frontFilter, $categoryFilter;
    
    $conditions = [];
    if ($equipmentFilter) $conditions['o.equipment_id'] = $equipmentFilter;
    if ($typeFilter) $conditions['o.occurrence_type_id'] = $typeFilter;
    if ($operatorFilter) $conditions['o.operator_id'] = $operatorFilter;
    if ($frontFilter) $conditions['e.front_id'] = $frontFilter;
    if ($categoryFilter) $conditions['ot.category'] = $categoryFilter;
    
    $dateRange = ['start' => $dateFrom, 'end' => $dateTo];
    
    return $occurrenceModel->findWithDetails($conditions, $dateRange);
}

function generateEquipmentUsageReport() {
    global $equipmentModel, $dateFrom, $dateTo, $equipmentFilter, $frontFilter;
    
    $conditions = [];
    if ($equipmentFilter) $conditions['e.id'] = $equipmentFilter;
    if ($frontFilter) $conditions['e.front_id'] = $frontFilter;
    
    return $equipmentModel->findWithUsageStats($conditions, $dateFrom, $dateTo);
}

function generateProductivityReport() {
    global $occurrenceModel, $dateFrom, $dateTo, $equipmentFilter, $frontFilter;
    
    $conditions = [];
    if ($equipmentFilter) $conditions['equipment_id'] = $equipmentFilter;
    if ($frontFilter) $conditions['front_id'] = $frontFilter;
    
    return $occurrenceModel->getEquipmentStats($conditions, $dateFrom, $dateTo);
}

function generateMaintenanceReport() {
    global $occurrenceModel, $dateFrom, $dateTo, $equipmentFilter, $frontFilter;
    
    $conditions = [];
    if ($equipmentFilter) $conditions['o.equipment_id'] = $equipmentFilter;
    if ($frontFilter) $conditions['e.front_id'] = $frontFilter;
    $conditions['ot.category'] = 'MANUTENCAO';
    
    $dateRange = ['start' => $dateFrom, 'end' => $dateTo];
    
    return $occurrenceModel->findWithDetails($conditions, $dateRange);
}

function generateStockReport() {
    global $inventoryModel, $dateFrom, $dateTo, $frontFilter, $clientFilter, $auth;
    if (!$frontFilter) {
        // Restringe por perfil: se não puder escolher frente, usa a do usuário
        $userFrontId = $_SESSION['user_front'] ?? null;
        if ($userFrontId) $frontFilter = $userFrontId;
    }
    if (!$frontFilter) {
        return [];
    }
    return $inventoryModel->getDailyStock((int)$frontFilter, $dateFrom, $dateTo, $clientFilter ? (int)$clientFilter : null);
}

function exportToCSV($reportType, $filters) {
    global $occurrenceModel, $equipmentModel, $inventoryModel;
    
    $filename = $reportType . '_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    switch ($reportType) {
        case 'occurrences':
            fputcsv($output, [
                'Data/Hora Início', 'Data/Hora Fim', 'Equipamento', 'Tipo', 'Categoria',
                'Operador', 'Horímetro Inicial', 'Horímetro Final', 'Horas Trabalhadas',
                'Duração (h)', 'Descrição'
            ], ';');
            
            // Gerar dados baseado nos filtros
            $conditions = [];
            if (!empty($filters['equipment_id'])) $conditions['o.equipment_id'] = $filters['equipment_id'];
            if (!empty($filters['type_id'])) $conditions['o.occurrence_type_id'] = $filters['type_id'];
            if (!empty($filters['operator_id'])) $conditions['o.operator_id'] = $filters['operator_id'];
            if (!empty($filters['front_id'])) $conditions['e.front_id'] = $filters['front_id'];
            if (!empty($filters['category'])) $conditions['ot.category'] = $filters['category'];
            
            $dateRange = [];
            if (!empty($filters['date_from'])) $dateRange['start'] = $filters['date_from'];
            if (!empty($filters['date_to'])) $dateRange['end'] = $filters['date_to'];
            
            $data = $occurrenceModel->findWithDetails($conditions, $dateRange);
            
            foreach ($data as $row) {
                fputcsv($output, [
                    date('d/m/Y H:i', strtotime($row['start_datetime'])),
                    $row['end_datetime'] ? date('d/m/Y H:i', strtotime($row['end_datetime'])) : '',
                    $row['equipment_tag'] . ' - ' . $row['equipment_description'],
                    $row['type_description'],
                    $row['type_category'],
                    $row['operator_name'],
                    number_format($row['initial_hour_meter'], 2, ',', '.'),
                    $row['final_hour_meter'] ? number_format($row['final_hour_meter'], 2, ',', '.') : '',
                    $row['hours_worked'] ? number_format($row['hours_worked'], 2, ',', '.') : '',
                    $row['duration_hours'] ? number_format($row['duration_hours'], 2, ',', '.') : '',
                    $row['description']
                ], ';');
            }
            break;
            
        case 'equipment_usage':
            fputcsv($output, [
                'Equipamento', 'Frente', 'Horas Produção', 'Horas Manutenção',
                'Horas Parada', 'Total Horas', 'Disponibilidade (%)', 'Utilização (%)'
            ], ';');
            
            $conditions = [];
            if (!empty($filters['equipment_id'])) $conditions['e.id'] = $filters['equipment_id'];
            if (!empty($filters['front_id'])) $conditions['e.front_id'] = $filters['front_id'];
            
            $dateFrom = $filters['date_from'] ?? date('Y-m-01');
            $dateTo = $filters['date_to'] ?? date('Y-m-d');
            
            $data = $equipmentModel->findWithUsageStats($conditions, $dateFrom, $dateTo);
            
            foreach ($data as $row) {
                $totalHours = ($row['production_hours'] ?? 0) + ($row['maintenance_hours'] ?? 0) + ($row['downtime_hours'] ?? 0);
                $availability = $totalHours > 0 ? (($row['production_hours'] ?? 0) / $totalHours) * 100 : 0;
                $utilization = $totalHours > 0 ? ((($row['production_hours'] ?? 0) + ($row['maintenance_hours'] ?? 0)) / $totalHours) * 100 : 0;
                
                fputcsv($output, [
                    $row['tag'] . ' - ' . $row['description'],
                    $row['front_name'] ?? '',
                    number_format($row['production_hours'] ?? 0, 2, ',', '.'),
                    number_format($row['maintenance_hours'] ?? 0, 2, ',', '.'),
                    number_format($row['downtime_hours'] ?? 0, 2, ',', '.'),
                    number_format($totalHours, 2, ',', '.'),
                    number_format($availability, 2, ',', '.'),
                    number_format($utilization, 2, ',', '.')
                ], ';');
            }
            break;
            
        case 'stock':
            fputcsv($output, [
                'Data', 'Saldo Inicial', 'Produção do Dia', 'Entregas do Dia', 'Estoque Final'
            ], ';');
            $frontId = !empty($filters['front_id']) ? (int)$filters['front_id'] : null;
            if (!$frontId) {
                $userFrontId = $_SESSION['user_front'] ?? null;
                $frontId = $userFrontId;
            }
            $clientId = !empty($filters['client_id']) ? (int)$filters['client_id'] : null;
            $dateFrom = $filters['date_from'] ?? date('Y-m-01');
            $dateTo = $filters['date_to'] ?? date('Y-m-d');
            if ($frontId) {
                $rows = $inventoryModel->getDailyStock($frontId, $dateFrom, $dateTo, $clientId);
                foreach ($rows as $r) {
                    fputcsv($output, [
                        date('d/m/Y', strtotime($r['date'])),
                        number_format($r['opening'], 2, ',', '.'),
                        number_format($r['produced'], 2, ',', '.'),
                        number_format($r['delivered'], 2, ',', '.'),
                        number_format($r['final'], 2, ',', '.'),
                    ], ';');
                }
            }
            break;
    }
    
    fclose($output);
}

include 'includes/header.php';
?>

<div class="space-y-6">
    <!-- Filtros de Relatório -->
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6">
        <h2 class="text-lg font-semibold text-rich-black mb-4">Filtros de Relatório</h2>
        
        <form method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-paynes-gray mb-2">Tipo de Relatório</label>
                    <select name="report_type" onchange="this.form.submit()" 
                            class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                        <option value="occurrences" <?php echo $reportType === 'occurrences' ? 'selected' : ''; ?>>Ocorrências</option>
                        <option value="equipment_usage" <?php echo $reportType === 'equipment_usage' ? 'selected' : ''; ?>>Uso de Equipamentos</option>
                        <option value="productivity" <?php echo $reportType === 'productivity' ? 'selected' : ''; ?>>Produtividade</option>
                        <option value="maintenance" <?php echo $reportType === 'maintenance' ? 'selected' : ''; ?>>Manutenção</option>
                        <option value="stock" <?php echo $reportType === 'stock' ? 'selected' : ''; ?>>Estoque</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-paynes-gray mb-2">Data Início</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" 
                           class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-paynes-gray mb-2">Data Fim</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" 
                           class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-paynes-gray mb-2">Equipamento</label>
                    <select name="equipment_id" 
                            class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                        <option value="">Todos os equipamentos</option>
                        <?php foreach ($equipments as $equipment): ?>
                            <option value="<?php echo $equipment['id']; ?>" <?php echo $equipmentFilter == $equipment['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($equipment['tag']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if ($auth->hasPermission(ROLE_ADMIN) || $auth->hasPermission(ROLE_SUPERVISOR)): ?>
                <div>
                    <label class="block text-sm font-medium text-paynes-gray mb-2">Frente</label>
                    <select name="front_id" 
                            class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                        <option value="">Todas as frentes</option>
                        <?php foreach ($fronts as $front): ?>
                            <option value="<?php echo $front['id']; ?>" <?php echo $frontFilter == $front['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($front['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <?php if ($reportType === 'stock'): ?>
                <div>
                    <label class="block text-sm font-medium text-paynes-gray mb-2">Cliente (opcional)</label>
                    <select name="client_id" 
                            class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                        <option value="">Todos os clientes</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>" <?php echo $clientFilter == $client['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($client['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <?php if ($reportType === 'occurrences' || $reportType === 'maintenance'): ?>
                <div>
                    <label class="block text-sm font-medium text-paynes-gray mb-2">Tipo de Ocorrência</label>
                    <select name="type_id" 
                            class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                        <option value="">Todos os tipos</option>
                        <?php foreach ($occurrenceTypes as $type): ?>
                            <option value="<?php echo $type['id']; ?>" <?php echo $typeFilter == $type['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['description']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-paynes-gray mb-2">Operador</label>
                    <select name="operator_id" 
                            class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                        <option value="">Todos os operadores</option>
                        <?php foreach ($operators as $operator): ?>
                            <option value="<?php echo $operator['id']; ?>" <?php echo $operatorFilter == $operator['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($operator['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-paynes-gray mb-2">Categoria</label>
                    <select name="category" 
                            class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                        <option value="">Todas as categorias</option>
                        <option value="PRODUCAO" <?php echo $categoryFilter === 'PRODUCAO' ? 'selected' : ''; ?>>Produção</option>
                        <option value="MANUTENCAO" <?php echo $categoryFilter === 'MANUTENCAO' ? 'selected' : ''; ?>>Manutenção</option>
                        <option value="PARADA" <?php echo $categoryFilter === 'PARADA' ? 'selected' : ''; ?>>Parada</option>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="flex space-x-4">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-search mr-2"></i>Gerar Relatório
                </button>
                
                <button type="button" onclick="exportReport()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    <i class="fas fa-download mr-2"></i>Exportar CSV
                </button>
            </div>
        </form>
    </div>

    <!-- Resultados do Relatório -->
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
        <div class="p-6 border-b border-silver-lake-blue">
            <h2 class="text-lg font-semibold text-rich-black"><?php echo $reportTitle; ?></h2>
            <p class="text-sm text-paynes-gray">
                Período: <?php echo date('d/m/Y', strtotime($dateFrom)); ?> a <?php echo date('d/m/Y', strtotime($dateTo)); ?>
                <?php if (!empty($reportData)): ?>
                    | Total de registros: <?php echo count($reportData); ?>
                <?php endif; ?>
            </p>
            <?php if (!empty($errorMessage)): ?>
                <div class="mt-3 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['success'])): ?>
                <div class="mt-3 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="mt-3 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($reportType === 'stock'): ?>
            <form method="POST" class="mt-4 flex items-end space-x-4">
                <input type="hidden" name="action" value="upsert_opening_balance">
                <input type="hidden" name="report_type" value="stock">
                <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                <input type="hidden" name="front_id" value="<?php echo htmlspecialchars($frontFilter); ?>">
                <input type="hidden" name="client_id" value="<?php echo htmlspecialchars($clientFilter); ?>">
                <div>
                    <label class="block text-sm font-medium text-paynes-gray mb-2">Saldo inicial na data inicial</label>
                    <input type="number" step="0.01" name="opening_value" class="px-3 py-2 border border-silver-lake-blue rounded-lg" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-paynes-gray mb-2">Data do saldo</label>
                    <input type="date" name="balance_date" value="<?php echo htmlspecialchars($dateFrom); ?>" class="px-3 py-2 border border-silver-lake-blue rounded-lg" required>
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium text-paynes-gray mb-2">Observações</label>
                    <input type="text" name="notes" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg">
                </div>
                <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition-colors">
                    <i class="fas fa-save mr-2"></i>Salvar saldo inicial
                </button>
            </form>
            <?php endif; ?>
        </div>
        
        <div class="overflow-x-auto">
            <?php if (empty($reportData)): ?>
                <div class="p-6 text-center text-paynes-gray">
                    Nenhum dado encontrado para os filtros selecionados.
                </div>
            <?php else: ?>
                <?php switch ($reportType): 
                    case 'occurrences': ?>
                        <table class="min-w-full divide-y divide-silver-lake-blue">
                            <thead class="bg-eggshell">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Data/Hora</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Equipamento</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Tipo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Operador</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Duração</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Horas Trab.</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-silver-lake-blue">
                                <?php foreach ($reportData as $row): ?>
                                    <tr class="hover:bg-eggshell">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-rich-black">
                                <?php echo date('d/m/Y H:i', strtotime($row['start_datetime'])); ?>
                            </div>
                            <?php if ($row['end_datetime']): ?>
                                <div class="text-xs text-paynes-gray">
                                    até <?php echo date('d/m/Y H:i', strtotime($row['end_datetime'])); ?>
                                </div>
                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-rich-black">
                                                <?php echo htmlspecialchars($row['equipment_tag']); ?>
                                            </div>
                                            <div class="text-xs text-paynes-gray">
                                                <?php echo htmlspecialchars($row['equipment_description']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-rich-black">
                                                <?php echo htmlspecialchars($row['type_description']); ?>
                                            </div>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php 
                                                switch($row['type_category']) {
                                                    case 'PRODUCAO': echo 'bg-green-100 text-green-800'; break;
                                                    case 'MANUTENCAO': echo 'bg-yellow-100 text-yellow-800'; break;
                                                    case 'PARADA': echo 'bg-red-100 text-red-800'; break;
                                                    default: echo 'bg-gray-100 text-gray-800';
                                                }
                                                ?>">
                                                <?php echo htmlspecialchars($row['type_category']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-rich-black">
                                                <?php echo htmlspecialchars($row['operator_name']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-rich-black">
                                                <?php echo $row['duration_hours'] ? number_format($row['duration_hours'], 2) . 'h' : '-'; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-rich-black">
                                                <?php echo $row['hours_worked'] ? number_format($row['hours_worked'], 2) . 'h' : '-'; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php break; ?>
                        
                    <?php case 'equipment_usage': ?>
                        <table class="min-w-full divide-y divide-silver-lake-blue">
                            <thead class="bg-eggshell">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Equipamento</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Frente</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Produção</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Manutenção</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Parada</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Total</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Disponibilidade</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-silver-lake-blue">
                                <?php foreach ($reportData as $row): ?>
                                    <?php 
                                    $totalHours = ($row['production_hours'] ?? 0) + ($row['maintenance_hours'] ?? 0) + ($row['downtime_hours'] ?? 0);
                                    $availability = $totalHours > 0 ? (($row['production_hours'] ?? 0) / $totalHours) * 100 : 0;
                                    ?>
                                    <tr class="hover:bg-eggshell">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-rich-black">
                                                <?php echo htmlspecialchars($row['tag']); ?>
                                            </div>
                                            <div class="text-xs text-paynes-gray">
                                                <?php echo htmlspecialchars($row['description']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-rich-black">
                                                <?php echo htmlspecialchars($row['front_name'] ?? 'N/A'); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-green-600 font-medium">
                                                <?php echo number_format($row['production_hours'] ?? 0, 2); ?>h
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-yellow-600 font-medium">
                                                <?php echo number_format($row['maintenance_hours'] ?? 0, 2); ?>h
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-red-600 font-medium">
                                                <?php echo number_format($row['downtime_hours'] ?? 0, 2); ?>h
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-rich-black font-medium">
                                                <?php echo number_format($totalHours, 2); ?>h
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="text-sm text-rich-black font-medium mr-2">
                                                    <?php echo number_format($availability, 1); ?>%
                                                </div>
                                                <div class="w-16 bg-gray-200 rounded-full h-2">
                                                    <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo $availability; ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php break; ?>
                        
                    <?php case 'productivity': ?>
                        <table class="min-w-full divide-y divide-silver-lake-blue">
                            <thead class="bg-eggshell">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Equipamento</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Produção</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Manutenção</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Parada</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Eficiência</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-silver-lake-blue">
                                <?php foreach ($reportData as $row): ?>
                                    <?php 
                                    $totalHours = ($row['production_hours'] ?? 0) + ($row['maintenance_hours'] ?? 0) + ($row['downtime_hours'] ?? 0);
                                    $efficiency = $totalHours > 0 ? (($row['production_hours'] ?? 0) / $totalHours) * 100 : 0;
                                    ?>
                                    <tr class="hover:bg-eggshell">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-rich-black">
                                                <?php echo htmlspecialchars($row['equipment_tag'] ?? $row['tag']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-green-600 font-medium">
                                                <?php echo number_format($row['production_hours'] ?? 0, 2); ?>h
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-yellow-600 font-medium">
                                                <?php echo number_format($row['maintenance_hours'] ?? 0, 2); ?>h
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-red-600 font-medium">
                                                <?php echo number_format($row['downtime_hours'] ?? 0, 2); ?>h
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php 
                                                if ($efficiency >= 80) echo 'bg-green-100 text-green-800';
                                                elseif ($efficiency >= 60) echo 'bg-yellow-100 text-yellow-800';
                                                else echo 'bg-red-100 text-red-800';
                                                ?>">
                                                <?php echo number_format($efficiency, 1); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php break; ?>
                        
                    <?php case 'maintenance': ?>
                        <table class="min-w-full divide-y divide-silver-lake-blue">
                            <thead class="bg-eggshell">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Data</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Equipamento</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Tipo Manutenção</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Duração</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Responsável</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-silver-lake-blue">
                                <?php foreach ($reportData as $row): ?>
                                    <tr class="hover:bg-eggshell">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-rich-black">
                                <?php echo date('d/m/Y', strtotime($row['start_datetime'])); ?>
                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-rich-black">
                                                <?php echo htmlspecialchars($row['equipment_tag']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-rich-black">
                                                <?php echo htmlspecialchars($row['type_description']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-rich-black">
                                                <?php echo $row['duration_hours'] ? number_format($row['duration_hours'], 2) . 'h' : '-'; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-rich-black">
                                                <?php echo htmlspecialchars($row['operator_name']); ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php break; ?>
                        
                    <?php case 'stock': ?>
                        <table class="min-w-full divide-y divide-silver-lake-blue">
                            <thead class="bg-eggshell">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Data</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-paynes-gray uppercase tracking-wider">Saldo Inicial</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-paynes-gray uppercase tracking-wider">Produção do Dia</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-paynes-gray uppercase tracking-wider">Entregas do Dia</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-paynes-gray uppercase tracking-wider">Estoque Final</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-silver-lake-blue">
                                <?php foreach ($reportData as $row): ?>
                                <tr class="hover:bg-eggshell">
                                    <td class="px-6 py-3 text-sm text-rich-black"><?php echo date('d/m/Y', strtotime($row['date'])); ?></td>
                                    <td class="px-6 py-3 text-right text-sm text-rich-black"><?php echo number_format($row['opening'], 2, ',', '.'); ?></td>
                                    <td class="px-6 py-3 text-right text-sm text-green-700 font-medium"><?php echo number_format($row['produced'], 2, ',', '.'); ?></td>
                                    <td class="px-6 py-3 text-right text-sm text-red-700 font-medium"><?php echo number_format($row['delivered'], 2, ',', '.'); ?></td>
                                    <td class="px-6 py-3 text-right text-sm font-semibold text-rich-black"><?php echo number_format($row['final'], 2, ',', '.'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php break; ?>
                <?php endswitch; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function exportReport() {
    const form = document.querySelector('form');
    const formData = new FormData(form);
    
    // Adicionar parâmetro de exportação
    formData.append('export', 'csv');
    
    // Criar URL com parâmetros
    const params = new URLSearchParams(formData);
    const url = window.location.pathname + '?' + params.toString();
    
    // Abrir em nova janela para download
    window.open(url, '_blank');
}

// Atualizar relatório automaticamente quando mudar o tipo
document.querySelector('select[name="report_type"]').addEventListener('change', function() {
    this.form.submit();
});
</script>

<?php include 'includes/footer.php'; ?>
