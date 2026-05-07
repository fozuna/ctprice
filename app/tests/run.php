<?php
// Testes simples de autenticação e sessão (CLI)
// Execução: php tests/run.php

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Isolar sessão de testes
@session_name('APPSESSID_TEST');
@session_id('');
@session_start();

require_once __DIR__ . '/../classes/BaseModel.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../classes/Auth.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Criar schema mínimo
$pdo->exec("
CREATE TABLE users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT,
  email TEXT UNIQUE,
  password TEXT,
  role_id INTEGER DEFAULT 1,
  functional_profile TEXT DEFAULT 'SOLICITANTE',
  front_id INTEGER DEFAULT NULL,
  cost_center_id INTEGER DEFAULT 1,
  active INTEGER DEFAULT 1,
  last_login TEXT DEFAULT NULL
);
");

function assert_true($cond, $msg) {
    if (!$cond) {
        fwrite(STDERR, '[FAIL] ' . $msg . PHP_EOL);
        exit(1);
    } else {
        echo '[OK] ' . $msg . PHP_EOL;
    }
}

// Inserir usuários
$email1 = 'ok@example.com';
$pass1 = 'secret123';
$hash1 = password_hash($pass1, PASSWORD_DEFAULT);
$pdo->prepare('INSERT INTO users (name,email,password,active) VALUES (?,?,?,1)')
    ->execute(['User OK', $email1, $hash1]);

$email2 = 'md5@example.com';
$pass2 = 'legacy';
$md52 = md5($pass2);
$pdo->prepare('INSERT INTO users (name,email,password,active) VALUES (?,?,?,1)')
    ->execute(['User MD5', $email2, $md52]);

$auth = new Auth($pdo);

// Teste 1: login com hash moderno
@session_regenerate_id(true);
$res1 = $auth->login($email1, $pass1);
assert_true($res1 === true, 'Login com hash moderno deve funcionar');
assert_true(isset($_SESSION['user_id']), 'Sessão deve conter user_id após login');
$uid1 = $_SESSION['user_id'];
// Reset da sessão
$_SESSION = [];
@session_write_close();
@session_start();

// Teste 2: login com MD5 legado (deve aceitar e rehash)
@session_regenerate_id(true);
$res2 = $auth->login($email2, $pass2);
assert_true($res2 === true, 'Login com MD5 legado deve funcionar');
assert_true(isset($_SESSION['user_id']), 'Sessão deve conter user_id após login (MD5)');
$uid2 = $_SESSION['user_id'];
// Reset da sessão
$_SESSION = [];
@session_write_close();
@session_start();

// Teste 3: senha incorreta
@session_regenerate_id(true);
$res3 = $auth->login($email1, 'wrong');
assert_true($res3 === false, 'Login com senha incorreta deve falhar');
assert_true(!isset($_SESSION['user_id']), 'Sessão não deve ter user_id após falha');

echo PHP_EOL . 'Todos os testes passaram.' . PHP_EOL;
exit(0);
