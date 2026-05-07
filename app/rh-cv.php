<?php
require_once 'config/config.php';
require_once 'classes/Auth.php';
require_once 'models/JobCandidate.php';
require_once 'includes/cv_file.php';

$database = Database::getInstance();
$db = $database->getConnection();
$auth = new Auth($db);

$role = $_SESSION['user_role'] ?? null;
if (!$auth->isLoggedIn() || !in_array($role, [ROLE_ADMIN, ROLE_COORD_RH])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Acesso negado.';
    exit;
}

$cid = (int)($_GET['id'] ?? 0);
if ($cid <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'ID inválido.';
    exit;
}

$candidateModel = new JobCandidate($db);
$row = $candidateModel->findById($cid);
if (!$row) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Candidato não encontrado.';
    exit;
}

$resume = (string)($row['resume_url'] ?? '');
$file = cv_resolve_resume_file($resume);
if (!$file) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Currículo não encontrado.';
    exit;
}

$meta = cv_detect_file_kind($file);
$ext = $meta['ext'] ?: strtolower(pathinfo($file, PATHINFO_EXTENSION));
$nameBase = preg_replace('/[^a-zA-Z0-9._-]+/', '_', (string)($row['name'] ?? 'candidato'));
$downloadName = 'curriculo_' . $cid . '_' . $nameBase . ($ext ? ('.' . $ext) : '');
$downloadName = substr($downloadName, 0, 180);

header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $meta['mime']);
header('Content-Disposition: ' . $meta['disposition'] . '; filename="' . $downloadName . '"');
header('Content-Length: ' . (string)filesize($file));

@readfile($file);
exit;

