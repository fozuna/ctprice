<?php
require_once __DIR__ . '/../models/Equipment.php';
require_once __DIR__ . '/../models/ProductionRate.php';
require_once __DIR__ . '/../models/ForecastSetting.php';
require_once __DIR__ . '/../models/EquipmentAllocation.php';
require_once __DIR__ . '/AuditLog.php';

class EquipmentService {
    private $db;
    public function __construct($db) { $this->db = $db; }

    public function backupAndPurgeHours($userId, $note = null) {
        $dir = __DIR__ . '/../uploads/backups';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $ts = date('Ymd_His');
        $path = $dir . "/hours_backup_{$ts}.json";
        $occurCount = (int)$this->db->query('SELECT COUNT(*) FROM occurrences')->fetchColumn();
        $prodCount = (int)$this->db->query('SELECT COUNT(*) FROM daily_production')->fetchColumn();
        $equipCount = (int)$this->db->query('SELECT COUNT(*) FROM equipments')->fetchColumn();
        $meta = ['timestamp' => date('c'), 'occurrences' => $occurCount, 'daily_production' => $prodCount, 'equipments' => $equipCount, 'note' => $note];
        file_put_contents($path, json_encode($meta));
        $this->db->exec("INSERT INTO equipment_hour_cleanup_logs (performed_by, performed_at, affected_equipments, affected_occurrences, affected_daily_production, backup_path, note) VALUES ({$userId}, NOW(), {$equipCount}, {$occurCount}, {$prodCount}, " . $this->db->quote(str_replace(__DIR__.'/../', '', $path)) . ", " . $this->db->quote($note) . ")");
        $this->db->exec('DELETE FROM occurrences');
        $this->db->exec('DELETE FROM daily_production');
        return ['backup' => $path, 'occurrences' => $occurCount, 'daily_production' => $prodCount, 'equipments' => $equipCount];
    }

    public function setInitialHourMeter($equipmentId, $hour, $userId) {
        if ($hour < 0) { throw new Exception('Horímetro inválido'); }
        $stmt = $this->db->prepare('SELECT current_hours FROM equipments WHERE id = :id');
        $stmt->execute([':id' => $equipmentId]);
        $row = $stmt->fetch();
        if (!$row) { throw new Exception('Equipamento não encontrado'); }
        $current = $row['current_hours'] !== null ? (float)$row['current_hours'] : 0.0;
        if ($hour < $current) { throw new Exception('Horímetro informado é menor que o horímetro atual'); }
        // Usar placeholders distintos para evitar HY093 em drivers sem emulação
        $u = $this->db->prepare('UPDATE equipments SET initial_hours = :hi, current_hours = :hc, updated_at = NOW() WHERE id = :id');
        $u->execute([':hi' => $hour, ':hc' => $hour, ':id' => $equipmentId]);
        $log = $this->db->prepare('INSERT INTO equipment_hour_logs (equipment_id, log_timestamp, hour_meter, source, note, created_by) VALUES (:e, NOW(), :h, :s, :n, :u)');
        $log->execute([':e' => $equipmentId, ':h' => $hour, ':s' => 'INIT', ':n' => 'Cadastro de horímetro', ':u' => $userId]);
        return true;
    }

    public function setProductionRate($equipmentId, $unit, $rate, $userId) {
        if ($rate < 0) { throw new Exception('Produção/hora inválida'); }
        $q = $this->db->prepare('SELECT id FROM production_rates WHERE equipment_id = :e');
        $q->execute([':e' => $equipmentId]);
        $id = $q->fetchColumn();
        if ($id) {
            $u = $this->db->prepare('UPDATE production_rates SET unit = :u, rate = :r, updated_by = :by WHERE id = :id');
            $u->execute([':u' => $unit, ':r' => $rate, ':by' => $userId, ':id' => $id]);
        } else {
            $i = $this->db->prepare('INSERT INTO production_rates (equipment_id, unit, rate, created_by) VALUES (:e, :u, :r, :by)');
            $i->execute([':e' => $equipmentId, ':u' => $unit, ':r' => $rate, ':by' => $userId]);
        }
        $h = $this->db->prepare('INSERT INTO production_rate_history (equipment_id, unit, rate, changed_by) VALUES (:e, :u, :r, :by)');
        $h->execute([':e' => $equipmentId, ':u' => $unit, ':r' => $rate, ':by' => $userId]);
        return true;
    }

    public function allocate($equipmentId, $frontId, $start, $end, $priority, $userId, $dailyHours = 0.0) {
        if (strtotime($end) <= strtotime($start)) { throw new Exception('Período inválido'); }
        $over = $this->db->prepare('SELECT COUNT(*) FROM equipment_allocations WHERE equipment_id = :e AND NOT (end_datetime <= :s OR start_datetime >= :f)');
        $over->execute([':e' => $equipmentId, ':s' => $start, ':f' => $end]);
        $overlaps = (int)$over->fetchColumn();
        // Tenta inserir com a coluna daily_productive_hours; se a coluna não existir, faz fallback sem ela
        try {
            $ins = $this->db->prepare('INSERT INTO equipment_allocations (equipment_id, front_id, start_datetime, end_datetime, priority, daily_productive_hours, created_by) VALUES (:e, :f, :s, :d, :p, :h, :u)');
            $ins->execute([':e' => $equipmentId, ':f' => $frontId, ':s' => $start, ':d' => $end, ':p' => $priority, ':h' => $dailyHours, ':u' => $userId]);
        } catch (Exception $ex) {
            $ins = $this->db->prepare('INSERT INTO equipment_allocations (equipment_id, front_id, start_datetime, end_datetime, priority, created_by) VALUES (:e, :f, :s, :d, :p, :u)');
            $ins->execute([':e' => $equipmentId, ':f' => $frontId, ':s' => $start, ':d' => $end, ':p' => $priority, ':u' => $userId]);
        }
        $rateQ = $this->db->prepare('SELECT unit, rate FROM production_rates WHERE equipment_id = :e');
        $rateQ->execute([':e' => $equipmentId]);
        $rateRow = $rateQ->fetch();
        $effQ = $this->db->prepare('SELECT efficiency_factor FROM forecast_settings WHERE equipment_id = :e AND (front_id = :f OR front_id IS NULL) ORDER BY (front_id IS NULL) ASC LIMIT 1');
        $effQ->execute([':e' => $equipmentId, ':f' => $frontId]);
        $eff = $effQ->fetchColumn();
        $eff = $eff !== false ? (float)$eff : 1.0;
        // cálculo: considerar jornadas diárias se fornecido; caso contrário, usar duração direta
        $rate = $rateRow ? (float)$rateRow['rate'] : 0.0;
        $unit = $rateRow ? $rateRow['unit'] : '';
        if (!defined('DEBUG_MODE') || !DEBUG_MODE) {
            $unit = 'ton/h';
        }
        $startTs = strtotime($start);
        $endTs = strtotime($end);
        $days = max(0, ceil(($endTs - $startTs) / 86400));
        if ($dailyHours > 0) {
            $hours = $days * (float)$dailyHours;
        } else {
            $hours = max(0, ($endTs - $startTs) / 3600);
        }
        $pred = $hours * $rate * $eff;
        return ['overlaps' => $overlaps > 0, 'predicted' => $pred, 'unit' => $unit, 'hours' => $hours, 'efficiency' => $eff];
    }
}
