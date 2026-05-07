<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\News;
use App\Models\Partner;
use App\Models\Service;
use App\Models\Testimonial;
use App\Services\ImageLoader;
use PDOException;

class InstitutionalController extends Controller
{
    public function faleConosco()
    {
        $success = isset($_GET['success']) && (string)$_GET['success'] === '1';

        return $this->view('pages.fale_conosco', [
            'metaTitle' => 'Fale Conosco — CT Price',
            'metaDescription' => 'Entre em contato com a CT Price. Tire dúvidas, envie sugestões e fale com um especialista.',
            'success' => $success,
            'errors' => [],
            'old' => [],
        ]);
    }

    public function enviarFaleConosco()
    {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $company = trim((string)($_POST['company'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Informe seu nome.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Informe um e-mail válido.';
        }
        if ($company === '') {
            $errors['company'] = 'Informe sua empresa.';
        }
        if ($message === '') {
            $errors['message'] = 'Escreva sua mensagem.';
        }

        $old = [
            'name' => mb_substr($name, 0, 120),
            'email' => mb_substr($email, 0, 180),
            'company' => mb_substr($company, 0, 180),
            'message' => mb_substr($message, 0, 3000),
        ];

        if (!empty($errors)) {
            return $this->view('pages.fale_conosco', [
                'metaTitle' => 'Fale Conosco — CT Price',
                'metaDescription' => 'Entre em contato com a CT Price. Tire dúvidas, envie sugestões e fale com um especialista.',
                'success' => false,
                'errors' => $errors,
                'old' => $old,
            ]);
        }

        $logDir = BASE_PATH . '/app/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $entry = [
            'ts' => gmdate('Y-m-d\TH:i:s\Z'),
            'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            'ua' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'name' => $old['name'],
            'email' => $old['email'],
            'company' => $old['company'],
            'message' => $old['message'],
        ];
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($line)) {
            @file_put_contents($logDir . '/contact_messages.jsonl', $line . PHP_EOL, FILE_APPEND);
        }

        $baseUrl = defined('APP_URL') ? APP_URL : '';
        $redirect = ($baseUrl !== '' ? $baseUrl : '') . '/fale-conosco?success=1';
        header('Location: ' . $redirect, true, 303);
        exit;
    }

    public function parceiros()
    {
        $partners = [];
        try {
            $partnerModel = new Partner();
            $dbPartners = $partnerModel->getAllActive();
        } catch (PDOException $e) {
            $dbPartners = [];
        }

        $dirPartners = $this->getPartnersFromDirectory();
        $partners = array_merge($dbPartners, $dirPartners);
        if (!empty($partners)) {
            shuffle($partners);
        }

        return $this->view('pages.parceiros', [
            'metaTitle' => 'Parceiros — CT Price',
            'metaDescription' => 'Parcerias, ferramentas e soluções que apoiam clientes CT Price no dia a dia.',
            'partners' => $partners,
        ]);
    }

    public function sobre()
    {
        $partners = [];
        try {
            $partnerModel = new Partner();
            $dbPartners = $partnerModel->getAllActive();
        } catch (PDOException $e) {
            $dbPartners = [];
        }

        $dirPartners = $this->getPartnersFromDirectory();
        $partners = array_merge($dbPartners, $dirPartners);
        if (!empty($partners)) {
            shuffle($partners);
            $partners = array_slice($partners, 0, 10);
        }

        return $this->view('pages.sobre', [
            'metaTitle' => 'A CT Price — Organização Contábil',
            'metaDescription' => 'Conheça a CT Price: ética, agilidade, segurança nos processos e respeito ao cliente.',
            'partners' => $partners,
        ]);
    }

    public function servicos()
    {
        $services = [];
        try {
            $serviceModel = new Service();
            $services = $serviceModel->getAllActive();
        } catch (PDOException $e) {
            $services = [];
        }

        if (empty($services)) {
            $services = $this->getFallbackServices();
        }

        return $this->view('pages.servicos', [
            'metaTitle' => 'Serviços — CT Price',
            'metaDescription' => 'Soluções completas e inteligentes para a gestão contábil, financeira e tributária do seu negócio.',
            'services' => $services,
        ]);
    }

