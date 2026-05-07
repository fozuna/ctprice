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

// Verificar ID da meta
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Meta não encontrada';
    header('Location: goals.php');
    exit;
}

// Instanciar modelos
$goalModel = new Goal($db);
$frontModel = new Front($db);
$clientModel = new Client($db);

// Buscar meta
$goalId = (int)$_GET['id'];
$goal = $goalModel->findByIdWithDetails($goalId);

if (!$goal) {
    $_SESSION['error'] = 'Meta não encontrada';
    header('Location: goals.php');
    exit;
}

// Buscar frentes e clientes ativos para os selects
$fronts = $frontModel->findAll(['active' => 1]);
$clients = $clientModel->findAll(['active' => 1]);

$pageTitle = 'Editar Meta';
include 'includes/header.php';
?>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
        <div class="p-6 border-b border-silver-lake-blue">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                        <i class="fas fa-edit text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-rich-black">Editar Meta</h1>
                        <p class="text-paynes-gray">Atualize as informações da meta e salve as alterações</p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <?php $backUrl = ($goal['goal_type'] ?? 'production') === 'production' ? 'goals-production.php' : ((($goal['goal_type'] ?? '') === 'delivery') ? 'goals-delivery.php' : 'goals.php'); ?>
                    <a href="<?= $backUrl ?>" class="inline-flex items-center px-4 py-2 border border-silver-lake-blue text-paynes-gray rounded-lg hover:bg-eggshell transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Voltar
                    </a>
                    <button type="button" class="inline-flex items-center px-4 py-2 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 transition-colors" onclick="confirmDelete(<?= $goal['id'] ?>)">
                        <i class="fas fa-trash mr-2"></i>Excluir Meta
                    </button>
                </div>
            </div>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <div class="text-paynes-gray">Frente</div>
                    <div class="font-semibold text-rich-black"><?= htmlspecialchars($goal['front_name']) ?></div>
                </div>
                <div>
                    <div class="text-paynes-gray">Cliente</div>
                    <div class="font-semibold text-rich-black"><?= htmlspecialchars($goal['client_name']) ?></div>
                </div>
                <div>
                    <div class="text-paynes-gray">Período</div>
                    <div class="font-semibold text-rich-black"><?= date('d/m/Y', strtotime($goal['start_date'])) ?> à <?= date('d/m/Y', strtotime($goal['end_date'])) ?></div>
                </div>
                <div>
                    <div class="text-paynes-gray">Meta Total</div>
                    <div class="font-semibold text-green-700"><?= number_format($goal['total_goal'], 2, ',', '.') ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
        <div class="p-6 border-b border-silver-lake-blue">
            <h3 class="text-lg font-semibold text-rich-black">Editar Informações da Meta</h3>
        </div>
        <div class="p-6">
            <form id="goalForm" action="goal-action.php" method="POST" class="space-y-6">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= $goal['id'] ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="front_id" class="block text-sm font-medium text-paynes-gray mb-2">Frente de Trabalho *</label>
                        <select class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="front_id" name="front_id" required>
                            <option value="">Selecione uma frente</option>
                            <?php foreach ($fronts as $front): ?>
                                <option value="<?= $front['id'] ?>" <?= $front['id'] == $goal['front_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($front['name']) ?> (<?= htmlspecialchars($front['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="text-xs text-paynes-gray mt-1">Origem da meta (frente de trabalho)</div>
                    </div>
                    <div>
                        <label for="client_id" class="block text-sm font-medium text-paynes-gray mb-2">Cliente *</label>
                        <select class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="client_id" name="client_id" required>
                            <option value="">Selecione um cliente</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>" <?= $client['id'] == $goal['client_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($client['name']) ?> (<?= htmlspecialchars($client['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="text-xs text-paynes-gray mt-1">Destino da meta (cliente)</div>
                    </div>
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-paynes-gray mb-2">Data Inicial *</label>
                        <input type="date" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="start_date" name="start_date" value="<?= $goal['start_date'] ?>" required>
                        <div class="text-xs text-paynes-gray mt-1">Data de início da meta</div>
                    </div>
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-paynes-gray mb-2">Data Final *</label>
                        <input type="date" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="end_date" name="end_date" value="<?= $goal['end_date'] ?>" required>
                        <div class="text-xs text-paynes-gray mt-1">Data de término da meta</div>
                    </div>
                    <div>
                        <label for="total_goal" class="block text-sm font-medium text-paynes-gray mb-2">Meta Total *</label>
                        <div class="flex">
                            <input type="number" class="w-full px-3 py-2 border border-silver-lake-blue rounded-l-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="total_goal" name="total_goal" step="0.01" min="0" value="<?= $goal['total_goal'] ?>" required>
                            <span class="px-3 py-2 border border-l-0 border-silver-lake-blue rounded-r-lg bg-eggshell text-paynes-gray">toneladas</span>
                        </div>
                        <div class="text-xs text-paynes-gray mt-1">Valor total da meta para o período</div>
                    </div>
                    <div>
                        <label for="goal_type" class="block text-sm font-medium text-paynes-gray mb-2">Tipo de Meta</label>
                        <select class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="goal_type" name="goal_type">
                            <option value="production" <?= ($goal['goal_type'] ?? 'production') == 'production' ? 'selected' : '' ?>>Produção</option>
                            <option value="sales" <?= ($goal['goal_type'] ?? 'production') == 'sales' ? 'selected' : '' ?>>Vendas</option>
                            <option value="delivery" <?= ($goal['goal_type'] ?? 'production') == 'delivery' ? 'selected' : '' ?>>Entrega</option>
                            <option value="other" <?= ($goal['goal_type'] ?? 'production') == 'other' ? 'selected' : '' ?>>Outro</option>
                        </select>
                        <div class="text-xs text-paynes-gray mt-1">Categoria da meta</div>
                    </div>
                    <div class="md:col-span-2">
                        <label for="description" class="block text-sm font-medium text-paynes-gray mb-2">Descrição</label>
                        <textarea class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="description" name="description" rows="3" placeholder="Descrição opcional da meta..."><?= htmlspecialchars($goal['description'] ?? '') ?></textarea>
                        <div class="text-xs text-paynes-gray mt-1">Informações adicionais sobre a meta</div>
                    </div>
                </div>

                <div id="calculationPreview" class="hidden">
                    <div class="mt-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-4">
                        <div class="font-semibold mb-2"><i class="fas fa-calculator mr-2"></i>Preview dos Novos Cálculos</div>
                        <p class="text-sm mb-3"><strong>Atenção:</strong> Alterar as datas ou valor total recalcula metas diárias.</p>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                            <div>
                                <div class="text-paynes-gray">Total de Dias</div>
                                <div id="totalDays" class="font-semibold text-rich-black">-</div>
                            </div>
                            <div>
                                <div class="text-paynes-gray">Meta Diária</div>
                                <div id="dailyGoal" class="font-semibold text-rich-black">-</div>
                            </div>
                            <div>
                                <div class="text-paynes-gray">Semanas</div>
                                <div id="totalWeeks" class="font-semibold text-rich-black">-</div>
                            </div>
                            <div>
                                <div class="text-paynes-gray">Meta Semanal (aprox.)</div>
                                <div id="weeklyGoal" class="font-semibold text-rich-black">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="<?= $backUrl ?>" class="inline-flex items-center px-4 py-2 border border-silver-lake-blue text-paynes-gray rounded-lg hover:bg-eggshell transition-colors">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-save mr-2"></i>Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de confirmação de exclusão -->
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50" style="display: none;">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Confirmar Exclusão</h3>
                    <button type="button" onclick="closeDeleteModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="mb-6">
                    <p class="text-gray-700 mb-3">Tem certeza que deseja excluir esta meta?</p>
                    <p class="text-red-600 text-sm"><strong>Atenção:</strong> Esta ação também excluirá todas as metas diárias relacionadas e não pode ser desfeita.</p>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">
                        Cancelar
                    </button>
                    <button type="button" id="confirmDeleteBtn" class="px-4 py-2 text-white bg-red-600 rounded-lg hover:bg-red-700">
                        Excluir
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let deleteGoalId = null;

// Valores originais para comparação
const originalValues = {
    start_date: '<?= $goal['start_date'] ?>',
    end_date: '<?= $goal['end_date'] ?>',
    total_goal: <?= $goal['total_goal'] ?>
};

// Função para calcular preview
function calculatePreview() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const totalGoal = parseFloat(document.getElementById('total_goal').value) || 0;
    
    // Verificar se houve mudanças significativas
    const hasChanges = startDate !== originalValues.start_date || 
                      endDate !== originalValues.end_date || 
                      Math.abs(totalGoal - originalValues.total_goal) > 0.01;
    
    if (startDate && endDate && totalGoal > 0 && hasChanges) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        
        // Validar datas
        if (start >= end) {
            document.getElementById('calculationPreview').style.display = 'none';
            return;
        }
        
        // Calcular total de dias
        const timeDiff = end.getTime() - start.getTime();
        const totalDays = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
        
        // Calcular metas
        const dailyGoal = totalGoal / totalDays;
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
        
        // Se data final for menor que inicial, limpar
        if (endDateInput.value && endDateInput.value <= startDate) {
            endDateInput.value = '';
        }
    }
});

document.getElementById('end_date').addEventListener('change', function() {
    const endDate = this.value;
    const startDateInput = document.getElementById('start_date');
    
    if (endDate) {
        startDateInput.max = endDate;
        
        // Se data inicial for maior que final, limpar
        if (startDateInput.value && startDateInput.value >= endDate) {
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

function confirmDelete(goalId) {
    deleteGoalId = goalId;
    const modalElement = document.getElementById('deleteModal');
    
    if (modalElement) {
        modalElement.style.display = 'block';
        modalElement.classList.remove('hidden');
    }
}

function closeDeleteModal() {
    const modalElement = document.getElementById('deleteModal');
    if (modalElement) {
        modalElement.style.display = 'none';
        modalElement.classList.add('hidden');
    }
}

// Aguardar o DOM estar carregado
document.addEventListener('DOMContentLoaded', function() {
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            if (deleteGoalId) {
                var origin = '<?= ($goal['goal_type'] ?? 'production') === 'production' ? 'production' : ((($goal['goal_type'] ?? '') === 'delivery') ? 'delivery' : '') ?>';
                var url = `goal-action.php?action=delete&id=${deleteGoalId}`;
                if (origin) { url += `&origin=${origin}`; }
                window.location.href = url;
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
