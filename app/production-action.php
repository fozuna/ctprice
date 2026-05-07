<?php
require_once 'config/config.php';

$database = Database::getInstance();
$db = $database->getConnection();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$productionModel = new Production($db);

$action = $_POST['action'] ?? $_GET['action'] ?? null;

try {
    switch ($action) {
        case 'create':
            $frontId = (int)$_POST['front_id'];
            $clientId = (!empty($_POST['client_id']) ? (int)$_POST['client_id'] : null);
            $date = $_POST['production_date'];
            $value = (float)$_POST['produced_value'];
            $unit = $_POST['unit'] ?? 'toneladas';
            $notes = $_POST['notes'] ?? null;
            $userId = $_SESSION['user_id'];
            $productionModel->upsertEntryByDate($frontId, $date, $value, $unit, $clientId, $notes, $userId);
            $_SESSION['success_message'] = 'Produção registrada com sucesso';
            header('Location: production-entry.php');
            break;
        case 'update':
            $id = $_POST['id'];
            $data = [
                'front_id' => $_POST['front_id'],
                'client_id' => (!empty($_POST['client_id']) ? (int)$_POST['client_id'] : null),
                'production_date' => $_POST['production_date'],
                'produced_value' => $_POST['produced_value'],
                'unit' => $_POST['unit'] ?? 'toneladas',
                'notes' => $_POST['notes'] ?? null,
                'updated_by' => $_SESSION['user_id']
            ];
            $productionModel->updateEntry($id, $data);
            $_SESSION['success_message'] = 'Produção atualizada com sucesso';
            header('Location: production-entry.php');
            break;
        case 'delete':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id > 0) {
                $productionModel->deleteEntry($id);
                $_SESSION['success_message'] = 'Produção excluída com sucesso';
            } else {
                $_SESSION['error_message'] = 'Não foi possível excluir: identificador inválido.';
            }
            header('Location: production-entry.php');
            break;
        default:
            throw new Exception('Ação inválida');
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Erro: ' . $e->getMessage();
    header('Location: production-entry.php');
}
?>
