<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\News;
use App\Models\Partner;
use PDOException;

class HomeController extends Controller
{
    public function index()
    {
        $data = [];
        
        try {
            // Services
            $serviceModel = new Service();
            $data['services'] = $serviceModel->getAllActive();
            
            // Testimonials
            $testimonialModel = new Testimonial();
            $data['testimonials'] = $testimonialModel->getAllActive();
            
            // News
            $newsModel = new News();
            $data['news'] = $newsModel->getLatest(3);
            
            // Partners
            $partnerModel = new Partner();
            $dbPartners = $partnerModel->getAllActive();
            
            // Get partners from directory
            $dirPartners = $this->getPartnersFromDirectory();
            
            // Merge and shuffle
            $data['partners'] = array_merge($dbPartners, $dirPartners);
            
            if (empty($data['partners'])) {
                 $data['partners'] = $this->getFallbackData()['partners'];
            } else {
                shuffle($data['partners']);
            }
            
            // Limit to 5 initial partners
            $data['partners'] = array_slice($data['partners'], 0, 5);
            
            // If DB is empty, use fallback (only if no partners found at all, but we handled that above)
            if (empty($data['services'])) {
                $fallback = $this->getFallbackData();
                $data['services'] = $fallback['services'];
                $data['testimonials'] = $fallback['testimonials'];
                $data['news'] = $fallback['news'];
                // Keep our mixed partners
            }
        } catch (PDOException $e) {
            // DB Error, use fallback but try directory for partners
            $fallback = $this->getFallbackData();
            $data = $fallback;
            
            // Try directory partners even if DB failed
            $dirPartners = $this->getPartnersFromDirectory();
            if (!empty($dirPartners)) {
                $data['partners'] = $dirPartners;
                shuffle($data['partners']);
            }
            
            $data['db_error'] = $e->getMessage();
        } catch (\Exception $e) {
            $fallback = $this->getFallbackData();
            $data = $fallback;
            
            $dirPartners = $this->getPartnersFromDirectory();
            if (!empty($dirPartners)) {
                $data['partners'] = $dirPartners;
                shuffle($data['partners']);
            }
        }

        return $this->view('home.index', $data);
    }

