<?php
/**
 * Logout
 * Sistema BDO - Controle de Maquinários
 */
require_once __DIR__ . '/config/config.php';

$database = Database::getInstance();
$db = $database->getConnection();
$auth = new Auth($db);
$auth->logout();

// Redirecionar para a página de login
header('Location: login.php?logout=1');
exit();
?>
