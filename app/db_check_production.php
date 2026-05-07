<?php
require_once 'config/config.php';

$database = Database::getInstance();
$db = $database->getConnection();

$schema = DB_NAME;
$tables = ['goals','daily_goals','daily_production'];
foreach ($tables as $t) {
    $stmt = $db->prepare('SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
    $stmt->execute([$schema, $t]);
    $row = $stmt->fetch();
    echo $t . ': ' . ($row && isset($row['cnt']) && $row['cnt'] > 0 ? 'exists' : 'missing') . PHP_EOL;
}

$month = $_GET['month'] ?? date('Y-m');
$start = $month . '-01';
$end = date('Y-m-t', strtotime($start));

$stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM goals WHERE goal_type = 'production' AND start_date <= ? AND end_date >= ?");
$stmt->execute([$end, $start]);
$row = $stmt->fetch();
echo 'production_goals_this_month: ' . ($row['cnt'] ?? 0) . PHP_EOL;

$stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM daily_goals WHERE goal_date BETWEEN ? AND ?");
$stmt->execute([$start, $end]);
$row = $stmt->fetch();
echo 'daily_goals_rows_this_month: ' . ($row['cnt'] ?? 0) . PHP_EOL;

$stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM daily_production WHERE production_date BETWEEN ? AND ?");
$stmt->execute([$start, $end]);
$row = $stmt->fetch();
echo 'daily_production_rows_this_month: ' . ($row['cnt'] ?? 0) . PHP_EOL;
?> 
