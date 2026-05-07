<?php
/**
 * Script de Instalação do Sistema BDO
 * Sistema BDO - Controle de Maquinários
 */

// Verificar se já foi instalado
if (file_exists('config/installed.lock')) {
    die('Sistema já foi instalado. Para reinstalar, remova o arquivo config/installed.lock');
}

$step = $_GET['step'] ?? $_POST['step'] ?? 1;
$error = '';
$success = '';

// Processar formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1:
            // Verificar requisitos
            $step = 2;
            break;
        case 2:
            $result = configurarBancoDados();
            if ($result['success']) {
                $step = 3;
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
            break;
        case 3:
            // Criar usuário administrador
            $result = criarUsuarioAdmin();
            if ($result['success']) {
                $step = 4;
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
            break;
        case 4:
            // Finalizar instalação
            $result = finalizarInstalacao();
            if ($result['success']) {
                $step = 5;
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
            break;
    }
}

function verificarRequisitos() {
    $requisitos = [
        'PHP >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'PDO Extension' => extension_loaded('pdo'),
        'PDO MySQL' => extension_loaded('pdo_mysql'),
        'JSON Extension' => extension_loaded('json'),
        'MBString Extension' => extension_loaded('mbstring'),
        'Config Directory Writable' => is_writable('config/'),
        'Uploads Directory Writable' => is_writable('public/uploads/') || mkdir('public/uploads/', 0755, true)
    ];
    
    return $requisitos;
}

function configurarBancoDados() {
    $host = $_POST['db_host'] ?? '';
    $port = $_POST['db_port'] ?? '3306';
    $name = $_POST['db_name'] ?? '';
    $user = $_POST['db_user'] ?? '';
    $pass = $_POST['db_pass'] ?? '';
    
    if (empty($host) || empty($name) || empty($user)) {
        return ['success' => false, 'message' => 'Todos os campos são obrigatórios'];
    }
    
    try {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ];
        $dsnDb = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        try {
            $pdo = new PDO($dsnDb, $user, $pass, $options);
        } catch (PDOException $e) {
            $driverCode = isset($e->errorInfo[1]) ? (int)$e->errorInfo[1] : 0;
            if ($driverCode === 1049) {
                try {
                    $dsnNoDb = "mysql:host={$host};port={$port};charset=utf8mb4";
                    $pdoNoDb = new PDO($dsnNoDb, $user, $pass, $options);
                    $pdoNoDb->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo = new PDO($dsnDb, $user, $pass, $options);
                } catch (PDOException $e2) {
                    $driverCode2 = isset($e2->errorInfo[1]) ? (int)$e2->errorInfo[1] : 0;
                    if ($driverCode2 === 1044) {
                        return ['success' => false, 'message' => 'Usuário sem permissão para criar banco. Crie o banco no painel e tente novamente.'];
                    }
                    throw $e2;
                }
            } elseif ($driverCode === 1044) {
                return ['success' => false, 'message' => 'Acesso negado ao banco. Verifique se o nome do banco inclui o prefixo do usuário e se o usuário tem privilégios sobre o banco.'];
            } else {
                throw $e;
            }
        }
        
        // Verificar se as tabelas já existem
        $stmt = $pdo->query("SHOW TABLES");
        $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($existingTables)) {
            // Executar schema SQL apenas se não houver tabelas
            $sqlFile = __DIR__ . '/database/schema.sql';
            if (!file_exists($sqlFile)) {
                throw new Exception('Arquivo schema.sql não encontrado');
            }
            
            $sql = file_get_contents($sqlFile);
            
            // Remover comentários e linhas vazias
            $sql = preg_replace('/--.*$/m', '', $sql);
            $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
            
            // Dividir em comandos individuais
            $commands = array_filter(array_map('trim', explode(';', $sql)));
            
            foreach ($commands as $command) {
                if (!empty($command)) {
                    $pdo->exec($command);
                }
            }
        }
        
        // Criar arquivo de configuração
        $configContent = "<?php
/**
 * Configurações do Sistema BDO
 * Gerado automaticamente pelo instalador
 */

// Iniciar sessão
session_start();

// Configurações do Banco de Dados
define('DB_HOST', '{$host}');
define('DB_PORT', '{$port}');
define('DB_NAME', '{$name}');
define('DB_USER', '{$user}');
define('DB_PASS', '{$pass}');

// Configurações de Segurança
define('JWT_SECRET', '" . bin2hex(random_bytes(32)) . "');
define('ENCRYPTION_KEY', '" . bin2hex(random_bytes(16)) . "');

// Configurações do Sistema
define('SYSTEM_NAME', 'Sistema BDO');
define('SITE_NAME', 'Sistema BDO');
define('SYSTEM_VERSION', '1.0.0');
define('TIMEZONE', 'America/Sao_Paulo');

// Roles do Sistema
define('ROLE_ADMIN', 1);
define('ROLE_SUPERVISOR', 2);
define('ROLE_LIDER', 3);
define('ROLE_OPERADOR', 4);
define('ROLE_COORD_RH', 5);
define('ROLE_COMPRAS', 6);

// Configurar timezone
date_default_timezone_set(TIMEZONE);

// Autoload das classes
spl_autoload_register(function (\$class) {
    \$paths = [
        __DIR__ . '/../classes/',
        __DIR__ . '/../models/',
        __DIR__ . '/'
    ];
    
    foreach (\$paths as \$path) {
        \$file = \$path . \$class . '.php';
        if (file_exists(\$file)) {
            require_once \$file;
            return;
        }
    }
});
";
        
        file_put_contents('config/config.php', $configContent);
        
        return ['success' => true, 'message' => 'Banco de dados configurado com sucesso'];
        
    } catch (Exception $e) {
        $errorDetails = [
            'Erro: ' . $e->getMessage(),
            'Arquivo: ' . $e->getFile(),
            'Linha: ' . $e->getLine(),
            'Host: ' . $host,
            'Banco: ' . $name,
            'Usuário: ' . $user
        ];
        return ['success' => false, 'message' => 'Erro na configuração do banco de dados:<br>' . implode('<br>', $errorDetails)];
    }
}

