<?php
/**
 * Gerenciamento de Usuários
 * Sistema BDO - Controle de Maquinários
 */

require_once 'config/config.php';

// Verificar autenticação e permissão
$database = Database::getInstance();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn() || !$auth->hasPermission(ROLE_ADMIN)) {
    header('Location: dashboard.php');
    exit();
}

$pageTitle = 'Usuários';
$userModel = new User($db);
$frontModel = new Front($db);
$costCenterModel = new CostCenter($db);
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
                    'name' => trim($_POST['name']),
                    'email' => trim($_POST['email']),
                    'password' => trim($_POST['password']),
                    'role_id' => intval($_POST['role_id']),
                    'functional_profile' => trim($_POST['functional_profile']),
                    'front_id' => !empty($_POST['front_id']) ? intval($_POST['front_id']) : null,
                    'cost_center_id' => intval($_POST['cost_center_id']),
                    'active' => 1
                ];
                
                $userId = $userModel->createUser($data);
                if ($userId) {
                    $auditLog->logInsert('users', $userId, $data, $_SESSION['user_id']);
                    $message = 'Usuário criado com sucesso!';
                    $messageType = 'success';
                } else {
                    $message = 'Erro ao criar usuário.';
                    $messageType = 'error';
                }
                break;
                
            case 'update':
                $id = intval($_POST['id']);
                
                // Obter dados antigos antes da atualização
                $oldUser = $userModel->findById($id);
                
                $data = [
                    'name' => trim($_POST['name']),
                    'email' => trim($_POST['email']),
                    'role_id' => intval($_POST['role_id']),
                    'functional_profile' => trim($_POST['functional_profile']),
                    'front_id' => !empty($_POST['front_id']) ? intval($_POST['front_id']) : null,
                    'cost_center_id' => intval($_POST['cost_center_id'])
                ];
                
                // Atualizar senha apenas se fornecida
                if (!empty($_POST['password'])) {
                    $data['password'] = trim($_POST['password']);
                }
                
                if ($userModel->updateUser($id, $data)) {
                    $auditLog->logUpdate('users', $id, $oldUser, $data, $_SESSION['user_id']);
                    $message = 'Usuário atualizado com sucesso!';
                    $messageType = 'success';
                } else {
                    $message = 'Erro ao atualizar usuário.';
                    $messageType = 'error';
                }
                break;
                
            case 'toggle_status':
                $id = intval($_POST['id']);
                $user = $userModel->findById($id);
                
                // Não permitir desativar o próprio usuário
                if ($id == $_SESSION['user_id']) {
                    $message = 'Você não pode desativar seu próprio usuário.';
                    $messageType = 'error';
                    break;
                }
                
                if ($user) {
                    $newStatus = $user['active'] ? 0 : 1;
                    $newData = ['active' => $newStatus];
                    if ($userModel->update($id, $newData)) {
                        $auditLog->logUpdate('users', $id, $user, $newData, $_SESSION['user_id']);
                        $message = $newStatus ? 'Usuário ativado!' : 'Usuário desativado!';
                        $messageType = 'success';
                    }
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                
                // Não permitir excluir o próprio usuário
                if ($id == $_SESSION['user_id']) {
                    $message = 'Você não pode excluir seu próprio usuário.';
                    $messageType = 'error';
                    break;
                }
                
                // Verificar se o usuário existe
                $user = $userModel->findById($id);
                if (!$user) {
                    $message = 'Usuário não encontrado.';
                    $messageType = 'error';
                    break;
                }
                
                if ($userModel->delete($id)) {
                    $auditLog->logDelete('users', $id, $user, $_SESSION['user_id']);
                    $message = 'Usuário excluído com sucesso!';
                    $messageType = 'success';
                } else {
                    $message = 'Erro ao excluir usuário.';
                    $messageType = 'error';
                }
                break;
        }
    } catch (Exception $e) {
        $message = 'Erro: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Buscar usuários
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role_id'] ?? '';
$functional_profile_filter = $_GET['functional_profile'] ?? '';
$front_filter = $_GET['front_id'] ?? '';
$cost_center_filter = $_GET['cost_center_id'] ?? '';
$status_filter = $_GET['status'] ?? '';

$conditions = [];
if ($search) {
    $conditions[] = "(u.name LIKE '%$search%' OR u.email LIKE '%$search%')";
}
if ($role_filter) {
    $conditions['u.role_id'] = $role_filter;
}
if ($functional_profile_filter) {
    $conditions['u.functional_profile'] = $functional_profile_filter;
}
if ($front_filter) {
    $conditions['u.front_id'] = $front_filter;
}
if ($cost_center_filter) {
    $conditions['u.cost_center_id'] = $cost_center_filter;
}
if ($status_filter !== '') {
    $conditions['u.active'] = $status_filter;
}

$users = $userModel->findWithRoleAndFront($conditions);
$fronts = $frontModel->findActive();
$costCenters = $costCenterModel->findActive();

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
                       placeholder="Buscar por nome ou email..." 
                       class="px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                
                <select name="role_id" class="px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                    <option value="">Todos os perfis</option>
                    <option value="<?php echo ROLE_ADMIN; ?>" <?php echo $role_filter == ROLE_ADMIN ? 'selected' : ''; ?>>Administrador</option>
                    <option value="<?php echo ROLE_SUPERVISOR; ?>" <?php echo $role_filter == ROLE_SUPERVISOR ? 'selected' : ''; ?>>Supervisor</option>
                    <option value="<?php echo ROLE_LIDER; ?>" <?php echo $role_filter == ROLE_LIDER ? 'selected' : ''; ?>>Líder</option>
                    <option value="<?php echo ROLE_OPERADOR; ?>" <?php echo $role_filter == ROLE_OPERADOR ? 'selected' : ''; ?>>Operador</option>
                    <option value="<?php echo ROLE_COORD_RH; ?>" <?php echo $role_filter == ROLE_COORD_RH ? 'selected' : ''; ?>>Coordenador de RH</option>
                </select>

                <select name="functional_profile" class="px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                    <option value="">Todos os perfis funcionais</option>
                    <option value="SOLICITANTE" <?php echo $functional_profile_filter === 'SOLICITANTE' ? 'selected' : ''; ?>>Solicitante</option>
                    <option value="APROVADOR" <?php echo $functional_profile_filter === 'APROVADOR' ? 'selected' : ''; ?>>Aprovador</option>
                </select>
                
                <select name="front_id" class="px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                    <option value="">Todas as frentes</option>
                    <?php foreach ($fronts as $front): ?>
                        <option value="<?php echo $front['id']; ?>" <?php echo $front_filter == $front['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($front['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="cost_center_id" class="px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                    <option value="">Todos os centros de custo</option>
                    <?php foreach ($costCenters as $costCenter): ?>
                        <option value="<?php echo $costCenter['id']; ?>" <?php echo $cost_center_filter == $costCenter['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($costCenter['name']); ?>
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
                <i class="fas fa-plus mr-2"></i>Novo Usuário
            </button>
        </div>
    </div>

    <!-- Tabela de Usuários -->
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-silver-lake-blue">
                <thead class="bg-eggshell">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Nome</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Perfil de Acesso</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Perfil Funcional</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Centro de Custo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Frente</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Último Acesso</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-silver-lake-blue">
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="9" class="px-6 py-4 text-center text-paynes-gray">
                                Nenhum usuário encontrado
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <?php
                            $functionalProfile = $user['functional_profile'] ?? 'SOLICITANTE';
                            $costCenterName = $user['cost_center_name'] ?? '';
                            $frontName = $user['front_name'] ?? '';
                            ?>
                            <tr class="hover:bg-eggshell">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-rich-black">
                                        <?php echo htmlspecialchars($user['name']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-rich-black">
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php 
                                        switch($user['role_id']) {
                                            case ROLE_ADMIN: echo 'bg-purple-100 text-purple-800'; break;
                                            case ROLE_SUPERVISOR: echo 'bg-blue-100 text-blue-800'; break;
                                            case ROLE_LIDER: echo 'bg-green-100 text-green-800'; break;
                                            case ROLE_OPERADOR: echo 'bg-yellow-100 text-yellow-800'; break;
                                            case ROLE_COORD_RH: echo 'bg-pink-100 text-pink-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?php 
                                        switch($user['role_id']) {
                                            case ROLE_ADMIN: echo 'Administrador'; break;
                                            case ROLE_SUPERVISOR: echo 'Supervisor'; break;
                                            case ROLE_LIDER: echo 'Líder'; break;
                                            case ROLE_OPERADOR: echo 'Operador'; break;
                                            case ROLE_COORD_RH: echo 'Coordenador de RH'; break;
                                            default: echo 'Desconhecido';
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $functionalProfile === 'APROVADOR' ? 'bg-indigo-100 text-indigo-800' : 'bg-cyan-100 text-cyan-800'; ?>">
                                        <?php echo htmlspecialchars($userModel->functionalProfileLabel($functionalProfile)); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-rich-black">
                                        <?php echo $costCenterName !== '' ? htmlspecialchars($costCenterName) : 'N/A'; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-rich-black">
                                        <?php echo $frontName !== '' ? htmlspecialchars($frontName) : 'N/A'; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $user['active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $user['active'] ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-paynes-gray">
                                        <?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Nunca'; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <button onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)" 
                                            class="text-blue-600 hover:text-blue-900" title="Editar usuário">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja <?php echo $user['active'] ? 'desativar' : 'ativar'; ?> este usuário?')">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="<?php echo $user['active'] ? 'text-orange-600 hover:text-orange-900' : 'text-green-600 hover:text-green-900'; ?>" 
                                                title="<?php echo $user['active'] ? 'Desativar' : 'Ativar'; ?> usuário">
                                            <i class="fas <?php echo $user['active'] ? 'fa-ban' : 'fa-check'; ?>"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="inline" onsubmit="return confirm('ATENÇÃO: Esta ação irá excluir permanentemente o usuário. Tem certeza que deseja continuar?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900" title="Excluir usuário">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
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

<!-- Modal Criar/Editar -->
<div id="createModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-md w-full">
            <div class="p-6 border-b border-silver-lake-blue">
                <h3 class="text-lg font-semibold text-rich-black" id="modalTitle">Novo Usuário</h3>
            </div>
            <form method="POST" id="userForm">
                <div class="p-6 space-y-4">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="userId">
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Nome *</label>
                        <input type="text" name="name" id="userName" required 
                               class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Email *</label>
                        <input type="email" name="email" id="userEmail" required 
                               class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">
                            <span id="passwordLabel">Senha *</span>
                            <span id="passwordHint" class="text-xs text-paynes-gray hidden">(Deixe em branco para manter a senha atual)</span>
                        </label>
                        <input type="password" name="password" id="userPassword" 
                               class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Perfil *</label>
                        <select name="role_id" id="userRoleId" required 
                                class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                            <option value="">Selecione um perfil</option>
                            <option value="<?php echo ROLE_ADMIN; ?>">Administrador</option>
                            <option value="<?php echo ROLE_SUPERVISOR; ?>">Supervisor</option>
                            <option value="<?php echo ROLE_LIDER; ?>">Líder</option>
                            <option value="<?php echo ROLE_OPERADOR; ?>">Operador</option>
                            <option value="<?php echo ROLE_COORD_RH; ?>">Coordenador de RH</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Perfil Funcional *</label>
                        <select name="functional_profile" id="userFunctionalProfile" required
                                class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                            <option value="">Selecione um perfil funcional</option>
                            <option value="SOLICITANTE">Solicitante</option>
                            <option value="APROVADOR">Aprovador</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Frente</label>
                        <select name="front_id" id="userFrontId" 
                                class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                            <option value="">Selecione uma frente (opcional)</option>
                            <?php foreach ($fronts as $front): ?>
                                <option value="<?php echo $front['id']; ?>">
                                    <?php echo htmlspecialchars($front['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Centro de Custo *</label>
                        <select name="cost_center_id" id="userCostCenterId" required
                                class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                            <option value="">Selecione um centro de custo</option>
                            <?php foreach ($costCenters as $costCenter): ?>
                                <option value="<?php echo $costCenter['id']; ?>">
                                    <?php echo htmlspecialchars($costCenter['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
    document.getElementById('userForm').reset();
    document.getElementById('formAction').value = 'create';
    document.getElementById('modalTitle').textContent = 'Novo Usuário';
    document.getElementById('userId').value = '';
    document.getElementById('passwordLabel').textContent = 'Senha *';
    document.getElementById('userPassword').required = true;
    document.getElementById('passwordHint').classList.add('hidden');
}

function editUser(user) {
    document.getElementById('formAction').value = 'update';
    document.getElementById('modalTitle').textContent = 'Editar Usuário';
    document.getElementById('userId').value = user.id;
    document.getElementById('userName').value = user.name;
    document.getElementById('userEmail').value = user.email;
    document.getElementById('userRoleId').value = user.role_id;
    document.getElementById('userFunctionalProfile').value = user.functional_profile || '';
    document.getElementById('userFrontId').value = user.front_id || '';
    document.getElementById('userCostCenterId').value = user.cost_center_id || '';
    
    // Senha não é obrigatória na edição
    document.getElementById('userPassword').value = '';
    document.getElementById('userPassword').required = false;
    document.getElementById('passwordLabel').textContent = 'Senha';
    document.getElementById('passwordHint').classList.remove('hidden');
    
    openModal('createModal');
}

// Fechar modal ao clicar fora
document.getElementById('createModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal('createModal');
    }
});

// Validação do formulário
document.getElementById('userForm').addEventListener('submit', function(e) {
    const action = document.getElementById('formAction').value;
    const password = document.getElementById('userPassword').value;
    
    if (action === 'create' && !password) {
        e.preventDefault();
        alert('A senha é obrigatória para novos usuários.');
    }
});
</script>

<?php include 'includes/footer.php'; ?>
