<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
$db = Database::getInstance()->getConnection();
echo "Testing open vacancies calculation...\n";
try {
    $db->exec("CREATE TABLE IF NOT EXISTS job_vacancies (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255), department VARCHAR(120), status VARCHAR(32) DEFAULT 'ATIVA', total_offered INT NOT NULL DEFAULT 1)");
    $db->exec("CREATE TABLE IF NOT EXISTS job_candidates (id INT AUTO_INCREMENT PRIMARY KEY, vacancy_id INT, stage VARCHAR(32) DEFAULT 'RECEBIDO', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, hire_date DATE NULL)");
    $db->exec("DELETE FROM job_candidates; DELETE FROM job_vacancies;");
    $db->exec("INSERT INTO job_vacancies (title, department, status, total_offered) VALUES ('Vaga A','Ops','ATIVA',3),('Vaga B','Ops','ATIVA',2),('Vaga C','Fin','INATIVA',5)");
    $db->exec("INSERT INTO job_candidates (vacancy_id, stage) VALUES (1,'CONTRATADO'),(1,'CONTRATADO'),(2,'RECEBIDO')");
    $sql = "SELECT COALESCE(SUM(GREATEST(v.total_offered - (SELECT COUNT(*) FROM job_candidates c WHERE c.vacancy_id = v.id AND c.stage='CONTRATADO'),0)),0) FROM job_vacancies v WHERE v.status='ATIVA'";
    $v = $db->query($sql)->fetchColumn();
    echo "Expected 3 (Vaga A: 3-2=1; Vaga B: 2-0=2; total=3). Got: $v\n";
    exit($v==3?0:1);
} catch (Throwable $e) {
    echo "Error: ".$e->getMessage()."\n"; exit(1);
}
