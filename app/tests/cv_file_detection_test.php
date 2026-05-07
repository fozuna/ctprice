<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../includes/cv_file.php';

function assert_true($cond, $msg) {
    if (!$cond) {
        fwrite(STDERR, '[FAIL] ' . $msg . PHP_EOL);
        exit(1);
    }
    echo '[OK] ' . $msg . PHP_EOL;
}

$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cv_detect_' . bin2hex(random_bytes(4));
@mkdir($dir, 0775, true);

$pdf = $dir . DIRECTORY_SEPARATOR . 'a.pdf';
file_put_contents($pdf, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n");
$doc = $dir . DIRECTORY_SEPARATOR . 'b.doc';
file_put_contents($doc, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1");
$docx = $dir . DIRECTORY_SEPARATOR . 'c.docx';
file_put_contents($docx, "PK\x03\x04");

$m1 = cv_detect_file_kind($pdf);
assert_true($m1['kind'] === 'pdf', 'PDF deve ser identificado como pdf');
assert_true($m1['disposition'] === 'inline', 'PDF deve ser inline');

$m2 = cv_detect_file_kind($doc);
assert_true($m2['kind'] === 'doc', 'DOC deve ser identificado como doc');
assert_true($m2['disposition'] === 'attachment', 'DOC deve ser attachment');

$m3 = cv_detect_file_kind($docx);
assert_true($m3['kind'] === 'docx', 'DOCX deve ser identificado como docx');
assert_true($m3['disposition'] === 'attachment', 'DOCX deve ser attachment');

@unlink($pdf);
@unlink($doc);
@unlink($docx);
@rmdir($dir);

echo PHP_EOL . 'Todos os testes passaram.' . PHP_EOL;
exit(0);

