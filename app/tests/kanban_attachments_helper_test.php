<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../includes/kanban_attachments.php';

function k_assert($cond, $msg)
{
    if (!$cond) {
        fwrite(STDERR, '[FAIL] ' . $msg . PHP_EOL);
        exit(1);
    }
    echo '[OK] ' . $msg . PHP_EOL;
}

try {
    $db = Database::getInstance()->getConnection();
    kanbanEnsureAttachmentSchema($db);

    $ext = kanbanAttachmentAllowedExtensions();
    k_assert(in_array('pdf', $ext, true) && in_array('jpg', $ext, true), 'Tipos permitidos devem incluir documentos e imagens');
    $validation = kanbanValidateAttachmentBatchMeta([
        ['name' => 'ok.pdf', 'size' => 1024, 'error' => UPLOAD_ERR_OK],
        ['name' => 'invalido.exe', 'size' => 100, 'error' => UPLOAD_ERR_OK],
        ['name' => 'grande.docx', 'size' => 11 * 1024 * 1024, 'error' => UPLOAD_ERR_OK],
    ]);
    k_assert($validation[0]['ok'] === true, 'Arquivo válido deve passar na validação de metadados');
    k_assert($validation[1]['ok'] === false, 'Formato inválido deve falhar na validação de metadados');
    k_assert($validation[2]['ok'] === false, 'Arquivo acima de 10MB deve falhar na validação de metadados');

    [$rw, $rh] = kanbanResizeFit(4000, 3000, 1920, 1080);
    k_assert($rw <= 1920 && $rh <= 1080, 'Resize deve respeitar limite 1920x1080');

    $tmpImg = null;
    $dest = null;
    $thumb = null;
    if (function_exists('imagecreatetruecolor')) {
        $tmpImg = tempnam(sys_get_temp_dir(), 'att_img_');
        $src = imagecreatetruecolor(3000, 2000);
        $bg = imagecolorallocate($src, 30, 120, 210);
        imagefilledrectangle($src, 0, 0, 2999, 1999, $bg);
        imagejpeg($src, $tmpImg, 95);
        imagedestroy($src);

        $dest = $tmpImg . '_opt.jpg';
        $thumb = $tmpImg . '_thumb.jpg';
        $res = kanbanOptimizeImageWithThumb($tmpImg, $dest, $thumb, 'jpg');
        k_assert(is_file($dest), 'Imagem otimizada deve ser gerada');
        k_assert(is_file($thumb), 'Thumbnail deve ser gerada');
        k_assert($res['optimized'] === true, 'Processamento de imagem deve ser otimizado');

        $sizeOriginal = filesize($tmpImg);
        $sizeOptimized = filesize($dest);
        k_assert($sizeOptimized > 0 && $sizeOriginal > 0, 'Tamanhos dos arquivos devem ser válidos');
    } else {
        echo '[OK] Ambiente sem GD: teste de compressão de imagem ignorado' . PHP_EOL;
    }

    $actor = (int)($_SESSION['user_id'] ?? 0);
    if ($actor <= 0) {
        $actor = (int)$db->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetchColumn();
    }
    kanbanNotifyUsersAboutMove($db, $actor, 99999, 'RECEBIDO', 'RH', 2);
    $cnt = (int)$db->query("SELECT COUNT(*) FROM job_realtime_notifications WHERE message LIKE 'Candidato #99999 movido%'")->fetchColumn();
    k_assert($cnt >= 1, 'Notificações em tempo real devem ser persistidas');

    if ($tmpImg) @unlink($tmpImg);
    if ($dest) @unlink($dest);
    if ($thumb) @unlink($thumb);

    echo PHP_EOL . 'Resultado: testes de anexos do kanban concluídos com sucesso.' . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    echo 'Erro inesperado: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
