<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
try {
    $db = Database::getInstance()->getConnection();
    $sqlFile = __DIR__ . '/../database/alter_job_vacancies_add_total_offered.sql';
    if (!is_file($sqlFile)) { throw new RuntimeException('SQL file missing'); }
    $sql = file_get_contents($sqlFile);
    $db->exec($sql);
    echo "Job vacancies: total_offered column added.\n";
} catch (Throwable $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}
