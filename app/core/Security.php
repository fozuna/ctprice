<?php
namespace App\Core;

class Security
{
    public static function startSecureSession(string $name = 'CTPRICESESSID'): void
    {
        $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null;
        $secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            || ($proto && strtolower($proto) === 'https')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name($name);
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
            session_regenerate_id(true);
        }
    }

    public static function enforceInactivityTimeout(int $seconds = 1200): void
    {
        $now = time();
        $last = $_SESSION['last_activity'] ?? null;
        $isLogged = !empty($_SESSION['user_id']);
        if ($isLogged && $last !== null && ($now - (int)$last) > $seconds) {
            \App\Core\Auth::logout();
            $base = \App\Core\Config::app()['base_url'] ?? '';
            header('Location: ' . $base . '/admin/login?expired=1');
            exit;
        }
        $_SESSION['last_activity'] = $now;
    }

    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function sanitizeString(?string $str): string
    {
        $str = trim((string)$str);
        $str = strip_tags($str);
        return $str;
    }

    public static function csrfToken(): string
    {
        $key = Config::app()['security']['csrf_key'];
        if (empty($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }
        return $_SESSION[$key];
    }

    public static function csrfCheck(?string $token): bool
    {
        $key = Config::app()['security']['csrf_key'];
        return isset($_SESSION[$key]) && hash_equals($_SESSION[$key], (string)$token);
    }

    public static function clientIp(): string
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (strpos($ip, ',') !== false) {
            $parts = explode(',', $ip);
            $ip = trim($parts[0]);
        }
        return $ip;
    }

    public static function rateLimitCheck(string $scope, string $key, int $maxAttempts, int $windowSeconds, int $lockoutSeconds): array
    {
        $dir = STORAGE_PATH . DIRECTORY_SEPARATOR . 'ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $hash = hash('sha256', $scope . '|' . $key);
        $file = $dir . DIRECTORY_SEPARATOR . $hash . '.json';
        $now = time();
        $data = ['attempts' => [], 'locked_until' => 0];
        if (is_file($file)) {
            $raw = file_get_contents($file);
            if ($raw !== false) {
                $tmp = json_decode($raw, true);
                if (is_array($tmp)) {
                    $data = array_merge($data, $tmp);
                }
            }
        }
        $data['attempts'] = array_values(array_filter($data['attempts'], function ($ts) use ($now, $windowSeconds) {
            return ($now - (int)$ts) <= $windowSeconds;
        }));
        $blocked = $now < (int)$data['locked_until'] || count($data['attempts']) >= $maxAttempts;
        $retryAfter = 0;
        if ($blocked) {
            $retryAfter = max(0, ((int)$data['locked_until'] ?: ($data['attempts'][0] ?? $now) + $lockoutSeconds) - $now);
        }
        // Em ambiente de desenvolvimento, não bloquear o login para facilitar demonstrações
        try {
            if (\App\Core\Config::app()['env'] === 'dev') {
                $blocked = false;
                $retryAfter = 0;
            }
        } catch (\Throwable $e) { /* ignora falha em dev override */ }
        return ['blocked' => $blocked, 'retry_after' => $retryAfter, 'file' => $file, 'data' => $data];
    }

    public static function rateLimitHit(string $file, array $data, bool $success, int $lockoutSeconds): void
    {
        $now = time();
        if ($success) {
            $data['attempts'] = [];
            $data['locked_until'] = 0;
        } else {
            $data['attempts'][] = $now;
            if (count($data['attempts']) > 0) {
                $data['locked_until'] = $now + $lockoutSeconds;
            }
        }
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function isValidCpf(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf);
        if (strlen($cpf) !== 11) { return false; }
        // rejeitar sequências de dígitos iguais
        if (preg_match('/^(\d)\1{10}$/', $cpf)) { return false; }
        // calcular primeiros 9 dígitos
        $sum = 0;
        for ($i = 0, $weight = 10; $i < 9; $i++, $weight--) {
            $sum += ((int)$cpf[$i]) * $weight;
        }
        $rest = $sum % 11;
        $d1 = ($rest < 2) ? 0 : 11 - $rest;
        if ((int)$cpf[9] !== $d1) { return false; }
        // segundo dígito
        $sum = 0;
        for ($i = 0, $weight = 11; $i < 10; $i++, $weight--) {
            $sum += ((int)$cpf[$i]) * $weight;
        }
        $rest = $sum % 11;
        $d2 = ($rest < 2) ? 0 : 11 - $rest;
        return (int)$cpf[10] === $d2;
    }
}