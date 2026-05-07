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

// Instanciar modelos
$frontModel = new Front($db);
$clientModel = new Client($db);

// Buscar frentes e clientes ativos
$fronts = $frontModel->findAll(['active' => 1]);
$clients = $clientModel->findAll(['active' => 1]);

$pageTitle = 'Nova Meta de Entrega';
include 'includes/header.php';
?>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
        <div class="p-6 border-b border-silver-lake-blue">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                        <i class="fas fa-plus-circle text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-rich-black">Nova Meta de Entrega</h1>
                        <p class="text-paynes-gray">Defina a demanda de entrega por cliente</p>
                    </div>
                </div>
                <a href="goals.php" class="inline-flex items-center px-4 py-2 border border-silver-lake-blue text-paynes-gray rounded-lg hover:bg-eggshell transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar
                </a>
            </div>
        </div>
        <div class="p-6">
            <form id="goalForm" action="goal-action.php" method="POST" class="space-y-6 max-w-4xl">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="goal_type" value="delivery">
                        
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="front_id" class="block text-sm font-medium text-paynes-gray mb-2">Frente de Trabalho *</label>
                        <select class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="front_id" name="front_id" required>
                            <option value="">Selecione uma frente</option>
                            <?php foreach ($fronts as $front): ?>
                                <option value="<?= $front['id'] ?>">
                                    <?= htmlspecialchars($front['name']) ?> (<?= htmlspecialchars($front['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-sm text-paynes-gray mt-1">Origem da meta (frente de trabalho)</p>
                    </div>
                    <div>
                        <label for="client_id" class="block text-sm font-medium text-paynes-gray mb-2">Cliente *</label>
                        <select class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="client_id" name="client_id" required>
                            <option value="">Selecione um cliente</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>">
                                    <?= htmlspecialchars($client['name']) ?> (<?= htmlspecialchars($client['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-sm text-paynes-gray mt-1">Cliente obrigatório para metas de entrega.</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-paynes-gray mb-2">Data Inicial *</label>
                        <input type="date" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="start_date" name="start_date" required>
                        <p class="text-sm text-paynes-gray mt-1">Data de início da meta</p>
                    </div>
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-paynes-gray mb-2">Data Final *</label>
                        <input type="date" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="end_date" name="end_date" required>
                        <p class="text-sm text-paynes-gray mt-1">Data de término da meta</p>
                    </div>
                </div>
                <div>
                    <label for="total_goal" class="block text-sm font-medium text-paynes-gray mb-2">Meta Total *</label>
                    <div class="flex">
                        <input type="number" class="w-full px-3 py-2 border border-silver-lake-blue rounded-l-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="total_goal" name="total_goal" step="0.01" min="0" required placeholder="0,00">
                        <span class="px-3 py-2 border border-l-0 border-silver-lake-blue rounded-r-lg bg-eggshell text-paynes-gray text-sm">toneladas</span>
                    </div>
                    <p class="text-sm text-paynes-gray mt-1">Valor total da meta para o período</p>
                </div>
                <div class="hidden" id="calculationPreview">
                    <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-lg p-4">
                        <div class="font-semibold mb-2"><i class="fas fa-calculator mr-2"></i>Preview dos Cálculos</div>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <span class="text-paynes-gray">Total de Dias:</span>
                                <span id="totalDays" class="block font-semibold text-rich-black">-</span>
                            </div>
                            <div>
                                <span class="text-paynes-gray">Meta Diária:</span>
                                <span id="dailyGoal" class="block font-semibold text-rich-black">-</span>
                            </div>
                            <div>
                                <span class="text-paynes-gray">Semanas:</span>
                                <span id="totalWeeks" class="block font-semibold text-rich-black">-</span>
                            </div>
                            <div>
                                <span class="text-paynes-gray">Meta Semanal (aprox.):</span>
                                <span id="weeklyGoal" class="block font-semibold text-rich-black">-</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <a href="goals.php" class="inline-flex items-center px-4 py-2 border border-silver-lake-blue text-paynes-gray rounded-lg hover:bg-eggshell transition-colors">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-save mr-2"></i>Criar Meta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Função para calcular preview
function calculatePreview() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const totalGoal = parseFloat(document.getElementById('total_goal').value) || 0;
    const goalType = 'delivery';
    
    if (startDate && endDate && totalGoal > 0) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        
        // Validar datas
        if (start >= end) {
            document.getElementById('calculationPreview').style.display = 'none';
            return;
        }
        
        // Calcular total de dias (e dias úteis para produção)
        const timeDiff = end.getTime() - start.getTime();
        const totalDays = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
        let workingDays = 0;
        
        // Calcular metas
        const denominator = totalDays;
        const dailyGoal = totalGoal / denominator;
        const totalWeeks = Math.ceil(totalDays / 7);
        const weeklyGoal = totalGoal / totalWeeks;
        
        // Atualizar preview
        document.getElementById('totalDays').textContent = totalDays;
        document.getElementById('dailyGoal').textContent = dailyGoal.toFixed(2);
        document.getElementById('totalWeeks').textContent = totalWeeks;
        document.getElementById('weeklyGoal').textContent = weeklyGoal.toFixed(2);
        
        document.getElementById('calculationPreview').style.display = 'block';
    } else {
        document.getElementById('calculationPreview').style.display = 'none';
    }
}

// Event listeners para recalcular preview
document.getElementById('start_date').addEventListener('change', calculatePreview);
document.getElementById('end_date').addEventListener('change', calculatePreview);
document.getElementById('total_goal').addEventListener('input', calculatePreview);


// Validação de datas
document.getElementById('start_date').addEventListener('change', function() {
    const startDate = this.value;
    const endDateInput = document.getElementById('end_date');
    
    if (startDate) {
        endDateInput.min = startDate;
        
        // Se data final for menor que inicial, limpar (permitir datas iguais)
        if (endDateInput.value && endDateInput.value < startDate) {
            endDateInput.value = '';
        }
    }
});

document.getElementById('end_date').addEventListener('change', function() {
    const endDate = this.value;
    const startDateInput = document.getElementById('start_date');
    
    if (endDate) {
        startDateInput.max = endDate;
        
        // Se data inicial for maior que final, limpar (permitir datas iguais)
        if (startDateInput.value && startDateInput.value > endDate) {
            startDateInput.value = '';
        }
    }
});

// Validação do formulário
document.getElementById('goalForm').addEventListener('submit', function(e) {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const totalGoal = parseFloat(document.getElementById('total_goal').value) || 0;
    
    if (startDate && endDate && new Date(startDate) >= new Date(endDate)) {
        e.preventDefault();
        alert('A data final deve ser maior que a data inicial.');
        return false;
    }
    
    if (totalGoal <= 0) {
        e.preventDefault();
        alert('A meta total deve ser maior que zero.');
        return false;
    }
});

// Permitir datas passadas - removido bloqueio de data mínima
// document.getElementById('start_date').min = new Date().toISOString().split('T')[0];
</script>

<?php include 'includes/footer.php'; ?>
