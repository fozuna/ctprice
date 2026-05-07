<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__DIR__);

$errors = [];
set_error_handler(function ($errno, $errstr) use (&$errors) {
    $errors[] = ['errno' => $errno, 'errstr' => $errstr];
    return false;
});

unset($_SERVER['HTTPS'], $_SERVER['SERVER_PORT']);

require_once $root . '/config/config.php';

restore_error_handler();

$failed = false;

function assert_equal($expected, $actual, $message, &$failed)
{
    if ((string)$expected !== (string)$actual) {
        fwrite(STDERR, '[FAIL] ' . $message . ' (esperado=' . var_export($expected, true) . ', atual=' . var_export($actual, true) . ')' . PHP_EOL);
        $failed = true;
    } else {
        echo '[OK] ' . $message . PHP_EOL;
    }
}

assert_equal(PHP_SESSION_ACTIVE, session_status(), 'Sessão deve estar ativa após carregar config', $failed);
assert_equal(SESSION_NAME, session_name(), 'session_name deve corresponder a SESSION_NAME', $failed);
assert_equal(SESSION_LIFETIME, ini_get('session.gc_maxlifetime'), 'session.gc_maxlifetime deve corresponder a SESSION_LIFETIME', $failed);
assert_equal(SESSION_LIFETIME, ini_get('session.cookie_lifetime'), 'session.cookie_lifetime deve corresponder a SESSION_LIFETIME', $failed);
assert_equal('1', ini_get('session.cookie_httponly'), 'session.cookie_httponly deve estar ativado', $failed);

foreach ($errors as $e) {
    if (strpos($e['errstr'], 'A session is active. You cannot change the session module\'s ini settings at this time') !== false) {
        fwrite(STDERR, '[FAIL] Foi emitido warning de ini_set com sessão ativa: ' . $e['errstr'] . PHP_EOL);
        $failed = true;
    }
}

if ($failed) {
    echo PHP_EOL . 'Resultado: FALHA na configuração de sessão.' . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Resultado: configuração de sessão ok.' . PHP_EOL;
exit(0);

