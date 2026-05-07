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

// Verificar parâmetros
if (!isset($_GET['id']) || !isset($_GET['action'])) {
    header('Location: equipment-mobilization.php');
    exit();
}

$mobilization_id = (int)$_GET['id'];
$action = $_GET['action'];

// Validar ação
$valid_actions = ['approve', 'start_transport', 'complete', 'cancel'];
if (!in_array($action, $valid_actions)) {
    $_SESSION['error'] = 'Ação inválida.';
    header('Location: equipment-mobilization.php');
    exit();
}

// Buscar mobilização
$stmt = $pdo->prepare("
    SELECT m.*, e.tag as equipment_tag, e.description as equipment_description,
           u.name as requested_by_name, 
           ff.name as from_front_name, tf.name as to_front_name
    FROM equipment_mobilizations m
    JOIN equipments e ON m.equipment_id = e.id
    JOIN users u ON m.requested_by = u.id
    LEFT JOIN fronts ff ON m.from_front_id = ff.id
    JOIN fronts tf ON m.to_front_id = tf.id
    WHERE m.id = ?
");
$stmt->execute([$mobilization_id]);
$mobilization = $stmt->fetch();

if (!$mobilization) {
    $_SESSION['error'] = 'Mobilização não encontrada.';
    header('Location: equipment-mobilization.php');
    exit();
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($action) {
            case 'approve':
                $stmt = $pdo->prepare("
                    UPDATE equipment_mobilizations 
                    SET status = 'APROVADA', approved_by = ?, approved_at = NOW(), observations = ?
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $_POST['observations'], $mobilization_id]);
                $_SESSION['success'] = 'Mobilização aprovada com sucesso!';
                break;
                
            case 'start_transport':
                $stmt = $pdo->prepare("
                    UPDATE equipment_mobilizations 
                    SET status = 'EM_TRANSPORTE', departure_datetime = ?, started_by = ?
                    WHERE id = ?
                ");
                $stmt->execute([$_POST['departure_datetime'], $_SESSION['user_id'], $mobilization_id]);
                $_SESSION['success'] = 'Transporte iniciado com sucesso!';
                break;
                
            case 'complete':
                $stmt = $pdo->prepare("
                    UPDATE equipment_mobilizations 
                    SET status = 'CONCLUIDA', arrival_datetime = ?, completed_by = ?
                    WHERE id = ?
                ");
                $stmt->execute([$_POST['arrival_datetime'], $_SESSION['user_id'], $mobilization_id]);
                $_SESSION['success'] = 'Mobilização concluída com sucesso!';
                break;
                
            case 'cancel':
                $stmt = $pdo->prepare("
                    UPDATE equipment_mobilizations 
                    SET status = 'CANCELADA', cancelled_by = ?, cancelled_at = NOW(), observations = ?
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $_POST['observations'], $mobilization_id]);
                $_SESSION['success'] = 'Mobilização cancelada com sucesso!';
                break;
        }
        
        header('Location: equipment-mobilization.php');
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Erro ao processar ação: ' . $e->getMessage();
    }
}

// Configurar dados da página baseado na ação
$page_config = [
    'approve' => [
        'title' => 'Aprovar Mobilização',
        'icon' => 'fas fa-check-circle',
        'color' => 'green',
        'button_text' => 'Aprovar',
        'button_class' => 'bg-green-600 hover:bg-green-700'
    ],
    'start_transport' => [
        'title' => 'Iniciar Transporte',
        'icon' => 'fas fa-shipping-fast',
        'color' => 'blue',
        'button_text' => 'Iniciar Transporte',
        'button_class' => 'bg-blue-600 hover:bg-blue-700'
    ],
    'complete' => [
        'title' => 'Concluir Mobilização',
        'icon' => 'fas fa-flag-checkered',
        'color' => 'green',
        'button_text' => 'Concluir',
        'button_class' => 'bg-green-600 hover:bg-green-700'
    ],
    'cancel' => [
        'title' => 'Cancelar Mobilização',
        'icon' => 'fas fa-times-circle',
        'color' => 'red',
        'button_text' => 'Cancelar Mobilização',
        'button_class' => 'bg-red-600 hover:bg-red-700'
    ]
];

