<?php
require_once 'config/config.php';

// Configurar conexão com banco
$database = Database::getInstance();
$db = $database->getConnection();

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: clients.php');
    exit;
}

// Instanciar modelo
$clientModel = new Client($db);

// Obter ação
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            // Validar dados obrigatórios
            if (empty($_POST['code']) || empty($_POST['name'])) {
                throw new Exception('Código e nome são obrigatórios');
            }
            
            // Verificar se código já existe
            if ($clientModel->codeExists($_POST['code'])) {
                throw new Exception('Código já existe. Escolha outro código.');
            }
            
            // Preparar dados
            $data = [
                'code' => trim($_POST['code']),
                'name' => trim($_POST['name']),
                'description' => trim($_POST['description'] ?? ''),
                'active' => isset($_POST['active']) ? (int)$_POST['active'] : 1
            ];
            
            // Criar cliente
            $clientId = $clientModel->create($data);
            
            $_SESSION['success'] = 'Cliente criado com sucesso!';
            break;
            
        case 'update':
            // Validar ID
            if (empty($_POST['id'])) {
                throw new Exception('ID do cliente não informado');
            }
            
            // Validar dados obrigatórios
            if (empty($_POST['code']) || empty($_POST['name'])) {
                throw new Exception('Código e nome são obrigatórios');
            }
            
            $clientId = (int)$_POST['id'];
            
            // Verificar se cliente existe
            $existingClient = $clientModel->findById($clientId);
            if (!$existingClient) {
                throw new Exception('Cliente não encontrado');
            }
            
            // Verificar se código já existe (exceto para o próprio cliente)
            if ($clientModel->codeExists($_POST['code'], $clientId)) {
                throw new Exception('Código já existe. Escolha outro código.');
            }
            
            // Preparar dados
            $data = [
                'code' => trim($_POST['code']),
                'name' => trim($_POST['name']),
                'description' => trim($_POST['description'] ?? ''),
                'active' => isset($_POST['active']) ? (int)$_POST['active'] : 1
            ];
            
            // Atualizar cliente
            $clientModel->update($clientId, $data);
            
            $_SESSION['success'] = 'Cliente atualizado com sucesso!';
            break;
            
        case 'delete':
            // Validar ID
            if (empty($_POST['id'])) {
                throw new Exception('ID do cliente não informado');
            }
            
            $clientId = (int)$_POST['id'];
            
            // Verificar se cliente existe
            $existingClient = $clientModel->findById($clientId);
            if (!$existingClient) {
                throw new Exception('Cliente não encontrado');
            }
            
            // Verificar se pode excluir (não tem metas associadas)
            if (!$clientModel->canDelete($clientId)) {
                throw new Exception('Não é possível excluir este cliente pois existem metas associadas a ele');
            }
            
            // Excluir cliente
            $clientModel->delete($clientId);
            
            $_SESSION['success'] = 'Cliente excluído com sucesso!';
            break;
            
        case 'activate':
            // Validar ID
            if (empty($_POST['id'])) {
                throw new Exception('ID do cliente não informado');
            }
            
            $clientId = (int)$_POST['id'];
            
            // Ativar cliente
            $clientModel->activate($clientId);
            
            $_SESSION['success'] = 'Cliente ativado com sucesso!';
            break;
            
        case 'deactivate':
            // Validar ID
            if (empty($_POST['id'])) {
                throw new Exception('ID do cliente não informado');
            }
            
            $clientId = (int)$_POST['id'];
            
            // Desativar cliente
            $clientModel->deactivate($clientId);
            
            $_SESSION['success'] = 'Cliente desativado com sucesso!';
            break;
            
        case 'toggle_status':
            $id = $_POST['id'] ?? 0;
            $active = isset($_POST['active']) ? (int)$_POST['active'] : 0;
            
            if ($id) {
                $result = $clientModel->updateStatus($id, $active);
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar status']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'ID inválido']);
            }
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

// Redirecionar de volta para a página de clientes
header('Location: clients.php');
exit;
?>
