<?php
require_once 'config/config.php';
require_once 'classes/Auth.php';
require_once 'classes/EquipmentMobilization.php';
require_once 'models/Equipment.php';
require_once 'models/Front.php';
require_once 'models/User.php';

// Verificar autenticação
$database = Database::getInstance();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Verificar ID da mobilização
if (!isset($_GET['id'])) {
    header('Location: equipment-mobilization.php');
    exit();
}

$mobilization_id = (int)$_GET['id'];

// Buscar mobilização
$stmt = $pdo->prepare("
    SELECT m.*, e.tag as equipment_tag, e.description as equipment_description
    FROM equipment_mobilizations m
    JOIN equipments e ON m.equipment_id = e.id
    WHERE m.id = ?
");
$stmt->execute([$mobilization_id]);
$mobilization = $stmt->fetch();

if (!$mobilization) {
    $_SESSION['error'] = 'Mobilização não encontrada.';
    header('Location: equipment-mobilization.php');
    exit();
}

// Verificar se a mobilização pode ser editada
if (!in_array($mobilization['status'], ['PENDENTE', 'APROVADA'])) {
    $_SESSION['error'] = 'Esta mobilização não pode ser editada no status atual.';
    header('Location: equipment-mobilization.php');
    exit();
}

// Buscar equipamentos
$stmt = $pdo->query("SELECT id, tag, description FROM equipments WHERE status = 'ATIVO' ORDER BY tag");
$equipments = $stmt->fetchAll();

// Buscar usuários
$stmt = $pdo->query("SELECT id, name FROM users WHERE status = 'ATIVO' ORDER BY name");
$users = $stmt->fetchAll();

// Buscar frentes
$stmt = $pdo->query("SELECT id, name FROM fronts WHERE status = 'ATIVO' ORDER BY name");
$fronts = $stmt->fetchAll();

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("
            UPDATE equipment_mobilizations SET
                equipment_id = ?, requested_by = ?, from_front_id = ?, to_front_id = ?, 
                mobilization_date = ?, mobilization_time = ?, reason = ?, transport_type = ?, 
                transport_company = ?, transport_cost = ?, observations = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $_POST['equipment_id'],
            $_POST['requested_by'],
            !empty($_POST['from_front_id']) ? $_POST['from_front_id'] : null,
            $_POST['to_front_id'],
            $_POST['mobilization_date'],
            $_POST['mobilization_time'],
            $_POST['reason'],
            $_POST['transport_type'],
            !empty($_POST['transport_company']) ? $_POST['transport_company'] : null,
            !empty($_POST['transport_cost']) ? $_POST['transport_cost'] : null,
            !empty($_POST['observations']) ? $_POST['observations'] : null,
            $mobilization_id
        ]);
        
        $_SESSION['success'] = 'Mobilização atualizada com sucesso!';
        header('Location: equipment-mobilization.php');
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Erro ao atualizar mobilização: ' . $e->getMessage();
    }
}

$page_title = 'Editar Mobilização';
include 'includes/header.php';
?>

