<?php
require_once 'config/database.php';
try {
    $db = Database::getInstance()->getConnection();
    $db->exec("ALTER TABLE goals MODIFY COLUMN client_id INT NULL");
    echo "OK: goals.client_id agora permite NULL\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
