<?php

if (!function_exists('rhBuildDashboardMetrics')) {
    function rhBuildDashboardMetrics(PDO $db, string $periodFrom, string $periodTo, string $department = '', string $contractType = ''): array
    {
        $metrics = [
            'recruitment' => [
                'open_vacancies' => 0,
                'candidates_in_process' => 0,
                'total_hired' => 0,
                'avg_hire_time_days' => 0,
            ],
            'workforce' => ['active_employees' => 0, 'turnover_pct' => 0, 'absenteeism_pct' => 0],
            'training' => ['courses_running' => 0, 'certified_employees' => 0],
            'satisfaction' => ['climate_score' => 0]
        ];

        try {
            $whereVac = "v.status='ATIVA'";
            $paramsVac = [];
            if ($department !== '') { $whereVac .= " AND v.department = :dept"; $paramsVac[':dept'] = $department; }
            if ($contractType !== '') { $whereVac .= " AND v.contract_type = :ctype"; $paramsVac[':ctype'] = $contractType; }
            $sql = "SELECT COALESCE(SUM(GREATEST(v.total_offered - (
                        SELECT COUNT(*)
                          FROM job_candidates c
                          JOIN stages s ON s.id = c.stage_id
                         WHERE c.vacancy_id = v.id AND s.code = 'CONTRATADO'
                    ), 0)), 0) AS available
                    FROM job_vacancies v
                    WHERE $whereVac";
            $stmt = $db->prepare($sql);
            $stmt->execute($paramsVac);
            $metrics['recruitment']['open_vacancies'] = (int)($stmt->fetchColumn() ?: 0);
            if (function_exists('error_log')) { error_log('[rh-dashboard] open_vacancies calc ok'); }
        } catch (Throwable $e) {
            if (function_exists('error_log')) error_log('[rh-dashboard] open_vacancies error: ' . $e->getMessage());
        }

        try {
            $whereCand = "c.created_at BETWEEN :from AND :to";
            $paramsCand = [':from' => $periodFrom . ' 00:00:00', ':to' => $periodTo . ' 23:59:59'];
            $join = "";
            if ($department !== '' || $contractType !== '') {
                $join = "LEFT JOIN job_vacancies v ON v.id = c.vacancy_id";
                if ($department !== '') { $whereCand .= " AND v.department = :dept"; $paramsCand[':dept'] = $department; }
                if ($contractType !== '') { $whereCand .= " AND v.contract_type = :ctype"; $paramsCand[':ctype'] = $contractType; }
            }
            $stmt = $db->prepare("SELECT COUNT(*)
                                    FROM job_candidates c
                                    JOIN stages s ON s.id = c.stage_id
                                    $join
                                   WHERE $whereCand AND s.code NOT IN ('DISPENSADO','CONTRATADO')");
            $stmt->execute($paramsCand);
            $metrics['recruitment']['candidates_in_process'] = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
        }

        try {
            $whereHired = "s.code = 'CONTRATADO'";
            $paramsHired = [];
            if ($department !== '' || $contractType !== '') {
                $whereHired .= " AND v.id = c.vacancy_id";
                if ($department !== '') { $whereHired .= " AND v.department = :dept"; $paramsHired[':dept'] = $department; }
                if ($contractType !== '') { $whereHired .= " AND v.contract_type = :ctype"; $paramsHired[':ctype'] = $contractType; }
            }
            $stmt = $db->prepare("SELECT COUNT(*)
                                    FROM job_candidates c
                                    JOIN stages s ON s.id = c.stage_id
                               LEFT JOIN job_vacancies v ON v.id = c.vacancy_id
                                   WHERE $whereHired");
            $stmt->execute($paramsHired);
            $metrics['recruitment']['total_hired'] = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            if (function_exists('error_log')) error_log('[rh-dashboard] total_hired error: ' . $e->getMessage());
        }

        try {
            $stmt = $db->prepare("SELECT AVG(DATEDIFF(c.hire_date, c.created_at))
                                    FROM job_candidates c
                                    JOIN stages s ON s.id = c.stage_id
                                   WHERE s.code='CONTRATADO' AND c.hire_date BETWEEN :from AND :to");
            $stmt->execute([':from' => $periodFrom, ':to' => $periodTo]);
            $avg = $stmt->fetchColumn();
            $metrics['recruitment']['avg_hire_time_days'] = $avg ? round((float)$avg, 1) : 0;
        } catch (Throwable $e) {
            if (function_exists('error_log')) error_log('[rh-dashboard] avg_hire_time error: ' . $e->getMessage());
        }

        try {
            $stmt = $db->prepare("SELECT total_active FROM workforce_counts ORDER BY recorded_at DESC, id DESC LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetchColumn();
            $metrics['workforce']['active_employees'] = $row ? (int)$row : 0;
        } catch (Throwable $e) {
        }

        try {
            $st = $db->prepare("SELECT COUNT(*) FROM manual_terminations WHERE termination_date BETWEEN :from AND :to");
            $st->execute([':from'=>$periodFrom, ':to'=>$periodTo]);
            $terms = (int)$st->fetchColumn();
            $st2 = $db->prepare("SELECT AVG(total_active) avg_active FROM (SELECT total_active FROM workforce_counts WHERE recorded_at BETWEEN :from AND :to ORDER BY recorded_at DESC) t");
            $st2->execute([':from'=>$periodFrom, ':to'=>$periodTo]);
            $avgActive = (float)($st2->fetchColumn() ?: 0);
            $metrics['workforce']['turnover_pct'] = ($avgActive > 0) ? round(($terms / $avgActive) * 100, 2) : 0.0;
            $metrics['workforce']['absenteeism_pct'] = 0.0;
        } catch (Throwable $e) {
        }

        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM trainings WHERE status='EM_ANDAMENTO'");
            $stmt->execute();
            $metrics['training']['courses_running'] = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
        }

        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM employee_certifications");
            $stmt->execute();
            $metrics['training']['certified_employees'] = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
        }

        try {
            $stmt = $db->prepare("SELECT AVG(score) FROM climate_surveys WHERE survey_date BETWEEN :from AND :to");
            $stmt->execute([':from' => $periodFrom, ':to' => $periodTo]);
            $avg = $stmt->fetchColumn();
            $metrics['satisfaction']['climate_score'] = $avg ? round((float)$avg, 1) : 0;
        } catch (Throwable $e) {
        }

        return $metrics;
    }
}
