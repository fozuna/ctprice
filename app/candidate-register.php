<?php
require_once __DIR__ . '/config/config.php';
if (file_exists(__DIR__ . '/includes/helpers.php')) {
    require_once __DIR__ . '/includes/helpers.php';
}
if (file_exists(__DIR__ . '/config/Database.php')) {
    require_once __DIR__ . '/config/Database.php';
}
if (!class_exists('BaseModel')) {
    $bm = __DIR__ . '/classes/BaseModel.php';
    if (is_file($bm)) {
        require_once $bm;
    }
}
if (!class_exists('JobVacancy')) {
    $jm = __DIR__ . '/models/JobVacancy.php';
    if (is_file($jm)) {
        require_once $jm;
    }
}

function cr_generate_form_token(): string {
    $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64_encode(json_encode([
        'iss' => 'candidate-register',
        'iat' => time(),
        'exp' => time() + 600
    ]));
    $signature = hash_hmac('sha256', $header . '.' . $payload, JWT_SECRET, true);
    return $header . '.' . $payload . '.' . base64_encode($signature);
}

function cr_validate_form_token(?string $token): bool {
    if (!$token) return false;
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;
    [$h, $p, $s] = $parts;
    $sig = base64_decode($s, true);
    if ($sig === false) return false;
    $calc = hash_hmac('sha256', $h . '.' . $p, JWT_SECRET, true);
    if (!hash_equals($sig, $calc)) return false;
    $payload = json_decode(base64_decode($p), true);
    if (!is_array($payload) || !isset($payload['exp'])) return false;
    return $payload['exp'] >= time();
}

