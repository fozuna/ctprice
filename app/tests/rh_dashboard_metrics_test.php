<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/rh_dashboard_metrics.php';

$db = Database::getInstance()->getConnection();
$t0 = microtime(true);
$metrics = rhBuildDashboardMetrics($db, date('Y-m-01'), date('Y-m-d'));
$t1 = microtime(true);

$elapsed = (int)round(($t1 - $t0) * 1000);
echo "ok: metrics carregadas em {$elapsed}ms\n";
if ($elapsed > 3000) { echo "warn: >3s\n"; }
echo "open_vacancies=" . ($metrics['recruitment']['open_vacancies'] ?? 'n/a') . "\n";
echo "candidates_in_process=" . ($metrics['recruitment']['candidates_in_process'] ?? 'n/a') . "\n";
echo "total_hired=" . ($metrics['recruitment']['total_hired'] ?? 'n/a') . "\n";
exit(0);
