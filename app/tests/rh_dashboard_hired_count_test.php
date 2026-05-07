<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/rh_dashboard_metrics.php';

$db = Database::getInstance()->getConnection();

function hired_assert($cond, $msg)
{
    if (!$cond) {
        fwrite(STDERR, '[FAIL] ' . $msg . PHP_EOL);
        exit(1);
    }
    echo '[OK] ' . $msg . PHP_EOL;
}

function hired_stage_id(PDO $db, string $code): int
{
    $st = $db->prepare("SELECT id FROM stages WHERE code = :code LIMIT 1");
    $st->execute([':code' => $code]);
    return (int)($st->fetchColumn() ?: 0);
}

function hired_create_vacancy(PDO $db, string $title): int
{
    $creatorId = (int)$db->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($creatorId <= 0) {
        throw new RuntimeException('Usuário base não encontrado.');
    }

    $ins = $db->prepare("INSERT INTO job_vacancies (title, description, requirements, salary, benefits, location, contract_type, department, status, valid_until, created_by) VALUES (:title, :description, :requirements, :salary, :benefits, :location, :contract_type, :department, :status, :valid_until, :created_by)");
    $ins->execute([
        ':title' => $title,
        ':description' => 'Desc teste',
        ':requirements' => 'Req teste',
        ':salary' => 0,
        ':benefits' => null,
        ':location' => 'Local teste',
        ':contract_type' => 'CLT',
        ':department' => 'RH Teste',
        ':status' => 'ATIVA',
        ':valid_until' => date('Y-m-d', strtotime('+30 days')),
        ':created_by' => $creatorId,
    ]);

    return (int)$db->lastInsertId();
}

function hired_create_candidate(PDO $db, int $vacancyId, string $stageCode, int $stageId, string $suffix): int
{
    $hasStageId = false;
    try {
        $chk = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_candidates' AND COLUMN_NAME = 'stage_id'");
        $chk->execute();
        $hasStageId = (int)$chk->fetchColumn() > 0;
    } catch (Throwable $e) {
        $hasStageId = false;
    }

    if ($hasStageId && $stageId > 0) {
        $stmt = $db->prepare("INSERT INTO job_candidates (vacancy_id, name, email, phone, resume_url, notes, status, stage_id, stage) VALUES (:vacancy_id, :name, :email, :phone, :resume_url, :notes, :status, :stage_id, :stage)");
        $stmt->execute([
            ':vacancy_id' => $vacancyId,
            ':name' => 'Candidato Hired ' . $suffix,
            ':email' => 'hired.' . $suffix . '@example.com',
            ':phone' => '11999999999',
            ':resume_url' => null,
            ':notes' => null,
            ':status' => 'NOVO',
            ':stage_id' => $stageId,
            ':stage' => $stageCode,
        ]);
    } else {
        $stmt = $db->prepare("INSERT INTO job_candidates (vacancy_id, name, email, phone, resume_url, notes, status, stage) VALUES (:vacancy_id, :name, :email, :phone, :resume_url, :notes, :status, :stage)");
        $stmt->execute([
            ':vacancy_id' => $vacancyId,
            ':name' => 'Candidato Hired ' . $suffix,
            ':email' => 'hired.' . $suffix . '@example.com',
            ':phone' => '11999999999',
            ':resume_url' => null,
            ':notes' => null,
            ':status' => 'NOVO',
            ':stage' => $stageCode,
        ]);
    }

    return (int)$db->lastInsertId();
}

try {
    $contractedStageId = hired_stage_id($db, 'CONTRATADO');
    $receivedStageId = hired_stage_id($db, 'RECEBIDO');
    hired_assert($contractedStageId > 0, 'Etapa CONTRATADO deve existir');

    $unique = bin2hex(random_bytes(4));
    $vacancyId = hired_create_vacancy($db, 'Vaga Dashboard Hired ' . $unique);
    $cand1 = hired_create_candidate($db, $vacancyId, 'CONTRATADO', $contractedStageId, $unique . 'a');
    $cand2 = hired_create_candidate($db, $vacancyId, 'CONTRATADO', $contractedStageId, $unique . 'b');
    $cand3 = hired_create_candidate($db, $vacancyId, 'RECEBIDO', $receivedStageId, $unique . 'c');

    $before = rhBuildDashboardMetrics($db, date('Y-m-01'), date('Y-m-d'), 'RH Teste', 'CLT');
    $countBefore = (int)($before['recruitment']['total_hired'] ?? 0);
    hired_assert($countBefore >= 2, 'Métrica deve contar candidatos atualmente em Contratados');

    if ($receivedStageId > 0) {
        $up = $db->prepare("UPDATE job_candidates SET stage_id = :sid, stage = 'CONTRATADO' WHERE id = :id");
        $up->execute([':sid' => $contractedStageId, ':id' => $cand3]);
    } else {
        $up = $db->prepare("UPDATE job_candidates SET stage = 'CONTRATADO' WHERE id = :id");
        $up->execute([':id' => $cand3]);
    }

    $after = rhBuildDashboardMetrics($db, date('Y-m-01'), date('Y-m-d'), 'RH Teste', 'CLT');
    $countAfter = (int)($after['recruitment']['total_hired'] ?? 0);
    hired_assert($countAfter === ($countBefore + 1), 'Métrica deve atualizar quando candidato for movido para Contratados');

    echo PHP_EOL . 'Resultado: card de contratados validado com sucesso.' . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    echo 'Erro inesperado: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
