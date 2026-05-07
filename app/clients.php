<?php
require_once 'config/config.php';

// Configurar conexão com banco
$database = Database::getInstance();
$db = $database->getConnection();

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Instanciar modelo
$clientModel = new Client($db);

// Obter parâmetros de filtro
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

// Buscar clientes
$conditions = [];
if ($status) {
    // Converter status para active (1 para active, 0 para inactive)
    $conditions['active'] = ($status === 'active') ? 1 : 0;
}

$clients = $clientModel->findAll($conditions);

// Filtrar por busca se necessário
if ($search) {
    $clients = array_filter($clients, function($client) use ($search) {
        return stripos($client['name'], $search) !== false || 
               stripos($client['code'], $search) !== false;
    });
}

$pageTitle = 'Gestão de Clientes';
include 'includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="container-fluid p-6">
    <!-- Cabeçalho -->
    <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                    <i class="fas fa-users text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-rich-black">Gestão de Clientes</h1>
                    <p class="text-paynes-gray">Gerencie os clientes do sistema</p>
                </div>
            </div>
            <div class="flex gap-3">
                <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200 flex items-center" data-bs-toggle="modal" data-bs-target="#clientModal">
                    <i class="fas fa-plus mr-2"></i>
                    Novo Cliente
                </button>
                <a href="goals.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg font-medium transition-colors duration-200 flex items-center">
                    <i class="fas fa-bullseye mr-2"></i>
                    Voltar para Metas
                </a>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Buscar por Nome/Código</label>
                <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                       id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Digite o nome ou código...">
            </div>
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="status" name="status">
                    <option value="">Todos</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Ativo</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inativo</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors duration-200 flex items-center">
                    <i class="fas fa-search mr-2"></i>
                    Buscar
                </button>
            </div>
        </form>
    </div>

    <!-- Listagem de Clientes -->
    <div class="bg-white rounded-lg shadow-sm border">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-rich-black flex items-center">
                <i class="fas fa-list text-blue-600 mr-2"></i>
                Lista de Clientes (<?= count($clients) ?>)
            </h2>
        </div>
        <div class="p-6">
            <?php if (empty($clients)): ?>
                <div class="text-center py-12">
                    <div class="p-4 rounded-full bg-gray-100 text-gray-400 w-20 h-20 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-users-slash text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhum cliente encontrado</h3>
                    <p class="text-gray-500 mb-4">
                        <?= $search ? 'Tente ajustar os filtros de busca.' : 'Cadastre o primeiro cliente para começar.' ?>
                    </p>
                    <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200 flex items-center mx-auto" data-bs-toggle="modal" data-bs-target="#clientModal">
                        <i class="fas fa-plus mr-2"></i>Cadastrar Cliente
                    </button>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4 font-medium text-gray-700">Código</th>
                                <th class="text-left py-3 px-4 font-medium text-gray-700">Nome</th>
                                <th class="text-left py-3 px-4 font-medium text-gray-700">Descrição</th>
                                <th class="text-center py-3 px-4 font-medium text-gray-700">Status</th>
                                <th class="text-center py-3 px-4 font-medium text-gray-700">Criado em</th>
                                <th class="text-center py-3 px-4 font-medium text-gray-700 w-32">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors duration-150">
                                    <td class="py-4 px-4">
                                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm font-mono font-medium"><?= htmlspecialchars($client['code']) ?></span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="font-medium text-gray-900"><?= htmlspecialchars($client['name']) ?></span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="text-gray-600"><?= htmlspecialchars($client['description'] ?? '-') ?></span>
                                    </td>
                                    <td class="py-4 px-4 text-center">
                                        <?php if ($client['active']): ?>
                                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-medium">Ativo</span>
                                        <?php else: ?>
                                            <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded-full text-xs font-medium">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4 text-center">
                                        <div class="text-sm">
                                            <div class="font-medium text-gray-900"><?= date('d/m/Y', strtotime($client['created_at'])) ?></div>
                                            <div class="text-gray-500"><?= date('H:i', strtotime($client['created_at'])) ?></div>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4 text-center">
                                        <div class="flex justify-center gap-1">
                                            <button type="button" class="p-2 text-blue-600 hover:bg-blue-50 rounded transition-colors duration-150" 
                                                    onclick="editClient(<?= htmlspecialchars(json_encode($client)) ?>)" 
                                                    title="Editar Cliente">
                                                <i class="fas fa-edit text-sm"></i>
                                            </button>
                                            <?php if ($client['active']): ?>
                                                <button type="button" class="p-2 text-yellow-600 hover:bg-yellow-50 rounded transition-colors duration-150" 
                                                        onclick="toggleStatus(<?= $client['id'] ?>, 'inactive')" 
                                                        title="Desativar Cliente">
                                                    <i class="fas fa-pause text-sm"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="p-2 text-green-600 hover:bg-green-50 rounded transition-colors duration-150" 
                                                        onclick="toggleStatus(<?= $client['id'] ?>, 'active')" 
                                                        title="Ativar Cliente">
                                                    <i class="fas fa-play text-sm"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="p-2 text-red-600 hover:bg-red-50 rounded transition-colors duration-150" 
                                                    onclick="confirmDelete(<?= $client['id'] ?>)" 
                                                    title="Excluir Cliente">
                                                <i class="fas fa-trash text-sm"></i>
                                            </button>
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
</div>

