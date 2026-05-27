<!-- Top Bar -->
<?php
$baseUrl = defined('APP_URL') ? APP_URL : '';
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$reqPath = $reqPath === null ? '/' : $reqPath;
if ($baseUrl !== '' && strpos($reqPath, $baseUrl) === 0) {
    $reqPath = substr($reqPath, strlen($baseUrl));
}
if ($reqPath === '') {
    $reqPath = '/';
}
if ($reqPath !== '/' && substr($reqPath, -1) === '/') {
    $reqPath = rtrim($reqPath, '/');
}

$hrefHome = ($baseUrl !== '' ? $baseUrl : '') . '/';
$hrefSobre = ($baseUrl !== '' ? $baseUrl : '') . '/sobre';
$hrefServicos = ($baseUrl !== '' ? $baseUrl : '') . '/servicos';
$hrefClientes = ($baseUrl !== '' ? $baseUrl : '') . '/clientes';
$hrefParceiros = ($baseUrl !== '' ? $baseUrl : '') . '/parceiros';
$hrefDepoimentos = ($baseUrl !== '' ? $baseUrl : '') . '/depoimentos';
$hrefNoticias = ($baseUrl !== '' ? $baseUrl : '') . '/noticias';
$hrefFaleConosco = ($baseUrl !== '' ? $baseUrl : '') . '/fale-conosco';

$isHome = $reqPath === '/';
$isSobre = $reqPath === '/sobre';
$isServicos = $reqPath === '/servicos';
$isClientes = $reqPath === '/clientes';
$isParceiros = $reqPath === '/parceiros';
$isDepoimentos = $reqPath === '/depoimentos';
$isNoticias = $reqPath === '/noticias';
$isFaleConosco = $reqPath === '/fale-conosco';
?>
<div class="bg-primary text-white text-xs py-2 hidden md:block border-b border-white/10">
    <div class="ct-container flex justify-between items-center">
        <div class="flex items-center space-x-6">
            <span class="flex items-center hover:text-accent transition-colors cursor-pointer">
                <i class="fas fa-map-marker-alt mr-2 text-accent"></i>
                R. José Antônio, 2.777 | Monte Castelo - Campo Grande - MS
            </span>
            <a href="mailto:contato@ctpricems.com.br" class="flex items-center hover:text-accent transition-colors">
               <i class="fas fa-envelope mr-2 text-accent"></i>
                contato@ctpricems.com.br
            </a>
        </div>
        <div class="flex items-center space-x-4">
            <a href="#" class="hover:text-accent transition-colors">Trabalhe Conosco</a>
            <span class="text-gray-500">|</span>
            <a href="#" class="hover:text-accent transition-colors">Ouvidoria</a>
            <div class="flex space-x-3 ml-4">
                <a href="#" class="ct-hit rounded-full hover:text-accent transition-colors" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                <a href="#" class="ct-hit rounded-full hover:text-accent transition-colors" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                <a href="#" class="ct-hit rounded-full hover:text-accent transition-colors" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
            </div>
        </div>
    </div>
</div>

