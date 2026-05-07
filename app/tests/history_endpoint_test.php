<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $db = Database::getInstance()->getConnection();
    // Garantir tabela
    $db->exec(file_get_contents(__DIR__ . '/../database/add_job_candidate_stage_logs.sql'));
    echo "ok: tabela existe\n";

    // Procurar um candidato qualquer
    $row = $db->query("SELECT id FROM job_candidates ORDER BY id DESC LIMIT 1")->fetch();
    if (!$row) { echo "skip: sem candidatos para testar\n"; exit(0); }
    $id = (int)$row['id'];

    // Criar um log fake
    $stmt = $db->prepare("INSERT INTO job_candidate_stage_logs (candidate_id, from_stage, to_stage, note, created_by) VALUES (:c, 'RECEBIDO', 'IA', 'Teste histórico', NULL)");
    $stmt->execute([':c' => $id]);
    echo "ok: log inserido\n";

    // Simular chamada do endpoint via include
    $_GET['action'] = 'history';
    $_GET['id'] = $id;
    ob_start();
    include __DIR__ . '/../rh-kanban.php';
    $out = ob_get_clean();
    echo "endpoint retornou: " . substr($out, 0, 200) . "\n";
    $j = json_decode($out, true);
    if (!$j || empty($j['ok'])) {
        echo "fail: endpoint não retornou ok\n";
        exit(1);
    }
    echo "ok: endpoint history\n";
    exit(0);
} catch (Throwable $e) {
    echo "erro: " . $e->getMessage() . "\n";
    exit(2);
}