$config = $page_config[$action];
$page_title = $config['title'];
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
                    <span class="text-sm font-medium text-gray-500"><?php echo $config['title']; ?></span>
                </div>
            </li>
        </ol>
    </nav>

    <!-- Header da Página -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="bg-<?php echo $config['color']; ?>-100 p-3 rounded-lg mr-4">
                    <i class="<?php echo $config['icon']; ?> text-<?php echo $config['color']; ?>-600 text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900"><?php echo $config['title']; ?></h1>
                    <p class="text-gray-600 mt-1">Mobilização #<?php echo $mobilization['id']; ?></p>
                </div>
            </div>
            <a href="equipment-mobilization.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg transition-colors duration-200">
                <i class="fas fa-arrow-left mr-2"></i>Voltar
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Informações da Mobilização -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                Informações da Mobilização
            </h3>
            
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Equipamento</label>
                        <p class="text-gray-900"><?php echo htmlspecialchars($mobilization['equipment_tag'] . ' - ' . $mobilization['equipment_description']); ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Status Atual</label>
                        <p class="text-gray-900">
                            <span class="px-2 py-1 text-xs font-medium rounded-full 
                                <?php echo getStatusClass($mobilization['status']); ?>">
                                <?php echo getStatusText($mobilization['status']); ?>
                            </span>
                        </p>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Solicitante</label>
                        <p class="text-gray-900"><?php echo htmlspecialchars($mobilization['requested_by_name']); ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Data da Mobilização</label>
                        <p class="text-gray-900"><?php echo date('d/m/Y H:i', strtotime($mobilization['mobilization_date'] . ' ' . $mobilization['mobilization_time'])); ?></p>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Origem</label>
                        <p class="text-gray-900"><?php echo $mobilization['from_front_name'] ?: 'Primeira mobilização'; ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Destino</label>
                        <p class="text-gray-900"><?php echo htmlspecialchars($mobilization['to_front_name']); ?></p>
                    </div>
                </div>
                
                <div>
                    <label class="text-sm font-medium text-gray-500">Motivo</label>
                    <p class="text-gray-900"><?php echo htmlspecialchars($mobilization['reason']); ?></p>
                </div>
                
                <?php if ($mobilization['observations']): ?>
                <div>
                    <label class="text-sm font-medium text-gray-500">Observações</label>
                    <p class="text-gray-900"><?php echo htmlspecialchars($mobilization['observations']); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Formulário de Ação -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <i class="<?php echo $config['icon']; ?> text-<?php echo $config['color']; ?>-600 mr-2"></i>
                <?php echo $config['title']; ?>
            </h3>
            
            <form method="POST" class="space-y-4">
                <?php if ($action === 'approve'): ?>
                    <div>
                        <p class="text-gray-700 mb-4">Tem certeza que deseja aprovar esta mobilização?</p>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Observações da Aprovação</label>
                            <textarea name="observations" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" rows="3" 
                                      placeholder="Observações sobre a aprovação..."></textarea>
                        </div>
                    </div>
                    
                <?php elseif ($action === 'start_transport'): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Data e Hora de Saída *</label>
                        <input type="datetime-local" name="departure_datetime" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" required
                               value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                    
                <?php elseif ($action === 'complete'): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Data e Hora de Chegada *</label>
                        <input type="datetime-local" name="arrival_datetime" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" required
                               value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                    
                <?php elseif ($action === 'cancel'): ?>
                    <div>
                        <p class="text-red-600 mb-4 font-medium">Tem certeza que deseja cancelar esta mobilização?</p>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Motivo do Cancelamento *</label>
                            <textarea name="observations" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" rows="3" required
                                      placeholder="Descreva o motivo do cancelamento..."></textarea>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Botões -->
                <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                    <a href="equipment-mobilization.php" class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </a>
                    <button type="submit" class="px-6 py-2 <?php echo $config['button_class']; ?> text-white rounded-lg transition-all duration-200 shadow-lg hover:shadow-xl">
                        <i class="<?php echo $config['icon']; ?> mr-2"></i><?php echo $config['button_text']; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>