<!-- Main Header -->
<header class="bg-white shadow-soft sticky top-0 z-50">
    <div class="ct-container">
        <div class="flex justify-between items-center h-20">
            <!-- Logo -->
            <a href="<?= htmlspecialchars($hrefHome) ?>" class="flex items-center group" aria-label="CT Price">
                <img src="public_html/assets/imagens/logo1.png" alt="CT Price - Organização Contábil" class="h-12 w-auto transition-transform duration-300 group-hover:scale-105">
            </a>

            <!-- Desktop Navigation -->
            <nav class="hidden md:flex items-center space-x-5">
                <a href="<?= htmlspecialchars($hrefHome) ?>" class="text-sm font-medium py-2 <?= $isHome ? 'text-secondary font-semibold' : 'text-gray-700' ?> hover:text-secondary transition-colors" <?= $isHome ? 'aria-current="page"' : '' ?>>Início</a>
                <a href="<?= htmlspecialchars($hrefSobre) ?>" class="text-sm font-medium py-2 <?= $isSobre ? 'text-secondary font-semibold' : 'text-gray-700' ?> hover:text-secondary transition-colors" <?= $isSobre ? 'aria-current="page"' : '' ?>>A CT Price</a>
                <a href="<?= htmlspecialchars($hrefServicos) ?>" class="text-sm font-medium py-2 <?= $isServicos ? 'text-secondary font-semibold' : 'text-gray-700' ?> hover:text-secondary transition-colors" <?= $isServicos ? 'aria-current="page"' : '' ?>>Serviços</a>
                <a href="<?= htmlspecialchars($hrefClientes) ?>" class="text-sm font-medium py-2 <?= $isClientes ? 'text-secondary font-semibold' : 'text-gray-700' ?> hover:text-secondary transition-colors" <?= $isClientes ? 'aria-current="page"' : '' ?>>Clientes</a>
                <a href="<?= htmlspecialchars($hrefParceiros) ?>" class="text-sm font-medium py-2 <?= $isParceiros ? 'text-secondary font-semibold' : 'text-gray-700' ?> hover:text-secondary transition-colors" <?= $isParceiros ? 'aria-current="page"' : '' ?>>Parceiros</a>
                <a href="<?= htmlspecialchars($hrefDepoimentos) ?>" class="text-sm font-medium py-2 <?= $isDepoimentos ? 'text-secondary font-semibold' : 'text-gray-700' ?> hover:text-secondary transition-colors" <?= $isDepoimentos ? 'aria-current="page"' : '' ?>>Depoimentos</a>
                <a href="<?= htmlspecialchars($hrefNoticias) ?>" class="text-sm font-medium py-2 <?= $isNoticias ? 'text-secondary font-semibold' : 'text-gray-700' ?> hover:text-secondary transition-colors" <?= $isNoticias ? 'aria-current="page"' : '' ?>>Notícias</a>
                <a href="<?= htmlspecialchars($hrefFaleConosco) ?>" class="text-sm font-medium py-2 <?= $isFaleConosco ? 'text-secondary font-semibold' : 'text-gray-700' ?> hover:text-secondary transition-colors" <?= $isFaleConosco ? 'aria-current="page"' : '' ?>>Fale Conosco</a>
                <a href="https://wa.me/5567992616117" target="_blank" rel="noopener noreferrer" aria-label="Conversar no WhatsApp" title="Conversar no WhatsApp" class="ct-hit w-11 h-11 bg-secondary text-white rounded-full hover:bg-secondary-light transition-all shadow-lg hover:shadow-secondary/30 transform hover:-translate-y-0.5">
                    <i class="fab fa-whatsapp text-lg" aria-hidden="true"></i>
                </a>
            </nav>

            <!-- Mobile Menu Button -->
            <button id="mobile-menu-button" class="ct-hit md:hidden text-gray-700 hover:text-primary focus:outline-none" aria-label="Abrir menu">
                <i class="fas fa-bars text-2xl"></i>
            </button>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div id="mobile-menu" class="hidden md:hidden bg-white border-t border-gray-100 absolute w-full shadow-lg">
        <div class="px-4 py-3 space-y-1">
            <a href="<?= htmlspecialchars($hrefHome) ?>" class="flex items-center min-h-[44px] px-3 py-2 text-base font-medium <?= $isHome ? 'text-secondary bg-gray-50' : 'text-gray-700' ?> hover:text-secondary hover:bg-gray-50 rounded-md" <?= $isHome ? 'aria-current="page"' : '' ?>>Início</a>
            <a href="<?= htmlspecialchars($hrefSobre) ?>" class="flex items-center min-h-[44px] px-3 py-2 text-base font-medium <?= $isSobre ? 'text-secondary bg-gray-50' : 'text-gray-700' ?> hover:text-secondary hover:bg-gray-50 rounded-md" <?= $isSobre ? 'aria-current="page"' : '' ?>>A CT Price</a>
            <a href="<?= htmlspecialchars($hrefServicos) ?>" class="flex items-center min-h-[44px] px-3 py-2 text-base font-medium <?= $isServicos ? 'text-secondary bg-gray-50' : 'text-gray-700' ?> hover:text-secondary hover:bg-gray-50 rounded-md" <?= $isServicos ? 'aria-current="page"' : '' ?>>Serviços</a>
            <a href="<?= htmlspecialchars($hrefClientes) ?>" class="flex items-center min-h-[44px] px-3 py-2 text-base font-medium <?= $isClientes ? 'text-secondary bg-gray-50' : 'text-gray-700' ?> hover:text-secondary hover:bg-gray-50 rounded-md" <?= $isClientes ? 'aria-current="page"' : '' ?>>Clientes</a>
            <a href="<?= htmlspecialchars($hrefParceiros) ?>" class="flex items-center min-h-[44px] px-3 py-2 text-base font-medium <?= $isParceiros ? 'text-secondary bg-gray-50' : 'text-gray-700' ?> hover:text-secondary hover:bg-gray-50 rounded-md" <?= $isParceiros ? 'aria-current="page"' : '' ?>>Parceiros</a>
            <a href="<?= htmlspecialchars($hrefDepoimentos) ?>" class="flex items-center min-h-[44px] px-3 py-2 text-base font-medium <?= $isDepoimentos ? 'text-secondary bg-gray-50' : 'text-gray-700' ?> hover:text-secondary hover:bg-gray-50 rounded-md" <?= $isDepoimentos ? 'aria-current="page"' : '' ?>>Depoimentos</a>
            <a href="<?= htmlspecialchars($hrefNoticias) ?>" class="flex items-center min-h-[44px] px-3 py-2 text-base font-medium <?= $isNoticias ? 'text-secondary bg-gray-50' : 'text-gray-700' ?> hover:text-secondary hover:bg-gray-50 rounded-md" <?= $isNoticias ? 'aria-current="page"' : '' ?>>Notícias</a>
            <a href="<?= htmlspecialchars($hrefFaleConosco) ?>" class="flex items-center min-h-[44px] px-3 py-2 text-base font-medium <?= $isFaleConosco ? 'text-secondary bg-gray-50' : 'text-gray-700' ?> hover:text-secondary hover:bg-gray-50 rounded-md" <?= $isFaleConosco ? 'aria-current="page"' : '' ?>>Fale Conosco</a>
            <a href="https://wa.me/5567992616117" target="_blank" rel="noopener noreferrer" class="flex items-center min-h-[44px] px-3 py-2 text-base font-medium text-secondary hover:bg-gray-50 rounded-md">
                <i class="fab fa-whatsapp mr-3 text-lg" aria-hidden="true"></i> WhatsApp
            </a>
            <div class="border-t border-gray-100 mt-2 pt-2">
                <a href="#" class="flex items-center min-h-[44px] px-3 py-2 text-sm text-gray-500 hover:text-secondary">Trabalhe Conosco</a>
                <a href="#" class="flex items-center min-h-[44px] px-3 py-2 text-sm text-gray-500 hover:text-secondary">Ouvidoria</a>
            </div>
        </div>
    </div>
</header>
