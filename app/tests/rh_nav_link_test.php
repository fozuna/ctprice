<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    // cenário 1: coordenador de RH
    $_SESSION['user_id'] = $_SESSION['user_id'] ?? 1;
    $_SESSION['user_role'] = ROLE_COORD_RH;
    ob_start();
    include __DIR__ . '/../includes/header.php';
    $html = ob_get_clean();
    $okRh = strpos($html, 'href="rh-dashboard.php"') !== false;
    echo $okRh ? "ok: link dashboard aponta para rh-dashboard.php\n" : "fail: link não aponta para rh-dashboard.php\n";

    // cenário 2: outro perfil
    $_SESSION['user_role'] = ROLE_ADMIN;
    ob_start();
    include __DIR__ . '/../includes/header.php';
    $html2 = ob_get_clean();
    $okDefault = strpos($html2, 'href="dashboard-entregas.php"') !== false;
    echo $okDefault ? "ok: link dashboard padrão\n" : "fail: link dashboard padrão ausente\n";
    exit(($okRh && $okDefault) ? 0 : 1);
} catch (Throwable $e) {
    echo "erro: " . $e->getMessage() . "\n";
    exit(2);
}

