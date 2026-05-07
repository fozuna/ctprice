<?php
class Delivery extends BaseModel {
    protected $table = 'daily_delivery';

    public function __construct($db) {
        parent::__construct($db);
    }

    public function createEntry($data) {
        if (empty($data['front_id']) || empty($data['client_id']) || empty($data['delivery_date'])) {
            throw new Exception('Campos obrigatórios: frente, cliente e data.');
        }
        if (!isset($data['delivered_value'])) {
            $data['delivered_value'] = 0;
        }
        return parent::create($data);
    }

    public function updateEntry($id, $data) {
        return parent::update($id, $data);
    }

    public function deleteEntry($id) {
        return parent::delete($id);
    }

    // Obter entregas diárias no período, agregada por frente/cliente/data
    public function getDeliveryByDateRange($startDate, $endDate, $frontId = null, $clientId = null) {
        $sql = "SELECT 
                    dd.delivery_date,
                    dd.front_id,
                    dd.client_id,
                    COALESCE(SUM(dd.delivered_value), 0) as delivered_value,
                    f.name as front_name,
                    f.code as front_code,
                    c.name as client_name,
                    c.code as client_code
                FROM daily_delivery dd
                JOIN fronts f ON dd.front_id = f.id
                JOIN clients c ON dd.client_id = c.id
                WHERE dd.delivery_date BETWEEN :start AND :end";

        if ($frontId) { $sql .= " AND dd.front_id = :front_id"; }
        if ($clientId) { $sql .= " AND dd.client_id = :client_id"; }

        $sql .= " GROUP BY dd.delivery_date, dd.front_id, dd.client_id, f.name, f.code, c.name, c.code
                  ORDER BY dd.delivery_date, f.name, c.name";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':start', $startDate);
        $stmt->bindValue(':end', $endDate);
        if ($frontId) { $stmt->bindValue(':front_id', $frontId); }
        if ($clientId) { $stmt->bindValue(':client_id', $clientId); }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Eficiência diária: entregue real / meta diária de entrega
    public function getDailyEfficiencyWithGoals($startDate, $endDate, $frontId = null, $clientId = null) {
        $sql = "SELECT 
                    dg.goal_date,
                    g.front_id,
                    g.client_id,
                    COALESCE(SUM(dg.daily_goal), 0) as daily_goal,
                    COALESCE(SUM(dd.delivered_value), 0) as delivered_value,
                    CASE WHEN COALESCE(SUM(dg.daily_goal), 0) > 0 THEN 
                         ROUND((COALESCE(SUM(dd.delivered_value), 0) / COALESCE(SUM(dg.daily_goal), 0)) * 100, 2)
                         ELSE 0 END as efficiency_percentage,
                    f.name as front_name,
                    f.code as front_code,
                    c.name as client_name,
                    c.code as client_code,
                    dg.week_number,
                    dg.week_start_date
                FROM daily_goals dg
                JOIN goals g ON dg.goal_id = g.id AND g.goal_type = 'delivery'
                JOIN fronts f ON g.front_id = f.id
                JOIN clients c ON g.client_id = c.id
                LEFT JOIN daily_delivery dd 
                    ON dd.delivery_date = dg.goal_date
                    AND dd.front_id = g.front_id
                    AND dd.client_id = g.client_id
                WHERE dg.goal_date BETWEEN :start AND :end";

        if ($frontId) { $sql .= " AND g.front_id = :front_id"; }
        if ($clientId) { $sql .= " AND g.client_id = :client_id"; }

        $sql .= " GROUP BY dg.goal_date, g.front_id, g.client_id, f.name, f.code, c.name, c.code, dg.week_number, dg.week_start_date
                  ORDER BY dg.goal_date, f.name, c.name";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':start', $startDate);
        $stmt->bindValue(':end', $endDate);
        if ($frontId) { $stmt->bindValue(':front_id', $frontId); }
        if ($clientId) { $stmt->bindValue(':client_id', $clientId); }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function upsertEntryByUnique($frontId, $clientId, $deliveryDate, $deliveredValue, $unit = 'toneladas', $notes = null, $userId = null) {
        $sql = "INSERT INTO daily_delivery (front_id, client_id, delivery_date, delivered_value, unit, notes, created_by, updated_by)
                VALUES (:front_id, :client_id, :delivery_date, :delivered_value, :unit, :notes, :created_by, :updated_by)
                ON DUPLICATE KEY UPDATE 
                    delivered_value = VALUES(delivered_value),
                    unit = VALUES(unit),
                    notes = VALUES(notes),
                    updated_by = VALUES(updated_by)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':front_id', $frontId, PDO::PARAM_INT);
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        $stmt->bindValue(':delivery_date', $deliveryDate);
        $stmt->bindValue(':delivered_value', $deliveredValue);
        $stmt->bindValue(':unit', $unit);
        $stmt->bindValue(':notes', $notes);
        $stmt->bindValue(':created_by', $userId ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(':updated_by', $userId ?? null);
        return $stmt->execute();
    }
}
?> 
