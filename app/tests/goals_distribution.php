<?php
// Execução: php tests/goals_distribution.php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../classes/BaseModel.php';
require_once __DIR__ . '/../models/Goal.php';
require_once __DIR__ . '/../classes/AuditLog.php';

function assert_close($a, $b, $eps, $msg) {
    if (abs($a - $b) > $eps) {
        fwrite(STDERR, "[FAIL] $msg (got: $a expected: $b ± $eps)\n");
        exit(1);
    } else {
        echo "[OK] $msg\n";
    }
}
function assert_true($cond, $msg) {
    if (!$cond) {
        fwrite(STDERR, "[FAIL] $msg\n");
        exit(1);
    } else {
        echo "[OK] $msg\n";
    }
}
function is_sunday($date) { return intval((new DateTime($date))->format('w')) === 0; }

// Banco em memória
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Schema mínimo
$pdo->exec("
CREATE TABLE goals (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  front_id INTEGER NOT NULL DEFAULT 1,
  client_id INTEGER NOT NULL DEFAULT 1,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  total_goal DECIMAL(12,2) NOT NULL,
  goal_type VARCHAR(20) DEFAULT 'production',
  unit VARCHAR(20) DEFAULT 'toneladas',
  description TEXT NULL,
  created_by INT NULL,
  updated_by INT NULL
);
CREATE TABLE daily_goals (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  goal_id INT NOT NULL,
  goal_date DATE NOT NULL,
  week_number INT NOT NULL,
  week_start_date DATE NOT NULL,
  daily_goal DECIMAL(12,2) NOT NULL,
  weekly_goal DECIMAL(12,2) NOT NULL,
  monthly_goal DECIMAL(12,2) NOT NULL
);
CREATE TABLE audit_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  table_name VARCHAR(50) NOT NULL,
  record_id INT NOT NULL,
  action VARCHAR(10) NOT NULL,
  old_values TEXT NULL,
  new_values TEXT NULL,
  user_id INT NOT NULL DEFAULT 1,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE holidays (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  holiday_date DATE NOT NULL,
  description TEXT
);
");

$goalModel = new Goal($pdo);

// Caso 1: 02/03 a 29/03/2026 total 19.000 (entrega) => 4 semanas x 4.750
$goalId1 = $goalModel->create([
    'front_id' => 1,
    'client_id' => 1,
    'start_date' => '2026-03-02',
    'end_date' => '2026-03-29',
    'total_goal' => 19000.00,
    'goal_type' => 'delivery',
    'created_by' => 1
]);
$weeks1 = $pdo->query("SELECT week_start_date, SUM(daily_goal) as s FROM daily_goals WHERE goal_id = $goalId1 GROUP BY week_start_date ORDER BY week_start_date")->fetchAll(PDO::FETCH_ASSOC);
assert_true(count($weeks1) === 4, "Período 02/03-29/03 cobre 4 semanas");
foreach ($weeks1 as $w) {
    assert_close((float)$w['s'], 4750.00, 0.5, "Soma semanal = 4.750");
}
$rows1 = $pdo->query("SELECT goal_date, daily_goal FROM daily_goals WHERE goal_id = $goalId1 ORDER BY goal_date")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows1 as $r) {
    if (is_sunday($r['goal_date'])) {
        assert_true((float)$r['daily_goal'] == 0.0, "Domingo deve ser 0");
    }
}
$sum1 = array_sum(array_map(fn($r)=> (float)$r['daily_goal'], $rows1));
assert_close($sum1, 19000.00, 0.5, "Soma total 19.000");

// Caso 2: Com feriado em 19/03/2026 (quinta) — manter 4.750 na semana
$pdo->exec("INSERT INTO holidays (holiday_date, description) VALUES ('2026-03-19', 'Feriado teste')");
$goalId2 = $goalModel->create([
    'front_id' => 1,
    'client_id' => 1,
    'start_date' => '2026-03-02',
    'end_date' => '2026-03-29',
    'total_goal' => 19000.00,
    'goal_type' => 'delivery',
    'created_by' => 1
]);
$rows2 = $pdo->query("SELECT goal_date, daily_goal, week_start_date FROM daily_goals WHERE goal_id = $goalId2 ORDER BY goal_date")->fetchAll(PDO::FETCH_ASSOC);
$sumWeeks = [];
foreach ($rows2 as $r) {
    $ws = $r['week_start_date'];
    if (!isset($sumWeeks[$ws])) $sumWeeks[$ws] = 0.0;
    $sumWeeks[$ws] += (float)$r['daily_goal'];
    if ($r['goal_date'] === '2026-03-19') {
        assert_true((float)$r['daily_goal'] == 0.0, "Feriado 19/03 deve ser 0");
    }
}
foreach ($sumWeeks as $ws => $s) {
    assert_close($s, 4750.00, 0.5, "Semana $ws mantém total 4.750 mesmo com feriado");
}
$sum2 = array_sum(array_map(fn($r)=> (float)$r['daily_goal'], $rows2));
assert_close($sum2, 19000.00, 0.5, "Soma total 19.000 com feriado");

// Caso 3: Meta atravessando fronteira de semana — total se mantém
$goalId3 = $goalModel->create([
    'front_id' => 1,
    'client_id' => 1,
    'start_date' => '2026-03-01',
    'end_date' => '2026-03-31',
    'total_goal' => 6200.00,
    'goal_type' => 'delivery',
    'created_by' => 1
]);
$sum3 = (float)$pdo->query("SELECT SUM(daily_goal) FROM daily_goals WHERE goal_id = $goalId3")->fetchColumn();
assert_close($sum3, 6200.00, 0.5, "Soma total mensal preservada em meta ampla");

echo PHP_EOL . "Todos os testes de metas de entrega passaram." . PHP_EOL;
exit(0);
