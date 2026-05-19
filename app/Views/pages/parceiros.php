<?php $baseUrl = defined('APP_URL') ? APP_URL : ''; ?>

<section class="ct-hero ct-hero--internal relative bg-primary-dark text-white overflow-hidden flex items-center">
    <div class="absolute inset-0 z-0">
        <img src="https://images.unsplash.com/photo-1556761175-5973dc0f32e7?auto=format&fit=crop&w=2070&q=80" alt="Parcerias" class="ct-hero-media w-full h-full object-cover mix-blend-overlay">
        <div class="ct-hero-overlay absolute inset-0"></div>
    </div>

    <div class="ct-container ct-hero-inner relative z-10">
        <div class="max-w-2xl animate-fade-in-up">
            <span class="inline-flex items-center py-1 px-3 rounded-full bg-white/10 text-accent text-xs md:text-sm font-semibold mb-4 border border-white/15 backdrop-blur-sm">
                Parceiros
            </span>
            <h1 class="text-3xl md:text-5xl font-display font-bold leading-tight mb-4 md:mb-5">
                Parcerias de sucesso
            </h1>
            <p class="text-base md:text-lg text-white/88 mb-6 leading-relaxed max-w-xl">
                São ainda maiores quando compartilhadas com quem caminha ao nosso lado.
            </p>
            <div class="flex flex-col sm:flex-row gap-4">
                <a href="https://wa.me/5567992616117" target="_blank" rel="noopener noreferrer" class="px-6 py-3 bg-accent text-primary font-bold text-sm md:text-base rounded hover:bg-accent-hover transition-all shadow-lg hover:shadow-accent/30 transform hover:-translate-y-1 text-center">
                    Fale Conosco
                </a>
                <a href="<?= htmlspecialchars(($baseUrl !== '' ? $baseUrl : '') . '/servicos'); ?>" class="px-6 py-3 bg-transparent border border-white/30 text-white font-semibold text-sm md:text-base rounded hover:bg-white/10 transition-all backdrop-blur-sm text-center">
                    Conheça os Serviços
                </a>
            </div>
        </div>
    </div>

    <div class="ct-hero-fade absolute bottom-0 right-0 w-1/3 h-1/3 z-10"></div>
</section>

