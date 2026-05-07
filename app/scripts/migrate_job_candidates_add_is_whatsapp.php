<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
try {
    $db = Database::getInstance()->getConnection();
    $db->exec("ALTER TABLE job_candidates ADD COLUMN is_whatsapp TINYINT(1) NOT NULL DEFAULT 0");
    echo "job_candidates: is_whatsapp added.\n";
} catch (Throwable $e) {
    echo "Migration notice: " . $e->getMessage() . "\n";
}
