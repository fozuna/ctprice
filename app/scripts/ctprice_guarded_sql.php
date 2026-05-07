<?php

function ctprice_now(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}

function ctprice_fail(string $message, int $code = 1): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

function ctprice_load_env(string $envPath): void
{
    if (!is_file($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $name = trim($parts[0]);
        $value = trim($parts[1]);
        if ($name === '') {
            continue;
        }
        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
        }
        if (!array_key_exists($name, $_SERVER)) {
            $_SERVER[$name] = $value;
        }
        if (getenv($name) === false) {
            putenv($name . '=' . $value);
        }
    }
}

function ctprice_connect(array $cfg): PDO
{
    foreach (['host', 'name', 'user'] as $k) {
        if (empty($cfg[$k])) {
            ctprice_fail('Configuração ausente: ' . $k);
        }
    }

    $host = (string)$cfg['host'];
    $port = (string)($cfg['port'] ?? '3306');
    $name = (string)$cfg['name'];
    $user = (string)$cfg['user'];
    $pass = (string)($cfg['pass'] ?? '');
    $charset = (string)($cfg['charset'] ?? 'utf8mb4');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ];
    return new PDO($dsn, $user, $pass, $options);
}

function ctprice_statement_returns_rows(string $stmt): bool
{
    return (bool)preg_match('/^\s*(SHOW|EXPLAIN|SELECT|DESCRIBE)\b/i', $stmt);
}

function ctprice_current_db(PDO $db): string
{
    $st = $db->query('SELECT DATABASE()');
    $v = $st->fetchColumn();
    return $v !== false ? (string)$v : '';
}

function ctprice_log_line(string $logFile, array $entry): void
{
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($line)) {
        $line = '{"error":"json_encode_failed"}';
    }
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
}

function ctprice_strip_sql_comments(string $sql): string
{
    $sql = preg_replace('~/\*[\s\S]*?\*/~', '', $sql);
    $lines = preg_split('/\R/', $sql);
    $out = [];
    foreach ($lines as $line) {
        $trim = ltrim($line);
        if ($trim === '' || str_starts_with($trim, '--') || str_starts_with($trim, '#')) {
            continue;
        }
        $out[] = $line;
    }
    return implode("\n", $out);
}

function ctprice_split_statements(string $sql): array
{
    $sql = ctprice_strip_sql_comments($sql);
    $stmts = [];
    $buf = '';
    $len = strlen($sql);
    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $prev = $i > 0 ? $sql[$i - 1] : '';

        if ($ch === "'" && !$inDouble && !$inBacktick && $prev !== "\\") {
            $inSingle = !$inSingle;
        } elseif ($ch === '"' && !$inSingle && !$inBacktick && $prev !== "\\") {
            $inDouble = !$inDouble;
        } elseif ($ch === '`' && !$inSingle && !$inDouble) {
            $inBacktick = !$inBacktick;
        }

        if ($ch === ';' && !$inSingle && !$inDouble && !$inBacktick) {
            $stmt = trim($buf);
            if ($stmt !== '') {
                $stmts[] = $stmt;
            }
            $buf = '';
            continue;
        }
        $buf .= $ch;
    }

    $stmt = trim($buf);
    if ($stmt !== '') {
        $stmts[] = $stmt;
    }
    return $stmts;
}

