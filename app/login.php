<?php
/**
 * Página de Login
 * Sistema BDO - Controle de Maquinários
 */

if (!file_exists(__DIR__ . '/config/config.php')) {
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
    $redir = ($base === '' || $base === '/') ? '/install.php' : ($base . '/install.php');
    header('Location: ' . $redir);
    exit();
}
require_once __DIR__ . '/config/config.php';
if (file_exists(__DIR__ . '/includes/helpers.php')) {
    require_once __DIR__ . '/includes/helpers.php';
}

if (file_exists(__DIR__ . '/config/version-hooks.php')) {
    require_once __DIR__ . '/config/version-hooks.php';
    $CURRENT_VERSION = checkAndIncrementVersion();
}

// Evitar cache agressivo da página de login
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Se já estiver logado, redirecionar para dashboard
if (isset($_SESSION['user_id'])) {
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
    $target = (isset($_SESSION['user_role']) && (int)$_SESSION['user_role'] === ROLE_COORD_RH) ? 'rh-dashboard.php' : 'dashboard.php';
    $redir = ($base === '' || $base === '/') ? ('/' . $target) : ($base . '/' . $target);
    if (defined('DEBUG_MODE') && DEBUG_MODE && function_exists('error_log')) {
        error_log('[login] Sessão já ativa para user_id=' . ($_SESSION['user_id'] ?? 'n/a') . ' -> ' . $redir);
    }
    header('Location: ' . $redir, true, 302);
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor, preencha todos os campos.';
    } else {
        $dbClassOk = class_exists('Database');
        if (!$dbClassOk) {
            if (file_exists(__DIR__ . '/config/Database.php')) {
                require_once __DIR__ . '/config/Database.php';
            } elseif (file_exists(__DIR__ . '/config/database.php')) {
                require_once __DIR__ . '/config/database.php';
            }
            $dbClassOk = class_exists('Database');
        }
        if (!$dbClassOk) {
            $detail = 'Classe Database ausente. Verifique deploy e nomes dos arquivos em /config (sensível a maiúsculas/minúsculas).';
            $error = (defined('DEBUG_MODE') && DEBUG_MODE) ? $detail : 'Erro de configuração do servidor. Contate o administrador.';
        } else {
            try {
                $database = Database::getInstance();
                $db = $database->getConnection();
                if (!class_exists('Auth') && file_exists(__DIR__ . '/classes/Auth.php')) {
                    require_once __DIR__ . '/classes/Auth.php';
                }
                $auth = new Auth($db);
                if ($auth->login($email, $password)) {
                    if (defined('DEBUG_MODE') && DEBUG_MODE && function_exists('error_log')) {
                        $sid = session_id();
                        $sname = session_name();
                        $spath = ini_get('session.cookie_path');
                        $ssame = ini_get('session.cookie_samesite');
                        $ssecure = ini_get('session.cookie_secure');
                        $shttp = ini_get('session.cookie_httponly');
                        error_log("[login] Autenticação OK para {$email} sid={$sid} name={$sname} path={$spath} samesite={$ssame} secure={$ssecure} httponly={$shttp}");
                    }
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        @session_write_close();
                    }
                    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
                    $target = (isset($_SESSION['user_role']) && (int)$_SESSION['user_role'] === ROLE_COORD_RH) ? 'rh-dashboard.php' : 'dashboard.php';
                    $redir = ($base === '' || $base === '/') ? ('/' . $target) : ($base . '/' . $target);
                    if (defined('DEBUG_MODE') && DEBUG_MODE && function_exists('error_log')) {
                        error_log('[login] Redirecionando para ' . $redir);
                    }
                    // PRG: forçar GET após POST em proxies/UA específicos
                    header('Location: ' . $redir, true, 303);
                    exit();
                } else {
                    if (defined('DEBUG_MODE') && DEBUG_MODE && function_exists('error_log')) {
                        error_log('[login] Falha de autenticação para ' . $email);
                    }
                    $error = 'Email ou senha inválidos.';
                }
            } catch (Exception $e) {
                $detail = $e->getMessage();
                $error = (defined('DEBUG_MODE') && DEBUG_MODE) ? $detail : 'Erro interno do sistema. Tente novamente.';
                if (function_exists('error_log')) {
                    error_log('[login] Exceção: ' . $detail);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'rich-black': '#0d1321',
                        'prussian-blue': '#1d2d44',
                        'paynes-gray': '#3e5c76',
                        'silver-lake-blue': '#748cab',
                        'eggshell': '#f0ebd8',
                        'custom-bg': '#1d2d44'
                    }
                }
            }
        }
    </script>
</head>
<?php
    // URLs de assets padronizadas
    $bgUrl = asset_url('assets/fundo-madeplant.JPG');
    $logoLoginUrl = asset_url('assets/logomdp.png');
?>
<body class="relative min-h-screen flex items-center justify-center bg-cover bg-center bg-no-repeat" style="background-image: url('<?php echo $bgUrl; ?>');">
    <div class="absolute inset-0 bg-custom-bg bg-opacity-85 z-0"></div>
    <div class="relative z-10 bg-white rounded-lg shadow-2xl p-8 w-full max-w-md">
        <div class="text-center mb-8">
            <img src="<?php echo $logoLoginUrl; ?>" alt="Logo Madeplant" class="mx-auto mb-4 w-24 h-24 sm:w-28 sm:h-28 md:w-32 md:h-32 object-contain">
            <h1 class="text-3xl font-bold text-rich-black mb-2">Madeplant Florestal</h1>
            <p class="text-paynes-gray">Controle de Produção</p>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-6">
                <label for="email" class="block text-base font-medium text-paynes-gray mb-2">
                    Email
                </label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    class="w-full px-3 py-2 text-base border border-silver-lake-blue rounded-md focus:outline-none focus:ring-2 focus:ring-paynes-gray focus:border-transparent"
                    required
                >
            </div>
            
            <div class="mb-6">
                <label for="password" class="block text-base font-medium text-paynes-gray mb-2">
                    Senha
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="w-full px-3 py-2 text-base border border-silver-lake-blue rounded-md focus:outline-none focus:ring-2 focus:ring-paynes-gray focus:border-transparent"
                    required
                >
            </div>
            
            <button 
                type="submit" 
                class="w-full bg-paynes-gray hover:bg-prussian-blue text-white font-medium py-2 px-4 rounded-md transition duration-200 min-h-[44px]"
            >
                Entrar
            </button>
        </form>
        
        <div class="mt-4 text-center text-xs text-paynes-gray">
            <?php
                if (function_exists('getFullSystemVersion')) {
                    $ver = getFullSystemVersion();
                    $info = function_exists('getVersionInfo') ? getVersionInfo() : [];
                    $updated = $info['last_updated'] ?? '';
                    $commit = $info['commit'] ?? '';
                    $commitText = $commit ? (' • ' . htmlspecialchars($commit)) : '';
                    echo 'Versão: ' . htmlspecialchars($ver) . $commitText . ($updated ? ' • ' . htmlspecialchars($updated) : '');
                }
            ?>
        </div>
        
    </div>
</body>
</html>
