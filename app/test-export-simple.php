<?php
require_once 'config/config.php';

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Configurar headers para download do Excel
$filename = 'teste_exportacao_utf8.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');

// Abrir output stream
$output = fopen('php://output', 'w');

// Adicionar BOM para UTF-8 (garante que o Excel reconheça caracteres especiais)
fwrite($output, "\xEF\xBB\xBF");

// Cabeçalho do arquivo
$header = [
    'Data',
    'Dia da Semana',
    'Frente',
    'Cliente',
    'Meta Diária',
    'Observações'
];

fputcsv($output, $header, ';');

// Dados de teste com caracteres especiais
$testData = [
    ['01/01/2025', 'Segunda-feira', 'FAZENDA APARECIDINHA', 'COSMO', '1.500,00', 'Teste com acentuação'],
    ['02/01/2025', 'Terça-feira', 'FAZENDA CAMPO BOM', 'CERRADINHO BIO', '2.250,50', 'Teste com ç e ã'],
    ['03/01/2025', 'Quarta-feira', 'FAZENDA GUANANDY', 'NEOMILLS MARACAJU', '3.750,75', 'Teste com ú e í'],
    ['04/01/2025', 'Quinta-feira', 'FAZENDA NOVA ÁRVORE GRANDE', 'IMPASA SIDROLÂNDIA', '4.100,25', 'Teste com Á e Â'],
    ['05/01/2025', 'Sexta-feira', 'FAZENDA SÃO JOSÉ', 'RIO PARDO', '2.800,00', 'Teste com Ã e Ó']
];

foreach ($testData as $row) {
    fputcsv($output, $row, ';');
}

// Adicionar linha de totais
fputcsv($output, ['', '', '', 'TOTAL:', '14.400,50', ''], ';');

// Adicionar informações adicionais
fputcsv($output, ['', '', '', '', '', ''], ';');
fputcsv($output, ['TESTE DE CARACTERES ESPECIAIS:', '', '', '', '', ''], ';');
fputcsv($output, ['Acentos: á, à, â, ã, é, è, ê, í, ì, î, ó, ò, ô, õ, ú, ù, û', '', '', '', '', ''], ';');
fputcsv($output, ['Cedilha: ç, Ç', '', '', '', '', ''], ';');
fputcsv($output, ['Data de exportação:', date('d/m/Y H:i:s'), '', '', '', ''], ';');

// Fechar output stream
fclose($output);
exit;
?>