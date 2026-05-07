<?php

class JobCandidateService
{
    public static function normalizeCpf(string $cpf): string
    {
        return preg_replace('/\D+/', '', $cpf);
    }

    public static function isValidCpf(string $cpf): bool
    {
        $cpf = self::normalizeCpf($cpf);
        if (!preg_match('/^\d{11}$/', $cpf)) {
            return false;
        }
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += (int) $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ((int) $cpf[$t] !== $d) {
                return false;
            }
        }
        return true;
    }

    public static function ensureCpfSchema(PDO $db): void
    {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_candidates' AND COLUMN_NAME = 'cpf'");
            $stmt->execute();
            if ((int) $stmt->fetchColumn() === 0) {
                $db->exec("ALTER TABLE job_candidates ADD COLUMN cpf CHAR(11) NULL AFTER phone");
            }
        } catch (Throwable $e) {
        }
        try {
            $db->exec("ALTER TABLE job_candidates ADD UNIQUE INDEX uq_job_candidates_vacancy_cpf (vacancy_id, cpf)");
        } catch (Throwable $e) {
        }
        try {
            $db->exec("ALTER TABLE job_candidates ADD INDEX idx_job_candidates_cpf (cpf)");
        } catch (Throwable $e) {
        }
    }

    public static function hasDuplicateCpfForVacancy(PDO $db, int $vacancyId, string $cpf): bool
    {
        $cpf = self::normalizeCpf($cpf);
        if ($cpf === '' || $vacancyId <= 0) {
            return false;
        }
        $stmt = $db->prepare('SELECT 1 FROM job_candidates WHERE vacancy_id = :vacancy_id AND cpf = :cpf LIMIT 1');
        $stmt->bindValue(':vacancy_id', $vacancyId, PDO::PARAM_INT);
        $stmt->bindValue(':cpf', $cpf);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }
}

