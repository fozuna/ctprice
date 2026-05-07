<?php
require_once 'config/config.php';

$database = Database::getInstance();
$db = $database->getConnection();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$deliveryModel = new Delivery($db);

$action = $_POST['action'] ?? $_GET['action'] ?? null;
if ($action === null) {
    if (isset($_GET['startDate']) || isset($_GET['endDate']) || isset($_GET['serviceFront']) || isset($_GET['client'])) {
        $action = 'sync_external';
    } elseif (isset($_POST['delivery_date']) && isset($_POST['front_id']) && isset($_POST['client_id'])) {
        $action = 'create';
    }
}

try {
    switch ($action) {
        case 'create':
            $loadsCount = isset($_POST['loads_count']) ? intval($_POST['loads_count']) : null;
            $delivered = isset($_POST['delivered_value']) ? floatval($_POST['delivered_value']) : null;
            if ($delivered === null || $delivered < 0) {
                throw new Exception('Peso total das cargas inválido.');
            }
            if (!is_null($loadsCount) && $loadsCount < 0) {
                throw new Exception('Quantidade de cargas deve ser um número inteiro não negativo.');
            }
            $notesExtra = '';
            if (!empty($loadsCount)) {
                $notesExtra = 'Qtd cargas: ' . $loadsCount;
            }
            if (!empty($_POST['notes'])) {
                $notesExtra = trim($notesExtra) ? ($notesExtra . ' • ' . $_POST['notes']) : $_POST['notes'];
            }
            $data = [
                'front_id' => $_POST['front_id'],
                'client_id' => $_POST['client_id'],
                'delivery_date' => $_POST['delivery_date'],
                'delivered_value' => $delivered,
                'unit' => $_POST['unit'] ?? 'toneladas',
                'notes' => $notesExtra ?: null,
                'created_by' => $_SESSION['user_id']
            ];
            $deliveryModel->createEntry($data);
            $_SESSION['success_message'] = 'Entrega registrada com sucesso';
            header('Location: delivery-entry.php');
            break;
        case 'update':
            $id = $_POST['id'];
            $data = [
                'front_id' => $_POST['front_id'],
                'client_id' => $_POST['client_id'],
                'delivery_date' => $_POST['delivery_date'],
                'delivered_value' => $_POST['delivered_value'],
                'unit' => $_POST['unit'] ?? 'toneladas',
                'notes' => $_POST['notes'] ?? null,
                'updated_by' => $_SESSION['user_id']
            ];
            $deliveryModel->updateEntry($id, $data);
            $_SESSION['success_message'] = 'Entrega atualizada com sucesso';
            header('Location: delivery-entry.php');
            break;
        case 'delete':
            $id = $_GET['id'];
            $deliveryModel->deleteEntry($id);
            $_SESSION['success_message'] = 'Entrega excluída com sucesso';
            header('Location: delivery-entry.php');
            break;
        case 'sync_external':
            $month = $_GET['month'] ?? date('Y-m');
            $frontId = $_GET['front_id'] ?? '';
            $clientId = $_GET['client_id'] ?? '';
            $monthDate = new DateTime($month . '-01');
            $startDate = $monthDate->format('Y-m-01');
            $endDate = $monthDate->format('Y-m-t');
            $externalUrl = getenv('EXTERNAL_API_DELIVERIES_URL') ?: (defined('EXTERNAL_API_DELIVERIES_URL') ? EXTERNAL_API_DELIVERIES_URL : null);
            $externalToken = getenv('EXTERNAL_API_TOKEN')
                ?: (defined('EXTERNAL_API_TOKEN') ? EXTERNAL_API_TOKEN : null)
                ?: getenv('LOGISTICS_DELIVERY_REPORT_API_TOKEN')
                ?: (defined('LOGISTICS_DELIVERY_REPORT_API_TOKEN') ? LOGISTICS_DELIVERY_REPORT_API_TOKEN : null);
            if (!$externalUrl) {
                throw new Exception('Endpoint externo não configurado (EXTERNAL_API_DELIVERIES_URL).');
            }
            $frontCode = null;
            $clientCode = null;
            if (!empty($frontId)) {
                $frontModel = new Front($db);
                $front = $frontModel->findById((int)$frontId);
                $frontCode = $front ? ($front['code'] ?? null) : null;
            }
            if (!empty($clientId)) {
                $clientModel = new Client($db);
                $client = $clientModel->findById((int)$clientId);
                $clientCode = $client ? ($client['code'] ?? null) : null;
            }
            $page = 1;
            $pageSize = 100;
            $allRecords = [];
            $apiReturned = 0;
            $apiSample = null;
            while (true) {
                $params = [
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'page' => $page,
                    'pageSize' => $pageSize
                ];
                if ($frontCode) { $params['serviceFront'] = $frontCode; }
                if ($clientCode) { $params['client'] = $clientCode; }
                $query = http_build_query($params);
                $url = $externalUrl . (strpos($externalUrl, '?') === false ? '?' : '&') . $query;
                $headers = ['Content-Type: application/json', 'Accept: application/json'];
                if ($externalToken) {
                    $headers[] = 'Authorization: Bearer ' . $externalToken;
                    $headers[] = 'x-api-token: ' . $externalToken;
                }
                $responseBody = null;
                $httpCode = 0;
                if (function_exists('curl_init')) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    $responseBody = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    if (curl_errno($ch)) {
                        $err = curl_error($ch);
                        curl_close($ch);
                        throw new Exception('Erro de comunicação com API externa: ' . $err);
                    }
                    curl_close($ch);
                } else {
                    $ctxOpts = ['http' => ['header' => implode("\r\n", $headers)]];
                    $context = stream_context_create($ctxOpts);
                    $responseBody = file_get_contents($url, false, $context);
                }
                if ($httpCode && ($httpCode < 200 || $httpCode >= 300)) {
                    throw new Exception('API externa retornou código HTTP ' . $httpCode);
                }
                if ($responseBody === false || $responseBody === null) {
                    throw new Exception('Falha ao obter dados da API externa');
                }
                $json = json_decode($responseBody, true);
                if ($json === null) {
                    throw new Exception('Resposta da API externa inválida (JSON).');
                }
                $pageData = [];
                if (is_array($json)) {
                    if (isset($json['data']) && is_array($json['data'])) {
                        $pageData = $json['data'];
                    } elseif (isset($json['records']) && is_array($json['records'])) {
                        $pageData = $json['records'];
                    } elseif (isset($json['result']) && is_array($json['result'])) {
                        $pageData = $json['result'];
                    } elseif (isset($json[0]) && is_array($json[0])) {
                        $pageData = $json;
                    }
                }
                if ($apiSample === null && is_array($pageData) && count($pageData) > 0) {
                    $first = reset($pageData);
                    if (is_array($first)) {
                        $apiSample = array_keys($first);
                    }
                }
                $apiReturned += is_array($pageData) ? count($pageData) : 0;
                if (!is_array($pageData)) {
                    throw new Exception('Formato de dados da API externa não suportado.');
                }
                foreach ($pageData as $rec) { $allRecords[] = $rec; }
                if (count($pageData) < $pageSize) { break; }
                $page++;
                if ($page > 1000) { break; }
            }
            $parseFloat = function($val) {
                if ($val === null) return null;
                if (is_numeric($val)) return (float)$val;
                $v = str_replace(['.', ','], ['', '.'], (string)$val);
                return is_numeric($v) ? (float)$v : null;
            };
            $aggregate = [];
            foreach ($allRecords as $r) {
                $dDate = $r['delivery_date'] ?? $r['date'] ?? $r['deliveryDate'] ?? $r['movementDate'] ?? $r['shipping_date'] ?? null;
                $fCode = $r['front_code'] ?? $r['serviceFront'] ?? $r['service_front'] ?? $r['front'] ?? null;
                $fName = $r['serviceFrontName'] ?? $r['front_name'] ?? $r['frontName'] ?? null;
                $cCode = $r['client_code'] ?? $r['client'] ?? $r['customer'] ?? $r['destination'] ?? null;
                $cName = $r['client_name'] ?? $r['customerName'] ?? $r['destinationName'] ?? null;
                $valueRaw = $r['delivered_value'] ?? $r['weight'] ?? $r['net_weight'] ?? $r['netWeight'] ?? $r['totalWeight'] ?? $r['quantity'] ?? null;
                $value = $parseFloat($valueRaw);
                if (!$dDate || !$fCode || !$cCode || $value === null) {
                    if (!$dDate || $value === null) { continue; }
                    if (!$fCode && $fName) { $fCode = $fName; }
                    if (!$cCode && $cName) { $cCode = $cName; }
                    if (!$fCode || !$cCode) { continue; }
                }
                $key = $dDate . '|' . (string)$fCode . '|' . (string)$cCode;
                if (!isset($aggregate[$key])) {
                    $aggregate[$key] = [
                        'date' => $dDate,
                        'front_code' => is_string($fCode) ? $fCode : (string)$fCode,
                        'front_name' => $fName,
                        'client_code' => is_string($cCode) ? $cCode : (string)$cCode,
                        'client_name' => $cName,
                        'value' => 0
                    ];
                }
                $aggregate[$key]['value'] += $value;
            }
            $frontModel = isset($frontModel) ? $frontModel : new Front($db);
            $clientModel = isset($clientModel) ? $clientModel : new Client($db);
            $imported = 0;
            $skipped = [];
            foreach ($aggregate as $item) {
                $f = $frontModel->findByCode($item['front_code']);
                if (!$f && !empty($item['front_name'])) { $f = $frontModel->findOne(['name' => $item['front_name']]); }
                $c = $clientModel->findByCode($item['client_code']);
                if (!$c && !empty($item['client_name'])) { $c = $clientModel->findOne(['name' => $item['client_name']]); }
                if (!$f || !$c) {
                    $skipped[] = $item;
                    continue;
                }
                $ok = $deliveryModel->upsertEntryByUnique((int)$f['id'], (int)$c['id'], $item['date'], $item['value'], 'toneladas', null, $_SESSION['user_id']);
                if ($ok) { $imported++; }
            }
            $_SESSION['success_message'] = "Sincronização concluída: {$imported} registros diários aplicados. Ignorados: " . count($skipped) . ". API retornou: {$apiReturned}.";
            if ($apiSample) { $_SESSION['debug_api_sample'] = implode(', ', $apiSample); }
            if (!empty($skipped)) {
                $_SESSION['error_message'] = 'Alguns registros foram ignorados por falta de dados ou códigos não mapeados.';
            }
            header('Location: delivery-entry.php?month=' . urlencode($month) . '&front_id=' . urlencode($frontId) . '&client_id=' . urlencode($clientId));
            break;
        default:
            throw new Exception('Ação inválida');
    }
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Erro: ' . $e->getMessage();
    header('Location: delivery-entry.php');
}
?>
