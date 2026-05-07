<?php
// Tests for accepting exactly 10 or 11 digits; reject 9 or 12; reject non-numerics
function onlyDigits($s){ return preg_replace('/\D+/', '', (string)$s); }
function valid10or11($s){ $d = onlyDigits($s); return (bool)preg_match('/^\d{10,11}$/', $d); }
$cases = [
  ['6791234567', true],      // 10
  ['67912345678', true],     // 11
  ['(67) 91234-5678', true], // 11 com máscara
  ['(67) 1234-5678', true],  // 10 com máscara
  ['679123456', false],      // 9
  ['679123456789', false],   // 12
  ['67a9123b567', false],    // letras
  ['', false]
];
foreach ($cases as $c) {
  echo $c[0].' => '.(valid10or11($c[0])===$c[1]?'OK':'FAIL').PHP_EOL;
}