<section class="ct-section bg-white border-t border-gray-100">
    <div class="ct-container">
        <div class="text-center mb-10">
            <h4 class="text-secondary font-semibold uppercase tracking-wider mb-2">Ferramentas</h4>
            <h2 class="text-2xl md:text-3xl font-display font-bold text-gray-900">Ferramentas Exclusivas dos Clientes CT Price</h2>
            <p class="text-gray-600 mt-3 max-w-2xl mx-auto">
                Seleção de plataformas e serviços que apoiam rotinas contábeis, gestão e produtividade.
            </p>
        </div>

        <div class="grid grid-cols-3 gap-5 w-full">
            <?php
                $tools = [
                    ['name' => 'Ponto Web', 'src' => 'https://ctprice.com.br/wp/wp-content/uploads/2025/08/PONTO-WEB.png'],
                    ['name' => 'Web E-mail', 'src' => 'https://ctprice.com.br/wp/wp-content/uploads/2025/08/WEB-EMAIL.png'],
                    ['name' => 'Folha Web', 'src' => 'https://ctprice.com.br/wp/wp-content/uploads/2025/08/FOLHA-WEB.png'],
                    ['name' => 'Open Finance', 'src' => 'https://ctprice.com.br/wp/wp-content/uploads/2025/08/OPEN-FINANCE.png'],
                    ['name' => 'Contra Cheque', 'src' => 'https://ctprice.com.br/wp/wp-content/uploads/2025/08/CONTRA-CHEQUE.png'],
                    ['name' => 'Conexão VIP', 'src' => 'https://ctprice.com.br/wp/wp-content/uploads/2025/08/CONEXAO-VIP.png'],
                    ['name' => 'Logotipo 9', 'src' => 'https://ctprice.com.br/wp/wp-content/uploads/2026/03/Logotipo-Caldo-de-Cana-Borcelle-Criativo-Verde-Branco-9.png'],
                    ['name' => 'Logotipo 19', 'src' => 'https://ctprice.com.br/wp/wp-content/uploads/2026/04/Logotipo-Caldo-de-Cana-Borcelle-Criativo-Verde-Branco-19.png'],
                    ['name' => 'Logotipo 21', 'src' => 'https://ctprice.com.br/wp/wp-content/uploads/2026/05/Logotipo-Caldo-de-Cana-Borcelle-Criativo-Verde-Branco-21.png'],
                ];

                $toolsToPartners = [
                    ['name' => 'Clicksign', 'logo_url' => 'https://ctprice.com.br/wp/wp-content/uploads/2024/09/clicksign.png', 'website_url' => '#'],
                    ['name' => 'Gov.br', 'logo_url' => 'https://ctprice.com.br/wp/wp-content/uploads/2024/09/gov-br.jpg', 'website_url' => '#'],
                    ['name' => 'E-Social', 'logo_url' => 'https://ctprice.com.br/wp/wp-content/uploads/2024/09/esocial-ok.png', 'website_url' => '#'],
                    ['name' => 'Omie', 'logo_url' => 'https://ctprice.com.br/wp/wp-content/uploads/2024/09/omie_62937cda.png', 'website_url' => '#'],
                    ['name' => 'Nibo', 'logo_url' => 'https://ctprice.com.br/wp/wp-content/uploads/2024/09/logo-nibo_89356101.png', 'website_url' => '#'],
                    ['name' => 'Sieg', 'logo_url' => 'https://ctprice.com.br/wp/wp-content/uploads/2024/09/sieg_040407c7.png', 'website_url' => '#'],
                    ['name' => 'Onvio', 'logo_url' => 'https://ctprice.com.br/wp/wp-content/uploads/2024/09/onvio4.png', 'website_url' => '#'],
                    ['name' => 'Registro.br', 'logo_url' => 'https://ctprice.com.br/wp/wp-content/uploads/2024/09/registro-br2.jpg', 'website_url' => '#'],
                    ['name' => 'Portal e-Fazenda', 'logo_url' => 'https://ctprice.com.br/wp/wp-content/uploads/2024/09/portal-e-fazenda.png', 'website_url' => '#'],
                    ['name' => 'Tech Contratos', 'logo_url' => 'https://ctprice.com.br/wp/wp-content/uploads/2024/09/tech-contratos.png', 'website_url' => '#'],
                ];
            ?>

            <?php foreach ($tools as $t): ?>
                <div class="w-full h-32 md:h-36 flex items-center justify-center">
                    <div class="group w-full h-full flex items-center justify-center bg-white rounded-xl shadow-sm hover:shadow-xl transition-all duration-300 p-5 md:p-6 border border-gray-50 hover:border-gray-100">
                        <img src="<?= htmlspecialchars($t['src']); ?>" alt="<?= htmlspecialchars($t['name']); ?>" loading="lazy" class="max-w-full max-h-24 md:max-h-28 object-contain transition duration-300 transform group-hover:scale-110">
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="ct-section bg-gray-50 border-t border-gray-100" aria-labelledby="partners-grid-title">
    <div class="ct-container">
        <div class="text-center mb-10">
            <h4 class="text-secondary font-semibold uppercase tracking-wider mb-2">Parceiros</h4>
            <h2 id="partners-grid-title" class="text-2xl md:text-3xl font-display font-bold text-gray-900">Parceiros</h2>
            <p class="text-gray-600 mt-3 max-w-2xl mx-auto">
                Relações construídas com confiança, alinhamento e qualidade.
            </p>
        </div>

        <?php
            $allPartners = array_merge($toolsToPartners ?? [], $partners ?? []);
        ?>

        <?php if (!empty($allPartners)): ?>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-5 w-full">
                <?php foreach ($allPartners as $partner): ?>
                    <?php
                        $name = htmlspecialchars($partner['name'] ?? '');
                        $logo = htmlspecialchars($partner['logo_url'] ?? '');
                        $link = !empty($partner['website_url']) ? htmlspecialchars($partner['website_url']) : null;
                    ?>
                    <div class="w-full h-28 flex items-center justify-center">
                        <?php if ($link && $link !== '#'): ?>
                            <a href="<?= $link; ?>" target="_blank" rel="noopener noreferrer" class="group w-full h-full flex items-center justify-center bg-white rounded-xl shadow-sm hover:shadow-xl transition-all duration-300 p-4 border border-gray-50 hover:border-gray-100">
                                <?php if (!empty($logo) && strpos($logo, 'placeholder') === false): ?>
                                    <img src="<?= $logo; ?>" alt="<?= $name; ?>" loading="lazy" class="max-w-full max-h-full object-contain opacity-80 group-hover:opacity-100 transition duration-500 transform group-hover:scale-105">
                                <?php else: ?>
                                    <span class="font-semibold text-sm text-gray-400 group-hover:text-gray-700 transition"><?= $name; ?></span>
                                <?php endif; ?>
                            </a>
                        <?php else: ?>
                            <div class="group w-full h-full flex items-center justify-center bg-white rounded-xl shadow-sm hover:shadow-xl transition-all duration-300 p-4 border border-gray-50 hover:border-gray-100">
                                <?php if (!empty($logo) && strpos($logo, 'placeholder') === false): ?>
                                    <img src="<?= $logo; ?>" alt="<?= $name; ?>" loading="lazy" class="max-w-full max-h-full object-contain opacity-80 group-hover:opacity-100 transition duration-500 transform group-hover:scale-105">
                                <?php else: ?>
                                    <span class="font-semibold text-sm text-gray-400 group-hover:text-gray-700 transition"><?= $name; ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center text-gray-500 py-10">
                <p class="font-semibold">Nenhum parceiro disponível no momento.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="ct-section bg-white border-t border-gray-100">
    <div class="ct-container">
        <div class="grid md:grid-cols-2 gap-5 items-start">
            <div>
                <h4 class="text-secondary font-semibold uppercase tracking-wider mb-2">Localização</h4>
                <h2 class="text-2xl md:text-3xl font-display font-bold text-gray-900 mb-4">Vamos conversar?</h2>
                <p class="text-gray-600 leading-relaxed">
                    Encontre a CT Price em Campo Grande, MS, ou fale com nosso time pelo WhatsApp.
                </p>
            </div>
            <div class="rounded-2xl overflow-hidden shadow-card border border-gray-100">
                <iframe
                    title="Mapa CT Price"
                    src="https://maps.google.com/maps?q=R.%20Jos%C3%A9%20Ant%C3%B4nio%2C%202777%20-%20Vila%20Rosa%20Pires%2C%20Campo%20Grande%20-%20MS%2C%2079002-400&t=m&z=15&output=embed&iwloc=near"
                    width="100%"
                    height="260"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    style="border:0;"
                    allowfullscreen
                ></iframe>
            </div>
        </div>
    </div>
</section>

