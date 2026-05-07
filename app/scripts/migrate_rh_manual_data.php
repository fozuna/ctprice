<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
try {
    $db = Database::getInstance()->getConnection();
    $sqlFile = __DIR__ . '/../database/add_rh_manual_data.sql';
    if (!is_file($sqlFile)) { throw new RuntimeException('SQL file missing'); }
    $sql = file_get_contents($sqlFile);
    $db->exec($sql);
    echo "RH manual data schema migrated.\n";
} catch (Throwable $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}
