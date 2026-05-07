<?php
if (!class_exists('BaseModel')) {
    $bm = __DIR__ . '/BaseModel.php';
    if (is_file($bm)) {
        require_once $bm;
    }
}
if (!class_exists('User')) {
    $um = __DIR__ . '/../models/User.php';
    if (is_file($um)) {
        require_once $um;
    }
}
/**
 * Classe de Autenticação
 * Sistema BDO - Controle de Maquinários
 */

class Auth {
    private $conn;
    private $userModel;
    
    public function __construct($db) {
        $this->conn = $db;
        $this->userModel = new User($db);
    }
    
    /**
     * Autenticar usuário
     */
    public function login($email, $password) {
        $user = $this->userModel->findOne(['email' => $email, 'active' => 1]);
        
        if (!$user) {
            return false;
        }
        
        $ok = password_verify($password, $user['password']);
        if (!$ok) {
            $info = password_get_info($user['password']);
            if (($info['algo'] === 0 || $info['algoName'] === 'unknown') && hash_equals($user['password'], $password)) {
                $ok = true;
                $this->userModel->update($user['id'], ['password' => password_hash($password, PASSWORD_DEFAULT)]);
            } elseif (strlen($user['password']) === 32 && hash_equals(strtolower($user['password']), md5($password))) {
                $ok = true;
                $this->userModel->update($user['id'], ['password' => password_hash($password, PASSWORD_DEFAULT)]);
            }
        }
        if ($ok) {
            // Atualizar último login
            $this->userModel->update($user['id'], ['last_login' => date('Y-m-d H:i:s')]);
            
            // Armazenar dados na sessão
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['logged_in'] = true;
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role_id'];
            $_SESSION['user_functional_profile'] = $user['functional_profile'] ?? null;
            $_SESSION['user_front'] = $user['front_id'];
            $_SESSION['user_cost_center'] = $user['cost_center_id'] ?? null;
            if (session_status() === PHP_SESSION_ACTIVE) {
                @session_regenerate_id(true);
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Verificar se usuário está logado
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    /**
     * Obter usuário atual
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return $this->userModel->findById($_SESSION['user_id']);
    }
    
    /**
     * Verificar se usuário tem permissão
     */
    public function hasPermission($requiredRole) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $role = $_SESSION['user_role'] ?? null;
        if ($role === null) {
            return false;
        }
        
        // Administrador tem acesso total
        if ($role == ROLE_ADMIN) {
            return true;
        }
        
        // Verificar hierarquia de permissões
        switch ($requiredRole) {
            case ROLE_ADMIN:
                return $role == ROLE_ADMIN;
                
            case ROLE_COORD_RH:
                return in_array($role, [ROLE_ADMIN, ROLE_COORD_RH]);

            case ROLE_COMPRAS:
                return in_array($role, [ROLE_ADMIN, ROLE_COMPRAS]);

            case ROLE_SUPERVISOR:
                return in_array($role, [ROLE_ADMIN, ROLE_SUPERVISOR]);
                
            case ROLE_LIDER:
                return in_array($role, [ROLE_ADMIN, ROLE_SUPERVISOR, ROLE_LIDER]);
                
            case ROLE_OPERADOR:
                return in_array($role, [ROLE_ADMIN, ROLE_SUPERVISOR, ROLE_LIDER, ROLE_OPERADOR]);
                
            default:
                return false;
        }
    }
    
    /**
     * Logout
     */
    public function logout() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        // Limpar dados da sessão
        $_SESSION = [];
        // Remover cookies de sessão (cobrir path padrão e '/')
        $params = session_get_cookie_params();
        $paths = array_unique([$params['path'] ?? '/', '/']);
        foreach ($paths as $p) {
            setcookie(session_name(), '', time() - 42000, $p, $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? true);
        }
        // Remover possíveis cookies de autenticação
        foreach (['auth_token', 'remember_me'] as $ck) {
            if (isset($_COOKIE[$ck])) {
                setcookie($ck, '', time() - 42000, '/');
                unset($_COOKIE[$ck]);
            }
        }
        // Destruir sessão
        @session_destroy();
        @session_write_close();
        @session_regenerate_id(true);
        return true;
    }
    
    /**
     * Gerar hash de senha
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verificar se usuário tem acesso a uma frente específica
     */
    public function hasAccessToFront($frontId) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        // Administrador e Supervisor têm acesso a todas as frentes
        $role = $_SESSION['user_role'] ?? null;
        if ($role !== null && in_array($role, [ROLE_ADMIN, ROLE_SUPERVISOR])) {
            return true;
        }
        
        // Líder e Operador só têm acesso à sua frente
        $front = $_SESSION['user_front'] ?? null;
        return $front !== null && $front == $frontId;
    }
}
?>
