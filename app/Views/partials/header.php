<!-- Top Bar -->
<div class="bg-primary text-white text-xs py-2 hidden md:block border-b border-white/10">
    <div class="container mx-auto px-4 flex justify-between items-center">
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
                <a href="#" class="hover:text-accent transition-colors"><i class="fab fa-facebook-f"></i></a>
                <a href="#" class="hover:text-accent transition-colors"><i class="fab fa-instagram"></i></a>
                <a href="#" class="hover:text-accent transition-colors"><i class="fab fa-linkedin-in"></i></a>
            </div>
        </div>
    </div>
</div>

<!-- Main Header -->
<header class="bg-white shadow-soft sticky top-0 z-50">
    <div class="container mx-auto px-4">
        <div class="flex justify-between items-center h-20">
            <!-- Logo -->
            <a href="/ctprice/" class="flex items-center group">
                <img src="public_html/assets/imagens/logo1.png" alt="CT Price - Organização Contábil" class="h-12 w-auto transition-transform duration-300 group-hover:scale-105">
            </a>

            <!-- Desktop Navigation -->
            <nav class="hidden md:flex items-center space-x-8">
                <a href="#home" class="text-sm font-medium text-gray-700 hover:text-secondary transition-colors">Início</a>
                <a href="#about" class="text-sm font-medium text-gray-700 hover:text-secondary transition-colors">A CT Price</a>
                <a href="#services" class="text-sm font-medium text-gray-700 hover:text-secondary transition-colors">Serviços</a>
                <a href="#clients" class="text-sm font-medium text-gray-700 hover:text-secondary transition-colors">Clientes</a>
                <a href="#testimonials" class="text-sm font-medium text-gray-700 hover:text-secondary transition-colors">Depoimentos</a>
                <a href="#news" class="text-sm font-medium text-gray-700 hover:text-secondary transition-colors">Notícias</a>
                <a href="#contact" class="px-5 py-2.5 bg-secondary text-white text-sm font-medium rounded hover:bg-secondary-light transition-all shadow-lg hover:shadow-secondary/30 transform hover:-translate-y-0.5">
                    Fale Conosco
                </a>
            </nav>

            <!-- Mobile Menu Button -->
            <button id="mobile-menu-button" class="md:hidden text-gray-700 hover:text-primary focus:outline-none">
                <i class="fas fa-bars text-2xl"></i>
            </button>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div id="mobile-menu" class="hidden md:hidden bg-white border-t border-gray-100 absolute w-full shadow-lg">
        <div class="px-4 py-3 space-y-1">
            <a href="#home" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-secondary hover:bg-gray-50 rounded-md">Início</a>
            <a href="#about" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-secondary hover:bg-gray-50 rounded-md">A CT Price</a>
            <a href="#services" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-secondary hover:bg-gray-50 rounded-md">Serviços</a>
            <a href="#clients" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-secondary hover:bg-gray-50 rounded-md">Clientes</a>
            <a href="#testimonials" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-secondary hover:bg-gray-50 rounded-md">Depoimentos</a>
            <a href="#news" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-secondary hover:bg-gray-50 rounded-md">Notícias</a>
            <a href="#contact" class="block px-3 py-2 text-base font-medium text-secondary font-bold hover:bg-gray-50 rounded-md">Fale Conosco</a>
            <div class="border-t border-gray-100 mt-2 pt-2">
                <a href="#" class="block px-3 py-2 text-sm text-gray-500 hover:text-secondary">Trabalhe Conosco</a>
                <a href="#" class="block px-3 py-2 text-sm text-gray-500 hover:text-secondary">Ouvidoria</a>
            </div>
        </div>
    </div>
</header>
