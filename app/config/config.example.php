<?php
/**
 * Arquivo de Configuração de Exemplo
 * 
 * Copie este arquivo para config.php e ajuste as configurações
 * conforme seu ambiente de produção ou desenvolvimento.
 */

// =============================================================================
// CONFIGURAÇÕES DO BANCO DE DADOS
// =============================================================================

// Host do banco de dados (geralmente localhost)
define('DB_HOST', 'localhost');

// Nome do banco de dados
define('DB_NAME', 'appmadeplant');

// Usuário do banco de dados
define('DB_USER', 'root');

// Senha do banco de dados
define('DB_PASS', '');

// Porta do banco de dados (padrão MySQL: 3306)
define('DB_PORT', '3306');

// Charset do banco de dados
define('DB_CHARSET', 'utf8mb4');

// =============================================================================
// CONFIGURAÇÕES DE SEGURANÇA
// =============================================================================

// Chave secreta para JWT (ALTERE ESTA CHAVE!)
// Gere uma chave aleatória de pelo menos 32 caracteres
define('JWT_SECRET', 'sua_chave_secreta_jwt_muito_segura_aqui');

// Chave para criptografia (ALTERE ESTA CHAVE!)
// Gere uma chave aleatória de pelo menos 32 caracteres
define('ENCRYPTION_KEY', 'sua_chave_criptografia_muito_segura_aqui');

// Salt para senhas (ALTERE ESTE SALT!)
define('PASSWORD_SALT', 'seu_salt_para_senhas_muito_seguro_aqui');

// =============================================================================
// CONFIGURAÇÕES DO SISTEMA
// =============================================================================

// Nome do sistema
define('SYSTEM_NAME', 'Sistema BDO - Controle de Maquinários');

// Versão do sistema
define('SYSTEM_VERSION', '1.0.0');

// Timezone
define('TIMEZONE', 'America/Sao_Paulo');

// URL base do sistema (sem barra no final)
define('BASE_URL', 'http://app.test');

// Diretório raiz do sistema
define('ROOT_PATH', __DIR__ . '/..');

// =============================================================================
// CONFIGURAÇÕES DE EMAIL (OPCIONAL)
// =============================================================================

// Configurações SMTP para envio de emails
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'seu_email@gmail.com');
define('SMTP_PASSWORD', 'sua_senha_app');
define('SMTP_ENCRYPTION', 'tls');

// Email remetente padrão
define('FROM_EMAIL', 'noreply@suaempresa.com');
define('FROM_NAME', 'Sistema BDO');

// =============================================================================
// CONFIGURAÇÕES DE DESENVOLVIMENTO
// =============================================================================

// Modo de desenvolvimento (true/false)
define('DEBUG_MODE', true);

// Exibir erros (true/false)
define('DISPLAY_ERRORS', true);

// Log de erros (true/false)
define('LOG_ERRORS', true);

// Nível de log (DEBUG, INFO, WARNING, ERROR)
define('LOG_LEVEL', 'DEBUG');

// =============================================================================
// CONFIGURAÇÕES DE UPLOAD
// =============================================================================

// Tamanho máximo de upload (em bytes)
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB

// Tipos de arquivo permitidos para upload
define('ALLOWED_FILE_TYPES', 'csv,txt,pdf,jpg,jpeg,png,gif');

// Diretório de uploads
define('UPLOAD_PATH', ROOT_PATH . '/uploads');

// Perfis (RBAC)
define('ROLE_ADMIN', 1);
define('ROLE_SUPERVISOR', 2);
define('ROLE_LIDER', 3);
define('ROLE_OPERADOR', 4);
define('ROLE_COORD_RH', 5);
define('ROLE_COMPRAS', 6);

// =============================================================================
// CONFIGURAÇÕES DE SESSÃO
// =============================================================================

// Nome da sessão
define('SESSION_NAME', 'BDO_SESSION');

// Tempo de vida da sessão (em segundos)
define('SESSION_LIFETIME', 3600 * 8); // 8 horas

// Regenerar ID da sessão
define('SESSION_REGENERATE', true);

// =============================================================================
// CONFIGURAÇÕES DE CACHE
// =============================================================================

// Habilitar cache (true/false)
define('CACHE_ENABLED', false);

