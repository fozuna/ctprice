<?php
if (!function_exists('kanbanEnsureSortOrder')) {
    function kanbanEnsureSortOrder(PDO $db): void {
        try { $db->exec("ALTER TABLE job_candidates ADD COLUMN sort_order INT NULL"); } catch (Throwable $e) {}
        try { $db->exec("ALTER TABLE job_candidates ADD COLUMN stage_id INT NULL"); } catch (Throwable $e) {}
        try { $db->exec("CREATE INDEX idx_job_candidates_stageid_sort_order ON job_candidates (stage_id, sort_order)"); } catch (Throwable $e) {}
    }
}

if (!function_exists('kanbanNormalizeIdList')) {
    function kanbanNormalizeIdList($value): array {
        if (!is_array($value)) return [];
        $out = [];
        foreach ($value as $v) {
            $id = (int)$v;
            if ($id > 0) $out[$id] = $id;
        }
        return array_values($out);
    }
}

if (!function_exists('kanbanApplySortOrder')) {
    function kanbanApplySortOrder(PDO $db, $stageId, $ids): void {
        $ids = kanbanNormalizeIdList($ids);
        if (empty($ids)) return;
        $sid = (int)$stageId;
        $clr = $db->prepare('UPDATE job_candidates SET sort_order = NULL WHERE stage_id = :sid');
        $clr->execute([':sid' => $sid]);
        $upd = $db->prepare('UPDATE job_candidates SET sort_order = :pos WHERE id = :id AND stage_id = :sid');
        $pos = 1;
        foreach ($ids as $id) {
            $upd->execute([':pos' => $pos, ':id' => (int)$id, ':sid' => $sid]);
            $pos++;
        }
    }
}

