<?php
require __DIR__ . '/app/core/bootstrap.php';
$base = \App\Core\Config::app()['base_url'] ?? '';
header('Location: ' . ($base !== '' ? $base . '/' : '/'));
exit;