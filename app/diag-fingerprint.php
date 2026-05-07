<?php
/**
 * Diagnóstico de carregamento de arquivos e cache
 * Acesse: /app/diag-fingerprint.php
 */

header('Content-Type: text/plain; charset=utf-8');

function p($k, $v) {
    echo str_pad($k, 36, ' ', STR_PAD_RIGHT) . ': ' . $v . PHP_EOL;
}

$targets = [
    'equipments-admin.php',
    'equipments-api.php',
    'classes/EquipmentService.php',
    'router.php',
    'includes/header.php',
    'config/config.php'
];

echo "=== Ambiente ===" . PHP_EOL;
p('PHP_VERSION', PHP_VERSION);
p('SAPI', php_sapi_name());
p('DOCUMENT_ROOT', $_SERVER['DOCUMENT_ROOT'] ?? '(n/a)');
p('SCRIPT_FILENAME', $_SERVER['SCRIPT_FILENAME'] ?? '(n/a)');
p('CWD', getcwd());
echo PHP_EOL;

echo "=== OPCache (se disponível) ===" . PHP_EOL;
foreach ([
    'opcache.enable',
    'opcache.enable_cli',
    'opcache.validate_timestamps',
    'opcache.revalidate_freq',
    'opcache.revalidate_path'
] as $k) {
    p($k, ini_get($k) === '' ? '(ini_get vazio)' : ini_get($k));
}
echo PHP_EOL;

echo "=== Arquivos alvo ===" . PHP_EOL;
foreach ($targets as $rel) {
    $path = __DIR__ . '/' . $rel;
    $exists = is_file($path);
    p($rel . ' exists', $exists ? 'yes' : 'no');
    if ($exists) {
        p('realpath', realpath($path));
        p('mtime', date('c', filemtime($path)));
        $md5 = @md5_file($path);
        p('md5', $md5 ?: '(erro md5_file)');
        $size = @filesize($path);
        p('size', $size !== false ? $size : '(erro filesize)');
        // Sondas de conteúdo
        $content = @file_get_contents($path);
        if ($content !== false) {
            $hasFrontSearch = strpos($content, 'alFrontSearch') !== false ? 'yes' : 'no';
            $hasSearchFronts = strpos($content, 'search_fronts') !== false ? 'yes' : 'no';
            p('contains alFrontSearch', $hasFrontSearch);
            p('contains search_fronts', $hasSearchFronts);
        }
    }
    echo str_repeat('-', 50) . PHP_EOL;
}

echo PHP_EOL . "=== Teste via HTTP (opcional) ===" . PHP_EOL;
$base = (isset($_SERVER['HTTP_HOST']) ? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : '');
if ($base && ini_get('allow_url_fopen')) {
    $url = $base . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\') . '/equipments-admin.php?__diag=1';
    p('URL', $url);
    $http = @file_get_contents($url);
    if ($http === false) {
        p('fetch', 'erro ao carregar URL');
    } else {
        p('http length', strlen($http));
        $hasMarker = strpos($http, 'alFrontSearch') !== false ? 'yes' : 'no';
        p('http contains alFrontSearch', $hasMarker);
    }
} else {
    p('HTTP test', 'pulado (sem HTTP_HOST ou allow_url_fopen=0)');
}

echo PHP_EOL . "=== Recomendações ===" . PHP_EOL;
echo "- Se 'contains alFrontSearch' for 'no' nos arquivos, as alterações não foram salvas no diretório certo.\n";
echo "- Se 'http contains alFrontSearch' for 'no' mas o arquivo local contém, o Apache pode estar servindo outro diretório/vhost ou há cache de opcode agressivo.\n";
echo "- Se OPCache estiver habilitado com validate_timestamps=0, ative (1) ou reinicie o servidor.\n";
echo "- Verifique permissões se mtime/md5 não mudarem após salvar.\n";
echo PHP_EOL;

