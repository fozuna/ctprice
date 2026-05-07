<?php
/**
 * Mobilização de Equipamentos
 * Sistema BDO - Controle de Maquinários
 */

require_once 'config/config.php';
require_once 'classes/Auth.php';
require_once 'classes/EquipmentMobilization.php';
require_once 'classes/AuditLog.php';
require_once 'models/Equipment.php';
require_once 'models/Front.php';
require_once 'models/User.php';

// Verificar autenticação e permissão
$database = Database::getInstance();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn() || !$auth->hasPermission(ROLE_LIDER)) {
    header('Location: dashboard.php');
    exit();
}

$pageTitle = 'Mobilização de Equipamentos';
$mobilizationModel = new EquipmentMobilization($db);
$equipmentModel = new Equipment($db);
$frontModel = new Front($db);
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
                    'from_front_id' => !empty($_POST['from_front_id']) ? intval($_POST['from_front_id']) : null,
                    'to_front_id' => intval($_POST['to_front_id']),
                    'mobilization_date' => $_POST['mobilization_date'],
                    'mobilization_time' => $_POST['mobilization_time'] ?: '08:00:00',
                    'requested_by' => intval($_POST['requested_by']),
                    'reason' => trim($_POST['reason']),
                    'observations' => trim($_POST['observations']) ?: null,
                    'transport_type' => $_POST['transport_type'],
                    'transport_company' => trim($_POST['transport_company']) ?: null,
                    'transport_cost' => !empty($_POST['transport_cost']) ? floatval($_POST['transport_cost']) : null,
                    'status' => 'SOLICITADA',
                    'created_by' => $_SESSION['user_id']
                ];
                
                $newId = $mobilizationModel->create($data);
                if ($newId) {
                    $auditLog->logInsert('equipment_mobilizations', $newId, $data, $_SESSION['user_id']);
                    $message = 'Mobilização solicitada com sucesso!';
                    $messageType = 'success';
                } else {
                    $message = 'Erro ao solicitar mobilização.';
                    $messageType = 'error';
                }
                break;
                
            case 'approve':
                $id = intval($_POST['id']);
                $observations = trim($_POST['observations']) ?: null;
                
                if ($mobilizationModel->approve($id, $_SESSION['user_id'], $observations)) {
                    $auditLog->logUpdate('equipment_mobilizations', $id, ['status' => 'SOLICITADA'], ['status' => 'APROVADA'], $_SESSION['user_id']);
                    $message = 'Mobilização aprovada com sucesso!';
                    $messageType = 'success';
                } else {
                    $message = 'Erro ao aprovar mobilização.';
                    $messageType = 'error';
                }
                break;
                
            case 'start_transport':
                $id = intval($_POST['id']);
                $departureDateTime = $_POST['departure_datetime'];
                
                if ($mobilizationModel->startTransport($id, $departureDateTime, $_SESSION['user_id'])) {
                    $auditLog->logUpdate('equipment_mobilizations', $id, ['status' => 'APROVADA'], ['status' => 'EM_TRANSITO'], $_SESSION['user_id']);
                    $message = 'Transporte iniciado com sucesso!';
                    $messageType = 'success';
                } else {
                    $message = 'Erro ao iniciar transporte.';
                    $messageType = 'error';
                }
                break;
                
            case 'complete':
                $id = intval($_POST['id']);
                $arrivalDateTime = $_POST['arrival_datetime'];
                
                if ($mobilizationModel->complete($id, $arrivalDateTime, $_SESSION['user_id'])) {
                    $auditLog->logUpdate('equipment_mobilizations', $id, ['status' => 'EM_TRANSITO'], ['status' => 'CONCLUIDA'], $_SESSION['user_id']);
                    $message = 'Mobilização concluída com sucesso!';
                    $messageType = 'success';
                } else {
                    $message = 'Erro ao concluir mobilização.';
                    $messageType = 'error';
                }
                break;
                
            case 'cancel':
                $id = intval($_POST['id']);
                $observations = trim($_POST['observations']) ?: null;
                
                $oldMobilization = $mobilizationModel->findById($id);
                if ($mobilizationModel->cancel($id, $_SESSION['user_id'], $observations)) {
                    $auditLog->logUpdate('equipment_mobilizations', $id, ['status' => $oldMobilization['status']], ['status' => 'CANCELADA'], $_SESSION['user_id']);
                    $message = 'Mobilização cancelada com sucesso!';
                    $messageType = 'success';
                } else {
                    $message = 'Erro ao cancelar mobilização.';
                    $messageType = 'error';
                }
                break;
        }
    } catch (Exception $e) {
        $message = 'Erro: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Buscar dados para os formulários
$equipments = $equipmentModel->findAll(['active' => 1], 'tag ASC');
$fronts = $frontModel->findAll(['active' => 1], 'name ASC');
$users = $userModel->findAll(['active' => 1], 'name ASC');

// Filtros para listagem
$statusFilter = $_GET['status'] ?? '';
$equipmentFilter = $_GET['equipment'] ?? '';
$fromFrontFilter = $_GET['from_front'] ?? '';
$toFrontFilter = $_GET['to_front'] ?? '';
$dateFromFilter = $_GET['date_from'] ?? '';
$dateToFilter = $_GET['date_to'] ?? '';

// Buscar mobilizações com filtros
$mobilizations = [];
if ($statusFilter) {
    $mobilizations = $mobilizationModel->findByStatus($statusFilter);
} elseif ($equipmentFilter) {
    $mobilizations = $mobilizationModel->findByEquipment($equipmentFilter);
} elseif ($fromFrontFilter) {
    $mobilizations = $mobilizationModel->findByFromFront($fromFrontFilter);
} elseif ($toFrontFilter) {
    $mobilizations = $mobilizationModel->findByToFront($toFrontFilter);
} elseif ($dateFromFilter && $dateToFilter) {
    $mobilizations = $mobilizationModel->findByDateRange($dateFromFilter, $dateToFilter);
} else {
    $mobilizations = $mobilizationModel->findAllWithDetails([], 'mobilization_date DESC, created_at DESC');
}

// Estatísticas
$statistics = $mobilizationModel->getStatistics();

include 'includes/header.php';
?>

<div class="space-y-6">
    <!-- Mensagens -->
    <?php if ($message): ?>
    <div class="p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <!-- Cabeçalho da Página -->
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6">
        <div class="flex justify-between items-center">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                    <i class="fas fa-truck-moving text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-semibold text-rich-black">Mobilização de Equipamentos</h1>
                    <p class="text-paynes-gray mt-1">Gerencie e acompanhe as mobilizações de equipamentos</p>
                </div>
            </div>
            <a href="new-mobilization.php" 
               class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors flex items-center space-x-2">
                <i class="fas fa-plus"></i>
                <span>Nova Mobilização</span>
            </a>
        </div>
    </div>

    <!-- Estatísticas -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total de Mobilizações -->
        <div class="bg-white rounded-lg shadow-sm p-6 border border-silver-lake-blue">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                    <i class="fas fa-truck-moving text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-paynes-gray">Total de Mobilizações</p>
                    <p class="text-2xl font-semibold text-rich-black"><?php echo $statistics['total']; ?></p>
                </div>
            </div>
        </div>

        <!-- Pendentes -->
        <div class="bg-white rounded-lg shadow-sm p-6 border border-silver-lake-blue">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                    <i class="fas fa-clock text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-paynes-gray">Pendentes</p>
                    <p class="text-2xl font-semibold text-rich-black"><?php echo $statistics['solicitadas']; ?></p>
                </div>
            </div>
        </div>

        <!-- Em Trânsito -->
        <div class="bg-white rounded-lg shadow-sm p-6 border border-silver-lake-blue">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                    <i class="fas fa-shipping-fast text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-paynes-gray">Em Trânsito</p>
                    <p class="text-2xl font-semibold text-rich-black"><?php echo $statistics['em_transito']; ?></p>
                </div>
            </div>
        </div>

        <!-- Concluídas -->
        <div class="bg-white rounded-lg shadow-sm p-6 border border-silver-lake-blue">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-paynes-gray">Concluídas</p>
                    <p class="text-2xl font-semibold text-rich-black"><?php echo $statistics['concluidas']; ?></p>
                </div>
            </div>
        </div>
    </div>

        <!-- Filtros -->
        <div class="bg-white rounded-lg shadow-sm p-6 border border-silver-lake-blue">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                <h2 class="text-lg font-semibold text-rich-black">Filtros de Pesquisa</h2>
            </div>
            
            <form method="GET" class="mt-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Status</label>
                        <select name="status" class="w-full px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray focus:border-paynes-gray">
                            <option value="">Todos os status</option>
                            <option value="SOLICITADA" <?php echo $statusFilter === 'SOLICITADA' ? 'selected' : ''; ?>>🟡 Solicitada</option>
                            <option value="APROVADA" <?php echo $statusFilter === 'APROVADA' ? 'selected' : ''; ?>>🔵 Aprovada</option>
                            <option value="EM_TRANSITO" <?php echo $statusFilter === 'EM_TRANSITO' ? 'selected' : ''; ?>>🟣 Em Trânsito</option>
                            <option value="CONCLUIDA" <?php echo $statusFilter === 'CONCLUIDA' ? 'selected' : ''; ?>>🟢 Concluída</option>
                            <option value="CANCELADA" <?php echo $statusFilter === 'CANCELADA' ? 'selected' : ''; ?>>🔴 Cancelada</option>
                        </select>
                    </div>

                    <!-- Equipamento -->
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Equipamento</label>
                        <select name="equipment" class="w-full px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray focus:border-paynes-gray">
                            <option value="">Todos os equipamentos</option>
                            <?php foreach ($equipments as $equipment): ?>
                            <option value="<?php echo $equipment['id']; ?>" <?php echo $equipmentFilter == $equipment['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($equipment['tag']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Frente Origem -->
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Frente Origem</label>
                        <select name="from_front" class="w-full px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray focus:border-paynes-gray">
                            <option value="">Todas as frentes</option>
                            <?php foreach ($fronts as $front): ?>
                            <option value="<?php echo $front['id']; ?>" <?php echo $fromFrontFilter == $front['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($front['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Frente Destino -->
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Frente Destino</label>
                        <select name="to_front" class="w-full px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray focus:border-paynes-gray">
                            <option value="">Todas as frentes</option>
                            <?php foreach ($fronts as $front): ?>
                            <option value="<?php echo $front['id']; ?>" <?php echo $toFrontFilter == $front['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($front['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Data Início -->
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Data Início</label>
                        <input type="date" 
                               name="date_from" 
                               class="w-full px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray focus:border-paynes-gray" 
                               value="<?php echo htmlspecialchars($dateFromFilter); ?>">
                    </div>

                    <!-- Data Fim -->
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Data Fim</label>
                        <input type="date" 
                               name="date_to" 
                               class="w-full px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray focus:border-paynes-gray" 
                               value="<?php echo htmlspecialchars($dateToFilter); ?>">
                    </div>
                </div>

                <!-- Botões de Ação -->
                <div class="flex flex-col sm:flex-row sm:space-x-4 space-y-4 sm:space-y-0 mt-6">
                    <button type="submit" 
                            class="bg-paynes-gray hover:bg-rich-black text-white font-medium py-2 px-4 rounded-lg transition-colors flex items-center justify-center">
                        <i class="fas fa-search mr-2"></i>
                        Aplicar Filtros
                    </button>
                    <a href="equipment-mobilization.php" 
                       class="bg-silver-lake-blue hover:bg-paynes-gray text-white font-medium py-2 px-4 rounded-lg transition-colors flex items-center justify-center">
                        <i class="fas fa-eraser mr-2"></i>
                        Limpar Filtros
                    </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabela de Mobilizações -->
        <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
            <div class="px-6 py-4 border-b border-silver-lake-blue">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-rich-black">Lista de Mobilizações</h2>
                    <div class="text-sm text-paynes-gray">
                        <?php echo count($mobilizations); ?> mobilizações encontradas
                    </div>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <?php if (empty($mobilizations)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-search text-gray-300 text-6xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhuma mobilização encontrada</h3>
                    <p class="text-gray-500">Tente ajustar os filtros ou criar uma nova mobilização.</p>
                </div>
                <?php else: ?>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Equipamento</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Origem</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destino</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Solicitante</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($mobilizations as $mobilization): ?>
                        <tr class="hover:bg-gray-50 transition-colors duration-200 table-row">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">#<?php echo $mobilization['id']; ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                            <i class="fas fa-truck text-blue-600"></i>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($mobilization['equipment_tag']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($mobilization['equipment_description']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?php if ($mobilization['from_front_name']): ?>
                                        <i class="fas fa-map-marker-alt text-gray-400 mr-2"></i><?php echo htmlspecialchars($mobilization['from_front_name']); ?>
                                    <?php else: ?>
                                        <span class="text-gray-500 italic">
                                            <i class="fas fa-home text-gray-400 mr-2"></i>Primeira mobilização
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <i class="fas fa-flag-checkered text-gray-400 mr-2"></i><?php echo htmlspecialchars($mobilization['to_front_name']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <i class="fas fa-calendar text-gray-400 mr-2"></i><?php echo date('d/m/Y', strtotime($mobilization['mobilization_date'])); ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <i class="fas fa-clock text-gray-400 mr-2"></i><?php echo date('H:i', strtotime($mobilization['mobilization_time'])); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <i class="fas fa-user text-gray-400 mr-2"></i><?php echo htmlspecialchars($mobilization['requested_by_name']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $statusConfig = [
                                    'SOLICITADA' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'icon' => 'fas fa-clock', 'label' => 'Solicitada'],
                                    'APROVADA' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'icon' => 'fas fa-check', 'label' => 'Aprovada'],
                                    'EM_TRANSITO' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-800', 'icon' => 'fas fa-shipping-fast', 'label' => 'Em Trânsito'],
                                    'CONCLUIDA' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'icon' => 'fas fa-check-circle', 'label' => 'Concluída'],
                                    'CANCELADA' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'icon' => 'fas fa-times-circle', 'label' => 'Cancelada']
                                ];
                                $config = $statusConfig[$mobilization['status']];
                                ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php echo $config['bg'] . ' ' . $config['text']; ?>">
                                    <i class="<?php echo $config['icon']; ?> mr-1"></i>
                                    <?php echo $config['label']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <a href="mobilization-action.php?id=<?php echo $mobilization['id']; ?>&action=view" 
                                       class="text-blue-600 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 p-2 rounded-lg transition-colors duration-200 btn-action" 
                                       title="Visualizar">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <a href="edit-mobilization.php?id=<?php echo $mobilization['id']; ?>" 
                                       class="text-gray-600 hover:text-gray-900 bg-gray-50 hover:bg-gray-100 p-2 rounded-lg transition-colors duration-200 btn-action" 
                                       title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <?php if ($mobilization['status'] === 'SOLICITADA' && $auth->hasPermission(ROLE_SUPERVISOR)): ?>
                                    <a href="mobilization-action.php?id=<?php echo $mobilization['id']; ?>&action=approve" 
                                       class="text-green-600 hover:text-green-900 bg-green-50 hover:bg-green-100 p-2 rounded-lg transition-colors duration-200 btn-action" 
                                       title="Aprovar">
                                        <i class="fas fa-check"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($mobilization['status'] === 'APROVADA'): ?>
                                    <a href="mobilization-action.php?id=<?php echo $mobilization['id']; ?>&action=start_transport" 
                                       class="text-purple-600 hover:text-purple-900 bg-purple-50 hover:bg-purple-100 p-2 rounded-lg transition-colors duration-200 btn-action" 
                                       title="Iniciar Transporte">
                                        <i class="fas fa-truck"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($mobilization['status'] === 'EM_TRANSITO'): ?>
                                    <a href="mobilization-action.php?id=<?php echo $mobilization['id']; ?>&action=complete" 
                                       class="text-green-600 hover:text-green-900 bg-green-50 hover:bg-green-100 p-2 rounded-lg transition-colors duration-200 btn-action" 
                                       title="Concluir">
                                        <i class="fas fa-check-circle"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array($mobilization['status'], ['SOLICITADA', 'APROVADA'])): ?>
                                    <a href="mobilization-action.php?id=<?php echo $mobilization['id']; ?>&action=cancel" 
                                       class="text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 p-2 rounded-lg transition-colors duration-200 btn-action" 
                                       title="Cancelar">
                                        <i class="fas fa-times"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>