function ctprice_statement_guards(string $stmt, string $expectedDb): array
{
    $errors = [];
    $normalized = preg_replace('/\s+/', ' ', trim($stmt));
    $upper = strtoupper($normalized);

    if (preg_match('/\bUSE\b/i', $normalized)) {
        if (!preg_match('/^USE\s+`?' . preg_quote($expectedDb, '/') . '`?\s*$/i', $normalized)) {
            $errors[] = 'USE não permitido (apenas USE ' . $expectedDb . ')';
        }
    }

    if (preg_match('/\bCREATE\s+(TRIGGER|PROCEDURE|FUNCTION|EVENT)\b/i', $normalized)) {
        $errors[] = 'Criação de rotinas/triggers não permitida neste executor';
    }

    $forbiddenSchemas = ['information_schema', 'mysql', 'performance_schema', 'sys', 'appmadeplant'];
    foreach ($forbiddenSchemas as $s) {
        if (preg_match('/\b' . preg_quote($s, '/') . '\b\s*\./i', $normalized)) {
            $errors[] = 'Referência explícita a schema proibido: ' . $s;
        }
    }

    if (preg_match_all('/\b(from|join|update|into|table|references)\s+`?([a-z0-9_]+)`?\s*\./i', $normalized, $m)) {
        foreach ($m[2] as $schema) {
            if (strcasecmp($schema, $expectedDb) !== 0) {
                $errors[] = 'Referência a schema diferente de ' . $expectedDb . ': ' . $schema;
            }
        }
    }

    if (str_contains($upper, 'DROP DATABASE') || str_contains($upper, 'CREATE DATABASE')) {
        $errors[] = 'Comando de DATABASE proibido';
    }

    return $errors;
}

function ctprice_usage(): void
{
    $msg = "Uso:\n" .
        "  php app/scripts/ctprice_guarded_sql.php --file <sql_file> [--expect-db <db>] [--apply] [--log <log_file>]\n" .
        "    [--db <db>] [--host <host>] [--port <port>] [--user <user>] [--pass <pass>] [--charset <charset>]\n" .
        "\n" .
        "Por padrão, roda em dry-run (sem executar). Use --apply para executar.\n";
    fwrite(STDOUT, $msg);
}

$args = $argv;
array_shift($args);
$opts = [
    'file' => null,
    'expect-db' => null,
    'apply' => false,
    'log' => null,
    'db' => null,
    'host' => null,
    'port' => null,
    'user' => null,
    'pass' => null,
    'charset' => null,
    'bootstrap-db' => false,
];

for ($i = 0; $i < count($args); $i++) {
    $a = $args[$i];
    if (str_starts_with($a, '--pass=')) {
        $opts['pass'] = substr($a, strlen('--pass='));
        continue;
    }
    if (str_starts_with($a, '--user=')) {
        $opts['user'] = substr($a, strlen('--user='));
        continue;
    }
    if (str_starts_with($a, '--db=')) {
        $opts['db'] = substr($a, strlen('--db='));
        continue;
    }
    if (str_starts_with($a, '--host=')) {
        $opts['host'] = substr($a, strlen('--host='));
        continue;
    }
    if (str_starts_with($a, '--port=')) {
        $opts['port'] = substr($a, strlen('--port='));
        continue;
    }
    if (str_starts_with($a, '--charset=')) {
        $opts['charset'] = substr($a, strlen('--charset='));
        continue;
    }
    if ($a === '--apply') {
        $opts['apply'] = true;
        continue;
    }
    if ($a === '--bootstrap-db') {
        $opts['bootstrap-db'] = true;
        continue;
    }
    if ($a === '--file' && isset($args[$i + 1])) {
        $opts['file'] = $args[++$i];
        continue;
    }
    if ($a === '--expect-db' && isset($args[$i + 1])) {
        $opts['expect-db'] = $args[++$i];
        continue;
    }
    if ($a === '--log' && isset($args[$i + 1])) {
        $opts['log'] = $args[++$i];
        continue;
    }
    if ($a === '--db' && isset($args[$i + 1])) {
        $opts['db'] = $args[++$i];
        continue;
    }
    if ($a === '--host' && isset($args[$i + 1])) {
        $opts['host'] = $args[++$i];
        continue;
    }
    if ($a === '--port' && isset($args[$i + 1])) {
        $opts['port'] = $args[++$i];
        continue;
    }
    if ($a === '--user' && isset($args[$i + 1])) {
        $opts['user'] = $args[++$i];
        continue;
    }
    if ($a === '--pass' && isset($args[$i + 1])) {
        $opts['pass'] = $args[++$i];
        continue;
    }
    if ($a === '--charset' && isset($args[$i + 1])) {
        $opts['charset'] = $args[++$i];
        continue;
    }
    if ($a === '--help' || $a === '-h') {
        ctprice_usage();
        exit(0);
    }
    ctprice_fail('Argumento inválido: ' . $a);
}

