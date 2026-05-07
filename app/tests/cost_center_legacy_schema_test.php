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
  active INTEGER NOT NULL DEFAULT 1
);
");

function cc_legacy_assert($cond, $msg)
{
    if (!$cond) {
        fwrite(STDERR, '[FAIL] ' . $msg . PHP_EOL);
        exit(1);
    }

    echo '[OK] ' . $msg . PHP_EOL;
}

$costCenterModel = new CostCenter($db);

$parentId = (int)$costCenterModel->createCostCenter([
    'name' => 'Centro Pai',
    'code' => 'PAI',
    'department' => 'ADM',
]);

$childId = (int)$costCenterModel->createCostCenter([
    'name' => 'Centro Filho',
    'code' => 'FILHO',
    'department' => 'TI',
    'parent_id' => $parentId,
]);

cc_legacy_assert($costCenterModel->hasUserCostCenterSchema() === false, 'Schema legado sem users.cost_center_id deve ser detectado');
cc_legacy_assert($costCenterModel->countLinkedUsers($childId) === 0, 'Contagem de usuários deve retornar zero sem a coluna cost_center_id');

$rows = $costCenterModel->findAllWithStats();
cc_legacy_assert(count($rows) === 2, 'findAllWithStats deve listar centros de custo sem falhar em schema legado');

$childRow = null;
foreach ($rows as $row) {
    if ((int)$row['id'] === $childId) {
        $childRow = $row;
        break;
    }
}

cc_legacy_assert(is_array($childRow), 'Centro filho deve estar presente no retorno');
cc_legacy_assert(($childRow['parent_name'] ?? null) === 'Centro Pai', 'Centro pai deve ser resolvido corretamente');
cc_legacy_assert((int)($childRow['linked_users'] ?? -1) === 0, 'linked_users deve retornar zero em schema legado');

echo PHP_EOL . 'Resultado: compatibilidade com schema legado validada.' . PHP_EOL;
exit(0);
