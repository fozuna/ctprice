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

// Instanciar modelo
$goalModel = new Goal($db);

// Obter ação
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$origin = $_POST['origin'] ?? $_GET['origin'] ?? '';

try {
    switch ($action) {
        case 'create':
            // Verificar se é uma requisição POST
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método inválido');
            }
            
            $goalType = $_POST['goal_type'] ?? 'production';
            $clientRequired = ($goalType !== 'production');
            if (empty($_POST['front_id']) || ($clientRequired && empty($_POST['client_id'])) || 
                empty($_POST['start_date']) || empty($_POST['end_date']) || 
                empty($_POST['total_goal'])) {
                throw new Exception('Todos os campos obrigatórios devem ser preenchidos');
            }
            
            // Validar datas
            $startDate = new DateTime($_POST['start_date']);
            $endDate = new DateTime($_POST['end_date']);
            
            if ($startDate >= $endDate) {
                throw new Exception('Data final deve ser maior que data inicial');
            }
            
            // Validar meta total
            $totalGoal = (float)$_POST['total_goal'];
            if ($totalGoal <= 0) {
                throw new Exception('Meta total deve ser maior que zero');
            }
            
            // Preparar dados
            $data = [
                'front_id' => (int)$_POST['front_id'],
                'client_id' => $clientRequired ? (int)$_POST['client_id'] : null,
                'start_date' => $_POST['start_date'],
                'end_date' => $_POST['end_date'],
                'total_goal' => $totalGoal,
                'goal_type' => $goalType,
                'description' => trim($_POST['description'] ?? ''),
                'created_by' => $_SESSION['user_id']
            ];
            
            // Criar meta
            $goalId = $goalModel->create($data);
            
            $_SESSION['success'] = 'Meta criada com sucesso! Metas diárias foram geradas automaticamente.';
            $redirect = 'goals.php';
            if ($origin === 'production' || $goalType === 'production') {
                $redirect = 'goals-production.php';
            } elseif ($origin === 'delivery' || $goalType === 'delivery') {
                $redirect = 'goals-delivery.php';
            }
            header('Location: ' . $redirect);
            exit;
            
        case 'update':
            // Verificar se é uma requisição POST
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método inválido');
            }
            
            // Validar ID
            if (empty($_POST['id'])) {
                throw new Exception('ID da meta não informado');
            }
            
            $goalId = (int)$_POST['id'];
            
            // Verificar se meta existe
            $existingGoal = $goalModel->findById($goalId);
            if (!$existingGoal) {
                throw new Exception('Meta não encontrada');
            }
            
            $goalType = $_POST['goal_type'] ?? 'production';
            $clientRequired = ($goalType !== 'production');
            if (empty($_POST['front_id']) || ($clientRequired && empty($_POST['client_id'])) || 
                empty($_POST['start_date']) || empty($_POST['end_date']) || 
                empty($_POST['total_goal'])) {
                throw new Exception('Todos os campos obrigatórios devem ser preenchidos');
            }
            
            // Validar datas
            $startDate = new DateTime($_POST['start_date']);
            $endDate = new DateTime($_POST['end_date']);
            
            if ($startDate >= $endDate) {
                throw new Exception('Data final deve ser maior que data inicial');
            }
            
            // Validar meta total
            $totalGoal = (float)$_POST['total_goal'];
            if ($totalGoal <= 0) {
                throw new Exception('Meta total deve ser maior que zero');
            }
            
            // Preparar dados
            $data = [
                'front_id' => (int)$_POST['front_id'],
                'client_id' => $clientRequired ? (int)$_POST['client_id'] : null,
                'start_date' => $_POST['start_date'],
                'end_date' => $_POST['end_date'],
                'total_goal' => $totalGoal,
                'goal_type' => $goalType,
                'description' => trim($_POST['description'] ?? ''),
                'updated_by' => $_SESSION['user_id']
            ];
            
            // Atualizar meta
            $goalModel->update($goalId, $data);
            
            $_SESSION['success'] = 'Meta atualizada com sucesso! Metas diárias foram recalculadas.';
            $redirect = 'goals.php';
            if ($origin === 'production' || $goalType === 'production') {
                $redirect = 'goals-production.php';
            } elseif ($origin === 'delivery' || $goalType === 'delivery') {
                $redirect = 'goals-delivery.php';
            }
            header('Location: ' . $redirect);
            exit;
            
        case 'delete':
            // Validar ID
            $goalId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            
            if (!$goalId) {
                throw new Exception('ID da meta não informado');
            }
            
            // Verificar se meta existe
            $existingGoal = $goalModel->findById($goalId);
            if (!$existingGoal) {
                throw new Exception('Meta não encontrada');
            }
            
            // Excluir meta (cascade irá excluir metas diárias automaticamente)
            $goalModel->delete($goalId);
            
            $_SESSION['success'] = 'Meta excluída com sucesso!';
            $redirect = 'goals.php';
            if ($origin === 'production') {
                $redirect = 'goals-production.php';
            } elseif ($origin === 'delivery') {
                $redirect = 'goals-delivery.php';
            }
            header('Location: ' . $redirect);
            exit;
        
        case 'delete_batch':
            $ids = $_POST['ids'] ?? [];
            if (!is_array($ids) || empty($ids)) {
                throw new Exception('Nenhuma meta selecionada para exclusão');
            }
            
            $deleted = 0;
            foreach ($ids as $rawId) {
                $goalId = (int)$rawId;
                if ($goalId > 0) {
                    $existingGoal = $goalModel->findById($goalId);
                    if ($existingGoal) {
                        $goalModel->delete($goalId);
                        $deleted++;
                    }
                }
            }
            
            if ($deleted > 0) {
                $_SESSION['success'] = "Excluídas {$deleted} metas com sucesso!";
            } else {
                $_SESSION['error'] = 'Nenhuma meta válida foi excluída';
            }
            $redirect = 'goals.php';
            if ($origin === 'production') {
                $redirect = 'goals-production.php';
            } elseif ($origin === 'delivery') {
                $redirect = 'goals-delivery.php';
            }
            header('Location: ' . $redirect);
            exit;
        
        case 'recalc_month':
            $month = $_POST['month'] ?? $_GET['month'] ?? date('Y-m');
            $frontId = $_POST['front_id'] ?? $_GET['front_id'] ?? null;
            $clientId = $_POST['client_id'] ?? $_GET['client_id'] ?? null;
            $goalType = 'delivery';
            $year = (int)substr($month, 0, 4);
            $mon = (int)substr($month, 5, 2);
            
            $goals = $goalModel->getGoalsWithPeriods($year, $mon, $frontId ?: null, $clientId ?: null, $goalType);
            $recalculated = 0;
            foreach ($goals as $g) {
                $goalModel->update((int)$g['id'], [
                    'start_date' => $g['start_date'],
                    'end_date' => $g['end_date'],
                    'total_goal' => $g['total_goal'],
                    'goal_type' => $g['goal_type'] ?: 'production'
                ]);
                $recalculated++;
            }
            $_SESSION['success'] = "Metas recalculadas para {$month}. Registros atualizados: {$recalculated}.";
            $q = http_build_query([
                'month' => $month,
                'front_id' => $frontId,
                'client_id' => $clientId
            ]);
            header('Location: goals-delivery.php?' . $q);
            exit;
            
        case 'activate':
            // Validar ID
            $goalId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            
            if (!$goalId) {
                throw new Exception('ID da meta não informado');
            }
            
            // Atualizar meta
            $goalModel->update($goalId, [
                'updated_by' => $_SESSION['user_id']
            ]);
            
            $_SESSION['success'] = 'Meta ativada com sucesso!';
            header('Location: goals.php');
            exit;
            
        case 'deactivate':
            // Validar ID
            $goalId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            
            if (!$goalId) {
                throw new Exception('ID da meta não informado');
            }
            
            // Desativar meta
            $goalModel->update($goalId, [
                'status' => 'inactive',
                'updated_by' => $_SESSION['user_id']
            ]);
            
            $_SESSION['success'] = 'Meta desativada com sucesso!';
            header('Location: goals.php');
            exit;
            
        default:
            throw new Exception('Ação inválida');
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    
    // Redirecionar baseado na ação
    if (in_array($action, ['create', 'update'])) {
        if (isset($_POST['id'])) {
            $redirectUrl = 'edit-goal.php?id=' . $_POST['id'];
        } else {
            $goalType = $_POST['goal_type'] ?? '';
            if ($origin === 'production' || $goalType === 'production') {
                $redirectUrl = 'new-production-goal.php';
            } elseif ($origin === 'delivery' || $goalType === 'delivery') {
                $redirectUrl = 'new-goal.php';
            } else {
                $redirectUrl = 'goals.php';
            }
        }
    } else {
        $redirectUrl = 'goals.php';
        if (in_array($action, ['delete', 'delete_batch'])) {
            if ($origin === 'production') {
                $redirectUrl = 'goals-production.php';
            } elseif ($origin === 'delivery') {
                $redirectUrl = 'goals-delivery.php';
            }
        }
    }
    
    header("Location: {$redirectUrl}");
    exit;
}
?>
