<?php
$baseUrl = defined('APP_URL') ? APP_URL : '';
$actionUrl = ($baseUrl !== '' ? $baseUrl : '') . '/fale-conosco/enviar';
$success = !empty($success);
$errors = is_array($errors ?? null) ? $errors : [];
$old = is_array($old ?? null) ? $old : [];

$oldName = htmlspecialchars((string)($old['name'] ?? ''), ENT_QUOTES, 'UTF-8');
$oldEmail = htmlspecialchars((string)($old['email'] ?? ''), ENT_QUOTES, 'UTF-8');
$oldCompany = htmlspecialchars((string)($old['company'] ?? ''), ENT_QUOTES, 'UTF-8');
$oldMessage = htmlspecialchars((string)($old['message'] ?? ''), ENT_QUOTES, 'UTF-8');
?>

<section class="ct-hero ct-hero--internal relative bg-primary-dark text-white overflow-hidden flex items-center">
    <div class="absolute inset-0 z-0">
        <img src="https://images.unsplash.com/photo-1557804506-669a67965ba0?auto=format&fit=crop&w=2070&q=80" alt="Contato" class="ct-hero-media w-full h-full object-cover mix-blend-overlay">
        <div class="ct-hero-overlay absolute inset-0"></div>
    </div>

    <div class="ct-container ct-hero-inner relative z-10">
        <div class="max-w-2xl animate-fade-in-up">
            <h1 class="ct-hero-title text-3xl md:text-5xl font-display font-bold leading-tight mb-4 md:mb-5">Tire suas dúvidas ou envie sugestões</h1>
            <p class="ct-hero-copy text-base md:text-lg leading-relaxed max-w-xl">
                Atendimento ágil e próximo para apoiar sua empresa com segurança, organização e clareza.
            </p>
        </div>
    </div>

    <div class="ct-hero-fade absolute bottom-0 right-0 w-1/3 h-1/3 z-10"></div>
</section>

