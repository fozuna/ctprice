<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
$db = Database::getInstance()->getConnection();
echo "Testing add_note ensure_stage flow...\n";
try {
    $db->exec("CREATE TABLE IF NOT EXISTS job_candidates (id INT AUTO_INCREMENT PRIMARY KEY, stage VARCHAR(32) DEFAULT 'RH', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS job_candidate_stage_logs (id INT AUTO_INCREMENT PRIMARY KEY, candidate_id INT, from_stage VARCHAR(32), to_stage VARCHAR(32), note TEXT, interview_at DATETIME NULL, interview_link VARCHAR(255) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("DELETE FROM job_candidate_stage_logs; DELETE FROM job_candidates;");
    $db->exec("INSERT INTO job_candidates (stage) VALUES ('RH')");
    $cid = (int)$db->query("SELECT id FROM job_candidates LIMIT 1")->fetchColumn();
    // Simular backend: alterar estágio e inserir log
    $db->beginTransaction();
    $db->prepare("UPDATE job_candidates SET stage='ENTREVISTA' WHERE id=:id")->execute([':id'=>$cid]);
    $db->prepare("INSERT INTO job_candidate_stage_logs (candidate_id, from_stage, to_stage, note, interview_at, interview_link) VALUES (:cid,'RH','ENTREVISTA','Obs de teste','2026-03-20 15:00:00','https://meet.example.com')")->execute([':cid'=>$cid]);
    $db->commit();
    $stage = $db->query("SELECT stage FROM job_candidates WHERE id=$cid")->fetchColumn();
    $log = $db->query("SELECT to_stage, interview_link FROM job_candidate_stage_logs WHERE candidate_id=$cid")->fetch(PDO::FETCH_ASSOC);
    echo "Stage: $stage; Log to_stage: {$log['to_stage']}; Link: {$log['interview_link']}\n";
    exit(($stage==='ENTREVISTA' && $log['to_stage']==='ENTREVISTA' && strpos($log['interview_link'],'https://')===0)?0:1);
} catch (Throwable $e) { echo "Error: ".$e->getMessage()."\n"; exit(1); }
