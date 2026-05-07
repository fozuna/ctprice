<?php

return new class {
    public string $id = '20260401140505_refactor_stage_to_stage_id_with_stages_table';

    private function tableExists(PDO $db, string $table): bool {
        $st = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
        $st->execute([':t' => $table]);
        return (int)$st->fetchColumn() > 0;
    }

    private function columnExists(PDO $db, string $table, string $col): bool {
        $st = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
        $st->execute([':t' => $table, ':c' => $col]);
        return (int)$st->fetchColumn() > 0;
    }

    private function indexExists(PDO $db, string $table, string $index): bool {
        $st = $db->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i");
        $st->execute([':t' => $table, ':i' => $index]);
        return (int)$st->fetchColumn() > 0;
    }

    private function fkExists(PDO $db, string $table, string $fk): bool {
        $st = $db->prepare("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :t AND CONSTRAINT_NAME = :c");
        $st->execute([':t' => $table, ':c' => $fk]);
        return (int)$st->fetchColumn() > 0;
    }

    public function up(PDO $db): void {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $db->exec("CREATE TABLE IF NOT EXISTS stages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(32) NOT NULL,
            name VARCHAR(80) NOT NULL,
            color VARCHAR(16) NULL,
            position INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_stages_code (code)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $db->exec("INSERT INTO stages (code, name, color, position, active) VALUES
            ('RECEBIDO', 'Recebidos', '#6b7280', 10, 1),
            ('IA', 'Analisados por IA', '#3b82f6', 20, 1),
            ('RH', 'Analisados RH', '#6366f1', 30, 1),
            ('REPROV_PESQ', 'Reprovados na Pesquisa', '#a855f7', 35, 1),
            ('ENTREVISTA', 'Entrevistas Marcadas', '#f59e0b', 40, 1),
            ('ENT_AGENDADA', 'Entrevista Agendada', '#f59e0b', 42, 1),
            ('ENT_CONFIRMADA', 'Entrevista Confirmada', '#10b981', 44, 1),
            ('POS_ENTREVISTA', 'Pós-Entrevista', '#3b82f6', 46, 1),
            ('REPROV_ENT', 'Reprovados na Entrevista', '#fb7185', 70, 1),
            ('TALENTOS', 'Banco de Talentos', '#14b8a6', 80, 1),
            ('DISPENSADO', 'Dispensados', '#ef4444', 90, 1),
            ('CONTRATADO', 'Contratados', '#10b981', 100, 1)
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            color = VALUES(color),
            position = VALUES(position),
            active = VALUES(active)");

        if (!$this->columnExists($db, 'job_candidates', 'stage_id')) {
            $db->exec('ALTER TABLE job_candidates ADD COLUMN stage_id INT NULL');
        }

        if (!$this->columnExists($db, 'job_candidates', 'sort_order')) {
            $db->exec('ALTER TABLE job_candidates ADD COLUMN sort_order INT NULL');
        }

        $db->exec("UPDATE job_candidates c
                    JOIN stages s ON s.code COLLATE utf8mb4_general_ci = c.stage COLLATE utf8mb4_general_ci
                       SET c.stage_id = s.id
                     WHERE c.stage_id IS NULL");

        $db->exec("UPDATE job_candidates c
                    JOIN stages s ON s.code = 'RECEBIDO'
                       SET c.stage_id = s.id,
                           c.stage = IF(c.stage IS NULL OR c.stage = '', 'RECEBIDO', c.stage)
                     WHERE c.stage_id IS NULL");

        $db->exec('ALTER TABLE job_candidates MODIFY COLUMN stage_id INT NOT NULL');

        if (!$this->indexExists($db, 'job_candidates', 'idx_job_candidates_stage_id')) {
            $db->exec('ALTER TABLE job_candidates ADD INDEX idx_job_candidates_stage_id (stage_id)');
        }
        if (!$this->indexExists($db, 'job_candidates', 'idx_job_candidates_stageid_sort_order')) {
            $db->exec('ALTER TABLE job_candidates ADD INDEX idx_job_candidates_stageid_sort_order (stage_id, sort_order)');
        }
        if (!$this->fkExists($db, 'job_candidates', 'fk_job_candidates_stage_id')) {
            $db->exec('ALTER TABLE job_candidates ADD CONSTRAINT fk_job_candidates_stage_id FOREIGN KEY (stage_id) REFERENCES stages(id)');
        }

        $db->exec("CREATE TABLE IF NOT EXISTS job_candidate_stage_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            candidate_id INT NOT NULL,
            from_stage_id INT NULL,
            to_stage_id INT NULL,
            from_stage VARCHAR(32) NULL,
            to_stage VARCHAR(32) NULL,
            note TEXT NOT NULL,
            interview_at DATETIME NULL,
            interview_link VARCHAR(255) NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_candidate(created_at, candidate_id)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        if (!$this->columnExists($db, 'job_candidate_stage_logs', 'from_stage_id')) {
            $db->exec('ALTER TABLE job_candidate_stage_logs ADD COLUMN from_stage_id INT NULL');
        }
        if (!$this->columnExists($db, 'job_candidate_stage_logs', 'to_stage_id')) {
            $db->exec('ALTER TABLE job_candidate_stage_logs ADD COLUMN to_stage_id INT NULL');
        }
        if (!$this->columnExists($db, 'job_candidate_stage_logs', 'interview_link')) {
            $db->exec('ALTER TABLE job_candidate_stage_logs ADD COLUMN interview_link VARCHAR(255) NULL');
        }

        $db->exec("UPDATE job_candidate_stage_logs l
               LEFT JOIN stages fs ON fs.code COLLATE utf8mb4_general_ci = l.from_stage COLLATE utf8mb4_general_ci
               LEFT JOIN stages ts ON ts.code COLLATE utf8mb4_general_ci = l.to_stage COLLATE utf8mb4_general_ci
                      SET l.from_stage_id = fs.id,
                          l.to_stage_id = ts.id
                    WHERE l.from_stage_id IS NULL OR l.to_stage_id IS NULL");

        if (!$this->indexExists($db, 'job_candidate_stage_logs', 'idx_stage_ids')) {
            $db->exec('ALTER TABLE job_candidate_stage_logs ADD INDEX idx_stage_ids (candidate_id, created_at, to_stage_id)');
        }
    }

    public function down(PDO $db): void {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if ($this->tableExists($db, 'job_candidates') && $this->columnExists($db, 'job_candidates', 'stage_id')) {
            if ($this->tableExists($db, 'stages')) {
                $db->exec("UPDATE job_candidates c
                            JOIN stages s ON s.id = c.stage_id
                               SET c.stage = s.code
                             WHERE s.code IN ('RECEBIDO','IA','RH','ENTREVISTA','DISPENSADO','TALENTOS','CONTRATADO')");
            }

            if ($this->fkExists($db, 'job_candidates', 'fk_job_candidates_stage_id')) {
                $db->exec('ALTER TABLE job_candidates DROP FOREIGN KEY fk_job_candidates_stage_id');
            }
            if ($this->indexExists($db, 'job_candidates', 'idx_job_candidates_stage_id')) {
                $db->exec('ALTER TABLE job_candidates DROP INDEX idx_job_candidates_stage_id');
            }
            if ($this->indexExists($db, 'job_candidates', 'idx_job_candidates_stageid_sort_order')) {
                $db->exec('ALTER TABLE job_candidates DROP INDEX idx_job_candidates_stageid_sort_order');
            }
            $db->exec('ALTER TABLE job_candidates DROP COLUMN stage_id');
        }

        if ($this->tableExists($db, 'job_candidate_stage_logs')) {
            if ($this->indexExists($db, 'job_candidate_stage_logs', 'idx_stage_ids')) {
                $db->exec('ALTER TABLE job_candidate_stage_logs DROP INDEX idx_stage_ids');
            }
            if ($this->columnExists($db, 'job_candidate_stage_logs', 'from_stage_id')) {
                $db->exec('ALTER TABLE job_candidate_stage_logs DROP COLUMN from_stage_id');
            }
            if ($this->columnExists($db, 'job_candidate_stage_logs', 'to_stage_id')) {
                $db->exec('ALTER TABLE job_candidate_stage_logs DROP COLUMN to_stage_id');
            }
        }
    }
};
