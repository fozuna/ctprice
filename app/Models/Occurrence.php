<?php
/**
 * Modelo Occurrence
 * Sistema BDO - Controle de Maquinários
 */

class Occurrence extends BaseModel {
    protected $table = 'occurrences';
    
    /**
     * Buscar ocorrências com detalhes
     */
    public function findAllWithDetails($conditions = [], $orderBy = 'start_datetime DESC', $limit = null) {
        $sql = "SELECT o.*, 
                       e.tag as equipment_tag, e.description as equipment_description,
                       u.name as operator_name,
                       f.name as front_name, f.code as front_code,
                       ot.code as occurrence_code, ot.description as occurrence_description, 
                       ot.category as occurrence_category, ot.color as occurrence_color,
                       uc.name as created_by_name
                FROM {$this->table} o
                LEFT JOIN equipments e ON o.equipment_id = e.id
                LEFT JOIN users u ON o.operator_id = u.id
                LEFT JOIN fronts f ON o.front_id = f.id
                LEFT JOIN occurrence_types ot ON o.occurrence_type_id = ot.id
                LEFT JOIN users uc ON o.created_by = uc.id";
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', array_map(function($key) {
                return "o.{$key} = :{$key}";
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
     * Verificar sobreposição de horários para um equipamento
     */
    public function checkTimeOverlap($equipmentId, $startDatetime, $endDatetime = null, $excludeId = null) {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} 
                WHERE equipment_id = :equipment_id";
        
        if ($endDatetime) {
            // Verificar sobreposição completa
            $sql .= " AND (
                        (start_datetime <= :start_datetime AND (end_datetime IS NULL OR end_datetime > :start_datetime))
                        OR 
                        (start_datetime < :end_datetime AND (end_datetime IS NULL OR end_datetime >= :end_datetime))
                        OR
                        (start_datetime >= :start_datetime AND start_datetime < :end_datetime)
                      )";
        } else {
            // Verificar se há ocorrência em aberto
            $sql .= " AND end_datetime IS NULL";
        }
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':equipment_id', $equipmentId);
        $stmt->bindValue(':start_datetime', $startDatetime);
        
        if ($endDatetime) {
            $stmt->bindValue(':end_datetime', $endDatetime);
        }
        
        if ($excludeId) {
            $stmt->bindValue(':exclude_id', $excludeId);
        }
        
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['total'] > 0;
    }
    
    /**
     * Buscar ocorrência em aberto para um equipamento
     */
    public function findOpenOccurrence($equipmentId) {
        return $this->findOne([
            'equipment_id' => $equipmentId,
            'end_datetime' => null
        ]);
    }
    
    /**
     * Criar ocorrência com validações
     */
    public function createOccurrence($data) {
        // Verificar sobreposição de horários
        if ($this->checkTimeOverlap($data['equipment_id'], $data['start_datetime'], $data['end_datetime'] ?? null)) {
            throw new Exception('Existe sobreposição de horários para este equipamento');
        }
        
        // Calcular duração se end_datetime fornecido
        if (isset($data['end_datetime']) && $data['end_datetime']) {
            $start = new DateTime($data['start_datetime']);
            $end = new DateTime($data['end_datetime']);
            $data['duration_minutes'] = $end->diff($start)->days * 1440 + $end->diff($start)->h * 60 + $end->diff($start)->i;
        }
        
        return $this->create($data);
    }
    
    /**
     * Finalizar ocorrência
     */
    public function finishOccurrence($id, $endDatetime, $endHours = null, $updatedBy = null) {
        $occurrence = $this->findById($id);
        if (!$occurrence) {
            throw new Exception('Ocorrência não encontrada');
        }
        
        if ($occurrence['end_datetime']) {
            throw new Exception('Ocorrência já foi finalizada');
        }
        
        // Calcular duração
        $start = new DateTime($occurrence['start_datetime']);
        $end = new DateTime($endDatetime);
        $durationMinutes = $end->diff($start)->days * 1440 + $end->diff($start)->h * 60 + $end->diff($start)->i;
        
        $data = [
            'end_datetime' => $endDatetime,
            'duration_minutes' => $durationMinutes
        ];
        
        if ($endHours !== null) {
            $data['end_hours'] = $endHours;
            $data['hours_worked'] = $endHours - ($occurrence['start_hours'] ?? 0);
        }
        
        if ($updatedBy) {
            $data['updated_by'] = $updatedBy;
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Buscar ocorrências por período
     */
    public function findByPeriod($startDate, $endDate, $filters = []) {
        $sql = "SELECT o.*, 
                       e.tag as equipment_tag, e.description as equipment_description,
                       u.name as operator_name,
                       f.name as front_name, f.code as front_code,
                       ot.code as occurrence_code, ot.description as occurrence_description, 
                       ot.category as occurrence_category, ot.color as occurrence_color
                FROM {$this->table} o
                LEFT JOIN equipments e ON o.equipment_id = e.id
                LEFT JOIN users u ON o.operator_id = u.id
                LEFT JOIN fronts f ON o.front_id = f.id
                LEFT JOIN occurrence_types ot ON o.occurrence_type_id = ot.id
                WHERE o.start_datetime BETWEEN :start_date AND :end_date";
        
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
        
        if (!empty($filters['equipment_id'])) {
            $sql .= " AND o.equipment_id = :equipment_id";
            $params['equipment_id'] = $filters['equipment_id'];
        }
        
        if (!empty($filters['front_id'])) {
            $sql .= " AND o.front_id = :front_id";
            $params['front_id'] = $filters['front_id'];
        }
        
        if (!empty($filters['operator_id'])) {
            $sql .= " AND o.operator_id = :operator_id";
            $params['operator_id'] = $filters['operator_id'];
        }
        
        if (!empty($filters['occurrence_type_id'])) {
            $sql .= " AND o.occurrence_type_id = :occurrence_type_id";
            $params['occurrence_type_id'] = $filters['occurrence_type_id'];
        }
        
        $sql .= " ORDER BY o.start_datetime DESC";
        
        $stmt = $this->conn->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Obter estatísticas por equipamento
     */
    public function getEquipmentStats($equipmentId, $startDate, $endDate) {
        $sql = "SELECT ot.category,
                       SUM(o.duration_minutes) as total_minutes,
                       COUNT(o.id) as occurrence_count
                FROM {$this->table} o
                LEFT JOIN occurrence_types ot ON o.occurrence_type_id = ot.id
                WHERE o.equipment_id = :equipment_id 
                AND o.start_datetime BETWEEN :start_date AND :end_date
                GROUP BY ot.category";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':equipment_id', $equipmentId);
        $stmt->bindValue(':start_date', $startDate);
        $stmt->bindValue(':end_date', $endDate);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Contar ocorrências por data específica
     */
    public function countByDate($date) {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} 
                WHERE DATE(start_datetime) = :date";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':date', $date);
        $stmt->execute();
        
        $result = $stmt->fetch();
        return $result['total'];
    }
    
    /**
     * Buscar equipamentos em operação (com ocorrências abertas)
     */
    public function getEquipmentsInOperation() {
        $sql = "SELECT DISTINCT e.id, e.tag, e.description, 
                       o.id as occurrence_id, o.start_datetime,
                       ot.code as occurrence_code, ot.description as occurrence_description,
                       ot.color as occurrence_color, ot.category as occurrence_category,
                       u.name as operator_name
                FROM equipments e
                INNER JOIN {$this->table} o ON e.id = o.equipment_id
                LEFT JOIN occurrence_types ot ON o.occurrence_type_id = ot.id
                LEFT JOIN users u ON o.operator_id = u.id
                WHERE o.end_datetime IS NULL
                AND e.active = 1
                ORDER BY e.tag";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Obter estatísticas por categoria em um período
     */
    public function getStatsByCategory($startDate, $endDate) {
        $sql = "SELECT ot.category,
                       COUNT(o.id) as occurrence_count,
                       COALESCE(SUM(o.duration_minutes), 0) as total_minutes,
                       COALESCE(AVG(o.duration_minutes), 0) as avg_minutes
                FROM {$this->table} o
                LEFT JOIN occurrence_types ot ON o.occurrence_type_id = ot.id
                WHERE o.start_datetime BETWEEN :start_date AND :end_date
                GROUP BY ot.category
                ORDER BY total_minutes DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':start_date', $startDate);
        $stmt->bindValue(':end_date', $endDate . ' 23:59:59');
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Buscar ocorrências com detalhes (alias para findAllWithDetails com filtros avançados)
     */
    public function findWithDetails($conditions = [], $dateRange = []) {
        $whereConditions = [];
        $params = [];
        
        // Processar condições básicas
        foreach ($conditions as $condition) {
            if (is_string($condition)) {
                $whereConditions[] = $condition;
            }
        }
        
        // Processar filtros de data
        if (!empty($dateRange['start'])) {
            $whereConditions[] = "DATE(o.start_datetime) >= :date_start";
            $params['date_start'] = $dateRange['start'];
        }
        
        if (!empty($dateRange['end'])) {
            $whereConditions[] = "DATE(o.start_datetime) <= :date_end";
            $params['date_end'] = $dateRange['end'];
        }
        
        $sql = "SELECT o.*, 
                       e.tag as equipment_tag, e.description as equipment_description,
                       u.name as operator_name,
                       f.name as front_name, f.code as front_code,
                       ot.code as occurrence_code, ot.description as occurrence_description, 
                       ot.category as occurrence_category, ot.color as occurrence_color,
                       uc.name as created_by_name
                FROM {$this->table} o
                LEFT JOIN equipments e ON o.equipment_id = e.id
                LEFT JOIN users u ON o.operator_id = u.id
                LEFT JOIN fronts f ON o.front_id = f.id
                LEFT JOIN occurrence_types ot ON o.occurrence_type_id = ot.id
                LEFT JOIN users uc ON o.created_by = uc.id";
        
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }
        
        $sql .= " ORDER BY o.start_datetime DESC";
        
        $stmt = $this->conn->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Buscar ocorrências por equipamento
     */
    public function findByEquipment($equipmentId, $dateFrom = null, $dateTo = null, $occurrenceTypeId = null) {
        $sql = "SELECT o.*, 
                       e.tag as equipment_tag, e.description as equipment_description,
                       u.name as operator_name,
                       f.name as front_name, f.code as front_code,
                       ot.code as occurrence_code, ot.description as occurrence_type_description, 
                       ot.category, ot.color
                FROM {$this->table} o
                LEFT JOIN equipments e ON o.equipment_id = e.id
                LEFT JOIN users u ON o.operator_id = u.id
                LEFT JOIN fronts f ON o.front_id = f.id
                LEFT JOIN occurrence_types ot ON o.occurrence_type_id = ot.id
                WHERE o.equipment_id = :equipment_id";
        
        $params = ['equipment_id' => $equipmentId];
        
        if ($dateFrom) {
            $sql .= " AND DATE(o.start_datetime) >= :date_from";
            $params['date_from'] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND DATE(o.start_datetime) <= :date_to";
            $params['date_to'] = $dateTo;
        }
        
        if ($occurrenceTypeId) {
            $sql .= " AND o.occurrence_type_id = :occurrence_type_id";
            $params['occurrence_type_id'] = $occurrenceTypeId;
        }
        
        $sql .= " ORDER BY o.start_datetime DESC";
        
        $stmt = $this->conn->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obter dados de comparação temporal para dashboard
     */
    public function getDashboardComparison($currentDate, $previousDate) {
        $sql = "SELECT 
                    'current' as period,
                    COUNT(*) as total_occurrences,
                    COUNT(CASE WHEN end_datetime IS NULL THEN 1 END) as open_occurrences,
                    COUNT(DISTINCT equipment_id) as active_equipments
                FROM {$this->table} 
                WHERE DATE(start_datetime) = :current_date
                UNION ALL
                SELECT 
                    'previous' as period,
                    COUNT(*) as total_occurrences,
                    COUNT(CASE WHEN end_datetime IS NULL THEN 1 END) as open_occurrences,
                    COUNT(DISTINCT equipment_id) as active_equipments
                FROM {$this->table} 
                WHERE DATE(start_datetime) = :previous_date";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':current_date', $currentDate);
        $stmt->bindValue(':previous_date', $previousDate);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Obter equipamentos com alertas críticos
     */
    public function getCriticalAlerts() {
        $sql = "SELECT DISTINCT e.id, e.tag, e.description,
                       COUNT(o.id) as open_occurrences,
                       MAX(o.start_datetime) as last_occurrence,
                       TIMESTAMPDIFF(HOUR, MAX(o.start_datetime), NOW()) as hours_since_last
                FROM equipments e
                INNER JOIN {$this->table} o ON e.id = o.equipment_id
                LEFT JOIN occurrence_types ot ON o.occurrence_type_id = ot.id
                WHERE o.end_datetime IS NULL
                AND e.active = 1
                GROUP BY e.id, e.tag, e.description
                HAVING COUNT(o.id) > 1 OR hours_since_last > 24
                ORDER BY open_occurrences DESC, hours_since_last DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Obter estatísticas de eficiência por equipamento
     */
    public function getEquipmentEfficiency($startDate, $endDate) {
        $sql = "SELECT e.id, e.tag, e.description,
                       COALESCE(SUM(CASE WHEN ot.category = 'OPERACAO' THEN o.duration_minutes ELSE 0 END), 0) as operation_minutes,
                       COALESCE(SUM(CASE WHEN ot.category = 'MANUTENCAO' THEN o.duration_minutes ELSE 0 END), 0) as maintenance_minutes,
                       COALESCE(SUM(CASE WHEN ot.category = 'PARADA' THEN o.duration_minutes ELSE 0 END), 0) as downtime_minutes,
                       COALESCE(SUM(o.duration_minutes), 0) as total_minutes,
                       ROUND(
                           (COALESCE(SUM(CASE WHEN ot.category = 'OPERACAO' THEN o.duration_minutes ELSE 0 END), 0) * 100.0) / 
                           NULLIF(COALESCE(SUM(o.duration_minutes), 0), 0), 2
                       ) as efficiency_percentage
                FROM equipments e
                LEFT JOIN {$this->table} o ON e.id = o.equipment_id 
                    AND o.start_datetime BETWEEN :start_date AND :end_date
                LEFT JOIN occurrence_types ot ON o.occurrence_type_id = ot.id
                WHERE e.active = 1
                GROUP BY e.id, e.tag, e.description
                HAVING total_minutes > 0
                ORDER BY efficiency_percentage DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':start_date', $startDate);
        $stmt->bindValue(':end_date', $endDate . ' 23:59:59');
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Obter dados de tendência semanal
     */
    public function getWeeklyTrend() {
        $sql = "SELECT DATE(start_datetime) as date,
                       COUNT(*) as total_occurrences,
                       COUNT(CASE WHEN end_datetime IS NULL THEN 1 END) as open_occurrences,
                       COUNT(DISTINCT equipment_id) as active_equipments
                FROM {$this->table}
                WHERE start_datetime >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY DATE(start_datetime)
                ORDER BY date";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}
?>