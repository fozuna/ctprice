<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
try {
    $db = Database::getInstance()->getConnection();
    $chk = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_vacancies' AND COLUMN_NAME = 'faixa_salarial'");
    $chk->execute();
    if ((int)$chk->fetchColumn() === 0) {
        $db->exec("ALTER TABLE job_vacancies ADD COLUMN faixa_salarial VARCHAR(100) NOT NULL DEFAULT '' AFTER salary");
        $db->exec("CREATE INDEX idx_job_vacancies_faixa ON job_vacancies(faixa_salarial)");
        echo "job_vacancies: faixa_salarial added and indexed.\n";
    } else {
        echo "job_vacancies: faixa_salarial already exists.\n";
    }
} catch (Throwable $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}
