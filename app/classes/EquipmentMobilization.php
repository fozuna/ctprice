<?php
/**
 * Modelo para Mobilização de Equipamentos
 * Sistema BDO - Controle de Maquinários
 */

class EquipmentMobilization extends BaseModel {
    protected $table = 'equipment_mobilizations';
    
    /**
     * Buscar mobilização por ID com todos os detalhes
     */
    public function findByIdWithDetails($id) {
        $sql = "SELECT em.*, 
                       e.tag as equipment_tag, e.description as equipment_description,
                       ff.name as from_front_name, ff.code as from_front_code,
                       tf.name as to_front_name, tf.code as to_front_code,
                       rb.name as requested_by_name,
                       ab.name as approved_by_name,
                       cb.name as created_by_name
                FROM {$this->table} em
                LEFT JOIN equipments e ON em.equipment_id = e.id
                LEFT JOIN fronts ff ON em.from_front_id = ff.id
                LEFT JOIN fronts tf ON em.to_front_id = tf.id
                LEFT JOIN users rb ON em.requested_by = rb.id
                LEFT JOIN users ab ON em.approved_by = ab.id
                LEFT JOIN users cb ON em.created_by = cb.id
                WHERE em.id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar mobilizações por equipamento
     */
    public function findByEquipment($equipmentId, $limit = null) {
        $sql = "SELECT em.*, 
                       e.tag as equipment_tag, e.description as equipment_description,
                       ff.name as from_front_name, ff.code as from_front_code,
                       tf.name as to_front_name, tf.code as to_front_code,
                       rb.name as requested_by_name,
                       ab.name as approved_by_name,
                       cb.name as created_by_name
                FROM {$this->table} em
                LEFT JOIN equipments e ON em.equipment_id = e.id
                LEFT JOIN fronts ff ON em.from_front_id = ff.id
                LEFT JOIN fronts tf ON em.to_front_id = tf.id
                LEFT JOIN users rb ON em.requested_by = rb.id
                LEFT JOIN users ab ON em.approved_by = ab.id
                LEFT JOIN users cb ON em.created_by = cb.id
                WHERE em.equipment_id = ?
                ORDER BY em.mobilization_date DESC, em.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$equipmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar mobilizações por frente de origem
     */
    public function findByFromFront($frontId, $limit = null) {
        $sql = "SELECT em.*, 
                       e.tag as equipment_tag, e.description as equipment_description,
                       ff.name as from_front_name, ff.code as from_front_code,
                       tf.name as to_front_name, tf.code as to_front_code,
                       rb.name as requested_by_name
                FROM {$this->table} em
                LEFT JOIN equipments e ON em.equipment_id = e.id
                LEFT JOIN fronts ff ON em.from_front_id = ff.id
                LEFT JOIN fronts tf ON em.to_front_id = tf.id
                LEFT JOIN users rb ON em.requested_by = rb.id
                WHERE em.from_front_id = ?
                ORDER BY em.mobilization_date DESC, em.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$frontId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar mobilizações por frente de destino
     */
    public function findByToFront($frontId, $limit = null) {
        $sql = "SELECT em.*, 
                       e.tag as equipment_tag, e.description as equipment_description,
                       ff.name as from_front_name, ff.code as from_front_code,
                       tf.name as to_front_name, tf.code as to_front_code,
                       rb.name as requested_by_name
                FROM {$this->table} em
                LEFT JOIN equipments e ON em.equipment_id = e.id
                LEFT JOIN fronts ff ON em.from_front_id = ff.id
                LEFT JOIN fronts tf ON em.to_front_id = tf.id
                LEFT JOIN users rb ON em.requested_by = rb.id
                WHERE em.to_front_id = ?
                ORDER BY em.mobilization_date DESC, em.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$frontId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar mobilizações por status
     */
    public function findByStatus($status, $limit = null) {
        $sql = "SELECT em.*, 
                       e.tag as equipment_tag, e.description as equipment_description,
                       ff.name as from_front_name, ff.code as from_front_code,
                       tf.name as to_front_name, tf.code as to_front_code,
                       rb.name as requested_by_name
                FROM {$this->table} em
                LEFT JOIN equipments e ON em.equipment_id = e.id
                LEFT JOIN fronts ff ON em.from_front_id = ff.id
                LEFT JOIN fronts tf ON em.to_front_id = tf.id
                LEFT JOIN users rb ON em.requested_by = rb.id
                WHERE em.status = ?
                ORDER BY em.mobilization_date DESC, em.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Aprovar mobilização
     */
    public function approve($id, $approvedBy, $observations = null) {
        $data = [
            'status' => 'APROVADA',
            'approved_by' => $approvedBy,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($observations) {
            $data['observations'] = $observations;
        }
        
        return $this->update($id, $data);
    }

    /**
     * Iniciar transporte
     */
    public function startTransport($id, $transportData) {
        $data = array_merge($transportData, [
            'status' => 'EM_TRANSITO',
            'departure_datetime' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        return $this->update($id, $data);
    }

    /**
     * Concluir mobilização
     */
    public function complete($id, $arrivalDatetime = null) {
        $data = [
            'status' => 'CONCLUIDA',
            'arrival_datetime' => $arrivalDatetime ?: date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->update($id, $data);
    }

    /**
     * Cancelar mobilização
     */
    public function cancel($id, $reason = null) {
        $data = [
            'status' => 'CANCELADA',
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($reason) {
            $data['observations'] = $reason;
        }
        
        return $this->update($id, $data);
    }

    /**
     * Buscar mobilizações por período
     */
    public function findByPeriod($startDate, $endDate, $conditions = []) {
        $sql = "SELECT em.*, 
                       e.tag as equipment_tag, e.description as equipment_description,
                       ff.name as from_front_name, ff.code as from_front_code,
                       tf.name as to_front_name, tf.code as to_front_code,
                       rb.name as requested_by_name
                FROM {$this->table} em
                LEFT JOIN equipments e ON em.equipment_id = e.id
                LEFT JOIN fronts ff ON em.from_front_id = ff.id
                LEFT JOIN fronts tf ON em.to_front_id = tf.id
                LEFT JOIN users rb ON em.requested_by = rb.id
                WHERE em.mobilization_date BETWEEN ? AND ?";
        
        $params = [$startDate, $endDate];
        
        // Adicionar condições extras
        if (!empty($conditions['equipment_id'])) {
            $sql .= " AND em.equipment_id = ?";
            $params[] = $conditions['equipment_id'];
        }
        
        if (!empty($conditions['from_front_id'])) {
            $sql .= " AND em.from_front_id = ?";
            $params[] = $conditions['from_front_id'];
        }
        
        if (!empty($conditions['to_front_id'])) {
            $sql .= " AND em.to_front_id = ?";
            $params[] = $conditions['to_front_id'];
        }
        
        if (!empty($conditions['status'])) {
            $sql .= " AND em.status = ?";
            $params[] = $conditions['status'];
        }
        
        $sql .= " ORDER BY em.mobilization_date DESC, em.created_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obter estatísticas de mobilizações
     */
    public function getStatistics($period = null) {
        $whereClause = "";
        $params = [];
        
        if ($period) {
            $whereClause = "WHERE mobilization_date >= ?";
            $params[] = $period;
        }
        
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'SOLICITADA' THEN 1 ELSE 0 END) as solicitadas,
                    SUM(CASE WHEN status = 'APROVADA' THEN 1 ELSE 0 END) as aprovadas,
                    SUM(CASE WHEN status = 'EM_TRANSITO' THEN 1 ELSE 0 END) as em_transito,
                    SUM(CASE WHEN status = 'CONCLUIDA' THEN 1 ELSE 0 END) as concluidas,
                    SUM(CASE WHEN status = 'CANCELADA' THEN 1 ELSE 0 END) as canceladas,
                    AVG(transport_cost) as custo_medio
                FROM {$this->table} {$whereClause}";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obter frente atual de um equipamento
     */
    public function getCurrentFront($equipmentId) {
        $sql = "SELECT to_front_id as current_front_id, tf.name as current_front_name, tf.code as current_front_code
                FROM {$this->table} em
                LEFT JOIN fronts tf ON em.to_front_id = tf.id
                WHERE em.equipment_id = ? AND em.status = 'CONCLUIDA'
                ORDER BY em.mobilization_date DESC, em.created_at DESC
                LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$equipmentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar mobilizações com filtros avançados
     */
    public function findWithFilters($filters = [], $limit = null, $offset = 0) {
        $sql = "SELECT em.*, 
                       e.tag as equipment_tag, e.description as equipment_description,
                       ff.name as from_front_name, ff.code as from_front_code,
                       tf.name as to_front_name, tf.code as to_front_code,
                       rb.name as requested_by_name,
                       ab.name as approved_by_name,
                       cb.name as created_by_name
                FROM {$this->table} em
                LEFT JOIN equipments e ON em.equipment_id = e.id
                LEFT JOIN fronts ff ON em.from_front_id = ff.id
                LEFT JOIN fronts tf ON em.to_front_id = tf.id
                LEFT JOIN users rb ON em.requested_by = rb.id
                LEFT JOIN users ab ON em.approved_by = ab.id
                LEFT JOIN users cb ON em.created_by = cb.id
                WHERE 1=1";
        
        $params = [];
        
        // Aplicar filtros
        if (!empty($filters['equipment_id'])) {
            $sql .= " AND em.equipment_id = ?";
            $params[] = $filters['equipment_id'];
        }
        
        if (!empty($filters['from_front_id'])) {
            $sql .= " AND em.from_front_id = ?";
            $params[] = $filters['from_front_id'];
        }
        
        if (!empty($filters['to_front_id'])) {
            $sql .= " AND em.to_front_id = ?";
            $params[] = $filters['to_front_id'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND em.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['start_date'])) {
            $sql .= " AND em.mobilization_date >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND em.mobilization_date <= ?";
            $params[] = $filters['end_date'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (e.tag LIKE ? OR e.description LIKE ? OR em.reason LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY em.mobilization_date DESC, em.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
            if ($offset > 0) {
                $sql .= " OFFSET " . intval($offset);
            }
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Contar mobilizações com filtros
     */
    public function countWithFilters($filters = []) {
        $sql = "SELECT COUNT(*) as total
                FROM {$this->table} em
                LEFT JOIN equipments e ON em.equipment_id = e.id
                WHERE 1=1";
        
        $params = [];
        
        // Aplicar os mesmos filtros da busca
        if (!empty($filters['equipment_id'])) {
            $sql .= " AND em.equipment_id = ?";
            $params[] = $filters['equipment_id'];
        }
        
        if (!empty($filters['from_front_id'])) {
            $sql .= " AND em.from_front_id = ?";
            $params[] = $filters['from_front_id'];
        }
        
        if (!empty($filters['to_front_id'])) {
            $sql .= " AND em.to_front_id = ?";
            $params[] = $filters['to_front_id'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND em.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['start_date'])) {
            $sql .= " AND em.mobilization_date >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND em.mobilization_date <= ?";
            $params[] = $filters['end_date'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (e.tag LIKE ? OR e.description LIKE ? OR em.reason LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }

    /**
     * Buscar todas as mobilizações com detalhes
     */
    public function findAllWithDetails($conditions = [], $orderBy = 'mobilization_date DESC, created_at DESC', $limit = null) {
        $sql = "SELECT em.*, 
                       e.tag as equipment_tag, e.description as equipment_description,
                       ff.name as from_front_name, ff.code as from_front_code,
                       tf.name as to_front_name, tf.code as to_front_code,
                       rb.name as requested_by_name,
                       ab.name as approved_by_name,
                       cb.name as created_by_name
                FROM {$this->table} em
                LEFT JOIN equipments e ON em.equipment_id = e.id
                LEFT JOIN fronts ff ON em.from_front_id = ff.id
                LEFT JOIN fronts tf ON em.to_front_id = tf.id
                LEFT JOIN users rb ON em.requested_by = rb.id
                LEFT JOIN users ab ON em.approved_by = ab.id
                LEFT JOIN users cb ON em.created_by = cb.id";
        
        $params = [];
        
        if (!empty($conditions)) {
            $whereConditions = [];
            foreach ($conditions as $key => $value) {
                $whereConditions[] = "em.{$key} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>