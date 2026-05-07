<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

$db = Database::getInstance()->getConnection();

$schemaRow = $db->query('SELECT DATABASE() as db')->fetch();
$schema = $schemaRow['db'];

$stmt = $db->prepare('SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
$stmt->execute([$schema, 'job_vacancies']);
$row = $stmt->fetch();
$exists = isset($row['cnt']) && (int)$row['cnt'] > 0;

if ($exists) {
    echo "Tabela job_vacancies já existe no schema {$schema}.\n";
    exit(0);
}

$sqlFile = __DIR__ . '/../database/add_job_vacancies_table.sql';
if (!is_file($sqlFile)) {
    echo "Arquivo SQL não encontrado: {$sqlFile}\n";
    exit(1);
}

$sql = file_get_contents($sqlFile);
if ($sql === false || $sql === '') {
    echo "Arquivo SQL vazio: {$sqlFile}\n";
    exit(1);
}

try {
    $db->exec($sql);
    echo "Migração de vagas de RH aplicada com sucesso no schema {$schema}.\n";
    exit(0);
} catch (Exception $e) {
    echo "Erro ao aplicar migração de vagas de RH: " . $e->getMessage() . "\n";
    exit(1);
}

