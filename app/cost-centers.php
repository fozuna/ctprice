<?php
require_once 'config/config.php';

$database = Database::getInstance();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn() || !in_array($_SESSION['user_role'] ?? null, [ROLE_ADMIN, ROLE_COMPRAS], true)) {
    header('Location: dashboard.php');
    exit();
}

$pageTitle = 'Centros de Custo';
$costCenterModel = new CostCenter($db);
$auditLog = new AuditLog($db);

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'create':
                $data = [
                    'name' => trim($_POST['name'] ?? ''),
                    'code' => trim($_POST['code'] ?? ''),
                    'department' => trim($_POST['department'] ?? ''),
                    'parent_id' => !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null,
                    'active' => 1,
                ];

                $newId = $costCenterModel->createCostCenter($data);
                if ($newId) {
                    $auditLog->logInsert('cost_centers', $newId, $costCenterModel->findById($newId), $_SESSION['user_id']);
                    $message = 'Centro de custo criado com sucesso!';
                    $messageType = 'success';
                } else {
                    $message = 'Erro ao criar centro de custo.';
                    $messageType = 'error';
                }
                break;

            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $oldCostCenter = $costCenterModel->findById($id);
                if (!$oldCostCenter) {
                    throw new Exception('Centro de custo não encontrado');
                }

                $data = [
                    'name' => trim($_POST['name'] ?? ''),
                    'code' => trim($_POST['code'] ?? ''),
                    'department' => trim($_POST['department'] ?? ''),
                    'parent_id' => !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null,
                    'active' => isset($_POST['active']) ? (int)$_POST['active'] : (int)$oldCostCenter['active'],
                ];

                if ($costCenterModel->updateCostCenter($id, $data)) {
                    $auditLog->logUpdate('cost_centers', $id, $oldCostCenter, $costCenterModel->findById($id), $_SESSION['user_id']);
                    $message = 'Centro de custo atualizado com sucesso!';
                    $messageType = 'success';
                } else {
                    $message = 'Erro ao atualizar centro de custo.';
                    $messageType = 'error';
                }
                break;

            case 'toggle_status':
                $id = (int)($_POST['id'] ?? 0);
                $costCenter = $costCenterModel->findById($id);
                if (!$costCenter) {
                    throw new Exception('Centro de custo não encontrado');
                }

                $newData = ['active' => $costCenter['active'] ? 0 : 1];
                if ($costCenterModel->update($id, $newData)) {
                    $auditLog->logUpdate('cost_centers', $id, $costCenter, $newData, $_SESSION['user_id']);
                    $message = $newData['active'] ? 'Centro de custo ativado!' : 'Centro de custo desativado!';
                    $messageType = 'success';
                } else {
                    $message = 'Erro ao alterar status do centro de custo.';
                    $messageType = 'error';
                }
                break;

            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                $costCenter = $costCenterModel->findById($id);
                if (!$costCenter) {
                    throw new Exception('Centro de custo não encontrado');
                }
                if ($costCenterModel->hasLinkedUsers($id)) {
                    throw new Exception('Não é possível excluir um centro de custo com usuários vinculados');
                }

                if ($costCenterModel->delete($id)) {
                    $auditLog->logDelete('cost_centers', $id, $costCenter, $_SESSION['user_id']);
                    $message = 'Centro de custo excluído com sucesso!';
                    $messageType = 'success';
                } else {
                    $message = 'Erro ao excluir centro de custo.';
                    $messageType = 'error';
                }
                break;
        }
    } catch (Exception $e) {
        $message = 'Erro: ' . $e->getMessage();
        $messageType = 'error';
    }
}

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$costCenters = $costCenterModel->findAllWithStats();
if ($search !== '' || $statusFilter !== '') {
    $costCenters = array_values(array_filter($costCenters, static function ($row) use ($search, $statusFilter) {
        $matchesSearch = $search === ''
            || stripos((string)$row['name'], $search) !== false
            || stripos((string)($row['code'] ?? ''), $search) !== false
            || stripos((string)($row['department'] ?? ''), $search) !== false;

        $matchesStatus = $statusFilter === '' || (string)$row['active'] === (string)$statusFilter;

        return $matchesSearch && $matchesStatus;
    }));
}

$parentOptions = $costCenterModel->findAll([], 'name ASC', 500);

include 'includes/header.php';
?>

