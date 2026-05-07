<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/ProcurementService.php';
require_once __DIR__ . '/models/Supplier.php';
require_once __DIR__ . '/models/Product.php';
require_once __DIR__ . '/models/Rfq.php';
require_once __DIR__ . '/models/RfqItem.php';
require_once __DIR__ . '/models/RfqSupplier.php';

header('Content-Type: application/json; charset=utf-8');

function ok($data = [], $message = 'ok') { echo json_encode(['ok' => true, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE); exit; }
function fail($message, $code = 200, $data = []) { http_response_code(200); echo json_encode(['ok' => false, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE); exit; }

$db = Database::getInstance()->getConnection();
$auth = new Auth($db);
$svc = new ProcurementService($db);

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$isSupplierPortal = isset($_SESSION['supplier_id']);

function requireInternal($auth) {
    if (!$auth->isLoggedIn() || !$auth->hasPermission(ROLE_COMPRAS)) {
        fail('Acesso negado');
    }
}

function requireSupplier() {
    if (!isset($_SESSION['supplier_id'])) {
        fail('Acesso negado (fornecedor)');
    }
}

try {
    switch ($action) {
        case 'suppliers_list':
            requireInternal($auth);
            $sup = new Supplier($GLOBALS['db']);
            $rows = $sup->findAll([], 'created_at DESC');
            ok(['suppliers' => $rows]);
        case 'suppliers_save':
            requireInternal($auth);
            $payload = json_decode(file_get_contents('php://input'), true);
            $sup = new Supplier($GLOBALS['db']);
            $data = [
                'razao_social' => $payload['razao_social'],
                'cnpj' => $payload['cnpj'],
                'email' => $payload['email'],
                'telefone' => $payload['telefone'] ?? null,
                'nome_contato' => $payload['nome_contato'] ?? null,
                'status' => (int)($payload['status'] ?? 1)
            ];
            if (!empty($payload['password'])) {
                $data['portal_password_hash'] = password_hash($payload['password'], PASSWORD_DEFAULT);
            }
            if (!empty($payload['id'])) {
                $sup->update($payload['id'], $data);
                ok(['id' => $payload['id']], 'Fornecedor atualizado');
            } else {
                $id = $sup->create($data);
                ok(['id' => $id], 'Fornecedor criado');
            }
        case 'products_list':
            requireInternal($auth);
            $prod = new Product($GLOBALS['db']);
            ok(['products' => $prod->findAll(['status' => 1], 'nome ASC')]);
        case 'products_save':
            requireInternal($auth);
            $payload = json_decode(file_get_contents('php://input'), true);
            $prod = new Product($GLOBALS['db']);
            $data = [
                'nome' => $payload['nome'],
                'descricao' => $payload['descricao'] ?? null,
                'unidade_medida' => $payload['unidade_medida'],
                'categoria' => $payload['categoria'] ?? null,
                'status' => (int)($payload['status'] ?? 1)
            ];
            if (!empty($payload['id'])) {
                $prod->update($payload['id'], $data);
                ok(['id' => $payload['id']], 'Produto atualizado');
            } else {
                $id = $prod->create($data);
                ok(['id' => $id], 'Produto criado');
            }
        case 'rfq_create':
            requireInternal($auth);
            $payload = json_decode(file_get_contents('php://input'), true);
            $rfqData = [
                'titulo' => $payload['titulo'],
                'descricao' => $payload['descricao'] ?? null,
                'prazo_resposta' => $payload['prazo_resposta'],
                'criado_por' => $_SESSION['user_id'],
                'status' => 'aberta'
            ];
            $items = $payload['itens'] ?? [];
            $rfqId = $svc->createRfq($rfqData, $items);
            ok(['rfq_id' => $rfqId], 'RFQ criada');
        case 'rfq_list':
            requireInternal($auth);
            $stmt = $db->query("SELECT r.*, (SELECT COUNT(*) FROM rfq_suppliers rs WHERE rs.rfq_id = r.id AND rs.status = 'respondeu') as respostas, (SELECT COUNT(*) FROM rfq_suppliers rs WHERE rs.rfq_id = r.id) as convidados FROM rfqs r ORDER BY r.created_at DESC");
            ok(['rfqs' => $stmt->fetchAll()]);
        case 'rfq_invite':
            requireInternal($auth);
            $payload = json_decode(file_get_contents('php://input'), true);
            $rfqId = (int)$payload['rfq_id'];
            $suppliers = array_map('intval', $payload['suppliers'] ?? []);
            $count = $svc->inviteSuppliers($rfqId, $suppliers);
            app_log_info('RFQ invitation sent', ['rfq_id' => $rfqId, 'suppliers' => $suppliers, 'count' => $count]);
            ok(['invited' => $count], 'Convites processados');
        case 'rfq_compare':
            requireInternal($auth);
            $rfqId = (int)($_GET['rfq_id'] ?? 0);
            $matrix = $svc->comparisonMatrix($rfqId);
            ok($matrix);
        case 'supplier_login':
            $email = $_POST['email'] ?? '';
            $pass = $_POST['password'] ?? '';
            $sup = new Supplier($db);
            $row = $sup->findOne(['email' => $email, 'status' => 1]);
            if (!$row || empty($row['portal_password_hash']) || !password_verify($pass, $row['portal_password_hash'])) {
                fail('Credenciais inválidas');
            }
            $_SESSION['supplier_id'] = (int)$row['id'];
            ok(['supplier' => ['id' => (int)$row['id'], 'razao_social' => $row['razao_social']]]);
        case 'supplier_logout':
            unset($_SESSION['supplier_id']);
            ok();
        case 'supplier_rfq_list':
            requireSupplier();
            $sid = (int)$_SESSION['supplier_id'];
            $stmt = $db->prepare("SELECT r.* , rs.status as invite_status FROM rfq_suppliers rs JOIN rfqs r ON r.id = rs.rfq_id WHERE rs.fornecedor_id = :sid ORDER BY r.created_at DESC");
            $stmt->execute([':sid' => $sid]);
            ok(['rfqs' => $stmt->fetchAll()]);
        case 'supplier_rfq_items':
            requireSupplier();
            $sid = (int)$_SESSION['supplier_id'];
            $rfqId = (int)($_GET['rfq_id'] ?? 0);
            // Validate supplier invited
            $chk = $db->prepare('SELECT 1 FROM rfq_suppliers WHERE rfq_id = :r AND fornecedor_id = :f');
            $chk->execute([':r' => $rfqId, ':f' => $sid]);
            if (!$chk->fetchColumn()) { fail('Acesso negado a esta RFQ'); }
            $stmt = $db->prepare("SELECT ri.produto_id, p.nome, p.unidade_medida, ri.quantidade FROM rfq_items ri JOIN products p ON p.id = ri.produto_id WHERE ri.rfq_id = :id");
            $stmt->execute([':id' => $rfqId]);
            ok(['items' => $stmt->fetchAll()]);
        case 'supplier_quote_submit':
            requireSupplier();
            $sid = (int)$_SESSION['supplier_id'];
            $payload = json_decode(file_get_contents('php://input'), true);
            $rfqId = (int)$payload['rfq_id'];
            // Validate invitation
            $chk = $db->prepare('SELECT 1 FROM rfq_suppliers WHERE rfq_id = :r AND fornecedor_id = :f');
            $chk->execute([':r' => $rfqId, ':f' => $sid]);
            if (!$chk->fetchColumn()) { fail('Acesso negado a esta RFQ'); }
            $items = $payload['itens'] ?? [];
            $svc->submitSupplierQuote($rfqId, $sid, $items);
            app_log_info('Supplier submitted quote', ['rfq_id' => $rfqId, 'supplier_id' => $sid]);
            ok([], 'Cotação enviada');
        case 'dashboard_counts':
            requireInternal($auth);
            $open = $db->query("SELECT COUNT(*) FROM rfqs WHERE status = 'aberta'")->fetchColumn();
            $respond = $db->query("SELECT COUNT(DISTINCT q.rfq_id) FROM supplier_quotes q")->fetchColumn();
            $pendStmt = $db->query("SELECT COUNT(*) FROM rfq_suppliers WHERE status = 'pendente'");
            $pend = $pendStmt->fetchColumn();
            ok(['abertas' => (int)$open, 'respondidas' => (int)$respond, 'pendentes' => (int)$pend]);
        default:
            fail('Ação inválida');
    }
} catch (Throwable $e) {
    fail('Erro: ' . $e->getMessage());
}
