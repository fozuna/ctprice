<?php
if (file_exists(__DIR__ . '/config/config.php')) {
    require_once __DIR__ . '/config/config.php';
}
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/BaseModel.php';
try {
    $db = Database::getInstance()->getConnection();
    $schemaRow = $db->query('SELECT DATABASE() as db')->fetch();
    $schema = $schemaRow['db'];
    echo 'schema: ' . $schema . "\n";
    $rolesDefined = [
        'ROLE_ADMIN' => defined('ROLE_ADMIN'),
        'ROLE_SUPERVISOR' => defined('ROLE_SUPERVISOR'),
        'ROLE_LIDER' => defined('ROLE_LIDER'),
        'ROLE_OPERADOR' => defined('ROLE_OPERADOR'),
        'ROLE_COORD_RH' => defined('ROLE_COORD_RH')
    ];
    foreach ($rolesDefined as $key => $ok) {
        echo $key . ': ' . ($ok ? 'defined' : 'missing') . "\n";
    }
    $tables = ['goals','daily_goals','daily_production','daily_delivery','clients','fronts','equipments','fases'];
    foreach ($tables as $t) {
        $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema=? AND table_name=?');
        $stmt->execute([$schema, $t]);
        $row = $stmt->fetch();
        echo $t . ': ' . ($row['cnt'] > 0 ? 'exists' : 'missing') . "\n";
    }
    $month = date('Y-m');
    $start = $month . '-01';
    $end = date('Y-m-t', strtotime($start));
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM goals WHERE goal_type='production' AND start_date <= ? AND end_date >= ?");
    $stmt->execute([$end, $start]);
    $row = $stmt->fetch();
    echo 'production_goals_this_month: ' . ($row['cnt'] ?? 0) . "\n";
    $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM daily_goals WHERE goal_date BETWEEN ? AND ?');
    $stmt->execute([$start, $end]);
    $row = $stmt->fetch();
    echo 'daily_goals_rows_this_month: ' . ($row['cnt'] ?? 0) . "\n";
    $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM daily_production WHERE production_date BETWEEN ? AND ?');
    $stmt->execute([$start, $end]);
    $row = $stmt->fetch();
    echo 'daily_production_rows_this_month: ' . ($row['cnt'] ?? 0) . "\n";
    $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM daily_delivery WHERE delivery_date BETWEEN ? AND ?');
    $stmt->execute([$start, $end]);
    $row = $stmt->fetch();
    echo 'daily_delivery_rows_this_month: ' . ($row['cnt'] ?? 0) . "\n";
    echo "DONE\n";
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
