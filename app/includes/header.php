<?php
require_once __DIR__ . '/../config/config.php';
if (!defined('ROLE_ADMIN')) { define('ROLE_ADMIN', 1); }
if (!defined('ROLE_SUPERVISOR')) { define('ROLE_SUPERVISOR', 2); }
if (!defined('ROLE_LIDER')) { define('ROLE_LIDER', 3); }
if (!defined('ROLE_OPERADOR')) { define('ROLE_OPERADOR', 4); }
if (!defined('ROLE_COORD_RH')) { define('ROLE_COORD_RH', 5); }
if (!defined('ROLE_COMPRAS')) { define('ROLE_COMPRAS', 6); }
// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Obter informações do usuário
$database = Database::getInstance();
$db = $database->getConnection();
$auth = new Auth($db);
$currentUser = $auth->getCurrentUser();

// Sincronizar sessão com o papel mais recente do usuário (evita menu desatualizado após alterações de perfil)
if ($currentUser && isset($currentUser['role_id'])) {
    $dbRole = (int)$currentUser['role_id'];
    $sessRole = isset($_SESSION['user_role']) ? (int)$_SESSION['user_role'] : null;
    if ($sessRole !== $dbRole) {
        $_SESSION['user_role'] = $dbRole;
        if (defined('DEBUG_MODE') && DEBUG_MODE && function_exists('error_log')) {
            error_log('[menu] Role atualizado na sessão: ' . $sessRole . ' -> ' . $dbRole);
        }
    }
}
// Log de depuração sobre status do menu e contadores de cadastros
if (defined('DEBUG_MODE') && DEBUG_MODE && function_exists('error_log')) {
    try {
        $eqCount = (new Equipment($db))->count(['active' => 1]);
        $frCount = (new Front($db))->count(['active' => 1]);
        error_log('[menu] header.php mtime=' . @date('c', @filemtime(__FILE__)) . ' eqCount=' . $eqCount . ' frCount=' . $frCount . ' role=' . ($_SESSION['user_role'] ?? 'n/a'));
    } catch (Throwable $e) {
        error_log('[menu] erro ao coletar contadores: ' . $e->getMessage());
    }
}

// Link dinâmico do dashboard por perfil
$userRole = $_SESSION['user_role'] ?? null;
$dashboardEntry = ((int)$userRole === ROLE_COORD_RH) ? 'rh-dashboard.php' : 'dashboard-entregas.php';
$isDashboardActive = basename($_SERVER['PHP_SELF']) === $dashboardEntry;

