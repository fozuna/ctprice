# 🚀 Guia de Instalação - Sistema BDO

Este guia fornece instruções detalhadas para instalar o Sistema BDO em diferentes ambientes.

## 📋 Pré-requisitos

### Servidor Web
- **Apache 2.4+** ou **Nginx 1.18+**
- **PHP 7.4** ou superior (recomendado PHP 8.0+)
- **MySQL 5.7** ou superior (ou MariaDB 10.3+)

### Extensões PHP Obrigatórias
```bash
# Verificar extensões instaladas
php -m | grep -E "(pdo|pdo_mysql|json|mbstring|openssl|curl|gd)"
```

Extensões necessárias:
- `pdo`
- `pdo_mysql`
- `json`
- `mbstring`
- `openssl`
- `curl` (opcional, para integrações)
- `gd` (opcional, para manipulação de imagens)

### Permissões de Sistema
- Permissão de escrita nos diretórios:
  - `config/`
  - `uploads/`
  - `logs/`
  - `temp/`

## 🎯 Instalação Rápida (Recomendada)

### 1. Download e Upload
```bash
# Faça download dos arquivos do sistema
# Extraia para o diretório do seu site
# Exemplo: public_html/bdo/ ou htdocs/bdo/
```

### 2. Configurar Permissões
```bash
# Linux/Unix
chmod 755 config/ uploads/ logs/ temp/
chmod 644 config/config.example.php

# Windows (via PowerShell como Administrador)
icacls "config" /grant "IIS_IUSRS:(OI)(CI)F"
icacls "uploads" /grant "IIS_IUSRS:(OI)(CI)F"
icacls "logs" /grant "IIS_IUSRS:(OI)(CI)F"
```

### 3. Executar Instalador (opcional)
```
http://seudominio.com/bdo/install.php
```

Siga o assistente de instalação que irá:
- ✅ Verificar requisitos do sistema
- ✅ Configurar conexão com banco de dados
- ✅ Criar estrutura do banco
- ✅ Configurar usuário administrador
- ✅ Finalizar instalação

### 4. Primeiro Acesso
```
http://seudominio.com/bdo/login.php
```

Use as credenciais criadas durante a instalação.

## 🔧 Instalação Manual

### 1. Preparar Banco de Dados

#### MySQL/MariaDB
```sql
-- Conectar como root
mysql -u root -p

-- Criar banco de dados
CREATE DATABASE appmadeplant CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Criar usuário (opcional)
CREATE USER 'bdo_user'@'localhost' IDENTIFIED BY 'senha_segura';
GRANT ALL PRIVILEGES ON appmadeplant.* TO 'bdo_user'@'localhost';
FLUSH PRIVILEGES;

-- Sair
EXIT;
```

#### Importar Schema
```bash
# Importar estrutura base
mysql -u bdo_user -p appmadeplant < database/schema.sql

# Estruturas de metas
mysql -u bdo_user -p appmadeplant < database/goals_schema.sql

# Tipos de meta
mysql -u bdo_user -p appmadeplant < database/add_goal_type_column.sql

# Produção diária
mysql -u bdo_user -p appmadeplant < database/add_daily_production_table.sql

# Entrega diária
mysql -u bdo_user -p appmadeplant < database/add_daily_delivery_table.sql

# (Opcional) dados de exemplo
mysql -u bdo_user -p appmadeplant < database/seeds.sql
```

### 2. Configurar Sistema

#### Copiar Configuração
```bash
# Copiar arquivo de exemplo
cp config/config.example.php config/config.php
```

#### Editar Configurações
```php
// config/config.php

// Banco de Dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'appmadeplant');
define('DB_USER', 'bdo_user');
define('DB_PASS', 'senha_segura');
define('BASE_URL', 'http://localhost:8000');

// Segurança (GERE CHAVES ÚNICAS!)
define('JWT_SECRET', 'sua_chave_jwt_32_caracteres_minimo');
define('ENCRYPTION_KEY', 'sua_chave_criptografia_32_caracteres');

// Sistema
define('SYSTEM_NAME', 'Sistema BDO - Sua Empresa');
define('BASE_URL', 'https://seudominio.com/bdo');
define('TIMEZONE', 'America/Sao_Paulo');
```

### 3. Criar Usuário Administrador

#### Via SQL
```sql
-- Conectar ao banco
mysql -u bdo_user -p appmadeplant

-- Inserir usuário admin (senha: admin123)
INSERT INTO users (name, email, password, role, status, created_at) 
VALUES (
    'Administrador', 
    'admin@suaempresa.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
    'ADMIN', 
    'ATIVO', 
    NOW()
);
```

#### Via PHP
```php
// criar_admin.php (execute uma vez e delete)
<?php
require_once 'config/config.php';
require_once 'config/Database.php';

$db = Database::getInstance()->getConnection();

$name = 'Administrador';
$email = 'admin@suaempresa.com';
$password = password_hash('admin123', PASSWORD_DEFAULT);

$stmt = $db->prepare("
    INSERT INTO users (name, email, password, role, status, created_at) 
    VALUES (?, ?, ?, 'ADMIN', 'ATIVO', NOW())
");

if ($stmt->execute([$name, $email, $password])) {
    echo "Usuário administrador criado com sucesso!";
} else {
    echo "Erro ao criar usuário administrador.";
}
?>
```

### 4. Configurar Servidor Web

