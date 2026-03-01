<?php

namespace App\Core;

use PDO;

abstract class Model
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    protected function query($sql, $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    protected function fetchAll($sql, $params = [])
    {
        return $this->query($sql, $params)->fetchAll();
    }

    protected function fetch($sql, $params = [])
    {
        return $this->query($sql, $params)->fetch();
    }
}
