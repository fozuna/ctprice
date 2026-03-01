<?php

// Basic error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Determine APP_URL for asset linking
$scriptName = $_SERVER['SCRIPT_NAME'];
if (strpos($scriptName, '/public_html/index.php') !== false) {
    $basePath = str_replace('/public_html/index.php', '', $scriptName);
} elseif (strpos($scriptName, '/index.php') !== false) {
    $basePath = str_replace('/index.php', '', $scriptName);
} else {
    $basePath = dirname($scriptName);
}
define('APP_URL', rtrim($basePath, '/'));

// Simple PSR-4 Autoloader implementation to work without Composer initially
spl_autoload_register(function ($class) {
    // Project-specific namespace prefix
    $prefix = 'App\\';

    // Base directory for the namespace prefix
    $base_dir = BASE_PATH . '/app/';

    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Simple .env parser since we might not have vlucas/phpdotenv installed via composer yet
if (file_exists(BASE_PATH . '/.env')) {
    $lines = file(BASE_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

use App\Core\Router;
use App\Core\Database;

// Initialize Router
$router = new Router();

// Define Routes
$router->get('/', [\App\Controllers\HomeController::class, 'index']);
$router->post('/partners/load-more', [\App\Controllers\HomeController::class, 'loadMorePartners']);

// Dispatch
try {
    $router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
