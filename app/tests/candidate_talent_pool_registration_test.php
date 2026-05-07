<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = [];
$_POST = [];
ob_start();
require_once __DIR__ . '/../candidate-register.php';
ob_end_clean();

require_once __DIR__ . '/../config/Database.php';

function t_assert($cond, $msg)
{
    if (!$cond) {
        fwrite(STDERR, '[FAIL] ' . $msg . PHP_EOL);
        exit(1);
    }
    echo '[OK] ' . $msg . PHP_EOL;
}

function create_test_vacancy(PDO $db, string $title): int
{
    $creatorId = (int)$db->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($creatorId <= 0) {
        throw new RuntimeException('Usuário base não encontrado para criação de vaga de teste.');
    }

    $cols = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_vacancies'")->fetchAll(PDO::FETCH_COLUMN);
    $cols = array_map('strtolower', is_array($cols) ? $cols : []);
    $data = [
        'title' => $title,
        'description' => 'Vaga de teste',
        'requirements' => 'Requisitos de teste',
        'salary' => '0',
        'faixa_salarial' => 'A combinar',
        'benefits' => '',
        'location' => 'A definir',
        'contract_type' => 'CLT',
        'department' => 'RH',
        'status' => 'ATIVA',
        'valid_until' => date('Y-m-d', strtotime('+1 year')),
        'created_by' => $creatorId,
        'total_offered' => 1
    ];

    $ic = [];
    $ip = [];
    foreach ($data as $k => $v) {
        if (in_array($k, $cols, true)) {
            $ic[] = $k;
            $ip[':' . $k] = $v;
        }
    }
    $sql = 'INSERT INTO job_vacancies (' . implode(', ', $ic) . ') VALUES (' . implode(', ', array_keys($ip)) . ')';
    $st = $db->prepare($sql);
    $st->execute($ip);
    return (int)$db->lastInsertId();
}

function insert_candidate_for_test(PDO $db, int $vacancyId, string $cpf, string $email): void
{
    $hasStageId = false;
    $stageId = 0;
    try {
        $chk = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_candidates' AND COLUMN_NAME = 'stage_id'");
        $chk->execute();
        $hasStageId = ((int)$chk->fetchColumn() > 0);
    } catch (Throwable $e) {
        $hasStageId = false;
    }
    if ($hasStageId) {
        $stageId = (int)$db->query("SELECT id FROM stages WHERE code = 'RECEBIDO' LIMIT 1")->fetchColumn();
        if ($stageId <= 0) {
            $stageId = 1;
        }
    }

    $sortOrder = 1;
    try {
        if ($hasStageId && $stageId > 0) {
            $q = $db->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM job_candidates WHERE stage_id = :sid");
            $q->execute([':sid' => $stageId]);
        } else {
            $q = $db->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM job_candidates WHERE stage = 'RECEBIDO'");
            $q->execute();
        }
        $sortOrder = (int)($q->fetchColumn() ?: 1);
    } catch (Throwable $e) {
        $sortOrder = 1;
    }

    if ($hasStageId) {
        $st = $db->prepare("INSERT INTO job_candidates (vacancy_id, name, email, phone, cpf, is_whatsapp, resume_url, notes, status, sort_order, stage_id, stage) VALUES (:vacancy_id, :name, :email, :phone, :cpf, :is_whatsapp, :resume_url, :notes, :status, :sort_order, :stage_id, :stage)");
        $st->execute([
            ':vacancy_id' => $vacancyId,
            ':name' => 'Candidato Teste',
            ':email' => $email,
            ':phone' => '67999999999',
            ':cpf' => $cpf,
            ':is_whatsapp' => 1,
            ':resume_url' => 'public/uploads/curriculos/teste.pdf',
            ':notes' => '{"desired_role":"Tester"}',
            ':status' => 'NOVO',
            ':sort_order' => $sortOrder,
            ':stage_id' => $stageId,
            ':stage' => 'RECEBIDO',
        ]);
    } else {
        $st = $db->prepare("INSERT INTO job_candidates (vacancy_id, name, email, phone, cpf, is_whatsapp, resume_url, notes, status, sort_order, stage) VALUES (:vacancy_id, :name, :email, :phone, :cpf, :is_whatsapp, :resume_url, :notes, :status, :sort_order, :stage)");
        $st->execute([
            ':vacancy_id' => $vacancyId,
            ':name' => 'Candidato Teste',
            ':email' => $email,
            ':phone' => '67999999999',
            ':cpf' => $cpf,
            ':is_whatsapp' => 1,
            ':resume_url' => 'public/uploads/curriculos/teste.pdf',
            ':notes' => '{"desired_role":"Tester"}',
            ':status' => 'NOVO',
            ':sort_order' => $sortOrder,
            ':stage' => 'RECEBIDO',
        ]);
    }
}