// Tempo de cache padrão (em segundos)
define('CACHE_TIME', 3600); // 1 hora

// =============================================================================
// CONFIGURAÇÕES DE API
// =============================================================================

// Habilitar API REST (true/false)
define('API_ENABLED', false);

// Chave da API
define('API_KEY', 'sua_chave_api_aqui');

// Rate limit (requisições por minuto)
define('API_RATE_LIMIT', 60);

// =============================================================================
// CONFIGURAÇÕES ESPECÍFICAS DO NEGÓCIO
// =============================================================================

// Horário de trabalho padrão
define('WORK_START_TIME', '06:00');
define('WORK_END_TIME', '18:00');

// Dias úteis (1=Segunda, 7=Domingo)
define('WORK_DAYS', '1,2,3,4,5,6');

// Tolerância para sobreposição de horários (em minutos)
define('TIME_OVERLAP_TOLERANCE', 5);

// Backup automático (true/false)
define('AUTO_BACKUP', false);

// Intervalo de backup (em horas)
define('BACKUP_INTERVAL', 24);

// =============================================================================
// CONFIGURAÇÕES DE NOTIFICAÇÃO
// =============================================================================

// Habilitar notificações (true/false)
define('NOTIFICATIONS_ENABLED', false);

// Tipos de notificação habilitados
define('NOTIFICATION_TYPES', 'email,sms,push');

// =============================================================================
// CONFIGURAÇÕES DE RELATÓRIOS
// =============================================================================

// Formato padrão de exportação
define('DEFAULT_EXPORT_FORMAT', 'csv');

// Limite de registros por relatório
define('REPORT_LIMIT', 10000);

// Diretório temporário para relatórios
define('TEMP_PATH', ROOT_PATH . '/temp');

// =============================================================================
// CONFIGURAÇÕES DE MANUTENÇÃO
// =============================================================================

// Modo de manutenção (true/false)
define('MAINTENANCE_MODE', false);

// Mensagem de manutenção
define('MAINTENANCE_MESSAGE', 'Sistema em manutenção. Tente novamente em alguns minutos.');

// IPs liberados durante manutenção
define('MAINTENANCE_ALLOWED_IPS', '127.0.0.1,::1');

// =============================================================================
// CONFIGURAÇÕES AVANÇADAS
// =============================================================================

// Habilitar compressão GZIP
define('GZIP_COMPRESSION', true);

// Habilitar minificação de CSS/JS
define('MINIFY_ASSETS', false);

// Usar CDN para assets
define('USE_CDN', false);

// URL do CDN
define('CDN_URL', 'https://cdn.suaempresa.com');

// =============================================================================
// CONFIGURAÇÕES DE INTEGRAÇÃO
// =============================================================================

// Integração com ERP (true/false)
define('ERP_INTEGRATION', false);

// URL da API do ERP
define('ERP_API_URL', 'https://api.erp.suaempresa.com');

// Token da API do ERP
define('ERP_API_TOKEN', 'seu_token_erp_aqui');

// =============================================================================
// CONFIGURAÇÕES DE MONITORAMENTO
// =============================================================================

// Habilitar monitoramento (true/false)
define('MONITORING_ENABLED', false);

// URL do serviço de monitoramento
define('MONITORING_URL', 'https://monitoring.suaempresa.com');

// Chave do monitoramento
define('MONITORING_KEY', 'sua_chave_monitoramento_aqui');

// =============================================================================
// CONFIGURAÇÕES FINAIS
// =============================================================================

// Definir timezone
date_default_timezone_set(TIMEZONE);

// Configurar exibição de erros
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', DISPLAY_ERRORS ? 1 : 0);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Configurar log de erros
ini_set('log_errors', LOG_ERRORS ? 1 : 0);
if (LOG_ERRORS) {
    ini_set('error_log', ROOT_PATH . '/logs/php_errors.log');
}

// Configurar sessão
ini_set('session.name', SESSION_NAME);
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
ini_set('session.cookie_lifetime', SESSION_LIFETIME);

// Configurar upload
ini_set('upload_max_filesize', MAX_UPLOAD_SIZE);
ini_set('post_max_size', MAX_UPLOAD_SIZE);

?>
