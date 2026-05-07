<?php
// Simple usability presence test for RH Dashboard badges
$url = 'http://localhost/app/rh-dashboard.php';
echo "Testing badges presence on $url\n";
$ctx = stream_context_create(['http' => ['method' => 'GET', 'header' => "Cookie: BDO_SESSION=test\r\n"]]);
$html = @file_get_contents($url, false, $ctx);
if (!$html) { echo "Unable to fetch page. Ensure dev server and session.\n"; exit(1); }
$checks = [
  'toggleBadges' => strpos($html, 'id="toggleBadges"') !== false,
  'expAvgHire' => strpos($html, 'id="expAvgHire"') !== false,
  'expTurnover' => strpos($html, 'id="expTurnover"') !== false,
  'expAbs' => strpos($html, 'id="expAbs"') !== false,
  'badgeButtons' => substr_count($html, 'aria-controls="exp') >= 3
];
$ok = array_sum(array_map(fn($b)=>$b?1:0, $checks)) === count($checks);
foreach ($checks as $k=>$v) echo sprintf("[%s] %s\n", $v?'OK':'FAIL', $k);
echo $ok ? "All checks passed.\n" : "Some checks failed.\n";
exit($ok?0:1);
