<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Security;
use App\Models\Vaga;

class AdminVagasController extends Controller
{
    public function index(): void
    {
        Auth::requireRole(['admin', 'rh', 'viewer']);
        $vagas = Vaga::all();
        $this->view->render('admin/vagas/index', ['vagas' => $vagas], 'layouts/admin');
    }

    public function create(): void
    {
        Auth::requireRole(['admin', 'rh']);
        $csrf = Security::csrfToken();
        $this->view->render('admin/vagas/form', ['csrf' => $csrf, 'vaga' => null], 'layouts/admin');
    }

    public function store(): void
    {
        Auth::requireRole(['admin', 'rh']);
        if (!Security::csrfCheck($_POST['csrf'] ?? '')) { http_response_code(400); echo 'CSRF inválido'; return; }
        $data = [
            'titulo' => Security::sanitizeString($_POST['titulo'] ?? ''),
            'descricao' => Security::sanitizeString($_POST['descricao'] ?? ''),
            'requisitos' => Security::sanitizeString($_POST['requisitos'] ?? ''),
            'area' => Security::sanitizeString($_POST['area'] ?? ''),
            'local' => Security::sanitizeString($_POST['local'] ?? ''),
            'ativo' => isset($_POST['ativo']) ? 1 : 0,
        ];
        if (!$data['titulo'] || !$data['descricao'] || !$data['requisitos']) {
            $this->view->render('admin/vagas/form', ['csrf' => Security::csrfToken(), 'vaga' => $data, 'error' => 'Preencha os campos obrigatórios']);
            return;
        }
        Vaga::create($data);
        header('Location: ' . \App\Core\Config::app()['base_url'] . '/admin/vagas');
        exit;
    }

    public function edit(string $id): void
    {
        Auth::requireRole(['admin', 'rh']);
        $vaga = Vaga::find((int)$id);
        if (!$vaga) { http_response_code(404); echo 'Vaga não encontrada'; return; }
        $csrf = Security::csrfToken();
        $this->view->render('admin/vagas/form', ['csrf' => $csrf, 'vaga' => $vaga], 'layouts/admin');
    }

    public function update(string $id): void
    {
        Auth::requireRole(['admin', 'rh']);
        if (!Security::csrfCheck($_POST['csrf'] ?? '')) { http_response_code(400); echo 'CSRF inválido'; return; }
        $data = [
            'titulo' => Security::sanitizeString($_POST['titulo'] ?? ''),
            'descricao' => Security::sanitizeString($_POST['descricao'] ?? ''),
            'requisitos' => Security::sanitizeString($_POST['requisitos'] ?? ''),
            'area' => Security::sanitizeString($_POST['area'] ?? ''),
            'local' => Security::sanitizeString($_POST['local'] ?? ''),
            'ativo' => isset($_POST['ativo']) ? 1 : 0,
        ];
        if (!Vaga::update((int)$id, $data)) { echo 'Falha ao atualizar'; return; }
        header('Location: ' . \App\Core\Config::app()['base_url'] . '/admin/vagas');
        exit;
    }

    public function delete(string $id): void
    {
        Auth::requireRole(['admin']);
        if (!Security::csrfCheck($_POST['csrf'] ?? '')) { http_response_code(400); echo 'CSRF inválido'; return; }
        Vaga::delete((int)$id);
        header('Location: ' . \App\Core\Config::app()['base_url'] . '/admin/vagas');
        exit;
    }
}