$root = dirname(__DIR__, 2);
$envPath = $root . DIRECTORY_SEPARATOR . '.env';
ctprice_load_env($envPath);

$dbName = (string)($opts['db'] ?? ($_ENV['CTPRICE_DB_NAME'] ?? ($_ENV['DB_NAME'] ?? '')));
if ($dbName === '') {
    ctprice_fail('DB_NAME não definido no .env');
}

$expectedDb = (string)($opts['expect-db'] ?? $dbName);

$sqlFile = $opts['file'];
if (!$sqlFile) {
    ctprice_usage();
    ctprice_fail('Informe --file <sql_file>');
}

if (!str_starts_with($sqlFile, DIRECTORY_SEPARATOR) && !preg_match('/^[A-Za-z]:\\\\/', $sqlFile)) {
    $sqlFile = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sqlFile);
}

if (!is_file($sqlFile)) {
    ctprice_fail('Arquivo SQL não encontrado: ' . $sqlFile);
}

$logFile = $opts['log'] ? (string)$opts['log'] : ($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'ctprice_db_ops.jsonl');
if (!str_starts_with($logFile, DIRECTORY_SEPARATOR) && !preg_match('/^[A-Za-z]:\\\\/', $logFile)) {
    $logFile = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $logFile);
}

$cfg = [
    'host' => (string)($opts['host'] ?? ($_ENV['CTPRICE_DB_HOST'] ?? ($_ENV['DB_HOST'] ?? '127.0.0.1'))),
    'port' => (string)($opts['port'] ?? ($_ENV['CTPRICE_DB_PORT'] ?? ($_ENV['DB_PORT'] ?? '3306'))),
    'name' => $dbName,
    'user' => (string)($opts['user'] ?? ($_ENV['CTPRICE_DB_USER'] ?? ($_ENV['DB_USER'] ?? ''))),
    'pass' => (string)($opts['pass'] ?? ($_ENV['CTPRICE_DB_PASS'] ?? ($_ENV['DB_PASS'] ?? ''))),
    'charset' => (string)($opts['charset'] ?? ($_ENV['CTPRICE_DB_CHARSET'] ?? ($_ENV['DB_CHARSET'] ?? 'utf8mb4'))),
];

try {
    $db = ctprice_connect($cfg);
} catch (Throwable $e) {
    $logFile = $opts['log'] ? (string)$opts['log'] : ($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'ctprice_db_ops.jsonl');
    if (!str_starts_with($logFile, DIRECTORY_SEPARATOR) && !preg_match('/^[A-Za-z]:\\\\/', $logFile)) {
        $logFile = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $logFile);
    }
    $errMsg = $e->getMessage();
    ctprice_log_line($logFile, [
        'ts' => ctprice_now(),
        'event' => 'connect_failed',
        'host' => $cfg['host'],
        'port' => $cfg['port'],
        'db' => $cfg['name'],
        'user' => $cfg['user'],
        'error' => $errMsg,
        'file' => $sqlFile,
    ]);

    $isUnknownDb = str_contains($errMsg, "Unknown database '") || str_contains($errMsg, 'SQLSTATE[HY000] [1049]');
    if ($opts['bootstrap-db'] && $isUnknownDb) {
        try {
            $dsnNoDb = "mysql:host={$cfg['host']};port={$cfg['port']};charset={$cfg['charset']}";
            $db0 = new PDO($dsnNoDb, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ]);
            $db0->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $expectedDb) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            ctprice_log_line($logFile, [
                'ts' => ctprice_now(),
                'event' => 'bootstrap_db_created',
                'db' => $expectedDb,
            ]);
            $cfg['name'] = $expectedDb;
            $db = ctprice_connect($cfg);
        } catch (Throwable $e2) {
            ctprice_log_line($logFile, [
                'ts' => ctprice_now(),
                'event' => 'bootstrap_db_failed',
                'db' => $expectedDb,
                'error' => $e2->getMessage(),
            ]);
            ctprice_fail('Falha ao criar/conectar no banco alvo via bootstrap.');
        }
    } else {
        ctprice_fail('Falha ao conectar no banco alvo.');
    }
}
$current = ctprice_current_db($db);
if (strcasecmp($current, $expectedDb) !== 0) {
    ctprice_log_line($logFile, [
        'ts' => ctprice_now(),
        'event' => 'preflight_failed',
        'expected_db' => $expectedDb,
        'current_db' => $current,
        'file' => $sqlFile,
    ]);
    ctprice_fail('Contexto inválido: conexão está em "' . $current . '", esperado "' . $expectedDb . '"');
}

