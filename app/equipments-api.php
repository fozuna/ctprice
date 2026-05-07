<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/EquipmentService.php';
require_once __DIR__ . '/classes/AuditLog.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = Database::getInstance()->getConnection();
    $auth = new Auth($db);
    if (!$auth->isLoggedIn() || !in_array($_SESSION['user_role'] ?? null, [ROLE_ADMIN, ROLE_SUPERVISOR])) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Forbidden']);
        exit;
    }
    $service = new EquipmentService($db);
    $audit = new AuditLog($db);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? '';
    if ($method === 'POST') {
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        switch ($action) {
            case 'search':
                $q = trim($payload['q'] ?? ($_GET['q'] ?? ''));
                if ($q === '') {
                    echo json_encode(['ok' => true, 'data' => []]);
                    break;
                }
                $stmt = $db->prepare('SELECT id, tag, description FROM equipments WHERE tag LIKE :q ORDER BY tag LIMIT 10');
                $stmt->execute([':q' => '%' . $q . '%']);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['ok' => true, 'data' => $rows]);
                break;
            case 'search_fronts':
                $q = trim($payload['q'] ?? ($_GET['q'] ?? ''));
                if ($q === '') {
                    echo json_encode(['ok' => true, 'data' => []]);
                    break;
                }
                $qLower = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
                $stmt = $db->prepare('SELECT id, name, code FROM fronts WHERE LOWER(name) LIKE :q OR LOWER(code) LIKE :q ORDER BY name LIMIT 10');
                $stmt->execute([':q' => '%' . $qLower . '%']);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['ok' => true, 'data' => $rows]);
                break;
            case 'reset_hours':
                $ids = $payload['ids'] ?? [];
                if (!is_array($ids) || empty($ids)) {
                    throw new Exception('Lista de equipamentos vazia');
                }
                $ids = array_values(array_unique(array_map('intval', $ids)));
                $now = date('Y-m-d H:i:s');
                $note = 'Reset via relatório';
                // Verificar existência da tabela de logs (produção pode não ter migration ainda)
                $hasLogTable = false;
                try {
                    $chk = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'equipment_hour_logs'");
                    $chk->execute();
                    $hasLogTable = ((int)$chk->fetchColumn()) > 0;
                } catch (Throwable $e) {
                    $hasLogTable = false;
                }
                $db->beginTransaction();
                try {
                    $ins = $hasLogTable
                        ? $db->prepare('INSERT INTO equipment_hour_logs (equipment_id, log_timestamp, hour_meter, source, note, created_by) VALUES (:e, :t, 0, :s, :n, :u)')
                        : null;
                    $upd = $db->prepare('UPDATE equipments SET current_hours = 0, initial_hours = 0, updated_at = NOW() WHERE id = :e');
                    $count = 0;
                    foreach ($ids as $eid) {
                        if ($ins) {
                            $ins->execute([':e' => $eid, ':t' => $now, ':s' => 'RESET', ':n' => $note, ':u' => $_SESSION['user_id']]);
                        }
                        $upd->execute([':e' => $eid]);
                        $count++;
                    }
                    $db->commit();
                    if (defined('DEBUG_MODE') && DEBUG_MODE && function_exists('error_log')) {
                        error_log('[reset_hours] updated=' . $count . ' hasLogTable=' . ($ins ? 'yes' : 'no'));
                    }
                    echo json_encode(['ok' => true, 'updated' => $count, 'ids' => $ids, 'log_saved' => $hasLogTable]);
                } catch (Throwable $ex) {
                    $db->rollBack();
                    throw $ex;
                }
                break;
            case 'cleanup_hours':
                $note = $payload['note'] ?? null;
                $res = $service->backupAndPurgeHours($_SESSION['user_id'], $note);
                $audit->logInsert('equipment_hour_cleanup_logs', 0, $res, $_SESSION['user_id']);
                echo json_encode(['ok' => true, 'data' => $res]);
                break;
            case 'set_hour_meter':
                $equipmentId = (int)($payload['equipment_id'] ?? 0);
                $value = (float)($payload['hour_meter'] ?? -1);
                if ($equipmentId <= 0) { throw new Exception('Equipamento inválido'); }
                $service->setInitialHourMeter($equipmentId, $value, $_SESSION['user_id']);
                echo json_encode(['ok' => true]);
                break;
            case 'set_production_rate':
                $equipmentId = (int)($payload['equipment_id'] ?? 0);
                $unit = trim($payload['unit'] ?? '');
                $rate = (float)($payload['rate'] ?? -1);
                if ($equipmentId <= 0 || $unit === '') { throw new Exception('Dados inválidos'); }
                if (!defined('DEBUG_MODE') || !DEBUG_MODE) { $unit = 'ton/h'; }
                $service->setProductionRate($equipmentId, $unit, $rate, $_SESSION['user_id']);
                echo json_encode(['ok' => true]);
                break;
            case 'allocate':
                $equipmentId = (int)($payload['equipment_id'] ?? 0);
                $frontId = (int)($payload['front_id'] ?? 0);
                $frontText = trim($payload['front_text'] ?? '');
                $start = $payload['start'] ?? '';
                $end = $payload['end'] ?? '';
                $priority = (int)($payload['priority'] ?? 0);
                $dailyHours = (float)($payload['daily_hours'] ?? 0);
                // Resolver id da frente a partir do texto quando usuário não clicou na sugestão
                if ($frontId <= 0 && $frontText !== '') {
                    $stmt = $db->prepare('SELECT id FROM fronts WHERE code = :t OR name = :t LIMIT 1');
                    $stmt->execute([':t' => $frontText]);
                    $resolved = $stmt->fetchColumn();
                    if ($resolved) { $frontId = (int)$resolved; }
                }
                if ($equipmentId <= 0 || $frontId <= 0 || !$start || !$end) { throw new Exception('Dados inválidos'); }
                $r = $service->allocate($equipmentId, $frontId, $start, $end, $priority, $_SESSION['user_id'], $dailyHours);
                echo json_encode(['ok' => true, 'data' => $r]);
                break;
            default:
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'Ação inválida']);
        }
        exit;
    }
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método não suportado']);
} catch (Exception $e) {
    // Retornar 200 com erro estruturado para não quebrar UX de autocomplete
    http_response_code(200);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
