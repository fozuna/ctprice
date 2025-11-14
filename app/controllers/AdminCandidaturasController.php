<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Security;
use App\Core\Config;
use App\Models\Candidatura;
use App\Models\Vaga;

class AdminCandidaturasController extends Controller
{
    public function index(): void
    {
        Auth::requireRole(['admin', 'rh', 'viewer']);
        $filters = [
            'vaga_id' => isset($_GET['vaga_id']) ? (int)$_GET['vaga_id'] : null,
            'status' => Security::sanitizeString($_GET['status'] ?? ''),
            'data_de' => Security::sanitizeString($_GET['data_de'] ?? ''),
            'data_ate' => Security::sanitizeString($_GET['data_ate'] ?? ''),
        ];
        $candidaturas = Candidatura::all(array_filter($filters, fn($v) => $v !== null && $v !== ''));
        $vagas = Vaga::all();
        $this->view->render('admin/candidaturas/index', [
            'candidaturas' => $candidaturas,
            'vagas' => $vagas,
            'filters' => $filters,
        ], 'layouts/admin');
    }

    public function show(string $id): void
    {
        Auth::requireRole(['admin', 'rh', 'viewer']);
        $c = Candidatura::find((int)$id);
        if (!$c) { http_response_code(404); echo 'Candidatura não encontrada'; return; }
        $historico = Candidatura::getHistorico((int)$id);
        $csrf = Security::csrfToken();
        $this->view->render('admin/candidaturas/show', ['c' => $c, 'historico' => $historico, 'csrf' => $csrf], 'layouts/admin');
    }

    public function download(string $id): void
    {
        Auth::requireRole(['admin', 'rh']);
        $c = Candidatura::find((int)$id);
        if (!$c) { http_response_code(404); echo 'Candidatura não encontrada'; return; }
        $name = basename((string)($c['pdf_path'] ?? ''));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') { http_response_code(400); echo 'Arquivo inválido.'; return; }
        $dir = STORAGE_PATH . DIRECTORY_SEPARATOR . 'resumes';
        $file = $dir . DIRECTORY_SEPARATOR . $name;
        $real = realpath($file);
        $realDir = realpath($dir);
        if ($real === false || $realDir === false || strpos($real, $realDir) !== 0 || !is_file($real)) {
            http_response_code(404); echo 'Arquivo não encontrado'; return;
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="curriculo-' . (int)$c['id'] . '.pdf"');
        header('Content-Length: ' . filesize($real));
        readfile($real);
        exit;
    }

    public function update(string $id): void
    {
        Auth::requireRole(['admin', 'rh']);
        if (!Security::csrfCheck($_POST['csrf'] ?? '')) {
            http_response_code(400);
            echo 'Falha na verificação de segurança (CSRF).';
            return;
        }
        $status = Security::sanitizeString($_POST['status'] ?? '');
        $observacoes = Security::sanitizeString($_POST['observacoes'] ?? '');
        if (!$status) { $status = 'em_analise'; }
        $usuarioId = $_SESSION['user_id'] ?? null;
        $ok = Candidatura::updateStatusNotes((int)$id, $status, $observacoes, $usuarioId);
        if (!$ok) {
            http_response_code(500);
            echo 'Falha ao atualizar candidatura.';
            return;
        }
        // Redireciona usando base_url da aplicação
        header('Location: ' . (Config::app()['base_url'] ?? '') . '/admin/candidaturas/' . (int)$id);
        exit;
    }
}