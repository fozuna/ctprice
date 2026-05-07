<?php
/**
 * Sistema de Versionamento Automático
 * Sistema BDO - Controle de Maquinários
 */

class VersionManager {
    private static $versionFile = __DIR__ . '/version.json';
    private static $instance = null;
    
    private $version;
    private $gitSynced = false;
    
    private function __construct() {
        $this->loadVersion();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Carrega a versão atual do arquivo JSON
     */
    private function loadVersion() {
        if (file_exists(self::$versionFile)) {
            $data = json_decode(file_get_contents(self::$versionFile), true);
            $this->version = $data;
        } else {
            // Versão inicial baseada na sua versão atual
            $this->version = [
                'major' => 1,
                'minor' => 3,
                'patch' => 360,
                'build' => 0,
                'last_updated' => date('Y-m-d H:i:s'),
                'last_change' => 'Inicialização do sistema de versionamento automático',
                'commit' => null,
                'commit_full' => null,
                'last_commit_time' => null
            ];
            $this->saveVersion();
        }
    }
    
    /**
     * Salva a versão atual no arquivo JSON
     */
    private function saveVersion() {
        $this->version['last_updated'] = date('Y-m-d H:i:s');
        file_put_contents(self::$versionFile, json_encode($this->version, JSON_PRETTY_PRINT));
    }
    
    /**
     * Retorna a versão atual formatada
     */
    public function getCurrentVersion() {
        return "v{$this->version['major']}.{$this->version['minor']}.{$this->version['patch']}";
    }
    
    /**
     * Retorna a versão completa com build
     */
    public function getFullVersion() {
        return "v{$this->version['major']}.{$this->version['minor']}.{$this->version['patch']}.{$this->version['build']}";
    }
    
    /**
     * Incrementa o número de build automaticamente
     */
    public function incrementBuild($changeDescription = 'Alteração automática') {
        $this->version['build']++;
        $this->version['last_change'] = $changeDescription;
        $this->saveVersion();
        return $this->getCurrentVersion();
    }
    
    /**
     * Incrementa o patch (para correções)
     */
    public function incrementPatch($changeDescription = 'Correção de bug') {
        $this->version['patch']++;
        $this->version['build'] = 0;
        $this->version['last_change'] = $changeDescription;
        $this->saveVersion();
        return $this->getCurrentVersion();
    }
    
    /**
     * Incrementa o minor (para novas funcionalidades)
     */
    public function incrementMinor($changeDescription = 'Nova funcionalidade') {
        $this->version['minor']++;
        $this->version['patch'] = 0;
        $this->version['build'] = 0;
        $this->version['last_change'] = $changeDescription;
        $this->saveVersion();
        return $this->getCurrentVersion();
    }
    
    /**
     * Incrementa o major (para mudanças importantes)
     */
    public function incrementMajor($changeDescription = 'Versão principal') {
        $this->version['major']++;
        $this->version['minor'] = 0;
        $this->version['patch'] = 0;
        $this->version['build'] = 0;
        $this->version['last_change'] = $changeDescription;
        $this->saveVersion();
        return $this->getCurrentVersion();
    }
    
    /**
     * Retorna informações completas da versão
     */
    public function getVersionInfo() {
        $this->syncCommit(false);
        return $this->version;
    }
    
    /**
     * Auto-incrementa baseado no tipo de mudança
     */
    public function autoIncrement($changeType = 'build', $description = '') {
        switch (strtolower($changeType)) {
            case 'major':
                return $this->incrementMajor($description);
            case 'minor':
            case 'feature':
                return $this->incrementMinor($description);
            case 'patch':
            case 'fix':
                return $this->incrementPatch($description);
            case 'build':
            default:
                return $this->incrementBuild($description);
        }
    }

    public function getLastUpdatedTimestamp() {
        $ts = $this->version['last_updated'] ?? null;
        return $ts ? strtotime($ts) : 0;
    }

    private function syncCommit($persist = false) {
        if ($this->gitSynced) return;
        $info = $this->readGitHead();
        if ($info) {
            $this->version['commit_full'] = $info['full'];
            $this->version['commit'] = $info['short'];
            $this->version['last_commit_time'] = date('Y-m-d H:i:s', $info['time']);
            if ($persist) {
                $this->saveVersion();
            }
        }
        $this->gitSynced = true;
    }

    private function readGitHead() {
        $root = dirname(__DIR__);
        $gitDir = $root . DIRECTORY_SEPARATOR . '.git';
        $headPath = $gitDir . DIRECTORY_SEPARATOR . 'HEAD';
        if (!is_file($headPath)) {
            return null;
        }
        $head = trim(@file_get_contents($headPath));
        $full = null;
        $t = @filemtime($headPath) ?: time();
        if (strpos($head, 'ref: ') === 0) {
            $ref = trim(substr($head, 5));
            $refPath = $gitDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $ref);
            if (is_file($refPath)) {
                $full = trim(@file_get_contents($refPath));
                $t = @filemtime($refPath) ?: $t;
            }
        } else {
            $full = $head;
        }
        if (!$full) return null;
        $short = substr($full, 0, 7);
        return ['full' => $full, 'short' => $short, 'time' => $t];
    }
}

// Função global para facilitar o uso
function getSystemVersion() {
    return VersionManager::getInstance()->getCurrentVersion();
}

function getFullSystemVersion() {
    return VersionManager::getInstance()->getFullVersion();
}

function incrementSystemVersion($type = 'build', $description = '') {
    return VersionManager::getInstance()->autoIncrement($type, $description);
}

function getVersionInfo() {
    return VersionManager::getInstance()->getVersionInfo();
}

// Definir a constante SYSTEM_VERSION para compatibilidade (apenas se não existir)
if (!defined('SYSTEM_VERSION')) {
    define('SYSTEM_VERSION', getSystemVersion());
}
