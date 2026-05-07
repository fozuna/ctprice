<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

$db = Database::getInstance()->getConnection();

$schemaRow = $db->query('SELECT DATABASE() as db')->fetch();
$schema = $schemaRow['db'];

echo "Schema atual: {$schema}\n";

$stmt = $db->prepare('SELECT COUNT(*) AS cnt FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?');
$stmt->execute([$schema, 'equipments', 'front_id']);
$row = $stmt->fetch();
$exists = isset($row['cnt']) && (int)$row['cnt'] > 0;

if ($exists) {
    echo "Coluna front_id já existe na tabela equipments.\n";
    exit(0);
}

echo "Adicionando coluna front_id à tabela equipments...\n";

$alterSql = "
ALTER TABLE equipments
    ADD COLUMN front_id INT NULL AFTER description;
";

$fkSql = "
ALTER TABLE equipments
    ADD CONSTRAINT fk_equipments_front
        FOREIGN KEY (front_id) REFERENCES fronts(id) ON DELETE SET NULL;
";

try {
    $db->exec($alterSql);
    $db->exec($fkSql);
    echo "Coluna front_id adicionada com sucesso e constraint fk_equipments_front criada.\n";
    exit(0);
} catch (Exception $e) {
    echo 'Erro ao aplicar migração de front_id: ' . $e->getMessage() . "\n";
    exit(1);
}

