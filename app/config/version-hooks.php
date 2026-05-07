<?php
/**
 * Sistema de Hooks para Versionamento Automático
 * Detecta mudanças em arquivos importantes e incrementa a versão automaticamente
 */

require_once __DIR__ . '/version.php';

class VersionHooks {
    private static $instance = null;
    private $watchedFiles = [];
    private $lastChecked = null;
    
    private function __construct() {
        $this->initializeWatchedFiles();
        $vm = VersionManager::getInstance();
        $this->lastChecked = $vm->getLastUpdatedTimestamp();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Inicializa a lista de arquivos monitorados
     */
    private function initializeWatchedFiles() {
        $appRoot = dirname(__DIR__);
        
        $this->watchedFiles = [
            // Arquivos PHP principais
            'php_files' => [
                'pattern' => $appRoot . '/*.php',
                'type' => 'build',
                'description' => 'Alteração em arquivo PHP principal'
            ],
            // Arquivos de configuração
            'config_files' => [
                'pattern' => $appRoot . '/config/*.php',
                'type' => 'patch',
                'description' => 'Alteração em configuração'
            ],
            // Arquivos de includes
            'includes_files' => [
                'pattern' => $appRoot . '/includes/*.php',
                'type' => 'build',
                'description' => 'Alteração em arquivo de include'
            ],
            // Arquivos CSS/JS
            'assets_files' => [
                'pattern' => $appRoot . '/public/assets/*',
                'type' => 'build',
                'description' => 'Alteração em assets públicos'
            ],
            // Novos arquivos importantes
            'important_files' => [
                'files' => [
                    $appRoot . '/dashboard.php',
                    $appRoot . '/equipment.php',
                    $appRoot . '/occurrences.php'
                ],
                'type' => 'minor',
                'description' => 'Alteração em funcionalidade principal'
            ]
        ];
    }
    
    /**
     * Verifica se houve mudanças nos arquivos monitorados
     */
    public function checkForChanges() {
        $changes = [];
        
        foreach ($this->watchedFiles as $category => $config) {
            if (isset($config['pattern'])) {
                $files = glob($config['pattern']);
                foreach ($files as $file) {
                    if (file_exists($file) && filemtime($file) > $this->lastChecked) {
                        $changes[] = [
                            'file' => $file,
                            'type' => $config['type'],
                            'description' => $config['description'] . ': ' . basename($file),
                            'modified' => filemtime($file)
                        ];
                    }
                }
            } elseif (isset($config['files'])) {
                foreach ($config['files'] as $file) {
                    if (file_exists($file) && filemtime($file) > $this->lastChecked) {
                        $changes[] = [
                            'file' => $file,
                            'type' => $config['type'],
                            'description' => $config['description'] . ': ' . basename($file),
                            'modified' => filemtime($file)
                        ];
                    }
                }
            }
        }
        
        return $changes;
    }
    
    /**
     * Processa as mudanças e incrementa a versão se necessário
     */
    public function processChanges() {
        $changes = $this->checkForChanges();
        
        if (empty($changes)) {
            return false;
        }
        
        // Determinar o tipo de incremento mais significativo
        $incrementType = 'build';
        $descriptions = [];
        
        foreach ($changes as $change) {
            $descriptions[] = $change['description'];
            
            // Prioridade: major > minor > patch > build
            if ($change['type'] === 'major') {
                $incrementType = 'major';
            } elseif ($change['type'] === 'minor' && $incrementType !== 'major') {
                $incrementType = 'minor';
            } elseif ($change['type'] === 'patch' && !in_array($incrementType, ['major', 'minor'])) {
                $incrementType = 'patch';
            }
        }
        
        // Incrementar versão
        $description = implode('; ', array_unique($descriptions));
        $versionManager = VersionManager::getInstance();
        $newVersion = $versionManager->autoIncrement($incrementType, $description);
        
        // Atualizar timestamp da última verificação
        $this->lastChecked = time();
        
        return [
            'version' => $newVersion,
            'type' => $incrementType,
            'changes' => count($changes),
            'description' => $description
        ];
    }
    
    /**
     * Força o incremento da versão
     */
    public function forceIncrement($type = 'build', $description = 'Incremento manual') {
        $versionManager = VersionManager::getInstance();
        return $versionManager->autoIncrement($type, $description);
    }
    
    /**
     * Registra um arquivo para monitoramento
     */
    public function addWatchedFile($file, $type = 'build', $description = 'Alteração em arquivo monitorado') {
        $this->watchedFiles['custom_' . md5($file)] = [
            'files' => [$file],
            'type' => $type,
            'description' => $description
        ];
    }
}

/**
 * Função para incrementar versão automaticamente ao incluir este arquivo
 */
function autoIncrementVersion($type = 'build', $description = '') {
    static $hasIncremented = false;
    
    // Evitar múltiplos incrementos na mesma execução
    if ($hasIncremented) {
        return VersionManager::getInstance()->getCurrentVersion();
    }
    
    $hooks = VersionHooks::getInstance();
    $result = $hooks->processChanges();
    
    if ($result) {
        $hasIncremented = true;
        return $result['version'];
    }
    
    // Se não houve mudanças detectadas, mas foi solicitado incremento manual
    if (!empty($type) && $type !== 'auto') {
        $hasIncremented = true;
        return $hooks->forceIncrement($type, $description);
    }
    
    return VersionManager::getInstance()->getCurrentVersion();
}

// Função global para facilitar o uso
function checkAndIncrementVersion() {
    return autoIncrementVersion('auto');
}
