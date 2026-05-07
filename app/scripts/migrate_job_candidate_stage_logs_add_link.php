<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
try {
    $db = Database::getInstance()->getConnection();
    // Try to add column if missing
    $sql = "ALTER TABLE job_candidate_stage_logs ADD COLUMN interview_link VARCHAR(255) NULL";
    $db->exec($sql);
    echo "job_candidate_stage_logs: interview_link added.\n";
} catch (Throwable $e) {
    echo "Migration notice: " . $e->getMessage() . "\n";
}
