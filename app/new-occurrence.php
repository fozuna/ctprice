<?php
/**
 * Nova Ocorrência
 * Sistema BDO - Controle de Maquinários
 */

require_once 'config/config.php';

// Verificar autenticação
$database = Database::getInstance();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$pageTitle = 'Nova Ocorrência';
$occurrenceModel = new Occurrence($db);
$equipmentModel = new Equipment($db);
$occurrenceTypeModel = new OccurrenceType($db);
$userModel = new User($db);
$frontModel = new Front($db);
$auditLog = new AuditLog($db);

$message = '';
$messageType = '';

// Processar criação de ocorrência
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'equipment_id' => intval($_POST['equipment_id']),
            'occurrence_type_id' => intval($_POST['occurrence_type_id']),
            'operator_id' => intval($_POST['operator_id']),
            'front_id' => intval($_POST['front_id']),
            'start_datetime' => $_POST['start_date'] . ' ' . $_POST['start_time'],
            'end_datetime' => !empty($_POST['end_date']) && !empty($_POST['end_time']) ? 
                          $_POST['end_date'] . ' ' . $_POST['end_time'] : null,
            'start_hours' => floatval($_POST['start_hours']),
            'end_hours' => !empty($_POST['end_hours']) ? floatval($_POST['end_hours']) : null,
            'observations' => trim($_POST['observations']),
            'created_by' => $_SESSION['user_id']
        ];
        
        // Calcular duração se data/hora final informada
        if ($data['end_datetime']) {
            $start = new DateTime($data['start_datetime']);
            $end = new DateTime($data['end_datetime']);
            $data['duration_minutes'] = $end->diff($start)->days * 24 * 60 + 
                                      $end->diff($start)->h * 60 + 
                                      $end->diff($start)->i;
        }
        
        // Calcular horas trabalhadas se horímetro final informado
        if ($data['end_hours']) {
            $data['hours_worked'] = $data['end_hours'] - $data['start_hours'];
        }
        
        $newId = $occurrenceModel->create($data);
        if ($newId) {
            $auditLog->logInsert('occurrences', $newId, $data, $_SESSION['user_id']);
            $message = 'Ocorrência criada com sucesso!';
            $messageType = 'success';
            
            // Redirecionar para a lista de ocorrências após 2 segundos
            header('refresh:2;url=occurrences.php');
        } else {
            $message = 'Erro ao criar ocorrência.';
            $messageType = 'error';
        }
    } catch (Exception $e) {
        $message = 'Erro: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Buscar dados para os selects
$equipments = $equipmentModel->findWithFronts(['e.active' => 1]);
$occurrenceTypes = $occurrenceTypeModel->findActive();
$operators = $userModel->findOperators();
$fronts = $frontModel->findActive();

// Obter dados do usuário logado
$userFrontId = $_SESSION['user_front'] ?? null;

include 'includes/header.php';
?>

<div class="space-y-6">
    <!-- Cabeçalho -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Nova Ocorrência</h1>
            <p class="text-gray-600">Registrar nova ocorrência/movimento de equipamento</p>
        </div>
        <a href="occurrences.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
            <i class="fas fa-arrow-left mr-2"></i>Voltar
        </a>
    </div>

    <!-- Mensagens -->
    <?php if ($message): ?>
        <div class="<?php echo $messageType === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?> px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <!-- Formulário -->
    <div class="bg-white shadow rounded-lg p-6">
        <form method="POST" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Equipamento -->
                <div>
                    <label for="equipment_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Equipamento *
                    </label>
                    <select name="equipment_id" id="equipment_id" required 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Selecione um equipamento</option>
                        <?php foreach ($equipments as $equipment): ?>
                            <option value="<?php echo $equipment['id']; ?>" 
                                    data-front="<?php echo $equipment['front_id']; ?>"
                                    data-current-hours="<?php echo $equipment['current_hours']; ?>">
                                <?php echo htmlspecialchars($equipment['tag'] . ' - ' . $equipment['description']); ?>
                                <?php if ($equipment['front_name']): ?>
                                    (<?php echo htmlspecialchars($equipment['front_name']); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tipo de Ocorrência -->
                <div>
                    <label for="occurrence_type_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Tipo de Ocorrência *
                    </label>
                    <select name="occurrence_type_id" id="occurrence_type_id" required 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Selecione um tipo</option>
                        <?php foreach ($occurrenceTypes as $type): ?>
                            <option value="<?php echo $type['id']; ?>" 
                                    data-category="<?php echo $type['category']; ?>"
                                    data-affects-hour-meter="<?php echo $type['affects_hour_meter']; ?>">
                                <?php echo htmlspecialchars($type['code'] . ' - ' . $type['description']); ?>
                                (<?php echo $type['category']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Operador -->
                <div>
                    <label for="operator_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Operador *
                    </label>
                    <select name="operator_id" id="operator_id" required 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Selecione um operador</option>
                        <?php foreach ($operators as $operator): ?>
                            <option value="<?php echo $operator['id']; ?>" 
                                    <?php echo ($operator['id'] == $_SESSION['user_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($operator['name']); ?>
                                <?php if ($operator['id'] == $_SESSION['user_id']): ?>
                                    (Você)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Frente -->
                <div>
                    <label for="front_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Frente *
                    </label>
                    <select name="front_id" id="front_id" required 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Selecione uma frente</option>
                        <?php foreach ($fronts as $front): ?>
                            <option value="<?php echo $front['id']; ?>" 
                                    <?php echo ($userFrontId && $front['id'] == $userFrontId) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($front['code'] . ' - ' . $front['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Data e Hora de Início -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">
                        Data de Início *
                    </label>
                    <input type="date" name="start_date" id="start_date" required 
                           value="<?php echo date('Y-m-d'); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label for="start_time" class="block text-sm font-medium text-gray-700 mb-2">
                        Hora de Início *
                    </label>
                    <input type="time" name="start_time" id="start_time" required 
                           value="<?php echo date('H:i'); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            <!-- Data e Hora de Fim (opcional) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">
                        Data de Fim
                    </label>
                    <input type="date" name="end_date" id="end_date" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label for="end_time" class="block text-sm font-medium text-gray-700 mb-2">
                        Hora de Fim
                    </label>
                    <input type="time" name="end_time" id="end_time" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            <!-- Horímetros -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="start_hours" class="block text-sm font-medium text-gray-700 mb-2">
                        Horímetro Inicial *
                    </label>
                    <input type="number" name="start_hours" id="start_hours" required 
                           step="0.01" min="0"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label for="end_hours" class="block text-sm font-medium text-gray-700 mb-2">
                        Horímetro Final
                    </label>
                    <input type="number" name="end_hours" id="end_hours" 
                           step="0.01" min="0"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            <!-- Observações -->
            <div>
                <label for="observations" class="block text-sm font-medium text-gray-700 mb-2">
                    Observações
                </label>
                <textarea name="observations" id="observations" rows="4" 
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          placeholder="Observações sobre a ocorrência..."></textarea>
            </div>

            <!-- Botões -->
            <div class="flex justify-end space-x-4">
                <a href="occurrences.php" 
                   class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Cancelar
                </a>
                <button type="submit" 
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-save mr-2"></i>Salvar Ocorrência
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Atualizar frente automaticamente quando equipamento for selecionado
document.getElementById('equipment_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const frontId = selectedOption.getAttribute('data-front');
    const currentHours = selectedOption.getAttribute('data-current-hours');
    
    if (frontId) {
        document.getElementById('front_id').value = frontId;
    }
    
    if (currentHours) {
        document.getElementById('start_hours').value = currentHours;
    }
});

// Validar horímetro final
document.getElementById('end_hours').addEventListener('change', function() {
    const startHours = parseFloat(document.getElementById('start_hours').value) || 0;
    const endHours = parseFloat(this.value) || 0;
    
    if (endHours > 0 && endHours < startHours) {
        alert('O horímetro final não pode ser menor que o inicial.');
        this.value = '';
    }
});

// Validar data/hora final
document.getElementById('end_date').addEventListener('change', validateEndDateTime);
document.getElementById('end_time').addEventListener('change', validateEndDateTime);

function validateEndDateTime() {
    const startDate = document.getElementById('start_date').value;
    const startTime = document.getElementById('start_time').value;
    const endDate = document.getElementById('end_date').value;
    const endTime = document.getElementById('end_time').value;
    
    if (startDate && startTime && endDate && endTime) {
        const start = new Date(startDate + 'T' + startTime);
        const end = new Date(endDate + 'T' + endTime);
        
        if (end <= start) {
            alert('A data/hora final deve ser posterior à inicial.');
            document.getElementById('end_date').value = '';
            document.getElementById('end_time').value = '';
        }
    }
}
</script>

<?php include 'includes/footer.php'; ?>