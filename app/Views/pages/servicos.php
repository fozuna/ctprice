<section class="ct-hero relative bg-primary-dark text-white overflow-hidden flex items-center">
    <div class="absolute inset-0 z-0">
        <img src="https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&w=2070&q=80" alt="Serviços" class="w-full h-full object-cover opacity-20 mix-blend-overlay">
        <div class="absolute inset-0 bg-gradient-to-r from-primary-dark via-primary-dark/90 to-transparent"></div>
    </div>

    <div class="ct-container ct-hero-inner relative z-10">
        <div class="max-w-3xl animate-fade-in-up">
            <span class="inline-block py-1 px-3 rounded-full bg-accent/10 text-accent text-sm font-semibold mb-6 border border-accent/20 backdrop-blur-sm">
                Nossos Serviços
            </span>
            <h1 class="text-4xl md:text-6xl font-display font-bold leading-tight mb-6">
                Soluções completas<br>
                <span class="text-accent">para decisões seguras</span>
            </h1>
            <p class="text-lg md:text-xl text-gray-300 mb-8 leading-relaxed max-w-2xl">
                Deixe a contabilidade nas mãos de quem entende: tecnologia, precisão e atendimento premium.
            </p>
            <div class="flex flex-col sm:flex-row gap-5">
                <a href="https://wa.me/5567992616117" target="_blank" rel="noopener noreferrer" class="px-8 py-4 bg-accent text-primary font-bold rounded hover:bg-accent-hover transition-all shadow-lg hover:shadow-accent/30 transform hover:-translate-y-1 text-center">
                    Fale com um Especialista
                </a>
                <a href="/ctprice/sobre" class="px-8 py-4 bg-transparent border border-white/30 text-white font-semibold rounded hover:bg-white/10 transition-all backdrop-blur-sm text-center">
                    Conheça a CT Price
                </a>
            </div>
        </div>
    </div>

    <div class="absolute bottom-0 right-0 w-1/3 h-1/3 bg-gradient-to-t from-primary-dark to-transparent z-10"></div>
</section>

<section class="ct-section relative overflow-hidden bg-[#050911]">
    <div class="absolute inset-0 z-0 opacity-[0.03] pointer-events-none">
        <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <pattern id="hexagons-services" width="50" height="43.4" patternUnits="userSpaceOnUse" patternTransform="scale(1.5)">
                    <path d="M25 0 L50 14.4 L50 43.3 L25 57.7 L0 43.3 L0 14.4 Z" fill="none" stroke="white" stroke-width="1"/>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#hexagons-services)"/>
        </svg>
    </div>

    <div class="absolute inset-0 z-0 bg-gradient-to-b from-[#050911] via-transparent to-[#050911] pointer-events-none"></div>
    <div class="absolute inset-0 z-0 bg-gradient-to-r from-[#050911] via-transparent to-[#050911] pointer-events-none"></div>
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-4xl h-full bg-[#10E36B] opacity-[0.02] blur-[120px] pointer-events-none rounded-full"></div>

    <div class="ct-container relative z-10">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <h4 class="text-[#10E36B] font-bold uppercase tracking-wider mb-3 text-sm">Serviços</h4>
            <h2 class="text-3xl md:text-5xl font-display font-bold text-white mb-6 leading-tight">
                Deixe a contabilidade nas mãos<br>de quem entende
            </h2>
            <p class="text-gray-400 text-lg font-light max-w-2xl mx-auto">
                Soluções completas e inteligentes para a gestão financeira e tributária do seu negócio.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php foreach ($services as $service): ?>
                <div class="bg-white rounded-2xl p-8 border border-white/5 transition-all duration-300 group hover:-translate-y-1 hover:shadow-[0_10px_40px_-10px_rgba(0,0,0,0.5)] flex flex-col h-full relative overflow-hidden">
                    <div class="w-16 h-16 rounded-xl bg-[#00222C] text-[#10E36B] flex items-center justify-center text-3xl mb-6 group-hover:scale-110 group-hover:bg-[#057038] transition-all duration-300 border border-[#10E36B]/20">
                        <i class="<?php echo htmlspecialchars($service['icon_class'] ?? 'fas fa-check'); ?>"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4 group-hover:text-[#10E36B] transition-colors">
                        <?php echo htmlspecialchars($service['title'] ?? ''); ?>
                    </h3>
                    <p class="text-gray-600 text-sm leading-relaxed mb-8 font-light flex-grow">
                        <?php echo htmlspecialchars($service['description'] ?? ''); ?>
                    </p>
                    <div class="mt-auto pt-4 border-t border-gray-100">
                        <?php
                            $link = $service['link_url'] ?? '#';
                            $href = ($link && $link !== '#') ? $link : 'https://wa.me/5567992616117';
                        ?>
                        <a href="<?php echo htmlspecialchars($href); ?>" <?php echo ($href !== '#') ? 'target="_blank" rel="noopener noreferrer"' : ''; ?> class="inline-flex items-center text-gray-900 font-semibold text-sm hover:text-[#10E36B] transition-colors group-hover:translate-x-1 duration-300">
                            Saiba Mais <i class="fas fa-arrow-right ml-2 text-xs text-[#10E36B]"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="ct-section bg-primary-dark relative overflow-hidden">
    <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-10"></div>
    <div class="ct-container relative z-10 text-center">
        <h2 class="text-3xl md:text-4xl font-display font-bold text-white mb-6">
            Pronto para começar?
        </h2>
        <p class="text-blue-100 text-lg mb-8 max-w-2xl mx-auto">
            Fale com um especialista e descubra a melhor estratégia para sua empresa.
        </p>
        <div class="flex flex-col sm:flex-row justify-center gap-5">
            <a href="https://wa.me/5567992616117" target="_blank" rel="noopener noreferrer" class="px-8 py-4 bg-green-600 text-white font-semibold rounded hover:bg-green-700 transition-all shadow-lg flex items-center justify-center">
                <i class="fab fa-whatsapp mr-2 text-xl"></i> Falar no WhatsApp
            </a>
            <a href="mailto:contato@ctpricems.com.br" class="px-8 py-4 bg-white text-primary-dark font-semibold rounded hover:bg-gray-100 transition-all shadow-lg flex items-center justify-center">
                <i class="fas fa-envelope mr-2"></i> Enviar E-mail
            </a>
        </div>
    </div>
</section>

