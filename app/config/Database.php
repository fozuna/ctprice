<?php
class Database {
    private static $instance = null;
    private $conn;
    private function __construct() {}
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    public function getConnection() {
        if ($this->conn === null) {
            $envHost = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? null);
            $envPort = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? null);
            $envName = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? null);
            $envUser = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? null);
            $envPass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? null);
            $envCharset = getenv('DB_CHARSET') ?: ($_ENV['DB_CHARSET'] ?? null);
            $host = $envHost ?: (defined('DB_HOST') ? DB_HOST : null);
            $port = $envPort ?: (defined('DB_PORT') ? DB_PORT : '3306');
            $dbname = $envName ?: (defined('DB_NAME') ? DB_NAME : null);
            $username = $envUser ?: (defined('DB_USER') ? DB_USER : null);
            $password = $envPass !== null ? $envPass : (defined('DB_PASS') ? DB_PASS : '');
            $charset = $envCharset ?: (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');
            $databaseUrl = getenv('DATABASE_URL') ?: ($_ENV['DATABASE_URL'] ?? null);
            if ($databaseUrl) {
                $parts = parse_url($databaseUrl);
                if ($parts && isset($parts['scheme']) && $parts['scheme'] === 'mysql') {
                    $host = $parts['host'] ?? $host;
                    $port = isset($parts['port']) ? (string)$parts['port'] : $port;
                    $dbname = isset($parts['path']) ? ltrim($parts['path'], '/') : $dbname;
                    $username = $parts['user'] ?? $username;
                    $password = $parts['pass'] ?? $password;
                    if (isset($parts['query'])) {
                        parse_str($parts['query'], $q);
                        if (isset($q['charset'])) {
                            $charset = $q['charset'];
                        }
                    }
                }
            }
            if ($host === null || $dbname === null || $username === null) {
                throw new \RuntimeException('Configuração de banco inválida: defina DB_HOST, DB_NAME e DB_USER.');
            }
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}"
            ];
            try {
                $this->conn = new PDO($dsn, $username, $password, $options);
            } catch (PDOException $e) {
                if ($host === 'localhost') {
                    $host = '127.0.0.1';
                    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
                    $this->conn = new PDO($dsn, $username, $password, $options);
                } else {
                    throw $e;
                }
            }
        }
        return $this->conn;
    }
}
