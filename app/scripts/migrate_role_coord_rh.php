<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

$db = Database::getInstance()->getConnection();

$schemaRow = $db->query('SELECT DATABASE() as db')->fetch();
$schema = $schemaRow['db'];

$stmt = $db->prepare('SELECT COUNT(*) AS cnt FROM roles WHERE name = ?');
$stmt->execute(['Coordenador de RH']);
$row = $stmt->fetch();
$exists = isset($row['cnt']) && (int)$row['cnt'] > 0;

if ($exists) {
    echo "Role Coordenador de RH já existe.\n";
    exit(0);
}

$stmt = $db->prepare('INSERT INTO roles (name, description) VALUES (?, ?)');
$stmt->execute(['Coordenador de RH', 'Acesso ao módulo de Recursos Humanos']);

echo "Role Coordenador de RH criada com sucesso.\n";
exit(0);

