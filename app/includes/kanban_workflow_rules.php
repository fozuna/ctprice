<?php

if (!function_exists('kanbanBuildStagePositionMap')) {
    function kanbanBuildStagePositionMap(array $stages): array {
        $map = [];
        $fallback = 1;
        foreach ($stages as $s) {
            $id = (int)($s['id'] ?? 0);
            if ($id <= 0) continue;
            $pos = null;
            if (isset($s['position']) && is_numeric($s['position'])) {
                $pos = (int)$s['position'];
            } elseif (isset($s['sort_order']) && is_numeric($s['sort_order'])) {
                $pos = (int)$s['sort_order'];
            } else {
                $pos = $fallback;
            }
            $map[$id] = $pos;
            $fallback++;
        }
        return $map;
    }
}

if (!function_exists('kanbanIsTransitionAllowed')) {
    function kanbanIsTransitionAllowed(int $fromStageId, int $toStageId, array $stagePositionMap, bool $allowJump): array {
        if ($fromStageId <= 0 || $toStageId <= 0) {
            return ['ok' => true, 'reason' => null];
        }
        if ($fromStageId === $toStageId) {
            return ['ok' => false, 'reason' => 'A etapa de origem e destino são iguais.'];
        }
        if ($allowJump) {
            return ['ok' => true, 'reason' => null];
        }
        $fromPos = $stagePositionMap[$fromStageId] ?? null;
        $toPos = $stagePositionMap[$toStageId] ?? null;
        if ($fromPos === null || $toPos === null) {
            return ['ok' => true, 'reason' => null];
        }
        if (abs((int)$toPos - (int)$fromPos) > 1) {
            return ['ok' => false, 'reason' => 'Transição inválida: mova apenas para a etapa anterior ou próxima.'];
        }
        return ['ok' => true, 'reason' => null];
    }
}

