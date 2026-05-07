<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
try {
    $db = Database::getInstance()->getConnection();
    $sqlFile = __DIR__ . '/../database/alter_vacancy_salary_text.sql';
    if (!is_file($sqlFile)) { throw new RuntimeException('SQL file missing'); }
    $sql = file_get_contents($sqlFile);
    $db->exec($sql);
    echo "Vacancy salary column migrated to VARCHAR with descriptive default.\n";
} catch (Throwable $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}
