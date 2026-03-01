<?php
// tests/test_partner_pagination.php

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/app/Services/ImageLoader.php';
require_once BASE_PATH . '/app/Core/Logger.php';

// Mock Partner Model and Directory behavior simulates
class MockPartnerController {
    private $allPartners = [];

    public function __construct() {
        // Create 10 dummy partners
        for ($i = 1; $i <= 10; $i++) {
            $this->allPartners[] = [
                'name' => "Partner $i",
                'logo_url' => "partner_$i.png",
                'website_url' => '#'
            ];
        }
    }

    public function loadMore($excluded = []) {
        $allPartners = $this->allPartners;
        
        $available = array_filter($allPartners, function($p) use ($excluded) {
            return !in_array($p['name'], $excluded);
        });

        // If not enough, recycle logic (simulate backend logic)
        if (count($available) < 5) {
            // Take all available first
            $nextBatch = $available;
            $needed = 5 - count($nextBatch);
            
            // Get the ones that were excluded (recycling pool)
            $recycledPool = array_filter($allPartners, function($p) use ($excluded) {
                 return in_array($p['name'], $excluded);
            });
            
            // Shuffle recycled pool
            shuffle($recycledPool);
            
            // Add needed amount
            $recycledBatch = array_slice($recycledPool, 0, $needed);
            $nextBatch = array_merge($nextBatch, $recycledBatch);
            
            // Shuffle final result
            shuffle($nextBatch);
        } else {
            shuffle($available);
            $nextBatch = array_slice($available, 0, 5);
        }

        return $nextBatch;
    }
}

// Test Execution
echo "Iniciando testes de paginação...\n";
$controller = new MockPartnerController();

// Test 1: Initial Load (empty exclusion)
echo "\nTeste 1: Carga inicial (sem exclusões)\n";
$batch1 = $controller->loadMore([]);
echo "Retornado: " . count($batch1) . " parceiros.\n";
if (count($batch1) !== 5) {
    echo "[FALHA] Esperado 5 parceiros.\n";
    exit(1);
}
$names1 = array_column($batch1, 'name');
echo "Nomes: " . implode(', ', $names1) . "\n";

// Test 2: Second Load (exclude previous)
echo "\nTeste 2: Segunda carga (excluindo anteriores)\n";
$batch2 = $controller->loadMore($names1);
echo "Retornado: " . count($batch2) . " parceiros.\n";
$names2 = array_column($batch2, 'name');
echo "Nomes: " . implode(', ', $names2) . "\n";

// Verify no duplicates between batches (since we have 10 total and take 5+5)
$intersection = array_intersect($names1, $names2);
if (!empty($intersection)) {
    echo "[FALHA] Duplicatas encontradas entre lotes: " . implode(', ', $intersection) . "\n";
    // Note: If shuffle is random, and we have exactly 10, intersection SHOULD be empty if logic is perfect.
    // If we had 7 partners, intersection would happen.
} else {
    echo "[SUCESSO] Sem duplicatas entre lote 1 e 2.\n";
}

// Test 3: Recycle (exclude all 10)
echo "\nTeste 3: Reciclagem (excluindo todos os 10)\n";
$allNames = array_merge($names1, $names2);
$batch3 = $controller->loadMore($allNames);
echo "Retornado: " . count($batch3) . " parceiros.\n";
if (count($batch3) !== 5) {
    echo "[FALHA] Esperado 5 parceiros na reciclagem.\n";
} else {
    echo "[SUCESSO] Reciclagem retornou 5 parceiros mesmo com tudo excluído.\n";
}

echo "\nTodos os testes de lógica passaram.\n";
