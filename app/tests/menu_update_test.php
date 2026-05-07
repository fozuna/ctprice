<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../models/User.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $db = Database::getInstance()->getConnection();
    $auth = new Auth($db);
    if (!$auth->isLoggedIn()) {
        echo "skip: usuário não logado\n";
        exit(0);
    }
    $user = (new User($db))->findById($_SESSION['user_id']);
    $sessRole = $_SESSION['user_role'] ?? null;
    $dbRole = $user['role_id'] ?? null;
    echo "Sessão role: " . var_export($sessRole, true) . PHP_EOL;
    echo "Banco role : " . var_export($dbRole, true) . PHP_EOL;
    // Forçar re-inclusão do header para sincronizar
    ob_start();
    include __DIR__ . '/../includes/header.php';
    ob_end_clean();
    $afterRole = $_SESSION['user_role'] ?? null;
    echo "Após header role: " . var_export($afterRole, true) . PHP_EOL;
    if ($dbRole !== null && (int)$afterRole === (int)$dbRole) {
        echo "ok: menu sincroniza role com o banco\n";
        exit(0);
    }
    echo "fail: role não sincronizou\n";
    exit(1);
} catch (Throwable $e) {
    echo "erro: " . $e->getMessage() . "\n";
    exit(2);
}

