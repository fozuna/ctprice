<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__DIR__);

require_once $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'autoload.php';

$classes = [
    'Database',
    'BaseModel',
    'Goal',
    'AuditLog'
];

$failed = false;

foreach ($classes as $class) {
    if (!class_exists($class)) {
        fwrite(STDERR, '[FAIL] Classe não autocarregada: ' . $class . PHP_EOL);
        $failed = true;
    } else {
        echo '[OK] Classe autocarregada: ' . $class . PHP_EOL;
    }
}

if ($failed) {
    echo PHP_EOL . 'Resultado: FALHA no autoload.' . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Resultado: autoload funcionando corretamente.' . PHP_EOL;
exit(0);

