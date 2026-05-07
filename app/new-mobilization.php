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

// Instanciar modelos
$equipmentModel = new Equipment($db);
$userModel = new User($db);
$frontModel = new Front($db);
$mobilizationModel = new EquipmentMobilization($db);

// Buscar dados para os selects
$equipments = $equipmentModel->findAll(['active' => 1]);
$users = $userModel->findAll(['active' => 1]);
$fronts = $frontModel->findAll(['active' => 1]);

// Processar formulário
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'equipment_id' => intval($_POST['equipment_id']),
            'requested_by' => intval($_POST['requested_by']),
            'from_front_id' => !empty($_POST['from_front_id']) ? intval($_POST['from_front_id']) : null,
            'to_front_id' => intval($_POST['to_front_id']),
            'mobilization_date' => $_POST['mobilization_date'],
            'mobilization_time' => $_POST['mobilization_time'],
            'reason' => trim($_POST['reason']),
            'transport_type' => $_POST['transport_type'],
            'transport_company' => !empty($_POST['transport_company']) ? trim($_POST['transport_company']) : null,
            'transport_cost' => !empty($_POST['transport_cost']) ? floatval($_POST['transport_cost']) : null,
            'observations' => !empty($_POST['observations']) ? trim($_POST['observations']) : null,
            'status' => 'SOLICITADA',
            'created_by' => $_SESSION['user_id']
        ];
        
        if ($mobilizationModel->create($data)) {
            $message = 'Mobilização criada com sucesso!';
            $messageType = 'success';
            // Redirecionar após 2 segundos
            echo "<script>setTimeout(function(){ window.location.href = 'equipment-mobilization.php'; }, 2000);</script>";
        } else {
            $message = 'Erro ao criar mobilização.';
            $messageType = 'error';
        }
        
    } catch (Exception $e) {
        $message = 'Erro ao criar mobilização: ' . $e->getMessage();
        $messageType = 'error';
    }
}

$pageTitle = 'Nova Mobilização de Equipamento';
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
                    <span class="text-sm font-medium text-gray-500">Nova Mobilização</span>
                </div>
            </li>
        </ol>
    </nav>

    <!-- Header da Página -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="bg-blue-100 p-3 rounded-lg mr-4">
                    <i class="fas fa-plus-circle text-blue-600 text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Nova Mobilização de Equipamento</h1>
                    <p class="text-gray-600 mt-1">Solicite a mobilização de um equipamento entre frentes</p>
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
                            <option value="<?php echo $equipment['id']; ?>">
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
                            <option value="<?php echo $user['id']; ?>" <?php echo $user['id'] == $_SESSION['user_id'] ? 'selected' : ''; ?>>
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
                            <option value="<?php echo $front['id']; ?>">
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
                            <option value="<?php echo $front['id']; ?>">
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
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-clock text-gray-400 mr-1"></i>
                            Horário
                        </label>
                        <input type="time" name="mobilization_time" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" value="08:00">
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
                          placeholder="Descreva o motivo da mobilização..."></textarea>
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
                            <option value="PRANCHA">Prancha</option>
                            <option value="PROPRIO">Próprio</option>
                            <option value="GUINCHO">Guincho</option>
                            <option value="OUTRO">Outro</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-building text-gray-400 mr-1"></i>
                            Empresa de Transporte
                        </label>
                        <input type="text" name="transport_company" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                               placeholder="Nome da empresa responsável pelo transporte">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-dollar-sign text-gray-400 mr-1"></i>
                            Custo do Transporte (R$)
                        </label>
                        <input type="number" name="transport_cost" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" step="0.01" min="0" 
                               placeholder="0,00">
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
                          placeholder="Observações adicionais..."></textarea>
            </div>

            <!-- Botões -->
            <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="equipment-mobilization.php" class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </a>
                <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-200 shadow-lg hover:shadow-xl">
                    <i class="fas fa-paper-plane mr-2"></i>Solicitar Mobilização
                </button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>