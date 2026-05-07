<?php
require_once __DIR__ . '/../classes/BaseModel.php';

class CostCenter extends BaseModel
{
    protected $table = 'cost_centers';
    protected $primaryKey = 'id';

    private function tableExists(string $table): bool
    {
        try {
            $driver = (string)$this->conn->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') {
                $st = $this->conn->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table");
                $st->execute([':table' => $table]);
                return (int)$st->fetchColumn() > 0;
            }

            if ($driver === 'sqlite') {
                $st = $this->conn->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = :table");
                $st->execute([':table' => $table]);
                return (int)$st->fetchColumn() > 0;
            }
        } catch (Throwable $e) {
        }

        return false;
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $driver = (string)$this->conn->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') {
                $st = $this->conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column");
                $st->execute([':table' => $table, ':column' => $column]);
                return (int)$st->fetchColumn() > 0;
            }

            if ($driver === 'sqlite') {
                $st = $this->conn->query("PRAGMA table_info(" . $table . ")");
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    if (($row['name'] ?? null) === $column) {
                        return true;
                    }
                }
            }
        } catch (Throwable $e) {
        }

        return false;
    }

    public function hasUserCostCenterSchema(): bool
    {
        return $this->tableExists('users') && $this->columnExists('users', 'cost_center_id');
    }

    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $code = trim($code);
        if ($code === '') {
            return false;
        }

        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE code = :code";
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':code', $code);
        if ($excludeId !== null) {
            $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return (int)$stmt->fetchColumn() > 0;
    }

    public function parentExists(int $parentId): bool
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM {$this->table} WHERE id = :id");
        $stmt->bindValue(':id', $parentId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn() > 0;
    }

    public function countLinkedUsers(int $costCenterId): int
    {
        if (!$this->hasUserCostCenterSchema()) {
            return 0;
        }

        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM users WHERE cost_center_id = :id");
        $stmt->bindValue(':id', $costCenterId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function hasLinkedUsers(int $costCenterId): bool
    {
        return $this->countLinkedUsers($costCenterId) > 0;
    }

    public function findAllWithStats(): array
    {
        $hasUserCostCenterSchema = $this->hasUserCostCenterSchema();
        $sql = "SELECT cc.*,
                       parent.name AS parent_name,
                       " . ($hasUserCostCenterSchema ? "COUNT(u.id)" : "0") . " AS linked_users
                FROM {$this->table} cc
                LEFT JOIN {$this->table} parent ON parent.id = cc.parent_id";

        if ($hasUserCostCenterSchema) {
            $sql .= " LEFT JOIN users u ON u.cost_center_id = cc.id";
        }

        $sql .= " GROUP BY cc.id
                  ORDER BY cc.name ASC";

        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll();
    }

    public function findActive(): array
    {
        return $this->findAll(['active' => 1], 'name ASC');
    }

    public function createCostCenter(array $data)
    {
        $data = $this->normalizeData($data);
        if ($data['name'] === '') {
            throw new Exception('Nome é obrigatório');
        }
        if ($this->codeExists((string)($data['code'] ?? ''))) {
            throw new Exception('Código já está em uso');
        }
        if (!empty($data['parent_id']) && !$this->parentExists((int)$data['parent_id'])) {
            throw new Exception('Centro de custo pai não encontrado');
        }
        return $this->create($data);
    }

    public function updateCostCenter(int $id, array $data): bool
    {
        $data = $this->normalizeData($data);
        if ($data['name'] === '') {
            throw new Exception('Nome é obrigatório');
        }
        if ($this->codeExists((string)($data['code'] ?? ''), $id)) {
            throw new Exception('Código já está em uso');
        }
        if (!empty($data['parent_id'])) {
            if ((int)$data['parent_id'] === $id) {
                throw new Exception('O centro de custo não pode ser pai de si mesmo');
            }
            if (!$this->parentExists((int)$data['parent_id'])) {
                throw new Exception('Centro de custo pai não encontrado');
            }
        }
        return $this->update($id, $data);
    }

    public function normalizeData(array $data): array
    {
        return [
            'name' => trim((string)($data['name'] ?? '')),
            'code' => ($code = trim((string)($data['code'] ?? ''))) !== '' ? $code : null,
            'parent_id' => !empty($data['parent_id']) ? (int)$data['parent_id'] : null,
            'department' => ($department = trim((string)($data['department'] ?? ''))) !== '' ? $department : null,
            'active' => isset($data['active']) ? (int)$data['active'] : 1,
        ];
    }
}
?>
