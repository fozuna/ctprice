<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

$db = Database::getInstance()->getConnection();
$schemaRow = $db->query('SELECT DATABASE() as db')->fetch();
$schema = $schemaRow['db'] ?? '';

$sqlFile = __DIR__ . '/../database/add_equipment_management.sql';
if (!is_file($sqlFile)) {
    echo "Arquivo SQL não encontrado: {$sqlFile}\n";
    exit(1);
}

try {
    $db->exec(file_get_contents($sqlFile));
    echo "Migração de gerenciamento de equipamentos aplicada no schema {$schema}.\n";
    exit(0);
} catch (Exception $e) {
    echo "Erro ao aplicar migração: " . $e->getMessage() . "\n";
    exit(1);
}
