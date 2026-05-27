<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
        $metaTitle = isset($metaTitle) && is_string($metaTitle) && $metaTitle !== '' ? $metaTitle : 'CT Price – Organização Contábil';
        $metaDescription = isset($metaDescription) && is_string($metaDescription) && $metaDescription !== ''
            ? $metaDescription
            : 'Cuide da sua empresa, e deixe a contabilidade nas mãos de quem entende. Atuamos nos ramos de contabilidade e planejamento tributário em formato digital.';
    ?>
    <title><?php echo htmlspecialchars($metaTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    
    <!-- Favicon -->
    <link rel="icon" href="public_html/assets/imagens/favicon.png" type="image/png">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS (CDN for development/preview) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                screens: {
                    sm: '480px',
                    md: '768px',
                    lg: '1024px',
                    xl: '1280px',
                    '2xl': '1440px',
                },
                extend: {
                    colors: {
                        // Official CT Price Palette
                        primary: {
                            DEFAULT: '#00222C', // Deep Navy / Black - Institucional Base
                            light: '#003344',   // Lighter shade for hover
                            dark: '#001116',    // Darker shade
                        },
                        secondary: {
                            DEFAULT: '#057038', // Dark Green - Institucional Accent
                            light: '#068542',   // Lighter shade
                            dark: '#045B2D',    // Darker shade
                        },
                        accent: {
                            DEFAULT: '#10E36B', // Vibrant Green - Call to Actions / Highlights
                            hover: '#0ED060',   // Slightly darker for hover
                            content: '#00222C', // Text color on top of accent (for contrast)
                        },
                        gray: {
                            50: '#F9FAFB',
                            100: '#F3F4F6',
                            200: '#E5E7EB',
                            300: '#D1D5DB',
                            400: '#9CA3AF',
                            500: '#6B7280',
                            600: '#4B5563',
                            700: '#374151',
                            800: '#1F2937',
                            900: '#111827',
                        }
                    },
                    fontFamily: {
                        sans: ['"Montserrat"', 'sans-serif'],
                        display: ['"Montserrat"', 'sans-serif'],
                    },
                    boxShadow: {
                        'soft': '0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03)',
                        'card': '0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025)',
                        'glow': '0 0 15px rgba(16, 227, 107, 0.2)', // Green glow
                    }
                }
            }
        }
    </script>
    
    <style>
        /* Custom Styles that Tailwind might miss or for specific overrides */
        body {
            font-family: 'Montserrat', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Montserrat', sans-serif;
        }
        button,
        input,
        textarea,
        select {
            font: inherit;
        }
        .hero-gradient {
            background: linear-gradient(135deg, #001116 0%, #00222C 100%);
        }
        .text-gradient {
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-image: linear-gradient(to right, #057038, #10E36B);
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.8s ease-out forwards;
        }

        body {
            font-size: 16px;
            line-height: 24px;
        }
        @media (min-width: 768px) {
            body {
                font-size: 18px;
                line-height: 28px;
            }
        }

        :root {
            --ct-space-1: 4px;
            --ct-space-2: 8px;
            --ct-space-3: 12px;
            --ct-space-4: 16px;
            --ct-space-6: 24px;
            --ct-space-8: 32px;
            --ct-space-10: 40px;
            --ct-space-12: 48px;
            --ct-space-16: 64px;
            --ct-motion-fast: 200ms;
            --ct-motion-base: 250ms;
        }

        a,
        button,
        [role="button"] {
            transition-property: color, background-color, border-color, text-decoration-color, fill, stroke, opacity, box-shadow, transform, filter;
            transition-duration: var(--ct-motion-fast);
            transition-timing-function: ease;
        }

        input,
        textarea,
        select {
            transition-property: border-color, box-shadow, background-color, color;
            transition-duration: var(--ct-motion-fast);
            transition-timing-function: ease;
        }

        a:focus-visible,
        button:focus-visible,
        [role="button"]:focus-visible {
            outline: 2px solid rgba(16, 227, 107, 0.65);
            outline-offset: 3px;
            border-radius: 8px;
        }

        .ct-hit {
            min-width: 44px;
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
                scroll-behavior: auto !important;
            }
        }

        .ct-container {
            width: 100%;
            margin-left: auto;
            margin-right: auto;
            max-width: 1140px;
            padding-left: 16px;
            padding-right: 16px;
        }
        @media (min-width: 768px) {
            .ct-container {
                padding-left: 20px;
                padding-right: 20px;
            }
        }
        @media (min-width: 1024px) {
            .ct-container {
                padding-left: 24px;
                padding-right: 24px;
            }
        }
        @media (max-width: 1024px) {
            .ct-container {
                max-width: 1024px;
            }
        }
        @media (max-width: 767px) {
            .ct-container {
                max-width: 767px;
            }
        }

        .ct-gap-20 {
            gap: 20px;
        }

        .ct-widget {
            margin-block-end: 20px;
        }

        .ct-section {
            padding-top: 32px;
            padding-bottom: 32px;
        }
        @media (min-width: 768px) {
            .ct-section {
                padding-top: 48px;
                padding-bottom: 48px;
            }
        }

        .ct-hero {
            min-height: 640px;
        }

        .ct-hero-inner {
            padding: 50px;
        }
        @media (max-width: 767px) {
            .ct-hero-inner {
                padding: 30px;
            }
        }

        .ct-hero.ct-hero--internal {
            min-height: clamp(280px, 34vw, 340px);
            background: linear-gradient(135deg, #072b2d 0%, #0b3f3a 48%, #106148 100%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }

        .ct-hero--internal .ct-hero-inner {
            padding-top: clamp(80px, 10vw, 112px);
            padding-bottom: clamp(40px, 6vw, 56px);
        }

        .ct-hero--internal .ct-hero-media {
            opacity: 0.22;
            filter: saturate(0.92) brightness(0.78);
        }

        .ct-hero--internal .ct-hero-overlay {
            background:
                radial-gradient(circle at top right, rgba(97, 224, 157, 0.22) 0%, rgba(97, 224, 157, 0) 36%),
                linear-gradient(90deg, rgba(7, 43, 45, 0.94) 0%, rgba(11, 63, 58, 0.88) 52%, rgba(16, 97, 72, 0.72) 100%);
        }

        .ct-hero--internal .ct-hero-fade {
            background: linear-gradient(to top, rgba(4, 24, 27, 0.34), transparent);
        }

        .ct-hero--internal .ct-hero-eyebrow {
            background: rgba(255, 255, 255, 0.1);
            color: #c8f8dc;
            border-color: rgba(255, 255, 255, 0.16);
            backdrop-filter: blur(6px);
        }

        .ct-hero--internal .ct-hero-title {
            color: #ffffff;
            font-size: clamp(1.9rem, 3.8vw, 3.1rem);
            line-height: 1.08;
            letter-spacing: -0.03em;
        }

        .ct-hero--internal .ct-hero-copy {
            color: rgba(255, 255, 255, 0.84);
            font-size: clamp(0.98rem, 1.55vw, 1.1rem);
            line-height: 1.72;
        }

        .ct-hero--internal .ct-hero-accent {
            color: #7ff0b3;
        }

        .ct-hero-home-title {
            font-size: clamp(2.2rem, 5.2vw, 4.5rem);
            line-height: 1.02;
            letter-spacing: -0.04em;
        }

        .ct-hero-home-copy {
            font-size: clamp(1rem, 1.9vw, 1.2rem);
            line-height: 1.72;
        }

        .ct-hero--internal .ct-hero-secondary-link {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.22);
            box-shadow: 0 8px 24px -20px rgba(4, 24, 27, 0.45);
        }

        .ct-hero--internal .ct-hero-secondary-link:hover {
            background: rgba(255, 255, 255, 0.14);
            border-color: rgba(255, 255, 255, 0.28);
        }

        .ct-section-surface {
            background: #fefefe;
        }

        .ct-section-surface .ct-surface-pattern {
            opacity: 0.45;
        }

        .ct-section-surface .ct-surface-pattern path {
            stroke: #d7e7de;
        }

        .ct-section-surface .ct-surface-overlay-y {
            background: linear-gradient(to bottom, rgba(5, 112, 56, 0.04), transparent 18%, transparent 82%, rgba(5, 112, 56, 0.04));
        }

        .ct-section-surface .ct-surface-overlay-x {
            background: linear-gradient(to right, rgba(5, 112, 56, 0.04), transparent 18%, transparent 82%, rgba(5, 112, 56, 0.04));
        }

        .ct-section-surface .ct-surface-glow {
            opacity: 0.06;
        }

        .ct-cta-surface {
            background: linear-gradient(180deg, #fefefe 0%, #f5faf7 100%);
            border-top: 1px solid #e4efe8;
        }

        .ct-cta-surface .ct-cta-title {
            color: #0f2d34;
        }

        .ct-cta-surface .ct-cta-copy {
            color: #556371;
        }

        .ct-cta-surface .ct-cta-email {
            background: #ffffff;
            color: #0f2d34;
            border: 1px solid #dce8df;
        }

        .ct-cta-surface .ct-cta-email:hover {
            background: #f4f8f5;
        }

        .ct-hero-home-media {
            opacity: 0.22;
            filter: saturate(0.92) brightness(0.96);
        }

        .ct-hero-home-overlay {
            background:
                radial-gradient(circle at top right, rgba(187, 247, 208, 0.24) 0%, rgba(187, 247, 208, 0) 34%),
                linear-gradient(90deg, rgba(8, 59, 51, 0.78) 0%, rgba(12, 97, 77, 0.66) 48%, rgba(45, 177, 122, 0.24) 100%);
        }

        .ct-hero-home-fade {
            background: linear-gradient(to top, rgba(8, 59, 51, 0.3), transparent);
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased flex flex-col min-h-screen">

    <?php require BASE_PATH . '/app/Views/partials/header.php'; ?>

    <main class="flex-grow">
        <?php echo $content; ?>
    </main>

    <?php require BASE_PATH . '/app/Views/partials/footer.php'; ?>

    <script>
        // Simple Mobile Menu Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const menuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');
            
            if (menuButton && mobileMenu) {
                menuButton.addEventListener('click', () => {
                    mobileMenu.classList.toggle('hidden');
                });
            }
        });
    </script>
</body>
</html>
