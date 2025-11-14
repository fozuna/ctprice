<?php
// Inicializa banco de dados e aplica o schema.sql
// Uso: php scripts/db_init.php

require __DIR__ . '/../app/core/bootstrap.php';

use App\Core\Config;

function parseDsnHostDb(string $dsn): array {
    // Ex.: mysql:host=127.0.0.1;dbname=ctprice;charset=utf8mb4
    $host = '127.0.0.1';
    $dbname = 'ctprice';
    $parts = explode(';', str_replace('mysql:', '', $dsn));
    foreach ($parts as $part) {
        $kv = explode('=', $part, 2);
        if (count($kv) === 2) {
            [$k, $v] = $kv;
            $k = trim($k);
            $v = trim($v);
            if ($k === 'host') { $host = $v; }
            if ($k === 'dbname') { $dbname = $v; }
        }
    }
    return [$host, $dbname];
}

function connectServer(string $host, string $user, string $pass, array $options): \PDO {
    $dsn = "mysql:host={$host};charset=utf8mb4";
    return new \PDO($dsn, $user, $pass, $options);
}

function connectDb(string $host, string $db, string $user, string $pass, array $options): \PDO {
    $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
    return new \PDO($dsn, $user, $pass, $options);
}

try {
    $cfg = Config::app()['database'];
    [$host, $db] = parseDsnHostDb($cfg['dsn']);
    $user = $cfg['user'];
    $pass = $cfg['pass'];
    $options = $cfg['options'];

    echo "Conectando ao servidor MySQL em {$host}...\n";
    $pdoServer = connectServer($host, $user, $pass, $options);

    // Criar banco se não existir
    $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Banco '{$db}' verificado/criado.\n";

    // Conectar no banco
    $pdoDb = connectDb($host, $db, $user, $pass, $options);

    // Aplicar schema.sql
    $schemaFile = __DIR__ . '/../database/schema.sql';
    if (!is_file($schemaFile)) {
        throw new \RuntimeException('Arquivo schema.sql não encontrado em database/.');
    }
    $sql = file_get_contents($schemaFile);
    if ($sql === false) {
        throw new \RuntimeException('Falha ao ler schema.sql');
    }
    $pdoDb->exec($sql);
    echo "Schema aplicado com sucesso.\n";

    // Criar tabela de benefícios antecipadamente
    $pdoDb->exec("CREATE TABLE IF NOT EXISTS beneficios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(120) NOT NULL,
        descricao TEXT NULL,
        parceiro VARCHAR(120) NULL,
        logo_path VARCHAR(255) NULL,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Tabela 'beneficios' verificada/criada.\n";

    echo "Concluído.\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "ERRO: " . $e->getMessage() . "\n");
    exit(1);
}