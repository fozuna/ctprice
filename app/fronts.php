<?php
/**
 * Gerenciamento de Frentes
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

$pageTitle = 'Frentes de Serviço';
$frontModel = new Front($db);
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
                    'name' => trim($_POST['name']),
                    'description' => trim($_POST['description']),
                    'active' => 1
                ];
                
                $newId = $frontModel->create($data);
                if ($newId) {
                    $auditLog->logInsert('fronts', $newId, $data, $_SESSION['user_id']);
                    $message = 'Frente criada com sucesso!';
                    $messageType = 'success';
                } else {
                    $message = 'Erro ao criar frente.';
                    $messageType = 'error';
                }
                break;
                
            case 'update':
                $id = intval($_POST['id']);
                
                // Obter dados antigos antes da atualização
                $oldFront = $frontModel->findById($id);
                
                $data = [
                    'code' => trim($_POST['code']),
                    'name' => trim($_POST['name']),
                    'description' => trim($_POST['description'])
                ];
                
                if ($frontModel->update($id, $data)) {
                    $auditLog->logUpdate('fronts', $id, $oldFront, $data, $_SESSION['user_id']);
                    $message = 'Frente atualizada com sucesso!';
                    $messageType = 'success';
                } else {
                    $message = 'Erro ao atualizar frente.';
                    $messageType = 'error';
                }
                break;
                
            case 'toggle_status':
                $id = intval($_POST['id']);
                $front = $frontModel->findById($id);
                if ($front) {
                    $newStatus = $front['active'] ? 0 : 1;
                    $newData = ['active' => $newStatus];
                    if ($frontModel->update($id, $newData)) {
                        $auditLog->logUpdate('fronts', $id, $front, $newData, $_SESSION['user_id']);
                        $message = $newStatus ? 'Frente ativada!' : 'Frente desativada!';
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

// Buscar frentes
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$conditions = [];
if ($search) {
    $conditions[] = "(code LIKE '%$search%' OR name LIKE '%$search%' OR description LIKE '%$search%')";
}
if ($status_filter !== '') {
    $conditions['active'] = $status_filter;
}

$fronts = $frontModel->findAll($conditions);

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
                       placeholder="Buscar por código, nome ou descrição..." 
                       class="px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                
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
                <i class="fas fa-plus mr-2"></i>Nova Frente
            </button>
        </div>
    </div>

    <!-- Tabela de Frentes -->
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-silver-lake-blue">
                <thead class="bg-eggshell">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Código</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Nome</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Descrição</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Criado em</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-silver-lake-blue">
                    <?php if (empty($fronts)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-paynes-gray">
                                Nenhuma frente encontrada
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($fronts as $front): ?>
                            <tr class="hover:bg-eggshell">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium">
                                        <a href="front-details.php?id=<?php echo $front['id']; ?>" 
                                           class="text-silver-lake-blue hover:text-paynes-gray hover:underline font-semibold transition-colors">
                                            <?php echo htmlspecialchars($front['code']); ?>
                                        </a>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-rich-black">
                                        <?php echo htmlspecialchars($front['name']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-rich-black">
                                        <?php echo htmlspecialchars($front['description'] ?? ''); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $front['active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $front['active'] ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-paynes-gray">
                                        <?php echo date('d/m/Y H:i', strtotime($front['created_at'])); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <button onclick="editFront(<?php echo htmlspecialchars(json_encode($front)); ?>)" 
                                            class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Tem certeza?')">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?php echo $front['id']; ?>">
                                        <button type="submit" class="<?php echo $front['active'] ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900'; ?>">
                                            <i class="fas <?php echo $front['active'] ? 'fa-ban' : 'fa-check'; ?>"></i>
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
                <h3 class="text-lg font-semibold text-rich-black" id="modalTitle">Nova Frente</h3>
            </div>
            <form method="POST" id="frontForm">
                <div class="p-6 space-y-4">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="frontId">
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Código *</label>
                        <input type="text" name="code" id="frontCode" required 
                               class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Nome *</label>
                        <input type="text" name="name" id="frontName" required 
                               class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Descrição</label>
                        <textarea name="description" id="frontDescription" rows="3"
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
    document.getElementById('frontForm').reset();
    document.getElementById('formAction').value = 'create';
    document.getElementById('modalTitle').textContent = 'Nova Frente';
    document.getElementById('frontId').value = '';
}

function editFront(front) {
    document.getElementById('formAction').value = 'update';
    document.getElementById('modalTitle').textContent = 'Editar Frente';
    document.getElementById('frontId').value = front.id;
    document.getElementById('frontCode').value = front.code;
    document.getElementById('frontName').value = front.name;
    document.getElementById('frontDescription').value = front.description || '';
    
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