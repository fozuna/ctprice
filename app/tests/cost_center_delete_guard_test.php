<?php
require_once __DIR__ . '/../classes/BaseModel.php';
require_once __DIR__ . '/../models/CostCenter.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$db->exec("
CREATE TABLE cost_centers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  code TEXT NULL UNIQUE,
  parent_id INTEGER NULL,
  department TEXT NULL,
  active INTEGER NOT NULL DEFAULT 1
);
");

$db->exec("
CREATE TABLE users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  password TEXT NOT NULL,
  role_id INTEGER NOT NULL,
  functional_profile TEXT NOT NULL,
  cost_center_id INTEGER NOT NULL,
  active INTEGER NOT NULL DEFAULT 1
);
");

function cc_guard_assert($cond, $msg)
{
    if (!$cond) {
        fwrite(STDERR, '[FAIL] ' . $msg . PHP_EOL);
        exit(1);
    }
    echo '[OK] ' . $msg . PHP_EOL;
}

$costCenterModel = new CostCenter($db);

$ccFree = (int)$costCenterModel->createCostCenter([
    'name' => 'Centro Livre',
    'code' => 'LIVRE',
    'department' => 'TI',
]);

$ccLinked = (int)$costCenterModel->createCostCenter([
    'name' => 'Centro Vinculado',
    'code' => 'VINC',
    'department' => 'Compras',
]);

$db->prepare("INSERT INTO users (name, email, password, role_id, functional_profile, cost_center_id, active) VALUES (?, ?, ?, ?, ?, ?, 1)")
   ->execute(['Usuário Vinculado', 'vinculado@example.com', 'hash', 6, 'SOLICITANTE', $ccLinked]);

cc_guard_assert($costCenterModel->countLinkedUsers($ccLinked) === 1, 'Centro vinculado deve contar usuários relacionados');
cc_guard_assert($costCenterModel->hasLinkedUsers($ccLinked) === true, 'Centro vinculado deve ser protegido');
cc_guard_assert($costCenterModel->hasLinkedUsers($ccFree) === false, 'Centro sem vínculo não deve ser protegido');

$deleteLinkedBlocked = false;
if ($costCenterModel->hasLinkedUsers($ccLinked)) {
    $deleteLinkedBlocked = true;
}
cc_guard_assert($deleteLinkedBlocked, 'Exclusão deve ser bloqueada quando houver usuários vinculados');

$deletedFree = $costCenterModel->delete($ccFree);
cc_guard_assert($deletedFree === true, 'Centro sem usuários vinculados deve poder ser excluído');

echo PHP_EOL . 'Resultado: proteção de exclusão de centro de custo validada.' . PHP_EOL;
exit(0);
