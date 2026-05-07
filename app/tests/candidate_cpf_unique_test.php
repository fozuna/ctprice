<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

$db = Database::getInstance()->getConnection();

function cpf_test_assert($cond, $msg)
{
    if (!$cond) {
        fwrite(STDERR, '[FAIL] ' . $msg . PHP_EOL);
        exit(1);
    }
    echo '[OK] ' . $msg . PHP_EOL;
}

try {
    $db->exec("CREATE TABLE IF NOT EXISTS job_candidates (id INT AUTO_INCREMENT PRIMARY KEY, vacancy_id INT NOT NULL, cpf CHAR(11) NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("DELETE FROM job_candidates");
    $db->exec("DELETE FROM job_vacancies");
    $userId = (int) $db->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($userId <= 0) {
        throw new RuntimeException('Usuário base não encontrado para job_vacancies');
    }
    $insVac = $db->prepare("INSERT INTO job_vacancies (title, description, requirements, salary, benefits, location, contract_type, department, status, valid_until, created_by) VALUES (:title, :description, :requirements, :salary, :benefits, :location, :contract_type, :department, :status, :valid_until, :created_by)");
    $common = [
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
    ];
    $insVac->execute(array_merge($common, [':title' => 'Vaga Teste 1']));
    $insVac->execute(array_merge($common, [':title' => 'Vaga Teste 2']));
    $v1 = (int) $db->query("SELECT id FROM job_vacancies WHERE title='Vaga Teste 1' LIMIT 1")->fetchColumn();
    $v2 = (int) $db->query("SELECT id FROM job_vacancies WHERE title='Vaga Teste 2' LIMIT 1")->fetchColumn();

    JobCandidateService::ensureCpfSchema($db);

    $hasStageId = false;
    $defaultStageId = null;
    try {
        $chk = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_candidates' AND COLUMN_NAME = 'stage_id'");
        $chk->execute();
        $hasStageId = ((int) $chk->fetchColumn() > 0);
    } catch (Throwable $e) {
        $hasStageId = false;
    }
    if ($hasStageId) {
        $defaultStageId = (int) $db->query("SELECT id FROM stages WHERE code = 'RECEBIDO' LIMIT 1")->fetchColumn();
        if ($defaultStageId <= 0) {
            $defaultStageId = 1;
        }
    }

    $cpfValido = '11144477735';
    $cpfInvalido = '12345678900';

    cpf_test_assert(JobCandidateService::isValidCpf($cpfValido) === true, 'CPF válido deve ser aceito');
    cpf_test_assert(JobCandidateService::isValidCpf($cpfInvalido) === false, 'CPF inválido deve ser rejeitado');

    $cpfNorm = JobCandidateService::normalizeCpf($cpfValido);

    $dup = JobCandidateService::hasDuplicateCpfForVacancy($db, $v1, $cpfNorm);
    cpf_test_assert($dup === false, 'Sem registro prévio, não deve haver duplicidade para vaga 1');
    if ($hasStageId) {
        $stmt = $db->prepare("INSERT INTO job_candidates (vacancy_id, name, email, phone, cpf, resume_url, notes, status, stage_id, stage) VALUES (:vacancy_id, :name, :email, :phone, :cpf, :resume_url, :notes, :status, :stage_id, :stage)");
        $baseCandidate = [
            ':name' => 'Teste',
            ':email' => 'teste@example.com',
            ':phone' => '11999999999',
            ':resume_url' => null,
            ':notes' => null,
            ':status' => 'NOVO',
            ':stage_id' => $defaultStageId,
            ':stage' => 'RECEBIDO',
        ];
    } else {
        $stmt = $db->prepare("INSERT INTO job_candidates (vacancy_id, name, email, phone, cpf, resume_url, notes, status) VALUES (:vacancy_id, :name, :email, :phone, :cpf, :resume_url, :notes, :status)");
        $baseCandidate = [
            ':name' => 'Teste',
            ':email' => 'teste@example.com',
            ':phone' => '11999999999',
            ':resume_url' => null,
            ':notes' => null,
            ':status' => 'NOVO',
        ];
    }
    $stmt->execute(array_merge($baseCandidate, [':vacancy_id' => $v1, ':cpf' => $cpfNorm]));

    $countV1 = (int) $db->query("SELECT COUNT(*) FROM job_candidates WHERE vacancy_id = {$v1}")->fetchColumn();
    cpf_test_assert($countV1 === 1, 'Cadastro válido para vaga 1 deve ser persistido');

    $dup2 = JobCandidateService::hasDuplicateCpfForVacancy($db, $v1, $cpfNorm);
    cpf_test_assert($dup2 === true, 'CPF já cadastrado na mesma vaga deve ser detectado como duplicado');

    $uniqueViolation = false;
    try {
        $stmt->execute(array_merge($baseCandidate, [':vacancy_id' => $v1, ':cpf' => $cpfNorm]));
    } catch (Throwable $e) {
        if ($e instanceof PDOException && $e->getCode() === '23000') {
            $uniqueViolation = true;
        }
    }
    cpf_test_assert($uniqueViolation === true, 'Violação de constraint UNIQUE deve ocorrer em duplicidade na mesma vaga');

    $stmt->execute(array_merge($baseCandidate, [':vacancy_id' => $v2, ':cpf' => $cpfNorm]));
    $countCpf = (int) $db->query("SELECT COUNT(*) FROM job_candidates WHERE cpf = '{$cpfNorm}'")->fetchColumn();
    cpf_test_assert($countCpf === 2, 'Mesmo CPF pode se candidatar em vagas diferentes');

    $concurrencyViolation = false;
    try {
        $db->beginTransaction();
        $stmt->execute(array_merge($baseCandidate, [':vacancy_id' => $v1, ':cpf' => $cpfNorm]));
        $stmt->execute(array_merge($baseCandidate, [':vacancy_id' => $v1, ':cpf' => $cpfNorm]));
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($e instanceof PDOException && $e->getCode() === '23000') {
            $concurrencyViolation = true;
        }
    }
    cpf_test_assert($concurrencyViolation === true, 'Constraint UNIQUE deve garantir integridade em cenário de concorrência simulada');

    echo PHP_EOL . 'Resultado: testes de CPF concluídos com sucesso.' . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    echo 'Erro inesperado nos testes de CPF: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
