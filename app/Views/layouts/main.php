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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
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
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        display: ['"Outfit"', 'sans-serif'],
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
            font-family: 'Plus Jakarta Sans', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Outfit', sans-serif;
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

        .ct-hero--internal {
            min-height: clamp(280px, 34vw, 340px);
        }

        .ct-hero--internal .ct-hero-inner {
            padding-top: clamp(80px, 10vw, 112px);
            padding-bottom: clamp(40px, 6vw, 56px);
        }

        .ct-hero--internal .ct-hero-media {
            opacity: 0.14;
        }

        .ct-hero--internal .ct-hero-overlay {
            background: linear-gradient(90deg, rgba(9, 41, 50, 0.94) 0%, rgba(15, 67, 79, 0.85) 52%, rgba(22, 114, 92, 0.5) 100%);
        }

        .ct-hero--internal .ct-hero-fade {
            background: linear-gradient(to top, rgba(9, 41, 50, 0.36), transparent);
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
