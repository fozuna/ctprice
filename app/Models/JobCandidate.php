<?php

class JobCandidate extends BaseModel {
    protected $table = 'job_candidates';

    public static function hasHireDateColumn(PDO $db): bool
    {
        $sql = "SELECT COUNT(*)
                  FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'job_candidates'
                   AND COLUMN_NAME = 'hire_date'";
        return (int)$db->query($sql)->fetchColumn() > 0;
    }

    public static function ensureHireDateColumn(PDO $db): void
    {
        if (self::hasHireDateColumn($db)) {
            return;
        }
        $db->exec("ALTER TABLE job_candidates ADD COLUMN hire_date DATE NULL");
    }
}
