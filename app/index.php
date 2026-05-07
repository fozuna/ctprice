<?php
/**
 * Página Principal do Sistema BDO
 * Sistema BDO - Controle de Maquinários
 */

if (!file_exists(__DIR__ . '/config/config.php')) {
    header('Location: install.php');
    exit();
}
require_once 'config/config.php';

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
    $redir = ($base === '' || $base === '/') ? '/login.php' : ($base . '/login.php');
    header('Location: ' . $redir);
    exit();
}

$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
$redir = ($base === '' || $base === '/') ? '/dashboard.php' : ($base . '/dashboard.php');
header('Location: ' . $redir, true, 302);
exit();
?>
