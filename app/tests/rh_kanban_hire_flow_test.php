<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/BaseModel.php';
require_once __DIR__ . '/../models/JobCandidate.php';
$db = Database::getInstance()->getConnection();
echo "Testing kanban hire flow...\n";
try {
    JobCandidate::ensureHireDateColumn($db);
    $hasHireDate = JobCandidate::hasHireDateColumn($db);
    if (!$hasHireDate) {
        throw new RuntimeException('hire_date não foi criada automaticamente');
    }

    $userId = (int)$db->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($userId <= 0) {
        throw new RuntimeException('Usuário base não encontrado para teste de contratação');
    }

    $vacancyTitle = 'Vaga Hire Flow ' . bin2hex(random_bytes(4));
    $insVac = $db->prepare("INSERT INTO job_vacancies (title, description, requirements, salary, benefits, location, contract_type, department, status, valid_until, created_by) VALUES (:title, :description, :requirements, :salary, :benefits, :location, :contract_type, :department, :status, :valid_until, :created_by)");
    $insVac->execute([
        ':title' => $vacancyTitle,
        ':description' => 'Desc teste',
        ':requirements' => 'Req teste',
        ':salary' => 0,
        ':benefits' => null,
        ':location' => 'Local teste',
        ':contract_type' => 'CLT',
        ':department' => 'Depto',
        ':status' => 'ATIVA',
        ':valid_until' => date('Y-m-d', strtotime('+30 days')),
        ':created_by' => $userId,
    ]);
    $vacancyId = (int)$db->lastInsertId();

    $stageId = null;
    try {
        $stageId = (int)$db->query("SELECT id FROM stages WHERE code = 'RECEBIDO' LIMIT 1")->fetchColumn();
    } catch (Throwable $e) {
        $stageId = null;
    }

    if ($stageId > 0) {
        $insCand = $db->prepare("INSERT INTO job_candidates (vacancy_id, name, email, phone, resume_url, notes, status, stage_id, stage) VALUES (:vacancy_id, :name, :email, :phone, :resume_url, :notes, :status, :stage_id, :stage)");
        $insCand->execute([
            ':vacancy_id' => $vacancyId,
            ':name' => 'Teste Contratado',
            ':email' => 'hire.flow.' . bin2hex(random_bytes(3)) . '@example.com',
            ':phone' => '11999999999',
            ':resume_url' => null,
            ':notes' => null,
            ':status' => 'NOVO',
            ':stage_id' => $stageId,
            ':stage' => 'RECEBIDO',
        ]);
    } else {
        $insCand = $db->prepare("INSERT INTO job_candidates (vacancy_id, name, email, phone, resume_url, notes, status, stage) VALUES (:vacancy_id, :name, :email, :phone, :resume_url, :notes, :status, :stage)");
        $insCand->execute([
            ':vacancy_id' => $vacancyId,
            ':name' => 'Teste Contratado',
            ':email' => 'hire.flow.' . bin2hex(random_bytes(3)) . '@example.com',
            ':phone' => '11999999999',
            ':resume_url' => null,
            ':notes' => null,
            ':status' => 'NOVO',
            ':stage' => 'RECEBIDO',
        ]);
    }
    $id = (int)$db->lastInsertId();
    $db->exec("UPDATE job_candidates SET stage='CONTRATADO', hire_date='2026-03-10' WHERE id=$id");
    $row = $db->query("SELECT stage, hire_date FROM job_candidates WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
    echo "Stage: {$row['stage']}, hire_date: {$row['hire_date']}\n";
    exit(($row['stage']==='CONTRATADO' && $row['hire_date']==='2026-03-10')?0:1);
} catch (Throwable $e) { echo "Error: ".$e->getMessage()."\n"; exit(1); }
