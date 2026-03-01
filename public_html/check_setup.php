<?php
/**
 * Script de diagnóstico para o ambiente Hostinger
 * 
 * Acesse: https://seu-dominio.com/check_setup.php
 * Apague este arquivo após a correção!
 */

// Erros visíveis
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Diagnóstico de Ambiente - CT Price</h1>";

// 1. Verificar PHP Version
echo "<h2>1. Versão do PHP</h2>";
echo "Versão atual: " . phpversion() . "<br>";
if (version_compare(phpversion(), '7.4.0', '<')) {
    echo "<strong style='color:red'>ERRO: Requer PHP 7.4 ou superior.</strong>";
} else {
    echo "<strong style='color:green'>OK</strong>";
}

// 2. Verificar Extensões
echo "<h2>2. Extensões Necessárias</h2>";
$extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "$ext: <strong style='color:green'>OK</strong><br>";
    } else {
        echo "$ext: <strong style='color:red'>FALTANDO</strong><br>";
    }
}

// 3. Verificar Estrutura de Diretórios
echo "<h2>3. Estrutura de Arquivos</h2>";
$basePath = __DIR__ . '/../';
echo "Base Path calculado: $basePath<br>";

$dirs = [
    'app' => $basePath . 'app',
    'public_html' => $basePath . 'public_html',
    'assets' => $basePath . 'public_html/assets',
    'env' => $basePath . '.env'
];

foreach ($dirs as $name => $path) {
    if (file_exists($path)) {
        echo "$name: <strong style='color:green'>ENCONTRADO</strong> ($path)<br>";
    } else {
        echo "$name: <strong style='color:red'>NÃO ENCONTRADO</strong> ($path)<br>";
        if ($name === 'env') {
            echo "<em>Nota: O arquivo .env é necessário para conectar ao banco de dados. Crie-o na raiz do projeto.</em><br>";
        }
    }
}

// 4. Testar Conexão com Banco (se .env existir)
echo "<h2>4. Teste de Conexão com Banco de Dados</h2>";
if (file_exists($basePath . '.env')) {
    // Parser simples do .env
    $env = file($basePath . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env as $line) {
        if (strpos($line, '=') !== false && substr($line, 0, 1) !== '#') {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
    
    $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $name = $_ENV['DB_NAME'] ?? 'ctprice_db';
    $user = $_ENV['DB_USER'] ?? 'root';
    $pass = $_ENV['DB_PASS'] ?? '';
    
    echo "Tentando conectar em <strong>$host</strong> (Banco: <strong>$name</strong>, Usuário: <strong>$user</strong>)...<br>";
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass);
        echo "<strong style='color:green'>CONEXÃO BEM SUCEDIDA!</strong><br>";
        
        // Verificar tabelas
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Tabelas encontradas: " . implode(', ', $tables) . "<br>";
        
        if (in_array('testimonials', $tables) && in_array('partners', $tables)) {
            echo "<strong style='color:green'>Estrutura do banco parece correta.</strong>";
        } else {
            echo "<strong style='color:red'>ATENÇÃO: Tabelas 'testimonials' ou 'partners' não encontradas. Importe o arquivo database.sql.</strong>";
        }
        
    } catch (PDOException $e) {
        echo "<strong style='color:red'>FALHA NA CONEXÃO:</strong> " . $e->getMessage();
    }
} else {
    echo "Arquivo .env não encontrado. Não é possível testar a conexão.";
}

echo "<hr><p><em>Apague este arquivo após o uso por segurança.</em></p>";