try {
    $db = Database::getInstance()->getConnection();
    if (class_exists('JobCandidateService')) {
        JobCandidateService::ensureCpfSchema($db);
    }

    $vErrors = cr_validate_candidate_input([
        'name' => '',
        'email' => '',
        'phone' => '',
        'cpf' => '',
        'desired_role' => '',
        'linkedin' => ''
    ], 'talent_pool');
    t_assert(isset($vErrors['name']) && isset($vErrors['email']) && isset($vErrors['phone']) && isset($vErrors['cpf']) && isset($vErrors['desired_role']), 'Campos obrigatórios ausentes devem falhar na validação backend');

    $vErrors2 = cr_validate_candidate_input([
        'name' => 'Teste',
        'email' => 'email-invalido',
        'phone' => '123',
        'cpf' => '123.456.789-00',
        'desired_role' => 'QA',
        'linkedin' => 'linkedin-sem-protocolo'
    ], 'talent_pool');
    t_assert(isset($vErrors2['email']) && isset($vErrors2['phone']) && isset($vErrors2['cpf']) && isset($vErrors2['linkedin']), 'Formatos inválidos devem falhar na validação backend');

    $talentId = cr_find_or_create_talent_pool_vacancy($db);
    t_assert($talentId > 0, 'Fluxo de sucesso: deve localizar/criar vaga Banco de Talentos');

    $uniqueCpf = '11144477735';
    $emailA = 'talent_a_' . time() . '@example.com';
    $emailB = 'talent_b_' . time() . '@example.com';
    insert_candidate_for_test($db, $talentId, $uniqueCpf, $emailA);
    $count1 = (int)$db->query("SELECT COUNT(*) FROM job_candidates WHERE vacancy_id = {$talentId} AND cpf = '{$uniqueCpf}'")->fetchColumn();
    t_assert($count1 >= 1, 'Cadastro válido deve persistir candidato');

    $dupBlocked = false;
    try {
        insert_candidate_for_test($db, $talentId, $uniqueCpf, $emailB);
    } catch (Throwable $e) {
        if ($e instanceof PDOException && $e->getCode() === '23000') {
            $dupBlocked = true;
        }
    }
    t_assert($dupBlocked === true, 'Dados duplicados (cpf + vaga) devem ser bloqueados');

    $otherVacancy = create_test_vacancy($db, 'Vaga Teste API ' . uniqid());
    $emailC = 'talent_c_' . time() . '@example.com';
    insert_candidate_for_test($db, $otherVacancy, $uniqueCpf, $emailC);
    $countByCpf = (int)$db->query("SELECT COUNT(*) FROM job_candidates WHERE cpf = '{$uniqueCpf}'")->fetchColumn();
    t_assert($countByCpf >= 2, 'Mesmo CPF deve ser permitido em vagas diferentes');

    $dbFailCaptured = false;
    try {
        $fake = new PDO('sqlite::memory:');
        cr_find_or_create_talent_pool_vacancy($fake);
    } catch (Throwable $e) {
        $dbFailCaptured = true;
    }
    t_assert($dbFailCaptured === true, 'Falha de banco deve ser capturada e tratável');

    echo PHP_EOL . 'Resultado: testes do cadastro em banco de talentos concluídos com sucesso.' . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    echo 'Erro inesperado: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

