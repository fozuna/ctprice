<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
$db = Database::getInstance()->getConnection();
$sql = file_get_contents(__DIR__ . '/../database/add_job_candidate_stage_logs.sql');
try {
    $db->exec($sql);
    echo "Tabela job_candidate_stage_logs criada/confirmada.\n";
} catch (Throwable $e) {
    echo "Erro ao aplicar migração: " . $e->getMessage() . "\n";
    exit(1);
}
exit(0);

