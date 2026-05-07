<?php
// Basic unit-like tests for interview datetime and URL validation
require_once __DIR__ . '/../config/config.php';
function valid_br_datetime($s){ return !!preg_match('/^\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2}$/', $s); }
function valid_url($u){ return filter_var($u, FILTER_VALIDATE_URL) !== false; }
$cases = [
  ['2026-03-16 09:30' , false],
  ['16/03/2026 09:30' , true],
  ['16/03/2026 9:30'  , false],
];
foreach ($cases as $c) {
  echo $c[0] . ' => ' . (valid_br_datetime($c[0]) ? 'OK' : 'FAIL') . PHP_EOL;
}
$urls = ['https://meet.google.com/abc-def', 'http://', 'ftp://invalid'];
foreach ($urls as $u) {
  echo $u . ' => ' . (valid_url($u)?'OK':'FAIL') . PHP_EOL;
}