<div class="container-fluid px-4 py-6">
    <!-- Breadcrumb -->
    <nav class="flex mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <li class="inline-flex items-center">
                <a href="dashboard.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                    <i class="fas fa-home mr-2"></i>Dashboard
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                    <a href="equipment-mobilization.php" class="text-sm font-medium text-gray-700 hover:text-blue-600">
                        Mobilização de Equipamentos
                    </a>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                    <span class="text-sm font-medium text-gray-500">Editar Mobilização</span>
                </div>
            </li>
        </ol>
    </nav>

    <!-- Header da Página -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="bg-orange-100 p-3 rounded-lg mr-4">
                    <i class="fas fa-edit text-orange-600 text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Editar Mobilização</h1>
                    <p class="text-gray-600 mt-1">Mobilização #<?php echo $mobilization['id']; ?> - <?php echo htmlspecialchars($mobilization['equipment_tag']); ?></p>
                </div>
            </div>
            <a href="equipment-mobilization.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg transition-colors duration-200">
                <i class="fas fa-arrow-left mr-2"></i>Voltar
            </a>
        </div>
    </div>

    <!-- Formulário -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <form method="POST" class="p-6 space-y-6">
            <!-- Seção: Equipamento e Solicitante -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h6 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-truck text-blue-600 mr-2"></i>
                    Informações Básicas
                </h6>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-cog text-gray-400 mr-1"></i>
                            Equipamento *
                        </label>
                        <select name="equipment_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" required>
                            <option value="">Selecione o equipamento</option>
                            <?php foreach ($equipments as $equipment): ?>
                            <option value="<?php echo $equipment['id']; ?>" <?php echo $equipment['id'] == $mobilization['equipment_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($equipment['tag'] . ' - ' . $equipment['description']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user text-gray-400 mr-1"></i>
                            Solicitante *
                        </label>
                        <select name="requested_by" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" required>
                            <option value="">Selecione o solicitante</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $user['id'] == $mobilization['requested_by'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Seção: Origem e Destino -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h6 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-route text-green-600 mr-2"></i>
                    Origem e Destino
                </h6>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-map-marker-alt text-gray-400 mr-1"></i>
                            Frente de Origem
                        </label>
                        <select name="from_front_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                            <option value="">Primeira mobilização</option>
                            <?php foreach ($fronts as $front): ?>
                            <option value="<?php echo $front['id']; ?>" <?php echo $front['id'] == $mobilization['from_front_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($front['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Deixe em branco se for a primeira mobilização do equipamento</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-flag-checkered text-gray-400 mr-1"></i>
                            Frente de Destino *
                        </label>
                        <select name="to_front_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" required>
                            <option value="">Selecione a frente de destino</option>
                            <?php foreach ($fronts as $front): ?>
                            <option value="<?php echo $front['id']; ?>" <?php echo $front['id'] == $mobilization['to_front_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($front['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Seção: Data e Horário -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h6 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-calendar-alt text-purple-600 mr-2"></i>
                    Agendamento
                </h6>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar text-gray-400 mr-1"></i>
                            Data da Mobilização *
                        </label>
                        <input type="date" name="mobilization_date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" required 
                               value="<?php echo $mobilization['mobilization_date']; ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-clock text-gray-400 mr-1"></i>
                            Horário
                        </label>
                        <input type="time" name="mobilization_time" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                               value="<?php echo $mobilization['mobilization_time']; ?>">
                    </div>
                </div>
            </div>
            
            <!-- Seção: Motivo -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-comment-alt text-gray-400 mr-1"></i>
                    Motivo da Mobilização *
                </label>
                <textarea name="reason" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" rows="3" required 
                          placeholder="Descreva o motivo da mobilização..."><?php echo htmlspecialchars($mobilization['reason']); ?></textarea>
            </div>
            
            <!-- Seção: Transporte -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h6 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-shipping-fast text-orange-600 mr-2"></i>
                    Informações de Transporte
                </h6>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-truck text-gray-400 mr-1"></i>
                            Tipo de Transporte
                        </label>
                        <select name="transport_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                            <option value="PRANCHA" <?php echo $mobilization['transport_type'] == 'PRANCHA' ? 'selected' : ''; ?>>Prancha</option>
                            <option value="PROPRIO" <?php echo $mobilization['transport_type'] == 'PROPRIO' ? 'selected' : ''; ?>>Próprio</option>
                            <option value="GUINCHO" <?php echo $mobilization['transport_type'] == 'GUINCHO' ? 'selected' : ''; ?>>Guincho</option>
                            <option value="OUTRO" <?php echo $mobilization['transport_type'] == 'OUTRO' ? 'selected' : ''; ?>>Outro</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-building text-gray-400 mr-1"></i>
                            Empresa de Transporte
                        </label>
                        <input type="text" name="transport_company" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                               placeholder="Nome da empresa responsável pelo transporte" value="<?php echo htmlspecialchars($mobilization['transport_company']); ?>">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-dollar-sign text-gray-400 mr-1"></i>
                            Custo do Transporte (R$)
                        </label>
                        <input type="number" name="transport_cost" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" step="0.01" min="0" 
                               placeholder="0,00" value="<?php echo $mobilization['transport_cost']; ?>">
                    </div>
                </div>
            </div>
            
            <!-- Seção: Observações -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-sticky-note text-gray-400 mr-1"></i>
                    Observações
                </label>
                <textarea name="observations" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" rows="3" 
                          placeholder="Observações adicionais..."><?php echo htmlspecialchars($mobilization['observations']); ?></textarea>
            </div>

            <!-- Botões -->
            <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="equipment-mobilization.php" class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </a>
                <button type="submit" class="px-6 py-2 bg-gradient-to-r from-orange-600 to-orange-700 text-white rounded-lg hover:from-orange-700 hover:to-orange-800 transition-all duration-200 shadow-lg hover:shadow-xl">
                    <i class="fas fa-save mr-2"></i>Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>