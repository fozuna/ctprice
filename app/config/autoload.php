<?php
if (!defined('ROLE_ADMIN')) { define('ROLE_ADMIN', 1); }
if (!defined('ROLE_SUPERVISOR')) { define('ROLE_SUPERVISOR', 2); }
if (!defined('ROLE_LIDER')) { define('ROLE_LIDER', 3); }
if (!defined('ROLE_OPERADOR')) { define('ROLE_OPERADOR', 4); }
if (!defined('ROLE_COORD_RH')) { define('ROLE_COORD_RH', 5); }
if (!defined('ROLE_COMPRAS')) { define('ROLE_COMPRAS', 6); }

if (!function_exists('__app_register_autoload')) {
    function __app_register_autoload(): void {
        static $registered = false;
        if ($registered) return;
        spl_autoload_register(function ($class) {
            $baseDirs = [
                __DIR__ . '/../classes',
                __DIR__ . '/../models',
                __DIR__,
            ];
            foreach ($baseDirs as $dir) {
                if (!is_dir($dir)) continue;
                $exact = $dir . '/' . $class . '.php';
                if (is_file($exact)) {
                    require_once $exact;
                    return;
                }
                $lower = strtolower($class);
                foreach (glob($dir . '/*.php') as $cand) {
                    if (strtolower(pathinfo($cand, PATHINFO_FILENAME)) === $lower) {
                        require_once $cand;
                        return;
                    }
                }
            }
        });
        $registered = true;
    }
}
__app_register_autoload();
if (!class_exists('Database')) {
    $dbShim = __DIR__ . '/database.php';
    $dbClass = __DIR__ . '/Database.php';
    if (is_file($dbClass)) {
        require_once $dbClass;
    } elseif (is_file($dbShim)) {
        require_once $dbShim;
    }
}
?> 
