<?php

function cv_normalize_rel_path(string $path): string {
    $p = str_replace('\\', '/', $path);
    $p = ltrim($p, '/');
    while (strpos($p, '../') !== false) {
        $p = str_replace('../', '', $p);
    }
    return $p;
}

function cv_resolve_resume_file(string $resumeUrlOrPath): ?string {
    $v = trim($resumeUrlOrPath);
    if ($v === '') return null;
    if (preg_match('#^https?://#i', $v)) return null;

    $rel = cv_normalize_rel_path($v);
    $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__);

    $candidates = [];
    $candidates[] = $root . DIRECTORY_SEPARATOR . $rel;
    $candidates[] = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . $rel;
    if (strpos($rel, 'public/') === 0) {
        $candidates[] = $root . DIRECTORY_SEPARATOR . substr($rel, 7);
    }

    $allowedBases = [];
    $allowedBases[] = realpath($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'curriculos');
    $allowedBases[] = realpath($root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'curriculos');
    $allowedBases = array_values(array_filter($allowedBases, fn($p) => is_string($p) && $p !== false));

    foreach ($candidates as $cand) {
        $rp = @realpath($cand);
        if (!$rp || !is_file($rp)) continue;
        foreach ($allowedBases as $base) {
            $baseNorm = rtrim(str_replace('\\', '/', $base), '/') . '/';
            $rpNorm = str_replace('\\', '/', $rp);
            if (strpos($rpNorm . '/', $baseNorm) === 0) {
                return $rp;
            }
        }
    }
    return null;
}

function cv_detect_file_kind(string $filePath): array {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mime = null;
    if (function_exists('finfo_open')) {
        try {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) {
                $mime = finfo_file($fi, $filePath);
                finfo_close($fi);
            }
        } catch (Throwable $e) {
            $mime = null;
        }
    }
    if (!is_string($mime) || $mime === '') {
        if (function_exists('mime_content_type')) {
            try { $mime = mime_content_type($filePath); } catch (Throwable $e) { $mime = null; }
        }
    }

    $kind = 'other';
    $outMime = 'application/octet-stream';
    $disposition = 'attachment';

    if ($ext === 'pdf' || $mime === 'application/pdf') {
        $kind = 'pdf';
        $outMime = 'application/pdf';
        $disposition = 'inline';
    } elseif ($ext === 'doc') {
        $kind = 'doc';
        $outMime = 'application/msword';
        $disposition = 'attachment';
    } elseif ($ext === 'docx') {
        $kind = 'docx';
        $outMime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $disposition = 'attachment';
    }

    return [
        'ext' => $ext,
        'mime_detected' => $mime,
        'mime' => $outMime,
        'kind' => $kind,
        'disposition' => $disposition
    ];
}

