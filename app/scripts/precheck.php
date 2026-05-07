<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__DIR__);

$checks = [];
$hasError = false;

function add_check(array &$checks, string $name, bool $ok, string $detail = ''): void {
    $checks[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];
}

function check_path(string $path, bool $mustBeFile = true, bool $required = true): array {
    $exists = $mustBeFile ? is_file($path) : is_dir($path);
    $readable = $exists && is_readable($path);
    $writable = $exists && is_writable($path);
    $status = $exists && $readable && (!$mustBeFile || $writable || !$required);
    return [
        'exists' => $exists,
        'readable' => $readable,
        'writable' => $writable,
        'status' => $status
    ];
}

$configFile = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
$configResult = check_path($configFile, true, true);
add_check(
    $checks,
    'Config config.php',
    $configResult['status'],
    json_encode($configResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);
if (!$configResult['status']) {
    $hasError = true;
}

$dbFile = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'Database.php';
$dbResult = check_path($dbFile, true, true);
add_check(
    $checks,
    'Config Database.php',
    $dbResult['status'],
    json_encode($dbResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);
if (!$dbResult['status']) {
    $hasError = true;
}

$autoloadFile = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'autoload.php';
$autoloadResult = check_path($autoloadFile, true, true);
add_check(
    $checks,
    'Config autoload.php',
    $autoloadResult['status'],
    json_encode($autoloadResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);
if (!$autoloadResult['status']) {
    $hasError = true;
}

$logsDir = $root . DIRECTORY_SEPARATOR . 'logs';
$logsResult = check_path($logsDir, false, true);
add_check(
    $checks,
    'Diretório logs/',
    $logsResult['status'] && $logsResult['writable'],
    json_encode($logsResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);
if (!($logsResult['status'] && $logsResult['writable'])) {
    $hasError = true;
}

$uploadsDir = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads';
$uploadsResult = check_path($uploadsDir, false, true);
add_check(
    $checks,
    'Diretório public/uploads/',
    $uploadsResult['status'] && $uploadsResult['writable'],
    json_encode($uploadsResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);
if (!($uploadsResult['status'] && $uploadsResult['writable'])) {
    $hasError = true;
}

$configDir = $root . DIRECTORY_SEPARATOR . 'config';
$configDirResult = check_path($configDir, false, true);
add_check(
    $checks,
    'Diretório config/',
    $configDirResult['status'] && $configDirResult['writable'],
    json_encode($configDirResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);
if (!($configDirResult['status'] && $configDirResult['writable'])) {
    $hasError = true;
}

$rootHtaccess = $root . DIRECTORY_SEPARATOR . '.htaccess';
$rootHtaccessResult = check_path($rootHtaccess, true, true);
add_check(
    $checks,
    'Arquivo .htaccess raiz',
    $rootHtaccessResult['status'],
    json_encode($rootHtaccessResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);
if (!$rootHtaccessResult['status']) {
    $hasError = true;
}

$publicHtaccess = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . '.htaccess';
$publicHtaccessResult = check_path($publicHtaccess, true, true);
add_check(
    $checks,
    'Arquivo public/.htaccess',
    $publicHtaccessResult['status'],
    json_encode($publicHtaccessResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);
if (!$publicHtaccessResult['status']) {
    $hasError = true;
}

$userIni = $root . DIRECTORY_SEPARATOR . '.user.ini';
$userIniResult = check_path($userIni, true, true);
add_check(
    $checks,
    'Arquivo .user.ini',
    $userIniResult['status'],
    json_encode($userIniResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);
if (!$userIniResult['status']) {
    $hasError = true;
}

echo 'Preflight de dependências do Sistema BDO' . PHP_EOL;
echo str_repeat('=', 60) . PHP_EOL;
foreach ($checks as $c) {
    $statusText = $c['ok'] ? '[OK] ' : '[ERRO] ';
    echo $statusText . $c['name'];
    if ($c['detail'] !== '') {
        echo ' -> ' . $c['detail'];
    }
    echo PHP_EOL;
}
echo str_repeat('=', 60) . PHP_EOL;
echo $hasError ? 'Resultado: FALHA' . PHP_EOL : 'Resultado: SUCESSO' . PHP_EOL;

exit($hasError ? 1 : 0);

