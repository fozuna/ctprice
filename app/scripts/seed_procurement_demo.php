<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

$db = Database::getInstance()->getConnection();

try {
    $db->exec("
        INSERT INTO suppliers (razao_social, cnpj, email, telefone, nome_contato, status, portal_password_hash)
        VALUES ('Fornecedor Demo', '00.000.000/0001-00', 'fornecedor.demo@example.com', '(11) 0000-0000', 'Contato Demo', 1, '" . password_hash('demo123', PASSWORD_DEFAULT) . "')
        ON DUPLICATE KEY UPDATE razao_social = VALUES(razao_social), telefone = VALUES(telefone), nome_contato = VALUES(nome_contato), status = VALUES(status);
    ");

    $sql = "INSERT INTO products (nome, descricao, unidade_medida, categoria, status)
            VALUES (:nome, :descricao, :um, :categoria, 1)";
    $stmt = $db->prepare($sql);
    $check = $db->prepare("SELECT id FROM products WHERE nome = :nome AND unidade_medida = :um LIMIT 1");

    $data = [
        ['Brita 1', 'Brita tipo 1', 'ton', 'Agregado'],
        ['Areia Média', 'Areia média lavada', 'm3', 'Agregado']
    ];
    foreach ($data as $d) {
        $check->execute([':nome' => $d[0], ':um' => $d[2]]);
        if (!$check->fetchColumn()) {
            $stmt->execute([
                ':nome' => $d[0],
                ':descricao' => $d[1],
                ':um' => $d[2],
                ':categoria' => $d[3]
            ]);
        }
    }

    echo "Seed de fornecedores e produtos aplicado com sucesso.\n";
} catch (Throwable $e) {
    echo "Erro ao aplicar seed: " . $e->getMessage() . "\n";
    exit(1);
}
