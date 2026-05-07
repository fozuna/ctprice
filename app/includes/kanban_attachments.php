<?php

if (!function_exists('kanbanAttachmentAllowedExtensions')) {
    function kanbanAttachmentAllowedExtensions(): array {
        return ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif'];
    }
}

if (!function_exists('kanbanAttachmentAllowedMimeTypes')) {
    function kanbanAttachmentAllowedMimeTypes(): array {
        return [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword', 'application/octet-stream'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
            'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
            'jpg' => ['image/jpeg', 'image/pjpeg'],
            'jpeg' => ['image/jpeg', 'image/pjpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif']
        ];
    }
}

if (!function_exists('kanbanEnsureAttachmentSchema')) {
    function kanbanEnsureAttachmentSchema(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS job_candidate_stage_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            movement_log_id INT NOT NULL,
            candidate_id INT NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            thumb_path VARCHAR(500) NULL,
            mime_type VARCHAR(120) NOT NULL,
            extension VARCHAR(12) NOT NULL,
            size_original BIGINT NOT NULL DEFAULT 0,
            size_stored BIGINT NOT NULL DEFAULT 0,
            compression_ratio DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            is_image TINYINT(1) NOT NULL DEFAULT 0,
            uploaded_by INT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_movement_log (movement_log_id),
            INDEX idx_candidate (candidate_id)
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS job_realtime_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            message VARCHAR(500) NOT NULL,
            payload TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_user_read (user_id, read_at)
        )");
    }
}

if (!function_exists('kanbanSanitizeFilename')) {
    function kanbanSanitizeFilename(string $name): string {
        $name = trim($name);
        $name = preg_replace('/[^\w\-. ]+/u', '_', $name);
        $name = preg_replace('/\s+/', ' ', (string)$name);
        return mb_substr($name, 0, 180);
    }
}

if (!function_exists('kanbanIsImageExtension')) {
    function kanbanIsImageExtension(string $ext): bool {
        return in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif'], true);
    }
}

if (!function_exists('kanbanCreateImageResource')) {
    function kanbanCreateImageResource(string $tmpPath, string $ext) {
        $ext = strtolower($ext);
        if ($ext === 'jpg' || $ext === 'jpeg') return @imagecreatefromjpeg($tmpPath);
        if ($ext === 'png') return @imagecreatefrompng($tmpPath);
        if ($ext === 'gif') return @imagecreatefromgif($tmpPath);
        return false;
    }
}

if (!function_exists('kanbanSaveImageResource')) {
    function kanbanSaveImageResource($img, string $targetPath, string $ext, int $quality = 80): bool {
        $ext = strtolower($ext);
        if ($ext === 'jpg' || $ext === 'jpeg') return @imagejpeg($img, $targetPath, $quality);
        if ($ext === 'png') {
            $level = max(0, min(9, (int)round((100 - $quality) / 10)));
            return @imagepng($img, $targetPath, $level);
        }
        if ($ext === 'gif') return @imagegif($img, $targetPath);
        return false;
    }
}

if (!function_exists('kanbanResizeFit')) {
    function kanbanResizeFit(int $srcW, int $srcH, int $maxW, int $maxH): array {
        if ($srcW <= 0 || $srcH <= 0) return [$maxW, $maxH];
        $ratio = min($maxW / $srcW, $maxH / $srcH, 1);
        return [max(1, (int)floor($srcW * $ratio)), max(1, (int)floor($srcH * $ratio))];
    }
}

