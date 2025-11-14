<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Security;
use App\Core\Config;
use App\Models\User;

class AdminUsuariosController extends Controller
{
    public function create(): void
    {
        Auth::requireRole(['admin']);
        $csrf = Security::csrfToken();
        $this->view->render('admin/usuarios/create', ['csrf' => $csrf], 'layouts/admin');
    }

    public function store(): void
    {
        Auth::requireRole(['admin']);
        if (!Security::csrfCheck($_POST['csrf'] ?? '')) {
            http_response_code(400);
            echo 'Falha na verificação de segurança (CSRF).';
            return;
        }
        $nome = Security::sanitizeString($_POST['nome'] ?? '');
        $email = Security::sanitizeString($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $role = Security::sanitizeString($_POST['role'] ?? 'viewer');
        if (!in_array($role, ['admin','rh','viewer'], true)) { $role = 'viewer'; }
        if (!$nome || !$email || !$senha) {
            $this->view->render('admin/usuarios/create', [
                'error' => 'Preencha nome, e-mail e senha.',
                'csrf' => Security::csrfToken()
            ], 'layouts/admin');
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->view->render('admin/usuarios/create', [
                'error' => 'E-mail inválido.',
                'csrf' => Security::csrfToken()
            ], 'layouts/admin');
            return;
        }
        if (strlen($senha) < 8) {
            $this->view->render('admin/usuarios/create', [
                'error' => 'A senha deve ter pelo menos 8 caracteres.',
                'csrf' => Security::csrfToken()
            ], 'layouts/admin');
            return;
        }
        $senhaHash = password_hash($senha, PASSWORD_BCRYPT);
        try {
            User::create($nome, $email, $senhaHash, $role);
        } catch (\Throwable $e) {
            $this->view->render('admin/usuarios/create', [
                'error' => 'Falha ao criar usuário: ' . Security::e($e->getMessage()),
                'csrf' => Security::csrfToken()
            ], 'layouts/admin');
            return;
        }
        header('Location: ' . (Config::app()['base_url'] ?? '') . '/admin');
        exit;
    }
}