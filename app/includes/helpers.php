<?php
// Helpers gerais de URL para assets públicos
if (!function_exists('asset_url')) {
    function asset_url(string $relativePath): string {
        $path = ltrim($relativePath, '/');
        $script = $_SERVER['SCRIPT_NAME'] ?? '/';
        $base = rtrim(dirname($script), '/\\');
        $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
        $publicExists = $docroot && is_file($docroot . $base . '/public/router.php');
        $prefix = ($base === '' || $base === '/') ? '/' : ($base . '/');
        if ($publicExists) {
            $prefix .= 'public/';
        }
        return $prefix . $path;
    }
}

if (!function_exists('base_url')) {
    function base_url(): string {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = $_SERVER['SCRIPT_NAME'] ?? '/';
        $basePath = rtrim(dirname($script), '/\\');
        return $scheme . '://' . $host . (($basePath === '' || $basePath === '/') ? '' : $basePath);
    }
}

if (!function_exists('app_log')) {
    function app_log(string $level, string $message, array $context = []): void {
        $upper = strtoupper($level);
        $map = [
            'DEBUG' => 0,
            'INFO' => 1,
            'WARNING' => 2,
            'ERROR' => 3
        ];
        $thresholdName = defined('LOG_LEVEL') ? strtoupper(LOG_LEVEL) : 'INFO';
        $threshold = $map[$thresholdName] ?? $map['INFO'];
        $current = $map[$upper] ?? $map['ERROR'];
        if ($current < $threshold) {
            return;
        }
        $baseDir = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__);
        $logDir = $baseDir . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $file = $logDir . DIRECTORY_SEPARATOR . 'system.log';
        $entry = [
            'time' => date('c'),
            'level' => $upper,
            'message' => $message,
            'context' => $context
        ];
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($line) || $line === '') {
            return;
        }
        $line .= PHP_EOL;
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('app_log_debug')) {
    function app_log_debug(string $message, array $context = []): void {
        app_log('DEBUG', $message, $context);
    }
}

if (!function_exists('app_log_info')) {
    function app_log_info(string $message, array $context = []): void {
        app_log('INFO', $message, $context);
    }
}

if (!function_exists('app_log_error')) {
    function app_log_error(string $message, array $context = []): void {
        app_log('ERROR', $message, $context);
    }
}
?>
