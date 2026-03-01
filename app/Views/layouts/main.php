<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CT Price – Organização Contábil</title>
    <meta name="description" content="Cuide da sua empresa, e deixe a contabilidade nas mãos de quem entende. Atuamos nos ramos de contabilidade e planejamento tributário em formato digital.">
    
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
