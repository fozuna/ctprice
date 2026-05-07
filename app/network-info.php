<?php
// Obter o IP do servidor
$serverIP = $_SERVER['SERVER_ADDR'] ?? 'Não disponível';
$serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
$serverPort = $_SERVER['SERVER_PORT'] ?? '8000';

// Tentar obter o IP real da máquina
$realIP = '';
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    // Windows
    $output = shell_exec('ipconfig | findstr /c:"IPv4"');
    if ($output) {
        preg_match('/(\d+\.\d+\.\d+\.\d+)/', $output, $matches);
        if (isset($matches[1])) {
            $realIP = $matches[1];
        }
    }
} else {
    // Linux/Mac
    $output = shell_exec('hostname -I');
    if ($output) {
        $realIP = trim(explode(' ', $output)[0]);
    }
}

$currentIP = $realIP ?: $serverIP;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informações de Acesso - Sistema de Gestão de Metas</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
        }
        .info-box {
            background: #e8f4fd;
            border: 1px solid #bee5eb;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
        .url-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            padding: 15px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 16px;
            text-align: center;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🌐 Sistema de Gestão de Metas</h1>
            <h2>Informações de Acesso - Rede Interna</h2>
        </div>

        <div class="info-box">
            <h3>📍 IP Atual do Servidor:</h3>
            <div class="url-box">
                <strong><?= htmlspecialchars($currentIP) ?></strong>
            </div>
        </div>

        <div class="info-box">
            <h3>🔗 URLs de Acesso:</h3>
            
            <p><strong>Acesso Local (apenas nesta máquina):</strong></p>
            <div class="url-box">
                <a href="http://localhost:<?= $serverPort ?>" target="_blank">
                    http://localhost:<?= $serverPort ?>
                </a>
            </div>

            <p><strong>Acesso pela Rede Interna (compartilhe com a equipe):</strong></p>
            <div class="url-box">
                <a href="http://<?= $currentIP ?>:<?= $serverPort ?>" target="_blank">
                    http://<?= $currentIP ?>:<?= $serverPort ?>
                </a>
            </div>
        </div>

        <div class="warning">
            <h3>⚠️ Importante:</h3>
            <ul>
                <li>O IP pode mudar diariamente devido ao DHCP</li>
                <li>Sempre verifique esta página para obter o IP atual</li>
                <li>Compartilhe a URL da rede interna com sua equipe</li>
                <li>Certifique-se de que o firewall permite conexões na porta <?= $serverPort ?></li>
            </ul>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" class="btn">🏠 Ir para o Sistema</a>
            <a href="javascript:location.reload()" class="btn">🔄 Atualizar IP</a>
        </div>

        <div style="text-align: center; margin-top: 20px; color: #666; font-size: 12px;">
            <p>Última atualização: <?= date('d/m/Y H:i:s') ?></p>
        </div>
    </div>
</body>
</html>