    public function clientes()
    {
        $partners = [];
        try {
            $partnerModel = new Partner();
            $dbPartners = $partnerModel->getAllActive();
        } catch (PDOException $e) {
            $dbPartners = [];
        }

        $dirPartners = $this->getPartnersFromDirectory();
        $partners = array_merge($dbPartners, $dirPartners);
        if (empty($partners)) {
            $partners = $this->getFallbackPartners();
        }

        shuffle($partners);
        $partners = array_slice($partners, 0, 10);

        $initialIds = [];
        foreach ($partners as $p) {
            $initialIds[] = $p['name'] ?? ($p['logo_url'] ?? '');
        }

        return $this->view('pages.clientes', [
            'metaTitle' => 'Clientes — CT Price',
            'metaDescription' => 'Conheça alguns dos clientes e parceiros que confiam na CT Price.',
            'partners' => $partners,
            'initialIds' => $initialIds,
        ]);
    }

    public function depoimentos()
    {
        $testimonials = [];
        try {
            $testimonialModel = new Testimonial();
            $testimonials = $testimonialModel->getAllActive();
        } catch (PDOException $e) {
            $testimonials = [];
        }

        if (empty($testimonials)) {
            $testimonials = $this->getFallbackTestimonials();
        }

        return $this->view('pages.depoimentos', [
            'metaTitle' => 'Depoimentos — CT Price',
            'metaDescription' => 'Histórias de sucesso construídas com parceria e confiança.',
            'testimonials' => $testimonials,
        ]);
    }

    public function noticias()
    {
        $news = [];
        try {
            $newsModel = new News();
            $news = $newsModel->getAllPublished();
        } catch (PDOException $e) {
            $news = [];
        }

        if (empty($news)) {
            $news = $this->getFallbackNews();
        }

        return $this->view('pages.noticias', [
            'metaTitle' => 'Notícias — CT Price',
            'metaDescription' => 'Acompanhe novidades, conteúdos e atualizações relevantes para sua empresa.',
            'news' => $news,
        ]);
    }

    private function getPartnersFromDirectory(): array
    {
        $partners = [];
        $directory = BASE_PATH . '/public_html/assets/imagens/clientes';

        $loader = new ImageLoader($directory);
        $files = $loader->getImages();

        foreach ($files as $file) {
            $baseUrl = defined('APP_URL') ? APP_URL : '';
            $partners[] = [
                'name' => pathinfo($file, PATHINFO_FILENAME),
                'logo_url' => $baseUrl . '/assets/imagens/clientes/' . $file,
                'website_url' => '#',
            ];
        }

        return $partners;
    }

    private function getFallbackServices(): array
    {
        return [
            [
                'title' => 'Contabilidade de Empresas',
                'description' => 'Gestão impecável que assegura conformidade e oferece insights estratégicos valiosos.',
                'icon_class' => 'fas fa-calculator',
                'link_url' => '#',
            ],
            [
                'title' => 'Abertura e Baixa',
                'description' => 'Transição tranquila e eficiente em cada etapa de abertura, alteração ou baixa.',
                'icon_class' => 'fas fa-door-open',
                'link_url' => '#',
            ],
            [
                'title' => 'Planejamento Tributário',
                'description' => 'Otimização da carga fiscal e maximização da eficiência financeira.',
                'icon_class' => 'fas fa-chart-line',
                'link_url' => '#',
            ],
            [
                'title' => 'Assessoria Rural',
                'description' => 'Suporte especializado para gestão financeira e tributária no campo.',
                'icon_class' => 'fas fa-tractor',
                'link_url' => '#',
            ],
            [
                'title' => 'Consultoria de Negócios',
                'description' => 'Estratégias personalizadas para impulsionar o crescimento.',
                'icon_class' => 'fas fa-briefcase',
                'link_url' => '#',
            ],
            [
                'title' => 'Gerenciamento de Riscos',
                'description' => 'Identificação e prevenção de riscos financeiros e tributários.',
                'icon_class' => 'fas fa-shield-alt',
                'link_url' => '#',
            ],
        ];
    }