<!-- Modal de Cliente -->
<div class="modal fade" id="clientModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-lg border-0 shadow-lg">
            <form id="clientForm" action="client-action.php" method="POST">
                <div class="modal-header bg-blue-600 text-white rounded-t-lg">
                    <h5 class="modal-title font-semibold" id="clientModalTitle">Novo Cliente</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-6">
                    <input type="hidden" id="client_id" name="id">
                    <input type="hidden" id="action" name="action" value="create">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="code" class="block text-sm font-medium text-gray-700 mb-2">Código *</label>
                            <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                   id="code" name="code" required placeholder="Ex: CLI001">
                            <p class="text-xs text-gray-500 mt-1">Código único para identificação do cliente</p>
                        </div>
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Nome *</label>
                            <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                   id="name" name="name" required placeholder="Nome do cliente">
                        </div>
                        <div class="md:col-span-2">
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
                            <textarea class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                      id="description" name="description" rows="3" placeholder="Descrição opcional do cliente"></textarea>
                        </div>
                        <div>
                            <label for="active" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                    id="modal_active" name="active">
                                <option value="1">Ativo</option>
                                <option value="0">Inativo</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-gray-50 rounded-b-lg">
                    <button type="button" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg font-medium transition-colors duration-200" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200 flex items-center">
                        <i class="fas fa-save mr-2"></i>Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de confirmação de exclusão -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-lg border-0 shadow-lg">
            <div class="modal-header bg-red-600 text-white rounded-t-lg">
                <h5 class="modal-title font-semibold">Confirmar Exclusão</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-6">
                <div class="flex items-center mb-4">
                    <div class="p-3 rounded-full bg-red-100 text-red-600 mr-4">
                        <i class="fas fa-exclamation-triangle text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">Tem certeza?</h3>
                        <p class="text-gray-600">Esta ação não pode ser desfeita.</p>
                    </div>
                </div>
                <p class="text-gray-700">Tem certeza que deseja excluir este cliente?</p>
            </div>
            <div class="modal-footer bg-gray-50 rounded-b-lg">
                <button type="button" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg font-medium transition-colors duration-200" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200 flex items-center" id="confirmDeleteBtn">
                    <i class="fas fa-trash mr-2"></i>Excluir
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
let deleteClientId = null;

function editClient(client) {
    document.getElementById('clientModalTitle').textContent = 'Editar Cliente';
    document.getElementById('client_id').value = client.id;
    document.getElementById('action').value = 'update';
    document.getElementById('code').value = client.code;
    document.getElementById('name').value = client.name;
    document.getElementById('description').value = client.description || '';
    document.getElementById('modal_active').value = client.active ? '1' : '0';
    
    const modal = new bootstrap.Modal(document.getElementById('clientModal'));
    modal.show();
}

function confirmDelete(clientId) {
    deleteClientId = clientId;
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

function toggleStatus(clientId, newStatus) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'client-action.php';
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'id';
    idInput.value = clientId;
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = newStatus === 'active' ? 'activate' : 'deactivate';
    
    form.appendChild(idInput);
    form.appendChild(actionInput);
    document.body.appendChild(form);
    form.submit();
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (deleteClientId) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'client-action.php';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = deleteClientId;
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete';
        
        form.appendChild(idInput);
        form.appendChild(actionInput);
        document.body.appendChild(form);
        form.submit();
    }
});

// Limpar modal ao fechar
document.getElementById('clientModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('clientModalTitle').textContent = 'Novo Cliente';
    document.getElementById('clientForm').reset();
    document.getElementById('client_id').value = '';
    document.getElementById('action').value = 'create';
});
</script>

<?php
// Notificações de sucesso/erro
$pageScripts = '';
if (isset($_SESSION['success'])) {
    $msg = $_SESSION['success'];
    unset($_SESSION['success']);
    $pageScripts .= '<script>window.addEventListener("load",function(){ showNotification(' . json_encode($msg) . ', "success"); });</script>';
}
if (isset($_SESSION['error'])) {
    $msg = $_SESSION['error'];
    unset($_SESSION['error']);
    $pageScripts .= '<script>window.addEventListener("load",function(){ showNotification(' . json_encode($msg) . ', "error"); });</script>';
}
?>

<?php include 'includes/footer.php'; ?>
