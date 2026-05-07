<?php
require_once 'config/config.php';

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Configurar conexão com banco
$database = Database::getInstance();
$db = $database->getConnection();

// Instanciar modelos
$goalModel = new Goal($db);

// Obter parâmetros de filtro
$currentMonth = $_GET['month'] ?? date('Y-m');
$frontFilter = $_GET['front_id'] ?? '';
$clientFilter = $_GET['client_id'] ?? '';

// Calcular datas do mês
$monthDate = new DateTime($currentMonth . '-01');
$startDate = $monthDate->format('Y-m-01');
$endDate = $monthDate->format('Y-m-t');

// Buscar metas diárias
// Tipo de meta: por padrão, exportar metas de entrega (delivery) — alinhado à tela de Gestão
$goalType = $_GET['goal_type'] ?? 'delivery';

// Buscar metas diárias
$dailyGoals = $goalModel->getDailyGoals($startDate, $endDate, $frontFilter, $clientFilter, $goalType);

// Verificar se há dados para exportar
if (empty($dailyGoals)) {
    $_SESSION['error'] = 'Não há metas diárias para exportar no período selecionado.';
    header('Location: goals.php?month=' . $currentMonth . '&front_id=' . $frontFilter . '&client_id=' . $clientFilter);
    exit;
}

// Função para traduzir nomes dos dias
function translateDayName($date) {
    $dayNames = [
        'Monday' => 'Segunda-feira',
        'Tuesday' => 'Terça-feira', 
        'Wednesday' => 'Quarta-feira',
        'Thursday' => 'Quinta-feira',
        'Friday' => 'Sexta-feira',
        'Saturday' => 'Sábado',
        'Sunday' => 'Domingo'
    ];
    $englishDay = date('l', strtotime($date));
    return $dayNames[$englishDay];
}

// Função para traduzir nome do mês
function translateMonth($monthDate) {
    $months = [
        'January' => 'Janeiro',
        'February' => 'Fevereiro',
        'March' => 'Março',
        'April' => 'Abril',
        'May' => 'Maio',
        'June' => 'Junho',
        'July' => 'Julho',
        'August' => 'Agosto',
        'September' => 'Setembro',
        'October' => 'Outubro',
        'November' => 'Novembro',
        'December' => 'Dezembro'
    ];
    
    $englishMonth = $monthDate->format('F');
    $year = $monthDate->format('Y');
    
    return $months[$englishMonth] . '/' . $year;
}

// Configurar headers para download amigável ao Excel (Windows-1252) e separador explícito
$filename = 'metas_diarias_' . $monthDate->format('Y_m') . '.csv';
header('Content-Type: application/vnd.ms-excel; charset=Windows-1252');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');

// Abrir output stream
$output = fopen('php://output', 'w');

// Linha de separador para Excel reconhecer ';' como delimitador
fwrite($output, "sep=;\r\n");

// Função para converter texto para Windows-1252 com fallback seguro
$toWin1252 = function($value) {
    if ($value === null) return '';
    if (is_numeric($value)) return $value;
    $str = (string)$value;
    $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $str);
    return $converted !== false ? $converted : $str;
};

// Cabeçalho do arquivo
$header = [
    'Data',
    'Dia da Semana',
    'Semana',
    'Início da Semana',
    'Frente',
    'Código da Frente',
    'Cliente',
    'Código do Cliente',
    'Meta Diária',
    'Meta Semanal',
    'Meta Mensal'
];
fputcsv($output, array_map($toWin1252, $header), ';');

// Adicionar linha de informações do período
$periodInfo = [
    'PERÍODO: ' . translateMonth($monthDate),
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    ''
];
fputcsv($output, array_map($toWin1252, $periodInfo), ';');

// Adicionar linha vazia para separação
fputcsv($output, ['', '', '', '', '', '', '', '', '', '', ''], ';');

// Adicionar dados das metas diárias
foreach ($dailyGoals as $goal) {
    $row = [
        date('d/m/Y', strtotime($goal['goal_date'])),
        translateDayName($goal['goal_date']),
        'Semana ' . $goal['week_number'],
        date('d/m/Y', strtotime($goal['week_start_date'])),
        $goal['front_name'] ?? '',
        $goal['front_code'] ?? '',
        $goal['client_name'] ?? '',
        $goal['client_code'] ?? '',
        number_format((float)$goal['daily_goal'], 2, ',', '.'),
        number_format((float)$goal['weekly_goal'], 2, ',', '.'),
        number_format((float)$goal['monthly_goal'], 2, ',', '.')
    ];
    // Converter strings para Windows-1252
    $rowConv = [];
    foreach ($row as $v) {
        $rowConv[] = is_string($v) ? $toWin1252($v) : $v;
    }
    fputcsv($output, $rowConv, ';');
}

// Adicionar linha vazia para separação
fputcsv($output, ['', '', '', '', '', '', '', '', '', '', ''], ';');

// Calcular e adicionar totais
$totalDaily = array_sum(array_column($dailyGoals, 'daily_goal'));
$totalWeekly = array_sum(array_unique(array_column($dailyGoals, 'weekly_goal')));
$totalMonthly = array_sum(array_unique(array_column($dailyGoals, 'monthly_goal')));

$totalsRow = [
    'TOTAIS',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    number_format($totalDaily, 2, ',', '.'),
    number_format($totalWeekly, 2, ',', '.'),
    number_format($totalMonthly, 2, ',', '.')
];
fputcsv($output, array_map($toWin1252, $totalsRow), ';');

// Adicionar informações adicionais
fputcsv($output, ['', '', '', '', '', '', '', '', '', '', ''], ';');
fputcsv($output, array_map($toWin1252, ['INFORMAÇÕES ADICIONAIS:', '', '', '', '', '', '', '', '', '', '']), ';');
fputcsv($output, array_map($toWin1252, ['Total de registros:', count($dailyGoals), '', '', '', '', '', '', '', '', '']), ';');
fputcsv($output, array_map($toWin1252, ['Data de exportação:', date('d/m/Y H:i:s'), '', '', '', '', '', '', '', '', '']), ';');
fputcsv($output, array_map($toWin1252, ['Usuário:', $_SESSION['user_name'] ?? 'Sistema', '', '', '', '', '', '', '', '', '']), ';');

// Fechar output stream
fclose($output);
exit;
?>
