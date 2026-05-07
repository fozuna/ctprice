<?php

$root = dirname(__DIR__);
require_once $root . '/config/autoload.php';

$failed = 0;

function role_const_assert(bool $condition, string $message, int &$failed): void
{
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        $failed++;
        return;
    }

    echo "[OK] {$message}\n";
}

role_const_assert(defined('ROLE_ADMIN'), 'ROLE_ADMIN deve estar definida no bootstrap', $failed);
role_const_assert(defined('ROLE_COORD_RH'), 'ROLE_COORD_RH deve estar definida no bootstrap', $failed);
role_const_assert(defined('ROLE_COMPRAS'), 'ROLE_COMPRAS deve estar definida no bootstrap', $failed);
role_const_assert(ROLE_COMPRAS === 6, 'ROLE_COMPRAS deve usar o valor 6', $failed);

exit($failed === 0 ? 0 : 1);