function criarUsuarioAdmin() {
    $name = $_POST['admin_name'] ?? '';
    $email = $_POST['admin_email'] ?? '';
    $password = $_POST['admin_password'] ?? '';
    $confirmPassword = $_POST['admin_password_confirm'] ?? '';
    
    if (empty($name) || empty($email) || empty($password)) {
        return ['success' => false, 'message' => 'Todos os campos são obrigatórios'];
    }
    
    if ($password !== $confirmPassword) {
        return ['success' => false, 'message' => 'Senhas não conferem'];
    }
    
    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'Senha deve ter pelo menos 6 caracteres'];
    }
    
    try {
        require_once 'config/config.php';
        require_once 'config/database.php';
        
        $database = Database::getInstance();
        $db = $database->getConnection();
        
        // Verificar se já existe usuário admin (role_id = 1 é Administrador)
        $stmt = $db->prepare("SELECT id FROM users WHERE role_id = 1 LIMIT 1");
        $stmt->execute();
        $adminExists = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $costCenterId = (int)$db->query("SELECT id FROM cost_centers ORDER BY id ASC LIMIT 1")->fetchColumn();
        if ($costCenterId <= 0) {
            $stmt = $db->prepare("
                INSERT INTO cost_centers (name, code, department, active, created_at)
                VALUES (?, ?, ?, 1, NOW())
            ");
            $stmt->execute(['Centro de Custo Geral', 'GERAL', 'Administrativo']);
            $costCenterId = (int)$db->lastInsertId();
        }
        
        if ($adminExists) {
            // Atualizar dados do administrador existente
            $stmt = $db->prepare("
                UPDATE users 
                SET name = ?, email = ?, password = ?, functional_profile = 'APROVADOR', cost_center_id = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            
            $stmt->execute([$name, $email, $hashedPassword, $costCenterId, $adminExists['id']]);
            $message = 'Dados do usuário administrador atualizados com sucesso';
        } else {
            // Criar novo usuário admin
            $stmt = $db->prepare("
                INSERT INTO users (name, email, password, role_id, functional_profile, cost_center_id, active, created_at) 
                VALUES (?, ?, ?, 1, 'APROVADOR', ?, 1, NOW())
            ");
            
            $stmt->execute([$name, $email, $hashedPassword, $costCenterId]);
            $message = 'Usuário administrador criado com sucesso';
        }
        
        return ['success' => true, 'message' => $message];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erro ao criar usuário: ' . $e->getMessage()];
    }
}

function finalizarInstalacao() {
    try {
        // Criar arquivo de lock
        file_put_contents('config/installed.lock', date('Y-m-d H:i:s'));
        
        // Criar diretórios necessários
        $dirs = ['uploads', 'logs', 'temp'];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        
        // Criar arquivo .htaccess para segurança
        $htaccessContent = "# Proteger arquivos de configuração
<Files \"*.php\">
    <RequireAll>
        Require all denied
    </RequireAll>
</Files>

<Files \"config.php\">
    <RequireAll>
        Require all denied
    </RequireAll>
</Files>

<Files \"*.lock\">
    <RequireAll>
        Require all denied
    </RequireAll>
</Files>
";
        
        file_put_contents('config/.htaccess', $htaccessContent);
        
        return ['success' => true, 'message' => 'Instalação finalizada com sucesso'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erro na finalização: ' . $e->getMessage()];
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação - Sistema BDO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'rich-black': '#0D1B2A',
                        'oxford-blue': '#1B263B',
                        'yinmn-blue': '#415A77',
                        'silver-lake-blue': '#778DA9',
                        'platinum': '#E0E1DD',
                        'eggshell': '#F5F3F0',
                        'paynes-gray': '#5A6B7D',
                        'custom-bg': '#f8f9fa'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-custom-bg min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-rich-black mb-2">Sistema BDO</h1>
                <p class="text-paynes-gray">Assistente de Instalação</p>
            </div>

            <!-- Progress Bar -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm text-paynes-gray">Progresso da Instalação</span>
                    <span class="text-sm text-paynes-gray"><?php echo $step; ?>/5</span>
                </div>
                <div class="w-full bg-silver-lake-blue rounded-full h-2">
                    <div class="bg-yinmn-blue h-2 rounded-full transition-all duration-300" 
                         style="width: <?php echo ($step / 5) * 100; ?>%"></div>
                </div>
            </div>

            <!-- Messages -->
            <?php if (!empty($error)): ?>
                <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <div class="text-sm"><?php echo $error; ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Content -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <?php switch ($step): 
                    case 1: ?>
                        <h2 class="text-xl font-semibold text-rich-black mb-4">
                            <i class="fas fa-clipboard-check mr-2"></i>
                            Verificação de Requisitos
                        </h2>
                        
                        <div class="space-y-3 mb-6">
                            <?php foreach (verificarRequisitos() as $requisito => $status): ?>
                                <div class="flex items-center justify-between p-3 border rounded-lg">
                                    <span class="text-paynes-gray"><?php echo $requisito; ?></span>
                                    <span class="<?php echo $status ? 'text-green-600' : 'text-red-600'; ?>">
                                        <i class="fas fa-<?php echo $status ? 'check' : 'times'; ?>"></i>
                                        <?php echo $status ? 'OK' : 'ERRO'; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (array_product(verificarRequisitos())): ?>
                            <form method="POST">
                                <input type="hidden" name="step" value="1">
                                <button type="submit" class="w-full px-4 py-2 bg-yinmn-blue text-white rounded-lg hover:bg-oxford-blue transition-colors">
                                    Continuar
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                                <p>Alguns requisitos não foram atendidos. Corrija os problemas antes de continuar.</p>
                            </div>
                        <?php endif; ?>
                        <?php break; ?>

                    <?php case 2: ?>
                        <h2 class="text-xl font-semibold text-rich-black mb-4">
                            <i class="fas fa-database mr-2"></i>
                            Configuração do Banco de Dados
                        </h2>
                        
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="step" value="2">
                            <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Host do Banco</label>
                        <input type="text" name="db_host" value="localhost" required
                                       class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-yinmn-blue">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Porta</label>
                        <input type="text" name="db_port" value="3306" required
                                       class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-yinmn-blue">
                    </div>
                    
                            <div>
                                <label class="block text-sm font-medium text-paynes-gray mb-2">Nome do Banco</label>
                                <input type="text" name="db_name" placeholder="appmadeplant" required
                                       class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-yinmn-blue">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-paynes-gray mb-2">Usuário</label>
                                <input type="text" name="db_user" required
                                       class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-yinmn-blue">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-paynes-gray mb-2">Senha</label>
                                <input type="password" name="db_pass"
                                       class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-yinmn-blue">
                            </div>
                            
                            <button type="submit" class="w-full px-4 py-2 bg-yinmn-blue text-white rounded-lg hover:bg-oxford-blue transition-colors">
                                Configurar Banco
                            </button>
                        </form>
                        <?php break; ?>

                    <?php case 3: ?>
                        <h2 class="text-xl font-semibold text-rich-black mb-4">
                            <i class="fas fa-user-shield mr-2"></i>
                            Criar Usuário Administrador
                        </h2>
                        
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="step" value="3">
                            <div>
                                <label class="block text-sm font-medium text-paynes-gray mb-2">Nome Completo</label>
                                <input type="text" name="admin_name" required
                                       class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-yinmn-blue">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-paynes-gray mb-2">Email</label>
                                <input type="email" name="admin_email" required
                                       class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-yinmn-blue">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-paynes-gray mb-2">Senha</label>
                                <input type="password" name="admin_password" required minlength="6"
                                       class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-yinmn-blue">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-paynes-gray mb-2">Confirmar Senha</label>
                                <input type="password" name="admin_password_confirm" required minlength="6"
                                       class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-yinmn-blue">
                            </div>
                            
                            <button type="submit" class="w-full px-4 py-2 bg-yinmn-blue text-white rounded-lg hover:bg-oxford-blue transition-colors">
                                Criar Administrador
                            </button>
                        </form>
                        <?php break; ?>

                    <?php case 4: ?>
                        <h2 class="text-xl font-semibold text-rich-black mb-4">
                            <i class="fas fa-cog mr-2"></i>
                            Finalizar Instalação
                        </h2>
                        
                        <div class="space-y-4 mb-6">
                            <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <h3 class="font-medium text-blue-800 mb-2">Configurações Finais</h3>
                                <ul class="text-sm text-blue-700 space-y-1">
                                    <li>• Criação de diretórios necessários</li>
                                    <li>• Configuração de segurança</li>
                                    <li>• Arquivo de bloqueio de reinstalação</li>
                                </ul>
                            </div>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="step" value="4">
                            <button type="submit" class="w-full px-4 py-2 bg-yinmn-blue text-white rounded-lg hover:bg-oxford-blue transition-colors">
                                Finalizar Instalação
                            </button>
                        </form>
                        <?php break; ?>

                    <?php case 5: ?>
                        <div class="text-center">
                            <div class="mb-6">
                                <i class="fas fa-check-circle text-6xl text-green-600 mb-4"></i>
                                <h2 class="text-2xl font-semibold text-rich-black mb-2">Instalação Concluída!</h2>
                                <p class="text-paynes-gray">O Sistema BDO foi instalado com sucesso.</p>
                            </div>
                            
                            <div class="space-y-4">
                                <div class="p-4 bg-green-50 border border-green-200 rounded-lg text-left">
                                    <h3 class="font-medium text-green-800 mb-2">Próximos Passos:</h3>
                                    <ul class="text-sm text-green-700 space-y-1">
                                        <li>1. Acesse o sistema através do login</li>
                                        <li>2. Configure as frentes de serviço</li>
                                        <li>3. Cadastre os equipamentos</li>
                                        <li>4. Defina os tipos de ocorrência</li>
                                        <li>5. Crie os usuários operadores</li>
                                    </ul>
                                </div>
                                
                                <a href="login.php" class="inline-block px-6 py-3 bg-yinmn-blue text-white rounded-lg hover:bg-oxford-blue transition-colors">
                                    <i class="fas fa-sign-in-alt mr-2"></i>
                                    Acessar Sistema
                                </a>
                            </div>
                        </div>
                        <?php break; ?>
                <?php endswitch; ?>
            </div>
        </div>
    </div>
</body>
</html>
