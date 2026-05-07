<?php
class Production extends BaseModel {
    protected $table = 'daily_production';

    public function __construct($db) {
        parent::__construct($db);
    }

    public function createEntry($data) {
        if (empty($data['front_id']) || empty($data['production_date'])) {
            throw new Exception('Campos obrigatórios: frente e data.');
        }
        if (!isset($data['client_id']) || $data['client_id'] === '') { $data['client_id'] = null; }
        if (!isset($data['produced_value'])) {
            $data['produced_value'] = 0;
        }
        return parent::create($data);
    }

    public function updateEntry($id, $data) {
        return parent::update($id, $data);
    }

    public function deleteEntry($id) {
        return parent::delete($id);
    }

    // Obter produção diária no período, agregada por frente/cliente/data
    public function getProductionByDateRange($startDate, $endDate, $frontId = null, $clientId = null) {
        $sql = "SELECT 
                    MIN(dp.id) as row_id,
                    dp.production_date,
                    dp.front_id,
                    dp.client_id,
                    COALESCE(SUM(dp.produced_value), 0) as produced_value,
                    f.name as front_name,
                    f.code as front_code,
                    c.name as client_name,
                    c.code as client_code
                FROM daily_production dp
                JOIN fronts f ON dp.front_id = f.id
                LEFT JOIN clients c ON dp.client_id = c.id
                WHERE dp.production_date BETWEEN :start AND :end";

        if ($frontId) { $sql .= " AND dp.front_id = :front_id"; }
        if ($clientId) { $sql .= " AND dp.client_id = :client_id"; }

        $sql .= " GROUP BY dp.production_date, dp.front_id, dp.client_id, f.name, f.code, c.name, c.code
                  ORDER BY dp.production_date, f.name, c.name";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':start', $startDate);
        $stmt->bindValue(':end', $endDate);
        if ($frontId) { $stmt->bindValue(':front_id', $frontId); }
        if ($clientId) { $stmt->bindValue(':client_id', $clientId); }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Eficiência diária: produção real / meta diária
    public function getDailyEfficiencyWithGoals($startDate, $endDate, $frontId = null, $clientId = null) {
        $sql = "SELECT 
                    dg.goal_date,
                    g.front_id,
                    g.client_id,
                    COALESCE(SUM(dg.daily_goal), 0) as daily_goal,
                    COALESCE(SUM(dp_sum.produced_sum), 0) as produced_value,
                    CASE WHEN COALESCE(SUM(dg.daily_goal), 0) > 0 THEN 
                         ROUND((COALESCE(SUM(dp_sum.produced_sum), 0) / COALESCE(SUM(dg.daily_goal), 0)) * 100, 2)
                         ELSE 0 END as efficiency_percentage,
                    f.name as front_name,
                    f.code as front_code,
                    c.name as client_name,
                    c.code as client_code,
                    dg.week_number,
                    dg.week_start_date
                FROM daily_goals dg
                JOIN goals g ON dg.goal_id = g.id
                JOIN fronts f ON g.front_id = f.id
                LEFT JOIN clients c ON g.client_id = c.id
                LEFT JOIN (
                    SELECT production_date as goal_date, front_id, SUM(produced_value) as produced_sum
                    FROM daily_production
                    WHERE production_date BETWEEN :dp_start AND :dp_end
                    GROUP BY production_date, front_id
                ) dp_sum
                    ON dp_sum.goal_date = dg.goal_date
                    AND dp_sum.front_id = g.front_id
                WHERE dg.goal_date BETWEEN :dg_start AND :dg_end";

        if ($frontId) { $sql .= " AND g.front_id = :front_id"; }

        $sql .= " GROUP BY dg.goal_date, g.front_id, g.client_id, f.name, f.code, c.name, c.code, dg.week_number, dg.week_start_date
                  ORDER BY dg.goal_date, f.name, c.name";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':dp_start', $startDate);
        $stmt->bindValue(':dp_end', $endDate);
        $stmt->bindValue(':dg_start', $startDate);
        $stmt->bindValue(':dg_end', $endDate);
        if ($frontId) { $stmt->bindValue(':front_id', $frontId); }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Upsert: um registro por (frente, data, cliente opcional).
    // Quando client_id é NULL, impede duplicidade usando igualdade NULL-safe.
    public function upsertEntryByDate($frontId, $productionDate, $producedValue, $unit = 'toneladas', $clientId = null, $notes = null, $userId = null) {
        // Fallback: se a coluna client_id ainda NÃO permitir NULL no servidor,
        // utiliza um cliente padrão "SEM_CLIENTE" para garantir gravação.
        if ($clientId === null && !$this->isClientIdNullable()) {
            $clientId = $this->getOrCreateNoClientId();
        }
        // Verifica se já existe
        $sqlSel = "SELECT id FROM daily_production 
                   WHERE front_id = :front_id 
                     AND production_date = :d
                     AND (client_id <=> :client_id)";
        $stmt = $this->conn->prepare($sqlSel);
        $stmt->bindValue(':front_id', $frontId, PDO::PARAM_INT);
        $stmt->bindValue(':d', $productionDate);
        if ($clientId === null) {
            $stmt->bindValue(':client_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $id = $stmt->fetchColumn();
        if ($id) {
            $sqlUp = "UPDATE daily_production
                      SET produced_value = :v, unit = :u, notes = :n, updated_by = :ub
                      WHERE id = :id";
            $st = $this->conn->prepare($sqlUp);
            $st->bindValue(':v', $producedValue);
            $st->bindValue(':u', $unit);
            $st->bindValue(':n', $notes);
            $st->bindValue(':ub', $userId ?? null);
            $st->bindValue(':id', $id, PDO::PARAM_INT);
            return $st->execute();
        } else {
            $sqlIns = "INSERT INTO daily_production (front_id, client_id, production_date, produced_value, unit, notes, created_by, updated_by)
                       VALUES (:front_id, :client_id, :d, :v, :u, :n, :cb, :ub)";
            $st = $this->conn->prepare($sqlIns);
            $st->bindValue(':front_id', $frontId, PDO::PARAM_INT);
            if ($clientId === null) {
                $st->bindValue(':client_id', null, PDO::PARAM_NULL);
            } else {
                $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
            }
            $st->bindValue(':d', $productionDate);
            $st->bindValue(':v', $producedValue);
            $st->bindValue(':u', $unit);
            $st->bindValue(':n', $notes);
            $st->bindValue(':cb', $userId ?? 0, PDO::PARAM_INT);
            $st->bindValue(':ub', $userId ?? null);
            return $st->execute();
        }
    }

    private function isClientIdNullable() {
        try {
            $sql = "SELECT IS_NULLABLE 
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'daily_production'
                      AND COLUMN_NAME = 'client_id'
                    LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $val = $stmt->fetchColumn();
            return strtoupper((string)$val) === 'YES';
        } catch (Exception $e) {
            return true; // assume permissivo se não conseguir verificar
        }
    }

    private function getOrCreateNoClientId() {
        try {
            $code = 'SEM_CLIENTE';
            $select = $this->conn->prepare("SELECT id FROM clients WHERE code = :code LIMIT 1");
            $select->bindValue(':code', $code);
            $select->execute();
            $id = $select->fetchColumn();
            if ($id) {
                return (int)$id;
            }
            $insert = $this->conn->prepare("INSERT INTO clients (name, code, active) VALUES (:name, :code, 1)");
            $insert->bindValue(':name', 'Sem Cliente');
            $insert->bindValue(':code', $code);
            $insert->execute();
            return (int)$this->conn->lastInsertId();
        } catch (Exception $e) {
            // Em último caso, retorna null e deixa a exceção estourar na escrita
            return null;
        }
    }
}
?>
