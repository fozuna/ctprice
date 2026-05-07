<section class="ct-hero relative bg-primary-dark text-white overflow-hidden flex items-center">
    <div class="absolute inset-0 z-0">
        <img src="https://images.unsplash.com/photo-1557804506-669a67965ba0?auto=format&fit=crop&w=2070&q=80" alt="Depoimentos" class="w-full h-full object-cover opacity-20 mix-blend-overlay">
        <div class="absolute inset-0 bg-gradient-to-r from-primary-dark via-primary-dark/90 to-transparent"></div>
    </div>

    <div class="ct-container ct-hero-inner relative z-10">
        <div class="max-w-3xl animate-fade-in-up">
            <span class="inline-block py-1 px-3 rounded-full bg-accent/10 text-accent text-sm font-semibold mb-6 border border-accent/20 backdrop-blur-sm">
                Depoimentos
            </span>
            <h1 class="text-4xl md:text-6xl font-display font-bold leading-tight mb-6">
                O que nossos clientes dizem
            </h1>
            <p class="text-lg md:text-xl text-gray-300 mb-8 leading-relaxed max-w-2xl">
                Histórias de confiança construídas com parceria, consistência e resultados.
            </p>
            <div class="flex flex-col sm:flex-row gap-5">
                <a href="/ctprice/clientes" class="px-8 py-4 bg-accent text-primary font-bold rounded hover:bg-accent-hover transition-all shadow-lg hover:shadow-accent/30 transform hover:-translate-y-1 text-center">
                    Ver Clientes
                </a>
                <a href="https://wa.me/5567992616117" target="_blank" rel="noopener noreferrer" class="px-8 py-4 bg-transparent border border-white/30 text-white font-semibold rounded hover:bg-white/10 transition-all backdrop-blur-sm text-center">
                    Fale com um Especialista
                </a>
            </div>
        </div>
    </div>

    <div class="absolute bottom-0 right-0 w-1/3 h-1/3 bg-gradient-to-t from-primary-dark to-transparent z-10"></div>
</section>