<section class="w-full bg-white border-t border-gray-100">
    <div class="mx-auto w-full" style="max-width:1024px;">
        <div class="relative grid grid-cols-1 md:grid-cols-[330px_1fr]" style="min-height:440px;">
            <div class="relative">
                <img
                    src="https://images.unsplash.com/photo-1521791136064-7986c2920216?auto=format&fit=crop&w=900&q=80"
                    alt="Aperto de mãos"
                    width="900"
                    height="900"
                    loading="lazy"
                    class="w-full h-full object-cover"
                >

                <a
                    href="https://wa.me/5567992616117"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="Falar no WhatsApp"
                    class="absolute left-4 bottom-4 w-[52px] h-[52px] rounded-full bg-[#25D366] text-white flex items-center justify-center shadow-lg"
                >
                    <i class="fab fa-whatsapp text-2xl"></i>
                </a>
            </div>

            <div id="ct-contact-form" class="relative" style="background:#062B31;">
                <div class="absolute inset-0 pointer-events-none">
                    <div class="absolute" style="left:70px;top:86px;width:98px;height:78px;border:2px solid rgba(30,208,122,1);"></div>

                    <svg
                        aria-hidden="true"
                        class="absolute"
                        style="left:-34px;top:84px;width:270px;height:300px;"
                        viewBox="0 0 270 300"
                        fill="none"
                    >
                        <path
                            d="M250 20H120L70 70V230L120 280H250"
                            stroke="rgba(30,208,122,1)"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </div>

                <div class="relative h-full" style="padding:38px 0 24px;">
                    <div class="mx-auto" style="max-width:360px;">
                        <div class="text-center text-white" style="margin-bottom:28px;">
                            <p class="text-[16px] leading-[20px]">
                                Quer tirar dúvidas ou conversar sobre como a <strong class="font-semibold">CT Price</strong> pode ajudar sua empresa a crescer?
                            </p>
                            <p class="text-[14px] leading-[18px]" style="margin-top:12px;">
                                Entre em contato com a gente!
                            </p>
                        </div>

                        <?php if ($success): ?>
                            <div class="rounded-md text-white text-[13px] leading-[18px]" style="background:rgba(46,204,113,0.18);border:1px solid rgba(46,204,113,0.35);padding:10px 12px;margin-bottom:16px;">
                                Mensagem enviada com sucesso.
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($errors)): ?>
                            <div class="rounded-md text-white text-[13px] leading-[18px]" style="background:rgba(231,76,60,0.18);border:1px solid rgba(231,76,60,0.35);padding:10px 12px;margin-bottom:16px;" role="alert" aria-live="polite">
                                Revise os campos destacados.
                            </div>
                        <?php endif; ?>

                        <form method="post" action="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8'); ?>" novalidate>
                            <div style="margin-bottom:14px;">
                                <label for="contact-name" class="block text-[12px] font-semibold" style="color:#28C76F;margin-bottom:8px;">Nome</label>
                                <input
                                    id="contact-name"
                                    name="name"
                                    type="text"
                                    autocomplete="name"
                                    required
                                    value="<?= $oldName; ?>"
                                    aria-invalid="<?= !empty($errors['name']) ? 'true' : 'false'; ?>"
                                    class="w-full"
                                    style="height:40px;background:#FFFFFF;border:1px solid <?= !empty($errors['name']) ? '#E57373' : '#E0E0E0'; ?>;border-radius:4px;padding:10px 12px;color:#212121;"
                                >
                                <?php if (!empty($errors['name'])): ?>
                                    <p class="text-[12px]" style="color:#FFB4B4;margin-top:6px;"><?= htmlspecialchars((string)$errors['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                            </div>

                            <div style="margin-bottom:14px;">
                                <label for="contact-email" class="block text-[12px] font-semibold" style="color:#28C76F;margin-bottom:8px;">E-mail</label>
                                <input
                                    id="contact-email"
                                    name="email"
                                    type="email"
                                    autocomplete="email"
                                    inputmode="email"
                                    required
                                    value="<?= $oldEmail; ?>"
                                    aria-invalid="<?= !empty($errors['email']) ? 'true' : 'false'; ?>"
                                    class="w-full"
                                    style="height:40px;background:#FFFFFF;border:1px solid <?= !empty($errors['email']) ? '#E57373' : '#E0E0E0'; ?>;border-radius:4px;padding:10px 12px;color:#212121;"
                                >
                                <?php if (!empty($errors['email'])): ?>
                                    <p class="text-[12px]" style="color:#FFB4B4;margin-top:6px;"><?= htmlspecialchars((string)$errors['email'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                            </div>

                            <div style="margin-bottom:14px;">
                                <label for="contact-company" class="block text-[12px] font-semibold" style="color:#28C76F;margin-bottom:8px;">Empresa</label>
                                <input
                                    id="contact-company"
                                    name="company"
                                    type="text"
                                    autocomplete="organization"
                                    required
                                    value="<?= $oldCompany; ?>"
                                    aria-invalid="<?= !empty($errors['company']) ? 'true' : 'false'; ?>"
                                    class="w-full"
                                    style="height:40px;background:#FFFFFF;border:1px solid <?= !empty($errors['company']) ? '#E57373' : '#E0E0E0'; ?>;border-radius:4px;padding:10px 12px;color:#212121;"
                                >
                                <?php if (!empty($errors['company'])): ?>
                                    <p class="text-[12px]" style="color:#FFB4B4;margin-top:6px;"><?= htmlspecialchars((string)$errors['company'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                            </div>

                            <div style="margin-bottom:18px;">
                                <label for="contact-message" class="block text-[12px] font-semibold" style="color:#28C76F;margin-bottom:8px;">Mensagem</label>
                                <textarea
                                    id="contact-message"
                                    name="message"
                                    required
                                    aria-invalid="<?= !empty($errors['message']) ? 'true' : 'false'; ?>"
                                    placeholder="Informe como podemos te ajudar"
                                    class="w-full"
                                    style="height:110px;resize:none;background:#FFFFFF;border:1px solid <?= !empty($errors['message']) ? '#E57373' : '#E0E0E0'; ?>;border-radius:4px;padding:10px 12px;color:#212121;"
                                ><?= $oldMessage; ?></textarea>
                                <?php if (!empty($errors['message'])): ?>
                                    <p class="text-[12px]" style="color:#FFB4B4;margin-top:6px;"><?= htmlspecialchars((string)$errors['message'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                            </div>

                            <button
                                type="submit"
                                class="block"
                                style="width:110px;height:40px;border-radius:5px;background:#2ECC71;color:#FFFFFF;font-weight:600;font-size:14px;margin:0 auto;"
                            >
                                Enviar
                            </button>
                        </form>
                    </div>
                </div>

                <button
                    type="button"
                    aria-label="Acessibilidade"
                    class="absolute right-4 bottom-4 w-[52px] h-[52px] rounded-full text-white flex items-center justify-center shadow-lg"
                    style="background:#2962FF;"
                >
                    <i class="fas fa-universal-access text-xl"></i>
                </button>
            </div>
        </div>
    </div>

    <style>
        #ct-contact-form input::placeholder,
        #ct-contact-form textarea::placeholder { color: #9AA0A6; opacity: 1; }
        #ct-contact-form input:focus,
        #ct-contact-form textarea:focus { outline: none; border-color: #28C76F !important; box-shadow: 0 0 0 2px rgba(40, 199, 111, 0.25); }
        #ct-contact-form button[type="submit"]:hover { filter: brightness(0.95); }
        #ct-contact-form button[type="submit"]:active { transform: translateY(1px); }
    </style>

    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "LocalBusiness",
      "name": "CT Price",
      "url": "<?= htmlspecialchars((($baseUrl !== '' ? $baseUrl : '') . '/fale-conosco'), ENT_QUOTES, 'UTF-8'); ?>",
      "telephone": ["+55 67 3313-7300", "+55 67 99261-6117"],
      "email": "contato@ctpricems.com.br",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "R. José Antônio, 2.777",
        "addressLocality": "Campo Grande",
        "addressRegion": "MS",
        "addressCountry": "BR"
      }
    }
    </script>
</section>

<section class="ct-section bg-white">
    <div class="ct-container">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <a href="tel:+556733137300" class="group flex items-start gap-4 p-5 bg-gray-50 border border-gray-100 rounded-xl shadow-sm hover:shadow-xl transition-all duration-300">
                <div class="w-11 h-11 rounded-xl bg-secondary/10 text-secondary flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-phone"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900 group-hover:text-secondary transition-colors">Telefone</p>
                    <p class="text-sm text-gray-600">(67) 3313-7300</p>
                </div>
            </a>

            <a href="mailto:contato@ctpricems.com.br" class="group flex items-start gap-4 p-5 bg-gray-50 border border-gray-100 rounded-xl shadow-sm hover:shadow-xl transition-all duration-300">
                <div class="w-11 h-11 rounded-xl bg-accent/10 text-accent flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-envelope"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900 group-hover:text-secondary transition-colors">E-mail</p>
                    <p class="text-sm text-gray-600">contato@ctpricems.com.br</p>
                </div>
            </a>

            <a href="https://wa.me/5567992616117" target="_blank" rel="noopener noreferrer" class="group flex items-start gap-4 p-5 bg-gray-50 border border-gray-100 rounded-xl shadow-sm hover:shadow-xl transition-all duration-300">
                <div class="w-11 h-11 rounded-xl bg-green-100 text-green-700 flex items-center justify-center flex-shrink-0">
                    <i class="fab fa-whatsapp"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900 group-hover:text-secondary transition-colors">WhatsApp</p>
                    <p class="text-sm text-gray-600">(67) 99261-6117</p>
                </div>
            </a>
        </div>
    </div>
</section>

<section class="w-full" style="background:#03232B;border-bottom:3px solid #1FC667;">
    <div class="ct-container" style="padding-top:34px;padding-bottom:34px;">
        <div class="grid" style="grid-template-columns:repeat(5,minmax(0,1fr));gap:0;">
            <div class="text-center" style="padding:0 24px;">
                <p class="font-semibold" style="color:#1FC667;font-size:16px;line-height:20px;">Comercial</p>
                <a href="tel:+5567992324097" class="block" style="color:#FFFFFF;font-size:16px;line-height:20px;margin-top:8px;">(67) 99232-4097</a>
            </div>
            <div class="text-center" style="padding:0 24px;">
                <p class="font-semibold" style="color:#1FC667;font-size:16px;line-height:20px;">Pessoal</p>
                <a href="tel:+556733137301" class="block" style="color:#FFFFFF;font-size:16px;line-height:20px;margin-top:8px;">(67) 3313-7301</a>
            </div>
            <div class="text-center" style="padding:0 24px;">
                <p class="font-semibold" style="color:#1FC667;font-size:16px;line-height:20px;">Fiscal</p>
                <a href="tel:+556733137302" class="block" style="color:#FFFFFF;font-size:16px;line-height:20px;margin-top:8px;">(67) 3313-7302</a>
            </div>
            <div class="text-center" style="padding:0 24px;">
                <p class="font-semibold" style="color:#1FC667;font-size:16px;line-height:20px;">Contábil</p>
                <a href="tel:+556733137304" class="block" style="color:#FFFFFF;font-size:16px;line-height:20px;margin-top:8px;">(67) 3313-7304</a>
            </div>
            <div class="text-center" style="padding:0 24px;">
                <p class="font-semibold" style="color:#1FC667;font-size:16px;line-height:20px;">Central/Empresarial</p>
                <a href="tel:+556733137300" class="block" style="color:#FFFFFF;font-size:16px;line-height:20px;margin-top:8px;">(67) 3313-7300</a>
            </div>
        </div>
    </div>

    <style>
        @media (max-width: 900px) {
            section[style*="background:#03232B"] > .ct-container > div { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; row-gap: 18px !important; }
        }
        @media (max-width: 520px) {
            section[style*="background:#03232B"] > .ct-container > div { grid-template-columns: 1fr !important; }
        }
        section[style*="background:#03232B"] a:hover { text-decoration: underline; text-underline-offset: 4px; }
        section[style*="background:#03232B"] a:focus { outline: 2px solid rgba(31,198,103,0.65); outline-offset: 4px; border-radius: 6px; }
    </style>
</section>

