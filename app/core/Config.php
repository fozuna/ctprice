<?php
namespace App\Core;

class Config
{
    public static function app(): array
    {
        $envFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
        static $env = null;
        if ($env === null) {
            $env = [];
            if (is_file($envFile)) {
                foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) { continue; }
                    [$k, $v] = array_map('trim', explode('=', $line, 2));
                    $env[$k] = $v;
                }
            }
        }
        $get = function (string $k, $d = '') use ($env) {
            $v = $_ENV[$k] ?? $_SERVER[$k] ?? getenv($k) ?? ($env[$k] ?? null);
            return ($v !== null && $v !== '') ? $v : $d;
        };
        $isCliServer = (PHP_SAPI === 'cli-server');
        $defaultBase = '';
        $base = $get('APP_BASE_URL', $isCliServer ? '' : $defaultBase);
        $envName = $get('APP_ENV', 'dev');
        return [
            'name' => 'CT Price - Sistema de Gestão de RH',
            'product_name' => 'TRAXTER RH',
            'version' => '1.23.25',
            'base_url' => $base,
            'env' => $envName,
            'security' => [
                'csrf_key' => 'ctprice_csrf_token',
                'session_name' => 'CTPRICESESSID',
                'allowed_upload_mime' => ['application/pdf'],
                'max_upload_bytes' => 5 * 1024 * 1024,
                'allowed_image_mime' => ['image/png','image/jpeg','image/webp'],
                'max_image_bytes' => 2 * 1024 * 1024,
            ],
            'mail' => [
                'enabled' => true,
                'from' => 'no-reply@ctprice.local',
                'to_hr' => 'rh@ctprice.local',
                'subject_new_application' => 'Nova candidatura recebida',
            ],
            'database' => [
                'dsn' => 'mysql:host=' . $get('DB_HOST', '127.0.0.1') . ';dbname=' . $get('DB_NAME', 'ctprice') . ';charset=utf8mb4',
                'user' => $get('DB_USER', 'root'),
                'pass' => $get('DB_PASS', ''),
                'options' => [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ],
            ],
        ];
    }
}