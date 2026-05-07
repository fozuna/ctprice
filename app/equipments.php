<?php
/**
 * Gerenciamento de Equipamentos
 * Sistema BDO - Controle de Maquinários
 */

require_once 'config/config.php';

// Verificar autenticação e permissão
$database = Database::getInstance();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn() || !$auth->hasPermission(ROLE_LIDER)) {
    header('Location: dashboard.php');
    exit();
}

$pageTitle = 'Equipamentos';
$equipmentModel = new Equipment($db);
$frontModel = new Front($db);
$auditLog = new AuditLog($db);

// Buscar fases para o formulário
$fasesQuery = $db->prepare("SELECT * FROM fases WHERE active = 1 ORDER BY nome");
$fasesQuery->execute();
$fases = $fasesQuery->fetchAll();

$message = '';
$messageType = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create':
                $data = [
                    'tag' => trim($_POST['tag']),
                    'description' => trim($_POST['description']),
                    'front_id' => $_POST['front_id'],
                    'fase_id' => $_POST['fase_id'] ?? null,
                    'current_hour_meter' => floatval($_POST['current_hour_meter']),
                    'active' => 1
                ];
                
                $newId = $equipmentModel->create($data);
                if ($newId) {
                    $auditLog->logInsert('equipments', $newId, $data, $_SESSION['user_id']);
                    $message = 'Equipamento criado com sucesso!';
                    $messageType = 'success';
                } else {
                    $message = 'Erro ao criar equipamento.';
                    $messageType = 'error';
                }
                break;
                
            case 'update':
                $id = intval($_POST['id']);
                
                // Obter dados antigos antes da atualização
                $oldEquipment = $equipmentModel->findById($id);
                
                $data = [
                    'tag' => trim($_POST['tag']),
                    'description' => trim($_POST['description']),
                    'front_id' => $_POST['front_id'],
                    'fase_id' => $_POST['fase_id'] ?? null,
                    'current_hours' => floatval($_POST['current_hour_meter'])
                ];
                
                if ($equipmentModel->update($id, $data)) {
                    $auditLog->logUpdate('equipments', $id, $oldEquipment, $data, $_SESSION['user_id']);
                    $message = 'Equipamento atualizado com sucesso!';
                    $messageType = 'success';
                } else {
                    $message = 'Erro ao atualizar equipamento.';
                    $messageType = 'error';
                }
                break;
                
            case 'toggle_status':
                $id = intval($_POST['id']);
                $equipment = $equipmentModel->findById($id);
                if ($equipment) {
                    $newStatus = $equipment['active'] ? 0 : 1;
                    $newData = ['active' => $newStatus];
                    if ($equipmentModel->update($id, $newData)) {
                        $auditLog->logUpdate('equipments', $id, $equipment, $newData, $_SESSION['user_id']);
                        $message = $newStatus ? 'Equipamento ativado!' : 'Equipamento desativado!';
                        $messageType = 'success';
                    }
                }
                break;
        }
    } catch (Exception $e) {
        $message = 'Erro: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Buscar equipamentos
$search = $_GET['search'] ?? '';
$front_filter = $_GET['front_id'] ?? '';
$status_filter = $_GET['status'] ?? '';

$conditions = [];
if ($search) {
    $conditions[] = "(e.tag LIKE '%$search%' OR e.description LIKE '%$search%')";
}
if ($front_filter) {
    $conditions['e.front_id'] = $front_filter;
}
if ($status_filter !== '') {
    $conditions['e.active'] = $status_filter;
}

$equipments = $equipmentModel->findWithFronts($conditions);
$fronts = $frontModel->findActive();

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
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
            <!-- Filtros -->
            <form method="GET" class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Buscar por tag ou descrição..." 
                       class="px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                
                <select name="front_id" class="px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                    <option value="">Todas as frentes</option>
                    <?php foreach ($fronts as $front): ?>
                        <option value="<?php echo $front['id']; ?>" <?php echo $front_filter == $front['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($front['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="status" class="px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                    <option value="">Todos os status</option>
                    <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Ativo</option>
                    <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inativo</option>
                </select>
                
                <button type="submit" class="px-4 py-2 bg-paynes-gray text-white rounded-lg hover:bg-prussian-blue transition-colors">
                    <i class="fas fa-search mr-2"></i>Filtrar
                </button>
            </form>
            
            <!-- Botão Novo -->
            <button onclick="openModal('createModal')" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-plus mr-2"></i>Novo Equipamento
            </button>
        </div>
    </div>

    <!-- Tabela de Equipamentos -->
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-silver-lake-blue">
                <thead class="bg-eggshell">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Tag</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Descrição</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Frente</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Fase</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Produtividade</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-silver-lake-blue">
                    <?php if (empty($equipments)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-paynes-gray">
                                Nenhum equipamento encontrado
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($equipments as $equipment): ?>
                            <tr class="hover:bg-eggshell">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium">
                                        <a href="equipment-details.php?id=<?php echo $equipment['id']; ?>" 
                                           class="text-silver-lake-blue hover:text-paynes-gray hover:underline font-medium transition-colors">
                                            <?php echo htmlspecialchars($equipment['tag']); ?>
                                        </a>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-rich-black">
                                        <?php echo htmlspecialchars($equipment['description']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-rich-black">
                                        <?php echo htmlspecialchars($equipment['front_name'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-rich-black">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                                            <?php echo htmlspecialchars($equipment['fase_name'] ?? 'N/A'); ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-rich-black">
                                        <?php echo number_format($equipment['current_hour_meter'], 1); ?>h
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $equipment['active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $equipment['active'] ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <button onclick="editEquipment(<?php echo htmlspecialchars(json_encode($equipment)); ?>)" 
                                            class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Tem certeza?')">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?php echo $equipment['id']; ?>">
                                        <button type="submit" class="<?php echo $equipment['active'] ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900'; ?>">
                                            <i class="fas <?php echo $equipment['active'] ? 'fa-ban' : 'fa-check'; ?>"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Criar/Editar -->
<div id="createModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-md w-full">
            <div class="p-6 border-b border-silver-lake-blue">
                <h3 class="text-lg font-semibold text-rich-black" id="modalTitle">Novo Equipamento</h3>
            </div>
            <form method="POST" id="equipmentForm">
                <div class="p-6 space-y-4">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="equipmentId">
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Tag *</label>
                        <input type="text" name="tag" id="equipmentTag" required 
                               class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Descrição *</label>
                        <input type="text" name="description" id="equipmentDescription" required 
                               class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Frente *</label>
                        <select name="front_id" id="equipmentFrontId" required 
                                class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                            <option value="">Selecione uma frente</option>
                            <?php foreach ($fronts as $front): ?>
                                <option value="<?php echo $front['id']; ?>">
                                    <?php echo htmlspecialchars($front['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Fase</label>
                        <select name="fase_id" id="equipmentFaseId" 
                                class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                            <option value="">Selecione uma fase</option>
                            <?php foreach ($fases as $fase): ?>
                                <option value="<?php echo $fase['id']; ?>">
                                    <?php echo htmlspecialchars($fase['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Horímetro Atual</label>
                        <input type="number" name="current_hour_meter" id="equipmentHourMeter" step="0.1" min="0" value="0"
                               class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
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

<script>
function openModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
    if (modalId === 'createModal') {
        resetForm();
    }
}

function resetForm() {
    document.getElementById('equipmentForm').reset();
    document.getElementById('formAction').value = 'create';
    document.getElementById('modalTitle').textContent = 'Novo Equipamento';
    document.getElementById('equipmentId').value = '';
}

function editEquipment(equipment) {
    document.getElementById('formAction').value = 'update';
    document.getElementById('modalTitle').textContent = 'Editar Equipamento';
    document.getElementById('equipmentId').value = equipment.id;
    document.getElementById('equipmentTag').value = equipment.tag;
    document.getElementById('equipmentDescription').value = equipment.description;
    document.getElementById('equipmentFrontId').value = equipment.front_id;
    document.getElementById('equipmentFaseId').value = equipment.fase_id || '';
    document.getElementById('equipmentHourMeter').value = equipment.current_hours;
    
    openModal('createModal');
}

// Fechar modal ao clicar fora
document.getElementById('createModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal('createModal');
    }
});
</script>

<?php include 'includes/footer.php'; ?>