if (!function_exists('kanbanOptimizeImageWithThumb')) {
    function kanbanOptimizeImageWithThumb(string $tmpPath, string $destPath, string $thumbPath, string $ext): array {
        $img = kanbanCreateImageResource($tmpPath, $ext);
        if (!$img) {
            if (!@copy($tmpPath, $destPath)) {
                throw new RuntimeException('Falha ao copiar imagem para armazenamento.');
            }
            return ['optimized' => false, 'thumb' => null];
        }

        $srcW = (int)imagesx($img);
        $srcH = (int)imagesy($img);
        [$newW, $newH] = kanbanResizeFit($srcW, $srcH, 1920, 1080);
        $canvas = imagecreatetruecolor($newW, $newH);
        if (in_array(strtolower($ext), ['png', 'gif'], true)) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $newW, $newH, $transparent);
        }
        imagecopyresampled($canvas, $img, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
        if (!kanbanSaveImageResource($canvas, $destPath, $ext, 80)) {
            imagedestroy($img);
            imagedestroy($canvas);
            throw new RuntimeException('Falha ao salvar imagem otimizada.');
        }

        [$thumbW, $thumbH] = kanbanResizeFit($newW, $newH, 320, 180);
        $thumb = imagecreatetruecolor($thumbW, $thumbH);
        if (in_array(strtolower($ext), ['png', 'gif'], true)) {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent2 = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
            imagefilledrectangle($thumb, 0, 0, $thumbW, $thumbH, $transparent2);
        }
        imagecopyresampled($thumb, $canvas, 0, 0, 0, 0, $thumbW, $thumbH, $newW, $newH);
        $thumbOk = kanbanSaveImageResource($thumb, $thumbPath, $ext, 80);

        imagedestroy($img);
        imagedestroy($canvas);
        imagedestroy($thumb);
        return ['optimized' => true, 'thumb' => $thumbOk ? $thumbPath : null];
    }
}

if (!function_exists('kanbanStoreAttachments')) {
    function kanbanValidateAttachmentBatchMeta(array $filesMeta, int $maxPerFile = 10485760, int $maxTotal = 52428800): array {
        $allowedExt = kanbanAttachmentAllowedExtensions();
        $total = 0;
        $result = [];
        foreach ($filesMeta as $idx => $f) {
            $name = (string)($f['name'] ?? '');
            $size = (int)($f['size'] ?? 0);
            $error = (int)($f['error'] ?? UPLOAD_ERR_OK);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $entry = ['index' => $idx, 'ok' => true, 'message' => null];
            if ($error !== UPLOAD_ERR_OK && $error !== UPLOAD_ERR_NO_FILE) {
                $entry['ok'] = false;
                $entry['message'] = 'Falha no upload de um arquivo (código ' . $error . ').';
                $result[] = $entry;
                continue;
            }
            if ($error === UPLOAD_ERR_NO_FILE) {
                $entry['ok'] = false;
                $entry['message'] = 'Arquivo não enviado.';
                $result[] = $entry;
                continue;
            }
            if (!in_array($ext, $allowedExt, true)) {
                $entry['ok'] = false;
                $entry['message'] = 'Tipo de arquivo não permitido: ' . $name;
                $result[] = $entry;
                continue;
            }
            if ($size > $maxPerFile) {
                $entry['ok'] = false;
                $entry['message'] = 'Arquivo "' . $name . '" excede 10MB.';
                $result[] = $entry;
                continue;
            }
            $total += $size;
            if ($total > $maxTotal) {
                $entry['ok'] = false;
                $entry['message'] = 'Total de anexos excede 50MB nesta movimentação.';
                $result[] = $entry;
                continue;
            }
            $result[] = $entry;
        }
        return $result;
    }
}

