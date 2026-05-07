<?php
/**
 * Modelo OccurrenceType
 * Sistema BDO - Controle de Maquinários
 */

class OccurrenceType extends BaseModel {
    protected $table = 'occurrence_types';
    
    /**
     * Buscar tipos de ocorrência ativos
     */
    public function findActive() {
        return $this->findAll(['active' => 1], 'code ASC');
    }
    
    /**
     * Buscar por categoria
     */
    public function findByCategory($category) {
        return $this->findAll(['category' => $category, 'active' => 1], 'code ASC');
    }
    
    /**
     * Buscar tipo por código
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
     * Criar tipo de ocorrência com validações
     */
    public function createOccurrenceType($data) {
        // Verificar se código já existe
        if ($this->codeExists($data['code'])) {
            throw new Exception('Código já está em uso');
        }
        
        return $this->create($data);
    }
    
    /**
     * Atualizar tipo de ocorrência com validações
     */
    public function updateOccurrenceType($id, $data) {
        // Verificar se código já existe (excluindo o próprio tipo)
        if (isset($data['code']) && $this->codeExists($data['code'], $id)) {
            throw new Exception('Código já está em uso');
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Buscar tipos com estatísticas de uso
     */
    public function findWithStats($startDate = null, $endDate = null) {
        $sql = "SELECT ot.*, 
                       COUNT(o.id) as usage_count,
                       COALESCE(SUM(o.duration_minutes), 0) as total_minutes
                FROM {$this->table} ot
                LEFT JOIN occurrences o ON ot.id = o.occurrence_type_id";
        
        if ($startDate && $endDate) {
            $sql .= " AND o.start_datetime BETWEEN :start_date AND :end_date";
        }
        
        $sql .= " WHERE ot.active = 1
                  GROUP BY ot.id
                  ORDER BY ot.category, ot.code";
        
        $stmt = $this->conn->prepare($sql);
        
        if ($startDate && $endDate) {
            $stmt->bindValue(':start_date', $startDate);
            $stmt->bindValue(':end_date', $endDate);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Obter categorias disponíveis
     */
    public function getCategories() {
        return ['OPERACAO', 'MANUTENCAO', 'PARADA', 'APOIO'];
    }
}
?>