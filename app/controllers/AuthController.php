<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Security;
use App\Core\Auth;

class AuthController extends Controller
{
    public function login(): void
    {
        $csrf = Security::csrfToken();
        $expired = isset($_GET['expired']) && $_GET['expired'] === '1';
        $error = $expired ? 'Sessão expirada por inatividade de 20 minutos. Faça login novamente.' : null;
        $this->view->render('admin/login', ['csrf' => $csrf, 'error' => $error], 'layouts/main');
    }

    public function doLogin(): void
    {
        if (!Security::csrfCheck($_POST['csrf'] ?? '')) {
            http_response_code(400);
            echo 'Falha na verificação de segurança (CSRF).';
            return;
        }
        $email = Security::sanitizeString($_POST['email'] ?? '');
        $pass = $_POST['password'] ?? '';
        if (!$email || !$pass) {
            $this->view->render('admin/login', ['error' => 'Informe e-mail e senha', 'csrf' => Security::csrfToken()]);
            return;
        }

        // Rate limiting: 5 tentativas em 10 minutos, bloqueio por 15 minutos
        $ip = Security::clientIp();
        $scope = 'login';
        $key = $ip . '|' . strtolower($email);
        $rl = Security::rateLimitCheck($scope, $key, 5, 600, 900);
        if ($rl['blocked']) {
            $wait = (int)$rl['retry_after'];
            $msg = $wait > 0
                ? 'Muitas tentativas. Tente novamente em ' . $wait . 's.'
                : 'Muitas tentativas. Aguarde alguns minutos e tente novamente.';
            $this->view->render('admin/login', ['error' => $msg, 'csrf' => Security::csrfToken()]);
            return;
        }

        if (Auth::login($email, $pass)) {
            // Sucesso: resetar contador
            Security::rateLimitHit($rl['file'], $rl['data'], true, 900);
            header('Location: ' . \App\Core\Config::app()['base_url'] . '/admin');
            exit;
        }
        // Falha: registrar tentativa
        Security::rateLimitHit($rl['file'], $rl['data'], false, 900);
        $this->view->render('admin/login', ['error' => 'Credenciais inválidas', 'csrf' => Security::csrfToken()]);
    }

    public function logout(): void
    {
        Auth::logout();
        header('Location: ' . \App\Core\Config::app()['base_url'] . '/admin/login');
        exit;
    }
}