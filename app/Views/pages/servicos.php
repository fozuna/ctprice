<section class="ct-hero ct-hero--internal relative bg-primary-dark text-white overflow-hidden flex items-center">
    <div class="absolute inset-0 z-0">
        <img src="https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&w=2070&q=80" alt="Serviços" class="ct-hero-media w-full h-full object-cover mix-blend-overlay">
        <div class="ct-hero-overlay absolute inset-0"></div>
    </div>

    <div class="ct-container ct-hero-inner relative z-10">
        <div class="max-w-2xl animate-fade-in-up">
            <h1 class="ct-hero-title text-3xl md:text-5xl font-display font-bold leading-tight mb-4 md:mb-5">
                Soluções completas<br>
                <span class="ct-hero-accent">para decisões seguras</span>
            </h1>
            <p class="ct-hero-copy text-base md:text-lg leading-relaxed max-w-xl">
                Deixe a contabilidade nas mãos de quem entende: tecnologia, precisão e atendimento premium.
            </p>
        </div>
    </div>

    <div class="ct-hero-fade absolute bottom-0 right-0 w-1/3 h-1/3 z-10"></div>
</section>

<section class="ct-section ct-section-surface relative overflow-hidden">
    <div class="ct-surface-pattern absolute inset-0 z-0 pointer-events-none">
        <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <pattern id="hexagons-services" width="50" height="43.4" patternUnits="userSpaceOnUse" patternTransform="scale(1.5)">
                    <path d="M25 0 L50 14.4 L50 43.3 L25 57.7 L0 43.3 L0 14.4 Z" fill="none" stroke="white" stroke-width="1"/>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#hexagons-services)"/>
        </svg>
    </div>

    <div class="ct-surface-overlay-y absolute inset-0 z-0 pointer-events-none"></div>
    <div class="ct-surface-overlay-x absolute inset-0 z-0 pointer-events-none"></div>
    <div class="ct-surface-glow absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-4xl h-full bg-[#10E36B] blur-[120px] pointer-events-none rounded-full"></div>

    <div class="ct-container relative z-10">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <h4 class="text-[#10E36B] font-bold uppercase tracking-wider mb-3 text-sm">Serviços</h4>
            <h2 class="text-3xl md:text-5xl font-display font-bold text-gray-900 mb-6 leading-tight">
                Deixe a contabilidade nas mãos<br>de quem entende
            </h2>
            <p class="text-gray-600 text-lg font-light max-w-2xl mx-auto">
                Soluções completas e inteligentes para a gestão financeira e tributária do seu negócio.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php foreach ($services as $service): ?>
                <div class="bg-white rounded-2xl p-8 border border-emerald-100/80 transition-all duration-300 group hover:-translate-y-1 hover:shadow-[0_20px_45px_-24px_rgba(0,34,44,0.35)] flex flex-col h-full relative overflow-hidden">
                    <div class="w-16 h-16 rounded-xl bg-emerald-50 text-secondary flex items-center justify-center text-3xl mb-6 group-hover:scale-110 group-hover:bg-secondary group-hover:text-white transition-all duration-300 border border-emerald-100">
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

<section class="ct-section ct-cta-surface relative overflow-hidden">
    <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-[0.03]"></div>
    <div class="ct-container relative z-10 text-center">
        <h2 class="ct-cta-title text-3xl md:text-4xl font-display font-bold mb-6">
            Pronto para começar?
        </h2>
        <p class="ct-cta-copy text-lg mb-8 max-w-2xl mx-auto">
            Fale com um especialista e descubra a melhor estratégia para sua empresa.
        </p>
        <div class="flex flex-col sm:flex-row justify-center gap-5">
            <a href="https://wa.me/5567992616117" target="_blank" rel="noopener noreferrer" class="px-8 py-4 bg-green-600 text-white font-semibold rounded hover:bg-green-700 transition-all shadow-lg flex items-center justify-center">
                <i class="fab fa-whatsapp mr-2 text-xl"></i> Falar no WhatsApp
            </a>
            <a href="mailto:contato@ctpricems.com.br" class="ct-cta-email px-8 py-4 font-semibold rounded transition-all shadow-lg flex items-center justify-center">
                <i class="fas fa-envelope mr-2"></i> Enviar E-mail
            </a>
        </div>
    </div>
</section>

