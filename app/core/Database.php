<?php
namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $pdo = null;

    public static function conn(): \PDO
    {
        if (self::$pdo === null) {
            $config = Config::app()['database'];
            
            try {
                self::$pdo = new \PDO($config['dsn'], $config['user'], $config['pass'], $config['options']);
            } catch (\PDOException $e) {
                $log = STORAGE_PATH . DIRECTORY_SEPARATOR . 'php-error.log';
                @file_put_contents($log, '[' . date('c') . "] DB CONNECT FATAL: " . $e->getMessage() . "\n", FILE_APPEND);
                if (Config::app()['env'] === 'dev') {
                    throw new \RuntimeException('MySQL não está rodando ou credenciais inválidas.');
                }
                throw new \RuntimeException('Erro ao conectar ao banco de dados.');
            }
        }
        
        return self::$pdo;
    }
}