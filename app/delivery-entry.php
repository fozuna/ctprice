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
$deliveryModel = new Delivery($db);

$fronts = $frontModel->findAll(['active' => 1]);
$clients = $clientModel->findAll(['active' => 1]);

$currentMonth = $_GET['month'] ?? date('Y-m');
$frontFilter = $_GET['front_id'] ?? '';
$clientFilter = $_GET['client_id'] ?? '';

$monthDate = new DateTime($currentMonth . '-01');
$startDate = $monthDate->format('Y-m-01');
$endDate = $monthDate->format('Y-m-t');

$entries = $deliveryModel->getDeliveryByDateRange($startDate, $endDate, $frontFilter ?: null, $clientFilter ?: null);

$externalUrl = getenv('EXTERNAL_API_DELIVERIES_URL') ?: (defined('EXTERNAL_API_DELIVERIES_URL') ? EXTERNAL_API_DELIVERIES_URL : null);
$externalToken = getenv('EXTERNAL_API_TOKEN')
    ?: getenv('LOGISTICS_DELIVERY_REPORT_API_TOKEN')
    ?: (defined('EXTERNAL_API_TOKEN') ? EXTERNAL_API_TOKEN : null)
    ?: (defined('LOGISTICS_DELIVERY_REPORT_API_TOKEN') ? LOGISTICS_DELIVERY_REPORT_API_TOKEN : null);
$apiReady = !empty($externalUrl) && !empty($externalToken);
$apiHost = $externalUrl ? parse_url($externalUrl, PHP_URL_HOST) : '';

