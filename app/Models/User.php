<?php
/**
 * Modelo User
 * Sistema BDO - Controle de Maquinários
 */

class User extends BaseModel {
    protected $table = 'users';

    public const FUNCTIONAL_PROFILES = [
        'SOLICITANTE',
        'APROVADOR',
    ];

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
                foreach ($st->fetchAll() as $row) {
                    if (($row['name'] ?? null) === $column) {
                        return true;
                    }
                }
            }
        } catch (Throwable $e) {
        }

        return false;
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            if ((string)$this->conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $st = $this->conn->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index_name");
                $st->execute([':table' => $table, ':index_name' => $index]);
                return (int)$st->fetchColumn() > 0;
            }
        } catch (Throwable $e) {
        }

        return false;
    }

    private function fkExists(string $table, string $constraint): bool
    {
        try {
            if ((string)$this->conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $st = $this->conn->prepare("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :table AND CONSTRAINT_NAME = :constraint_name");
                $st->execute([':table' => $table, ':constraint_name' => $constraint]);
                return (int)$st->fetchColumn() > 0;
            }
        } catch (Throwable $e) {
        }

        return false;
    }

    public function hasFunctionalProfileSchema(): bool
    {
        return $this->tableExists('cost_centers')
            && $this->columnExists('users', 'functional_profile')
            && $this->columnExists('users', 'cost_center_id');
    }

    public function ensureFunctionalProfileSchema(): void
    {
        if ((string)$this->conn->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return;
        }

        if (!$this->tableExists('cost_centers')) {
            $this->conn->exec("
                CREATE TABLE cost_centers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(120) NOT NULL,
                    code VARCHAR(50) NULL,
                    parent_id INT NULL,
                    department VARCHAR(120) NULL,
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    CONSTRAINT fk_cc_parent FOREIGN KEY (parent_id) REFERENCES cost_centers(id) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        if (!$this->columnExists('users', 'functional_profile')) {
            $this->conn->exec("ALTER TABLE users ADD COLUMN functional_profile ENUM('SOLICITANTE','APROVADOR') NOT NULL DEFAULT 'SOLICITANTE' AFTER role_id");
        }

        if (!$this->columnExists('users', 'cost_center_id')) {
            $this->conn->exec("ALTER TABLE users ADD COLUMN cost_center_id INT NULL AFTER front_id");
        }

        if (!$this->indexExists('cost_centers', 'idx_cost_centers_code')) {
            $this->conn->exec("ALTER TABLE cost_centers ADD INDEX idx_cost_centers_code (code)");
        }

        $st = $this->conn->prepare("SELECT id FROM cost_centers WHERE code = :code LIMIT 1");
        $st->execute([':code' => 'GERAL']);
        $defaultCostCenterId = (int)($st->fetchColumn() ?: 0);
        if ($defaultCostCenterId <= 0) {
            $ins = $this->conn->prepare("INSERT INTO cost_centers (name, code, department, active) VALUES (:name, :code, :department, 1)");
            $ins->execute([
                ':name' => 'Centro de Custo Geral',
                ':code' => 'GERAL',
                ':department' => 'Administrativo',
            ]);
            $defaultCostCenterId = (int)$this->conn->lastInsertId();
        }

        $this->conn->exec("
            UPDATE users
            SET functional_profile = CASE
                WHEN role_id IN (1, 2, 5, 6) THEN 'APROVADOR'
                ELSE 'SOLICITANTE'
            END
            WHERE functional_profile IS NULL OR functional_profile = ''
        ");

        $up = $this->conn->prepare("UPDATE users SET cost_center_id = :cc WHERE cost_center_id IS NULL OR cost_center_id = 0");
        $up->execute([':cc' => $defaultCostCenterId]);

        $this->conn->exec("ALTER TABLE users MODIFY functional_profile ENUM('SOLICITANTE','APROVADOR') NOT NULL");
        $this->conn->exec("ALTER TABLE users MODIFY cost_center_id INT NOT NULL");

        if (!$this->indexExists('users', 'idx_users_cost_center_id')) {
            $this->conn->exec("ALTER TABLE users ADD INDEX idx_users_cost_center_id (cost_center_id)");
        }

        if (!$this->indexExists('users', 'idx_users_functional_profile')) {
            $this->conn->exec("ALTER TABLE users ADD INDEX idx_users_functional_profile (functional_profile)");
        }

        if (!$this->fkExists('users', 'fk_users_cost_center')) {
            $this->conn->exec("ALTER TABLE users ADD CONSTRAINT fk_users_cost_center FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id) ON DELETE RESTRICT ON UPDATE CASCADE");
        }
    }
    
    /**
     * Buscar usuários com informações de role e frente
     */
    public function findAllWithDetails($conditions = []) {
        $hasCostCenterSchema = $this->hasFunctionalProfileSchema();

        $sql = "SELECT u.*, r.name as role_name, f.name as front_name, " . ($hasCostCenterSchema ? "cc.name as cost_center_name" : "NULL as cost_center_name") . "
                FROM {$this->table} u 
                LEFT JOIN roles r ON u.role_id = r.id 
                LEFT JOIN fronts f ON u.front_id = f.id";

        if ($hasCostCenterSchema) {
            $sql .= " LEFT JOIN cost_centers cc ON u.cost_center_id = cc.id";
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', array_map(function($key) {
                return "u.{$key} = :{$key}";
            }, array_keys($conditions)));
        }
        
        $sql .= " ORDER BY u.name";
        
        $stmt = $this->conn->prepare($sql);
        
        foreach ($conditions as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Buscar usuários com informações de role e frente (alias para findAllWithDetails)
     */
    public function findWithRoleAndFront($conditions = []) {
        $whereConditions = [];
        $params = [];
        $counter = 0;
        $hasFunctionalProfileColumn = $this->columnExists('users', 'functional_profile');
        $hasCostCenterSchema = $this->hasFunctionalProfileSchema();
        
        // Processar condições
        foreach ($conditions as $key => $value) {
            if (is_numeric($key)) {
                // Condição customizada (como LIKE)
                $whereConditions[] = $value;
            } else {
                if ($key === 'u.functional_profile' && !$hasFunctionalProfileColumn) {
                    continue;
                }
                if ($key === 'u.cost_center_id' && !$hasCostCenterSchema) {
                    continue;
                }
                $placeholder = ':p' . $counter++;
                $whereConditions[] = "$key = $placeholder";
                $params[$placeholder] = $value;
            }
        }
        
        $sql = "SELECT u.*, f.name as front_name, " . ($hasCostCenterSchema ? "cc.name as cost_center_name" : "NULL as cost_center_name") . "
                FROM {$this->table} u 
                LEFT JOIN fronts f ON u.front_id = f.id";

        if ($hasCostCenterSchema) {
            $sql .= " LEFT JOIN cost_centers cc ON u.cost_center_id = cc.id";
        }
        
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }
        
        $sql .= " ORDER BY u.name";
        
        $stmt = $this->conn->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Buscar usuário por email
     */
    public function findByEmail($email) {
        return $this->findOne(['email' => $email]);
    }
    
    /**
     * Buscar operadores ativos
     */
    public function findOperators() {
        return $this->findAllWithDetails(['active' => 1, 'role_id' => ROLE_OPERADOR]);
    }
    
    /**
     * Buscar usuários por frente
     */
    public function findByFront($frontId) {
        return $this->findAllWithDetails(['front_id' => $frontId, 'active' => 1]);
    }
    
    /**
     * Verificar se email já existe
     */
    public function emailExists($email, $excludeId = null) {
        $conditions = ['email' => $email];
        
        if ($excludeId) {
            $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE email = :email AND id != :exclude_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':email', $email);
            $stmt->bindValue(':exclude_id', $excludeId);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result['total'] > 0;
        }
        
        return $this->exists($conditions);
    }
    
    /**
     * Criar usuário com validações
     */
    public function createUser($data) {
        $data = $this->validateAndNormalizeUserData($data);

        // Verificar se email já existe
        if ($this->emailExists($data['email'])) {
            throw new Exception('Email já está em uso');
        }
        
        // Hash da senha
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        return $this->create($data);
    }
    
    /**
     * Atualizar usuário com validações
     */
    public function updateUser($id, $data) {
        $data = $this->validateAndNormalizeUserData($data, true);

        // Verificar se email já existe (excluindo o próprio usuário)
        if (isset($data['email']) && $this->emailExists($data['email'], $id)) {
            throw new Exception('Email já está em uso');
        }
        
        // Hash da senha se fornecida
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } else {
            // Remover senha vazia para não atualizar
            unset($data['password']);
        }
        
        return $this->update($id, $data);
    }

    public function functionalProfileLabel(?string $profile): string
    {
        return $profile === 'APROVADOR' ? 'Aprovador' : 'Solicitante';
    }

    private function validateAndNormalizeUserData(array $data, bool $isUpdate = false): array
    {
        $normalized = $data;

        if (array_key_exists('name', $normalized)) {
            $normalized['name'] = trim((string)$normalized['name']);
            if ($normalized['name'] === '') {
                throw new Exception('Nome é obrigatório');
            }
        }

        if (array_key_exists('email', $normalized)) {
            $normalized['email'] = trim((string)$normalized['email']);
            if ($normalized['email'] === '' || !filter_var($normalized['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email inválido');
            }
        }

        if (!$isUpdate || array_key_exists('password', $normalized)) {
            $password = (string)($normalized['password'] ?? '');
            if (!$isUpdate && $password === '') {
                throw new Exception('Senha é obrigatória');
            }
        }

        if (array_key_exists('role_id', $normalized)) {
            $normalized['role_id'] = (int)$normalized['role_id'];
            if ($normalized['role_id'] <= 0) {
                throw new Exception('Perfil de acesso é obrigatório');
            }
        }

        if (array_key_exists('front_id', $normalized)) {
            $normalized['front_id'] = !empty($normalized['front_id']) ? (int)$normalized['front_id'] : null;
        }

        if ($this->columnExists('users', 'cost_center_id') && array_key_exists('cost_center_id', $normalized)) {
            $normalized['cost_center_id'] = (int)$normalized['cost_center_id'];
            if ($normalized['cost_center_id'] <= 0) {
                throw new Exception('Centro de custo é obrigatório');
            }
        } elseif ($this->columnExists('users', 'cost_center_id') && !$isUpdate) {
            throw new Exception('Centro de custo é obrigatório');
        }

        if ($this->columnExists('users', 'functional_profile') && array_key_exists('functional_profile', $normalized)) {
            $normalized['functional_profile'] = strtoupper(trim((string)$normalized['functional_profile']));
            if (!in_array($normalized['functional_profile'], self::FUNCTIONAL_PROFILES, true)) {
                throw new Exception('Perfil funcional inválido');
            }
        } elseif ($this->columnExists('users', 'functional_profile') && !$isUpdate) {
            throw new Exception('Perfil funcional é obrigatório');
        }

        return $normalized;
    }
}
?>
