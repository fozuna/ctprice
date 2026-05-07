<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/AuditLog.php';
require_once __DIR__ . '/models/ManualHire.php';
require_once __DIR__ . '/models/ManualTermination.php';
require_once __DIR__ . '/models/WorkforceCount.php';
require_once __DIR__ . '/models/CostCenter.php';
require_once __DIR__ . '/models/CostCenterPermission.php';

header('Content-Type: application/json; charset=utf-8');
function ok($data=[], $message='ok'){ echo json_encode(['ok'=>true,'message'=>$message,'data'=>$data], JSON_UNESCAPED_UNICODE); exit; }
function fail($message){ http_response_code(200); echo json_encode(['ok'=>false,'message'=>$message], JSON_UNESCAPED_UNICODE); exit; }

$db = Database::getInstance()->getConnection();
$auth = new Auth($db);
$audit = new AuditLog($db);
if (!$auth->isLoggedIn() || !in_array($_SESSION['user_role'] ?? null, [ROLE_ADMIN, ROLE_COORD_RH], true)) { fail('Sem permissão'); }
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function ensureManualTables(PDO $db){
  try { $db->exec("CREATE TABLE IF NOT EXISTS cost_centers (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, code VARCHAR(50) NULL, parent_id INT NULL, department VARCHAR(120) NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP)"); } catch (Throwable $e) {}
  try { $db->exec("CREATE TABLE IF NOT EXISTS cost_center_permissions (id INT AUTO_INCREMENT PRIMARY KEY, cost_center_id INT NOT NULL, role_id INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)"); } catch (Throwable $e) {}
  try { $db->exec("CREATE TABLE IF NOT EXISTS manual_hires (id INT AUTO_INCREMENT PRIMARY KEY, employee_name VARCHAR(180) NOT NULL, admission_date DATE NOT NULL, cost_center_id INT NOT NULL, role_title VARCHAR(120) NOT NULL, contract_type ENUM('CLT','PJ','INTEGRAL','MEIO_PERIODO') NOT NULL, source VARCHAR(60) NULL, notes TEXT NULL, created_by INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP)"); } catch (Throwable $e) {}
  try { $db->exec("CREATE TABLE IF NOT EXISTS manual_terminations (id INT AUTO_INCREMENT PRIMARY KEY, employee_name VARCHAR(180) NOT NULL, termination_date DATE NOT NULL, cost_center_id INT NOT NULL, reason VARCHAR(180) NOT NULL, recorded_by INT NULL, notes TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP)"); } catch (Throwable $e) {}
  try { $db->exec("CREATE TABLE IF NOT EXISTS workforce_counts (id INT AUTO_INCREMENT PRIMARY KEY, recorded_at DATE NOT NULL, total_active INT NOT NULL, department VARCHAR(120) NULL, recorded_by INT NULL, reason VARCHAR(180) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP)"); } catch (Throwable $e) {}
}
ensureManualTables($db);

try {
  switch ($action) {
    case 'list_hires':
      $m = new ManualHire($db);
      ok(['rows'=>$m->findAll([], 'admission_date DESC', 200)]);
    case 'create_hire':
      $payload = json_decode(file_get_contents('php://input'), true);
      $data = [
        'employee_name' => trim($payload['employee_name'] ?? ''),
        'admission_date' => $payload['admission_date'] ?? '',
        'cost_center_id' => (int)($payload['cost_center_id'] ?? 0),
        'role_title' => trim($payload['role_title'] ?? ''),
        'contract_type' => $payload['contract_type'] ?? '',
        'source' => $payload['source'] ?? null,
        'notes' => $payload['notes'] ?? null,
        'created_by' => $_SESSION['user_id'] ?? null
      ];
      foreach (['employee_name','admission_date','cost_center_id','role_title','contract_type'] as $f){ if(empty($data[$f])) fail('Campos obrigatórios ausentes'); }
      $m = new ManualHire($db); $id = (int)$m->create($data); $audit->logInsert('manual_hires', $id, $data, $_SESSION['user_id'] ?? null); ok(['id'=>$id], 'Contratação registrada');
    case 'list_terms':
      $m = new ManualTermination($db);
      ok(['rows'=>$m->findAll([], 'termination_date DESC', 200)]);
    case 'create_term':
      $payload = json_decode(file_get_contents('php://input'), true);
      $data = [
        'employee_name' => trim($payload['employee_name'] ?? ''),
        'termination_date' => $payload['termination_date'] ?? '',
        'cost_center_id' => (int)($payload['cost_center_id'] ?? 0),
        'reason' => trim($payload['reason'] ?? ''),
        'recorded_by' => $_SESSION['user_id'] ?? null,
        'notes' => $payload['notes'] ?? null
      ];
      foreach (['employee_name','termination_date','cost_center_id','reason'] as $f){ if(empty($data[$f])) fail('Campos obrigatórios ausentes'); }
      $m = new ManualTermination($db); $id = (int)$m->create($data); $audit->logInsert('manual_terminations', $id, $data, $_SESSION['user_id'] ?? null); ok(['id'=>$id], 'Demissão registrada');
    case 'list_counts':
      $m = new WorkforceCount($db); ok(['rows'=>$m->findAll([], 'recorded_at DESC', 200)]);
    case 'create_count':
      $payload = json_decode(file_get_contents('php://input'), true);
      $data = [
        'recorded_at' => $payload['recorded_at'] ?? date('Y-m-d'),
        'total_active' => (int)($payload['total_active'] ?? 0),
        'department' => $payload['department'] ?? null,
        'recorded_by' => $_SESSION['user_id'] ?? null,
        'reason' => $payload['reason'] ?? null
      ];
      if ($data['total_active'] <= 0) fail('Total ativo deve ser maior que zero');
      $m = new WorkforceCount($db); $id = (int)$m->create($data); $audit->logInsert('workforce_counts', $id, $data, $_SESSION['user_id'] ?? null); ok(['id'=>$id], 'Contagem registrada');
    case 'list_cc':
      $m = new CostCenter($db); ok(['rows'=>$m->findAll([], 'name ASC', 500)]);
    case 'create_cc':
      $payload = json_decode(file_get_contents('php://input'), true);
      $data = [
        'name' => trim($payload['name'] ?? ''),
        'code' => trim($payload['code'] ?? ''),
        'parent_id' => $payload['parent_id'] ? (int)$payload['parent_id'] : null,
        'department' => trim($payload['department'] ?? ''),
        'active' => isset($payload['active']) ? (int)$payload['active'] : 1
      ];
      if ($data['name'] === '') fail('Nome é obrigatório');
      $m = new CostCenter($db); $id = (int)$m->create($data); $audit->logInsert('cost_centers', $id, $data, $_SESSION['user_id'] ?? null); ok(['id'=>$id], 'Centro de custo criado');
    case 'export_csv':
      $type = $_GET['type'] ?? 'hires';
      $sets = ['hires'=>'manual_hires','terms'=>'manual_terminations','counts'=>'workforce_counts','cc'=>'cost_centers'];
      if (!isset($sets[$type])) fail('Tipo inválido');
      $table = $sets[$type];
      $rows = $db->query("SELECT * FROM $table ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename="'.$table.'_export_'.date('Ymd').'.csv"');
      $out = fopen('php://output', 'w');
      if (!empty($rows)) { fputcsv($out, array_keys($rows[0])); foreach ($rows as $r) { fputcsv($out, $r); } }
      fclose($out); exit;
    default:
      fail('Ação inválida');
  }
} catch (Throwable $e) { fail('Erro: '.$e->getMessage()); }
