<?php
require_once __DIR__ . '/../classes/BaseModel.php';
require_once __DIR__ . '/../models/User.php';
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
  front_id INTEGER NULL,
  cost_center_id INTEGER NOT NULL,
  active INTEGER NOT NULL DEFAULT 1,
  last_login TEXT NULL
);
");

$db->exec("
CREATE TABLE fronts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL
);
");

function ucc_assert($cond, $msg)
{
    if (!$cond) {
        fwrite(STDERR, '[FAIL] ' . $msg . PHP_EOL);
        exit(1);
    }
    echo '[OK] ' . $msg . PHP_EOL;
}

$costCenterModel = new CostCenter($db);
$userModel = new User($db);

$ccId = (int)$costCenterModel->createCostCenter([
    'name' => 'Centro Teste',
    'code' => 'CC-TESTE',
    'department' => 'Compras',
    'active' => 1,
]);

ucc_assert($ccId > 0, 'Centro de custo deve ser criado');

$userId = (int)$userModel->createUser([
    'name' => 'Usuário Solicitante',
    'email' => 'solicitante@example.com',
    'password' => '123456',
    'role_id' => 6,
    'functional_profile' => 'SOLICITANTE',
    'cost_center_id' => $ccId,
    'front_id' => null,
    'active' => 1,
]);

ucc_assert($userId > 0, 'Usuário com perfil funcional e centro de custo deve ser criado');

$user = $userModel->findById($userId);
ucc_assert(($user['functional_profile'] ?? '') === 'SOLICITANTE', 'Perfil funcional deve ser persistido');
ucc_assert((int)($user['cost_center_id'] ?? 0) === $ccId, 'Centro de custo deve ser persistido no usuário');

$list = $userModel->findWithRoleAndFront(['u.id' => $userId]);
ucc_assert(!empty($list), 'Consulta com join deve retornar o usuário');
ucc_assert(($list[0]['cost_center_name'] ?? '') === 'Centro Teste', 'Join deve retornar o nome do centro de custo');

$missingFunctionalProfileRejected = false;
try {
    $userModel->createUser([
        'name' => 'Usuário Sem Perfil',
        'email' => 'semperfil@example.com',
        'password' => '123456',
        'role_id' => 6,
        'cost_center_id' => $ccId,
        'active' => 1,
    ]);
} catch (Exception $e) {
    $missingFunctionalProfileRejected = strpos($e->getMessage(), 'Perfil funcional') !== false;
}
ucc_assert($missingFunctionalProfileRejected, 'Cadastro deve exigir perfil funcional');

$missingCostCenterRejected = false;
try {
    $userModel->createUser([
        'name' => 'Usuário Sem Centro',
        'email' => 'semcc@example.com',
        'password' => '123456',
        'role_id' => 6,
        'functional_profile' => 'APROVADOR',
        'active' => 1,
    ]);
} catch (Exception $e) {
    $missingCostCenterRejected = strpos($e->getMessage(), 'Centro de custo') !== false;
}
ucc_assert($missingCostCenterRejected, 'Cadastro deve exigir centro de custo');

echo PHP_EOL . 'Resultado: perfis funcionais e vínculo com centro de custo validados.' . PHP_EOL;
exit(0);
