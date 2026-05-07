<?php
/**
 * Classe Base para Modelos
 * Sistema BDO - Controle de Maquinários
 */

abstract class BaseModel {
    protected $conn;
    protected $table;
    protected $primaryKey = 'id';
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Buscar todos os registros
     */
    public function findAll($conditions = [], $orderBy = null, $limit = null) {
        $sql = "SELECT * FROM {$this->table}";
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', array_map(function($key) {
                return "{$key} = :{$key}";
            }, array_keys($conditions)));
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        $stmt = $this->conn->prepare($sql);
        
        foreach ($conditions as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Buscar um registro por ID
     */
    public function findById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    /**
     * Buscar um registro por condições
     */
    public function findOne($conditions = []) {
        $sql = "SELECT * FROM {$this->table}";
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', array_map(function($key) {
                return "{$key} = :{$key}";
            }, array_keys($conditions)));
        }
        
        $sql .= " LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        
        foreach ($conditions as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        $stmt->execute();
        return $stmt->fetch();
    }
    
    /**
     * Criar novo registro
     */
    public function create($data) {
        $fields = array_keys($data);
        $placeholders = array_map(function($field) { return ":{$field}"; }, $fields);
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->conn->prepare($sql);
        
        foreach ($data as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Atualizar registro
     */
    public function update($id, $data) {
        $fields = array_map(function($field) { return "{$field} = :{$field}"; }, array_keys($data));
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE {$this->primaryKey} = :id";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        
        foreach ($data as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        return $stmt->execute();
    }
    
    /**
     * Deletar registro
     */
    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
    
    /**
     * Contar registros
     */
    public function count($conditions = []) {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', array_map(function($key) {
                return "{$key} = :{$key}";
            }, array_keys($conditions)));
        }
        
        $stmt = $this->conn->prepare($sql);
        
        foreach ($conditions as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['total'];
    }
    
    /**
     * Verificar se registro existe
     */
    public function exists($conditions) {
        return $this->count($conditions) > 0;
    }
}
?>