<?php
use App\Core\Autoload;
use App\Core\Security;
use App\Core\Config;

require __DIR__ . '/Autoload.php';
Autoload::register();

// Configurações básicas
$app = Config::app();

// Segurança de sessão
Security::startSecureSession($app['security']['session_name']);
// Aplicar timeout de inatividade de 20 minutos
Security::enforceInactivityTimeout(1200);

// Erros em dev
if ($app['env'] === 'dev') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
}

set_error_handler(function ($severity, $message, $file, $line) {
    $msg = '[' . date('c') . "] PHPERROR {$severity} {$message} in {$file}:{$line}\n";
    @file_put_contents(STORAGE_PATH . DIRECTORY_SEPARATOR . 'php-error.log', $msg, FILE_APPEND);
});
set_exception_handler(function ($ex) {
    $msg = '[' . date('c') . '] EXCEPTION ' . get_class($ex) . ': ' . $ex->getMessage() . "\n";
    @file_put_contents(STORAGE_PATH . DIRECTORY_SEPARATOR . 'php-error.log', $msg, FILE_APPEND);
});

// Constantes de caminho
define('BASE_PATH', dirname(__DIR__, 2));
define('APP_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'app');
define('PUBLIC_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'public');
define('STORAGE_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'storage');

// Logging de erros em arquivo
@mkdir(STORAGE_PATH, 0775, true);
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . DIRECTORY_SEPARATOR . 'php-error.log');

// Garantir diretório de currículos
if (!is_dir(STORAGE_PATH . DIRECTORY_SEPARATOR . 'resumes')) {
    @mkdir(STORAGE_PATH . DIRECTORY_SEPARATOR . 'resumes', 0775, true);
}

// Garantir diretório público para logos
$logosDir = PUBLIC_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'logos';
if (!is_dir($logosDir)) {
    @mkdir($logosDir, 0775, true);
}