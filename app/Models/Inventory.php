<?php
class Inventory extends BaseModel {
    protected $table = 'front_opening_balance';

    public function __construct($db) {
        parent::__construct($db);
    }

    // Série diária de estoque para um período:
    // estoque_final(d) = estoque_inicial(d) + produção(d) - entregas(d)
    // estoque_inicial(d) = estoque_final(d-1); para o primeiro dia usa o saldo inicial encontrado
    public function getDailyStock($frontId, $startDate, $endDate, $clientId = null) {
        if (empty($frontId) || empty($startDate) || empty($endDate)) {
            return [];
        }
        // Saldo inicial como base para o primeiro dia
        $opening = $this->getOpeningBalanceForFront($frontId, $startDate);
        $result = [];
        $cursor = new DateTime($startDate);
        $limit = new DateTime($endDate);
        while ($cursor <= $limit) {
            $d = $cursor->format('Y-m-d');
            $prod = $this->getProducedOnDate($frontId, $d, $clientId);
            $deliv = $this->getDeliveredOnDate($frontId, $d, $clientId);
            $final = $opening + $prod - $deliv;
            $result[] = [
                'date' => $d,
                'opening' => round($opening, 2),
                'produced' => round($prod, 2),
                'delivered' => round($deliv, 2),
                'final' => round($final, 2),
            ];
            $opening = $final;
            $cursor->add(new DateInterval('P1D'));
        }
        return $result;
    }

    // Retorna o saldo inicial (mais recente) válido até uma data
    public function getOpeningBalanceForFront($frontId, $asOfDate) {
        if (empty($frontId) || empty($asOfDate)) {
            return 0.0;
        }
        $sql = "SELECT opening_balance_value
                FROM {$this->table}
                WHERE front_id = :front_id
                  AND balance_date <= :as_of
                ORDER BY balance_date DESC
                LIMIT 1";
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':front_id', (int)$frontId, PDO::PARAM_INT);
            $stmt->bindValue(':as_of', $asOfDate);
            $stmt->execute();
            $val = $stmt->fetchColumn();
            return $val !== false ? floatval($val) : 0.0;
        } catch (Exception $e) {
            return 0.0;
        }
    }

    // Upsert de saldo inicial por frente e data
    public function upsertOpeningBalance($frontId, $balanceDate, $value, $unit = 'toneladas', $notes = null, $userId = null) {
        $sql = "INSERT INTO {$this->table} (front_id, balance_date, opening_balance_value, unit, notes, created_by, updated_by)
                VALUES (:front_id, :balance_date, :value, :unit, :notes, :created_by, :updated_by)
                ON DUPLICATE KEY UPDATE
                    opening_balance_value = VALUES(opening_balance_value),
                    unit = VALUES(unit),
                    notes = VALUES(notes),
                    updated_by = VALUES(updated_by)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':front_id', (int)$frontId, PDO::PARAM_INT);
        $stmt->bindValue(':balance_date', $balanceDate);
        $stmt->bindValue(':value', $value);
        $stmt->bindValue(':unit', $unit);
        $stmt->bindValue(':notes', $notes);
        $stmt->bindValue(':created_by', $userId ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(':updated_by', $userId ?? null);
        return $stmt->execute();
    }

    private function getProducedOnDate($frontId, $date, $clientId = null) {
        try {
            // Produção diária por frente (cliente opcional)
            $sql = "SELECT COALESCE(SUM(produced_value), 0) 
                    FROM daily_production 
                    WHERE front_id = :front_id AND production_date = :d";
            if (!is_null($clientId)) {
                $sql .= " AND (client_id = :client_id OR (client_id IS NULL AND :client_id IS NULL))";
            }
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':front_id', (int)$frontId, PDO::PARAM_INT);
            $stmt->bindValue(':d', $date);
            if (!is_null($clientId)) {
                $stmt->bindValue(':client_id', $clientId, PDO::PARAM_INT);
            }
            $stmt->execute();
            $val = $stmt->fetchColumn();
            return $val !== false ? floatval($val) : 0.0;
        } catch (Exception $e) {
            return 0.0;
        }
    }

    private function getDeliveredOnDate($frontId, $date, $clientId = null) {
        try {
            // Entregas diárias por frente e cliente (cliente obrigatório na tabela)
            $sql = "SELECT COALESCE(SUM(delivered_value), 0) 
                    FROM daily_delivery 
                    WHERE front_id = :front_id AND delivery_date = :d";
            if (!is_null($clientId)) {
                $sql .= " AND client_id = :client_id";
            }
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':front_id', (int)$frontId, PDO::PARAM_INT);
            $stmt->bindValue(':d', $date);
            if (!is_null($clientId)) {
                $stmt->bindValue(':client_id', $clientId, PDO::PARAM_INT);
            }
            $stmt->execute();
            $val = $stmt->fetchColumn();
            return $val !== false ? floatval($val) : 0.0;
        } catch (Exception $e) {
            return 0.0;
        }
    }
}
?>