#### Apache (DocumentRoot em `public/`)
```apache
# .htaccess (em public/.htaccess)
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]
RewriteRule ^ router.php [L]

# Proteger arquivos sensíveis
<Files "*.sql">
    Require all denied
</Files>

<Files "config.php">
    Require all denied
</Files>

# Configurações de segurança
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"

## ▶️ Execução Local Rápida

### Usando servidor embutido do PHP
```bash
php -S localhost:8000 -t public public/router.php
# Acesse: http://localhost:8000/
```

### Usando XAMPP
- Copie o diretório do projeto para `C:\xampp\htdocs\app`
- Configure o Apache para usar `C:\xampp\htdocs\app\public` como DocumentRoot (ou use `start-server.bat`)
- Inicie Apache e MySQL no XAMPP
- Acesse: `http://localhost:8000/` (servidor embutido) ou `http://localhost/app/` (Apache)
```

#### Nginx
```nginx
# /etc/nginx/sites-available/bdo
server {
    listen 80;
    server_name seudominio.com;
    root /var/www/html/bdo;
    index login.php;

    # Proteger arquivos sensíveis
    location ~ \.(sql|log|env)$ {
        deny all;
    }

    location ~ /config/ {
        deny all;
    }

    # PHP
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Configurações de segurança
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
}
```

## 🌐 Instalação em Hospedagem Compartilhada

### 1. Preparação
- Faça upload via FTP/cPanel File Manager
- Descompacte no diretório `public_html/`
- Acesse o cPanel para configurar banco

### 2. Banco de Dados via cPanel
1. **MySQL Databases** → Criar banco
2. **MySQL Users** → Criar usuário
3. **Add User to Database** → Dar privilégios
4. Anotar: host, banco, usuário, senha

### 3. Configuração
- Use o instalador web: `seudominio.com/install.php`
- Ou configure manualmente via File Manager

### 4. Problemas Comuns

#### Erro de Permissões
```bash
# Via File Manager do cPanel
# Selecionar pastas → Change Permissions → 755
# config/, public/uploads/, logs/
```

#### Limite de Memória PHP
```php
// .htaccess
php_value memory_limit 256M
php_value max_execution_time 300
php_value upload_max_filesize 10M
php_value post_max_size 10M
```

#### Erro de Conexão MySQL
- Verificar host (pode ser diferente de localhost)
- Verificar se usuário tem privilégios
- Testar conexão via phpMyAdmin

## 🐳 Instalação com Docker

### 1. Criar docker-compose.yml
```yaml
version: '3.8'

services:
  web:
    image: php:8.0-apache
    ports:
      - "8080:80"
    volumes:
      - .:/var/www/html
    depends_on:
      - db
    environment:
      - DB_HOST=db
      - DB_NAME=appmadeplant
      - DB_USER=root
      - DB_PASS=rootpassword

  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_DATABASE: appmadeplant
    volumes:
      - mysql_data:/var/lib/mysql
      - ./database/schema.sql:/docker-entrypoint-initdb.d/schema.sql

volumes:
  mysql_data:
```

### 2. Executar
```bash
# Iniciar containers
docker-compose up -d

# Acessar
http://localhost:8080/install.php
```

## 🔒 Configurações de Segurança

### 1. HTTPS (Recomendado)
```apache
# .htaccess - Forçar HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 2. Chaves de Segurança
```bash
# Gerar chaves aleatórias
openssl rand -base64 32  # JWT_SECRET
openssl rand -base64 32  # ENCRYPTION_KEY
openssl rand -base64 32  # PASSWORD_SALT
```

### 3. Backup do Banco
```bash
# Backup automático
mysqldump -u bdo_user -p appmadeplant > backup_$(date +%Y%m%d).sql

# Restaurar backup
mysql -u bdo_user -p appmadeplant < backup_20231020.sql
```

## 🧪 Teste da Instalação

### 1. Verificar Funcionalidades
- [ ] Login com usuário administrador
- [ ] Cadastro de equipamentos
- [ ] Cadastro de frentes
- [ ] Cadastro de tipos de ocorrência
- [ ] Cadastro de usuários
- [ ] Registro de ocorrências
- [ ] Geração de relatórios
- [ ] Importação de dados CSV

### 2. Verificar Segurança
- [ ] Acesso negado a arquivos sensíveis
- [ ] Redirecionamento de usuários não autenticados
- [ ] Controle de permissões por role
- [ ] Log de auditoria funcionando

### 3. Verificar Performance
- [ ] Tempo de carregamento < 3 segundos
- [ ] Responsividade em dispositivos móveis
- [ ] Funcionamento em diferentes navegadores

## 🆘 Solução de Problemas

### Erro 500 - Internal Server Error
```bash
# Verificar logs
tail -f /var/log/apache2/error.log
tail -f logs/php_errors.log

# Verificar permissões
ls -la config/ public/uploads/ logs/
```

### Erro de Conexão com Banco
```php
// teste_conexao.php
<?php
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=appmadeplant;charset=utf8mb4",
        "bdo_user",
        "senha"
    );
    echo "Conexão OK!";
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>
```

### Página em Branco
```php
// Ativar exibição de erros temporariamente
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

### Problemas de Sessão
```php
// Verificar configurações de sessão
echo "Session Save Path: " . session_save_path();
echo "Session Name: " . session_name();
```

## 📞 Suporte

### Logs do Sistema
- `logs/system.log` - Logs gerais
- `logs/php_errors.log` - Erros PHP
- `logs/access.log` - Logs de acesso
- Tabela `audit_logs` - Auditoria do sistema

### Informações do Sistema
```php
// info.php (delete após uso)
<?php phpinfo(); ?>
```

### Contato
- Documentação: README.md
- Logs de erro: logs/
- Configurações: config/config.php

---

**✅ Instalação concluída com sucesso!**

Acesse o sistema e comece a gerenciar seus equipamentos e maquinários de forma eficiente.