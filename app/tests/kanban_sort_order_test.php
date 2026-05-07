<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../includes/kanban_sort_order.php';

function assert_true($cond, $msg) {
    if (!$cond) {
        fwrite(STDERR, '[FAIL] ' . $msg . PHP_EOL);
        exit(1);
    }
    echo '[OK] ' . $msg . PHP_EOL;
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE job_candidates (id INTEGER PRIMARY KEY, stage_id INTEGER, created_at TEXT)");
$pdo->exec("INSERT INTO job_candidates (id, stage_id, created_at) VALUES (1,3,'2026-01-01'),(2,3,'2026-01-02'),(3,3,'2026-01-03'),(4,2,'2026-01-04')");

kanbanEnsureSortOrder($pdo);
kanbanEnsureSortOrder($pdo);

$cols = $pdo->query("PRAGMA table_info(job_candidates)")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_map(fn($r) => $r['name'], $cols);
assert_true(in_array('sort_order', $colNames, true), 'sort_order deve existir');

$pdo->exec("UPDATE job_candidates SET sort_order = 99 WHERE stage_id=3");
kanbanApplySortOrder($pdo, 3, [3, 1]);

$rows = $pdo->query("SELECT id, stage_id, sort_order FROM job_candidates ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$byId = [];
foreach ($rows as $r) { $byId[(int)$r['id']] = $r; }

assert_true((int)$byId[3]['sort_order'] === 1, 'stage_id=3 id=3 deve virar sort_order=1');
assert_true((int)$byId[1]['sort_order'] === 2, 'stage_id=3 id=1 deve virar sort_order=2');
assert_true($byId[2]['sort_order'] === null, 'stage_id=3 id=2 não listado deve virar NULL');
assert_true($byId[4]['sort_order'] === null, 'stage_id=2 não deve ser afetado');

kanbanApplySortOrder($pdo, 3, [2, 1, 3]);
$rows2 = $pdo->query("SELECT id, sort_order FROM job_candidates WHERE stage_id=3 ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$order = array_map(fn($r) => (int)$r['id'], $rows2);
assert_true($order === [2,1,3], 'Aplicar ordem completa deve persistir sequência');

echo PHP_EOL . 'Todos os testes passaram.' . PHP_EOL;
exit(0);