if (!function_exists('kanbanStoreAttachments')) {
    function kanbanStoreAttachments(PDO $db, array $filesInput, int $movementLogId, int $candidateId, ?int $userId = null): array {
        kanbanEnsureAttachmentSchema($db);
        $allowedExt = kanbanAttachmentAllowedExtensions();
        $allowedMimeMap = kanbanAttachmentAllowedMimeTypes();
        $maxPerFile = 10 * 1024 * 1024;
        $maxTotal = 50 * 1024 * 1024;

        $normalized = [];
        $totalSize = 0;
        if (isset($filesInput['name']) && is_array($filesInput['name'])) {
            $count = count($filesInput['name']);
            for ($i = 0; $i < $count; $i++) {
                $normalized[] = [
                    'name' => (string)($filesInput['name'][$i] ?? ''),
                    'type' => (string)($filesInput['type'][$i] ?? ''),
                    'tmp_name' => (string)($filesInput['tmp_name'][$i] ?? ''),
                    'error' => (int)($filesInput['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                    'size' => (int)($filesInput['size'][$i] ?? 0),
                ];
            }
        } elseif (!empty($filesInput['name'])) {
            $normalized[] = $filesInput;
        }
        $validation = kanbanValidateAttachmentBatchMeta($normalized, $maxPerFile, $maxTotal);

        $baseDir = (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__)) . '/uploads/kanban/movements/' . $movementLogId;
        $thumbDir = $baseDir . '/thumbs';
        if (!is_dir($baseDir)) @mkdir($baseDir, 0775, true);
        if (!is_dir($thumbDir)) @mkdir($thumbDir, 0775, true);
        if (is_file($baseDir . '/.htaccess') === false) {
            @file_put_contents($baseDir . '/.htaccess', "Options -Indexes\nphp_flag engine off\n");
        }

        $saved = [];
        $warnings = [];
        foreach ($normalized as $i => $f) {
            $v = $validation[$i] ?? ['ok' => true];
            if (empty($v['ok'])) {
                $warnings[] = (string)($v['message'] ?? 'Falha de validação do arquivo.');
                continue;
            }
            if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                $warnings[] = 'Falha no upload de um arquivo (código ' . (int)$f['error'] . ').';
                continue;
            }
            $size = (int)($f['size'] ?? 0);
            $totalSize += $size;
            $origName = (string)($f['name'] ?? 'arquivo');
            $safeOrigName = kanbanSanitizeFilename($origName);
            $ext = strtolower(pathinfo($safeOrigName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) {
                $warnings[] = 'Tipo de arquivo não permitido: ' . $safeOrigName;
                continue;
            }
            $tmp = (string)($f['tmp_name'] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                $warnings[] = 'Arquivo temporário inválido: ' . $safeOrigName;
                continue;
            }
            $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
            $mime = $finfo ? (string)finfo_file($finfo, $tmp) : (string)($f['type'] ?? 'application/octet-stream');
            if ($finfo) finfo_close($finfo);
            $allowedMimes = $allowedMimeMap[$ext] ?? [];
            if (!empty($allowedMimes) && !in_array($mime, $allowedMimes, true) && !str_starts_with($mime, 'image/')) {
                $warnings[] = 'MIME inválido para ' . $safeOrigName;
                continue;
            }

            $storedBase = date('Ymd_His') . '_' . bin2hex(random_bytes(6));
            $storedName = $storedBase . '.' . $ext;
            $destAbs = $baseDir . '/' . $storedName;
            $thumbAbs = $thumbDir . '/' . $storedBase . '_thumb.' . $ext;
            $relativePath = 'uploads/kanban/movements/' . $movementLogId . '/' . $storedName;
            $thumbRelative = null;
            $isImage = kanbanIsImageExtension($ext);
            $sizeOriginal = (int)$size;
            $sizeStored = 0;

            try {
                if ($isImage) {
                    $res = kanbanOptimizeImageWithThumb($tmp, $destAbs, $thumbAbs, $ext);
                    $thumbRelative = !empty($res['thumb']) ? ('uploads/kanban/movements/' . $movementLogId . '/thumbs/' . basename($thumbAbs)) : null;
                } else {
                    if (!@move_uploaded_file($tmp, $destAbs)) {
                        throw new RuntimeException('Falha ao salvar arquivo não-imagem.');
                    }
                }
                $sizeStored = is_file($destAbs) ? (int)filesize($destAbs) : 0;
                $ratio = ($sizeOriginal > 0 && $sizeStored > 0) ? max(0, min(100, (1 - ($sizeStored / $sizeOriginal)) * 100)) : 0;

                $ins = $db->prepare("INSERT INTO job_candidate_stage_attachments (
                    movement_log_id, candidate_id, original_name, stored_name, file_path, thumb_path, mime_type, extension,
                    size_original, size_stored, compression_ratio, is_image, uploaded_by
                ) VALUES (
                    :ml, :cid, :orig, :stored, :path, :thumb, :mime, :ext, :so, :ss, :cr, :img, :by
                )");
                $ins->execute([
                    ':ml' => $movementLogId,
                    ':cid' => $candidateId,
                    ':orig' => $safeOrigName,
                    ':stored' => $storedName,
                    ':path' => $relativePath,
                    ':thumb' => $thumbRelative,
                    ':mime' => $mime,
                    ':ext' => $ext,
                    ':so' => $sizeOriginal,
                    ':ss' => $sizeStored,
                    ':cr' => round($ratio, 2),
                    ':img' => $isImage ? 1 : 0,
                    ':by' => $userId
                ]);

                $saved[] = [
                    'id' => (int)$db->lastInsertId(),
                    'name' => $safeOrigName,
                    'path' => $relativePath,
                    'thumb' => $thumbRelative,
                    'is_image' => $isImage,
                    'size' => $sizeStored,
                    'compression_ratio' => round($ratio, 2)
                ];
            } catch (Throwable $e) {
                $warnings[] = 'Falha ao processar "' . $safeOrigName . '".';
            }
        }
        return ['saved' => $saved, 'warnings' => $warnings];
    }
}

if (!function_exists('kanbanNotifyUsersAboutMove')) {
    function kanbanNotifyUsersAboutMove(PDO $db, ?int $actorId, int $candidateId, string $fromStage, string $toStage, int $attachmentsCount): void {
        kanbanEnsureAttachmentSchema($db);
        $msg = 'Candidato #' . $candidateId . ' movido de ' . $fromStage . ' para ' . $toStage;
        if ($attachmentsCount > 0) {
            $msg .= ' com ' . $attachmentsCount . ' anexo(s)';
        }
        $users = $db->query("SELECT id FROM users WHERE active = 1 AND role_id IN (" . (int)ROLE_ADMIN . "," . (int)ROLE_COORD_RH . ")")->fetchAll(PDO::FETCH_COLUMN);
        if (!is_array($users)) return;
        $ins = $db->prepare("INSERT INTO job_realtime_notifications (user_id, message, payload) VALUES (:uid, :msg, :payload)");
        foreach ($users as $uid) {
            $uid = (int)$uid;
            if ($uid <= 0) continue;
            if ($actorId !== null && $uid === (int)$actorId) continue;
            $payload = json_encode([
                'candidate_id' => $candidateId,
                'from_stage' => $fromStage,
                'to_stage' => $toStage,
                'attachments' => $attachmentsCount
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $ins->execute([':uid' => $uid, ':msg' => $msg, ':payload' => $payload]);
        }
    }
}

if (!function_exists('kanbanEnsureCandidateFileSchema')) {
    function kanbanEnsureCandidateFileSchema(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS job_candidate_files (
            id INT AUTO_INCREMENT PRIMARY KEY,
            candidate_id INT NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            thumb_path VARCHAR(500) NULL,
            mime_type VARCHAR(120) NOT NULL,
            extension VARCHAR(12) NOT NULL,
            size_bytes BIGINT NOT NULL DEFAULT 0,
            is_image TINYINT(1) NOT NULL DEFAULT 0,
            uploaded_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_candidate_files_candidate (candidate_id)
        )");
    }
}

if (!function_exists('kanbanStoreCandidateFile')) {
    function kanbanStoreCandidateFile(PDO $db, array $file, int $candidateId, ?int $userId = null): array {
        kanbanEnsureCandidateFileSchema($db);
        $meta = [[
            'name' => (string)($file['name'] ?? ''),
            'size' => (int)($file['size'] ?? 0),
            'error' => (int)($file['error'] ?? UPLOAD_ERR_NO_FILE)
        ]];
        $valid = kanbanValidateAttachmentBatchMeta($meta, 10 * 1024 * 1024, 10 * 1024 * 1024);
        if (empty($valid[0]['ok'])) {
            throw new RuntimeException((string)($valid[0]['message'] ?? 'Arquivo inválido.'));
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Arquivo temporário inválido.');
        }

        $safeOrigName = kanbanSanitizeFilename((string)$file['name']);
        $ext = strtolower(pathinfo($safeOrigName, PATHINFO_EXTENSION));
        $isImage = kanbanIsImageExtension($ext);
        $baseDir = (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__)) . '/uploads/candidatos/' . $candidateId;
        $thumbDir = $baseDir . '/thumbs';
        if (!is_dir($baseDir)) @mkdir($baseDir, 0775, true);
        if (!is_dir($thumbDir)) @mkdir($thumbDir, 0775, true);
        if (is_file($baseDir . '/.htaccess') === false) {
            @file_put_contents($baseDir . '/.htaccess', "Options -Indexes\nphp_flag engine off\n");
        }

        $storedBase = date('Ymd_His') . '_' . bin2hex(random_bytes(6));
        $storedName = $storedBase . '.' . $ext;
        $destAbs = $baseDir . '/' . $storedName;
        $thumbAbs = $thumbDir . '/' . $storedBase . '_thumb.' . $ext;
        $relativePath = 'uploads/candidatos/' . $candidateId . '/' . $storedName;
        $thumbRelative = null;

        if ($isImage) {
            $res = kanbanOptimizeImageWithThumb($tmp, $destAbs, $thumbAbs, $ext);
            $thumbRelative = !empty($res['thumb']) ? ('uploads/candidatos/' . $candidateId . '/thumbs/' . basename($thumbAbs)) : null;
        } else {
            if (!@move_uploaded_file($tmp, $destAbs)) {
                throw new RuntimeException('Falha ao salvar arquivo.');
            }
        }

        $mime = function_exists('mime_content_type') ? (string)@mime_content_type($destAbs) : (string)($file['type'] ?? 'application/octet-stream');
        $sizeBytes = is_file($destAbs) ? (int)filesize($destAbs) : 0;
        $ins = $db->prepare("INSERT INTO job_candidate_files (
            candidate_id, original_name, stored_name, file_path, thumb_path, mime_type, extension, size_bytes, is_image, uploaded_by
        ) VALUES (
            :candidate_id, :original_name, :stored_name, :file_path, :thumb_path, :mime_type, :extension, :size_bytes, :is_image, :uploaded_by
        )");
        $ins->execute([
            ':candidate_id' => $candidateId,
            ':original_name' => $safeOrigName,
            ':stored_name' => $storedName,
            ':file_path' => $relativePath,
            ':thumb_path' => $thumbRelative,
            ':mime_type' => $mime,
            ':extension' => $ext,
            ':size_bytes' => $sizeBytes,
            ':is_image' => $isImage ? 1 : 0,
            ':uploaded_by' => $userId
        ]);

        return [
            'id' => (int)$db->lastInsertId(),
            'original_name' => $safeOrigName,
            'file_path' => $relativePath,
            'thumb_path' => $thumbRelative,
            'is_image' => $isImage,
            'size_bytes' => $sizeBytes,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
}

if (!function_exists('kanbanListCandidateFiles')) {
    function kanbanListCandidateFiles(PDO $db, int $candidateId): array {
        kanbanEnsureCandidateFileSchema($db);
        $st = $db->prepare("SELECT id, original_name, file_path, thumb_path, is_image, size_bytes, created_at
                              FROM job_candidate_files
                             WHERE candidate_id = :candidate_id
                             ORDER BY id DESC");
        $st->execute([':candidate_id' => $candidateId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
