<?php
$configPath = __DIR__ . '/config/config.php';
if (!is_file($configPath)) {
    if (function_exists('error_log')) {
        error_log('Config file not found at ' . $configPath);
    }
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
    $redir = ($base === '' || $base === '/') ? '/install.php' : ($base . '/install.php');
    header('Location: ' . $redir, true, 302);
    exit;
}
require_once $configPath;
if (file_exists(__DIR__ . '/includes/helpers.php')) {
    require_once __DIR__ . '/includes/helpers.php';
}
if (function_exists('app_log')) {
    $reqUri = $_SERVER['REQUEST_URI'] ?? '';
    app_log('INFO', 'Router initialized and config loaded', ['uri' => $reqUri, 'config' => $configPath]);
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = $uri === null ? '/' : $uri;
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
if ($base && $base !== '/' && strpos($uri, $base . '/') === 0) {
    $uri = substr($uri, strlen($base));
}

if ($uri === '' || $uri === '/') {
    require_once __DIR__ . '/index.php';
    exit;
}

$path = ltrim($uri, '/');

if (strpos($path, '..') !== false || strpos($path, './') === 0) {
    http_response_code(400);
    echo '400 Bad Request';
    exit;
}

$blockedPrefixes = [
    'config/', 'database/', 'classes/', 'models/', 'includes/', 'scripts/'
];
foreach ($blockedPrefixes as $prefix) {
    if (strpos($path, $prefix) === 0) {
        http_response_code(403);
        echo '403 Forbidden';
        exit;
    }
}

$allowedPhp = [
    'login.php', 'logout.php', 'dashboard.php', 'dashboard-entregas.php',
    'goals.php', 'goals-production.php', 'goals-delivery.php', 'dashboard-metas.php',
    'clients.php', 'fronts.php', 'front-details.php',
    'equipments.php', 'equipment-details.php', 'equipment-mobilization.php', 'equipment-mobilization-details.php', 'equipments-admin.php',
    'occurrences.php', 'occurrence-types.php', 'new-occurrence.php',
    'reports.php', 'reports-front.php', 'reports-equipment.php', 'reports-operator.php',
    'users.php',
    'production-entry.php', 'delivery-entry.php',
    'production-action.php', 'delivery-action.php', 'goal-action.php', 'mobilization-action.php', 'client-action.php',
    'export-daily-goals.php',
    'equipments-api.php',
    'procurement-api.php',
    'rh-vagas.php', 'rh-vaga.php', 'rh-kanban.php', 'rh-dashboard.php', 'rh-data.php', 'rh-data-api.php', 'rh-interview.php', 'rh-solicitacao-vaga.php',
    'candidate-register.php', 'vagas.php',
    'install.php', 'network-info.php', 'test-plugins.php', 'test-export.php', 'test-export-simple.php',
    'favicon-upload.php'
];

$publicWithoutAuth = [
    'login.php', 'install.php', 'candidate-register.php', 'vagas.php',
    'supplier-login.php', 'supplier-portal.php'
];

if (in_array($path, $allowedPhp, true)) {
    if (!in_array($path, $publicWithoutAuth, true)) {
        if (!isset($_SESSION['user_id'])) {
            $redir = ($base === '' || $base === '/') ? '/login.php' : ($base . '/login.php');
            header('Location: ' . $redir, true, 302);
            exit;
        }
    }

    $file = __DIR__ . '/' . $path;
    if (is_file($file)) {
        require_once $file;
        exit;
    }
}

$publicFile = __DIR__ . '/' . $path;
if (is_file($publicFile)) {
    $ext = strtolower(pathinfo($publicFile, PATHINFO_EXTENSION));
    if ($ext === 'php') {
        require_once $publicFile;
    } else {
        // Definir Content-Type adequado para evitar bloqueio de CSS/JS/imagens
        $mimeMap = [
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf'
        ];
        $mime = $mimeMap[$ext] ?? (function_exists('mime_content_type') ? mime_content_type($publicFile) : 'application/octet-stream');
        header('Content-Type: ' . $mime);
        readfile($publicFile);
    }
    exit;
}

// 404 padrão
http_response_code(404);
echo '404 Not Found';
exit;
?>
