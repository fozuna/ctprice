<?php

return new class {
    public string $id = '20260424110000_add_user_functional_profile_and_cost_center';

    private function tableExists(PDO $db, string $table): bool
    {
        $st = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
        $st->execute([':t' => $table]);
        return (int)$st->fetchColumn() > 0;
    }

    private function columnExists(PDO $db, string $table, string $column): bool
    {
        $st = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
        $st->execute([':t' => $table, ':c' => $column]);
        return (int)$st->fetchColumn() > 0;
    }

    private function indexExists(PDO $db, string $table, string $index): bool
    {
        $st = $db->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i");
        $st->execute([':t' => $table, ':i' => $index]);
        return (int)$st->fetchColumn() > 0;
    }

    private function fkExists(PDO $db, string $table, string $constraint): bool
    {
        $st = $db->prepare("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :t AND CONSTRAINT_NAME = :c");
        $st->execute([':t' => $table, ':c' => $constraint]);
        return (int)$st->fetchColumn() > 0;
    }

    private function ensureCostCentersTable(PDO $db): void
    {
        if (!$this->tableExists($db, 'cost_centers')) {
            $db->exec("
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

        if (!$this->indexExists($db, 'cost_centers', 'idx_cost_centers_code')) {
            $db->exec("ALTER TABLE cost_centers ADD INDEX idx_cost_centers_code (code)");
        }

        if (!$this->indexExists($db, 'cost_centers', 'idx_cost_centers_parent_id')) {
            $db->exec("ALTER TABLE cost_centers ADD INDEX idx_cost_centers_parent_id (parent_id)");
        }
    }

    private function ensureDefaultCostCenter(PDO $db): int
    {
        $st = $db->prepare("SELECT id FROM cost_centers WHERE code = :code LIMIT 1");
        $st->execute([':code' => 'GERAL']);
        $existingId = (int)($st->fetchColumn() ?: 0);
        if ($existingId > 0) {
            return $existingId;
        }

        $ins = $db->prepare("INSERT INTO cost_centers (name, code, department, active) VALUES (:name, :code, :department, 1)");
        $ins->execute([
            ':name' => 'Centro de Custo Geral',
            ':code' => 'GERAL',
            ':department' => 'Administrativo',
        ]);

        return (int)$db->lastInsertId();
    }

    public function up(PDO $db): void
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if (!$this->tableExists($db, 'users')) {
            throw new RuntimeException('Tabela users não encontrada.');
        }

        $this->ensureCostCentersTable($db);
        $defaultCostCenterId = $this->ensureDefaultCostCenter($db);

        if (!$this->columnExists($db, 'users', 'functional_profile')) {
            $db->exec("ALTER TABLE users ADD COLUMN functional_profile ENUM('SOLICITANTE','APROVADOR') NOT NULL DEFAULT 'SOLICITANTE' AFTER role_id");
        }

        if (!$this->columnExists($db, 'users', 'cost_center_id')) {
            $db->exec("ALTER TABLE users ADD COLUMN cost_center_id INT NULL AFTER front_id");
        }

        $upProfiles = $db->prepare("
            UPDATE users
            SET functional_profile = CASE
                WHEN role_id IN (1, 2, 5, 6) THEN 'APROVADOR'
                ELSE 'SOLICITANTE'
            END
            WHERE functional_profile IS NULL OR functional_profile = ''
        ");
        $upProfiles->execute();

        $upCostCenters = $db->prepare("UPDATE users SET cost_center_id = :cc WHERE cost_center_id IS NULL OR cost_center_id = 0");
        $upCostCenters->execute([':cc' => $defaultCostCenterId]);

        $db->exec("ALTER TABLE users MODIFY functional_profile ENUM('SOLICITANTE','APROVADOR') NOT NULL");
        $db->exec("ALTER TABLE users MODIFY cost_center_id INT NOT NULL");

        if (!$this->indexExists($db, 'users', 'idx_users_cost_center_id')) {
            $db->exec("ALTER TABLE users ADD INDEX idx_users_cost_center_id (cost_center_id)");
        }

        if (!$this->indexExists($db, 'users', 'idx_users_functional_profile')) {
            $db->exec("ALTER TABLE users ADD INDEX idx_users_functional_profile (functional_profile)");
        }

        if (!$this->fkExists($db, 'users', 'fk_users_cost_center')) {
            $db->exec("ALTER TABLE users ADD CONSTRAINT fk_users_cost_center FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id) ON DELETE RESTRICT ON UPDATE CASCADE");
        }
    }

    public function down(PDO $db): void
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if ($this->tableExists($db, 'users')) {
            if ($this->fkExists($db, 'users', 'fk_users_cost_center')) {
                $db->exec("ALTER TABLE users DROP FOREIGN KEY fk_users_cost_center");
            }
            if ($this->indexExists($db, 'users', 'idx_users_cost_center_id')) {
                $db->exec("ALTER TABLE users DROP INDEX idx_users_cost_center_id");
            }
            if ($this->indexExists($db, 'users', 'idx_users_functional_profile')) {
                $db->exec("ALTER TABLE users DROP INDEX idx_users_functional_profile");
            }
            if ($this->indexExists($db, 'cost_centers', 'idx_cost_centers_code')) {
                $db->exec("ALTER TABLE cost_centers DROP INDEX idx_cost_centers_code");
            }
            if ($this->columnExists($db, 'users', 'cost_center_id')) {
                $db->exec("ALTER TABLE users DROP COLUMN cost_center_id");
            }
            if ($this->columnExists($db, 'users', 'functional_profile')) {
                $db->exec("ALTER TABLE users DROP COLUMN functional_profile");
            }
        }
    }
};