$pageTitle = 'Registrar Entrega Diária';
include 'includes/header.php';
?>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
        <div class="p-6 border-b border-silver-lake-blue">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                        <i class="fas fa-truck-loading text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-rich-black">Registrar Entrega Diária</h1>
                        <p class="text-paynes-gray">Lance a entrega por frente/cliente e dia</p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <a href="goals-delivery.php" class="inline-flex items-center px-4 py-2 border border-silver-lake-blue text-paynes-gray rounded-lg hover:bg-eggshell transition-colors">
                        <i class="fas fa-clipboard-list mr-2"></i>Metas de Entrega
                    </a>
                    <a href="delivery-action.php?action=sync_external&month=<?= urlencode($currentMonth) ?>&front_id=<?= urlencode($frontFilter) ?>&client_id=<?= urlencode($clientFilter) ?>" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                        <i class="fas fa-cloud-download-alt mr-2"></i>Sincronizar API
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
            <?php if (!empty($_SESSION['debug_api_sample'])): ?>
                <div class="mb-4 p-3 rounded bg-blue-50 text-blue-700 border border-blue-200">
                    <i class="fas fa-info-circle mr-2"></i>Campos do primeiro registro retornado pela API: <?= $_SESSION['debug_api_sample']; unset($_SESSION['debug_api_sample']); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error_message'])): ?>
                <div class="mb-4 p-3 rounded bg-red-50 text-red-700 border border-red-200">
                    <i class="fas fa-exclamation-triangle mr-2"></i><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>
            <form action="delivery-action.php" method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <input type="hidden" name="action" value="create" />
                <div>
                    <label for="delivery_date" class="block text-sm font-medium text-paynes-gray mb-2">Data</label>
                    <input type="date" id="delivery_date" name="delivery_date" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="<?= date('Y-m-d') ?>">
                </div>
                <div>
                    <label for="front_id" class="block text-sm font-medium text-paynes-gray mb-2">Frente</label>
                    <select id="front_id" name="front_id" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Selecione a frente</option>
                        <?php foreach ($fronts as $front): ?>
                            <option value="<?= $front['id'] ?>"><?= htmlspecialchars($front['name']) ?> (<?= htmlspecialchars($front['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="client_id" class="block text-sm font-medium text-paynes-gray mb-2">Cliente</label>
                    <select id="client_id" name="client_id" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Selecione o cliente</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['name']) ?> (<?= htmlspecialchars($client['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="delivered_value" class="block text-sm font-medium text-paynes-gray mb-2">Peso total das cargas</label>
                    <input type="number" step="0.01" min="0" id="delivered_value" name="delivered_value" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="0,00" required>
                    <div class="text-xs text-paynes-gray mt-1">Informe o peso total (ex.: toneladas).</div>
                </div>
                <div>
                    <label for="unit" class="block text-sm font-medium text-paynes-gray mb-2">Unidade</label>
                    <input type="text" id="unit" name="unit" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="toneladas">
                </div>
                <div>
                    <label for="loads_count" class="block text-sm font-medium text-paynes-gray mb-2">Quantidade de cargas</label>
                    <input type="number" step="1" min="0" id="loads_count" name="loads_count" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="0" required>
                    <div class="text-xs text-paynes-gray mt-1">Número inteiro de viagens/cargas no dia.</div>
                </div>
                <div class="md:col-span-5">
                    <label for="notes" class="block text-sm font-medium text-paynes-gray mb-2">Observações</label>
                    <textarea id="notes" name="notes" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" rows="2"></textarea>
                    <div id="avgHint" class="text-xs text-paynes-gray mt-1"></div>
                </div>
                <div class="md:col-span-5">
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-save mr-2"></i>Registrar Entrega
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
        <div class="p-6 border-b border-silver-lake-blue">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-rich-black">Importar Entregas da API</h3>
                <div class="flex items-center gap-3">
                    <?php if ($apiReady): ?>
                        <span class="inline-flex items-center px-2 py-1 rounded bg-green-100 text-green-700 border border-green-200 text-sm">
                            <i class="fas fa-check-circle mr-2"></i>API OK • <?= htmlspecialchars($apiHost) ?>
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-2 py-1 rounded bg-yellow-100 text-yellow-800 border border-yellow-200 text-sm">
                            <i class="fas fa-exclamation-triangle mr-2"></i>API não configurada (URL/Token)
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="p-6">
            <form method="GET" action="delivery-action.php" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <input type="hidden" name="action" value="sync_external" />
                <div>
                    <label for="startDate" class="block text-sm font-medium text-paynes-gray mb-2">Data inicial</label>
                    <input type="date" id="startDate" name="startDate" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="<?= $startDate ?>">
                </div>
                <div>
                    <label for="endDate" class="block text-sm font-medium text-paynes-gray mb-2">Data final</label>
                    <input type="date" id="endDate" name="endDate" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="<?= $endDate ?>">
                </div>
                <div>
                    <label for="serviceFront" class="block text-sm font-medium text-paynes-gray mb-2">Frente (código)</label>
                    <select id="serviceFront" name="serviceFront" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todas</option>
                        <?php foreach ($fronts as $front): ?>
                            <option value="<?= htmlspecialchars($front['code']) ?>"><?= htmlspecialchars($front['name']) ?> (<?= htmlspecialchars($front['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="client" class="block text-sm font-medium text-paynes-gray mb-2">Cliente (código)</label>
                    <select id="client" name="client" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todos</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= htmlspecialchars($client['code']) ?>"><?= htmlspecialchars($client['name']) ?> (<?= htmlspecialchars($client['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="transporter" class="block text-sm font-medium text-paynes-gray mb-2">Transportadora</label>
                    <input type="text" id="transporter" name="transporter" placeholder="ALL" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="licensePlate" class="block text-sm font-medium text-paynes-gray mb-2">Placa</label>
                    <input type="text" id="licensePlate" name="licensePlate" placeholder="ALL" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="driver" class="block text-sm font-medium text-paynes-gray mb-2">Motorista</label>
                    <input type="text" id="driver" name="driver" placeholder="ALL" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="invoice" class="block text-sm font-medium text-paynes-gray mb-2">Nota Fiscal</label>
                    <input type="text" id="invoice" name="invoice" placeholder="parcial" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="trip" class="block text-sm font-medium text-paynes-gray mb-2">Viagem</label>
                    <input type="text" id="trip" name="trip" placeholder="sequencial com zeros" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="pageSize" class="block text-sm font-medium text-paynes-gray mb-2">Itens/página</label>
                    <input type="number" id="pageSize" name="pageSize" min="1" max="100" value="100" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="md:col-span-6">
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors <?= $apiReady ? '' : 'opacity-60 cursor-not-allowed' ?>" <?= $apiReady ? '' : 'disabled' ?>>
                        <i class="fas fa-cloud-download-alt mr-2"></i>Importar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
        <div class="p-6 border-b border-silver-lake-blue">
            <h3 class="text-lg font-semibold text-rich-black">Entregas do Mês</h3>
        </div>
        <div class="p-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                <div>
                    <label for="month" class="block text-sm font-medium text-paynes-gray mb-2">Mês/Ano</label>
                    <input type="month" class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="month" name="month" value="<?= $currentMonth ?>">
                </div>
                <div>
                    <label for="filter_front_id" class="block text-sm font-medium text-paynes-gray mb-2">Frente</label>
                    <select class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="filter_front_id" name="front_id">
                        <option value="">Todas as frentes</option>
                        <?php foreach ($fronts as $front): ?>
                            <option value="<?= $front['id'] ?>" <?= $frontFilter == $front['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($front['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filter_client_id" class="block text-sm font-medium text-paynes-gray mb-2">Cliente</label>
                    <select class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="filter_client_id" name="client_id">
                        <option value="">Todos os clientes</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>" <?= $clientFilter == $client['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($client['name']) ?> (<?= htmlspecialchars($client['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-filter mr-2"></i>Filtrar
                    </button>
                </div>
            </form>

            <?php if (empty($entries)) : ?>
                <div class="text-center py-10">
                    <h5 class="text-lg font-medium text-paynes-gray mb-2">Nenhum lançamento de entrega para este período</h5>
                    <p class="text-paynes-gray">Registre uma entrega usando o formulário acima.</p>
                </div>
            <?php else : ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-paynes-gray text-white">
                                <th class="px-4 py-3 text-left font-semibold">Data</th>
                                <th class="px-4 py-3 text-left font-semibold">Frente</th>
                                <th class="px-4 py-3 text-left font-semibold">Cliente</th>
                                <th class="px-4 py-3 text-right font-semibold">Entregue</th>
                                <th class="px-4 py-3 text-center font-semibold">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $row): ?>
                                <tr class="border-b border-eggshell">
                                    <td class="px-4 py-3"><?= date('d/m/Y', strtotime($row['delivery_date'])) ?></td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-rich-black"><?= htmlspecialchars($row['front_name']) ?></div>
                                        <div class="text-sm text-paynes-gray"><?= htmlspecialchars($row['front_code']) ?></div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-rich-black"><?= htmlspecialchars($row['client_name']) ?></div>
                                        <div class="text-sm text-paynes-gray"><?= htmlspecialchars($row['client_code']) ?></div>
                                    </td>
                                    <td class="px-4 py-3 text-right"><span class="font-semibold text-blue-600"><?= number_format($row['delivered_value'], 2, ',', '.') ?></span></td>
                                    <td class="px-4 py-3 text-center">
                                        <a href="delivery-action.php?action=delete&id=<?= $row['id'] ?? '' ?>" class="inline-flex items-center px-2 py-1 border border-red-300 text-red-600 rounded hover:bg-red-50 transition-colors" title="Excluir">
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
document.getElementById('filter_front_id').addEventListener('change', function() { this.form.submit(); });
document.getElementById('filter_client_id').addEventListener('change', function() { this.form.submit(); });
var deliveredInput = document.getElementById('delivered_value');
var loadsInput = document.getElementById('loads_count');
var avgHint = document.getElementById('avgHint');
function updateAvgHint() {
    var delivered = parseFloat(deliveredInput.value);
    var loads = parseInt(loadsInput.value || '0', 10);
    if (avgHint && delivered > 0 && loads > 0) {
        var avg = delivered / loads;
        avgHint.textContent = 'Peso médio por carga: ' + avg.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ' + (document.getElementById('unit').value || '');
    } else if (avgHint) {
        avgHint.textContent = '';
    }
}
if (deliveredInput && loadsInput) {
    deliveredInput.addEventListener('input', updateAvgHint);
    loadsInput.addEventListener('input', updateAvgHint);
}
</script>

<?php include 'includes/footer.php'; ?>
