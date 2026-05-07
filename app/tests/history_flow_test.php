<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $db = Database::getInstance()->getConnection();
    // Garantir tabela
    $db->exec(file_get_contents(__DIR__ . '/../database/add_job_candidate_stage_logs.sql'));

    // Selecionar um candidato existente
    $row = $db->query("SELECT id, stage FROM job_candidates ORDER BY id DESC LIMIT 1")->fetch();
    if (!$row) { echo "skip: sem candidatos\n"; exit(0); }
    $cid = (int)$row['id'];
    $from = $row['stage'] ?: 'RECEBIDO';
    $to = ($from === 'IA') ? 'RH' : 'IA';

    // Inserir log simulado
    $note = 'Teste fluxo ' . date('H:i:s');
    $stmt = $db->prepare("INSERT INTO job_candidate_stage_logs (candidate_id, from_stage, to_stage, note, created_by) VALUES (:c, :fs, :ts, :n, NULL)");
    $stmt->execute([':c' => $cid, ':fs' => $from, ':ts' => $to, ':n' => $note]);
    echo "ok: log inserido para cid={$cid}\n";

    // Consultar endpoint de histórico
    $_GET['action'] = 'history';
    $_GET['id'] = $cid;
    ob_start();
    include __DIR__ . '/../rh-kanban.php';
    $out = ob_get_clean();
    $j = json_decode($out, true);
    if (!$j || empty($j['ok'])) { echo "fail: endpoint retornou erro\n"; exit(1); }
    $found = false;
    foreach ($j['data'] as $it) {
        if (($it['note'] ?? '') === $note) { $found = true; break; }
    }
    echo $found ? "ok: histórico contém a observação\n" : "fail: histórico não contém a observação\n";
    exit($found ? 0 : 1);
} catch (Throwable $e) {
    echo "erro: " . $e->getMessage() . "\n";
    exit(2);
}

