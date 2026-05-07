<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/models/JobVacancy.php';
require_once __DIR__ . '/classes/AuditLog.php';
require_once __DIR__ . '/models/User.php';

$database = Database::getInstance();
$db = $database->getConnection();
$auth = new Auth($db);

$role = $_SESSION['user_role'] ?? null;
if (!$auth->isLoggedIn() || !in_array($role, [ROLE_ADMIN, ROLE_COORD_RH])) {
    header('Location: dashboard.php');
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$pageTitle = 'Vagas de Trabalho';
$vacancyModel = new JobVacancy($db);
$auditLog = new AuditLog($db);
$userModel = new User($db);

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrfToken, $token)) {
        $message = 'Falha de validação de segurança. Recarregue a página.';
        $messageType = 'error';
    } else {
        try {
            $currentUserId = $_SESSION['user_id'] ?? null;
            if ($currentUserId === null) {
                throw new Exception('Usuário não autenticado.');
            }
            if ($action === 'create' || $action === 'update') {
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $requirements = trim($_POST['requirements'] ?? '');
                $benefits = trim($_POST['benefits'] ?? '');
                $location = trim($_POST['location'] ?? '');
                $contractType = $_POST['contract_type'] ?? '';
                $department = trim($_POST['department'] ?? '');
                $validUntil = $_POST['valid_until'] ?? '';
                $salary = $_POST['salary'] ?? '';
                $totalOffered = isset($_POST['total_offered']) ? (int)$_POST['total_offered'] : 1;
                try {
                    $chk = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_vacancies' AND COLUMN_NAME = 'total_offered'");
                    $chk->execute();
                    if ((int)$chk->fetchColumn() === 0) {
                        $db->exec("ALTER TABLE job_vacancies ADD COLUMN total_offered INT NOT NULL DEFAULT 1");
                    }
                } catch (Throwable $e) {}
                try {
                    $chk = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_vacancies' AND COLUMN_NAME = 'faixa_salarial'");
                    $chk->execute();
                    if ((int)$chk->fetchColumn() === 0) {
                        $db->exec("ALTER TABLE job_vacancies ADD COLUMN faixa_salarial VARCHAR(100) NOT NULL DEFAULT ''");
                        $db->exec("CREATE INDEX idx_job_vacancies_faixa ON job_vacancies(faixa_salarial)");
                    }
                } catch (Throwable $e) {}
                if ($title === '' || $description === '' || $requirements === '' || $location === '' || $contractType === '' || $department === '' || $validUntil === '') {
                    throw new Exception('Preencha todos os campos obrigatórios.');
                }
                if (!in_array($contractType, ['CLT', 'PJ', 'INTEGRAL', 'MEIO_PERIODO'], true)) {
                    throw new Exception('Tipo de contratação inválido.');
                }
                $status = $_POST['status'] ?? 'ATIVA';
                if (!in_array($status, ['ATIVA', 'INATIVA'], true)) {
                    $status = 'ATIVA';
                }
                $allowedSalary = [
                  'Salário competitivo',
                  'A combinar',
                  'Faixa salarial compatível com o mercado',
                  'Remuneração atrativa',
                  'Nível Júnior',
                  'Nível Pleno',
                  'Nível Sênior',
                  'Nível Executivo'
                ];
                if ($salary !== '' && !in_array($salary, $allowedSalary, true)) {
                    throw new Exception('Descrição de salário inválida.');
                }
                if ($totalOffered < 1 || $totalOffered > 99) {
                    throw new Exception('Quantidade de vagas ofertadas deve ser entre 1 e 99.');
                }
                $data = [
                    'title' => $title,
                    'description' => $description,
                    'requirements' => $requirements,
                    'salary' => null,
                    'faixa_salarial' => $salary === '' ? '' : $salary,
                    'benefits' => $benefits === '' ? null : $benefits,
                    'location' => $location,
                    'contract_type' => $contractType,
                    'department' => $department,
                    'status' => $status,
                    'valid_until' => $validUntil,
                    'total_offered' => $totalOffered
                ];
                if ($action === 'create') {
                    $data['created_by'] = $currentUserId;
                    $id = $vacancyModel->create($data);
                    if ($id) {
                        $auditLog->logInsert('job_vacancies', $id, $data, $currentUserId);
                        $message = 'Vaga criada com sucesso.';
                        $messageType = 'success';
                    } else {
                        throw new Exception('Erro ao criar vaga.');
                    }
                } else {
                    $id = intval($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        throw new Exception('Vaga inválida para atualização.');
                    }
                    $old = $vacancyModel->findById($id);
                    $data['updated_by'] = $currentUserId;
                    if ($vacancyModel->update($id, $data)) {
                        $auditLog->logUpdate('job_vacancies', $id, $old, $data, $currentUserId);
                        $message = 'Vaga atualizada com sucesso.';
                        $messageType = 'success';
                    } else {
                        throw new Exception('Erro ao atualizar vaga.');
                    }
                }
            } elseif ($action === 'toggle_status') {
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception('Vaga inválida.');
                }
                $vacancy = $vacancyModel->findById($id);
                if (!$vacancy) {
                    throw new Exception('Vaga não encontrada.');
                }
                $newStatus = $vacancy['status'] === 'ATIVA' ? 'INATIVA' : 'ATIVA';
                $data = ['status' => $newStatus, 'updated_by' => $_SESSION['user_id'] ?? null];
                if ($vacancyModel->update($id, $data)) {
                    $auditLog->logUpdate('job_vacancies', $id, $vacancy, $data, $_SESSION['user_id'] ?? null);
                    $message = $newStatus === 'ATIVA' ? 'Vaga ativada.' : 'Vaga inativada.';
                    $messageType = 'success';
                } else {
                    throw new Exception('Erro ao alterar status da vaga.');
                }
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
}

$statusFilter = $_GET['status'] ?? '';
$departmentFilter = $_GET['department'] ?? '';
$dateFilter = $_GET['date'] ?? '';

$conditions = [];
if ($statusFilter !== '') {
    $conditions['status'] = $statusFilter;
}

$vacancies = $vacancyModel->findAll($conditions, 'valid_until DESC');

if ($departmentFilter !== '') {
    $vacancies = array_filter($vacancies, function ($row) use ($departmentFilter) {
        return isset($row['department']) && $row['department'] === $departmentFilter;
    });
}

if ($dateFilter !== '') {
    $vacancies = array_filter($vacancies, function ($row) use ($dateFilter) {
        return isset($row['valid_until']) && $row['valid_until'] >= $dateFilter;
    });
}

include 'includes/header.php';
?>

<div class="space-y-6">
    <?php if ($message): ?>
        <div class="p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
            <form method="GET" class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
                <select name="status" class="px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                    <option value="">Todos os status</option>
                    <option value="ATIVA" <?php echo $statusFilter === 'ATIVA' ? 'selected' : ''; ?>>Ativa</option>
                    <option value="INATIVA" <?php echo $statusFilter === 'INATIVA' ? 'selected' : ''; ?>>Inativa</option>
                </select>
                <input type="text" name="department" value="<?php echo htmlspecialchars($departmentFilter); ?>" placeholder="Departamento" class="px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                <input type="date" name="date" value="<?php echo htmlspecialchars($dateFilter); ?>" class="px-4 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                <button type="submit" class="px-4 py-2 bg-paynes-gray text-white rounded-lg hover:bg-prussian-blue transition-colors">
                    Filtrar
                </button>
            </form>
            <a href="rh-vaga.php?mode=create" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                Nova Vaga
            </a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-silver-lake-blue">
                <thead class="bg-eggshell">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Título</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Departamento</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Localização</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Tipo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Validade</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-paynes-gray uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-silver-lake-blue">
                    <?php if (empty($vacancies)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-paynes-gray">
                                Nenhuma vaga encontrada.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vacancies as $vacancy): ?>
                            <tr class="hover:bg-eggshell">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-rich-black">
                                    <?php echo htmlspecialchars($vacancy['title']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-rich-black">
                                    <?php echo htmlspecialchars($vacancy['department']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-rich-black">
                                    <?php echo htmlspecialchars($vacancy['location']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-rich-black">
                                    <?php echo htmlspecialchars($vacancy['contract_type']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-rich-black">
                                    <?php echo htmlspecialchars(date('d/m/Y', strtotime($vacancy['valid_until']))); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $vacancy['status'] === 'ATIVA' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $vacancy['status'] === 'ATIVA' ? 'Ativa' : 'Inativa'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <a href="rh-vaga.php?mode=edit&id=<?php echo (int)$vacancy['id']; ?>" class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" class="inline" onsubmit="return confirm('Tem certeza?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?php echo $vacancy['id']; ?>">
                                        <button type="submit" class="<?php echo $vacancy['status'] === 'ATIVA' ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900'; ?>">
                                            <i class="fas <?php echo $vacancy['status'] === 'ATIVA' ? 'fa-ban' : 'fa-check'; ?>"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal removido em favor da página dedicada rh-vaga.php -->

<script>
// Ações migradas para navegação na página dedicada (rh-vaga.php)
</script>

<?php include 'includes/footer.php'; ?>