$raw = file_get_contents($sqlFile);
if (!is_string($raw) || trim($raw) === '') {
    ctprice_fail('Arquivo SQL vazio');
}

$statements = ctprice_split_statements($raw);
if ($statements === []) {
    ctprice_fail('Nenhuma statement SQL encontrada');
}

ctprice_log_line($logFile, [
    'ts' => ctprice_now(),
    'event' => 'start',
    'apply' => $opts['apply'],
    'expected_db' => $expectedDb,
    'current_db' => $current,
    'file' => $sqlFile,
    'statements' => count($statements),
]);

$executed = 0;
foreach ($statements as $idx => $stmt) {
    $current = ctprice_current_db($db);
    if (strcasecmp($current, $expectedDb) !== 0) {
        ctprice_log_line($logFile, [
            'ts' => ctprice_now(),
            'event' => 'context_drift',
            'expected_db' => $expectedDb,
            'current_db' => $current,
            'index' => $idx,
        ]);
        ctprice_fail('Contexto mudou durante a execução; abortando.');
    }

    $guards = ctprice_statement_guards($stmt, $expectedDb);
    if ($guards !== []) {
        ctprice_log_line($logFile, [
            'ts' => ctprice_now(),
            'event' => 'statement_blocked',
            'index' => $idx,
            'reasons' => $guards,
            'sql' => $stmt,
        ]);
        ctprice_fail('Statement bloqueada por segurança (index ' . $idx . '): ' . implode('; ', $guards));
    }

    if (!$opts['apply']) {
        ctprice_log_line($logFile, [
            'ts' => ctprice_now(),
            'event' => 'dry_run',
            'index' => $idx,
            'sql' => $stmt,
        ]);
        continue;
    }

    $t0 = microtime(true);
    try {
        $rows = null;
        if (ctprice_statement_returns_rows($stmt)) {
            $q = $db->query($stmt);
            $all = $q->fetchAll();
            $rows = is_array($all) ? count($all) : 0;
        } else {
            $db->exec($stmt);
        }
        $ms = (int)round((microtime(true) - $t0) * 1000);
        $executed++;
        ctprice_log_line($logFile, [
            'ts' => ctprice_now(),
            'event' => 'executed',
            'index' => $idx,
            'ms' => $ms,
            'rows' => $rows,
            'sql' => $stmt,
        ]);
    } catch (Throwable $e) {
        $ms = (int)round((microtime(true) - $t0) * 1000);
        ctprice_log_line($logFile, [
            'ts' => ctprice_now(),
            'event' => 'error',
            'index' => $idx,
            'ms' => $ms,
            'sql' => $stmt,
            'error' => $e->getMessage(),
        ]);
        ctprice_fail('Erro ao executar statement (index ' . $idx . '): ' . $e->getMessage());
    }
}

ctprice_log_line($logFile, [
    'ts' => ctprice_now(),
    'event' => 'finish',
    'apply' => $opts['apply'],
    'executed' => $executed,
    'file' => $sqlFile,
]);

echo "OK\n";

