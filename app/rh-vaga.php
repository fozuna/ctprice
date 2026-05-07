<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/models/JobVacancy.php';
require_once __DIR__ . '/classes/AuditLog.php';
require_once __DIR__ . '/models/User.php';

$db = Database::getInstance()->getConnection();
$auth = new Auth($db);
$role = $_SESSION['user_role'] ?? null;
if (!$auth->isLoggedIn() || !in_array($role, [ROLE_ADMIN, ROLE_COORD_RH], true)) {
    header('Location: dashboard.php'); exit;
}
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = $_SESSION['csrf_token'];

$vacancyModel = new JobVacancy($db);
$auditLog = new AuditLog($db);
$userModel = new User($db);

$__ensureVacancyTotalOffered = function(PDO $db) {
    try {
        $chk = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_vacancies' AND COLUMN_NAME = 'total_offered'");
        $chk->execute();
        if ((int)$chk->fetchColumn() === 0) {
            $db->exec("ALTER TABLE job_vacancies ADD COLUMN total_offered INT NOT NULL DEFAULT 1");
        }
    } catch (Throwable $e) {
        // Ignorar: ambiente pode não permitir ALTER; migrador disponível em scripts/migrate_job_vacancies_add_total_offered.php
    }
};
$__ensureVacancyFaixa = function(PDO $db) {
    try {
        $chk = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_vacancies' AND COLUMN_NAME = 'faixa_salarial'");
        $chk->execute();
        if ((int)$chk->fetchColumn() === 0) {
            $db->exec("ALTER TABLE job_vacancies ADD COLUMN faixa_salarial VARCHAR(100) NOT NULL DEFAULT ''");
            $db->exec("CREATE INDEX idx_job_vacancies_faixa ON job_vacancies(faixa_salarial)");
        }
    } catch (Throwable $e) {}
};

