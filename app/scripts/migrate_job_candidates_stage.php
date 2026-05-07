<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

$db = Database::getInstance()->getConnection();

$schemaRow = $db->query('SELECT DATABASE() as db')->fetch();
$schema = $schemaRow['db'];

$stmt = $db->prepare('SELECT COUNT(*) AS cnt FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?');
$stmt->execute([$schema, 'job_candidates', 'stage']);
$row = $stmt->fetch();
$exists = isset($row['cnt']) && (int)$row['cnt'] > 0;

if ($exists) {
    echo "Coluna stage já existe na tabela job_candidates.\n";
    exit(0);
}

$sqlFile = __DIR__ . '/../database/add_job_candidates_stage.sql';
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
    echo "Coluna stage adicionada com sucesso em job_candidates no schema {$schema}.\n";
    exit(0);
} catch (Exception $e) {
    echo "Erro ao aplicar migração de stage em job_candidates: " . $e->getMessage() . "\n";
    exit(1);
}