<div class="space-y-6">
    <?php if ($message): ?>
    <div class="p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
            <form method="GET" class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Buscar por nome, código ou departamento..."
                       class="px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">

                <select name="status" class="px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                    <option value="">Todos os status</option>
                    <option value="1" <?php echo $statusFilter === '1' ? 'selected' : ''; ?>>Ativo</option>
                    <option value="0" <?php echo $statusFilter === '0' ? 'selected' : ''; ?>>Inativo</option>
                </select>

                <button type="submit" class="px-4 py-2 bg-paynes-gray text-white rounded-lg hover:bg-prussian-blue transition-colors">
                    <i class="fas fa-search mr-2"></i>Filtrar
                </button>
            </form>

            <button onclick="openModal('costCenterModal')" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-plus mr-2"></i>Novo Centro de Custo
            </button>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-silver-lake-blue">
                <thead class="bg-eggshell">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Nome</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Código</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Departamento</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Centro Pai</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Usuários Vinculados</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-silver-lake-blue">
                    <?php if (empty($costCenters)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-paynes-gray">Nenhum centro de custo encontrado</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($costCenters as $costCenter): ?>
                            <tr class="hover:bg-eggshell">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-rich-black"><?php echo htmlspecialchars($costCenter['name']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-rich-black"><?php echo htmlspecialchars($costCenter['code'] ?? ''); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-rich-black"><?php echo htmlspecialchars($costCenter['department'] ?? ''); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-rich-black"><?php echo htmlspecialchars($costCenter['parent_name'] ?? ''); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-slate-100 text-slate-800">
                                        <?php echo (int)($costCenter['linked_users'] ?? 0); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo !empty($costCenter['active']) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo !empty($costCenter['active']) ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <button onclick="editCostCenter(<?php echo htmlspecialchars(json_encode($costCenter), ENT_QUOTES, 'UTF-8'); ?>)"
                                            class="text-blue-600 hover:text-blue-900" title="Editar centro de custo">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja <?php echo !empty($costCenter['active']) ? 'desativar' : 'ativar'; ?> este centro de custo?')">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?php echo (int)$costCenter['id']; ?>">
                                        <button type="submit" class="<?php echo !empty($costCenter['active']) ? 'text-orange-600 hover:text-orange-900' : 'text-green-600 hover:text-green-900'; ?>"
                                                title="<?php echo !empty($costCenter['active']) ? 'Desativar' : 'Ativar'; ?> centro de custo">
                                            <i class="fas <?php echo !empty($costCenter['active']) ? 'fa-ban' : 'fa-check'; ?>"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="inline" onsubmit="return confirm('ATENÇÃO: Esta ação irá excluir permanentemente o centro de custo. Tem certeza que deseja continuar?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int)$costCenter['id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900" title="Excluir centro de custo">
                                            <i class="fas fa-trash"></i>
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

<div id="costCenterModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-md w-full">
            <div class="p-6 border-b border-silver-lake-blue">
                <h3 class="text-lg font-semibold text-rich-black" id="modalTitle">Novo Centro de Custo</h3>
            </div>
            <form method="POST" id="costCenterForm">
                <div class="p-6 space-y-4">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="costCenterId">

                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Nome *</label>
                        <input type="text" name="name" id="costCenterName" required
                               class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Código</label>
                        <input type="text" name="code" id="costCenterCode"
                               class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Departamento</label>
                        <input type="text" name="department" id="costCenterDepartment"
                               class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Centro de Custo Pai</label>
                        <select name="parent_id" id="costCenterParentId"
                                class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                            <option value="">Selecione um centro pai (opcional)</option>
                            <?php foreach ($parentOptions as $parentOption): ?>
                                <option value="<?php echo (int)$parentOption['id']; ?>">
                                    <?php echo htmlspecialchars($parentOption['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Status</label>
                        <select name="active" id="costCenterActive"
                                class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </div>
                </div>

                <div class="p-6 border-t border-silver-lake-blue flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('costCenterModal')"
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
    if (modalId === 'costCenterModal') {
        resetForm();
    }
}

function resetForm() {
    document.getElementById('costCenterForm').reset();
    document.getElementById('formAction').value = 'create';
    document.getElementById('costCenterId').value = '';
    document.getElementById('modalTitle').textContent = 'Novo Centro de Custo';
    document.getElementById('costCenterActive').value = '1';
}

function editCostCenter(costCenter) {
    document.getElementById('formAction').value = 'update';
    document.getElementById('costCenterId').value = costCenter.id;
    document.getElementById('modalTitle').textContent = 'Editar Centro de Custo';
    document.getElementById('costCenterName').value = costCenter.name || '';
    document.getElementById('costCenterCode').value = costCenter.code || '';
    document.getElementById('costCenterDepartment').value = costCenter.department || '';
    document.getElementById('costCenterParentId').value = costCenter.parent_id || '';
    document.getElementById('costCenterActive').value = String(costCenter.active ?? 1);

    openModal('costCenterModal');
}

document.getElementById('costCenterModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal('costCenterModal');
    }
});
</script>

<?php include 'includes/footer.php'; ?>
