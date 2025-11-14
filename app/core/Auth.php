<?php
namespace App\Core;

use App\Models\User;

class Auth
{
    public static function login(string $email, string $password): bool
    {
        $user = User::findByEmail($email);
        if (!$user) { return false; }
        if (!User::verifyPassword($user, $password)) { return false; }
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_role'] = $user->role;
        $_SESSION['user_name'] = $user->nome;
        session_regenerate_id(true);
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function role(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    public static function requireRole(array $roles): void
    {
        if (!self::check() || !in_array(self::role(), $roles, true)) {
            http_response_code(403);
            echo 'Acesso negado';
            exit;
        }
    }
}