$roleNames = [
    ROLE_ADMIN => 'Administrador',
    ROLE_SUPERVISOR => 'Supervisor', 
    ROLE_LIDER => 'Líder',
    ROLE_OPERADOR => 'Operador',
    ROLE_COORD_RH => 'Coordenador de RH',
    ROLE_COMPRAS => 'Compras'
];
// Helpers de assets
require_once __DIR__ . '/helpers.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Dashboard'; ?> - <?php echo SITE_NAME; ?></title>
    
    <!-- Fonte Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php
      $favPick = null; $favMtime = 0;
      $siteCfg = __DIR__ . '/../config/site.json';
      if (is_file($siteCfg)) {
        $json = @file_get_contents($siteCfg);
        $data = json_decode((string)$json, true);
        if (is_array($data) && !empty($data['favicon_path'])) {
          $rel = ltrim($data['favicon_path'], '/');
          $absRoot = __DIR__ . '/../' . $rel;
          $absPublic = __DIR__ . '/../public/' . $rel;
          if (is_file($absRoot)) {
            $favPick = $rel;
            $favMtime = @filemtime($absRoot) ?: 0;
          } elseif (is_file($absPublic)) {
            $favPick = 'public/' . $rel;
            $favMtime = @filemtime($absPublic) ?: 0;
          }
        }
      }
      if (!$favPick) {
        $favCandidates = [
          'assets/favicons/favicon.ico','assets/favicons/favicon.png','assets/favicons/favicon.jpg','assets/favicons/favicon.jpeg','assets/favicons/favicon.webp',
          'assets/favicon.ico','assets/favicon.png','assets/favicon.jpg','assets/favicon.jpeg','assets/favicon.webp',
          'public/favicon.ico','public/favicon.png','public/favicon.jpg','public/favicon.jpeg','public/favicon.webp'
        ];
        foreach ($favCandidates as $rel) {
          $abs = __DIR__ . '/../' . $rel;
          if (is_file($abs)) {
            $mt = @filemtime($abs) ?: 0;
            if ($mt >= $favMtime) { $favMtime = $mt; $favPick = $rel; }
          }
        }
      }
      if ($favPick) {
        $ext = strtolower(pathinfo($favPick, PATHINFO_EXTENSION));
        $mimeMap = ['ico'=>'image/x-icon','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','gif'=>'image/gif'];
        $mime = $mimeMap[$ext] ?? 'image/png';
        $urlRel = (strpos($favPick, 'public/') === 0) ? substr($favPick, 7) : $favPick;
        $favUrl = function_exists('asset_url') ? asset_url($urlRel) : ($urlRel);
        echo '<link rel="icon" href="'. htmlspecialchars($favUrl) . '?v=' . $favMtime . '" type="'. htmlspecialchars($mime) .'">' . PHP_EOL;
        echo '<link rel="shortcut icon" href="'. htmlspecialchars($favUrl) . '?v=' . $favMtime . '" type="'. htmlspecialchars($mime) .'">' . PHP_EOL;
      }
    ?>
    
    <!-- Tailwind via CDN com plugins oficiais -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
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
              'custom-bg': '#f8f9fa'
            },
            fontFamily: {
              'sans': ['Inter', 'system-ui', 'sans-serif'],
            },
            spacing: {
              '18': '4.5rem',
              '88': '22rem',
            },
            animation: {
              'fade-in': 'fadeIn 0.5s ease-in-out',
              'slide-in': 'slideIn 0.3s ease-out',
            },
            keyframes: {
              fadeIn: {
                '0%': { opacity: '0' },
                '100%': { opacity: '1' },
              },
              slideIn: {
                '0%': { transform: 'translateY(-10px)', opacity: '0' },
                '100%': { transform: 'translateY(0)', opacity: '1' },
              }
            }
          }
        }
      }
    </script>
    <style>
        html { font-size: 16px; }
        nav a, nav button { min-height: 44px; font-size: 1rem; line-height: 1.25; }
        a:focus-visible, button:focus-visible { outline: 3px solid #3e5c76; outline-offset: 2px; border-radius: 0.5rem; }
        @media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; } }
        .sidebar-fixed { position: fixed; top: 0; left: 0; width: 16rem; height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
        .sidebar-scroll { flex: 1 1 auto; overflow-y: auto; overscroll-behavior: contain; }
        .main-with-sidebar { margin-left: 16rem; }
        @media (max-width: 768px) {
            .main-with-sidebar { margin-left: 0; }
        }
        :root { --vvh: 100vh; --modal-safe: 24px; }
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: none; align-items: center; justify-content: center; z-index: 50; }
        .modal-overlay[aria-hidden="false"] { display: flex; }
        .modal-panel { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; border-radius: 0.75rem; box-shadow: 0 10px 25px rgba(0,0,0,.2); max-height: calc(var(--vvh, 100vh) - var(--modal-safe, 24px)); display: flex; flex-direction: column; outline: none; width: auto; max-width: 85vw; }
        @media (max-width: 768px) { .modal-panel { max-width: 95vw; } }
        @media (min-width: 768px) and (max-width: 1024px) { .modal-panel { max-width: 90vw; } }
        @media (min-width: 1025px) and (max-width: 1920px) { .modal-panel { max-width: 85vw; } }
        @media (min-width: 1921px) { .modal-panel { max-width: 60vw; } }
        .modal-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 1rem; }
        .modal-actions { flex-shrink: 0; background: #fff; padding: 0.75rem 1rem; border-top: 1px solid #e5e7eb; }
        .hidden { display: none !important; }
        html.no-scroll, body.no-scroll { overflow: hidden !important; }
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            outline: none;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: 2px solid #3b82f6;
            outline-offset: 2px;
            border-color: #3b82f6;
        }
        .form-select {
            background-color: white;
        }
        .form-textarea {
            resize: vertical;
        }
        .btn-primary {
            background-color: #2563eb;
            color: white;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        .btn-primary:hover {
            background-color: #1d4ed8;
        }
        .btn-secondary {
            background-color: #4b5563;
            color: white;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        .btn-secondary:hover {
            background-color: #374151;
        }
        .alert-info {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
        }
    </style>
    <script>
      (function(){
        function lock(b){ document.documentElement.classList.toggle('no-scroll', b); document.body.classList.toggle('no-scroll', b); }
        function updateViewportVars(){
          var vh = (window.visualViewport && window.visualViewport.height) ? window.visualViewport.height : window.innerHeight;
          document.documentElement.style.setProperty('--vvh', vh + 'px');
          var safeBottom = (typeof CSS !== 'undefined' && CSS.supports && CSS.supports('padding-bottom: env(safe-area-inset-bottom)')) ? 'calc(env(safe-area-inset-bottom, 0px) + 24px)' : '24px';
          document.documentElement.style.setProperty('--modal-safe', safeBottom);
        }
        updateViewportVars();
        window.addEventListener('resize', updateViewportVars);
        if (window.visualViewport) {
          window.visualViewport.addEventListener('resize', updateViewportVars);
          window.visualViewport.addEventListener('scroll', updateViewportVars);
        }
        window.modalOpen = function(id){
          var m = document.getElementById(id); if(!m) return;
          m.classList.remove('hidden'); m.setAttribute('aria-hidden','false'); lock(true);
          setTimeout(function(){ var p = m.querySelector('.modal-panel'); if(p){ try{ p.focus(); }catch(e){} } },0);
        };
        window.modalClose = function(id){
          var m = document.getElementById(id); if(!m) return;
          m.setAttribute('aria-hidden','true'); m.classList.add('hidden'); lock(false);
        };
        document.addEventListener('keydown', function(e){
          if(e.key === 'Escape'){
            var modals = Array.prototype.slice.call(document.querySelectorAll('.modal-overlay[aria-hidden=\"false\"]'));
            if(modals.length){ var m = modals[modals.length - 1]; modalClose(m.id); }
          }
        });
        document.addEventListener('click', function(e){
          var ov = e.target.closest('.modal-overlay');
          if(ov && e.target === ov){ modalClose(ov.id); }
        });
      })();
    </script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" />
</head>
<body class="bg-custom-bg">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:bg-white focus:text-black focus:px-4 focus:py-2 focus:rounded">Pular para o conteúdo</a>
    <div class="flex h-screen" x-data="{ sidebarOpen: false }">
        <div class="bg-rich-black text-white w-64 flex-shrink-0 sidebar-fixed transition-transform duration-300 ease-out z-40 -translate-x-full md:translate-x-0" :class="{ 'translate-x-0': sidebarOpen, '-translate-x-full': !sidebarOpen }">
            <div class="md:hidden absolute top-3 right-3">
                <button type="button" @click="sidebarOpen = false" class="p-2 rounded-lg hover:bg-prussian-blue" aria-label="Fechar menu">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-4">
                <?php $logoPath = __DIR__ . '/../assets/logowhite.png'; $logoVer = file_exists($logoPath) ? filemtime($logoPath) : time(); ?>
                <?php $logoUrl = asset_url('assets/logowhite.png') . '?v=' . $logoVer; ?>
                <img src="<?php echo $logoUrl; ?>" alt="Logo Madeplant" class="mx-auto mb-6 max-w-full h-auto object-contain" style="max-height: 80px; width: auto;">
                <h1 class="text-xl font-bold text-center">Controller Madeplant</h1>
                <p class="text-sm text-silver-lake-blue text-center">Controle de Produção</p>
            </div>
            
            <!-- Menu Navigation -->
            <nav class="mt-8 sidebar-scroll" role="navigation" aria-label="Menu principal">
                <div class="px-4 py-2">
                    <a href="<?php echo $dashboardEntry; ?>" class="flex items-center px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors <?php echo $isDashboardActive ? 'bg-paynes-gray' : ''; ?>">
                        <i class="fas fa-tachometer-alt mr-3"></i>
                        Dashboard
                    </a>
                </div>
                
                <?php if ($auth->hasPermission(ROLE_LIDER)): ?>
                <!-- Cadastros -->
                <div class="px-4 py-2" x-data="{ open: false }">
                    <button @click="open = !open" class="flex items-center justify-between w-full px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors" :aria-expanded="open.toString()" aria-controls="menu-cadastros">
                        <div class="flex items-center">
                            <i class="fas fa-database mr-3"></i>
                            Cadastros
                        </div>
                        <i class="fas fa-chevron-down transform transition-transform" :class="{ 'rotate-180': open }"></i>
                    </button>
                    <div id="menu-cadastros" x-show="open" x-transition class="ml-4 mt-2 space-y-1">
                        <?php if ($auth->hasPermission(ROLE_ADMIN)): ?>
                        <a href="users.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors">
                            <i class="fas fa-users mr-2"></i>
                            Usuários
                        </a>
                        <a href="cost-centers.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors">
                            <i class="fas fa-sitemap mr-2"></i>
                            Centros de Custo
                        </a>
                        <?php endif; ?>
                        <a href="equipments.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors">
                            <i class="fas fa-cogs mr-2"></i>
                            Equipamentos
                        </a>
                        <a href="fronts.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors">
                            <i class="fas fa-map-marker-alt mr-2"></i>
                            Frentes
                        </a>
                        <a href="occurrence-types.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors">
                            <i class="fas fa-tags mr-2"></i>
                            Tipos de Ocorrência
                        </a>
                        <a href="clients.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors">
                            <i class="fas fa-building mr-2"></i>
                            Clientes
                        </a>
                        <?php if ($auth->hasPermission(ROLE_SUPERVISOR)): ?>
                        <a href="equipments-admin.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors">
                            <i class="fas fa-wrench mr-2"></i>
                            Admin. de Equipamentos
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (in_array($_SESSION['user_role'] ?? null, [ROLE_ADMIN, ROLE_COORD_RH], true)): ?>
                <div class="px-4 py-2" x-data="{ open: <?php echo in_array(basename($_SERVER['PHP_SELF']), ['rh-vagas.php','rh-kanban.php']) ? 'true' : 'false'; ?> }">
                    <button @click="open = !open" class="flex items-center justify-between w-full px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors" :aria-expanded="open.toString()" aria-controls="menu-rh">
                        <div class="flex items-center">
                            <i class="fas fa-user-tie mr-3"></i>
                            RH
                        </div>
                        <i class="fas fa-chevron-down transform transition-transform" :class="{ 'rotate-180': open }"></i>
                    </button>
                    <div id="menu-rh" x-show="open" x-transition class="ml-4 mt-2 space-y-1">
                        <a href="rh-dashboard.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'rh-dashboard.php' ? 'bg-paynes-gray' : ''; ?>">
                            <i class="fas fa-chart-line mr-2"></i>
                            Dashboard RH
                        </a>
                        <a href="rh-vagas.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'rh-vagas.php' ? 'bg-paynes-gray' : ''; ?>">
                            <i class="fas fa-briefcase mr-2"></i>
                            Vagas de Trabalho
                        </a>
                        <a href="rh-kanban.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'rh-kanban.php' ? 'bg-paynes-gray' : ''; ?>">
                            <i class="fas fa-columns mr-2"></i>
                            Kanban de Currículos
                        </a>
                        <a href="rh-interview.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'rh-interview.php' ? 'bg-paynes-gray' : ''; ?>">
                            <i class="fas fa-calendar-check mr-2"></i>
                            Agendar Entrevista
                        </a>
                        <a href="rh-solicitacao-vaga.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'rh-solicitacao-vaga.php' ? 'bg-paynes-gray' : ''; ?>">
                            <i class="fas fa-file-signature mr-2"></i>
                            Solicitação de Vaga
                        </a>
                        <a href="rh-data.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'rh-data.php' ? 'bg-paynes-gray' : ''; ?>">
                            <i class="fas fa-database mr-2"></i>
                            Dados Manuais do RH
                        </a>
                        <a href="vagas.php" target="_blank" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors">
                            <i class="fas fa-user-plus mr-2"></i>
                            Link de Cadastro Externo
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (in_array($_SESSION['user_role'] ?? null, [ROLE_ADMIN, ROLE_COMPRAS], true)): ?>
                <div class="px-4 py-2" x-data="{ open: <?php echo in_array(basename($_SERVER['PHP_SELF']), ['purchases-dashboard.php','suppliers.php','products-catalog.php','rfq-list.php']) ? 'true' : 'false'; ?> }">
                    <button @click="open = !open" class="flex items-center justify-between w-full px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors" :aria-expanded="open.toString()" aria-controls="menu-compras">
                        <div class="flex items-center">
                            <i class="fas fa-shopping-cart mr-3"></i>
                            Compras
                        </div>
                        <i class="fas fa-chevron-down transform transition-transform" :class="{ 'rotate-180': open }"></i>
                    </button>
                    <div id="menu-compras" x-show="open" x-transition class="ml-4 mt-2 space-y-1">
                        <a href="purchases-dashboard.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'purchases-dashboard.php' ? 'bg-paynes-gray' : ''; ?>">
                            <i class="fas fa-chart-pie mr-2"></i>
                            Dashboard de Compras
                        </a>
                        <a href="rfq-list.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'rfq-list.php' ? 'bg-paynes-gray' : ''; ?>">
                            <i class="fas fa-file-invoice-dollar mr-2"></i>
                            Solicitações de Cotação
                        </a>
                        <a href="suppliers.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'suppliers.php' ? 'bg-paynes-gray' : ''; ?>">
                            <i class="fas fa-industry mr-2"></i>
                            Fornecedores
                        </a>
                        <a href="products-catalog.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'products-catalog.php' ? 'bg-paynes-gray' : ''; ?>">
                            <i class="fas fa-box-open mr-2"></i>
                            Catálogo de Produtos
                        </a>
                        <a href="cost-centers.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'cost-centers.php' ? 'bg-paynes-gray' : ''; ?>">
                            <i class="fas fa-sitemap mr-2"></i>
                            Centros de Custo
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Movimentos -->
                <div class="px-4 py-2" x-data="{ open: false }">
                    <button @click="open = !open" class="flex items-center justify-between w-full px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors" :aria-expanded="open.toString()" aria-controls="menu-movimentos">
                        <div class="flex items-center">
                            <i class="fa-solid fa-repeat mr-2"></i>
                            Movimentos
                        </div>
                        <i class="fas fa-chevron-down transform transition-transform" :class="{ 'rotate-180': open }"></i>
                    </button>
                    <div id="menu-movimentos" x-show="open" x-transition class="ml-4 mt-2 space-y-1">
                        <a href="occurrences.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors">
                            <i class="fa-solid fa-screwdriver-wrench mr-2"></i>
                            Ocorrências
                        </a>
                        <a href="new-occurrence.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors">
                            <i class="fa-solid fa-car-burst mr-2"></i>
                            Nova Ocorrência
                        </a>
                        <a href="equipment-mobilization.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors">
                            <i class="fas fa-truck mr-2"></i>
                            Mobilização de Equipamentos
                        </a>
                    </div>
                </div>
                
                <?php if ($auth->hasPermission(ROLE_LIDER)): ?>
                <!-- Gestão -->
                <div class="px-4 py-2" x-data="{ open: false }">
                    <button @click="open = !open" class="flex items-center justify-between w-full px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors" :aria-expanded="open.toString()" aria-controls="menu-gestao">
                        <div class="flex items-center">
                            <i class="fa-solid fa-arrows-to-circle mr-3"></i>
                            Gestão
                        </div>
                        <i class="fas fa-chevron-down transform transition-transform" :class="{ 'rotate-180': open }"></i>
                    </button>
                    <div id="menu-gestao" x-show="open" x-transition class="ml-4 mt-2 space-y-1">
                        <a href="dashboard-metas.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard-metas.php' ? 'bg-paynes-gray' : ''; ?>">
                            <i class="fas fa-chart-line mr-2"></i>
                            Dashboard de Metas
                        </a>
                        <div class="mt-1" x-data="{ openGoals: <?php echo in_array(basename($_SERVER['PHP_SELF']), ['goals.php','goals-production.php','goals-delivery.php']) ? 'true' : 'false'; ?> }">
                            <div class="flex items-center justify-between w-full px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'goals.php' ? 'bg-paynes-gray' : ''; ?>">
                                <a href="goals.php" class="flex items-center">
                                    <i class="fa-solid fa-bullseye mr-2"></i>
                                    Metas
                                </a>
                                <button @click="openGoals = !openGoals" class="text-white/80 hover:text-white" :aria-expanded="openGoals.toString()" aria-controls="submenu-metas">
                                    <i class="fas fa-chevron-down transform transition-transform" :class="{ 'rotate-180': openGoals }"></i>
                                </button>
                            </div>
                            <div id="submenu-metas" x-show="openGoals" x-transition class="ml-4 mt-2 space-y-1">
                                <?php $isOpening = basename($_SERVER['PHP_SELF']) === 'opening-balance.php'; ?>
                                <a href="opening-balance.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors <?php echo $isOpening ? 'bg-paynes-gray' : ''; ?>">
                                    <i class="fas fa-clipboard-list mr-2"></i>
                                    Saldos Iniciais
                                </a>
                                <a href="goals-production.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'goals-production.php' ? 'bg-paynes-gray' : ''; ?>">
                                    <i class="fas fa-industry mr-2"></i>
                                    Metas de Produção
                                </a>
                                <a href="goals-delivery.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'goals-delivery.php' ? 'bg-paynes-gray' : ''; ?>">
                                    <i class="fas fa-truck-loading mr-2"></i>
                                    Metas de Entrega
                                </a>
                                <?php $isStockReport = (basename($_SERVER['PHP_SELF']) === 'reports.php') && (($_GET['report_type'] ?? '') === 'stock'); ?>
                                <a href="reports.php?report_type=stock" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors <?php echo $isStockReport ? 'bg-paynes-gray' : ''; ?>">
                                    <i class="fas fa-warehouse mr-2"></i>
                                    Saldo Inicial (Estoque)
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($auth->hasPermission(ROLE_SUPERVISOR)): ?>
                <!-- Relatórios -->
                <div class="px-4 py-2" x-data="{ open: false }">
                    <button @click="open = !open" class="flex items-center justify-between w-full px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors" :aria-expanded="open.toString()" aria-controls="menu-relatorios">
                        <div class="flex items-center">
                            <i class="fas fa-chart-bar mr-3"></i>
                            Relatórios
                        </div>
                        <i class="fas fa-chevron-down transform transition-transform" :class="{ 'rotate-180': open }"></i>
                    </button>
                    <div id="menu-relatorios" x-show="open" x-transition class="ml-4 mt-2 space-y-1">
                        <a href="reports-equipment.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors">
                            <i class="fas fa-cog mr-2"></i>
                            Por Equipamento
                        </a>
                        <a href="reports-front.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors">
                            <i class="fas fa-map mr-2"></i>
                            Por Frente
                        </a>
                        <a href="reports-operator.php" class="block px-4 py-2 rounded-lg hover:bg-prussian-blue transition-colors">
                            <i class="fas fa-user mr-2"></i>
                            Por Operador
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </nav>
            
            <!-- User Info -->
            <div class="w-64 p-4 border-t border-prussian-blue">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-paynes-gray rounded-full flex items-center justify-center">
                        <i class="fas fa-user text-sm"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate"><?php echo htmlspecialchars($currentUser['name']); ?></p>
                        <p class="text-xs text-silver-lake-blue truncate"><?php echo $roleNames[$currentUser['role_id']] ?? 'Usuário'; ?></p>
                    </div>
                    <a href="logout.php" class="text-silver-lake-blue hover:text-white transition-colors" title="Sair">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
        <div class="fixed inset-0 bg-black/40 md:hidden" x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false"></div>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden main-with-sidebar">
            <!-- Top Header -->
            <header class="bg-white shadow-sm border-b border-silver-lake-blue">
                <div class="px-4 py-3 sm:px-6 sm:py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <button type="button" class="md:hidden p-2 rounded-lg hover:bg-gray-100 mr-2" aria-label="Abrir menu" @click="sidebarOpen = true">
                                <i class="fas fa-bars"></i>
                            </button>
                            <h2 class="text-2xl font-semibold text-rich-black">
                            <?php echo $pageTitle ?? 'Dashboard'; ?>
                            </h2>
                        </div>
                        <div class="flex items-center space-x-4">
                            <span class="text-base text-paynes-gray">
                                <?php echo date('d/m/Y H:i'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <main id="main-content" class="flex-1 overflow-y-auto p-4 sm:p-6">
