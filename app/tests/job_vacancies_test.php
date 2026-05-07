<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../classes/BaseModel.php';
require_once __DIR__ . '/../models/JobVacancy.php';

function assert_true_hr($cond, $msg) {
    if (!$cond) {
        fwrite(STDERR, "[FAIL] {$msg}\n");
        exit(1);
    } else {
        echo "[OK] {$msg}\n";
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("
CREATE TABLE job_vacancies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    requirements TEXT NOT NULL,
    salary REAL NULL,
    benefits TEXT NULL,
    location TEXT NOT NULL,
    contract_type TEXT NOT NULL,
    department TEXT NOT NULL,
    status TEXT NOT NULL,
    valid_until TEXT NOT NULL,
    created_by INTEGER NOT NULL,
    updated_by INTEGER NULL
);
");

class JobVacancyTestModel extends JobVacancy {
    public function __construct($db) {
        $this->conn = $db;
        $this->table = 'job_vacancies';
    }
}

$model = new JobVacancyTestModel($pdo);

$id = $model->create([
    'title' => 'Coordenador de Campo',
    'description' => 'Responsável pela coordenação de equipes.',
    'requirements' => 'Experiência com gestão de equipes.',
    'salary' => 5000.00,
    'benefits' => 'Plano de saúde',
    'location' => 'Dourados - MS',
    'contract_type' => 'CLT',
    'department' => 'Operações',
    'status' => 'ATIVA',
    'valid_until' => '2026-12-31',
    'created_by' => 1,
    'updated_by' => null
]);

assert_true_hr($id > 0, 'Criação de vaga deve retornar ID válido');

$one = $model->findById($id);
assert_true_hr($one !== false && $one['title'] === 'Coordenador de Campo', 'findById deve retornar a vaga criada');

$model->update($id, ['status' => 'INATIVA']);
$one = $model->findById($id);
assert_true_hr($one['status'] === 'INATIVA', 'Atualização de status deve funcionar');

echo PHP_EOL . "Todos os testes de vagas de RH passaram." . PHP_EOL;
exit(0);