    public function loadMorePartners()
    {
        header('Content-Type: application/json');
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $excluded = $input['excluded'] ?? [];
            
            // Fetch all partners again (DB + Directory)
            $partnerModel = new Partner();
            try {
                $dbPartners = $partnerModel->getAllActive();
            } catch (\Exception $e) {
                $dbPartners = [];
            }
            
            $dirPartners = $this->getPartnersFromDirectory();
            $allPartners = array_merge($dbPartners, $dirPartners);
            
            if (empty($allPartners)) {
                $allPartners = $this->getFallbackData()['partners'];
            }
            
            // Filter out excluded partners
            $availablePartners = array_filter($allPartners, function($partner) use ($excluded) {
                // Use name or logo_url as unique identifier
                $id = $partner['name'] ?? $partner['logo_url'];
                return !in_array($id, $excluded);
            });
            
            // Shuffle and pick 5
            
            // If we have less than 5 available, we need to recycle
            if (count($availablePartners) < 5) {
                // Take all available first
                $nextBatch = $availablePartners;
                $needed = 5 - count($nextBatch);
                
                // Get the ones that were excluded (recycling pool)
                // Filter allPartners to find those in $excluded
                $recycledPool = array_filter($allPartners, function($partner) use ($excluded) {
                     $id = $partner['name'] ?? $partner['logo_url'];
                     return in_array($id, $excluded);
                });
                
                // Shuffle recycled pool
                shuffle($recycledPool);
                
                // Add needed amount
                $recycledBatch = array_slice($recycledPool, 0, $needed);
                $nextBatch = array_merge($nextBatch, $recycledBatch);
                
                // Shuffle final result so new ones aren't always at the end
                shuffle($nextBatch);
                
                // We are recycling, so hasMore is effectively true (infinite loop)
                $hasMore = true;
            } else {
                shuffle($availablePartners);
                $nextBatch = array_slice($availablePartners, 0, 5);
                $hasMore = true; // Always true if we recycle logic above, or check count > 5
            }
            
            echo json_encode([
                'success' => true,
                'partners' => $nextBatch,
                'hasMore' => true // Infinite rotation
            ]);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    private function getPartnersFromDirectory()
    {
        $partners = [];
        $directory = BASE_PATH . '/public_html/assets/imagens/clientes';
        
        // Use ImageLoader service for robust image detection
        $loader = new \App\Services\ImageLoader($directory);
        $files = $loader->getImages();

        foreach ($files as $file) {
            // Determine URL - handle subdirectory installation if needed
            $baseUrl = defined('APP_URL') ? APP_URL : '';
            
            $partners[] = [
                'name' => pathinfo($file, PATHINFO_FILENAME),
                'logo_url' => $baseUrl . '/assets/imagens/clientes/' . $file,
                'website_url' => '#' // Default link
            ];
        }
        return $partners;
    }

    private function getFallbackData()
    {
        return [
            'services' => [
                [
                    'title' => 'Contabilidade de Empresas',
                    'description' => 'Na CT Price, tratamos cada detalhe com máxima seriedade e precisão. Confie sua empresa a nós para uma gestão impecável.',
                    'icon_class' => 'fas fa-building',
                    'link_url' => '#'
                ],
                [
                    'title' => 'Abertura, Alteração e Baixa',
                    'description' => 'Nosso serviço garante uma transição tranquila e eficiente em cada etapa. Facilitamos todos os processos legais e burocráticos.',
                    'icon_class' => 'fas fa-file-signature',
                    'link_url' => '#'
                ],
                [
                    'title' => 'Planejamento Tributário',
                    'description' => 'Projetado para otimizar sua carga fiscal e maximizar a eficiência financeira. Abordagem estratégica e personalizada.',
                    'icon_class' => 'fas fa-chart-pie',
                    'link_url' => '#'
                ],
                [
                    'title' => 'Assessoria ao Produtor Rural',
                    'description' => 'Suporte especializado para gestão financeira e tributária no campo. Foco no crescimento sustentável da produção.',
                    'icon_class' => 'fas fa-tractor',
                    'link_url' => '#'
                ],
                [
                    'title' => 'Consultoria de Negócios',
                    'description' => 'Estratégias personalizadas e soluções inteligentes para impulsionar o crescimento e o sucesso da sua empresa.',
                    'icon_class' => 'fas fa-handshake',
                    'link_url' => '#'
                ],
                [
                    'title' => 'Gerenciamento de Riscos',
                    'description' => 'Identificamos, reduzimos e prevenimos riscos, garantindo uma gestão financeira e tributária segura.',
                    'icon_class' => 'fas fa-shield-alt',
                    'link_url' => '#'
                ]
            ],
            'news' => [
                [
                    'title' => 'Reforma Tributária: O que muda para sua empresa?',
                    'excerpt' => 'Entenda os principais impactos da nova reforma tributária e como se preparar para as mudanças.',
                    'image_url' => 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&q=80&w=600',
                    'published_at' => '2025-05-15 10:00:00'
                ],
                [
                    'title' => 'Gestão Financeira Eficiente',
                    'excerpt' => 'Dicas essenciais para manter o fluxo de caixa da sua empresa saudável em tempos de incerteza.',
                    'image_url' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&q=80&w=600',
                    'published_at' => '2025-05-10 14:30:00'
                ],
                [
                    'title' => 'Tecnologia na Contabilidade',
                    'excerpt' => 'Como a digitalização dos processos contábeis está transformando o mercado e beneficiando os clientes.',
                    'image_url' => 'https://images.unsplash.com/photo-1518186285589-2f7649de83e0?auto=format&fit=crop&q=80&w=600',
                    'published_at' => '2025-05-01 09:15:00'
                ]
            ],
            'testimonials' => [
                [
                    'client_name' => 'João Silva',
                    'client_company' => 'Empresa X',
                    'content' => 'A CT Price transformou a gestão da minha empresa. Profissionalismo e competência ímpares.'
                ],
                [
                    'client_name' => 'Maria Oliveira',
                    'client_company' => 'Comércio Y',
                    'content' => 'Excelente atendimento e suporte. Sinto-me segura com a contabilidade nas mãos deles.'
                ]
            ],
            'partners' => [
                ['name' => 'Parceiro 1', 'logo_url' => 'https://via.placeholder.com/150x80?text=Parceiro+1'],
                ['name' => 'Parceiro 2', 'logo_url' => 'https://via.placeholder.com/150x80?text=Parceiro+2'],
                ['name' => 'Parceiro 3', 'logo_url' => 'https://via.placeholder.com/150x80?text=Parceiro+3'],
                ['name' => 'Parceiro 4', 'logo_url' => 'https://via.placeholder.com/150x80?text=Parceiro+4'],
                ['name' => 'Parceiro 5', 'logo_url' => 'https://via.placeholder.com/150x80?text=Parceiro+5'],
            ]
        ];
    }
}
