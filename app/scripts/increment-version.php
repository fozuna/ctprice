<?php
/**
 * Script para incrementar versão automaticamente
 * Uso: php increment-version.php [tipo] [descrição]
 * 
 * Tipos disponíveis:
 * - build (padrão): Para pequenas alterações
 * - patch: Para correções de bugs
 * - minor: Para novas funcionalidades
 * - major: Para mudanças importantes
 */

// Incluir o sistema de versionamento
require_once __DIR__ . '/../config/version.php';

// Verificar argumentos da linha de comando
$type = isset($argv[1]) ? $argv[1] : 'build';
$description = isset($argv[2]) ? $argv[2] : '';

// Validar tipo
$validTypes = ['build', 'patch', 'minor', 'major', 'fix', 'feature'];
if (!in_array(strtolower($type), $validTypes)) {
    echo "Erro: Tipo de versão inválido. Use: build, patch, minor, major, fix, feature\n";
    exit(1);
}

// Se não foi fornecida descrição, usar uma padrão baseada no tipo
if (empty($description)) {
    switch (strtolower($type)) {
        case 'major':
            $description = 'Nova versão principal';
            break;
        case 'minor':
        case 'feature':
            $description = 'Nova funcionalidade adicionada';
            break;
        case 'patch':
        case 'fix':
            $description = 'Correção de bug';
            break;
        case 'build':
        default:
            $description = 'Alteração no código';
            break;
    }
}

try {
    // Obter versão anterior
    $versionManager = VersionManager::getInstance();
    $oldVersion = $versionManager->getCurrentVersion();
    
    // Incrementar versão
    $newVersion = $versionManager->autoIncrement($type, $description);
    
    // Exibir resultado
    echo "✅ Versão atualizada com sucesso!\n";
    echo "📦 Versão anterior: {$oldVersion}\n";
    echo "🚀 Nova versão: {$newVersion}\n";
    echo "📝 Descrição: {$description}\n";
    echo "⏰ Data/Hora: " . date('d/m/Y H:i:s') . "\n";
    
    // Exibir informações completas se solicitado
    if (isset($argv[3]) && $argv[3] === '--info') {
        echo "\n📊 Informações completas da versão:\n";
        $info = $versionManager->getVersionInfo();
        foreach ($info as $key => $value) {
            echo "   {$key}: {$value}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erro ao atualizar versão: " . $e->getMessage() . "\n";
    exit(1);
}