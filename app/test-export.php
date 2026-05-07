<?php
require_once 'config/config.php';

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

echo "<h1>Teste de Exportação de Metas Diárias</h1>";

// Configurar conexão com banco
$database = Database::getInstance();
$db = $database->getConnection();

// Instanciar modelos
$goalModel = new Goal($db);
$frontModel = new Front($db);
$clientModel = new Client($db);

echo "<h2>Verificando dados existentes...</h2>";

// Buscar metas diárias do mês atual
$currentMonth = date('Y-m');
$monthDate = new DateTime($currentMonth . '-01');
$startDate = $monthDate->format('Y-m-01');
$endDate = $monthDate->format('Y-m-t');

$dailyGoals = $goalModel->getDailyGoals($startDate, $endDate);

echo "<p><strong>Período:</strong> " . $startDate . " a " . $endDate . "</p>";
echo "<p><strong>Total de metas diárias encontradas:</strong> " . count($dailyGoals) . "</p>";

if (!empty($dailyGoals)) {
    echo "<h3>Exemplo de dados que serão exportados:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr>";
    echo "<th>Data</th>";
    echo "<th>Frente</th>";
    echo "<th>Cliente</th>";
    echo "<th>Meta Diária</th>";
    echo "<th>Meta Semanal</th>";
    echo "<th>Meta Mensal</th>";
    echo "</tr>";
    
    // Mostrar apenas os primeiros 5 registros
    $sample = array_slice($dailyGoals, 0, 5);
    foreach ($sample as $goal) {
        echo "<tr>";
        echo "<td>" . date('d/m/Y', strtotime($goal['goal_date'])) . "</td>";
        echo "<td>" . htmlspecialchars($goal['front_name']) . "</td>";
        echo "<td>" . htmlspecialchars($goal['client_name']) . "</td>";
        echo "<td>" . number_format($goal['daily_goal'], 2, ',', '.') . "</td>";
        echo "<td>" . number_format($goal['weekly_goal'], 2, ',', '.') . "</td>";
        echo "<td>" . number_format($goal['monthly_goal'], 2, ',', '.') . "</td>";
        echo "</tr>";
    }
    
    if (count($dailyGoals) > 5) {
        echo "<tr><td colspan='6'>... e mais " . (count($dailyGoals) - 5) . " registros</td></tr>";
    }
    
    echo "</table>";
    
    echo "<h3>Teste de Caracteres Especiais:</h3>";
    echo "<p>Verificando se há caracteres especiais nos dados:</p>";
    
    $specialChars = false;
    foreach ($dailyGoals as $goal) {
        if (preg_match('/[áàâãéèêíìîóòôõúùûç]/i', $goal['front_name'] . $goal['client_name'])) {
            $specialChars = true;
            echo "<p>✓ Encontrados caracteres especiais em: " . htmlspecialchars($goal['front_name']) . " / " . htmlspecialchars($goal['client_name']) . "</p>";
            break;
        }
    }
    
    if (!$specialChars) {
        echo "<p>⚠️ Nenhum caractere especial encontrado nos dados atuais.</p>";
    }
    
    echo "<h3>Links de Teste:</h3>";
    echo "<p><a href='export-daily-goals.php?month=" . $currentMonth . "' target='_blank'>🔗 Testar Exportação (mês atual)</a></p>";
    echo "<p><a href='goals.php' target='_blank'>🔗 Voltar para Gestão de Metas</a></p>";
    
} else {
    echo "<p>⚠️ Não há metas diárias para o mês atual. Crie algumas metas primeiro.</p>";
    echo "<p><a href='new-goal.php'>🔗 Criar Nova Meta</a></p>";
}

echo "<h3>Informações Técnicas:</h3>";
echo "<p><strong>Formato de exportação:</strong> CSV com separador ';' (ponto e vírgula)</p>";
echo "<p><strong>Codificação:</strong> UTF-8 com BOM</p>";
echo "<p><strong>Compatibilidade:</strong> Microsoft Excel, LibreOffice Calc, Google Sheets</p>";
echo "<p><strong>Caracteres especiais suportados:</strong> á, à, â, ã, é, è, ê, í, ì, î, ó, ò, ô, õ, ú, ù, û, ç, etc.</p>";

?>