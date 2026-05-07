<?php
require_once 'config/config.php';
require_once 'classes/Auth.php';
require_once 'classes/AuditLog.php';
require_once 'models/JobCandidate.php';
require_once 'includes/kanban_sort_order.php';
require_once 'includes/kanban_attachments.php';
require_once 'includes/stages_repo.php';

$database = Database::getInstance();
$db = $database->getConnection();
$auth = new Auth($db);

$role = $_SESSION['user_role'] ?? null;
if (!$auth->isLoggedIn() || !in_array($role, [ROLE_ADMIN, ROLE_COORD_RH])) {
    header('Location: dashboard.php');
    exit();
}

$pageTitle = 'Kanban de Currículos';
$candidateModel = new JobCandidate($db);
$auditLog = new AuditLog($db);

$fallbackStageLabels = [
    'RECEBIDO' => 'Recebidos',
    'IA' => 'Analisados por IA',
    'RH' => 'Analisados RH',
    'REPROV_PESQ' => 'Reprovados na Pesquisa',
    'ENTREVISTA' => 'Entrevistas Marcadas',
    'ENT_AGENDADA' => 'Entrevista Agendada',
    'ENT_CONFIRMADA' => 'Entrevista Confirmada',
    'POS_ENTREVISTA' => 'Pós-Entrevista',
    'REPROV_ENT' => 'Reprovados na Entrevista',
    'DISPENSADO' => 'Dispensados',
    'TALENTOS' => 'Banco de Talentos',
    'CONTRATADO' => 'Contratados'
];
$fallbackStageColors = [
    'RECEBIDO' => '#6b7280',
    'IA' => '#3b82f6',
    'RH' => '#6366f1',
    'REPROV_PESQ' => '#a855f7',
    'ENTREVISTA' => '#f59e0b',
    'ENT_AGENDADA' => '#f59e0b',
    'ENT_CONFIRMADA' => '#10b981',
    'POS_ENTREVISTA' => '#3b82f6',
    'REPROV_ENT' => '#fb7185',
    'DISPENSADO' => '#ef4444',
    'TALENTOS' => '#14b8a6',
    'CONTRATADO' => '#10b981'
];

$stagesList = stages_try_load($db);
if (!$stagesList) {
    $stagesList = stages_from_fallback($fallbackStageLabels, $fallbackStageColors);
}
[$stagesById, $stagesByCode] = stages_build_maps($stagesList);