    private function getFallbackTestimonials(): array
    {
        return [
            [
                'client_name' => 'Aline Zacarini',
                'client_company' => 'Agro Só Sal',
                'content' => 'Contar com a CT Price é a certeza de estar sempre com a melhor parceira.',
                'image_url' => 'https://ctprice.com.br/wp/wp-content/uploads/2026/02/AlineZacarini-1024x1020.jpeg',
            ],
            [
                'client_name' => 'Bruno Alessio',
                'client_company' => 'Soldamaq',
                'content' => 'CT Price: nossa parceira, trazendo visão estratégica e gerencial para melhorar nossa performance.',
                'image_url' => 'https://ctprice.com.br/wp/wp-content/uploads/2026/02/Bruno-Thumb-1018x1024.png',
            ],
            [
                'client_name' => 'Réus Fornari',
                'client_company' => 'Cotto Figueira',
                'content' => 'Comunicação próxima e o conforto de crescer com segurança ao lado da CT Price.',
                'image_url' => 'https://ctprice.com.br/wp/wp-content/uploads/2026/02/Reus-Thumb-1018x1024.png',
            ],
        ];
    }

    private function getFallbackPartners(): array
    {
        return [
            ['name' => 'Parceiro 1', 'logo_url' => 'https://via.placeholder.com/150x80?text=Parceiro+1', 'website_url' => '#'],
            ['name' => 'Parceiro 2', 'logo_url' => 'https://via.placeholder.com/150x80?text=Parceiro+2', 'website_url' => '#'],
            ['name' => 'Parceiro 3', 'logo_url' => 'https://via.placeholder.com/150x80?text=Parceiro+3', 'website_url' => '#'],
            ['name' => 'Parceiro 4', 'logo_url' => 'https://via.placeholder.com/150x80?text=Parceiro+4', 'website_url' => '#'],
            ['name' => 'Parceiro 5', 'logo_url' => 'https://via.placeholder.com/150x80?text=Parceiro+5', 'website_url' => '#'],
        ];
    }

    private function getFallbackNews(): array
    {
        return [
            [
                'title' => 'Reforma Tributária: o que muda para sua empresa?',
                'excerpt' => 'Entenda os principais impactos e como se preparar para as mudanças com segurança.',
                'image_url' => 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&q=80&w=900',
                'published_at' => '2025-05-15 10:00:00',
                'content' => 'A reforma tributária traz novos cenários de planejamento e adequação. Mapear impactos, revisar rotinas e antecipar decisões é o caminho para preservar margem e competitividade.',
                'slug' => 'reforma-tributaria-o-que-muda',
                'id' => 1,
            ],
            [
                'title' => 'Gestão financeira eficiente em tempos de incerteza',
                'excerpt' => 'Dicas essenciais para manter o fluxo de caixa saudável e previsível.',
                'image_url' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&q=80&w=900',
                'published_at' => '2025-05-10 14:30:00',
                'content' => 'Com indicadores claros e disciplina de processos, é possível reduzir riscos e melhorar a tomada de decisão. Uma gestão eficiente começa pelo controle, previsibilidade e consistência.',
                'slug' => 'gestao-financeira-eficiente',
                'id' => 2,
            ],
            [
                'title' => 'Tecnologia na contabilidade: ganhos reais para sua empresa',
                'excerpt' => 'Como a digitalização transforma processos e aumenta a segurança da informação.',
                'image_url' => 'https://images.unsplash.com/photo-1518186285589-2f7649de83e0?auto=format&fit=crop&q=80&w=900',
                'published_at' => '2025-05-01 09:15:00',
                'content' => 'A automação reduz retrabalho, melhora compliance e cria base sólida para análises. Com dados bem estruturados, decisões deixam de ser reativas e passam a ser estratégicas.',
                'slug' => 'tecnologia-na-contabilidade',
                'id' => 3,
            ],
        ];
    }
}

