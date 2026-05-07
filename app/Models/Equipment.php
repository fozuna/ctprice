<?php
/**
 * Modelo Equipment
 * Sistema BDO - Controle de Maquinários
 */

class Equipment extends BaseModel {
    protected $table = 'equipments';
    
    /**
     * Buscar equipamentos ativos
     */
    public function findActive() {
        return $this->findAll(['active' => 1], 'tag ASC');
    }
    
    /**
     * Buscar equipamento por tag
     */
    public function findByTag($tag) {
        return $this->findOne(['tag' => $tag]);
    }
    
    /**
     * Verificar se tag já existe
     */
    public function tagExists($tag, $excludeId = null) {
        if ($excludeId) {
            $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE tag = :tag AND id != :exclude_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':tag', $tag);
            $stmt->bindValue(':exclude_id', $excludeId);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result['total'] > 0;
        }
        
        return $this->exists(['tag' => $tag]);
    }
    
    /**
     * Criar equipamento com validações
     */
    public function createEquipment($data) {
        // Verificar se tag já existe
        if ($this->tagExists($data['tag'])) {
            throw new Exception('Tag já está em uso');
        }
        
        // Definir horímetro atual igual ao inicial se não informado
        if (!isset($data['current_hours'])) {
            $data['current_hours'] = $data['initial_hours'] ?? 0;
        }
        
        return $this->create($data);
    }
    
    /**
     * Atualizar equipamento com validações
     */
    public function updateEquipment($id, $data) {
        // Verificar se tag já existe (excluindo o próprio equipamento)
        if (isset($data['tag']) && $this->tagExists($data['tag'], $id)) {
            throw new Exception('Tag já está em uso');
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Atualizar horímetro atual
     */
    public function updateCurrentHours($id, $hours) {
        return $this->update($id, ['current_hours' => $hours]);
    }
    
    /**
     * Buscar equipamentos com estatísticas de uso
     */
    public function findWithStats($startDate = null, $endDate = null) {
        $sql = "SELECT e.*, 
                       COALESCE(SUM(CASE WHEN ot.category = 'OPERACAO' THEN o.duration_minutes ELSE 0 END), 0) as operation_minutes,
                       COALESCE(SUM(CASE WHEN ot.category = 'MANUTENCAO' THEN o.duration_minutes ELSE 0 END), 0) as maintenance_minutes,
                       COALESCE(SUM(CASE WHEN ot.category = 'PARADA' THEN o.duration_minutes ELSE 0 END), 0) as downtime_minutes,
                       COALESCE(SUM(o.duration_minutes), 0) as total_minutes
                FROM {$this->table} e
                LEFT JOIN occurrences o ON e.id = o.equipment_id";
        
        if ($startDate && $endDate) {
            $sql .= " AND o.start_datetime BETWEEN :start_date AND :end_date";
        }
        
        $sql .= " LEFT JOIN occurrence_types ot ON o.occurrence_type_id = ot.id
                  WHERE e.active = 1
                  GROUP BY e.id
                  ORDER BY e.tag";
        
        $stmt = $this->conn->prepare($sql);
        
        if ($startDate && $endDate) {
            $stmt->bindValue(':start_date', $startDate);
            $stmt->bindValue(':end_date', $endDate);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Estatísticas de uso por equipamento, com filtros e nomes de frente
     * Retorna horas por categoria (produção/operacao, manutenção, parada)
     * Campos esperados pelo relatório:
     * - tag, description, front_name
     * - production_hours, maintenance_hours, downtime_hours
     */
    public function findWithUsageStats($conditions = [], $startDate = null, $endDate = null) {
        $whereConditions = ["e.active = 1"];
        $params = [];
        $paramCounter = 0;

        foreach ($conditions as $key => $value) {
            if ($value === null || $value === '') continue;
            $paramName = 'p' . $paramCounter++;
            $column = $key;
            if (strpos($key, '.') === false) {
                $column = "e.$key";
            }
            $whereConditions[] = "$column = :$paramName";
            $params[$paramName] = $value;
        }

        $dateJoin = "";
        if ($startDate && $endDate) {
            $dateJoin = " AND o.start_datetime BETWEEN :start_date AND :end_date";
        }

        $sql = "SELECT 
                    e.id,
                    e.tag,
                    e.description,
                    f.name AS front_name,
                    COALESCE(SUM(CASE WHEN ot.category IN ('PRODUCAO','OPERACAO') THEN o.duration_minutes ELSE 0 END), 0) / 60.0 AS production_hours,
                    COALESCE(SUM(CASE WHEN ot.category = 'MANUTENCAO' THEN o.duration_minutes ELSE 0 END), 0) / 60.0 AS maintenance_hours,
                    COALESCE(SUM(CASE WHEN ot.category = 'PARADA' THEN o.duration_minutes ELSE 0 END), 0) / 60.0 AS downtime_hours
                FROM {$this->table} e
                LEFT JOIN fronts f ON e.front_id = f.id
                LEFT JOIN occurrences o ON e.id = o.equipment_id {$dateJoin}
                LEFT JOIN occurrence_types ot ON o.occurrence_type_id = ot.id";

        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }

        $sql .= " GROUP BY e.id, e.tag, e.description, f.name
                  ORDER BY e.tag";

        $stmt = $this->conn->prepare($sql);

        if ($startDate && $endDate) {
            $stmt->bindValue(':start_date', $startDate);
            $stmt->bindValue(':end_date', $endDate);
        }
        foreach ($params as $name => $val) {
            $stmt->bindValue(":$name", $val);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Buscar todos os equipamentos com detalhes (alias para findWithFronts)
     */
    public function findAllWithDetails($conditions = []) {
        return $this->findWithFronts($conditions);
    }
    
    /**
     * Buscar equipamentos com informações das frentes e fases
     */
    public function findWithFronts($conditions = []) {
        $whereConditions = [];
        $params = [];
        $paramCounter = 0;
        
        // Processar condições
        foreach ($conditions as $key => $value) {
            if (is_numeric($key)) {
                // Condição customizada (como LIKE)
                $whereConditions[] = $value;
            } else {
                // Condição simples de igualdade
                $paramName = 'param' . $paramCounter++;
                // Adicionar alias da tabela para evitar ambiguidade
                $columnName = $key;
                if (!strpos($key, '.')) {
                    $columnName = "e.$key";
                }
                $whereConditions[] = "$columnName = :$paramName";
                $params[$paramName] = $value;
            }
        }
        
        $sql = "SELECT e.*, f.name as front_name, e.current_hours as current_hour_meter, 
                       fa.nome as fase_name, fa.id as fase_id
                FROM {$this->table} e 
                LEFT JOIN fronts f ON e.front_id = f.id
                LEFT JOIN fases fa ON e.fase_id = fa.id";
        
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }
        
        $sql .= " ORDER BY e.tag";
        
        $stmt = $this->conn->prepare($sql);
        
        foreach ($params as $paramName => $value) {
            $stmt->bindValue(":$paramName", $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Buscar frente atual do equipamento considerando mobilizações
     */
    public function getCurrentFrontWithMobilization($equipmentId) {
        // Primeiro, verificar se há mobilizações concluídas
        $sql = "SELECT to_front_id as current_front_id, tf.name as current_front_name, tf.code as current_front_code,
                       em.mobilization_date, em.arrival_datetime
                FROM equipment_mobilizations em
                LEFT JOIN fronts tf ON em.to_front_id = tf.id
                WHERE em.equipment_id = :equipment_id AND em.status = 'CONCLUIDA'
                ORDER BY em.mobilization_date DESC, em.arrival_datetime DESC
                LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':equipment_id', $equipmentId);
        $stmt->execute();
        $mobilization = $stmt->fetch();
        
        if ($mobilization) {
            return $mobilization;
        }
        
        // Se não há mobilizações, buscar a frente original do equipamento
        $sql = "SELECT e.front_id as current_front_id, f.name as current_front_name, f.code as current_front_code
                FROM equipments e
                LEFT JOIN fronts f ON e.front_id = f.id
                WHERE e.id = :equipment_id";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':equipment_id', $equipmentId);
        $stmt->execute();
        return $stmt->fetch();
    }

    /**
     * Frente ativa considerando equipment_allocations e intervalo solicitado.
     * Retorna a frente que possui alocação sobreposta ao intervalo [from, to].
     * Em caso de múltiplas, prioriza maior prioridade e alocação mais recente.
     */
    public function getFrontByRange($equipmentId, $fromDate, $toDate) {
        if (!$fromDate) { $fromDate = date('Y-m-d'); }
        if (!$toDate) { $toDate = $fromDate; }
        $sql = "SELECT a.front_id, f.name as current_front_name, f.code as current_front_code
                FROM equipment_allocations a
                LEFT JOIN fronts f ON a.front_id = f.id
                WHERE a.equipment_id = :e
                  AND NOT (a.end_datetime < :from OR a.start_datetime > :to)
                ORDER BY a.priority DESC, a.start_datetime DESC
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':e' => $equipmentId,
            ':from' => $fromDate . (strlen($fromDate) === 10 ? ' 00:00:00' : ''),
            ':to' => $toDate . (strlen($toDate) === 10 ? ' 23:59:59' : '')
        ]);
        $row = $stmt->fetch();
        if ($row) { return $row; }
        // Fallback: lógica existente (mobilização ou frente original)
        return $this->getCurrentFrontWithMobilization($equipmentId);
    }
    
    /**
     * Buscar histórico completo de mobilizações do equipamento
     */
    public function getMobilizationHistory($equipmentId, $limit = null) {
        $sql = "SELECT em.*, 
                       ff.name as from_front_name, ff.code as from_front_code,
                       tf.name as to_front_name, tf.code as to_front_code,
                       ur.name as requested_by_name,
                       ua.name as approved_by_name
                FROM equipment_mobilizations em
                LEFT JOIN fronts ff ON em.from_front_id = ff.id
                LEFT JOIN fronts tf ON em.to_front_id = tf.id
                LEFT JOIN users ur ON em.requested_by = ur.id
                LEFT JOIN users ua ON em.approved_by = ua.id
                WHERE em.equipment_id = :equipment_id
                ORDER BY em.mobilization_date DESC, em.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':equipment_id', $equipmentId);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Buscar equipamentos com informações de mobilização atual
     */
    public function findWithCurrentLocation($conditions = []) {
        $whereConditions = [];
        $params = [];
        $paramCounter = 0;
        
        // Processar condições
        foreach ($conditions as $key => $value) {
            if (is_numeric($key)) {
                $whereConditions[] = $value;
            } else {
                $paramName = 'param' . $paramCounter++;
                $columnName = $key;
                if (!strpos($key, '.')) {
                    $columnName = "e.$key";
                }
                $whereConditions[] = "$columnName = :$paramName";
                $params[$paramName] = $value;
            }
        }
        
        $sql = "SELECT e.*, 
                       COALESCE(current_loc.name, original_front.name) as current_front_name,
                       COALESCE(current_loc.code, original_front.code) as current_front_code,
                       COALESCE(current_loc.id, e.front_id) as current_front_id,
                       original_front.name as original_front_name,
                       last_mobilization.mobilization_date as last_mobilization_date,
                       last_mobilization.status as last_mobilization_status
                FROM equipments e
                LEFT JOIN fronts original_front ON e.front_id = original_front.id
                LEFT JOIN (
                    SELECT em1.equipment_id, em1.to_front_id, em1.mobilization_date, em1.status
                    FROM equipment_mobilizations em1
                    WHERE em1.status = 'CONCLUIDA'
                    AND em1.id = (
                        SELECT em2.id 
                        FROM equipment_mobilizations em2 
                        WHERE em2.equipment_id = em1.equipment_id 
                        AND em2.status = 'CONCLUIDA'
                        ORDER BY em2.mobilization_date DESC, em2.arrival_datetime DESC 
                        LIMIT 1
                    )
                ) last_mobilization ON e.id = last_mobilization.equipment_id
                LEFT JOIN fronts current_loc ON last_mobilization.to_front_id = current_loc.id";
        
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }
        
        $sql .= " ORDER BY e.tag";
        
        $stmt = $this->conn->prepare($sql);
        
        foreach ($params as $paramName => $value) {
            $stmt->bindValue(":$paramName", $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Obter estatísticas de mobilização do equipamento
     */
    public function getMobilizationStats($equipmentId) {
        $sql = "SELECT 
                    COUNT(*) as total_mobilizations,
                    SUM(CASE WHEN status = 'CONCLUIDA' THEN 1 ELSE 0 END) as completed_mobilizations,
                    SUM(CASE WHEN status = 'CANCELADA' THEN 1 ELSE 0 END) as cancelled_mobilizations,
                    SUM(CASE WHEN status IN ('SOLICITADA', 'APROVADA', 'EM_TRANSITO') THEN 1 ELSE 0 END) as pending_mobilizations,
                    AVG(transport_cost) as average_transport_cost,
                    MIN(mobilization_date) as first_mobilization,
                    MAX(mobilization_date) as last_mobilization
                FROM equipment_mobilizations 
                WHERE equipment_id = :equipment_id";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':equipment_id', $equipmentId);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    /**
     * Verificar se equipamento tem mobilizações pendentes
     */
    public function hasPendingMobilizations($equipmentId) {
        $sql = "SELECT COUNT(*) as pending_count
                FROM equipment_mobilizations 
                WHERE equipment_id = :equipment_id 
                AND status IN ('SOLICITADA', 'APROVADA', 'EM_TRANSITO')";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':equipment_id', $equipmentId);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['pending_count'] > 0;
    }
}
?>
