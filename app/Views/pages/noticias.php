<section class="ct-hero ct-hero--internal relative bg-primary-dark text-white overflow-hidden flex items-center">
    <div class="absolute inset-0 z-0">
        <img src="https://images.unsplash.com/photo-1454165205744-3b78555e5572?auto=format&fit=crop&w=2070&q=80" alt="Notícias" class="ct-hero-media w-full h-full object-cover mix-blend-overlay">
        <div class="ct-hero-overlay absolute inset-0"></div>
    </div>

    <div class="ct-container ct-hero-inner relative z-10">
        <div class="max-w-2xl animate-fade-in-up">
            <h1 class="ct-hero-title text-3xl md:text-5xl font-display font-bold leading-tight mb-4 md:mb-5">
                Conteúdo e atualizações
            </h1>
            <p class="ct-hero-copy text-base md:text-lg leading-relaxed max-w-xl">
                Insights, novidades e temas relevantes para apoiar decisões com segurança e organização.
            </p>
        </div>
    </div>

    <div class="ct-hero-fade absolute bottom-0 right-0 w-1/3 h-1/3 z-10"></div>
</section>

<section class="ct-section bg-white">
    <div class="ct-container">
        <div class="text-center max-w-3xl mx-auto mb-14">
            <h4 class="text-secondary font-semibold uppercase tracking-wider mb-2">Notícias</h4>
            <h2 class="text-3xl md:text-4xl font-display font-bold text-gray-900 mb-4">
                Artigos e atualizações
            </h2>
            <p class="text-gray-600">
                Conteúdo preparado para integração futura com publicações e categorias.
            </p>
        </div>

        <?php if (empty($news)): ?>
            <div class="text-center text-gray-500 py-16">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-gray-100 text-gray-500 mb-4">
                    <i class="fas fa-newspaper"></i>
                </div>
                <p class="font-semibold">Nenhuma notícia disponível no momento.</p>
                <p class="text-sm text-gray-500 mt-1">Em breve, novas publicações por aqui.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <?php foreach ($news as $post): ?>
                    <?php
                        $title = htmlspecialchars($post['title'] ?? '');
                        $excerpt = htmlspecialchars($post['excerpt'] ?? '');
                        $image = htmlspecialchars($post['image_url'] ?? 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&q=80&w=900');
                        $dateRaw = $post['published_at'] ?? null;
                        $dateText = $dateRaw ? date('d/m/Y', strtotime((string)$dateRaw)) : '';
                        $id = (int)($post['id'] ?? 0);
                        $content = trim((string)($post['content'] ?? ''));
                        $contentSafe = htmlspecialchars($content);
                    ?>
                    <article class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-[0_10px_40px_-10px_rgba(0,0,0,0.08)] hover:shadow-2xl transition-all duration-300 group">
                        <div class="relative">
                            <img src="<?php echo $image; ?>" alt="<?php echo $title; ?>" loading="lazy" class="w-full h-52 object-cover group-hover:scale-105 transition-transform duration-700">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent"></div>
                            <div class="absolute bottom-4 left-4 flex items-center gap-2">
                                <span class="inline-flex items-center px-3 py-1 rounded-full bg-white/10 text-white text-xs font-semibold border border-white/20 backdrop-blur-sm">
                                    <i class="fas fa-calendar-alt mr-2"></i><?php echo htmlspecialchars($dateText); ?>
                                </span>
                            </div>
                        </div>

                        <div class="p-7">
                            <h3 class="text-xl font-bold text-gray-900 mb-3 group-hover:text-secondary transition-colors">
                                <?php echo $title; ?>
                            </h3>
                            <p class="text-gray-600 text-sm leading-relaxed mb-5">
                                <?php echo $excerpt; ?>
                            </p>

                            <details class="group/details">
                                <summary class="list-none inline-flex items-center text-gray-900 font-semibold text-sm hover:text-secondary transition-colors cursor-pointer">
                                    Ler artigo <i class="fas fa-arrow-right ml-2 text-xs text-accent"></i>
                                </summary>
                                <div class="mt-5 pt-5 border-t border-gray-100 text-gray-700 text-sm leading-relaxed" id="post-<?php echo $id; ?>">
                                    <?php echo nl2br($contentSafe); ?>
                                </div>
                            </details>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="ct-section ct-cta-surface relative overflow-hidden">
    <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-[0.03]"></div>
    <div class="ct-container relative z-10 text-center">
        <h2 class="ct-cta-title text-3xl md:text-4xl font-display font-bold mb-6">
            Quer orientação prática para sua empresa?
        </h2>
        <p class="ct-cta-copy text-lg mb-8 max-w-2xl mx-auto">
            Fale com a CT Price e transforme informação em decisão.
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

