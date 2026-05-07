<?php
/**
 * Saldos Iniciais (Estoque)
 * Sistema BDO - Controle de Maquinários
 */
require_once __DIR__ . '/config/config.php';

$database = Database::getInstance();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}
// Permissão mínima: Líder
if (!$auth->hasPermission(ROLE_LIDER)) {
    header('Location: dashboard-entregas.php');
    exit();
}

$pageTitle = 'Saldos Iniciais';
$inventoryModel = new Inventory($db);
$frontModel = new Front($db);

$fronts = $frontModel->findActive();
$userFrontId = $_SESSION['user_front'] ?? null;

// Filtros
$selectedFront = isset($_GET['front_id']) ? intval($_GET['front_id']) : ($userFrontId ? intval($userFrontId) : 0);
$refDate = $_GET['ref_date'] ?? date('Y-m-01');

// Ação: upsert saldo inicial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upsert_opening_balance') {
    $frontId = intval($_POST['front_id'] ?? 0);
    $balanceDate = $_POST['balance_date'] ?? $refDate;
    $value = floatval($_POST['opening_value'] ?? 0);
    $unit = trim($_POST['unit'] ?? 'toneladas');
    $notes = trim($_POST['notes'] ?? '');
    $userId = $_SESSION['user_id'] ?? null;
    if ($frontId <= 0) {
        $_SESSION['error'] = 'Selecione uma frente válida.';
    } elseif (!$balanceDate || strtotime($balanceDate) === false) {
        $_SESSION['error'] = 'Informe uma data válida para o saldo.';
    } else {
        try {
            $inventoryModel->upsertOpeningBalance($frontId, $balanceDate, $value, $unit ?: 'toneladas', $notes ?: null, $userId);
            $_SESSION['success'] = 'Saldo inicial salvo com sucesso.';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Falha ao salvar saldo inicial: ' . $e->getMessage();
        }
    }
    // Redirecionar para evitar repost
    $qs = [
        'front_id' => $frontId ?: $selectedFront,
        'ref_date' => $balanceDate ?: $refDate,
    ];
    header('Location: opening-balance.php?' . http_build_query($qs));
    exit();
}

// Buscar últimos saldos para a frente selecionada (se houver)
$recentBalances = [];
if ($selectedFront > 0) {
    try {
        $sql = "SELECT f.name as front_name, b.balance_date, b.opening_balance_value, b.unit, b.notes
                FROM front_opening_balance b
                JOIN fronts f ON f.id = b.front_id
                WHERE b.front_id = :front_id
                ORDER BY b.balance_date DESC
                LIMIT 20";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':front_id', $selectedFront, PDO::PARAM_INT);
        $stmt->execute();
        $recentBalances = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        $recentBalances = [];
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6">
        <h2 class="text-lg font-semibold text-rich-black mb-4">Lançar/Atualizar Saldo Inicial</h2>

        <?php if (!empty($_SESSION['success'])): ?>
            <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="upsert_opening_balance">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="form-label">Frente</label>
                    <select name="front_id" class="form-select" required <?php echo (!$auth->hasPermission(ROLE_ADMIN) && !$auth->hasPermission(ROLE_SUPERVISOR)) ? '' : ''; ?>>
                        <option value="">Selecione</option>
                        <?php foreach ($fronts as $front): ?>
                            <?php
                            $disabledByRole = (!$auth->hasPermission(ROLE_ADMIN) && !$auth->hasPermission(ROLE_SUPERVISOR) && $userFrontId && intval($front['id']) !== intval($userFrontId));
                            ?>
                            <option value="<?php echo $front['id']; ?>" <?php echo intval($selectedFront) === intval($front['id']) ? 'selected' : ''; ?> <?php echo $disabledByRole ? 'disabled' : ''; ?>>
                                <?php echo htmlspecialchars($front['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$auth->hasPermission(ROLE_ADMIN) && !$auth->hasPermission(ROLE_SUPERVISOR) && $userFrontId): ?>
                        <p class="text-xs text-paynes-gray mt-1">Sua permissão limita a esta frente.</p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="form-label">Data do Saldo</label>
                    <input type="date" name="balance_date" value="<?php echo htmlspecialchars($refDate); ?>" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Valor (ton)</label>
                    <input type="number" step="0.01" name="opening_value" class="form-input" placeholder="0,00" required>
                </div>
                <div>
                    <label class="form-label">Unidade</label>
                    <input type="text" name="unit" class="form-input" value="toneladas">
                </div>
                <div class="md:col-span-2 lg:col-span-4">
                    <label class="form-label">Observações</label>
                    <input type="text" name="notes" class="form-input" placeholder="Opcional">
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save mr-2"></i>Salvar Saldo Inicial
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue">
        <div class="p-6 border-b border-silver-lake-blue">
            <h2 class="text-lg font-semibold text-rich-black">Saldos Recentes</h2>
            <p class="text-sm text-paynes-gray">Histórico dos últimos lançamentos para a frente selecionada.</p>
        </div>
        <div class="overflow-x-auto">
            <?php if (empty($recentBalances)): ?>
                <div class="p-6 text-center text-paynes-gray">Nenhum saldo encontrado para a frente.</div>
            <?php else: ?>
            <table class="min-w-full divide-y divide-silver-lake-blue">
                <thead class="bg-eggshell">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Frente</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Data</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-paynes-gray uppercase tracking-wider">Saldo Inicial</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Unidade</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Observações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-silver-lake-blue">
                    <?php foreach ($recentBalances as $row): ?>
                        <tr class="hover:bg-eggshell">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-rich-black">
                                <?php echo htmlspecialchars($row['front_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-rich-black">
                                <?php echo date('d/m/Y', strtotime($row['balance_date'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-rich-black">
                                <?php echo number_format((float)$row['opening_balance_value'], 2, ',', '.'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-rich-black">
                                <?php echo htmlspecialchars($row['unit'] ?? ''); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-paynes-gray">
                                <?php echo htmlspecialchars($row['notes'] ?? ''); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

