<?php

function stages_try_load(PDO $db): ?array {
    try {
        $st = $db->prepare('SELECT id, code, name, color, position, active FROM stages WHERE active = 1 ORDER BY position ASC, id ASC');
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows) || count($rows) === 0) return null;
        $out = [];
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            $code = strtoupper((string)($r['code'] ?? ''));
            if ($id <= 0 || $code === '') continue;
            $out[] = [
                'id' => $id,
                'code' => $code,
                'name' => (string)($r['name'] ?? $code),
                'color' => (string)($r['color'] ?? ''),
                'position' => (int)($r['position'] ?? 0)
            ];
        }
        return count($out) ? $out : null;
    } catch (Throwable $e) {
        return null;
    }
}

function stages_from_fallback(array $labelsByCode, array $colorsByCode = []): array {
    $out = [];
    $pos = 10;
    foreach ($labelsByCode as $code => $label) {
        $c = strtoupper((string)$code);
        $out[] = [
            'id' => null,
            'code' => $c,
            'name' => (string)$label,
            'color' => (string)($colorsByCode[$c] ?? ''),
            'position' => $pos
        ];
        $pos += 10;
    }
    return $out;
}

function stages_build_maps(array $stages): array {
    $byId = [];
    $byCode = [];
    foreach ($stages as $s) {
        $code = strtoupper((string)($s['code'] ?? ''));
        $id = $s['id'] ?? null;
        if ($code !== '') $byCode[$code] = $s;
        if ($id !== null) $byId[(int)$id] = $s;
    }
    return [$byId, $byCode];
}

