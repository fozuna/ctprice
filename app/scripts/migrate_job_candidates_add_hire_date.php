<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/BaseModel.php';
require_once __DIR__ . '/../models/JobCandidate.php';
try {
    $db = Database::getInstance()->getConnection();
    if (JobCandidate::hasHireDateColumn($db)) {
        echo "Job candidates: hire_date column already exists.\n";
        exit(0);
    }
    JobCandidate::ensureHireDateColumn($db);
    echo "Job candidates: hire_date column added.\n";
} catch (Throwable $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}
