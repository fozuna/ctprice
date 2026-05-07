<?php
// Simple tests for phone sanitization and validation rule (11 digits)
function sanitizeDigits($s){ return preg_replace('/\D+/', '', (string)$s); }
function isValidPhone($s){ $d = sanitizeDigits($s); return (bool)preg_match('/^\d{11}$/', $d); }
$cases = [
  ['67912345678', true],
  ['67 91234-5678', true],
  ['(67) 91234-5678', true],
  ['6791234567', false],
  ['abcdef', false],
  ['', false],
];
foreach ($cases as $c) {
  $ok = isValidPhone($c[0]) === $c[1];
  echo $c[0].' => '.($ok?'OK':'FAIL').PHP_EOL;
}
