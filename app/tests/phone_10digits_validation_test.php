<?php
// Validation cases for 10-digit rule and special characters rejection
function digits($s){ return preg_replace('/\D+/', '', (string)$s); }
function valid10($s){ return (bool)preg_match('/^\d{10}$/', digits($s)); }
$cases = [
  ['6791234567', true],       // DDD 67 + 8 digitos (exemplo fixo)
  ['67 91234567', true],      // com espaco
  ['(67)9123-4567', true],    // com mascara
  ['679123456', false],       // 9 digitos
  ['679123456789', false],    // 12 digitos
  ['abc6791234', false],      // letras
  ['67-9123-4567', true],     // com hifens
  ['', false]
];
foreach ($cases as $c) {
  echo $c[0].' => '.(valid10($c[0])===$c[1]?'OK':'FAIL').PHP_EOL;
}