function cr_verify_recaptcha(string $response): array {
    $secret = defined('RECAPTCHA_SECRET') ? RECAPTCHA_SECRET : '';
    if ($secret === '' || $response === '') return ['ok' => false, 'message' => 'Confirme o reCAPTCHA.'];
    $payload = http_build_query([
        'secret' => $secret,
        'response' => $response,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);
    $ok = false; $data = null;
    if (function_exists('curl_init')) {
        $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 7);
        $res = curl_exec($ch);
        if ($res !== false) { $data = json_decode($res, true); $ok = is_array($data) && !empty($data['success']); }
        curl_close($ch);
    }
    if (!$ok) {
        $opts = ['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $payload, 'timeout' => 7]];
        $ctx = stream_context_create($opts);
        $res = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
        if ($res !== false) { $data = json_decode($res, true); $ok = is_array($data) && !empty($data['success']); }
    }
    return $ok ? ['ok' => true] : ['ok' => false, 'message' => 'Falha na validação do reCAPTCHA.'];
}

function cr_is_api_request(): bool {
    $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
    $xhr = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    $format = strtolower((string)($_GET['format'] ?? $_POST['format'] ?? ''));
    return strpos($accept, 'application/json') !== false || $xhr === 'xmlhttprequest' || $format === 'json';
}

function cr_send_api_response(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cr_find_or_create_talent_pool_vacancy(PDO $db): int {
    $sel = $db->prepare("SELECT id FROM job_vacancies WHERE title = :t AND status = 'ATIVA' ORDER BY id ASC LIMIT 1");
    $sel->execute([':t' => 'Banco de Talentos']);
    $found = (int)($sel->fetchColumn() ?: 0);
    if ($found > 0) {
        return $found;
    }

    $creatorId = (int)$db->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($creatorId <= 0) {
        throw new RuntimeException('Não existe usuário válido para vincular a vaga Banco de Talentos.');
    }

    $colStmt = $db->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_vacancies'");
    $colStmt->execute();
    $columns = $colStmt->fetchAll(PDO::FETCH_COLUMN);
    if (!is_array($columns) || empty($columns)) {
        throw new RuntimeException('Tabela job_vacancies não encontrada ou sem colunas disponíveis.');
    }
    $columns = array_map('strtolower', $columns);

    $data = [
        'title' => 'Banco de Talentos',
        'description' => 'Cadastro contínuo de candidatos para futuras oportunidades.',
        'requirements' => 'Cadastro espontâneo.',
        'salary' => '0',
        'faixa_salarial' => 'A combinar',
        'benefits' => '',
        'location' => 'A definir',
        'contract_type' => 'CLT',
        'department' => 'Banco de Talentos',
        'status' => 'ATIVA',
        'valid_until' => date('Y-m-d', strtotime('+5 years')),
        'created_by' => $creatorId,
        'total_offered' => 1
    ];

    $insertCols = [];
    $insertParams = [];
    foreach ($data as $col => $value) {
        if (in_array($col, $columns, true)) {
            $insertCols[] = $col;
            $insertParams[':' . $col] = $value;
        }
    }
    if (empty($insertCols)) {
        throw new RuntimeException('Não foi possível montar INSERT para a vaga Banco de Talentos.');
    }

    $sql = 'INSERT INTO job_vacancies (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', array_keys($insertParams)) . ')';
    $ins = $db->prepare($sql);
    $ins->execute($insertParams);

    return (int)$db->lastInsertId();
}

function cr_validate_candidate_input(array $input, string $mode): array {
    $errors = [];
    $name = trim((string)($input['name'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $phone = preg_replace('/\D+/', '', (string)($input['phone'] ?? ''));
    $cpf = trim((string)($input['cpf'] ?? ''));
    $desiredRole = trim((string)($input['desired_role'] ?? ''));
    $linkedin = trim((string)($input['linkedin'] ?? ''));

    if ($name === '') {
        $errors['name'] = 'Informe seu nome completo.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Informe um email válido.';
    }
    if ($phone === '') {
        $errors['phone'] = 'Informe um telefone para contato.';
    } elseif (!preg_match('/^\d{10,11}$/', $phone)) {
        $errors['phone'] = 'Por favor, insira um número de telefone válido com 10 ou 11 dígitos (DDD + número).';
    }
    if ($cpf === '') {
        $errors['cpf'] = 'Informe seu CPF.';
    } elseif (!class_exists('JobCandidateService') || !JobCandidateService::isValidCpf($cpf)) {
        $errors['cpf'] = 'CPF inválido.';
    }
    if ($mode !== 'vacancy' && $desiredRole === '') {
        $errors['desired_role'] = 'Informe o cargo pretendido.';
    }
    if ($linkedin !== '' && !filter_var($linkedin, FILTER_VALIDATE_URL)) {
        $errors['linkedin'] = 'Informe um link de LinkedIn válido.';
    }

    return $errors;
}

$errors = [];
$success = false;
$apiRequest = cr_is_api_request();
$apiResponse = ['success' => false, 'message' => 'Falha ao processar cadastro.', 'errorCode' => 'CANDIDATE_REGISTER_FAILED'];

$vacancyIdGet = isset($_GET['vacancy_id']) ? (int)$_GET['vacancy_id'] : 0;
$talentPoolGet = isset($_GET['talent_pool']) && $_GET['talent_pool'] === '1';
$vacancyTitle = '';
$mode = 'talent_pool';

if ($vacancyIdGet > 0) {
    try {
        $database = Database::getInstance();
        $dbTmp = $database->getConnection();
        $stmtVac = $dbTmp->prepare("SELECT title FROM job_vacancies WHERE id = :id AND status = 'ATIVA'");
        $stmtVac->bindValue(':id', $vacancyIdGet, PDO::PARAM_INT);
        $stmtVac->execute();
        $rowVac = $stmtVac->fetch(PDO::FETCH_ASSOC);
        if ($rowVac) {
            $vacancyTitle = $rowVac['title'];
            $mode = 'vacancy';
        }
    } catch (Exception $e) {
    }
}
if ($mode !== 'vacancy') {
    $vacancyIdGet = 0;
    if ($talentPoolGet) {
        $mode = 'talent_pool';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = preg_replace('/\D+/', '', trim($_POST['phone'] ?? ''));
    $isWhatsApp = isset($_POST['is_whatsapp']) ? 1 : 0;
    $cpf = trim($_POST['cpf'] ?? '');
    $cpfDigits = class_exists('JobCandidateService') ? JobCandidateService::normalizeCpf($cpf) : preg_replace('/\D+/', '', $cpf);
    $postedVacancyId = isset($_POST['vacancy_id']) ? (int)$_POST['vacancy_id'] : 0;
    $postedTalentPool = isset($_POST['talent_pool']) && $_POST['talent_pool'] === '1';
    $linkedin = trim($_POST['linkedin'] ?? '');
    $portfolio = trim($_POST['portfolio'] ?? '');
    $skills = trim($_POST['skills'] ?? '');
    $token = $_POST['form_token'] ?? null;
    if (function_exists('app_log_info')) {
        app_log_info('candidate_register_request', [
            'endpoint' => 'candidate-register.php',
            'mode' => $mode,
            'posted_vacancy_id' => $postedVacancyId,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'payload' => [
                'name_len' => strlen($name),
                'email' => $email,
                'phone_len' => strlen($phone),
                'cpf_hash' => $cpfDigits !== '' ? sha1($cpfDigits) : null,
                'has_resume' => isset($_FILES['resume']) ? 1 : 0
            ]
        ]);
    }

    $mode = 'talent_pool';
    $vacancyId = 0;
    $desiredRole = '';
    if ($postedVacancyId > 0) {
        try {
            $database = Database::getInstance();
            $dbCheck = $database->getConnection();
            $st = $dbCheck->prepare("SELECT title FROM job_vacancies WHERE id = :id AND status = 'ATIVA'");
            $st->bindValue(':id', $postedVacancyId, PDO::PARAM_INT);
            $st->execute();
            $vr = $st->fetch(PDO::FETCH_ASSOC);
            if ($vr) {
                $mode = 'vacancy';
                $vacancyId = $postedVacancyId;
                $desiredRole = $vr['title'];
                $vacancyTitle = $vr['title'];
            }
        } catch (Exception $e) {
        }
    }
    if ($mode !== 'vacancy') {
        $vacancyId = 0;
        $mode = $postedTalentPool ? 'talent_pool' : 'talent_pool';
        $desiredRole = trim($_POST['desired_role'] ?? '');
    }

    if (!cr_validate_form_token($token)) {
        $errors['form'] = 'Formulário expirado. Recarregue a página.';
        $apiResponse = ['success' => false, 'message' => 'Formulário expirado. Recarregue a página.', 'errorCode' => 'FORM_TOKEN_INVALID'];
    }

    $errors = array_merge($errors, cr_validate_candidate_input([
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'cpf' => $cpf,
        'desired_role' => $desiredRole,
        'linkedin' => $linkedin
    ], $mode));
    if (!empty($errors)) {
        $first = reset($errors);
        $apiResponse = ['success' => false, 'message' => (string)$first, 'errorCode' => 'VALIDATION_ERROR'];
    }
    if (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
        $errors['resume'] = 'Envie seu currículo.';
        $apiResponse = ['success' => false, 'message' => 'Envie seu currículo.', 'errorCode' => 'RESUME_REQUIRED'];
    } else {
        $maxSize = 5 * 1024 * 1024;
        if ($_FILES['resume']['size'] > $maxSize) {
            $errors['resume'] = 'Currículo deve ter no máximo 5MB.';
            $apiResponse = ['success' => false, 'message' => 'Currículo deve ter no máximo 5MB.', 'errorCode' => 'RESUME_TOO_LARGE'];
        } else {
            $ext = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf', 'doc', 'docx'], true)) {
                $errors['resume'] = 'Currículo deve ser PDF, DOC ou DOCX.';
                $apiResponse = ['success' => false, 'message' => 'Formato de currículo inválido.', 'errorCode' => 'RESUME_INVALID_FORMAT'];
            }
        }
    }

    if (defined('RECAPTCHA_SECRET') && RECAPTCHA_SECRET) {
        $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
        $rc = cr_verify_recaptcha($recaptchaResponse);
        if (!$rc['ok']) {
            $errors['recaptcha'] = $rc['message'];
            $apiResponse = ['success' => false, 'message' => $rc['message'], 'errorCode' => 'RECAPTCHA_FAILED'];
        }
    }

    if (empty($errors)) {
        $database = Database::getInstance();
        $db = $database->getConnection();
        $resumePath = null;
        $uploadDir = __DIR__ . '/public/uploads/curriculos';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }
        $ext = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
        $filename = 'cv_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($_FILES['resume']['tmp_name'], $dest)) {
            $errors['resume'] = 'Não foi possível salvar o currículo. Tente novamente.';
            $apiResponse = ['success' => false, 'message' => 'Não foi possível salvar o currículo. Tente novamente.', 'errorCode' => 'RESUME_STORE_FAILED'];
        } else {
            $resumePath = 'public/uploads/curriculos/' . $filename;
        }

        if (empty($errors)) {
            $notesData = [
                'cpf' => $cpf,
                'linkedin' => $linkedin,
                'portfolio' => $portfolio,
                'skills' => $skills,
                'desired_role' => $desiredRole
            ];
            $notes = json_encode($notesData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $status = 'NOVO';
            $stage = $vacancyId > 0 ? 'RECEBIDO' : 'TALENTOS';
            // Garantir vacancy_id válido quando for banco de talentos
            if ($vacancyId <= 0) {
                try {
                    $vacancyId = cr_find_or_create_talent_pool_vacancy($db);
                } catch (Throwable $e) {
                    $errors['form'] = 'Não foi possível preparar a vaga Banco de Talentos. Contate o suporte.';
                    $apiResponse = ['success' => false, 'message' => 'Não foi possível preparar a vaga Banco de Talentos.', 'errorCode' => 'TALENT_POOL_VACANCY_SETUP_FAILED'];
                    if (function_exists('app_log_error')) {
                        app_log_error('candidate_register_talent_pool_setup_error', [
                            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
            // Garantir coluna is_whatsapp
            try {
                $chk = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_candidates' AND COLUMN_NAME = 'is_whatsapp'");
                $chk->execute();
                if ((int)$chk->fetchColumn() === 0) {
                    $db->exec("ALTER TABLE job_candidates ADD COLUMN is_whatsapp TINYINT(1) NOT NULL DEFAULT 0");
                }
            } catch (Throwable $e) {}
            $stageId = 0;
            $hasStageId = false;
            try {
                $chk2 = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_candidates' AND COLUMN_NAME = 'stage_id'");
                $chk2->execute();
                $hasStageId = ((int)$chk2->fetchColumn() > 0);
            } catch (Throwable $e) { $hasStageId = false; }
            if ($hasStageId) {
                try {
                    $stg = $db->prepare('SELECT id FROM stages WHERE code = :c LIMIT 1');
                    $stg->execute([':c' => $stage]);
                    $stageId = (int)($stg->fetchColumn() ?: 0);
                } catch (Throwable $e) { $stageId = 0; }
                if ($stageId <= 0) {
                    $errors['form'] = 'Etapa do candidato não está configurada no sistema. Contate o administrador.';
                }
            }
            if (empty($errors['form'])) {
                if (class_exists('JobCandidateService')) {
                    JobCandidateService::ensureCpfSchema($db);
                    try {
                        $duplicate = JobCandidateService::hasDuplicateCpfForVacancy($db, $vacancyId, $cpfDigits);
                    } catch (Throwable $e) {
                        $duplicate = false;
                    }
                } else {
                    $duplicate = false;
                }
                if (!empty($duplicate)) {
                    $errors['cpf'] = 'Já existe uma candidatura registrada para esta vaga com este documento.';
                    $apiResponse = ['success' => false, 'message' => 'Já existe candidatura para esta vaga com este documento.', 'errorCode' => 'CANDIDATE_DUPLICATE_CPF_VACANCY'];
                    if (function_exists('app_log_info')) {
                        app_log_info('candidate_cpf_duplicate_precheck', [
                            'vacancy_id' => $vacancyId,
                            'cpf_hash' => $cpfDigits !== '' ? sha1($cpfDigits) : null,
                            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                            'agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                        ]);
                    }
                }
            }
            if (empty($errors['form']) && empty($errors['cpf'])) {
                $hasSortOrder = false;
                $sortOrder = null;
                try {
                    $chkSO = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_candidates' AND COLUMN_NAME = 'sort_order'");
                    $chkSO->execute();
                    $hasSortOrder = ((int)$chkSO->fetchColumn() > 0);
                } catch (Throwable $e) { $hasSortOrder = false; }
                if ($hasSortOrder) {
                    try {
                        if ($hasStageId && $stageId > 0) {
                            $q = $db->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM job_candidates WHERE stage_id = :sid");
                            $q->execute([':sid' => $stageId]);
                        } else {
                            $q = $db->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM job_candidates WHERE stage = :scode");
                            $q->execute([':scode' => $stage]);
                        }
                        $sortOrder = (int)($q->fetchColumn() ?: 1);
                    } catch (Throwable $e) { $sortOrder = 1; }
                }
                if ($hasStageId) {
                    if ($hasSortOrder) {
                        $stmt = $db->prepare('INSERT INTO job_candidates (vacancy_id, name, email, phone, cpf, is_whatsapp, resume_url, notes, status, sort_order, stage_id, stage) VALUES (:vacancy_id, :name, :email, :phone, :cpf, :is_whatsapp, :resume_url, :notes, :status, :sort_order, :stage_id, :stage)');
                        $stmt->bindValue(':sort_order', $sortOrder, PDO::PARAM_INT);
                    } else {
                        $stmt = $db->prepare('INSERT INTO job_candidates (vacancy_id, name, email, phone, cpf, is_whatsapp, resume_url, notes, status, stage_id, stage) VALUES (:vacancy_id, :name, :email, :phone, :cpf, :is_whatsapp, :resume_url, :notes, :status, :stage_id, :stage)');
                    }
                    $stmt->bindValue(':stage_id', $stageId, PDO::PARAM_INT);
                } else {
                    if ($hasSortOrder) {
                        $stmt = $db->prepare('INSERT INTO job_candidates (vacancy_id, name, email, phone, cpf, is_whatsapp, resume_url, notes, status, sort_order, stage) VALUES (:vacancy_id, :name, :email, :phone, :cpf, :is_whatsapp, :resume_url, :notes, :status, :sort_order, :stage)');
                        $stmt->bindValue(':sort_order', $sortOrder, PDO::PARAM_INT);
                    } else {
                        $stmt = $db->prepare('INSERT INTO job_candidates (vacancy_id, name, email, phone, cpf, is_whatsapp, resume_url, notes, status, stage) VALUES (:vacancy_id, :name, :email, :phone, :cpf, :is_whatsapp, :resume_url, :notes, :status, :stage)');
                    }
                }
                $stmt->bindValue(':vacancy_id', $vacancyId, PDO::PARAM_INT);
                $stmt->bindValue(':name', $name);
                $stmt->bindValue(':email', $email);
                $stmt->bindValue(':phone', $phone);
                $stmt->bindValue(':cpf', $cpfDigits);
                $stmt->bindValue(':is_whatsapp', $isWhatsApp, PDO::PARAM_INT);
                $stmt->bindValue(':resume_url', $resumePath);
                $stmt->bindValue(':notes', $notes);
                $stmt->bindValue(':status', $status);
                $stmt->bindValue(':stage', $stage);
                try {
                    if ($stmt->execute()) {
                        $success = true;
                        $apiResponse = ['success' => true, 'message' => 'Cadastro enviado com sucesso.', 'errorCode' => null];
                        if (function_exists('app_log_info')) {
                            app_log_info('candidate_register_success', [
                                'vacancy_id' => $vacancyId,
                                'candidate_id' => (int)$db->lastInsertId(),
                                'ip' => $_SERVER['REMOTE_ADDR'] ?? null
                            ]);
                        }
                    } else {
                        $errors['form'] = 'Erro ao salvar seus dados. Tente novamente.';
                        $apiResponse = ['success' => false, 'message' => 'Erro ao salvar seus dados. Tente novamente.', 'errorCode' => 'CANDIDATE_INSERT_FAILED'];
                    }
                } catch (Throwable $e) {
                    $isDuplicate = false;
                    if ($e instanceof PDOException && $e->getCode() === '23000') {
                        $msg = $e->getMessage();
                        if (strpos($msg, 'uq_job_candidates_vacancy_cpf') !== false || strpos($msg, 'job_candidates') !== false || strpos($msg, 'Duplicate entry') !== false) {
                            $isDuplicate = true;
                        }
                    }
                    if ($isDuplicate) {
                        $errors['cpf'] = 'Já existe uma candidatura registrada para esta vaga com este documento.';
                        $apiResponse = ['success' => false, 'message' => 'Já existe candidatura para esta vaga com este documento.', 'errorCode' => 'CANDIDATE_DUPLICATE_CPF_VACANCY'];
                        if (function_exists('app_log_info')) {
                            app_log_info('candidate_cpf_duplicate_db', [
                                'vacancy_id' => $vacancyId,
                                'cpf_hash' => $cpfDigits !== '' ? sha1($cpfDigits) : null,
                                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                                'agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                            ]);
                        }
                    } else {
                        $errors['form'] = 'Erro ao salvar seus dados. Tente novamente.';
                        $apiResponse = ['success' => false, 'message' => 'Erro interno ao salvar cadastro.', 'errorCode' => 'CANDIDATE_INSERT_EXCEPTION'];
                        if (function_exists('app_log_error')) {
                            app_log_error('candidate_register_error', [
                                'vacancy_id' => $vacancyId,
                                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }
            }
        }
    }
    if (!empty($errors) && $apiResponse['success'] === false) {
        $apiResponse['fields'] = array_keys($errors);
        if (function_exists('app_log_info')) {
            app_log_info('candidate_register_validation_or_business_error', [
                'fields' => array_keys($errors),
                'error_code' => $apiResponse['errorCode'] ?? 'UNKNOWN'
            ]);
        }
    }
    if ($apiRequest) {
        cr_send_api_response($apiResponse, $apiResponse['success'] ? 200 : 422);
    }
}

$formToken = cr_generate_form_token();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Candidato - <?php echo SITE_NAME; ?></title>
    <?php
      $favSrc = (function_exists('asset_url') ? asset_url('assets/logomdp.png') : 'public/assets/logomdp.png');
      $favM = @filemtime(__DIR__ . '/public/assets/logomdp.png') ?: time();
    ?>
    <link rel="icon" href="<?php echo htmlspecialchars($favSrc) . '?v=' . $favM; ?>" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              'rich-black': '#0d1321',
              'prussian-blue': '#1d2d44',
              'paynes-gray': '#3e5c76',
              'silver-lake-blue': '#748cab',
              'eggshell': '#f0ebd8'
            },
            fontFamily: {
              'sans': ['Inter', 'system-ui', 'sans-serif']
            }
          }
        }
      }
    </script>
    <style>
        :root { --vvh: 100vh; }
        a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible {
            outline: 3px solid #3e5c76;
            outline-offset: 2px;
            border-radius: 0.375rem;
        }
        .pane { overflow-y: auto; }
        @media (min-width: 768px) {
            .pane { max-height: calc(var(--vvh, 100vh) - 220px); }
        }
        @media (max-width: 767px) {
            .pane { max-height: none; }
        }
        .back-sticky { position: fixed; left: 1rem; right: 1rem; bottom: 1rem; z-index: 50; }
        @media (min-width: 768px) { .back-sticky { display: none; } }
        /* Match readonly cargo pretendido to the back button style */
        #desired_role[readonly] {
            background-color: #f0ebd8; /* eggshell */
            color: #3e5c76; /* paynes-gray */
            border: 1px solid #748cab; /* silver-lake-blue */
        }
    </style>
    <?php if (defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY): ?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
</head>
<body class="min-h-screen bg-gray-50 flex flex-col font-sans">
<header class="bg-[#175327] text-eggshell">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-3 items-center h-16">
            <div class="justify-self-start">
                <?php
                $logoPath = __DIR__ . '/assets/logowhite.png';
                $logoVer = file_exists($logoPath) ? filemtime($logoPath) : time();
                $logoUrl = (function_exists('asset_url') ? asset_url('assets/logowhite.png') : 'public/assets/logowhite.png') . '?v=' . $logoVer;
                ?>
                <a href="vagas.php" class="flex items-center">
                    <img src="<?php echo $logoUrl; ?>" alt="Logo Madeplant" class="h-10 w-auto md:h-12 object-contain">
                </a>
            </div>
            <div class="justify-self-center">
                <span class="font-semibold text-eggshell text-base md:text-lg text-center">Trabalhe Conosco</span>
            </div>
            <div class="justify-self-end"></div>
        </div>
    </div>
</header>

<main id="main-content" class="flex-1 py-8 px-4">
    <div class="max-w-6xl mx-auto">
        <div class="mb-4">
          <div class="flex items-center justify-center">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800 text-center">Cadastro de Candidato</h1>
          </div>
          <div class="mt-2 flex justify-end">
            <a href="vagas.php" class="px-3 py-1.5 rounded bg-eggshell border border-silver-lake-blue text-paynes-gray hover:bg-silver-lake-blue hover:text-white">
              Voltar para Vagas
            </a>
          </div>
        </div>
        <p class="text-gray-600 mb-4">
          <?php if ($mode === 'vacancy' && $vacancyTitle !== ''): ?>
            Preencha seus dados para se candidatar à vaga <strong><?php echo htmlspecialchars($vacancyTitle); ?></strong>.
          <?php else: ?>
            Preencha seus dados para entrar no banco de talentos.
          <?php endif; ?>
        </p>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
          <?php
            $vacancyDetails = null;
            if ($mode === 'vacancy' && $vacancyIdGet > 0) {
                try {
                    $database = Database::getInstance();
                    $dbDet = $database->getConnection();
                    // Verificar coluna faixa_salarial para evitar erro 42S22 em ambientes sem migração aplicada
                    $hasFaixa = false;
                    try {
                        $chk = $dbDet->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_vacancies' AND COLUMN_NAME = 'faixa_salarial'");
                        $chk->execute();
                        $hasFaixa = (int)$chk->fetchColumn() > 0;
                    } catch (Throwable $e) { $hasFaixa = false; }
                    $fields = "title, description, requirements, salary, benefits, location, contract_type";
                    if ($hasFaixa) { $fields = "title, description, requirements, salary, faixa_salarial, benefits, location, contract_type"; }
                    $stDet = $dbDet->prepare("SELECT $fields FROM job_vacancies WHERE id = :id LIMIT 1");
                    $stDet->bindValue(':id', $vacancyIdGet, PDO::PARAM_INT);
                    $stDet->execute();
                    $vacancyDetails = $stDet->fetch(PDO::FETCH_ASSOC) ?: null;
                } catch (Throwable $e) { $vacancyDetails = null; }
            }
          ?>
          <!-- Left pane: 40% width on desktop -->
          <aside class="md:col-span-2 bg-white rounded-lg shadow border border-silver-lake-blue p-4 md:p-6 pane">
            <h2 class="text-lg font-semibold text-rich-black mb-3">Informações da Vaga</h2>
            <?php if ($vacancyDetails): ?>
              <div class="space-y-3 text-sm">
                <div>
                  <div class="font-bold text-paynes-gray">Título</div>
                  <div class="text-rich-black"><?php echo htmlspecialchars($vacancyDetails['title'] ?? ''); ?></div>
                </div>
                <div>
                  <div class="font-bold text-paynes-gray">Descrição</div>
                  <div class="text-rich-black font-semibold"><?php echo nl2br(htmlspecialchars($vacancyDetails['description'] ?? '')); ?></div>
                </div>
                <div>
                  <div class="font-bold text-paynes-gray">Requisitos</div>
                  <div class="text-rich-black"><?php echo nl2br(htmlspecialchars($vacancyDetails['requirements'] ?? '')); ?></div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div>
                    <div class="font-bold text-paynes-gray">Faixa de salário</div>
                    <?php
                      $salRaw = $vacancyDetails['faixa_salarial'] ?? ($vacancyDetails['salary'] ?? '');
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
                      if ($salRaw === null) $salRaw = '';
                      $salRawStr = trim((string)$salRaw);
                      if ($salRawStr === '' || $salRawStr === '0' || $salRawStr === '0.00') {
                        $salTxt = '-';
                      } elseif (in_array($salRawStr, $allowedSalary, true)) {
                        $salTxt = $salRawStr;
                      } elseif (is_numeric($salRawStr) && (float)$salRawStr > 0) {
                        // Registros antigos com valor monetário: exibe como faixa compatível
                        $salTxt = 'Faixa salarial compatível com o mercado';
                      } else {
                        $salTxt = $salRawStr;
                      }
                    ?>
                    <div class="text-rich-black"><?php echo htmlspecialchars($salTxt); ?></div>
                  </div>
                  <div>
                    <div class="font-bold text-paynes-gray">Localização</div>
                    <div class="text-rich-black"><?php echo htmlspecialchars($vacancyDetails['location'] ?? '-'); ?></div>
                  </div>
                </div>
                <div>
                  <div class="font-bold text-paynes-gray">Benefícios</div>
                  <div class="text-rich-black"><?php echo nl2br(htmlspecialchars($vacancyDetails['benefits'] ?? '-')); ?></div>
                </div>
                <div>
                  <div class="font-bold text-paynes-gray">Tipo de contratação</div>
                  <div class="text-rich-black"><?php echo htmlspecialchars($vacancyDetails['contract_type'] ?? '-'); ?></div>
                </div>
              </div>
            <?php else: ?>
              <?php if ($mode === 'vacancy' && $vacancyIdGet > 0): ?>
                <div class="text-sm text-red-700 border border-red-200 bg-red-50 rounded p-3">
                  Não foi possível carregar os dados desta vaga. Tente novamente mais tarde ou candidate-se pelo Banco de Talentos.
                </div>
              <?php else: ?>
                <div class="text-sm text-paynes-gray">
                  Esta candidatura é para o Banco de Talentos. Informe seu cargo pretendido e anexe seu currículo para futuras oportunidades.
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </aside>

          <!-- Right pane: 60% width on desktop -->
          <section class="md:col-span-3 bg-white rounded-lg shadow border border-silver-lake-blue p-4 md:p-6 pane">
        <?php if ($success): ?>
            <div class="mb-4 p-4 rounded-lg bg-green-100 text-green-800 text-sm">
                Cadastro enviado com sucesso. Obrigado pelo interesse.
            </div>
        <?php elseif (!empty($errors['form'])): ?>
            <div class="mb-4 p-4 rounded-lg bg-red-100 text-red-800 text-sm">
                <?php echo htmlspecialchars($errors['form']); ?>
            </div>
        <?php endif; ?>

        <form id="candidateForm" method="POST" enctype="multipart/form-data" novalidate class="space-y-4">
            <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($formToken); ?>">
            <input type="hidden" name="vacancy_id" value="<?php echo $mode === 'vacancy' && $vacancyIdGet > 0 ? (int)$vacancyIdGet : 0; ?>">
            <input type="hidden" name="talent_pool" value="<?php echo $mode === 'talent_pool' ? '1' : '0'; ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="name">Nome completo *</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 <?php echo isset($errors['name']) ? 'border-red-500' : 'border-gray-300'; ?>">
                    <?php if (isset($errors['name'])): ?>
                        <p class="mt-1 text-xs text-red-600"><?php echo htmlspecialchars($errors['name']); ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="email">Email *</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 <?php echo isset($errors['email']) ? 'border-red-500' : 'border-gray-300'; ?>">
                    <?php if (isset($errors['email'])): ?>
                        <p class="mt-1 text-xs text-red-600"><?php echo htmlspecialchars($errors['email']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="phone">Telefone * <span class="text-xs text-paynes-gray">(Somente números)</span></label>
                    <input type="tel" id="phone" name="phone" inputmode="numeric" pattern="\d{10,11}" maxlength="15" placeholder="(XX) XXXXX-XXXX" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 <?php echo isset($errors['phone']) ? 'border-red-500' : 'border-gray-300'; ?>">
                    <?php if (isset($errors['phone'])): ?>
                        <p class="mt-1 text-xs text-red-600"><?php echo htmlspecialchars($errors['phone']); ?></p>
                    <?php endif; ?>
                    <div class="mt-2 flex items-center gap-2">
                      <input id="is_whatsapp" name="is_whatsapp" type="checkbox" class="form-checkbox" <?php echo !empty($_POST['is_whatsapp'])?'checked':''; ?>>
                      <label for="is_whatsapp" class="text-sm text-gray-700">Este número possui WhatsApp</label>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="cpf">CPF *</label>
                    <input type="text" id="cpf" name="cpf" value="<?php echo htmlspecialchars($_POST['cpf'] ?? ''); ?>" class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 <?php echo isset($errors['cpf']) ? 'border-red-500' : 'border-gray-300'; ?>">
                    <?php if (isset($errors['cpf'])): ?>
                        <p class="mt-1 text-xs text-red-600"><?php echo htmlspecialchars($errors['cpf']); ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="desired_role">Cargo pretendido *</label>
                    <?php
                    $desiredValue = $mode === 'vacancy' && $vacancyTitle !== '' ? $vacancyTitle : ($_POST['desired_role'] ?? '');
                    $readonly = $mode === 'vacancy' && $vacancyTitle !== '';
                    ?>
                    <input type="text" id="desired_role" name="desired_role" value="<?php echo htmlspecialchars($desiredValue); ?>" <?php echo $readonly ? 'readonly' : ''; ?> class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 <?php echo isset($errors['desired_role']) ? 'border-red-500' : 'border-gray-300'; ?>">
                    <?php if (isset($errors['desired_role']) && !$readonly): ?>
                        <p class="mt-1 text-xs text-red-600"><?php echo htmlspecialchars($errors['desired_role']); ?></p>
                    <?php endif; ?>
                    <?php if ($readonly): ?>
                        <p class="mt-1 text-xs text-paynes-gray">Vaga selecionada.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="linkedin">LinkedIn (opcional)</label>
                <input type="url" id="linkedin" name="linkedin" value="<?php echo htmlspecialchars($_POST['linkedin'] ?? ''); ?>" placeholder="https://www.linkedin.com/in/usuario" class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 <?php echo isset($errors['linkedin']) ? 'border-red-500' : 'border-gray-300'; ?>">
                <?php if (isset($errors['linkedin'])): ?>
                    <p class="mt-1 text-xs text-red-600"><?php echo htmlspecialchars($errors['linkedin']); ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="portfolio">Portfólio (opcional)</label>
                <input type="url" id="portfolio" name="portfolio" value="<?php echo htmlspecialchars($_POST['portfolio'] ?? ''); ?>" class="w-full px-3 py-2 border rounded-md border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="skills">Habilidades (separe por vírgulas)</label>
                <input type="text" id="skills" name="skills" value="<?php echo htmlspecialchars($_POST['skills'] ?? ''); ?>" class="w-full px-3 py-2 border rounded-md border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="resume">Currículo (PDF, DOC ou DOCX, até 5MB) *</label>
                <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx" class="w-full text-sm text-gray-700">
                <?php if (isset($errors['resume'])): ?>
                    <p class="mt-1 text-xs text-red-600"><?php echo htmlspecialchars($errors['resume']); ?></p>
                <?php endif; ?>
            </div>

            <?php if (defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY): ?>
            <div class="<?php echo isset($errors['recaptcha']) ? '' : 'mt-2'; ?>">
                <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY); ?>"></div>
                <?php if (isset($errors['recaptcha'])): ?>
                    <p class="mt-1 text-xs text-red-600"><?php echo htmlspecialchars($errors['recaptcha']); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="pt-2">
                <button type="submit" class="w-full md:w-auto px-6 py-2 bg-[#175327] text-white font-semibold rounded-md hover:bg-[#12411f] focus:outline-none focus:ring-2 focus:ring-[#175327]">Enviar cadastro</button>
            </div>
        </form>
          </section>
        </div>
    </div>
</main>
<div class="back-sticky">
  <a href="vagas.php" class="w-full flex items-center justify-center px-4 py-2 rounded-lg bg-eggshell border border-silver-lake-blue text-paynes-gray shadow hover:bg-silver-lake-blue hover:text-white" aria-label="Voltar para Vagas">
    Voltar para Vagas
  </a>
 </div>

<footer class="bg-[#175327] text-white mt-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
            <div>
                <h3 class="font-semibold mb-2">Sobre</h3>
                <p class="opacity-90">
                    <?php echo SITE_NAME; ?> – Gestão de produção e pessoas com foco em excelência operacional.
                </p>
            </div>
            <div>
                <h3 class="font-semibold mb-2">Contato</h3>
                <p class="opacity-90">Email: rh@madeplant.com.br</p>
                <p class="opacity-90">Telefone: (00) 0000-0000</p>
            </div>
            <div>
                <h3 class="font-semibold mb-2">Conecte-se</h3>
                <div class="flex space-x-3" aria-label="Redes sociais">
                    <a href="#" class="opacity-90 hover:opacity-100" aria-label="LinkedIn">
                        <i class="fab fa-linkedin"></i>
                    </a>
                    <a href="#" class="opacity-90 hover:opacity-100" aria-label="Instagram">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="#" class="opacity-90 hover:opacity-100" aria-label="Facebook">
                        <i class="fab fa-facebook"></i>
                    </a>
                </div>
            </div>
        </div>
        <div class="mt-4 border-t border-white/30 pt-3 text-xs text-white flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Todos os direitos reservados.</p>
            <div class="mt-1 sm:mt-0 flex items-center gap-3">
              <p class="opacity-90">Acessível e responsivo, seguindo boas práticas WCAG.</p>
              <a href="vagas.php" class="underline hover:opacity-100 opacity-90">Voltar para Vagas</a>
            </div>
        </div>
    </div>
</footer>

<script>
    (function(){
      function upd(){ var vh=(window.visualViewport&&window.visualViewport.height)?window.visualViewport.height:window.innerHeight; document.documentElement.style.setProperty('--vvh', vh+'px'); }
      upd(); window.addEventListener('resize',upd); if(window.visualViewport){ window.visualViewport.addEventListener('resize',upd); window.visualViewport.addEventListener('scroll',upd); }
    })();
    function setError(el, msg) {
        const errId = el.id + '-error';
        let err = document.getElementById(errId);
        if (!err) {
            err = document.createElement('p');
            err.id = errId;
            err.className = 'mt-1 text-xs text-red-600';
            el.parentElement.appendChild(err);
        }
        err.textContent = msg;
        el.classList.add('border-red-500');
    }
    function clearError(el) {
        const errId = el.id + '-error';
        const err = document.getElementById(errId);
        if (err) err.remove();
        el.classList.remove('border-red-500');
    }
    function validateField(el) {
        const v = el.value.trim();
        if (el.id === 'name' && v === '') {
            setError(el, 'Informe seu nome completo.');
        } else if (el.id === 'email') {
            const ok = v !== '' && /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v);
            if (!ok) setError(el, 'Informe um email válido.'); else clearError(el);
        } else if (el.id === 'phone' && v === '') {
            setError(el, 'Informe um telefone para contato.');
        } else if (el.id === 'cpf' && v === '') {
            setError(el, 'Informe seu CPF.');
        } else if (el.id === 'desired_role') {
            if (!el.readOnly && v === '') {
                setError(el, 'Informe o cargo pretendido.');
            } else if (el.readOnly) {
                clearError(el);
            } else {
                clearError(el);
            }
        } else if (el.id === 'linkedin') {
            if (v !== '') {
                const ok = /^https?:\/\/.+/i.test(v);
                if (!ok) setError(el, 'Informe um link de LinkedIn válido.'); else clearError(el);
            } else {
                clearError(el);
            }
        } else {
            clearError(el);
        }
    }
    document.querySelectorAll('#candidateForm input[type="text"], #candidateForm input[type="email"], #candidateForm input[type="tel"], #candidateForm input[type="url"]').forEach(function(el) {
        el.addEventListener('input', function() { validateField(el); });
        el.addEventListener('blur', function() { validateField(el); });
    });
    // Regras específicas de telefone: apenas dígitos, máximo 11, validação ao digitar/colar
    (function(){
      var tel = document.getElementById('phone');
      if (!tel) return;
      function sanitizeDigits(s){ return String(s||'').replace(/\D+/g,'').slice(0,11); }
      function maskPhone(d){
        // d = 10 ou 11 dígitos
        var p = d.length === 11 ? d : d.padEnd(10,'');
        var ddd = p.slice(0,2);
        var n1 = p.slice(2);
        if (d.length === 11 || (d.length >= 3 && d[2] === '9')) {
          // móvel 11 dígitos: (XX) 9XXXX-XXXX
          var a = n1.slice(0,5), b = n1.slice(5,9); // p contém 8 ou 9, mas exibimos progressivo
          return '('+ddd+') '+a+(b?('-'+b):'');
        } else {
          // fixo: (XX) XXXX-XXXX
          var a = n1.slice(0,4), b = n1.slice(4,8);
          return '('+ddd+') '+a+(b?('-'+b):'');
        }
      }
      function showPhoneError(msg){ setError(tel, msg); }
      function clearPhoneError(){ clearError(tel); }
      tel.addEventListener('keydown', function(e){
        const allowed = ['Backspace','Delete','ArrowLeft','ArrowRight','Tab','Home','End'];
        if (allowed.includes(e.key)) return;
        if (!/\d/.test(e.key)) { e.preventDefault(); }
        if (/\d/.test(e.key) && tel.value.replace(/\D+/g,'').length >= 11 && window.getSelection().toString()==='') {
          e.preventDefault();
        }
      });
      tel.addEventListener('input', function(){
        var digits = sanitizeDigits(tel.value);
        tel.value = maskPhone(digits);
        if (digits.length === 0) {
          showPhoneError('Informe um telefone para contato.');
        } else if (digits.length < 10) {
          showPhoneError('Por favor, insira um número válido com 10 ou 11 dígitos (DDD + número).');
        } else if (digits.length === 10 || digits.length === 11) {
          clearPhoneError();
        }
      });
      tel.addEventListener('paste', function(e){
        e.preventDefault();
        var clip = (e.clipboardData || window.clipboardData).getData('text');
        var dg = sanitizeDigits(clip);
        tel.value = maskPhone(dg);
        tel.dispatchEvent(new Event('input'));
      });
    })();
    </script>
</body>
</html>