<section class="ct-section bg-gray-50">
    <div class="ct-container">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <h4 class="text-secondary font-semibold uppercase tracking-wider mb-2">Depoimentos</h4>
            <h2 class="text-3xl md:text-4xl font-display font-bold text-gray-900 mb-4">
                O que nossos clientes dizem
            </h2>
            <p class="text-gray-600">
                Histórias de sucesso construídas com parceria e confiança.
            </p>
        </div>

        <?php
        $demoTestimonials = [
            [
                'name' => 'Aline Zacarini',
                'role' => 'Sócia da Agro Só Sal e Cliente CT Price.',
                'company_logo_text' => 'Só Sal',
                'company_subtext' => 'PRODUTOS AGROPECUÁRIOS - A Família do Agro',
                'content' => 'Contar com a CT price é a certeza de estar sempre com a melhor parceira.',
                'avatar_url' => 'https://ctprice.com.br/wp/wp-content/uploads/2026/02/AlineZacarini-1024x1020.jpeg',
                'video_thumb' => 'https://ctprice.com.br/wp/wp-content/uploads/2026/02/Thumb_Aline.jpeg',
                'youtube_id' => 'Vr9EFGVx0T8',
                'socials' => true
            ],
            [
                'name' => 'Bruno Alessio',
                'role' => 'Sócio da Soldamaq e Cliente CT Price.',
                'company_logo_text' => 'SOLDAMAQ',
                'company_subtext' => '+mais profissional',
                'content' => 'CT Price: nossa parceira há 3 anos, trazendo visão estratégica e gerencial para melhorar nossa performance.',
                'avatar_url' => 'https://ctprice.com.br/wp/wp-content/uploads/2026/02/Bruno-Thumb-1018x1024.png',
                'video_thumb' => 'https://ctprice.com.br/wp/wp-content/uploads/2026/02/Thumb_Bruno.png',
                'youtube_id' => 'Cq4w62rSpyE',
                'socials' => true
            ],
            [
                'name' => 'Réus Fornari',
                'role' => 'Proprietário da Cotto Figueira e Cliente CT Price.',
                'company_logo_text' => 'Cotto Figueira',
                'company_subtext' => 'www.cottofigueira.com',
                'content' => 'Há mais de 15 anos com a CT Price: comunicação próxima e o conforto de crescer com segurança.',
                'avatar_url' => 'https://ctprice.com.br/wp/wp-content/uploads/2026/02/Reus-Thumb-1018x1024.png',
                'video_thumb' => 'https://ctprice.com.br/wp/wp-content/uploads/2026/02/Thumb_Reus.png',
                'youtube_id' => 'eyPTwRBjzU0',
                'socials' => true
            ]
        ];

        $displayData = !empty($testimonials) ? array_map(function($t) {
            $name = $t['client_name'] ?? '';
            $company = $t['client_company'] ?? 'Cliente CT Price';
            $avatar = $t['image_url'] ?? ('https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=10E36B&color=00222C');
            return [
                'name' => $name,
                'role' => $company,
                'company_logo_text' => $company,
                'company_subtext' => '',
                'content' => $t['content'] ?? '',
                'avatar_url' => $avatar,
                'video_thumb' => 'https://images.unsplash.com/photo-1557804506-669a67965ba0?auto=format&fit=crop&q=80&w=900',
                'youtube_id' => 'Vr9EFGVx0T8',
                'socials' => true
            ];
        }, $testimonials) : $demoTestimonials;
        ?>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php foreach ($displayData as $testimonial): ?>
                <div class="bg-white p-8 rounded-xl shadow-[0_10px_40px_-10px_rgba(0,0,0,0.08)] hover:shadow-2xl transition-all duration-300 flex flex-col h-full border border-gray-100 group testimonial-card">
                    <div class="flex gap-5 mb-6">
                        <div class="w-20 h-20 flex-shrink-0 rounded-xl overflow-hidden shadow-sm">
                            <img src="<?php echo htmlspecialchars($testimonial['avatar_url']); ?>" alt="<?php echo htmlspecialchars($testimonial['name']); ?>" class="w-full h-full object-cover">
                        </div>
                        <div class="flex-1 pt-1">
                            <i class="fas fa-quote-left text-[#10E36B] text-3xl mb-3 block opacity-80"></i>
                            <p class="text-gray-600 text-[15px] leading-relaxed mb-2 font-medium">
                                <?php echo htmlspecialchars($testimonial['content']); ?>
                            </p>
                            <h4 class="font-bold text-gray-900 text-base border-t border-gray-100 pt-2 mt-2 inline-block">
                                <?php echo htmlspecialchars($testimonial['name']); ?>
                            </h4>
                        </div>
                    </div>

                    <div class="relative w-full aspect-video rounded-xl overflow-hidden mt-auto group cursor-pointer shadow-md video-trigger" data-video-id="<?php echo htmlspecialchars($testimonial['youtube_id']); ?>">
                        <img src="<?php echo htmlspecialchars($testimonial['video_thumb']); ?>" alt="Video de <?php echo htmlspecialchars($testimonial['name']); ?>" class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-700">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent opacity-90"></div>
                        <div class="absolute inset-0 flex flex-col justify-between p-5 z-10 pointer-events-none">
                            <div class="flex justify-between items-start">
                                <span class="text-white/90 text-xs font-bold uppercase tracking-wider bg-[#10E36B]/90 px-2 py-1 rounded">CT Price</span>
                            </div>

                            <div class="flex items-center gap-5">
                                <div class="w-12 h-12 rounded-full border-2 border-white/80 flex items-center justify-center backdrop-blur-sm group-hover:bg-white group-hover:text-primary transition-all duration-300 shadow-lg group-hover:scale-110 pointer-events-auto">
                                    <i class="fas fa-play text-white ml-1 text-lg group-hover:text-primary transition-colors"></i>
                                </div>

                                <div class="text-white pointer-events-auto">
                                    <h5 class="font-bold text-lg leading-tight text-shadow-sm"><?php echo htmlspecialchars($testimonial['name']); ?></h5>
                                    <p class="text-[10px] text-gray-200 leading-tight max-w-[200px] mt-1 font-light">
                                        <?php echo htmlspecialchars($testimonial['role']); ?>
                                    </p>
                                    <div class="mt-2">
                                        <span class="font-display font-bold text-xl block leading-none"><?php echo htmlspecialchars($testimonial['company_logo_text']); ?></span>
                                        <?php if (!empty($testimonial['company_subtext'])): ?>
                                            <span class="text-[9px] uppercase tracking-wide opacity-80"><?php echo htmlspecialchars($testimonial['company_subtext']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-center gap-5">
                        <a href="#" class="w-9 h-9 rounded-full bg-[#008751] text-white flex items-center justify-center hover:bg-[#00683e] transition-colors shadow-sm hover:shadow-md transform hover:-translate-y-1 duration-200" aria-label="Website">
                            <i class="fab fa-chrome text-sm"></i>
                        </a>
                        <a href="#" class="w-9 h-9 rounded-full bg-[#008751] text-white flex items-center justify-center hover:bg-[#00683e] transition-colors shadow-sm hover:shadow-md transform hover:-translate-y-1 duration-200" aria-label="Instagram">
                            <i class="fab fa-instagram text-sm"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<div id="video-overlay" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/90 backdrop-blur-sm opacity-0 transition-opacity duration-300">
    <div class="relative w-full max-w-5xl mx-4 aspect-video bg-black rounded-2xl overflow-hidden shadow-2xl transform scale-95 transition-transform duration-300" id="video-container">
        <button id="close-video" class="absolute top-4 right-4 z-20 w-10 h-10 rounded-full bg-black/50 hover:bg-white/20 text-white flex items-center justify-center transition-colors focus:outline-none">
            <i class="fas fa-times text-xl"></i>
        </button>

        <div class="absolute inset-0 flex items-center justify-center z-0 pointer-events-none">
            <div class="animate-spin rounded-full h-12 w-12 border-4 border-primary border-t-transparent"></div>
        </div>

        <div id="youtube-player" class="relative z-10 w-full h-full"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const overlay = document.getElementById('video-overlay');
    const container = document.getElementById('video-container');
    const closeBtn = document.getElementById('close-video');
    const triggers = document.querySelectorAll('.video-trigger');
    const youtubeContainer = document.getElementById('youtube-player');
    let player = null;

    const tag = document.createElement('script');
    tag.src = 'https://www.youtube.com/iframe_api';
    const firstScriptTag = document.getElementsByTagName('script')[0];
    firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

    window.onYouTubeIframeAPIReady = function() {};

    function openModal(videoId) {
        overlay.classList.remove('hidden');
        void overlay.offsetWidth;
        overlay.classList.remove('opacity-0');
        container.classList.remove('scale-95');
        container.classList.add('scale-100');

        if (player) {
            player.loadVideoById(videoId);
        } else {
            player = new YT.Player('youtube-player', {
                height: '100%',
                width: '100%',
                videoId: videoId,
                playerVars: { autoplay: 1, rel: 0, modestbranding: 1 },
                events: { onReady: (event) => event.target.playVideo() }
            });
        }

        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        overlay.classList.add('opacity-0');
        container.classList.remove('scale-100');
        container.classList.add('scale-95');

        setTimeout(() => {
            overlay.classList.add('hidden');
            if (player && player.stopVideo) {
                player.stopVideo();
            }
            youtubeContainer.innerHTML = youtubeContainer.innerHTML;
            document.body.style.overflow = '';
        }, 300);
    }

    triggers.forEach(trigger => {
        trigger.addEventListener('click', function() {
            const videoId = this.getAttribute('data-video-id');
            if (videoId) {
                openModal(videoId);
            }
        });
    });

    closeBtn.addEventListener('click', closeModal);
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !overlay.classList.contains('hidden')) {
            closeModal();
        }
    });
});
</script>

<section class="ct-section bg-primary-dark relative overflow-hidden">
    <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-10"></div>
    <div class="ct-container relative z-10 text-center">
        <h2 class="text-3xl md:text-4xl font-display font-bold text-white mb-6">
            Vamos falar sobre o seu próximo passo?
        </h2>
        <p class="text-blue-100 text-lg mb-8 max-w-2xl mx-auto">
            Entre em contato e veja como a CT Price pode apoiar sua empresa com segurança e estratégia.
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

