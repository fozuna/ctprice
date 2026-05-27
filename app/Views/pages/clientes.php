<section class="ct-hero ct-hero--internal relative bg-primary-dark text-white overflow-hidden flex items-center">
    <div class="absolute inset-0 z-0">
        <img src="https://images.unsplash.com/photo-1521790797524-b2497295b8a0?auto=format&fit=crop&w=2070&q=80" alt="Clientes" class="ct-hero-media w-full h-full object-cover mix-blend-overlay">
        <div class="ct-hero-overlay absolute inset-0"></div>
    </div>

    <div class="ct-container ct-hero-inner relative z-10">
        <div class="max-w-2xl animate-fade-in-up">
            <h1 class="ct-hero-title text-3xl md:text-5xl font-display font-bold leading-tight mb-4 md:mb-5">
                Confiança construída<br>
                <span class="ct-hero-accent">com parceria</span>
            </h1>
            <p class="ct-hero-copy text-base md:text-lg leading-relaxed max-w-xl">
                Empresas que escolhem a CT Price pela segurança, agilidade e qualidade nos processos.
            </p>
        </div>
    </div>

    <div class="ct-hero-fade absolute bottom-0 right-0 w-1/3 h-1/3 z-10"></div>
</section>

<section class="ct-section bg-white border-t border-gray-100">
    <div class="ct-container">
        <div class="text-center mb-10">
            <h4 class="text-secondary font-semibold uppercase tracking-wider mb-2">Confiam em nós</h4>
            <h2 class="text-2xl md:text-3xl font-display font-bold text-gray-900">
                Parceiros e Clientes
            </h2>
        </div>

        <div id="partners-container" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-5 w-full">
            <?php if (!empty($partners)): ?>
                <?php foreach ($partners as $partner): ?>
                    <?php
                        $name = htmlspecialchars($partner['name'] ?? '');
                        $logo = htmlspecialchars($partner['logo_url'] ?? '');
                        $link = !empty($partner['website_url']) ? htmlspecialchars($partner['website_url']) : null;
                    ?>
                    <div class="partner-item w-full h-40 flex items-center justify-center transition-all duration-500 opacity-100 transform translate-y-0">
                        <?php if ($link && $link !== '#'): ?>
                            <a href="<?= $link; ?>" target="_blank" rel="noopener noreferrer" class="group w-full h-full flex items-center justify-center bg-white rounded-xl shadow-sm hover:shadow-xl transition-all duration-300 p-6 border border-gray-50 hover:border-gray-100">
                                <?php if (!empty($logo) && strpos($logo, 'placeholder') === false): ?>
                                    <img src="<?= $logo; ?>" alt="<?= $name; ?>" loading="lazy" class="max-w-full max-h-full object-contain grayscale group-hover:grayscale-0 opacity-80 group-hover:opacity-100 transition duration-500 transform group-hover:scale-105">
                                <?php else: ?>
                                    <span class="font-semibold text-lg text-gray-400 group-hover:text-gray-700 transition"><?= $name; ?></span>
                                <?php endif; ?>
                            </a>
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center bg-gray-50 rounded-xl p-6 group hover:shadow-lg transition-all duration-300 border border-transparent hover:border-gray-100">
                                <?php if (!empty($logo) && strpos($logo, 'placeholder') === false): ?>
                                    <img src="<?= $logo; ?>" alt="<?= $name; ?>" loading="lazy" class="max-w-full max-h-full object-contain grayscale group-hover:grayscale-0 opacity-80 group-hover:opacity-100 transition duration-500 transform group-hover:scale-105">
                                <?php else: ?>
                                    <span class="font-semibold text-lg text-gray-400 group-hover:text-gray-700 transition"><?= $name; ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-span-full text-center text-gray-400 font-semibold text-lg">Nenhum parceiro encontrado.</div>
            <?php endif; ?>
        </div>

        <div class="text-center mt-12">
            <button id="load-more-partners" class="px-8 py-3 bg-secondary text-white font-semibold rounded-full shadow-lg hover:bg-opacity-90 transition-all duration-300 transform hover:-translate-y-1 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-secondary">
                Carregar Mais
            </button>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('partners-container');
    const loadMoreBtn = document.getElementById('load-more-partners');
    let excludedPartners = <?= json_encode($initialIds ?? []); ?>;

    loadMoreBtn.addEventListener('click', function() {
        const originalText = loadMoreBtn.innerText;
        const currentHTML = container.innerHTML;

        loadMoreBtn.innerText = 'Carregando...';
        loadMoreBtn.disabled = true;
        loadMoreBtn.classList.add('opacity-75', 'cursor-not-allowed');

        container.innerHTML = `
            <div class="col-span-full flex justify-center items-center py-12">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-secondary"></div>
            </div>
        `;

        fetch('<?= defined('APP_URL') ? APP_URL : '' ?>/partners/load-more', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ excluded: excludedPartners })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.partners.length > 0) {
                container.innerHTML = '';
                data.partners.forEach((partner, index) => {
                    const name = partner.name || '';
                    const logo = partner.logo_url || '';
                    const link = partner.website_url && partner.website_url !== '#' ? partner.website_url : null;
                    const id = name || logo;
                    if (!excludedPartners.includes(id)) {
                        excludedPartners.push(id);
                    }

                    const div = document.createElement('div');
                    div.className = 'partner-item w-full h-40 flex items-center justify-center transition-all duration-500 opacity-0 transform translate-y-4';

                    const imgContent = (logo && !logo.includes('placeholder'))
                        ? `<img src="${logo}" alt="${name}" loading="lazy" class="max-w-full max-h-full object-contain grayscale group-hover:grayscale-0 opacity-80 group-hover:opacity-100 transition duration-500 transform group-hover:scale-105">`
                        : `<span class="font-semibold text-lg text-gray-400 group-hover:text-gray-700 transition">${name}</span>`;

                    let innerContent = '';
                    if (link) {
                        innerContent = `
                            <a href="${link}" target="_blank" rel="noopener noreferrer" class="group w-full h-full flex items-center justify-center bg-white rounded-xl shadow-sm hover:shadow-xl transition-all duration-300 p-6 border border-gray-50 hover:border-gray-100">
                                ${imgContent}
                            </a>
                        `;
                    } else {
                        innerContent = `
                            <div class="w-full h-full flex items-center justify-center bg-gray-50 rounded-xl p-6 group hover:shadow-lg transition-all duration-300 border border-transparent hover:border-gray-100">
                                ${imgContent}
                            </div>
                        `;
                    }

                    div.innerHTML = innerContent;
                    container.appendChild(div);
                    setTimeout(() => {
                        div.classList.remove('opacity-0', 'translate-y-4');
                    }, 50 + (index * 100));
                });
            } else {
                throw new Error('No partners returned');
            }
        })
        .catch(() => {
            container.innerHTML = currentHTML;
            alert('Não foi possível carregar mais parceiros. Tente novamente.');
        })
        .finally(() => {
            loadMoreBtn.innerText = originalText;
            loadMoreBtn.disabled = false;
            loadMoreBtn.classList.remove('opacity-75', 'cursor-not-allowed');
        });
    });
});
</script>

<section class="ct-section ct-cta-surface relative overflow-hidden">
    <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-[0.03]"></div>
    <div class="ct-container relative z-10 text-center">
        <h2 class="ct-cta-title text-3xl md:text-4xl font-display font-bold mb-6">
            Vamos conversar sobre sua empresa?
        </h2>
        <p class="ct-cta-copy text-lg mb-8 max-w-2xl mx-auto">
            Conte com a CT Price para apoiar decisões com segurança, organização e agilidade.
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

