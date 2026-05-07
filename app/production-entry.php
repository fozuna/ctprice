<?php
require_once 'config/config.php';

$database = Database::getInstance();
$db = $database->getConnection();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$frontModel = new Front($db);
$clientModel = new Client($db);
$productionModel = new Production($db);

$fronts = $frontModel->findAll(['active' => 1]);
$clients = $clientModel->findAll(['active' => 1]);

$currentMonth = $_GET['month'] ?? date('Y-m');
$frontFilter = $_GET['front_id'] ?? '';
$clientFilter = $_GET['client_id'] ?? '';

$monthDate = new DateTime($currentMonth . '-01');
$startDate = $monthDate->format('Y-m-01');
$endDate = $monthDate->format('Y-m-t');

$entries = $productionModel->getProductionByDateRange($startDate, $endDate, $frontFilter ?: null, $clientFilter ?: null);

$pageTitle = 'Registrar Produção Diária';
include 'includes/header.php';
?>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
        <div class="p-6 border-b border-silver-lake-blue">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                        <i class="fas fa-clipboard-check text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-rich-black">Registrar Produção Diária</h1>
                        <p class="text-paynes-gray">Lance a produção por frente e dia</p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <a href="goals-production.php" class="inline-flex items-center px-4 py-2 border border-silver-lake-blue text-paynes-gray rounded-lg hover:bg-eggshell transition-colors">
                        <i class="fas fa-industry mr-2"></i>Metas de Produção
                    </a>
                </div>
            </div>
        </div>
        <div class="p-6">
            <?php if (!empty($_SESSION['success_message'])): ?>
                <div class="mb-4 p-3 rounded bg-green-50 text-green-700 border border-green-200">
                    <i class="fas fa-check-circle mr-2"></i><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error_message'])): ?>
                <div class="mb-4 p-3 rounded bg-red-50 text-red-700 border border-red-200">
                    <i class="fas fa-exclamation-triangle mr-2"></i><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>
            <form action="production-action.php" method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <input type="hidden" name="action" value="create" />
                <div>
                    <label class="block text-sm font-medium text-paynes-gray mb-2">Data</label>
                    <input type="date" name="production_date" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-paynes-gray mb-2">Frente</label>
                    <select name="front_id" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg" required>
                        <option value="">Selecione</option>
                        <?php foreach ($fronts as $front): ?>
                            <option value="<?= $front['id'] ?>"><?= htmlspecialchars($front['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-paynes-gray mb-2">Cliente (opcional)</label>
                    <select name="client_id" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg">
                        <option value="">Selecione</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-paynes-gray mb-2">Produção</label>
                    <input type="number" step="0.01" name="produced_value" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg" placeholder="0,00" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-paynes-gray mb-2">Unidade</label>
                    <select name="unit" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg">
                        <option value="toneladas">Toneladas</option>
                        <option value="m3">m³</option>
                        <option value="unidades">Unidades</option>
                    </select>
                </div>
                <div class="md:col-span-5">
                    <label class="block text-sm font-medium text-paynes-gray mb-2">Observações</label>
                    <input type="text" name="notes" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg" placeholder="Opcional">
                </div>
                <div class="md:col-span-5">
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-save mr-2"></i>Salvar Produção
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Listagem de Produções do mês -->
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
        <div class="p-6 border-b border-silver-lake-blue">
            <h3 class="text-lg font-semibold text-rich-black">Produções do mês</h3>
        </div>
        <div class="p-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                <div>
                    <label for="month" class="block text-sm font-medium text-paynes-gray mb-2">Mês/Ano</label>
                    <input type="month" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg" id="month" name="month" value="<?= $currentMonth ?>">
                </div>
                <div>
                    <label for="front_id" class="block text-sm font-medium text-paynes-gray mb-2">Frente</label>
                    <select class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg" id="front_id" name="front_id">
                        <option value="">Todas</option>
                        <?php foreach ($fronts as $front): ?>
                            <option value="<?= $front['id'] ?>" <?= $frontFilter == $front['id'] ? 'selected' : '' ?>><?= htmlspecialchars($front['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="client_id" class="block text-sm font-medium text-paynes-gray mb-2">Cliente</label>
                    <select class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg" id="client_id" name="client_id">
                        <option value="">Todos</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>" <?= $clientFilter == $client['id'] ? 'selected' : '' ?>><?= htmlspecialchars($client['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-filter mr-2"></i>Filtrar
                    </button>
                </div>
            </form>

            <?php if (empty($entries)): ?>
                <div class="text-center py-10">
                    <p class="text-paynes-gray mb-2">Nenhuma produção registrada para o período.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-paynes-gray text-white">
                                <th class="px-4 py-3 text-left font-semibold">Data</th>
                                <th class="px-4 py-3 text-left font-semibold">Frente</th>
                                <th class="px-4 py-3 text-left font-semibold">Cliente</th>
                                <th class="px-4 py-3 text-right font-semibold">Produção</th>
                                <th class="px-4 py-3 text-center font-semibold">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $row): ?>
                                <tr class="border-b border-eggshell">
                                    <td class="px-4 py-3"><?= date('d/m/Y', strtotime($row['production_date'])) ?></td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-rich-black"><?= htmlspecialchars($row['front_name']) ?></div>
                                        <div class="text-sm text-paynes-gray"><?= htmlspecialchars($row['front_code']) ?></div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-rich-black"><?= htmlspecialchars($row['client_name'] ?? '-') ?></div>
                                        <div class="text-sm text-paynes-gray"><?= htmlspecialchars($row['client_code'] ?? '') ?></div>
                                    </td>
                                    <td class="px-4 py-3 text-right"><span class="font-semibold text-blue-600"><?= number_format($row['produced_value'], 2, ',', '.') ?></span></td>
                                    <td class="px-4 py-3 text-center">
                                        <a href="production-action.php?action=delete&id=<?= $row['row_id'] ?? '' ?>" class="inline-flex items-center px-2 py-1 border border-red-300 text-red-600 rounded hover:bg-red-50 transition-colors" title="Excluir" onclick="return confirm('Confirma excluir a produção de <?= date('d/m/Y', strtotime($row['production_date'])) ?>?');">
                                            <i class="fas fa-trash text-sm"></i>
                                        </a>
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

<script>
document.getElementById('month').addEventListener('change', function() { this.form.submit(); });
document.getElementById('front_id').addEventListener('change', function() { this.form.submit(); });
document.getElementById('client_id').addEventListener('change', function() { this.form.submit(); });
</script>

<?php include 'includes/footer.php'; ?>
