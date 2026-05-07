<?php

return new class {
    public string $id = '20260422123000_add_job_candidates_hire_date';

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

    public function up(PDO $db): void {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (!$this->tableExists($db, 'job_candidates')) {
            throw new RuntimeException('Tabela job_candidates não encontrada.');
        }
        if (!$this->columnExists($db, 'job_candidates', 'hire_date')) {
            $db->exec('ALTER TABLE job_candidates ADD COLUMN hire_date DATE NULL');
        }
    }

    public function down(PDO $db): void {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($this->tableExists($db, 'job_candidates') && $this->columnExists($db, 'job_candidates', 'hire_date')) {
            $db->exec('ALTER TABLE job_candidates DROP COLUMN hire_date');
        }
    }
};
