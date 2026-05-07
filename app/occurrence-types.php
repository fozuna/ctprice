<?php
/**
 * Gerenciamento de Tipos de Ocorrência
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

$pageTitle = 'Tipos de Ocorrência';
$occurrenceTypeModel = new OccurrenceType($db);
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
                    'code' => trim($_POST['code']),
                    'description' => trim($_POST['description']),
                    'category' => trim($_POST['category']),
                    'affects_hour_meter' => isset($_POST['affects_hour_meter']) ? 1 : 0,
                    'active' => 1
                ];
                
                $newId = $occurrenceTypeModel->create($data);
                if ($newId) {
                    $auditLog->logInsert('occurrence_types', $newId, $data, $_SESSION['user_id']);
                    $message = 'Tipo de ocorrência criado com sucesso!';
                    $messageType = 'success';
                } else {
                    $message = 'Erro ao criar tipo de ocorrência.';
                    $messageType = 'error';
                }
                break;
                
            case 'update':
                $id = intval($_POST['id']);
                
                // Obter dados antigos antes da atualização
                $oldOccurrenceType = $occurrenceTypeModel->findById($id);
                
                $data = [
                    'code' => trim($_POST['code']),
                    'description' => trim($_POST['description']),
                    'category' => trim($_POST['category']),
                    'affects_hour_meter' => isset($_POST['affects_hour_meter']) ? 1 : 0
                ];
                
                if ($occurrenceTypeModel->update($id, $data)) {
                    $auditLog->logUpdate('occurrence_types', $id, $oldOccurrenceType, $data, $_SESSION['user_id']);
                    $message = 'Tipo de ocorrência atualizado com sucesso!';
                    $messageType = 'success';
                } else {
                    $message = 'Erro ao atualizar tipo de ocorrência.';
                    $messageType = 'error';
                }
                break;
                
            case 'toggle_status':
                $id = intval($_POST['id']);
                $occurrenceType = $occurrenceTypeModel->findById($id);
                if ($occurrenceType) {
                    $newStatus = $occurrenceType['active'] ? 0 : 1;
                    $newData = ['active' => $newStatus];
                    if ($occurrenceTypeModel->update($id, $newData)) {
                        $auditLog->logUpdate('occurrence_types', $id, $occurrenceType, $newData, $_SESSION['user_id']);
                        $message = $newStatus ? 'Tipo de ocorrência ativado!' : 'Tipo de ocorrência desativado!';
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

// Buscar tipos de ocorrência
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';

$conditions = [];
if ($search) {
    $conditions[] = "(code LIKE '%$search%' OR description LIKE '%$search%')";
}
if ($category_filter) {
    $conditions['category'] = $category_filter;
}
if ($status_filter !== '') {
    $conditions['active'] = $status_filter;
}

$occurrenceTypes = $occurrenceTypeModel->findAll($conditions);
$categories = $occurrenceTypeModel->getCategories();

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
                       placeholder="Buscar por código ou descrição..." 
                       class="px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                
                <select name="category" class="px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                    <option value="">Todas as categorias</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category); ?>
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
                <i class="fas fa-plus mr-2"></i>Novo Tipo
            </button>
        </div>
    </div>

    <!-- Tabela de Tipos de Ocorrência -->
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-silver-lake-blue">
                <thead class="bg-eggshell">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Código</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Descrição</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Categoria</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Afeta Horímetro</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-silver-lake-blue">
                    <?php if (empty($occurrenceTypes)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-paynes-gray">
                                Nenhum tipo de ocorrência encontrado
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($occurrenceTypes as $type): ?>
                            <tr class="hover:bg-eggshell">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-rich-black">
                                        <?php echo htmlspecialchars($type['code']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-rich-black">
                                        <?php echo htmlspecialchars($type['description']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php 
                                        switch($type['category']) {
                                            case 'Produção': echo 'bg-green-100 text-green-800'; break;
                                            case 'Manutenção': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'Parada': echo 'bg-red-100 text-red-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?php echo htmlspecialchars($type['category']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $type['affects_hour_meter'] ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo $type['affects_hour_meter'] ? 'Sim' : 'Não'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $type['active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $type['active'] ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <button onclick="editOccurrenceType(<?php echo htmlspecialchars(json_encode($type)); ?>)" 
                                            class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Tem certeza?')">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?php echo $type['id']; ?>">
                                        <button type="submit" class="<?php echo $type['active'] ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900'; ?>">
                                            <i class="fas <?php echo $type['active'] ? 'fa-ban' : 'fa-check'; ?>"></i>
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
                <h3 class="text-lg font-semibold text-rich-black" id="modalTitle">Novo Tipo de Ocorrência</h3>
            </div>
            <form method="POST" id="occurrenceTypeForm">
                <div class="p-6 space-y-4">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="occurrenceTypeId">
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Código *</label>
                        <input type="text" name="code" id="occurrenceTypeCode" required 
                               class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Descrição *</label>
                        <input type="text" name="description" id="occurrenceTypeDescription" required 
                               class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Categoria *</label>
                        <select name="category" id="occurrenceTypeCategory" required 
                                class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                            <option value="">Selecione uma categoria</option>
                            <option value="Produção">Produção</option>
                            <option value="Manutenção">Manutenção</option>
                            <option value="Parada">Parada</option>
                            <option value="Transporte">Transporte</option>
                            <option value="Outros">Outros</option>
                        </select>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" name="affects_hour_meter" id="occurrenceTypeAffectsHourMeter" 
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-silver-lake-blue rounded">
                        <label for="occurrenceTypeAffectsHourMeter" class="ml-2 block text-sm text-paynes-gray">
                            Afeta horímetro do equipamento
                        </label>
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
    document.getElementById('occurrenceTypeForm').reset();
    document.getElementById('formAction').value = 'create';
    document.getElementById('modalTitle').textContent = 'Novo Tipo de Ocorrência';
    document.getElementById('occurrenceTypeId').value = '';
}

function editOccurrenceType(type) {
    document.getElementById('formAction').value = 'update';
    document.getElementById('modalTitle').textContent = 'Editar Tipo de Ocorrência';
    document.getElementById('occurrenceTypeId').value = type.id;
    document.getElementById('occurrenceTypeCode').value = type.code;
    document.getElementById('occurrenceTypeDescription').value = type.description;
    document.getElementById('occurrenceTypeCategory').value = type.category;
    document.getElementById('occurrenceTypeAffectsHourMeter').checked = type.affects_hour_meter == 1;
    
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