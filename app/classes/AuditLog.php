<?php
/**
 * Classe de Log de Auditoria
 * Sistema BDO - Controle de Maquinários
 */

class AuditLog {
    private $conn;
    private $table = 'audit_logs';
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Registrar ação de inserção
     */
    public function logInsert($tableName, $recordId, $newValues, $userId) {
        return $this->log($tableName, $recordId, 'INSERT', $userId, null, $newValues);
    }
    
    /**
     * Registrar ação de atualização
     */
    public function logUpdate($tableName, $recordId, $oldValues, $newValues, $userId) {
        return $this->log($tableName, $recordId, 'UPDATE', $userId, $oldValues, $newValues);
    }
    
    /**
     * Registrar ação de exclusão
     */
    public function logDelete($tableName, $recordId, $oldValues, $userId) {
        return $this->log($tableName, $recordId, 'DELETE', $userId, $oldValues, null);
    }
    
    /**
     * Registrar log
     */
    private function log($tableName, $recordId, $action, $userId = null, $oldValues = null, $newValues = null) {
        $sql = "INSERT INTO {$this->table} (table_name, record_id, action, old_values, new_values, user_id) 
                VALUES (:table_name, :record_id, :action, :old_values, :new_values, :user_id)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':table_name', $tableName);
        $stmt->bindParam(':record_id', $recordId);
        $stmt->bindParam(':action', $action);
        
        // Usar bindValue para valores JSON que não podem ser passados por referência
        $oldValuesJson = $oldValues ? json_encode($oldValues) : null;
        $newValuesJson = $newValues ? json_encode($newValues) : null;
        $stmt->bindValue(':old_values', $oldValuesJson);
        $stmt->bindValue(':new_values', $newValuesJson);
        
        if ($userId === null && isset($_SESSION['user_id'])) {
            $userId = intval($_SESSION['user_id']);
        } elseif ($userId === null) {
            $userId = 1;
        }
        $stmt->bindParam(':user_id', $userId);
        
        return $stmt->execute();
    }
    
    /**
     * Buscar logs por tabela e registro
     */
    public function findByRecord($tableName, $recordId) {
        $sql = "SELECT al.*, u.name as user_name 
                FROM {$this->table} al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.table_name = :table_name AND al.record_id = :record_id
                ORDER BY al.created_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':table_name', $tableName);
        $stmt->bindParam(':record_id', $recordId);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Buscar logs por usuário
     */
    public function findByUser($userId, $limit = 100) {
        $sql = "SELECT al.*, u.name as user_name 
                FROM {$this->table} al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.user_id = :user_id
                ORDER BY al.created_at DESC
                LIMIT :limit";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Buscar logs recentes
     */
    public function findRecent($limit = 100) {
        $sql = "SELECT al.*, u.name as user_name 
                FROM {$this->table} al
                LEFT JOIN users u ON al.user_id = u.id
                ORDER BY al.created_at DESC
                LIMIT :limit";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}
?>
