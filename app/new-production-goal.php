<?php
require_once 'config/config.php';

$database = Database::getInstance();
$db = $database->getConnection();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$frontModel = new Front($db);
$fronts = $frontModel->findAll(['active' => 1]);

$pageTitle = 'Nova Meta de Produção';
include 'includes/header.php';
?>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
        <div class="p-6 border-b border-silver-lake-blue">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                        <i class="fas fa-industry text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-rich-black">Nova Meta de Produção</h1>
                        <p class="text-paynes-gray">Defina a demanda mensal por frente</p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <a href="goals-production.php" class="inline-flex items-center px-4 py-2 border border-silver-lake-blue text-paynes-gray rounded-lg hover:bg-eggshell transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Voltar
                    </a>
                    <a href="new-goal.php" class="inline-flex items-center px-4 py-2 border border-silver-lake-blue text-paynes-gray rounded-lg hover:bg-eggshell transition-colors">
                        <i class="fas fa-truck-loading mr-2"></i>Nova Meta de Entrega
                    </a>
                </div>
            </div>
        </div>
        <div class="p-6">
            <form id="prodGoalForm" action="goal-action.php" method="POST" class="space-y-6 max-w-3xl">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="goal_type" value="production">
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
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-paynes-gray mb-2">Data Inicial *</label>
                        <input type="date" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="start_date" name="start_date" required>
                    </div>
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-paynes-gray mb-2">Data Final *</label>
                        <input type="date" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="end_date" name="end_date" required>
                    </div>
                </div>
                <div>
                    <label for="total_goal" class="block text-sm font-medium text-paynes-gray mb-2">Meta Total *</label>
                    <div class="flex">
                        <input type="number" class="w-full px-3 py-2 border border-silver-lake-blue rounded-l-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="total_goal" name="total_goal" step="0.01" min="0" required placeholder="0,00">
                        <span class="px-3 py-2 border border-l-0 border-silver-lake-blue rounded-r-lg bg-eggshell text-paynes-gray text-sm">toneladas</span>
                    </div>
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-paynes-gray mb-2">Descrição</label>
                    <textarea class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="description" name="description" rows="3" placeholder="Descrição opcional da meta..."></textarea>
                </div>
                <div id="calculationPreview" class="hidden">
                    <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-lg p-4">
                        <div class="font-semibold mb-2"><i class="fas fa-calculator mr-2"></i>Preview dos Cálculos</div>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <div class="text-paynes-gray">Dias no período</div>
                                <div id="totalDays" class="font-semibold text-rich-black">-</div>
                            </div>
                            <div>
                                <div class="text-paynes-gray">Dias úteis</div>
                                <div id="workingDays" class="font-semibold text-rich-black">-</div>
                            </div>
                            <div>
                                <div class="text-paynes-gray">Meta Diária</div>
                                <div id="dailyGoal" class="font-semibold text-rich-black">-</div>
                            </div>
                            <div>
                                <div class="text-paynes-gray">Meta Semanal (aprox.)</div>
                                <div id="weeklyGoal" class="font-semibold text-rich-black">-</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <a href="goals-production.php" class="inline-flex items-center px-4 py-2 border border-silver-lake-blue text-paynes-gray rounded-lg hover:bg-eggshell transition-colors">
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
function calculatePreview() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const totalGoal = parseFloat(document.getElementById('total_goal').value) || 0;
    if (startDate && endDate && totalGoal > 0) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        if (start >= end) { document.getElementById('calculationPreview').style.display = 'none'; return; }
        const timeDiff = end.getTime() - start.getTime();
        const totalDays = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
        let workingDays = 0;
        const cursor = new Date(start);
        while (cursor <= end) {
            const dow = cursor.getDay();
            if (dow !== 0) { workingDays++; }
            cursor.setDate(cursor.getDate() + 1);
        }
        const denominator = Math.max(workingDays, 1);
        const dailyGoal = totalGoal / denominator;
        const totalWeeks = Math.ceil(totalDays / 7);
        const weeklyGoal = totalGoal / totalWeeks;
        document.getElementById('totalDays').textContent = totalDays;
        document.getElementById('workingDays').textContent = workingDays;
        document.getElementById('dailyGoal').textContent = dailyGoal.toFixed(2);
        document.getElementById('weeklyGoal').textContent = weeklyGoal.toFixed(2);
        document.getElementById('calculationPreview').style.display = 'block';
    } else {
        document.getElementById('calculationPreview').style.display = 'none';
    }
}
document.getElementById('start_date').addEventListener('change', calculatePreview);
document.getElementById('end_date').addEventListener('change', calculatePreview);
document.getElementById('total_goal').addEventListener('input', calculatePreview);

document.getElementById('prodGoalForm').addEventListener('submit', function(e) {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const totalGoal = parseFloat(document.getElementById('total_goal').value) || 0;
    if (startDate && endDate && new Date(startDate) >= new Date(endDate)) { e.preventDefault(); alert('A data final deve ser maior que a data inicial.'); return false; }
    if (totalGoal <= 0) { e.preventDefault(); alert('A meta total deve ser maior que zero.'); return false; }
});
</script>

<?php include 'includes/footer.php'; ?>
