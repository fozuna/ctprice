<?php
/**
 * Modelo Front
 * Sistema BDO - Controle de Maquinários
 */

class Front extends BaseModel {
    protected $table = 'fronts';
    
    /**
     * Buscar frentes ativas
     */
    public function findActive() {
        return $this->findAll(['active' => 1], 'name ASC');
    }
    
    /**
     * Buscar frente por código
     */
    public function findByCode($code) {
        return $this->findOne(['code' => $code]);
    }
    
    /**
     * Verificar se código já existe
     */
    public function codeExists($code, $excludeId = null) {
        if ($excludeId) {
            $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE code = :code AND id != :exclude_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':code', $code);
            $stmt->bindValue(':exclude_id', $excludeId);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result['total'] > 0;
        }
        
        return $this->exists(['code' => $code]);
    }
    
    /**
     * Criar frente com validações
     */
    public function createFront($data) {
        // Verificar se código já existe
        if ($this->codeExists($data['code'])) {
            throw new Exception('Código já está em uso');
        }
        
        return $this->create($data);
    }
    
    /**
     * Atualizar frente com validações
     */
    public function updateFront($id, $data) {
        // Verificar se código já existe (excluindo a própria frente)
        if (isset($data['code']) && $this->codeExists($data['code'], $id)) {
            throw new Exception('Código já está em uso');
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Buscar frentes com estatísticas de produção
     */
    public function findWithStats($startDate = null, $endDate = null) {
        $sql = "SELECT f.*, 
                       COUNT(DISTINCT o.equipment_id) as equipment_count,
                       COALESCE(SUM(CASE WHEN ot.category = 'OPERACAO' THEN o.duration_minutes ELSE 0 END), 0) as operation_minutes,
                       COALESCE(SUM(o.duration_minutes), 0) as total_minutes
                FROM {$this->table} f
                LEFT JOIN occurrences o ON f.id = o.front_id";
        
        if ($startDate && $endDate) {
            $sql .= " AND o.start_datetime BETWEEN :start_date AND :end_date";
        }
        
        $sql .= " LEFT JOIN occurrence_types ot ON o.occurrence_type_id = ot.id
                  WHERE f.active = 1
                  GROUP BY f.id
                  ORDER BY f.name";
        
        $stmt = $this->conn->prepare($sql);
        
        if ($startDate && $endDate) {
            $stmt->bindValue(':start_date', $startDate);
            $stmt->bindValue(':end_date', $endDate);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
?>