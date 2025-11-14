<?php
namespace App\Core;

class Config
{
    public static function app(): array
    {
        // Detecta se está rodando no servidor embutido do PHP
        $isCliServer = (PHP_SAPI === 'cli-server');
        // Caminho base padrão para Apache/XAMPP
        $defaultBase = '/ctprice/public';
        // No servidor embutido, como docroot já é 'public', o base deve ser vazio
        $base = $isCliServer ? '' : $defaultBase;
        return [
            'name' => 'CT Price - Sistema de Gestão de RH',
            'product_name' => 'TRAXTER RH',
            'version' => '1.23.25',
            'base_url' => $base, // prefixo de caminho usado pelo Router
            'env' => 'dev',
            'security' => [
                'csrf_key' => 'ctprice_csrf_token',
                'session_name' => 'CTPRICESESSID',
                'allowed_upload_mime' => ['application/pdf'],
                'max_upload_bytes' => 5 * 1024 * 1024, // 5MB
                'allowed_image_mime' => ['image/png','image/jpeg','image/webp'],
                'max_image_bytes' => 2 * 1024 * 1024, // 2MB
            ],
            'mail' => [
                'enabled' => true,
                'from' => 'no-reply@ctprice.local',
                'to_hr' => 'rh@ctprice.local',
                'subject_new_application' => 'Nova candidatura recebida',
            ],
            'database' => [
                'dsn' => 'mysql:host=127.0.0.1;dbname=ctprice;charset=utf8mb4',
                'user' => 'root',
                'pass' => '',
                'options' => [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ],
            ],
        ];
    }
}