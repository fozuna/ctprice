<?php
/**
 * Gerenciamento de Ocorrências/Movimentos
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

$pageTitle = 'Ocorrências';
$occurrenceModel = new Occurrence($db);
$equipmentModel = new Equipment($db);
$occurrenceTypeModel = new OccurrenceType($db);
$userModel = new User($db);
$auditLog = new AuditLog($db);

$message = '';
$messageType = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create':
                $data = [
                    'equipment_id' => intval($_POST['equipment_id']),
                    'occurrence_type_id' => intval($_POST['occurrence_type_id']),
                    'operator_id' => intval($_POST['operator_id']),
                    'start_datetime' => $_POST['start_date'] . ' ' . $_POST['start_time'],
                    'end_datetime' => !empty($_POST['end_date']) && !empty($_POST['end_time']) ? 
                                  $_POST['end_date'] . ' ' . $_POST['end_time'] : null,
                    'initial_hour_meter' => floatval($_POST['initial_hour_meter']),
                    'final_hour_meter' => !empty($_POST['final_hour_meter']) ? floatval($_POST['final_hour_meter']) : null,
                    'description' => trim($_POST['description']),
                    'created_by' => $_SESSION['user_id']
                ];
                
                $newId = $occurrenceModel->create($data);
                if ($newId) {
                    $auditLog->logInsert('occurrences', $newId, $data, $_SESSION['user_id']);
                    $message = 'Ocorrência criada com sucesso!';
                    $messageType = 'success';
                } else {
                    $message = 'Erro ao criar ocorrência.';
                    $messageType = 'error';
                }
                break;
                
            case 'finish':
                $id = intval($_POST['id']);
                
                // Obter dados antigos antes da atualização
                $oldOccurrence = $occurrenceModel->findById($id);
                
                $data = [
                    'end_datetime' => $_POST['end_date'] . ' ' . $_POST['end_time'],
                    'final_hour_meter' => floatval($_POST['final_hour_meter']),
                    'description' => trim($_POST['description'])
                ];
                
                if ($occurrenceModel->finish($id, $data)) {
                    $auditLog->logUpdate('occurrences', $id, $oldOccurrence, $data, $_SESSION['user_id']);
                    $message = 'Ocorrência finalizada com sucesso!';
                    $messageType = 'success';
                } else {
                    $message = 'Erro ao finalizar ocorrência.';
                    $messageType = 'error';
                }
                break;
                
            case 'update':
                $id = intval($_POST['id']);
                
                // Obter dados antigos antes da atualização
                $oldOccurrence = $occurrenceModel->findById($id);
                
                $data = [
                    'equipment_id' => intval($_POST['equipment_id']),
                    'occurrence_type_id' => intval($_POST['occurrence_type_id']),
                    'operator_id' => intval($_POST['operator_id']),
                    'start_datetime' => $_POST['start_date'] . ' ' . $_POST['start_time'],
                    'initial_hour_meter' => floatval($_POST['initial_hour_meter']),
                    'description' => trim($_POST['description'])
                ];
                
                // Se a ocorrência estiver finalizada, incluir dados de finalização
                if (!empty($_POST['end_date']) && !empty($_POST['end_time'])) {
                    $data['end_datetime'] = $_POST['end_date'] . ' ' . $_POST['end_time'];
                    $data['final_hour_meter'] = floatval($_POST['final_hour_meter']);
                }
                
                if ($occurrenceModel->update($id, $data)) {
                    $auditLog->logUpdate('occurrences', $id, $oldOccurrence, $data, $_SESSION['user_id']);
                    $message = 'Ocorrência atualizada com sucesso!';
                    $messageType = 'success';
                } else {
                    $message = 'Erro ao atualizar ocorrência.';
                    $messageType = 'error';
                }
                break;
        }
    } catch (Exception $e) {
        $message = 'Erro: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Buscar dados para filtros
$equipments = $equipmentModel->findActive();
$occurrenceTypes = $occurrenceTypeModel->findActive();
$operators = $userModel->findOperators();

// Aplicar filtros
$search = $_GET['search'] ?? '';
$equipment_filter = $_GET['equipment_id'] ?? '';
$type_filter = $_GET['type_id'] ?? '';
$operator_filter = $_GET['operator_id'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$conditions = [];
if ($search) {
    $conditions[] = "(e.tag LIKE '%$search%' OR ot.description LIKE '%$search%' OR o.description LIKE '%$search%')";
}
if ($equipment_filter) {
    $conditions['o.equipment_id'] = $equipment_filter;
}
if ($type_filter) {
    $conditions['o.occurrence_type_id'] = $type_filter;
}
if ($operator_filter) {
    $conditions['o.operator_id'] = $operator_filter;
}
if ($status_filter === 'open') {
    $conditions[] = "o.end_datetime IS NULL";
} elseif ($status_filter === 'closed') {
    $conditions[] = "o.end_datetime IS NOT NULL";
}

$dateRange = [];
if ($date_from) {
    $dateRange['start'] = $date_from;
}
if ($date_to) {
    $dateRange['end'] = $date_to;
}

// Verificar permissões para filtrar por frente
if (!$auth->hasPermission(ROLE_ADMIN) && !$auth->hasPermission(ROLE_SUPERVISOR)) {
    $userFrontId = $_SESSION['user_front_id'] ?? null;
    if ($userFrontId) {
        $conditions['e.front_id'] = $userFrontId;
    }
}

$occurrences = $occurrenceModel->findWithDetails($conditions, $dateRange);

include 'includes/header.php';
?>

<div class="space-y-6">
    <!-- Mensagens -->
    <?php if ($message): ?>
    <div class="p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <!-- Filtros e Ações -->
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6">
        <form method="GET" class="space-y-4">
            <!-- Primeira linha: Busca e Equipamento -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="lg:col-span-2">
                    <label class="block text-sm font-medium text-paynes-gray mb-1">Buscar</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Buscar por tag, tipo ou descrição..." 
                           class="w-full px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                </div>
                <div>
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
            </div>
            
            <!-- Segunda linha: Tipo, Operador e Status -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-paynes-gray mb-1">Tipo de Ocorrência</label>
                    <select name="type_id" class="w-full px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                        <option value="">Todos os tipos</option>
                        <?php foreach ($occurrenceTypes as $type): ?>
                            <option value="<?php echo $type['id']; ?>" <?php echo $type_filter == $type['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['description']); ?>
                            </option>
                        <?php endforeach; ?>
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
                    <label class="block text-sm font-medium text-paynes-gray mb-1">Status</label>
                    <select name="status" class="w-full px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                        <option value="">Todos os status</option>
                        <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Em Andamento</option>
                        <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Finalizadas</option>
                    </select>
                </div>
            </div>
            
            <!-- Terceira linha: Datas e Ações -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-paynes-gray mb-1">Data Início</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" 
                           class="w-full px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-paynes-gray mb-1">Data Fim</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" 
                           class="w-full px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                </div>
                
                <div>
                    <button type="submit" class="w-full px-4 py-2 bg-paynes-gray text-white rounded-lg hover:bg-prussian-blue transition-colors">
                        <i class="fas fa-search mr-2"></i>Filtrar
                    </button>
                </div>
                
                <div>
                    <button type="button" onclick="openModal('createModal')" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Nova Ocorrência
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabela de Ocorrências -->
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-silver-lake-blue">
                <thead class="bg-eggshell">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Equipamento</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Tipo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Operador</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Início</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Fim</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Duração</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Horas</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-silver-lake-blue">
                    <?php if (empty($occurrences)): ?>
                        <tr>
                            <td colspan="9" class="px-6 py-4 text-center text-paynes-gray">
                                Nenhuma ocorrência encontrada
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($occurrences as $occurrence): ?>
                            <tr class="hover:bg-eggshell">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-rich-black">
                                        <?php echo htmlspecialchars($occurrence['equipment_tag']); ?>
                                    </div>
                                    <div class="text-xs text-paynes-gray">
                                        <?php echo htmlspecialchars($occurrence['equipment_description']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-rich-black">
                                        <?php echo htmlspecialchars($occurrence['type_description']); ?>
                                    </div>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php 
                                        switch($occurrence['type_category']) {
                                            case 'PRODUCAO': echo 'bg-green-100 text-green-800'; break;
                                            case 'MANUTENCAO': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'PARADA': echo 'bg-red-100 text-red-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?php echo htmlspecialchars($occurrence['type_category']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-rich-black">
                                        <?php echo htmlspecialchars($occurrence['operator_name']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-rich-black">
                                        <?php echo date('d/m/Y', strtotime($occurrence['start_datetime'])); ?>
                                    </div>
                                    <div class="text-xs text-paynes-gray">
                                        <?php echo date('H:i', strtotime($occurrence['start_datetime'])); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($occurrence['end_datetime']): ?>
                                        <div class="text-sm text-rich-black">
                                            <?php echo date('d/m/Y', strtotime($occurrence['end_datetime'])); ?>
                                        </div>
                                        <div class="text-xs text-paynes-gray">
                                            <?php echo date('H:i', strtotime($occurrence['end_datetime'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-sm text-paynes-gray">Em andamento</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-rich-black">
                                        <?php 
                                        if ($occurrence['duration_hours']) {
                                            echo number_format($occurrence['duration_hours'], 2) . 'h';
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-rich-black">
                                        <?php echo number_format($occurrence['initial_hour_meter'], 2); ?>
                                        <?php if ($occurrence['final_hour_meter']): ?>
                                            → <?php echo number_format($occurrence['final_hour_meter'], 2); ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($occurrence['hours_worked']): ?>
                                        <div class="text-xs text-paynes-gray">
                                            <?php echo number_format($occurrence['hours_worked'], 2); ?>h trabalhadas
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $occurrence['end_datetime'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo $occurrence['end_datetime'] ? 'Finalizada' : 'Em Andamento'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <button onclick="viewOccurrence(<?php echo htmlspecialchars(json_encode($occurrence)); ?>)" 
                                            class="text-blue-600 hover:text-blue-900" title="Visualizar">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if (!$occurrence['end_datetime']): ?>
                                        <button onclick="finishOccurrence(<?php echo htmlspecialchars(json_encode($occurrence)); ?>)" 
                                                class="text-green-600 hover:text-green-900" title="Finalizar">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($auth->hasPermission(ROLE_ADMIN) || $auth->hasPermission(ROLE_SUPERVISOR)): ?>
                                        <button onclick="editOccurrence(<?php echo htmlspecialchars(json_encode($occurrence)); ?>)" 
                                                class="text-orange-600 hover:text-orange-900" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Criar Ocorrência -->
<div id="createModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-2xl w-full max-h-screen overflow-y-auto">
            <div class="p-6 border-b border-silver-lake-blue">
                <h3 class="text-lg font-semibold text-rich-black">Nova Ocorrência</h3>
            </div>
            <form method="POST" id="createForm">
                <div class="p-6 space-y-4">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-paynes-gray mb-2">Equipamento *</label>
                            <select name="equipment_id" id="createEquipmentId" required 
                                    class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                                <option value="">Selecione um equipamento</option>
                                <?php foreach ($equipments as $equipment): ?>
                                    <option value="<?php echo $equipment['id']; ?>" data-current-hour="<?php echo $equipment['current_hours']; ?>">
                                        <?php echo htmlspecialchars($equipment['tag'] . ' - ' . $equipment['description']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-paynes-gray mb-2">Tipo de Ocorrência *</label>
                            <select name="occurrence_type_id" required 
                                    class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                                <option value="">Selecione um tipo</option>
                                <?php foreach ($occurrenceTypes as $type): ?>
                                    <option value="<?php echo $type['id']; ?>">
                                        <?php echo htmlspecialchars($type['description'] . ' (' . $type['category'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-paynes-gray mb-2">Operador *</label>
                            <select name="operator_id" required 
                                    class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                                <option value="">Selecione um operador</option>
                                <?php foreach ($operators as $operator): ?>
                                    <option value="<?php echo $operator['id']; ?>">
                                        <?php echo htmlspecialchars($operator['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-paynes-gray mb-2">Horímetro Inicial *</label>
                            <input type="number" name="initial_hour_meter" id="createInitialHour" step="0.01" required 
                                   class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-paynes-gray mb-2">Data de Início *</label>
                            <input type="date" name="start_date" required 
                                   class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-paynes-gray mb-2">Hora de Início *</label>
                            <input type="time" name="start_time" required 
                                   class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-paynes-gray mb-2">Data de Fim</label>
                            <input type="date" name="end_date" 
                                   class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-paynes-gray mb-2">Hora de Fim</label>
                            <input type="time" name="end_time" 
                                   class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Horímetro Final</label>
                        <input type="number" name="final_hour_meter" step="0.01" 
                               class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Descrição</label>
                        <textarea name="description" rows="3" 
                                  class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray"></textarea>
                    </div>
                </div>
                
                <div class="p-6 border-t border-silver-lake-blue flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('createModal')" 
                            class="px-4 py-2 text-paynes-gray border border-silver-lake-blue rounded-lg hover:bg-eggshell transition-colors">
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Finalizar Ocorrência -->
<div id="finishModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-md w-full">
            <div class="p-6 border-b border-silver-lake-blue">
                <h3 class="text-lg font-semibold text-rich-black">Finalizar Ocorrência</h3>
            </div>
            <form method="POST" id="finishForm">
                <div class="p-6 space-y-4">
                    <input type="hidden" name="action" value="finish">
                    <input type="hidden" name="id" id="finishOccurrenceId">
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Data de Fim *</label>
                        <input type="date" name="end_date" id="finishEndDate" required 
                               class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Hora de Fim *</label>
                        <input type="time" name="end_time" id="finishEndTime" required 
                               class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Horímetro Final *</label>
                        <input type="number" name="final_hour_meter" id="finishFinalHour" step="0.01" required 
                               class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Descrição Final</label>
                        <textarea name="description" id="finishDescription" rows="3" 
                                  class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray"></textarea>
                    </div>
                </div>
                
                <div class="p-6 border-t border-silver-lake-blue flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('finishModal')" 
                            class="px-4 py-2 text-paynes-gray border border-silver-lake-blue rounded-lg hover:bg-eggshell transition-colors">
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                        Finalizar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Visualizar/Editar -->
<div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-2xl w-full max-h-screen overflow-y-auto">
            <div class="p-6 border-b border-silver-lake-blue">
                <h3 class="text-lg font-semibold text-rich-black" id="viewModalTitle">Detalhes da Ocorrência</h3>
            </div>
            <form method="POST" id="viewForm">
                <div class="p-6 space-y-4">
                    <input type="hidden" name="action" id="viewAction" value="view">
                    <input type="hidden" name="id" id="viewOccurrenceId">
                    
                    <div id="viewContent">
                        <!-- Conteúdo será preenchido via JavaScript -->
                    </div>
                </div>
                
                <div class="p-6 border-t border-silver-lake-blue flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('viewModal')" 
                            class="px-4 py-2 text-paynes-gray border border-silver-lake-blue rounded-lg hover:bg-eggshell transition-colors">
                        Fechar
                    </button>
                    <button type="button" id="editButton" onclick="enableEdit()" 
                            class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors hidden">
                        Editar
                    </button>
                    <button type="submit" id="saveButton" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors hidden">
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Definir data e hora atuais como padrão
document.addEventListener('DOMContentLoaded', function() {
    const now = new Date();
    const today = now.toISOString().split('T')[0];
    const currentTime = now.toTimeString().slice(0, 5);
    
    document.querySelector('input[name="start_date"]').value = today;
    document.querySelector('input[name="start_time"]').value = currentTime;
});

// Atualizar horímetro inicial baseado no equipamento selecionado
document.getElementById('createEquipmentId').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const currentHour = selectedOption.getAttribute('data-current-hour');
    if (currentHour) {
        document.getElementById('createInitialHour').value = currentHour;
    }
});

function openModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

function finishOccurrence(occurrence) {
    document.getElementById('finishOccurrenceId').value = occurrence.id;
    
    const now = new Date();
    document.getElementById('finishEndDate').value = now.toISOString().split('T')[0];
    document.getElementById('finishEndTime').value = now.toTimeString().slice(0, 5);
    document.getElementById('finishFinalHour').value = occurrence.initial_hour_meter;
    document.getElementById('finishDescription').value = occurrence.description || '';
    
    openModal('finishModal');
}

function viewOccurrence(occurrence) {
    document.getElementById('viewOccurrenceId').value = occurrence.id;
    document.getElementById('viewAction').value = 'view';
    document.getElementById('viewModalTitle').textContent = 'Detalhes da Ocorrência';
    
    const canEdit = <?php echo ($auth->hasPermission(ROLE_ADMIN) || $auth->hasPermission(ROLE_SUPERVISOR)) ? 'true' : 'false'; ?>;
    
    let content = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-paynes-gray mb-2">Equipamento</label>
                <div class="px-3 py-2 bg-gray-50 rounded-lg">${occurrence.equipment_tag} - ${occurrence.equipment_description}</div>
            </div>
            <div>
                <label class="block text-sm font-medium text-paynes-gray mb-2">Tipo</label>
                <div class="px-3 py-2 bg-gray-50 rounded-lg">${occurrence.type_description} (${occurrence.type_category})</div>
            </div>
            <div>
                <label class="block text-sm font-medium text-paynes-gray mb-2">Operador</label>
                <div class="px-3 py-2 bg-gray-50 rounded-lg">${occurrence.operator_name}</div>
            </div>
            <div>
                <label class="block text-sm font-medium text-paynes-gray mb-2">Horímetro Inicial</label>
                <div class="px-3 py-2 bg-gray-50 rounded-lg">${parseFloat(occurrence.initial_hour_meter).toFixed(2)}</div>
            </div>
            <div>
                <label class="block text-sm font-medium text-paynes-gray mb-2">Data/Hora Início</label>
                <div class="px-3 py-2 bg-gray-50 rounded-lg">${new Date(occurrence.start_datetime).toLocaleString('pt-BR')}</div>
            </div>
            <div>
                <label class="block text-sm font-medium text-paynes-gray mb-2">Data/Hora Fim</label>
                <div class="px-3 py-2 bg-gray-50 rounded-lg">${occurrence.end_datetime ? new Date(occurrence.end_datetime).toLocaleString('pt-BR') : 'Em andamento'}</div>
            </div>
        </div>
        
        ${occurrence.final_hour_meter ? `
        <div>
            <label class="block text-sm font-medium text-paynes-gray mb-2">Horímetro Final</label>
            <div class="px-3 py-2 bg-gray-50 rounded-lg">${parseFloat(occurrence.final_hour_meter).toFixed(2)}</div>
        </div>
        ` : ''}
        
        ${occurrence.duration_hours ? `
        <div>
            <label class="block text-sm font-medium text-paynes-gray mb-2">Duração</label>
            <div class="px-3 py-2 bg-gray-50 rounded-lg">${parseFloat(occurrence.duration_hours).toFixed(2)} horas</div>
        </div>
        ` : ''}
        
        ${occurrence.hours_worked ? `
        <div>
            <label class="block text-sm font-medium text-paynes-gray mb-2">Horas Trabalhadas</label>
            <div class="px-3 py-2 bg-gray-50 rounded-lg">${parseFloat(occurrence.hours_worked).toFixed(2)} horas</div>
        </div>
        ` : ''}
        
        <div>
            <label class="block text-sm font-medium text-paynes-gray mb-2">Descrição</label>
            <div class="px-3 py-2 bg-gray-50 rounded-lg min-h-[60px]">${occurrence.description || 'Nenhuma descrição'}</div>
        </div>
    `;
    
    document.getElementById('viewContent').innerHTML = content;
    
    if (canEdit) {
        document.getElementById('editButton').classList.remove('hidden');
    } else {
        document.getElementById('editButton').classList.add('hidden');
    }
    
    document.getElementById('saveButton').classList.add('hidden');
    
    openModal('viewModal');
}

function editOccurrence(occurrence) {
    viewOccurrence(occurrence);
    enableEdit();
}

function enableEdit() {
    // Implementar lógica de edição aqui
    document.getElementById('viewAction').value = 'update';
    document.getElementById('editButton').classList.add('hidden');
    document.getElementById('saveButton').classList.remove('hidden');
    // Converter campos para inputs editáveis
}

// Fechar modais ao clicar fora
document.querySelectorAll('[id$="Modal"]').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal(this.id);
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>