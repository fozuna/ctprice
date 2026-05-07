<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/helpers.php';

try {
    $db = Database::getInstance()->getConnection();
    $sqlFile = __DIR__ . '/../database/add_procurement_module.sql';
    if (!is_file($sqlFile)) {
        throw new RuntimeException('SQL file not found: ' . $sqlFile);
    }
    $sql = file_get_contents($sqlFile);
    $db->exec($sql);
    echo "Procurement module schema migrated successfully.\n";
} catch (Throwable $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}