$mode = ($_GET['mode'] ?? 'create');
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = ''; $messageType = '';
$currentUserId = $_SESSION['user_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrfToken, $token)) {
        $message = 'Falha de validação de segurança. Recarregue a página.'; $messageType = 'error';
    } else {
        try {
            if ($action === 'save') {
                $id = (int)($_POST['id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $requirements = trim($_POST['requirements'] ?? '');
                $benefits = trim($_POST['benefits'] ?? '');
                $location = trim($_POST['location'] ?? '');
                $contractType = $_POST['contract_type'] ?? '';
                $department = trim($_POST['department'] ?? '');
                $validUntil = $_POST['valid_until'] ?? '';
                $salary = $_POST['salary'] ?? '';
                $status = $_POST['status'] ?? 'ATIVA';
                $totalOffered = isset($_POST['total_offered']) ? (int)$_POST['total_offered'] : 1;
                if ($title === '' || $description === '' || $requirements === '' || $location === '' || $contractType === '' || $department === '' || $validUntil === '') {
                    throw new Exception('Preencha todos os campos obrigatórios.');
                }
                if (!in_array($contractType, ['CLT', 'PJ', 'INTEGRAL', 'MEIO_PERIODO'], true)) {
                    throw new Exception('Tipo de contratação inválido.');
                }
                if (!in_array($status, ['ATIVA', 'INATIVA'], true)) { $status = 'ATIVA'; }
                if ($totalOffered < 1 || $totalOffered > 99) { throw new Exception('Quantidade de vagas ofertadas deve ser entre 1 e 99.'); }
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
                $__ensureVacancyFaixa($db);
                $__ensureVacancyTotalOffered($db);
                $data = [
                    'title' => $title,
                    'description' => $description,
                    'requirements' => $requirements,
                    // manter compatibilidade: salary legado não será mais utilizado para exibição
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
                if ($id > 0) {
                    $old = $vacancyModel->findById($id);
                    $data['updated_by'] = $currentUserId;
                    if ($vacancyModel->update($id, $data)) {
                        $auditLog->logUpdate('job_vacancies', $id, $old, $data, $currentUserId);
                        $message = 'Vaga atualizada com sucesso.'; $messageType = 'success';
                        $mode = 'edit';
                    } else {
                        throw new Exception('Erro ao atualizar vaga.');
                    }
                } else {
                    $data['created_by'] = $currentUserId;
                    $newId = (int)$vacancyModel->create($data);
                    if ($newId > 0) {
                        $auditLog->logInsert('job_vacancies', $newId, $data, $currentUserId);
                        header('Location: rh-vaga.php?mode=view&id=' . $newId); exit;
                    }
                    throw new Exception('Erro ao criar vaga.');
                }
            } elseif ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Vaga inválida para exclusão.');
                $old = $vacancyModel->findById($id);
                if (!$old) throw new Exception('Vaga não encontrada.');
                if ($vacancyModel->delete($id)) {
                    $auditLog->logDelete('job_vacancies', $id, $old, $currentUserId);
                    header('Location: rh-vagas.php?msg=Vaga%20excluida'); exit;
                }
                throw new Exception('Erro ao excluir vaga.');
            }
        } catch (Exception $e) { $message = $e->getMessage(); $messageType = 'error'; }
    }
}

$vacancy = null;
if (in_array($mode, ['edit','view'], true)) {
    if ($id <= 0) { header('Location: rh-vagas.php'); exit; }
    $vacancy = $vacancyModel->findById($id);
    if (!$vacancy) { header('Location: rh-vagas.php?msg=Vaga%20nao%20encontrada'); exit; }
}

$pageTitle = ($mode === 'create' ? 'Nova Vaga' : ($mode === 'edit' ? 'Editar Vaga' : 'Vaga'));
include __DIR__ . '/includes/header.php';
?>
<main id="main-content" class="p-6">
  <div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
      <nav class="text-sm text-paynes-gray">
        <a href="rh-vagas.php" class="hover:underline">Vagas</a>
        <span class="mx-2">/</span>
        <span><?php echo htmlspecialchars($pageTitle); ?></span>
      </nav>
      <div class="space-x-2">
        <a href="rh-vagas.php" class="px-3 py-1.5 rounded bg-eggshell border border-silver-lake-blue text-paynes-gray hover:bg-silver-lake-blue hover:text-white">Voltar</a>
        <?php if ($mode !== 'create' && $vacancy): ?>
          <form method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja excluir?');">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?php echo (int)$vacancy['id']; ?>">
            <button type="submit" class="px-3 py-1.5 rounded bg-red-600 text-white hover:bg-red-700">Excluir</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($message): ?>
      <div class="p-3 rounded <?php echo $messageType==='success'?'bg-green-100 text-green-800':'bg-red-100 text-red-800'; ?>">
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <section class="lg:col-span-2 bg-white border rounded-lg">
        <header class="px-6 pt-6">
          <h1 class="text-xl font-semibold text-rich-black"><?php echo htmlspecialchars($pageTitle); ?></h1>
          <p class="text-xs text-paynes-gray">Preencha os campos obrigatórios marcados com *</p>
        </header>
        <form method="POST" id="vacancyForm" class="modal-body">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?php echo $vacancy['id'] ?? ''; ?>">
          <div class="space-y-4">
            <div>
              <label class="form-label">Título *</label>
              <input type="text" name="title" class="form-input" required value="<?php echo htmlspecialchars($vacancy['title'] ?? ''); ?>">
            </div>
            <div>
              <label class="form-label">Descrição *</label>
              <textarea name="description" class="form-textarea" rows="3" required><?php echo htmlspecialchars($vacancy['description'] ?? ''); ?></textarea>
            </div>
            <div>
              <label class="form-label">Requisitos *</label>
              <textarea name="requirements" class="form-textarea" rows="3" required><?php echo htmlspecialchars($vacancy['requirements'] ?? ''); ?></textarea>
            </div>
            <div>
              <label class="form-label">Benefícios</label>
              <textarea name="benefits" class="form-textarea" rows="2"><?php echo htmlspecialchars($vacancy['benefits'] ?? ''); ?></textarea>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="form-label">Faixa de salário</label>
                <?php $sal = $vacancy['faixa_salarial'] ?? ($vacancy['salary'] ?? ''); ?>
                <select name="salary" class="form-select">
                  <option value="">Selecione</option>
                  <option <?php echo $sal==='Salário competitivo'?'selected':''; ?>>Salário competitivo</option>
                  <option <?php echo $sal==='A combinar'?'selected':''; ?>>A combinar</option>
                  <option <?php echo $sal==='Faixa salarial compatível com o mercado'?'selected':''; ?>>Faixa salarial compatível com o mercado</option>
                  <option <?php echo $sal==='Remuneração atrativa'?'selected':''; ?>>Remuneração atrativa</option>
                  <option <?php echo $sal==='Nível Júnior'?'selected':''; ?>>Nível Júnior</option>
                  <option <?php echo $sal==='Nível Pleno'?'selected':''; ?>>Nível Pleno</option>
                  <option <?php echo $sal==='Nível Sênior'?'selected':''; ?>>Nível Sênior</option>
                  <option <?php echo $sal==='Nível Executivo'?'selected':''; ?>>Nível Executivo</option>
                </select>
              </div>
              <div>
                <label class="form-label">Localização *</label>
                <input type="text" name="location" class="form-input" required value="<?php echo htmlspecialchars($vacancy['location'] ?? ''); ?>">
              </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="form-label">Quantidade de Vagas Ofertadas *</label>
                <input type="number" name="total_offered" min="1" max="99" class="form-input" required value="<?php echo htmlspecialchars($vacancy['total_offered'] ?? '1'); ?>">
              </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="form-label">Tipo de contratação *</label>
                <select name="contract_type" class="form-select" required>
                  <?php $ct = $vacancy['contract_type'] ?? ''; ?>
                  <option value="">Selecione</option>
                  <option value="CLT" <?php echo $ct==='CLT'?'selected':''; ?>>CLT</option>
                  <option value="PJ" <?php echo $ct==='PJ'?'selected':''; ?>>PJ</option>
                  <option value="INTEGRAL" <?php echo $ct==='INTEGRAL'?'selected':''; ?>>Tempo integral</option>
                  <option value="MEIO_PERIODO" <?php echo $ct==='MEIO_PERIODO'?'selected':''; ?>>Meio período</option>
                </select>
              </div>
              <div>
                <label class="form-label">Departamento *</label>
                <input type="text" name="department" class="form-input" required value="<?php echo htmlspecialchars($vacancy['department'] ?? ''); ?>">
              </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="form-label">Validade da vaga *</label>
                <input type="date" name="valid_until" class="form-input" required value="<?php echo htmlspecialchars($vacancy['valid_until'] ?? ''); ?>">
              </div>
              <div>
                <label class="form-label">Status</label>
                <?php $st = $vacancy['status'] ?? 'ATIVA'; ?>
                <select name="status" class="form-select">
                  <option value="ATIVA" <?php echo $st==='ATIVA'?'selected':''; ?>>Ativa</option>
                  <option value="INATIVA" <?php echo $st==='INATIVA'?'selected':''; ?>>Inativa</option>
                </select>
              </div>
            </div>
          </div>
          <div class="modal-actions flex justify-end gap-2">
            <a href="rh-vagas.php" class="btn-secondary">Cancelar</a>
            <?php if ($mode === 'view'): ?>
              <a href="rh-vaga.php?mode=edit&id=<?php echo (int)$vacancy['id']; ?>" class="btn-primary bg-gray-600 hover:bg-gray-700">Editar</a>
            <?php else: ?>
              <button type="submit" class="btn-primary">Salvar</button>
            <?php endif; ?>
          </div>
        </form>
      </section>
      <aside class="bg-white border rounded-lg p-4">
        <h2 class="text-sm font-semibold mb-2">Últimas vagas</h2>
        <div class="space-y-2">
          <?php
            try {
              $latest = $vacancyModel->findAll([], 'valid_until DESC', 8);
              foreach ($latest as $row) {
                $idRow = (int)$row['id'];
                echo '<div class="border border-silver-lake-blue rounded p-2 flex items-start justify-between">';
                echo '<div><div class="text-sm font-medium">'.htmlspecialchars($row['title']).'</div><div class="text-[11px] text-paynes-gray">'.htmlspecialchars($row['department'] ?? '-') . ' • ' . htmlspecialchars($row['location'] ?? '-') . '</div></div>';
                echo '<div class="text-right space-x-1">';
                echo '<a href="rh-vaga.php?mode=view&id='.$idRow.'" class="text-blue-700 text-xs hover:underline">Ver</a>';
                echo '<a href="rh-vaga.php?mode=edit&id='.$idRow.'" class="text-blue-700 text-xs hover:underline">Editar</a>';
                echo '</div></div>';
              }
            } catch (Throwable $e) {
              echo '<div class="text-xs text-red-700">Erro ao carregar últimas vagas</div>';
            }
          ?>
        </div>
      </aside>
    </div>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