if (($_GET['action'] ?? '') === 'notifications') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        try { kanbanEnsureAttachmentSchema($db); } catch (Throwable $e) {
            echo json_encode(['ok' => true, 'data' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        $uid = (int)($_SESSION['user_id'] ?? 0);
        if ($uid <= 0) {
            echo json_encode(['ok' => false, 'message' => 'Usuário não autenticado.']);
            exit;
        }
        $stmt = $db->prepare("SELECT id, message, payload, created_at FROM job_realtime_notifications WHERE user_id = :u AND read_at IS NULL ORDER BY id DESC LIMIT 20");
        $stmt->execute([':u' => $uid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $markRead = !isset($_GET['mark_read']) || $_GET['mark_read'] !== '0';
        if ($markRead && !empty($rows)) {
            $ids = array_map(static fn($r) => (int)$r['id'], $rows);
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $up = $db->prepare("UPDATE job_realtime_notifications SET read_at = NOW() WHERE user_id = ? AND id IN ($ph)");
            $params = array_merge([$uid], $ids);
            $up->execute($params);
        }
        echo json_encode(['ok' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'reorder') {
    header('Content-Type: application/json; charset=utf-8');
    kanbanEnsureSortOrder($db);
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    $stageId = (int)($data['stage_id'] ?? 0);
    if ($stageId <= 0) {
        $stageCode = strtoupper((string)($data['stage'] ?? ''));
        $stageId = isset($stagesByCode[$stageCode]['id']) ? (int)$stagesByCode[$stageCode]['id'] : 0;
    }
    $ordered = $data['ordered_ids'] ?? [];
    if ($stageId <= 0 || !isset($stagesById[$stageId])) {
        echo json_encode(['ok' => false, 'message' => 'Stage inválido.']);
        exit;
    }
    $ids = kanbanNormalizeIdList($ordered);
    if (empty($ids)) {
        echo json_encode(['ok' => false, 'message' => 'Lista vazia.']);
        exit;
    }
    try {
        $db->beginTransaction();
        kanbanApplySortOrder($db, $stageId, $ids);
        $db->commit();
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        if (function_exists('error_log')) { error_log('[rh-kanban/reorder] erro: ' . $e->getMessage()); }
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'move') {
    header('Content-Type: application/json; charset=utf-8');
    kanbanEnsureSortOrder($db);
    $attachmentsAvailable = true;
    try {
        kanbanEnsureAttachmentSchema($db);
    } catch (Throwable $e) {
        $attachmentsAvailable = false;
        if (function_exists('error_log')) {
            error_log('[rh-kanban/move] anexos indisponíveis (schema): ' . $e->getMessage());
        }
    }
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    $isMultipart = strpos($contentType, 'multipart/form-data') !== false;
    $uploadedAttachments = null;
    if ($isMultipart) {
        $payloadRaw = (string)($_POST['payload'] ?? '');
        if ($payloadRaw !== '') {
            $data = json_decode($payloadRaw, true);
        } else {
            $data = $_POST;
        }
        $body = $payloadRaw;
        $uploadedAttachments = $_FILES['attachments'] ?? null;
    } else {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);
    }
    if (defined('DEBUG_MODE') && DEBUG_MODE && function_exists('error_log')) {
        error_log('[rh-kanban/move] payload=' . (is_string($body) ? $body : ''));
    }
    if (!is_array($data) || empty($data['id']) || (empty($data['stage_id']) && empty($data['stage']))) {
        echo json_encode(['ok' => false, 'message' => 'Dados inválidos.']);
        exit;
    }
    $id = (int)$data['id'];
    $toStageId = (int)($data['stage_id'] ?? 0);
    $toStageCode = strtoupper((string)($data['stage'] ?? ''));
    if ($toStageId <= 0 && $toStageCode !== '' && isset($stagesByCode[$toStageCode]['id'])) {
        $toStageId = (int)$stagesByCode[$toStageCode]['id'];
    }
    $toStage = $toStageId > 0 ? ($stagesById[$toStageId] ?? null) : null;
    if (!$toStage) {
        echo json_encode(['ok' => false, 'message' => 'Stage inválido.']);
        exit;
    }
    $newStageId = (int)$toStage['id'];
    $newStageCode = strtoupper((string)$toStage['code']);
    $fromStageReqId = (int)($data['from_stage_id'] ?? 0);
    $fromStageReqCode = strtoupper((string)($data['from_stage'] ?? ''));
    $toOrderReq = $data['to_order'] ?? null;
    $fromOrderReq = $data['from_order'] ?? null;
    $note = trim($data['note'] ?? '');
    $interviewStr = trim($data['interview_dt'] ?? '');
    $interviewType = trim($data['interview_type'] ?? '');
    $interviewLink = trim($data['interview_link'] ?? '');
    $hireStr = trim($data['hire_dt'] ?? '');
    if ($note === '') {
        echo json_encode(['ok' => false, 'message' => 'Observação é obrigatória.']);
        exit;
    }
    $interviewAt = null;
    $hireDate = null;
    if ($newStageCode === 'ENTREVISTA') {
        if ($interviewStr === '') {
            echo json_encode(['ok' => false, 'message' => 'Data e horário são obrigatórios para entrevistas.']);
            exit;
        }
        if (!preg_match('/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})$/', $interviewStr, $m)) {
            echo json_encode(['ok' => false, 'message' => 'Formato de data/hora inválido. Use DD/MM/AAAA HH:MM']);
            exit;
        }
        $interviewAt = sprintf('%04d-%02d-%02d %02d:%02d:00', (int)$m[3], (int)$m[2], (int)$m[1], (int)$m[4], (int)$m[5]);
        if ($interviewType === 'online' && $interviewLink !== '') {
            if (!filter_var($interviewLink, FILTER_VALIDATE_URL)) {
                echo json_encode(['ok' => false, 'message' => 'URL inválida para entrevista online.']); exit;
            }
        } else {
            $interviewLink = null;
        }
    }
    if ($newStageCode === 'CONTRATADO') {
        if ($hireStr === '') {
            echo json_encode(['ok' => false, 'message' => 'Data de contratação é obrigatória (DD/MM/AAAA).']);
            exit;
        }
        if (!preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $hireStr, $h)) {
            echo json_encode(['ok' => false, 'message' => 'Formato inválido. Use DD/MM/AAAA']);
            exit;
        }
        $hireDate = sprintf('%04d-%02d-%02d', (int)$h[3], (int)$h[2], (int)$h[1]);
    }
    try {
        $old = $candidateModel->findById($id);
        if (!$old) {
            echo json_encode(['ok' => false, 'message' => 'Candidato não encontrado.']);
            exit;
        }
        $fromStageIdOld = (int)($old['stage_id'] ?? 0);
        $fromStageCodeOld = strtoupper((string)($old['stage'] ?? ''));
        if ($fromStageReqId > 0 && $fromStageIdOld > 0 && $fromStageReqId !== $fromStageIdOld) {
            echo json_encode(['ok' => false, 'message' => 'O card foi atualizado por outro usuário. Recarregue o kanban.']);
            exit;
        }
        if ($fromStageReqCode !== '' && $fromStageCodeOld !== '' && $fromStageReqCode !== $fromStageCodeOld && $fromStageReqId <= 0) {
            echo json_encode(['ok' => false, 'message' => 'O estágio de origem mudou. Recarregue e tente novamente.']);
            exit;
        }
        // Garantir tabela de logs
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS job_candidate_stage_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                candidate_id INT NOT NULL,
                from_stage_id INT NULL,
                to_stage_id INT NULL,
                from_stage VARCHAR(32) NULL,
                to_stage VARCHAR(32) NOT NULL,
                note TEXT NOT NULL,
                interview_at DATETIME NULL,
                interview_link VARCHAR(255) NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_candidate(created_at, candidate_id)
            )");
            // Ensure column exists
            try { $db->exec("ALTER TABLE job_candidate_stage_logs ADD COLUMN interview_link VARCHAR(255) NULL"); } catch (Throwable $e2) {}
            try { $db->exec("ALTER TABLE job_candidate_stage_logs ADD COLUMN from_stage_id INT NULL"); } catch (Throwable $e2) {}
            try { $db->exec("ALTER TABLE job_candidate_stage_logs ADD COLUMN to_stage_id INT NULL"); } catch (Throwable $e2) {}
        } catch (Throwable $e) {
            // prosseguir; se falhar criacao, insert abaixo vai capturar
        }
        if ($hireDate) {
            JobCandidate::ensureHireDateColumn($db);
        }
        $db->beginTransaction();
        $sqlUpdate = 'UPDATE job_candidates SET stage_id = :sid WHERE id = :id';
        $stmtUpd = $db->prepare($sqlUpdate);
        if (defined('DEBUG_MODE') && DEBUG_MODE && function_exists('error_log')) {
            error_log('[rh-kanban/move] sql=' . $sqlUpdate . ' params=' . json_encode(['id' => $id, 'stage_id' => $newStageId], JSON_UNESCAPED_UNICODE));
        }
        if (!$stmtUpd->execute([':sid' => $newStageId, ':id' => $id])) {
            $db->rollBack();
            echo json_encode(['ok' => false, 'message' => 'Não foi possível atualizar o estágio.']);
            exit;
        }
        try {
            $stmtStageCompat = $db->prepare('UPDATE job_candidates SET stage = :scode WHERE id = :id');
            $stmtStageCompat->execute([':scode' => $newStageCode, ':id' => $id]);
        } catch (Throwable $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE && function_exists('error_log')) {
                error_log('[rh-kanban/move] stage_compat_update_failed: ' . $e->getMessage());
            }
        }

        $chk = $db->prepare('SELECT stage_id, stage FROM job_candidates WHERE id = :id LIMIT 1');
        $chk->execute([':id' => $id]);
        $persisted = $chk->fetch(PDO::FETCH_ASSOC);
        $persistedId = (int)($persisted['stage_id'] ?? 0);
        $persistedCode = strtoupper((string)($persisted['stage'] ?? ''));
        if ($persistedId !== $newStageId) {
            $db->rollBack();
            if (defined('DEBUG_MODE') && DEBUG_MODE && function_exists('error_log')) {
                error_log('[rh-kanban/move] stage_mismatch expected_id=' . $newStageId . ' expected_code=' . $newStageCode . ' got_id=' . $persistedId . ' got_code=' . $persistedCode);
            }
            echo json_encode(['ok' => false, 'message' => 'Falha ao persistir a etapa no banco.']);
            exit;
        }
        if ($hireDate) {
            $stmtHd = $db->prepare('UPDATE job_candidates SET hire_date = :hd WHERE id = :id');
            $stmtHd->execute([':hd' => $hireDate, ':id' => $id]);
        }
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId !== null) {
            $auditLog->logUpdate('job_candidates', $id, $old, ['stage_id' => $newStageId, 'stage' => $newStageCode], $userId);
        }
        if ($fromStageIdOld <= 0) {
            if ($fromStageReqId > 0) {
                $fromStageIdOld = $fromStageReqId;
                $fromStageCodeOld = strtoupper((string)($stagesById[$fromStageIdOld]['code'] ?? $fromStageCodeOld));
            } elseif ($fromStageReqCode !== '' && isset($stagesByCode[$fromStageReqCode]['id'])) {
                $fromStageIdOld = (int)$stagesByCode[$fromStageReqCode]['id'];
                $fromStageCodeOld = $fromStageReqCode;
            } elseif ($fromStageCodeOld !== '' && isset($stagesByCode[$fromStageCodeOld]['id'])) {
                $fromStageIdOld = (int)$stagesByCode[$fromStageCodeOld]['id'];
            }
        }
        $ins = $db->prepare('INSERT INTO job_candidate_stage_logs (candidate_id, from_stage_id, to_stage_id, from_stage, to_stage, note, interview_at, interview_link, created_by) VALUES (:cid, :fsid, :tsid, :fs, :ts, :note, :iat, :il, :by)');
        $ins->execute([
            ':cid' => $id,
            ':fsid' => ($fromStageIdOld > 0 ? $fromStageIdOld : null),
            ':tsid' => $newStageId,
            ':fs' => ($fromStageCodeOld !== '' ? $fromStageCodeOld : null),
            ':ts' => $newStageCode,
            ':note' => $note,
            ':iat' => $interviewAt,
            ':il' => $interviewLink,
            ':by' => $userId
        ]);
        $movementLogId = (int)$db->lastInsertId();

        if (is_array($toOrderReq)) {
            kanbanApplySortOrder($db, $newStageId, $toOrderReq);
        }
        if (is_array($fromOrderReq)) {
            $fsid = $fromStageReqId > 0 ? $fromStageReqId : (int)($old['stage_id'] ?? 0);
            if ($fsid <= 0 && $fromStageReqCode !== '' && isset($stagesByCode[$fromStageReqCode]['id'])) {
                $fsid = (int)$stagesByCode[$fromStageReqCode]['id'];
            }
            if ($fsid > 0) {
                kanbanApplySortOrder($db, $fsid, $fromOrderReq);
            }
        }
        $db->commit();
        $attachmentResult = ['saved' => [], 'warnings' => []];
        if ($attachmentsAvailable && $isMultipart && is_array($uploadedAttachments)) {
            try {
                $attachmentResult = kanbanStoreAttachments($db, $uploadedAttachments, $movementLogId, $id, $userId !== null ? (int)$userId : null);
            } catch (Throwable $e) {
                $attachmentResult['warnings'][] = 'A movimentação foi salva, mas houve falha ao anexar arquivos.';
            }
        }
        if ($attachmentsAvailable) {
            try {
                kanbanNotifyUsersAboutMove($db, $userId !== null ? (int)$userId : null, $id, ($fromStageCodeOld !== '' ? $fromStageCodeOld : '-'), $newStageCode, count($attachmentResult['saved']));
            } catch (Throwable $notifyErr) {
            }
        }
        if (defined('DEBUG_MODE') && DEBUG_MODE && function_exists('error_log')) {
            error_log('[rh-kanban/move] ok id=' . $id . ' stage_id=' . $newStageId . ' stage=' . $newStageCode);
        }
        echo json_encode([
            'ok' => true,
            'stage_id' => $newStageId,
            'stage' => $newStageCode,
            'persisted_stage' => $persistedCode,
            'movement_log_id' => $movementLogId,
            'attachments' => $attachmentResult['saved'],
            'attachment_warnings' => $attachmentResult['warnings']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Exception $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        if (function_exists('error_log')) { error_log('[rh-kanban/move] erro: ' . $e->getMessage()); }
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Agendamento via formulário dedicado
if (($_GET['action'] ?? '') === 'schedule_interview' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $cid = (int)($_POST['candidate_id'] ?? 0);
        $date = trim($_POST['date'] ?? ''); // YYYY-MM-DD
        $time = trim($_POST['time'] ?? ''); // HH:MM
        $type = trim($_POST['type'] ?? 'presencial');
        $link = trim($_POST['link'] ?? '');
        $notify = trim($_POST['notify_emails'] ?? ''); // CSV opcional
        if ($cid <= 0 || $date === '' || $time === '') { echo json_encode(['ok'=>false,'message'=>'Informe candidato, data e hora']); exit; }
        if ($type === 'online' && $link !== '' && !filter_var($link, FILTER_VALIDATE_URL)) { echo json_encode(['ok'=>false,'message'=>'URL inválida']); exit; }
        // montar datetime e validar futuro
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) { echo json_encode(['ok'=>false,'message'=>'Formato de data/hora inválido']); exit; }
        $interviewAt = $date . ' ' . $time . ':00';
        $now = (new DateTime())->format('Y-m-d H:i:s');
        if ($interviewAt <= $now) { echo json_encode(['ok'=>false,'message'=>'Não é permitido agendar em datas passadas']); exit; }
        // checar conflitos (mesmo candidato ±30min)
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS job_candidate_stage_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                candidate_id INT NOT NULL,
                from_stage_id INT NULL,
                to_stage_id INT NULL,
                from_stage VARCHAR(32) NULL,
                to_stage VARCHAR(32) NOT NULL,
                note TEXT NOT NULL,
                interview_at DATETIME NULL,
                interview_link VARCHAR(255) NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_candidate(created_at, candidate_id)
            )");
            try { $db->exec("ALTER TABLE job_candidate_stage_logs ADD COLUMN interview_link VARCHAR(255) NULL"); } catch (Throwable $e2) {}
            try { $db->exec("ALTER TABLE job_candidate_stage_logs ADD COLUMN from_stage_id INT NULL"); } catch (Throwable $e2) {}
            try { $db->exec("ALTER TABLE job_candidate_stage_logs ADD COLUMN to_stage_id INT NULL"); } catch (Throwable $e2) {}
        } catch (Throwable $e) {}
        $chk = $db->prepare("SELECT COUNT(*) FROM job_candidate_stage_logs WHERE candidate_id = :cid AND interview_at IS NOT NULL AND ABS(TIMESTAMPDIFF(MINUTE, interview_at, :iat)) < 30");
        $chk->bindParam(':cid', $cid, PDO::PARAM_INT);
        $chk->bindParam(':iat', $interviewAt, PDO::PARAM_STR);
        $chk->execute();
        if ((int)$chk->fetchColumn() > 0) { echo json_encode(['ok'=>false,'message'=>'Conflito de agendamento: existe entrevista próxima para este candidato']); exit; }
        // mapping de etapa
        $diffMinStmt = $db->prepare("SELECT TIMESTAMPDIFF(MINUTE, :now, :iat)");
        $diffMinStmt->bindParam(':now', $now, PDO::PARAM_STR);
        $diffMinStmt->bindParam(':iat', $interviewAt, PDO::PARAM_STR);
        $diffMinStmt->execute();
        $diffMin = (int)$diffMinStmt->fetchColumn();
        $newStageCode = ($diffMin <= 120) ? 'ENT_CONFIRMADA' : 'ENT_AGENDADA';
        $newStageId = isset($stagesByCode[$newStageCode]['id']) ? (int)$stagesByCode[$newStageCode]['id'] : 0;
        if ($newStageId <= 0) { echo json_encode(['ok'=>false,'message'=>'Etapa não configurada: ' . $newStageCode]); exit; }
        // atualizar candidato e inserir log
        $row = $candidateModel->findById($cid);
        if (!$row) { echo json_encode(['ok'=>false,'message'=>'Candidato não encontrado']); exit; }
        $db->beginTransaction();
        $stmtUpd = $db->prepare('UPDATE job_candidates SET stage_id = :sid, stage = :scode WHERE id = :id');
        $stmtUpd->execute([':sid' => $newStageId, ':scode' => $newStageCode, ':id' => $cid]);
        $userId = $_SESSION['user_id'] ?? null;
        $fromStageId = (int)($row['stage_id'] ?? 0);
        $fromStageCode = strtoupper((string)($row['stage'] ?? ''));
        if ($fromStageId <= 0 && $fromStageCode !== '' && isset($stagesByCode[$fromStageCode]['id'])) {
            $fromStageId = (int)$stagesByCode[$fromStageCode]['id'];
        }
        $ins = $db->prepare('INSERT INTO job_candidate_stage_logs (candidate_id, from_stage_id, to_stage_id, from_stage, to_stage, note, interview_at, interview_link, created_by) VALUES (:cid, :fsid, :tsid, :fs, :ts, :note, :iat, :il, :by)');
        $note = 'Agendado via formulário de entrevista';
        $ins->bindParam(':cid', $cid, PDO::PARAM_INT);
        $fsid = ($fromStageId > 0) ? $fromStageId : null;
        $ins->bindParam(':fsid', $fsid);
        $ins->bindParam(':tsid', $newStageId);
        $ins->bindParam(':fs', $fromStageCode);
        $ins->bindParam(':ts', $newStageCode);
        $ins->bindParam(':note', $note);
        $ins->bindParam(':iat', $interviewAt);
        $il = ($type==='online' && $link!=='') ? $link : null;
        $ins->bindParam(':il', $il);
        $ins->bindParam(':by', $userId);
        $ins->execute();
        $db->commit();
        // notificações (best-effort)
        try {
            $cInfo = $db->prepare("SELECT name, email FROM job_candidates WHERE id = :id");
            $cInfo->bindParam(':id', $cid, PDO::PARAM_INT);
            $cInfo->execute();
            $c = $cInfo->fetch(PDO::FETCH_ASSOC);
            $emails = [];
            if (!empty($c['email'])) $emails[] = $c['email'];
            if ($notify !== '') {
                foreach (explode(',', $notify) as $em) { $em = trim($em); if ($em !== '') $emails[] = $em; }
            }
            $subject = 'Entrevista ' . ($newStageCode==='ENT_CONFIRMADA'?'Confirmada':'Agendada') . ' - ' . ($c['name'] ?? 'Candidato');
            $msg = "Entrevista {$newStageCode} para {$c['name']} em {$interviewAt}" . (($il) ? "\nLink: {$il}" : '');
            foreach ($emails as $em) {
                @mail($em, $subject, $msg);
            }
        } catch (Throwable $e) { /* silencioso */ }
        echo json_encode(['ok'=>true,'stage_id'=>$newStageId,'stage'=>$newStageCode]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}
// Histórico de movimentações
if (($_GET['action'] ?? '') === 'history' && isset($_GET['id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $cid = (int)$_GET['id'];
    if ($cid <= 0) {
        echo json_encode(['ok' => false, 'message' => 'ID inválido']);
        exit;
    }
    try {
        // Garantir que a tabela exista (produção/homolog podem não ter migração aplicada ainda)
        $db->exec("CREATE TABLE IF NOT EXISTS job_candidate_stage_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            candidate_id INT NOT NULL,
            from_stage_id INT NULL,
            to_stage_id INT NULL,
            from_stage VARCHAR(32) NULL,
            to_stage VARCHAR(32) NOT NULL,
            note TEXT NOT NULL,
            interview_at DATETIME NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_candidate(created_at, candidate_id)
        )");
        try { $db->exec("ALTER TABLE job_candidate_stage_logs ADD COLUMN from_stage_id INT NULL"); } catch (Throwable $e2) {}
        try { $db->exec("ALTER TABLE job_candidate_stage_logs ADD COLUMN to_stage_id INT NULL"); } catch (Throwable $e2) {}
        $stmt = $db->prepare('SELECT l.id AS log_id, l.from_stage_id, l.to_stage_id, l.from_stage, l.to_stage, l.note, l.interview_at, l.created_at,
                                     fs.name AS from_stage_name, ts.name AS to_stage_name
                                FROM job_candidate_stage_logs l
                           LEFT JOIN stages fs ON fs.id = l.from_stage_id
                           LEFT JOIN stages ts ON ts.id = l.to_stage_id
                               WHERE l.candidate_id = :c
                            ORDER BY l.created_at DESC
                               LIMIT 50');
        $stmt->execute([':c' => $cid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            try {
                kanbanEnsureAttachmentSchema($db);
                $logIds = array_map(static fn($r) => (int)$r['log_id'], $rows);
                $ph = implode(',', array_fill(0, count($logIds), '?'));
                $attStmt = $db->prepare("SELECT id, movement_log_id, original_name, file_path, thumb_path, is_image, size_stored, uploaded_at
                                           FROM job_candidate_stage_attachments
                                          WHERE movement_log_id IN ($ph)
                                          ORDER BY id ASC");
                $attStmt->execute($logIds);
                $atts = $attStmt->fetchAll(PDO::FETCH_ASSOC);
                $map = [];
                foreach ($atts as $a) {
                    $lid = (int)$a['movement_log_id'];
                    if (!isset($map[$lid])) $map[$lid] = [];
                    $map[$lid][] = $a;
                }
                foreach ($rows as &$r) {
                    $lid = (int)$r['log_id'];
                    $r['attachments'] = $map[$lid] ?? [];
                }
                unset($r);
            } catch (Throwable $e) {
                foreach ($rows as &$r) {
                    $r['attachments'] = [];
                }
            }
            unset($r);
        }
        echo json_encode(['ok' => true, 'data' => $rows]);
    } catch (PDOException $e) {
        if ($e->getCode() === '42S02') {
            // Tabela não existe e não pôde ser criada; retornar lista vazia para não quebrar fluxo
            if (function_exists('error_log')) {
                error_log('[rh-kanban/history] Tabela job_candidate_stage_logs ausente. Retornando vazio. Detalhe: ' . $e->getMessage());
            }
            echo json_encode(['ok' => true, 'data' => []]);
        } else {
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        }
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if (($_GET['action'] ?? '') === 'add_note') {
    header('Content-Type: application/json; charset=utf-8');
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    $cid = (int)($data['id'] ?? 0);
    $note = trim($data['note'] ?? '');
    $ensureStage = !empty($data['ensure_stage']);
    $interviewStr = trim($data['interview_dt'] ?? '');
    $interviewType = trim($data['interview_type'] ?? '');
    $interviewLink = trim($data['interview_link'] ?? '');
    if ($cid <= 0 || $note === '') { echo json_encode(['ok'=>false,'message'=>'Dados inválidos']); exit; }
    try {
        $row = $candidateModel->findById($cid);
        if (!$row) { echo json_encode(['ok'=>false,'message'=>'Candidato não encontrado']); exit; }
        $fromStageId = (int)($row['stage_id'] ?? 0);
        $fromStage = strtoupper((string)($row['stage'] ?? ''));
        if ($fromStageId <= 0 && $fromStage !== '' && isset($stagesByCode[$fromStage]['id'])) {
            $fromStageId = (int)$stagesByCode[$fromStage]['id'];
        }
        $toStageId = $fromStageId;
        $toStage = $fromStage;
        $iat = null; $il = null;
        if ($ensureStage) {
            // Validar data/hora e link quando mover para ENTREVISTA
            if ($interviewStr === '' || !preg_match('/^(\d{2})\/(\d{2})\/(\d{4})\s+\d{2}:\d{2}$/', $interviewStr)) {
                echo json_encode(['ok'=>false,'message'=>'Informe data e horário válidos (DD/MM/AAAA HH:MM).']); exit;
            }
            if ($interviewType === 'online' && $interviewLink !== '' && !filter_var($interviewLink, FILTER_VALIDATE_URL)) {
                echo json_encode(['ok'=>false,'message'=>'URL inválida para entrevista online.']); exit;
            }
            $m = []; preg_match('/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})$/', $interviewStr, $m);
            $iat = sprintf('%04d-%02d-%02d %02d:%02d:00', (int)$m[3], (int)$m[2], (int)$m[1], (int)$m[4], (int)$m[5]);
            $il = ($interviewType === 'online' && $interviewLink !== '') ? $interviewLink : null;
            $toStage = 'ENTREVISTA';
            $toStageId = isset($stagesByCode[$toStage]['id']) ? (int)$stagesByCode[$toStage]['id'] : 0;
            if ($toStageId <= 0) { echo json_encode(['ok'=>false,'message'=>'Etapa não configurada: ENTREVISTA']); exit; }
        }
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS job_candidate_stage_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                candidate_id INT NOT NULL,
                from_stage_id INT NULL,
                to_stage_id INT NULL,
                from_stage VARCHAR(32) NULL,
                to_stage VARCHAR(32) NULL,
                note TEXT NOT NULL,
                interview_at DATETIME NULL,
                interview_link VARCHAR(255) NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_candidate(created_at, candidate_id)
            )");
            try { $db->exec("ALTER TABLE job_candidate_stage_logs ADD COLUMN interview_link VARCHAR(255) NULL"); } catch (Throwable $e2) {}
            try { $db->exec("ALTER TABLE job_candidate_stage_logs ADD COLUMN from_stage_id INT NULL"); } catch (Throwable $e2) {}
            try { $db->exec("ALTER TABLE job_candidate_stage_logs ADD COLUMN to_stage_id INT NULL"); } catch (Throwable $e2) {}
        } catch (Throwable $e) {}
        $db->beginTransaction();
        $userId = $_SESSION['user_id'] ?? null;
        if ($ensureStage && $fromStage !== 'ENTREVISTA') {
            $upd = $db->prepare("UPDATE job_candidates SET stage_id = :sid, stage = 'ENTREVISTA' WHERE id = :id");
            $upd->execute([':sid' => $toStageId, ':id' => $cid]);
        }
        $st = $db->prepare('INSERT INTO job_candidate_stage_logs (candidate_id, from_stage_id, to_stage_id, from_stage, to_stage, note, interview_at, interview_link, created_by) VALUES (:cid, :fsid, :tsid, :fs, :ts, :note, :iat, :il, :by)');
        $st->execute([
            ':cid' => $cid,
            ':fsid' => ($fromStageId > 0 ? $fromStageId : null),
            ':tsid' => ($toStageId > 0 ? $toStageId : null),
            ':fs' => ($fromStage !== '' ? $fromStage : null),
            ':ts' => ($toStage !== '' ? $toStage : null),
            ':note' => $note,
            ':iat' => $iat,
            ':il' => $il,
            ':by' => $userId
        ]);
        $db->commit();
        if (function_exists('error_log')) { error_log("[kanban/add_note] cid=$cid ensureStage=".($ensureStage?'1':'0')); }
        echo json_encode(['ok'=>true,'stage_id'=>$toStageId,'stage'=>$toStage]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        if (function_exists('error_log')) { error_log('[kanban/add_note] erro: '.$e->getMessage()); }
        echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

// Visualização de currículo (read-only para RH/Admin)
if (($_GET['action'] ?? '') === 'view' && isset($_GET['id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $cid = (int)$_GET['id'];
    $debug = (($_GET['kanban_debug'] ?? '') === '1');
    if ($debug && function_exists('error_log')) {
        $uid = $_SESSION['user_id'] ?? null;
        error_log('[rh-kanban/view] cid=' . $cid . ' user_id=' . ($uid === null ? 'null' : (int)$uid));
    }
    try {
        $stmt = $db->prepare('SELECT c.*, v.title AS vacancy_title, s.id AS stage_id, s.code AS stage_code, s.name AS stage_name
                                FROM job_candidates c
                           LEFT JOIN job_vacancies v ON c.vacancy_id = v.id
                           LEFT JOIN stages s ON s.id = c.stage_id
                               WHERE c.id = :id
                               LIMIT 1');
        $stmt->execute([':id' => $cid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['ok' => false, 'message' => 'Currículo não encontrado.']);
            exit;
        }
        $notes = [];
        if (!empty($row['notes'])) {
            $j = json_decode($row['notes'], true);
            if (is_array($j)) $notes = $j;
        }
        $data = [
            'id' => (int)$row['id'],
            'vacancy' => $row['vacancy_title'] ?? null,
            'name' => $row['name'] ?? '',
            'email' => $row['email'] ?? '',
            'phone' => $row['phone'] ?? '',
            'status' => $row['status'] ?? '',
            'stage_id' => isset($row['stage_id']) ? (int)$row['stage_id'] : null,
            'stage_code' => $row['stage_code'] ?? ($row['stage'] ?? ''),
            'stage_name' => $row['stage_name'] ?? null,
            'resume_url' => $row['resume_url'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'cpf' => $notes['cpf'] ?? null,
            'linkedin' => $notes['linkedin'] ?? null,
            'portfolio' => $notes['portfolio'] ?? null,
            'skills' => $notes['skills'] ?? null,
            'desired_role' => $notes['desired_role'] ?? null,
            'experiences' => $notes['experiences'] ?? null,
            'education' => $notes['education'] ?? null,
            'files' => []
        ];
        try {
            $data['files'] = kanbanListCandidateFiles($db, $cid);
        } catch (Throwable $e) {
            $data['files'] = [];
        }
        if ($debug && function_exists('error_log')) {
            error_log('[rh-kanban/view] ok cid=' . $cid . ' name=' . ($data['name'] ?? ''));
        }
        echo json_encode(['ok' => true, 'data' => $data]);
    } catch (Exception $e) {
        if ($debug && function_exists('error_log')) {
            error_log('[rh-kanban/view] erro cid=' . $cid . ' msg=' . $e->getMessage());
        }
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
if (($_GET['action'] ?? '') === 'upload_candidate_file' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $cid = (int)($_POST['candidate_id'] ?? 0);
        if ($cid <= 0) {
            echo json_encode(['ok' => false, 'message' => 'Candidato inválido.']);
            exit;
        }
        $chk = $candidateModel->findById($cid);
        if (!$chk) {
            echo json_encode(['ok' => false, 'message' => 'Candidato não encontrado.']);
            exit;
        }
        if (empty($_FILES['candidate_file'])) {
            echo json_encode(['ok' => false, 'message' => 'Nenhum arquivo enviado.']);
            exit;
        }
        $saved = kanbanStoreCandidateFile($db, $_FILES['candidate_file'], $cid, (int)($_SESSION['user_id'] ?? 0));
        echo json_encode(['ok' => true, 'file' => $saved], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
try { kanbanEnsureSortOrder($db); } catch (Throwable $e) {}
$search = trim($_GET['q'] ?? '');
$where = [];
$params = [];
if ($search !== '') {
    $where[] = "(c.name LIKE :q1 OR c.notes LIKE :q2)";
    $params['q1'] = '%' . $search . '%';
    $params['q2'] = '%' . $search . '%';
}
$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}
$sqlSort = "SELECT c.id, c.vacancy_id, c.name, c.email, c.phone, c.resume_url, c.notes, c.status,
                   c.stage_id, c.stage, COALESCE(s.code, c.stage) AS stage_code,
                   COALESCE(s.name, c.stage) AS stage_name,
                   c.created_at, c.sort_order, COALESCE(s.position, 999999) AS stage_pos
              FROM job_candidates c
         LEFT JOIN stages s ON s.id = c.stage_id
            {$whereSql}
          ORDER BY stage_pos ASC, COALESCE(c.sort_order, 2147483647) ASC, c.created_at DESC
             LIMIT 300";
$sqlFallback = "SELECT c.id, c.vacancy_id, c.name, c.email, c.phone, c.resume_url, c.notes, c.status,
                       c.stage_id, c.stage, c.stage AS stage_code,
                       c.stage AS stage_name,
                       c.created_at, c.sort_order, 999999 AS stage_pos
                  FROM job_candidates c
                {$whereSql}
              ORDER BY COALESCE(c.sort_order, 2147483647) ASC, c.created_at DESC
                 LIMIT 300";
try {
    $stmt = $db->prepare($sqlSort);
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stmt = $db->prepare($sqlFallback);
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$columns = [];
foreach ($stagesList as $s) {
    $key = ($s['id'] !== null) ? (int)$s['id'] : strtoupper((string)$s['code']);
    $columns[$key] = [];
}
foreach ($rows as $row) {
    $sid = (int)($row['stage_id'] ?? 0);
    if ($sid > 0 && array_key_exists($sid, $columns)) {
        $columns[$sid][] = $row;
        continue;
    }
    $code = strtoupper((string)($row['stage_code'] ?? $row['stage'] ?? 'RECEBIDO'));
    $mappedId = isset($stagesByCode[$code]['id']) ? (int)$stagesByCode[$code]['id'] : null;
    $key = ($mappedId !== null && array_key_exists($mappedId, $columns)) ? $mappedId : $code;
    if (!array_key_exists($key, $columns)) {
        $fallbackCode = 'RECEBIDO';
        $fallbackId = isset($stagesByCode[$fallbackCode]['id']) ? (int)$stagesByCode[$fallbackCode]['id'] : null;
        $key = ($fallbackId !== null && array_key_exists($fallbackId, $columns)) ? $fallbackId : $fallbackCode;
    }
    $columns[$key][] = $row;
}

include 'includes/header.php';
?>

<main id="main-content" class="flex-1">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-rich-black">Kanban de Currículos</h1>
                <p class="text-sm text-paynes-gray mt-1">
                    Acompanhe o fluxo dos candidatos entre as etapas do processo seletivo.
                </p>
            </div>
            <form method="GET" class="flex flex-col sm:flex-row gap-2">
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Buscar por nome, skills, cargo..."
                       class="w-full sm:w-64 px-3 py-2 border border-silver-lake-blue rounded-lg focus:outline-none focus:ring-2 focus:ring-paynes-gray text-sm">
                <button type="submit"
                        class="px-4 py-2 bg-paynes-gray text-white text-sm rounded-lg hover:bg-prussian-blue focus:outline-none focus:ring-2 focus:ring-prussian-blue">
                    Buscar
                </button>
            </form>
        </div>

        <div class="overflow-x-auto pb-4">
            <div class="min-w-max flex gap-4" aria-label="Quadro Kanban de currículos">
                <?php foreach ($stagesList as $s): ?>
                    <?php $code = strtoupper((string)$s['code']); $label = (string)$s['name']; $sid = $s['id'] !== null ? (int)$s['id'] : null; $colKey = $sid !== null ? $sid : $code; $color = (string)($s['color'] ?? ($fallbackStageColors[$code] ?? '#3e5c76')); ?>
                    <section class="w-72 flex-shrink-0 bg-white rounded-lg shadow-sm border border-silver-lake-blue flex flex-col" aria-label="<?php echo htmlspecialchars($label); ?>">
                        <div class="kanban-col" data-stage-id="<?php echo $sid !== null ? (int)$sid : 0; ?>" data-stage="<?php echo htmlspecialchars($code); ?>">
                        <header class="kanban-col-header px-3 py-2 bg-eggshell border-b border-silver-lake-blue flex items-center justify-between" style="border-left: 4px solid <?php echo htmlspecialchars($color); ?>;">
                            <h2 class="text-xs font-semibold text-paynes-gray uppercase tracking-wide">
                                <?php echo htmlspecialchars($label); ?>
                            </h2>
                            <span class="text-xs text-paynes-gray" data-counter="<?php echo $sid !== null ? (int)$sid : htmlspecialchars($code); ?>">
                                <?php echo count($columns[$colKey] ?? []); ?>
                            </span>
                        </header>
                        <div class="kanban-col-body p-2 space-y-2 min-h-[12rem] flex-1">
                            <?php foreach (($columns[$colKey] ?? []) as $cand): ?>
                                <?php
                                $notes = [];
                                if (!empty($cand['notes'])) {
                                    $decoded = json_decode($cand['notes'], true);
                                    if (is_array($decoded)) {
                                        $notes = $decoded;
                                    }
                                }
                                $desiredRole = $notes['desired_role'] ?? '';
                                $skills = $notes['skills'] ?? '';
                                ?>
                                <article class="kanban-card bg-white border border-silver-lake-blue rounded-md shadow-sm cursor-move"
                                         draggable="true"
                                         data-id="<?php echo (int)$cand['id']; ?>">
                                    <div class="p-2 border-b border-silver-lake-blue">
                                        <p class="text-sm font-semibold text-rich-black">
                                            <?php echo htmlspecialchars($cand['name']); ?>
                                        </p>
                                        <?php if ($desiredRole !== ''): ?>
                                            <p class="text-xs text-paynes-gray">
                                                <?php echo htmlspecialchars($desiredRole); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="p-2 space-y-1">
                                        <p class="text-xs text-paynes-gray truncate">
                                            <?php echo htmlspecialchars($cand['email']); ?>
                                        </p>
                                        <?php if (!empty($cand['phone'])): ?>
                                            <p class="text-xs text-paynes-gray">
                                                <?php echo htmlspecialchars($cand['phone']); ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if ($skills !== ''): ?>
                                            <div class="flex flex-wrap gap-1 mt-1">
                                                <?php foreach (explode(',', $skills) as $skill): ?>
                                                    <?php $skill = trim($skill); if ($skill === '') continue; ?>
                                                    <span class="px-2 py-0.5 rounded-full bg-eggshell text-[10px] text-paynes-gray">
                                                        <?php echo htmlspecialchars($skill); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="px-2 pb-2 flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <button type="button" draggable="false" class="text-xs text-blue-700 hover:underline" onclick="kanbanViewCV(<?php echo (int)$cand['id']; ?>)">Ver dados</button>
                                            <?php if (!empty($cand['resume_url'])): ?>
                                                <a draggable="false" href="rh-cv.php?id=<?php echo (int)$cand['id']; ?>" target="_blank" class="text-xs text-blue-700 hover:underline">Abrir currículo</a>
                                            <?php else: ?>
                                                <span class="text-xs text-gray-400">Sem arquivo</span>
                                            <?php endif; ?>
                                            <button type="button" draggable="false" class="text-xs text-blue-700 hover:underline" onclick="kanbanShowHistory(<?php echo (int)$cand['id']; ?>)">Histórico</button>
                                        </div>
                                        <?php $stBadge = strtoupper((string)($cand['stage_code'] ?? $cand['stage'] ?? '')); $stColor = $fallbackStageColors[$stBadge] ?? '#3e5c76'; ?>
                                        <span class="kanban-stage-badge text-[10px] text-white px-2 py-0.5 rounded" style="background-color: <?php echo $stColor; ?>"><?php echo htmlspecialchars($cand['stage_name'] ?? ($cand['stage'] ?? '')); ?></span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>

<style>
  .kanban-col { display: flex; flex-direction: column; overflow-y: auto; max-height: calc(var(--vvh, 100vh) - 240px); min-height: 420px; }
  .kanban-col-header { position: sticky; top: 0; z-index: 20; }
  .kanban-is-dragging .kanban-col { outline: 2px dashed rgba(62,92,118,.25); outline-offset: -2px; }
  .kanban-col.is-dragover { outline: 2px solid rgba(37,99,235,.65); outline-offset: -2px; box-shadow: 0 6px 18px rgba(37,99,235,.15); }
  .kanban-is-dragging .kanban-card { pointer-events: none; }
  .kanban-toast { position: fixed; right: 16px; bottom: 16px; z-index: 60; background: #111827; color: #fff; padding: 10px 12px; border-radius: 10px; font-size: 13px; box-shadow: 0 10px 25px rgba(0,0,0,.25); max-width: min(420px, calc(100vw - 32px)); display: none; }
  .kanban-toast.show { display: block; }
  .kanban-toast.success { background: #065f46; }
  .kanban-toast.error { background: #991b1b; }
  .kanban-flash { animation: kanbanFlash 900ms ease-out 1; }
  @keyframes kanbanFlash { 0% { box-shadow: 0 0 0 0 rgba(16,185,129,.0); } 20% { box-shadow: 0 0 0 3px rgba(16,185,129,.45); } 100% { box-shadow: 0 0 0 0 rgba(16,185,129,.0); } }
</style>

<script>
let kanbanDragId = null;
let kanbanHoverCol = null;
const KANBAN_STAGES = <?php echo json_encode($stagesList, JSON_UNESCAPED_UNICODE); ?>;
const KANBAN_STAGE_BY_ID = {};
const KANBAN_STAGE_BY_CODE = {};
const KANBAN_STAGE_POS_BY_ID = {};
KANBAN_STAGES.forEach(s => {
  if (s && s.id != null) KANBAN_STAGE_BY_ID[String(s.id)] = s;
  if (s && s.code) KANBAN_STAGE_BY_CODE[String(s.code).toUpperCase()] = s;
  if (s && s.id != null) {
    const pos = Number((s.position ?? s.sort_order ?? 0));
    KANBAN_STAGE_POS_BY_ID[String(s.id)] = Number.isFinite(pos) && pos > 0 ? pos : 0;
  }
});

function kanbanIsDebug() {
    return new URLSearchParams(location.search).get('kanban_debug') === '1';
}

function kanbanLog(type, data) {
    if (!kanbanIsDebug()) return;
    try { console.log('[kanban-dnd]', type, data); } catch (e) {}
}

function kanbanSnapshotColumn(col) {
    return Array.from(col.querySelectorAll('[data-id]')).map(el => String(el.dataset.id));
}

function kanbanRestoreColumnOrder(col, ids) {
    const map = new Map();
    Array.from(col.querySelectorAll('[data-id]')).forEach(el => map.set(String(el.dataset.id), el));
    ids.forEach(id => {
        const el = map.get(String(id));
        if (el) col.appendChild(el);
    });
}

function kanbanGetDragAfterElement(container, y) {
    const cards = Array.from(container.querySelectorAll('[data-id]')).filter(el => !el.classList.contains('kanban-dragging'));
    let closest = { offset: Number.NEGATIVE_INFINITY, element: null };
    for (const child of cards) {
        const box = child.getBoundingClientRect();
        const offset = y - (box.top + box.height / 2);
        if (offset < 0 && offset > closest.offset) {
            closest = { offset, element: child };
        }
    }
    return closest.element;
}

function kanbanAllowDrop(event) {
    event.preventDefault();
    try { if (event.dataTransfer) event.dataTransfer.dropEffect = 'move'; } catch (e) {}
    const col = event.currentTarget && event.currentTarget.classList && event.currentTarget.classList.contains('kanban-col') ? event.currentTarget : null;
    if (col && kanbanHoverCol !== col) {
        if (kanbanHoverCol) kanbanHoverCol.classList.remove('is-dragover');
        kanbanHoverCol = col;
        kanbanHoverCol.classList.add('is-dragover');
    }
}

function kanbanToast(type, message) {
    let el = document.getElementById('kanbanToast');
    if (!el) {
        el = document.createElement('div');
        el.id = 'kanbanToast';
        el.className = 'kanban-toast';
        document.body.appendChild(el);
    }
    el.classList.remove('success', 'error', 'show');
    el.classList.add(type === 'success' ? 'success' : (type === 'error' ? 'error' : ''));
    el.textContent = String(message || '');
    el.classList.add('show');
    window.clearTimeout(window.__kanbanToastT);
    window.__kanbanToastT = window.setTimeout(() => { el.classList.remove('show'); }, 2400);
}

function kanbanSetButtonLoading(btn, loading, loadingText){
    if (!btn) return;
    if (loading) {
        if (!btn.dataset.originalLabel) btn.dataset.originalLabel = btn.textContent || '';
        btn.textContent = loadingText || 'Processando...';
        btn.disabled = true;
        btn.classList.add('opacity-70', 'cursor-not-allowed');
    } else {
        if (btn.dataset.originalLabel) btn.textContent = btn.dataset.originalLabel;
        btn.disabled = false;
        btn.classList.remove('opacity-70', 'cursor-not-allowed');
    }
}

const KANBAN_ATTACHMENT_ALLOWED_EXT = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png','gif'];
const KANBAN_ATTACHMENT_MAX_FILE = 10 * 1024 * 1024;
const KANBAN_ATTACHMENT_MAX_TOTAL = 50 * 1024 * 1024;
window.__kanbanAttachmentCtx = null;
window.__kanbanAttachmentFiles = [];

function kanbanFmtBytes(v){
    const n = Number(v || 0);
    if (n < 1024) return n + ' B';
    if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
    return (n / (1024 * 1024)).toFixed(2) + ' MB';
}

function kanbanAttachmentExt(name){
    const n = String(name || '');
    const i = n.lastIndexOf('.');
    return i >= 0 ? n.slice(i + 1).toLowerCase() : '';
}

function kanbanRenderAttachmentPreview(){
    const list = document.getElementById('attList');
    const totalEl = document.getElementById('attTotalSize');
    if (!list || !totalEl) return;
    const files = window.__kanbanAttachmentFiles || [];
    let total = 0;
    const rows = files.map((f, idx) => {
        total += Number(f.size || 0);
        const isImg = /^image\//i.test(String(f.type || '')) || ['jpg','jpeg','png','gif'].includes(kanbanAttachmentExt(f.name));
        const preview = isImg ? '<img src="' + URL.createObjectURL(f) + '" class="h-12 w-12 rounded object-cover border" alt="preview">' : '<div class="h-12 w-12 rounded border bg-gray-50 flex items-center justify-center text-[10px] text-paynes-gray">ARQ</div>';
        return '<div class="flex items-center justify-between gap-2 border rounded p-2">' +
            '<div class="flex items-center gap-2 min-w-0">' + preview +
            '<div class="min-w-0"><div class="text-xs font-medium truncate">' + esc(f.name) + '</div><div class="text-[11px] text-paynes-gray">' + esc(kanbanFmtBytes(f.size)) + '</div></div>' +
            '</div>' +
            '<button type="button" class="text-xs text-red-700 underline" onclick="kanbanRemoveAttachment(' + idx + ')">Remover</button>' +
            '</div>';
    });
    list.innerHTML = rows.length ? rows.join('') : '<div class="text-xs text-paynes-gray">Nenhum anexo selecionado.</div>';
    totalEl.textContent = 'Total: ' + kanbanFmtBytes(total) + ' / 50 MB';
}

function kanbanRemoveAttachment(index){
    const files = window.__kanbanAttachmentFiles || [];
    files.splice(index, 1);
    window.__kanbanAttachmentFiles = files;
    kanbanRenderAttachmentPreview();
}

function kanbanAddAttachmentFiles(fileList){
    const curr = window.__kanbanAttachmentFiles || [];
    const next = curr.slice();
    for (const f of Array.from(fileList || [])) {
        const ext = kanbanAttachmentExt(f.name);
        if (!KANBAN_ATTACHMENT_ALLOWED_EXT.includes(ext)) {
            kanbanToast('error', 'Tipo não permitido: ' + f.name);
            continue;
        }
        if ((f.size || 0) > KANBAN_ATTACHMENT_MAX_FILE) {
            kanbanToast('error', 'Arquivo excede 10MB: ' + f.name);
            continue;
        }
        next.push(f);
    }
    const total = next.reduce((a, b) => a + Number(b.size || 0), 0);
    if (total > KANBAN_ATTACHMENT_MAX_TOTAL) {
        kanbanToast('error', 'Total de anexos excede 50MB.');
        return;
    }
    window.__kanbanAttachmentFiles = next;
    kanbanRenderAttachmentPreview();
}

function kanbanOpenAttachmentModal(ctx){
    window.__kanbanAttachmentCtx = ctx || null;
    window.__kanbanAttachmentFiles = [];
    const titleEl = document.getElementById('attTitle');
    if (titleEl && ctx) titleEl.textContent = 'Anexos da movimentação ' + String(ctx.fromLabel || '') + ' → ' + String(ctx.toLabel || '');
    const errEl = document.getElementById('attError');
    if (errEl) errEl.textContent = '';
    kanbanAttachmentProgress(0, 0, true);
    kanbanAttachmentUploadingState(false);
    kanbanRenderAttachmentPreview();
    modalOpen('attachModal');
}

function kanbanCloseAttachmentModal(){
    window.__kanbanAttachmentCtx = null;
    window.__kanbanAttachmentFiles = [];
    modalClose('attachModal');
}

function kanbanAbortAttachmentModal(){
    const ctx = window.__kanbanAttachmentCtx;
    kanbanCloseAttachmentModal();
    if (ctx && typeof ctx.onCancel === 'function') ctx.onCancel();
}

function kanbanAttachmentUploadingState(loading){
    const sendBtn = document.getElementById('attSendBtn');
    const skipBtn = document.getElementById('attSkipBtn');
    const cancelBtn = document.getElementById('attCancelBtn');
    const input = document.getElementById('attInput');
    const drop = document.getElementById('attDropzone');
    if (sendBtn) {
        if (loading) {
            if (!sendBtn.dataset.originalLabel) sendBtn.dataset.originalLabel = sendBtn.textContent || 'Enviar movimentação';
            sendBtn.textContent = 'Enviando...';
            sendBtn.disabled = true;
            sendBtn.classList.add('opacity-70', 'cursor-not-allowed');
        } else {
            sendBtn.textContent = sendBtn.dataset.originalLabel || 'Enviar movimentação';
            sendBtn.disabled = false;
            sendBtn.classList.remove('opacity-70', 'cursor-not-allowed');
        }
    }
    if (skipBtn) skipBtn.disabled = !!loading;
    if (cancelBtn) cancelBtn.disabled = !!loading;
    if (input) input.disabled = !!loading;
    if (drop) {
        if (loading) drop.classList.add('opacity-70', 'pointer-events-none');
        else drop.classList.remove('opacity-70', 'pointer-events-none');
    }
}

function kanbanAttachmentProgress(loaded, total, reset){
    const wrap = document.getElementById('attProgressWrap');
    const bar = document.getElementById('attProgressBar');
    const txt = document.getElementById('attProgressText');
    if (!wrap || !bar || !txt) return;
    if (reset) {
        wrap.classList.add('hidden');
        bar.style.width = '0%';
        txt.textContent = '';
        return;
    }
    wrap.classList.remove('hidden');
    const l = Number(loaded || 0);
    const t = Number(total || 0);
    const p = t > 0 ? Math.max(0, Math.min(100, Math.round((l / t) * 100))) : 0;
    bar.style.width = p + '%';
    txt.textContent = p + '% enviado (' + kanbanFmtBytes(l) + ' / ' + kanbanFmtBytes(t) + ')';
}

function kanbanRenderCandidateFiles(files){
    const arr = Array.isArray(files) ? files : [];
    if (arr.length === 0) {
        return '<div class="text-xs text-paynes-gray">Nenhum arquivo enviado.</div>';
    }
    return '<div class="space-y-2">' + arr.map(f => {
        const isImg = parseInt(String(f.is_image || '0'), 10) === 1;
        const thumb = isImg && f.thumb_path
            ? ('<img src="' + escAttr(f.thumb_path) + '" class="h-10 w-10 rounded object-cover border" alt="thumb">')
            : '<div class="h-10 w-10 rounded border bg-gray-50 flex items-center justify-center text-[10px] text-paynes-gray">ARQ</div>';
        return '<a href="' + escAttr(f.file_path || '#') + '" target="_blank" class="flex items-center gap-2 border rounded p-2 hover:bg-gray-50">' +
            thumb +
            '<div class="min-w-0"><div class="text-xs font-medium truncate">' + esc(f.original_name || '') + '</div><div class="text-[11px] text-paynes-gray">' + esc(kanbanFmtBytes(f.size_bytes || 0)) + '</div></div></a>';
    }).join('') + '</div>';
}

function kanbanUploadCandidateFile(candidateId){
    const input = document.getElementById('cvFileInput');
    const btn = document.getElementById('cvUploadBtn');
    const err = document.getElementById('cvUploadError');
    const list = document.getElementById('cvFilesList');
    if (!input || !btn || !list) return;
    if (err) err.textContent = '';
    const file = input.files && input.files[0] ? input.files[0] : null;
    if (!file) {
        if (err) err.textContent = 'Selecione um arquivo.';
        return;
    }
    const ext = kanbanAttachmentExt(file.name);
    const allowed = ['pdf','doc','docx','jpg','jpeg','png'];
    if (!allowed.includes(ext)) {
        if (err) err.textContent = 'Formato inválido. Use PDF, DOC, DOCX, JPG ou PNG.';
        return;
    }
    if ((file.size || 0) > KANBAN_ATTACHMENT_MAX_FILE) {
        if (err) err.textContent = 'Arquivo excede 10MB.';
        return;
    }
    kanbanSetButtonLoading(btn, true, 'Enviando...');
    const fd = new FormData();
    fd.append('candidate_id', String(candidateId));
    fd.append('candidate_file', file, file.name);
    fetch('rh-kanban.php?action=upload_candidate_file', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(j => {
        kanbanSetButtonLoading(btn, false);
        if (!j || !j.ok) {
            if (err) err.textContent = (j && j.message) ? j.message : 'Falha no upload.';
            return;
        }
        input.value = '';
        list.innerHTML = kanbanRenderCandidateFiles([j.file].concat([]));
        kanbanToast('success', 'Arquivo enviado com sucesso.');
        kanbanViewCV(candidateId);
      })
      .catch(() => {
        kanbanSetButtonLoading(btn, false);
        if (err) err.textContent = 'Erro de comunicação no upload.';
      });
}

function kanbanSetCardStage(card, stageId) {
    const badge = card.querySelector('.kanban-stage-badge');
    if (badge) {
        const st = KANBAN_STAGE_BY_ID[String(stageId)] || null;
        if (st) {
            badge.textContent = String(st.name || st.code || '');
            badge.style.backgroundColor = st.color ? String(st.color) : '#3e5c76';
        }
    }
}

function kanbanDrop(event) {
    event.preventDefault();
    const targetCol = event.currentTarget;
    const newStageId = parseInt((targetCol?.dataset?.stageId || '0'), 10) || 0;
    const newStage = KANBAN_STAGE_BY_ID[String(newStageId)] || null;
    const targetBody = targetCol ? targetCol.querySelector('.kanban-col-body') : null;
    const dragId = String(kanbanDragId || (function(){
        try { return event.dataTransfer ? event.dataTransfer.getData('text/plain') : ''; } catch (e) { return ''; }
    })() || '').trim();
    if (!dragId || !newStage || !targetBody) return;
    const card = document.querySelector('[data-id="' + dragId + '"]');
    if (!card) return;

    const fromCol = card.closest('.kanban-col');
    const fromBody = fromCol ? fromCol.querySelector('.kanban-col-body') : card.parentElement;
    const fromStageId = parseInt((fromCol?.dataset?.stageId || '0'), 10) || 0;
    const fromStage = KANBAN_STAGE_BY_ID[String(fromStageId)] || null;
    const fromOrderBefore = kanbanSnapshotColumn(fromBody);
    const toOrderBefore = kanbanSnapshotColumn(targetBody);
    const sameStage = fromStageId > 0 && fromStageId === newStageId;
    if (sameStage) {
        const insertBefore = kanbanGetDragAfterElement(targetBody, event.clientY);
        if (insertBefore) {
            targetBody.insertBefore(card, insertBefore);
        } else {
            targetBody.appendChild(card);
        }
        const toOrder = kanbanSnapshotColumn(targetBody);
        kanbanPersistOrder(newStageId, toOrder, () => {
            kanbanRestoreColumnOrder(targetBody, toOrderBefore);
            kanbanToast('error', 'Não foi possível salvar a nova ordem.');
        });
        return;
    }
    const requiresSchedule = (newStage.code === 'ENTREVISTA' || newStage.code === 'ENT_AGENDADA' || newStage.code === 'ENT_CONFIRMADA');
    const insertBefore = kanbanGetDragAfterElement(targetBody, event.clientY);

    let note = '';
    while (!note) {
        note = prompt('Informe a observação para mover de ' + String(fromStage?.name || fromStage?.code || '') + ' para ' + String(newStage?.name || newStage?.code || '') + ':', '');
        if (note === null) {
            kanbanRestoreColumnOrder(fromBody, fromOrderBefore);
            if (fromBody !== targetBody) kanbanRestoreColumnOrder(targetBody, toOrderBefore);
            return;
        }
        note = (note || '').trim();
        if (!note) kanbanToast('error', 'Observação é obrigatória.');
    }

    if (requiresSchedule) {
        window.__pendingMove = {
            id: dragId,
            fromStageId,
            newStageId,
            note,
            fromBody,
            targetBody,
            fromOrderBefore,
            toOrderBefore,
            beforeId: insertBefore?.dataset?.id || null
        };
        modalOpen('scheduleModal');
        return;
    }

    if (insertBefore) {
        targetBody.insertBefore(card, insertBefore);
    } else {
        targetBody.appendChild(card);
    }

    const fromOrder = kanbanSnapshotColumn(fromBody);
    const toOrder = kanbanSnapshotColumn(targetBody);

    let hire_dt = '';
    if (newStage.code === 'CONTRATADO') {
        const msgH = 'Informe a data de contratação (DD/MM/AAAA):';
        while (true) {
            hire_dt = prompt(msgH, '');
            if (hire_dt === null) {
                kanbanRestoreColumnOrder(fromBody, fromOrderBefore);
                kanbanRestoreColumnOrder(targetBody, toOrderBefore);
                return;
            }
            hire_dt = (hire_dt || '').trim();
            if (!/^\d{2}\/\d{2}\/\d{4}$/.test(hire_dt)) {
                kanbanToast('error', 'Formato inválido. Use DD/MM/AAAA');
                continue;
            }
            break;
        }
    }

    kanbanAdjustCounters(fromStageId, newStageId, +1);
    kanbanSetCardStage(card, newStageId);
    kanbanUpdateStage(dragId, newStageId, note, '', hire_dt, () => {
        kanbanRestoreColumnOrder(fromBody, fromOrderBefore);
        kanbanRestoreColumnOrder(targetBody, toOrderBefore);
        kanbanSetCardStage(card, fromStageId);
        kanbanAdjustCounters(fromStageId, newStageId, -1);
        kanbanToast('error', 'Não foi possível mover o candidato.');
    }, '', '', { from_stage_id: fromStageId, from_order: fromOrder, to_order: toOrder }, () => {
        card.classList.remove('kanban-flash');
        void card.offsetWidth;
        card.classList.add('kanban-flash');
        kanbanToast('success', 'Movimentação salva.');
    }, []);
}

function kanbanInitDnD() {
    const cols = Array.from(document.querySelectorAll('.kanban-col[data-stage]'));
    cols.forEach(col => {
        col.addEventListener('dragenter', kanbanAllowDrop, true);
        col.addEventListener('dragover', kanbanAllowDrop, true);
        col.addEventListener('drop', function(e){
            if (kanbanHoverCol) { kanbanHoverCol.classList.remove('is-dragover'); kanbanHoverCol = null; }
            kanbanDrop(e);
        }, true);
    });

    document.addEventListener('dragstart', function(e){
        const card = e.target && e.target.closest ? e.target.closest('.kanban-card[data-id]') : null;
        if (!card) return;
        kanbanDragId = String(card.dataset.id || '');
        card.classList.add('kanban-dragging');
        document.documentElement.classList.add('kanban-is-dragging');
        try {
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', kanbanDragId);
            }
        } catch (err) {}
    }, true);

    document.addEventListener('dragend', function(e){
        const card = e.target && e.target.closest ? e.target.closest('.kanban-card[data-id]') : null;
        if (!card) return;
        card.classList.remove('kanban-dragging');
        document.documentElement.classList.remove('kanban-is-dragging');
        if (kanbanHoverCol) { kanbanHoverCol.classList.remove('is-dragover'); kanbanHoverCol = null; }
        kanbanDragId = null;
        try { if (e.dataTransfer) e.dataTransfer.clearData(); } catch (err) {}
    }, true);

    document.querySelectorAll('.kanban-card a, .kanban-card button').forEach(el => {
        try { el.setAttribute('draggable', 'false'); } catch (e) {}
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', kanbanInitDnD);
} else {
    kanbanInitDnD();
}

function kanbanGetCounterEl(stageKey) {
    return document.querySelector('[data-counter=\"' + String(stageKey) + '\"]');
}
function kanbanReadCount(stageKey) {
    const el = kanbanGetCounterEl(stageKey);
    return parseInt((el?.textContent || '0').trim(), 10) || 0;
}
function kanbanSetCount(stageKey, value) {
    const el = kanbanGetCounterEl(stageKey);
    if (el) el.textContent = String(Math.max(0, value));
}
function kanbanAdjustCounters(fromStageKey, toStageKey, direction) {
    // direction +1 significa que movemos do 'from' para 'to'
    // então from decrementa e to incrementa
    kanbanSetCount(fromStageKey, kanbanReadCount(fromStageKey) - direction);
    kanbanSetCount(toStageKey, kanbanReadCount(toStageKey) + direction);
}

function kanbanUpdateStage(id, stageId, note, interview_dt, hire_dt, onError, interview_type, interview_link, extra, onSuccess, attachmentsFiles) {
    const sid = parseInt(String(stageId || '0'), 10) || 0;
    const st = KANBAN_STAGE_BY_ID[String(sid)] || null;
    if (id == null || String(id).trim() === '' || sid <= 0 || !st) {
        kanbanToast('error', 'Dados inválidos para salvar movimentação.');
        if (typeof onError === 'function') onError();
        return;
    }
    const payload = Object.assign({
        id: id,
        stage_id: sid,
        stage: String(st.code || ''),
        note: note,
        interview_dt: interview_dt,
        hire_dt: hire_dt,
        interview_type: interview_type || '',
        interview_link: interview_link || ''
    }, (extra && typeof extra === 'object') ? extra : {});
    if (kanbanIsDebug()) {
        try { console.log('[kanban-move] payload', payload); } catch (e) {}
    }
    const files = Array.isArray(attachmentsFiles) ? attachmentsFiles : [];
    const attErr = document.getElementById('attError');
    if (attErr) attErr.textContent = '';
    if (files.length > 0) {
        const fd = new FormData();
        fd.append('payload', JSON.stringify(payload));
        files.forEach(f => fd.append('attachments[]', f, f.name));
        kanbanAttachmentUploadingState(true);
        const totalBytes = files.reduce((acc, f) => acc + Number(f.size || 0), 0);
        kanbanAttachmentProgress(0, totalBytes, false);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'rh-kanban.php?action=move', true);
        xhr.upload.onprogress = function(evt){
            if (evt.lengthComputable) {
                kanbanAttachmentProgress(evt.loaded, evt.total, false);
            } else {
                kanbanAttachmentProgress(Math.min(totalBytes, Math.round(totalBytes * 0.7)), totalBytes, false);
            }
        };
        xhr.onload = function(){
            let data = { ok: false, message: 'Resposta inválida do servidor' };
            try { data = JSON.parse(xhr.responseText || '{}'); } catch (e) {}
            if (!data.ok) {
                if (data && data.message) {
                    if (attErr) attErr.textContent = String(data.message);
                    kanbanToast('error', data.message);
                }
                kanbanAttachmentUploadingState(false);
                kanbanAttachmentProgress(0, 0, true);
                if (typeof onError === 'function') onError();
            } else {
                kanbanAttachmentProgress(totalBytes, totalBytes, false);
                if (Array.isArray(data.attachment_warnings) && data.attachment_warnings.length > 0) {
                    if (attErr) attErr.textContent = data.attachment_warnings.join(' | ');
                    kanbanToast('error', data.attachment_warnings.join(' | '));
                }
                kanbanCloseAttachmentModal();
                kanbanAttachmentUploadingState(false);
                kanbanAttachmentProgress(0, 0, true);
                if (typeof onSuccess === 'function') onSuccess(data);
            }
        };
        xhr.onerror = function(){
            if (attErr) attErr.textContent = 'Falha de comunicação com o servidor.';
            kanbanAttachmentUploadingState(false);
            kanbanAttachmentProgress(0, 0, true);
            if (typeof onError === 'function') onError();
        };
        xhr.send(fd);
        return;
    } else {
        kanbanAttachmentProgress(0, 0, true);
        kanbanAttachmentUploadingState(false);
        const fetchOptions = {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        };
        fetch('rh-kanban.php?action=move', fetchOptions).then(async resp => {
            const text = await resp.text();
            try { return JSON.parse(text); } catch (e) { return { ok: false, message: 'Resposta inválida do servidor', raw: text }; }
        })
          .then(data => {
              if (!data.ok) {
                  if (data && data.message) kanbanToast('error', data.message);
                  if (typeof onError === 'function') onError();
              } else {
                  if (Array.isArray(data.attachment_warnings) && data.attachment_warnings.length > 0) {
                      kanbanToast('error', data.attachment_warnings.join(' | '));
                  }
                  if (typeof onSuccess === 'function') onSuccess(data);
              }
          }).catch(() => {
              if (typeof onError === 'function') onError();
          });
    }
}

function kanbanPersistOrder(stageId, orderedIds, onError) {
    const sid = parseInt(String(stageId || '0'), 10) || 0;
    if (sid <= 0) {
        if (typeof onError === 'function') onError({ ok: false });
        return;
    }
    fetch('rh-kanban.php?action=reorder', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ stage_id: sid, ordered_ids: orderedIds })
    }).then(async r => {
        const text = await r.text();
        try { return JSON.parse(text); } catch (e) { return { ok: false, message: 'Resposta inválida do servidor', raw: text }; }
      }).then(j => {
        if (!j.ok) {
          if (j && j.message) kanbanToast('error', j.message);
          if (typeof onError === 'function') onError(j);
        }
      })
      .catch(() => {
          if (typeof onError === 'function') onError({ ok: false });
      });
}
</script>

<!-- Modal de Agendamento (exibido somente para destino ENTREVISTA) -->
<div id="scheduleModal" class="modal-overlay hidden" aria-hidden="true">
  <div class="modal-panel" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="schTitle">
    <div class="px-4 py-3 border-b flex items-center justify-between">
      <h3 id="schTitle" class="text-lg font-semibold text-rich-black">Agendar Entrevista</h3>
      <button class="text-paynes-gray hover:text-rich-black" onclick="modalClose('scheduleModal')" aria-label="Fechar">✕</button>
    </div>
    <div class="modal-body">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="form-label">Data</label>
          <input id="schDate" type="date" class="form-input">
        </div>
        <div>
          <label class="form-label">Hora</label>
          <input id="schTime" type="time" class="form-input">
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
        <div>
          <label class="form-label">Tipo</label>
          <select id="schType" class="form-select">
            <option value="presencial">Presencial</option>
            <option value="online">Online</option>
          </select>
        </div>
        <div>
          <label class="form-label">Link (apenas online)</label>
          <input id="schLink" type="url" class="form-input" placeholder="https://meet.example.com/..." disabled>
        </div>
      </div>
      <p class="text-xs text-paynes-gray mt-2">O link será exibido no histórico como hyperlink quando informado.</p>
    </div>
    <div class="modal-actions text-right">
      <button class="px-4 py-2 bg-paynes-gray text-white rounded hover:bg-prussian-blue" onclick="kanbanCancelScheduleMove()">Cancelar</button>
      <button id="schSave" class="btn-primary">Salvar</button>
    </div>
  </div>
</div>
<script>
  document.getElementById('schType').addEventListener('change', function(){
    const online = this.value === 'online';
    const link = document.getElementById('schLink');
    link.disabled = !online;
    if (!online) link.value='';
  });
  function brDateTime(d,t){
    if(!d||!t) return '';
    const [y,m,dd]=d.split('-'); const [hh,mm]=t.split(':');
    return dd+'/'+m+'/'+y+' '+hh+':'+mm;
  }
  function kanbanCancelScheduleMove(){
    window.__pendingMove = null;
    modalClose('scheduleModal');
  }
  document.getElementById('schSave').addEventListener('click', function(){
    const schBtn = document.getElementById('schSave');
    const pm = window.__pendingMove;
    if(!pm){ modalClose('scheduleModal'); return; }
    const d = document.getElementById('schDate').value;
    const t = document.getElementById('schTime').value;
    const type = document.getElementById('schType').value;
    const link = document.getElementById('schLink').value.trim();
    if(!d || !t){ alert('Selecione data e hora.'); return; }
    if(type==='online' && link && !/^https?:\/\/.+/i.test(link)){ alert('URL inválida.'); return; }
    kanbanSetButtonLoading(schBtn, true, 'Salvando...');
    const dt = brDateTime(d,t);
    const card = document.querySelector('[data-id="'+pm.id+'"]');
    if(!card){ kanbanCancelScheduleMove(); return; }
    const beforeEl = pm.beforeId ? pm.targetBody.querySelector('[data-id="'+pm.beforeId+'"]') : null;
    if (beforeEl) {
      pm.targetBody.insertBefore(card, beforeEl);
    } else {
      pm.targetBody.appendChild(card);
    }
    const fromOrder = kanbanSnapshotColumn(pm.fromBody);
    const toOrder = kanbanSnapshotColumn(pm.targetBody);
    modalClose('scheduleModal');
    kanbanAdjustCounters(pm.fromStageId, pm.newStageId, +1);
    kanbanSetCardStage(card, pm.newStageId);
    kanbanUpdateStage(pm.id, pm.newStageId, pm.note, dt, '', () => {
      kanbanRestoreColumnOrder(pm.fromBody, pm.fromOrderBefore);
      kanbanRestoreColumnOrder(pm.targetBody, pm.toOrderBefore);
      kanbanSetCardStage(card, pm.fromStageId);
      kanbanAdjustCounters(pm.fromStageId, pm.newStageId, -1);
      kanbanSetButtonLoading(schBtn, false);
      kanbanToast('error', 'Não foi possível agendar/mover.');
    }, type, link, { from_stage_id: pm.fromStageId, from_order: fromOrder, to_order: toOrder }, () => {
      card.classList.remove('kanban-flash');
      void card.offsetWidth;
      card.classList.add('kanban-flash');
      kanbanSetButtonLoading(schBtn, false);
      kanbanToast('success', 'Movimentação salva.');
    }, []);
    window.__pendingMove = null;
  });
</script>

<!-- Modal de Anexos (opcional por movimentação) -->
<div id="attachModal" class="modal-overlay hidden" aria-hidden="true">
  <div class="modal-panel" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="attTitle">
    <div class="px-4 py-3 border-b flex items-center justify-between">
      <h3 id="attTitle" class="text-lg font-semibold text-rich-black">Anexos da movimentação</h3>
      <button class="text-paynes-gray hover:text-rich-black" onclick="kanbanAbortAttachmentModal()" aria-label="Fechar">✕</button>
    </div>
    <div class="modal-body space-y-3">
      <div id="attDropzone" class="border-2 border-dashed border-silver-lake-blue rounded-lg p-4 text-center bg-eggshell/40 cursor-pointer">
        <p class="text-sm text-paynes-gray">Arraste arquivos aqui ou clique para selecionar</p>
        <p class="text-xs text-paynes-gray mt-1">Tipos: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, GIF | até 10MB por arquivo e 50MB por movimentação</p>
        <button type="button" class="mt-2 px-3 py-1.5 rounded bg-white border border-silver-lake-blue text-paynes-gray hover:bg-silver-lake-blue hover:text-white">Selecionar arquivos</button>
        <input id="attInput" type="file" multiple class="hidden" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif">
      </div>
      <div id="attTotalSize" class="text-xs text-paynes-gray">Total: 0 B / 50 MB</div>
      <div id="attList" class="space-y-2 max-h-56 overflow-y-auto"></div>
    </div>
    <div class="modal-actions text-right flex items-center justify-end gap-2">
      <button id="attCancelBtn" class="px-4 py-2 bg-paynes-gray text-white rounded hover:bg-prussian-blue" onclick="kanbanAbortAttachmentModal()">Cancelar</button>
      <button id="attSkipBtn" class="px-4 py-2 bg-silver-lake-blue text-white rounded hover:bg-paynes-gray">Sem anexos</button>
      <button id="attSendBtn" class="btn-primary">Enviar movimentação</button>
    </div>
    <div id="attProgressWrap" class="hidden px-4 pb-3">
      <div class="w-full bg-gray-200 rounded h-2 overflow-hidden">
        <div id="attProgressBar" class="h-2 bg-blue-600" style="width:0%"></div>
      </div>
      <div id="attProgressText" class="text-xs text-paynes-gray mt-1"></div>
      <div id="attError" class="text-xs text-red-700 mt-1"></div>
    </div>
  </div>
</div>
<script>
  (function(){
    const drop = document.getElementById('attDropzone');
    const input = document.getElementById('attInput');
    if (drop && input) {
      drop.addEventListener('click', () => input.click());
      drop.addEventListener('dragover', (e) => { e.preventDefault(); drop.classList.add('ring-2','ring-paynes-gray'); });
      drop.addEventListener('dragleave', () => drop.classList.remove('ring-2','ring-paynes-gray'));
      drop.addEventListener('drop', (e) => {
        e.preventDefault();
        drop.classList.remove('ring-2','ring-paynes-gray');
        kanbanAddAttachmentFiles(e.dataTransfer?.files || []);
      });
      input.addEventListener('change', (e) => {
        kanbanAddAttachmentFiles(e.target.files || []);
        input.value = '';
      });
    }
    document.getElementById('attSkipBtn').addEventListener('click', function(){
      const ctx = window.__kanbanAttachmentCtx;
      if (!ctx || typeof ctx.onSubmit !== 'function') { kanbanCloseAttachmentModal(); return; }
      const files = [];
      ctx.onSubmit(files);
      kanbanCloseAttachmentModal();
    });
    document.getElementById('attSendBtn').addEventListener('click', function(){
      const ctx = window.__kanbanAttachmentCtx;
      if (!ctx || typeof ctx.onSubmit !== 'function') { kanbanAbortAttachmentModal(); return; }
      const files = (window.__kanbanAttachmentFiles || []).slice();
      if (files.length === 0) {
        document.getElementById('attError').textContent = 'Nenhum arquivo selecionado. Use "Sem anexos" para continuar sem upload.';
        return;
      }
      ctx.onSubmit(files);
    });
    document.getElementById('attachModal').addEventListener('click', function(ev){
      if (ev.target && ev.target.id === 'attachModal') {
        const ctx = window.__kanbanAttachmentCtx;
        kanbanCloseAttachmentModal();
        if (ctx && typeof ctx.onCancel === 'function') ctx.onCancel();
      }
    });
  })();
</script>

<script>
function kanbanShowHistory(id) {
  fetch('rh-kanban.php?action=history&id=' + encodeURIComponent(id))
    .then(r => r.json())
    .then(j => {
      window.__histCandidateId = id;
      const modal = document.getElementById('histModal');
      const panel = document.getElementById('histModalPanel');
      const body = document.getElementById('histModalBody');
      if (!j.ok) {
        body.innerHTML = '<div class="text-sm text-red-700"> ' + esc(j.message || 'Erro ao obter histórico') + '</div>';
        openHistModal(modal, panel);
        return;
      }
      if (!Array.isArray(j.data) || j.data.length === 0) {
        body.innerHTML = '<div class="text-sm text-paynes-gray">Sem histórico.</div>';
        openHistModal(modal, panel);
        return;
      }
      const rows = j.data.map(it => {
        const dt = it.created_at ? new Date(it.created_at.replace(' ', 'T')) : null;
        const dts = dt ? dt.toLocaleString() : '';
        const ent = it.interview_at ? ('<span class="ml-2 text-xs px-2 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-200">Entrevista: ' + esc(it.interview_at) + '</span>') : '';
        const enl = it.interview_link ? ('<a class="ml-2 text-xs text-blue-700 underline" target="_blank" href="'+escAttr(it.interview_link)+'">Link</a>') : '';
        const att = Array.isArray(it.attachments) ? it.attachments : [];
        const attHtml = att.length ? (
          '<div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">' +
          att.map(a => {
            const isImg = parseInt(String(a.is_image || '0'), 10) === 1;
            const thumb = isImg && a.thumb_path ? ('<img src="' + escAttr(a.thumb_path) + '" class="h-12 w-12 rounded object-cover border" alt="thumb">') : '<div class="h-12 w-12 rounded border bg-gray-50 flex items-center justify-center text-[10px] text-paynes-gray">ARQ</div>';
            return '<a href="' + escAttr(a.file_path || '#') + '" target="_blank" class="flex items-center gap-2 border rounded p-2 hover:bg-gray-50">' + thumb +
              '<div class="min-w-0"><div class="text-xs font-medium truncate">' + esc(a.original_name || '') + '</div><div class="text-[11px] text-paynes-gray">' + esc(a.uploaded_at || '') + '</div></div></a>';
          }).join('') +
          '</div>'
        ) : '';
        return (
          '<div class="py-2 border-b last:border-b-0">' +
            '<div class="flex items-center justify-between">' +
              '<div class="text-[11px] text-paynes-gray">' + esc(dts) + '</div>' +
              '<div class="text-[11px] text-paynes-gray">' + esc(it.from_stage || '-') + ' → ' + esc(it.to_stage) + '</div>' +
            '</div>' +
            '<div class="mt-1 text-sm text-rich-black">' + esc(it.note || '') + ent + enl + '</div>' + attHtml +
          '</div>'
        );
      }).join('');
      body.innerHTML = '<div class="divide-y divide-gray-200">' + rows + '</div>';
      openHistModal(modal, panel);
    })
    .catch(() => {
      const modal = document.getElementById('histModal');
      const panel = document.getElementById('histModalPanel');
      const body = document.getElementById('histModalBody');
      body.innerHTML = '<div class="text-sm text-red-700">Erro de comunicação ao buscar histórico</div>';
      openHistModal(modal, panel);
    });
}

function kanbanViewCV(id) {
  const url = 'rh-kanban.php?action=view&id=' + encodeURIComponent(id) + (kanbanIsDebug() ? '&kanban_debug=1' : '');
  fetch(url)
    .then(async r => {
      const text = await r.text();
      try {
        return JSON.parse(text);
      } catch (e) {
        throw new Error('Resposta inválida do servidor');
      }
    })
    .then(j => {
      if (!j || !j.ok) {
        kanbanToast('error', (j && j.message) ? j.message : 'Erro ao carregar currículo');
        return;
      }
      const d = j.data || {};
      const body = document.getElementById('cvModalBody');
      const lines = [];
      const currentStageId = parseInt(String(d.stage_id || '0'), 10) || 0;
      const currentStageName = String(d.stage_name || d.stage_code || d.stage || '');
      lines.push('<div class="space-y-3">');
      const orderedStages = KANBAN_STAGES
        .filter(s => s && s.id != null)
        .slice()
        .sort((a,b) => {
          const pa = Number((a.position ?? a.sort_order ?? 999999));
          const pb = Number((b.position ?? b.sort_order ?? 999999));
          if (pa !== pb) return pa - pb;
          return Number(a.id || 0) - Number(b.id || 0);
        });
      lines.push('<div class="p-3 rounded border border-silver-lake-blue bg-eggshell">' +
        '<div class="text-xs text-paynes-gray">Mover etapa</div>' +
        '<div class="grid grid-cols-1 md:grid-cols-3 gap-2 items-end mt-2">' +
          '<div class="md:col-span-1">' +
            '<label class="form-label">Etapa atual</label>' +
            '<input class="form-input" value="' + esc(currentStageName) + '" disabled>' +
          '</div>' +
          '<div class="md:col-span-1">' +
            '<label class="form-label">Nova etapa</label>' +
            '<select id="cvStageSelect" class="form-select">' +
              '<option value="">Selecione...</option>' +
              orderedStages.map(s => {
                const dis = (parseInt(String(s.id), 10) === currentStageId) ? ' disabled' : '';
                return '<option value="' + escAttr(s.id) + '"' + dis + '>' + esc(s.name || s.code || String(s.id)) + '</option>';
              }).join('') +
            '</select>' +
          '</div>' +
          '<div class="md:col-span-1">' +
            '<label class="form-label">Data contratação (se Contratado)</label>' +
            '<input id="cvHireDate" type="date" class="form-input">' +
          '</div>' +
        '</div>' +
        '<div class="mt-2">' +
          '<label class="form-label">Observação (obrigatória)</label>' +
          '<textarea id="cvMoveNote" class="form-textarea" rows="2" placeholder="Informe a observação..."></textarea>' +
        '</div>' +
        '<div class="mt-2 flex items-center justify-end gap-2">' +
          '<button id="cvMoveBtn" class="btn-primary" disabled>Mover</button>' +
        '</div>' +
      '</div>');
      lines.push('<div><span class="font-semibold">Nome:</span> ' + esc(d.name) + '</div>');
      if (d.vacancy) lines.push('<div><span class="font-semibold">Vaga:</span> ' + esc(d.vacancy) + '</div>');
      if (d.email) lines.push('<div><span class="font-semibold">Email:</span> ' + esc(d.email) + '</div>');
      if (d.phone) lines.push('<div><span class="font-semibold">Telefone:</span> ' + esc(d.phone) + '</div>');
      if (d.cpf) lines.push('<div><span class="font-semibold">CPF:</span> ' + esc(d.cpf) + '</div>');
      if (d.desired_role) lines.push('<div><span class="font-semibold">Cargo pretendido:</span> ' + esc(d.desired_role) + '</div>');
      if (d.linkedin) lines.push('<div><span class="font-semibold">LinkedIn:</span> <a href="' + escAttr(d.linkedin) + '" target="_blank" class="text-blue-700 underline">' + esc(d.linkedin) + '</a></div>');
      if (d.portfolio) lines.push('<div><span class="font-semibold">Portfolio:</span> <a href="' + escAttr(d.portfolio) + '" target="_blank" class="text-blue-700 underline">' + esc(d.portfolio) + '</a></div>');
      if (d.skills) lines.push('<div><span class="font-semibold">Competências:</span> ' + esc(d.skills) + '</div>');
      if (Array.isArray(d.experiences)) {
        lines.push('<div><span class="font-semibold">Experiências:</span><ul class="list-disc ml-5">' + d.experiences.map(esc).map(x => '<li>' + x + '</li>').join('') + '</ul></div>');
      }
      if (Array.isArray(d.education)) {
        lines.push('<div><span class="font-semibold">Formações:</span><ul class="list-disc ml-5">' + d.education.map(esc).map(x => '<li>' + x + '</li>').join('') + '</ul></div>');
      }
      if (d.resume_url) lines.push('<div><a class="px-3 py-1 bg-silver-lake-blue text-white rounded" target="_blank" href="rh-cv.php?id=' + escAttr(d.id) + '">Abrir currículo</a></div>');
      lines.push(
        '<div class="mt-3 p-3 rounded border border-silver-lake-blue bg-eggshell">' +
          '<div class="flex items-center justify-between gap-2 flex-wrap">' +
            '<div class="text-xs text-paynes-gray">Upload de Arquivo</div>' +
            '<label class="px-3 py-1.5 rounded bg-white border border-silver-lake-blue text-paynes-gray hover:bg-silver-lake-blue hover:text-white cursor-pointer">' +
              'Selecionar arquivo' +
              '<input id="cvFileInput" type="file" class="hidden" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">' +
            '</label>' +
          '</div>' +
          '<div class="mt-2 flex items-center justify-between gap-2 flex-wrap">' +
            '<div class="text-xs text-paynes-gray">Tipos: PDF, DOC, DOCX, JPG, PNG | Limite: 10MB</div>' +
            '<button id="cvUploadBtn" type="button" class="btn-primary">Upload de Arquivo</button>' +
          '</div>' +
          '<div id="cvUploadError" class="text-xs text-red-700 mt-2"></div>' +
            '<div id="cvFilesList" class="mt-3">' + kanbanRenderCandidateFiles(d.files || []) + '</div>' +
        '</div>'
      );
      lines.push('</div>');
      body.innerHTML = lines.join('');
      modalOpen('cvModal');

      const sel = document.getElementById('cvStageSelect');
      const noteEl = document.getElementById('cvMoveNote');
      const hireEl = document.getElementById('cvHireDate');
      const btn = document.getElementById('cvMoveBtn');
      const uploadBtn = document.getElementById('cvUploadBtn');
      if (uploadBtn) uploadBtn.addEventListener('click', function(){ kanbanUploadCandidateFile(id); });

      const sync = () => {
        const nsId = parseInt(String(sel.value || '0'), 10) || 0;
        const st = KANBAN_STAGE_BY_ID[String(nsId)] || null;
        const note = String(noteEl.value || '').trim();
        const needsHire = !!st && st.code === 'CONTRATADO';
        hireEl.disabled = !needsHire;
        if (!needsHire) hireEl.value = '';
        btn.disabled = !(nsId > 0 && nsId !== currentStageId && note.length > 0);
      };
      sel.addEventListener('change', sync);
      noteEl.addEventListener('input', sync);
      sync();

      btn.addEventListener('click', function(){
        const nsId = parseInt(String(sel.value || '0'), 10) || 0;
        const st = KANBAN_STAGE_BY_ID[String(nsId)] || null;
        const note = String(noteEl.value || '').trim();
        if (!st || nsId === currentStageId) { kanbanToast('error', 'Selecione uma nova etapa.'); return; }
        if (!note) { kanbanToast('error', 'Informe a observação.'); return; }

        const card = document.querySelector('[data-id="' + String(id) + '"]');
        if (!card) { kanbanToast('error', 'Card não encontrado no kanban atual.'); return; }
        const fromCol = card.closest('.kanban-col');
        const fromStageId = parseInt(String(fromCol?.dataset?.stageId || '0'), 10) || 0;
        const fromBody = fromCol ? fromCol.querySelector('.kanban-col-body') : card.parentElement;
        const toCol = document.querySelector('.kanban-col[data-stage-id="' + String(nsId) + '"]');
        const toBody = toCol ? toCol.querySelector('.kanban-col-body') : null;
        if (!fromStageId || !toBody) { kanbanToast('error', 'Não foi possível localizar a coluna de destino.'); return; }

        const fromOrderBefore = kanbanSnapshotColumn(fromBody);
        const toOrderBefore = kanbanSnapshotColumn(toBody);

        kanbanSetButtonLoading(btn, true, 'Movendo...');
        sel.disabled = true;
        noteEl.disabled = true;
        hireEl.disabled = true;

        const requiresSchedule = (st.code === 'ENTREVISTA' || st.code === 'ENT_AGENDADA' || st.code === 'ENT_CONFIRMADA');
        if (requiresSchedule) {
          window.__pendingMove = {
            id: String(id),
            fromStageId,
            newStageId: nsId,
            note,
            fromBody,
            targetBody: toBody,
            fromOrderBefore,
            toOrderBefore,
            beforeId: null
          };
          sel.disabled = false; noteEl.disabled = false; kanbanSetButtonLoading(btn, false); btn.disabled = false;
          modalClose('cvModal');
          modalOpen('scheduleModal');
          return;
        }

        let hire_dt = '';
        if (st.code === 'CONTRATADO') {
          const dstr = String(hireEl.value || '').trim();
          if (!dstr) { kanbanToast('error', 'Informe a data de contratação.'); sel.disabled = false; noteEl.disabled = false; kanbanSetButtonLoading(btn, false); return; }
          const parts = dstr.split('-');
          if (parts.length === 3) { hire_dt = parts[2] + '/' + parts[1] + '/' + parts[0]; }
        }

        toBody.appendChild(card);
        const fromOrder = kanbanSnapshotColumn(fromBody);
        const toOrder = kanbanSnapshotColumn(toBody);
        kanbanAdjustCounters(fromStageId, nsId, +1);
        kanbanSetCardStage(card, nsId);
        kanbanUpdateStage(String(id), nsId, note, '', hire_dt, () => {
          kanbanRestoreColumnOrder(fromBody, fromOrderBefore);
          kanbanRestoreColumnOrder(toBody, toOrderBefore);
          kanbanAdjustCounters(fromStageId, nsId, -1);
          kanbanSetCardStage(card, fromStageId);
          sel.disabled = false; noteEl.disabled = false; kanbanSetButtonLoading(btn, false);
        }, '', '', { from_stage_id: fromStageId, from_order: fromOrder, to_order: toOrder }, () => {
          card.classList.remove('kanban-flash');
          void card.offsetWidth;
          card.classList.add('kanban-flash');
          kanbanToast('success', 'Movimentação salva.');
          kanbanSetButtonLoading(btn, false);
          modalClose('cvModal');
        }, []);
      });
    })
    .catch(err => {
      kanbanToast('error', 'Erro de comunicação ao carregar currículo');
    });
}
function esc(s){ return (s==null?'':String(s)).replace(/[&<>]/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[m])); }
function escAttr(s){ return (s==null?'':String(s)).replace(/"/g,'&quot;'); }
function closeCvModal(){ modalClose('cvModal'); }
</script>

<!-- Modal de Visualização -->
<div id="cvModal" class="modal-overlay hidden" aria-hidden="true">
  <div class="modal-panel" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="cvTitle">
    <div class="px-4 py-3 border-b flex items-center justify-between">
      <h3 class="text-lg font-semibold text-rich-black">Dados do Currículo</h3>
      <button class="text-paynes-gray hover:text-rich-black" onclick="closeCvModal()" aria-label="Fechar">✕</button>
    </div>
    <div id="cvModalBody" class="modal-body text-sm text-rich-black space-y-2"></div>
    <div class="modal-actions text-right">
      <button class="px-4 py-2 bg-paynes-gray text-white rounded hover:bg-prussian-blue" onclick="closeCvModal()">Fechar</button>
    </div>
  </div>
</div>

<!-- Modal de Histórico -->
<div id="histModal" class="modal-overlay hidden" aria-hidden="true">
  <div id="histModalPanel" class="modal-panel" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="histTitle">
    <div class="px-4 py-3 border-b flex items-center justify-between">
      <h3 class="text-lg font-semibold text-rich-black">Histórico de movimentações</h3>
      <button class="text-paynes-gray hover:text-rich-black" onclick="closeHistModal()" aria-label="Fechar">✕</button>
    </div>
    <div id="histModalBody" class="modal-body"></div>
    <div class="px-4 pt-2">
      <div class="space-y-2">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-2 items-end">
          <div class="md:col-span-2">
            <input id="histNoteInput" class="form-input" placeholder="Inserir observação">
          </div>
          <div class="text-right">
            <button id="histNoteBtn" class="btn-primary">Adicionar observação</button>
          </div>
        </div>
        <div id="histInterviewBlock" class="space-y-2" style="display:none">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-2 items-end">
            <div>
              <label class="form-label">Mover para Entrevista</label>
              <input id="histEnsureStage" type="checkbox" class="form-checkbox">
            </div>
            <div>
              <label class="form-label">Data</label>
              <input id="histDate" type="date" class="form-input">
            </div>
            <div>
              <label class="form-label">Hora</label>
              <input id="histTime" type="time" class="form-input">
            </div>
            <div>
              <label class="form-label">Tipo</label>
              <select id="histType" class="form-select">
                <option value="presencial">Presencial</option>
                <option value="online">Online</option>
              </select>
            </div>
          </div>
          <div>
            <label class="form-label">Link (apenas online)</label>
            <input id="histLink" type="url" class="form-input" placeholder="https://meet.example.com/...">
          </div>
          <p class="text-xs text-paynes-gray">Ao marcar “Mover para Entrevista”, a observação e a mudança de estágio serão salvas juntas.</p>
        </div>
        <p class="text-xs text-paynes-gray">Ao marcar “Mover para Entrevista”, a observação e a mudança de estágio serão salvas juntas.</p>
      </div>
    </div>
    <div class="modal-actions text-right"><button class="px-4 py-2 bg-paynes-gray text-white rounded hover:bg-prussian-blue" onclick="closeHistModal()">Fechar</button></div>
  </div>
  <style>
    @media (max-width: 640px){ #histModalPanel{ width: 95vw; } }
  </style>
</div>
<script>
function openHistModal(modal, panel){
  modalOpen('histModal');
}
function closeHistModal(){
  modalClose('histModal');
}
// Exibir bloco de entrevista somente quando marcado
document.addEventListener('change', function(ev){
  if(ev.target && ev.target.id==='histEnsureStage'){
    const block = document.getElementById('histInterviewBlock');
    const checked = ev.target.checked;
    block.style.display = checked ? 'block' : 'none';
  }
});
// Habilitar link apenas para online
document.addEventListener('change', function(ev){
  if(ev.target && ev.target.id==='histType'){
    const link = document.getElementById('histLink');
    link.disabled = (ev.target.value!=='online');
    if (link.disabled) link.value='';
  }
});
document.getElementById('histNoteBtn').addEventListener('click', function(){
  const id = window.__histCandidateId;
  const note = (document.getElementById('histNoteInput').value || '').trim();
  const ensure = !!document.getElementById('histEnsureStage').checked;
  const d = document.getElementById('histDate').value;
  const t = document.getElementById('histTime').value;
  const type = document.getElementById('histType').value;
  const link = document.getElementById('histLink').value.trim();
  if (!id) { alert('ID do candidato ausente. Abra novamente o histórico.'); return; }
  if (note.length === 0) { alert('Informe a observação.'); return; }
  if (ensure && (!d || !t)) { alert('Selecione data e hora para entrevistas.'); return; }
  if (ensure && type==='online' && link && !/^https?:\/\/.+/i.test(link)) { alert('URL inválida.'); return; }
  const dt = (!ensure)? '' : (function(){ const [y,m,dd]=d.split('-'); const [hh,mm]=t.split(':'); return dd+'/'+m+'/'+y+' '+hh+':'+mm; })();
  fetch('rh-kanban.php?action=add_note', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ id, note, ensure_stage: ensure, interview_dt: dt, interview_type: type, interview_link: link })
  }).then(r=>r.json()).then(j=>{
    if(j.ok){
      // manter modal aberto; limpar observação e recarregar histórico
      document.getElementById('histNoteInput').value='';
      kanbanShowHistory(id);
      // se estágio mudado para ENTREVISTA, refletir no badge do card (opcionalmente via reload)
    } else {
      alert(j.message||'Falha ao salvar observação/estágio');
    }
  }).catch(()=>alert('Erro de comunicação'));
});
</script>
<script>
  (function(){
    let polling = null;
    async function pollNotifications(){
      try {
        const r = await fetch('rh-kanban.php?action=notifications');
        const j = await r.json();
        if (!j || !j.ok || !Array.isArray(j.data)) return;
        const arr = j.data.slice().reverse();
        arr.forEach(n => {
          if (n && n.message) kanbanToast('success', n.message);
        });
      } catch (e) {}
    }
    if (document.visibilityState !== 'hidden') {
      pollNotifications();
    }
    polling = setInterval(() => {
      if (document.visibilityState === 'hidden') return;
      pollNotifications();
    }, 10000);
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') pollNotifications();
    });
    window.addEventListener('beforeunload', () => {
      if (polling) clearInterval(polling);
    });
  })();
</script>
