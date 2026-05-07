<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

$db = Database::getInstance()->getConnection();

$tables = ['equipments', 'fronts'];

foreach ($tables as $table) {
    echo "DESCRIBE {$table}:\n";
    $stmt = $db->query("DESCRIBE {$table}");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        echo sprintf(
            "  %-20s %-20s %s\n",
            $row['Field'],
            $row['Type'],
            $row['Null'] === 'NO' ? 'NOT NULL' : 'NULL'
        );
    }
    echo "\n";
}

