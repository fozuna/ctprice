# Sistema BDO - Controle de Maquinários

Sistema completo para controle e monitoramento de equipamentos e maquinários, desenvolvido em PHP com interface moderna e responsiva.

## 📋 Funcionalidades

### Gestão de Equipamentos
- Cadastro completo de equipamentos com tags únicas
- Controle de horímetros
- Associação com frentes de serviço
- Status de atividade

### Controle de Ocorrências
- Registro de movimentos de produção, manutenção e paradas
- Controle de horários de início e fim
- Cálculo automático de duração e horas trabalhadas
- Validações para evitar sobreposições

### Sistema de Usuários e Permissões (RBAC)
- **ADMIN**: Acesso total ao sistema
- **SUPERVISOR**: Gestão de frentes e relatórios
- **LEADER**: Controle de equipe e equipamentos
- **OPERATOR**: Registro de ocorrências

### Relatórios e Análises
- Relatórios de ocorrências com filtros avançados
- Análise de uso de equipamentos
- Relatórios de produtividade
- Relatórios de manutenção
- Exportação para CSV

### Importação de Dados
- Importação em lote via CSV
- Templates de exemplo
- Validação de dados
- Log de erros detalhado

## 🛠️ Tecnologias Utilizadas

- **Backend**: PHP 8.0+
- **Banco de Dados**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript
- **Framework CSS**: Tailwind CSS
- **Ícones**: Font Awesome
- **Interatividade**: Alpine.js

## 📦 Requisitos do Sistema

### Servidor Web
- Apache 2.4+ ou Nginx
- PHP 7.4 ou superior
- MySQL 5.7 ou superior

### Extensões PHP Necessárias
- PDO
- PDO MySQL
- JSON
- MBString
- OpenSSL (recomendado)

### Permissões de Diretório
- Diretório `config/` com permissão de escrita
- Diretório `public/uploads/` com permissão de escrita
- Diretório `logs/` com permissão de escrita

## 🚀 Instalação

### Quickstart (Local)

1. Clonar o repositório
   ```bash
   git clone https://github.com/<seu-usuario>/<seu-repo>.git
   cd <seu-repo>
   ```
2. Configurar o banco
   ```sql
   CREATE DATABASE appmadeplant CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Executar scripts principais (ordem):
   ```bash
   # Estrutura base
   mysql -u root appmadeplant < database/schema.sql
   # Estrutura de metas
   mysql -u root appmadeplant < database/goals_schema.sql
   # Tipos de meta (production/delivery)
   mysql -u root appmadeplant < database/add_goal_type_column.sql
   # Produção diária
   mysql -u root appmadeplant < database/add_daily_production_table.sql
   # Entrega diária
   mysql -u root appmadeplant < database/add_daily_delivery_table.sql
   # (Opcional) dados de exemplo
   mysql -u root appmadeplant < database/seeds.sql
   ```
4. Configurar ambiente
   ```bash
   # Copiar arquivo de exemplo
   cp config/config.example.php config/config.php
   # Editar DB_HOST, DB_NAME, DB_USER, DB_PASS, BASE_URL e chaves
   ```
5. Subir servidor local (PHP embutido)
   ```bash
   php -S localhost:8000 -t public public/router.php
   # Acesse: http://localhost:8000/
   ```

### Método 1: Instalação Automática (Recomendado)

1. **Upload dos Arquivos**
   ```bash
   # Faça upload de todos os arquivos para o diretório do seu site
   # Exemplo: public_html/bdo/ ou htdocs/bdo/
   ```

2. **Acesse o Instalador**
   ```
   http://seudominio.com/bdo/install.php
   ```

3. **Siga o Assistente**
   - Verificação de requisitos
   - Configuração do banco de dados
   - Criação do usuário administrador
   - Finalização da instalação

### Método 2: Instalação Manual

1. **Configurar Banco de Dados**
   ```sql
   CREATE DATABASE appmadeplant CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Importar Schema**
   ```bash
   mysql -u usuario -p appmadeplant < database/schema.sql
   ```

3. **Configurar Conexão**
   - Copie `config/config.example.php` para `config/config.php`
   - Edite as configurações do banco de dados

4. **Criar Usuário Administrador**
   ```sql
   INSERT INTO users (name, email, password, role, status, created_at) 
   VALUES ('Administrador', 'admin@empresa.com', '$2y$10$hash_da_senha', 'ADMIN', 'ATIVO', NOW());
   ```

## 📁 Estrutura do Projeto

```
app/
├── classes/           # Classes do sistema
│   ├── Auth.php      # Autenticação e autorização
│   ├── AuditLog.php  # Log de auditoria
│   └── BaseModel.php # Modelo base
├── config/           # Configurações
│   ├── config.php    # Configurações principais (não versionado)
│   └── database.php  # Classe de conexão
├── database/         # Scripts SQL
│   ├── schema.sql                # Schema base
│   ├── goals_schema.sql          # Estruturas de metas
│   ├── add_goal_type_column.sql  # Coluna goal_type em goals
│   ├── add_daily_production_table.sql # Tabela daily_production
│   ├── add_daily_delivery_table.sql   # Tabela daily_delivery
│   └── seeds.sql                 # (Opcional) dados de exemplo
├── includes/         # Arquivos incluídos
│   ├── header.php    # Cabeçalho
│   └── footer.php    # Rodapé
├── models/           # Modelos de dados
│   ├── Equipment.php
│   ├── Front.php
│   ├── Occurrence.php
│   ├── OccurrenceType.php
│   └── User.php
├── logs/             # Logs do sistema
├── public/           # Raiz pública
│   ├── assets/       # CSS, imagens e estáticos
│   ├── uploads/      # Arquivos enviados
│   ├── index.php     # Entrada pública
│   └── router.php    # Roteador público
├── dashboard.php     # Painel principal
├── login.php         # Página de login
├── equipments.php    # Gestão de equipamentos
├── fronts.php        # Gestão de frentes
├── users.php         # Gestão de usuários
├── occurrences.php   # Gestão de ocorrências
├── reports.php       # Relatórios
├── import.php        # Importação de dados
└── install.php       # Instalador
```

## ☁️ Deploy na Hostinger

- Crie o banco de dados MySQL pelo painel e guarde `host`, `usuário`, `senha` e `nome`.
- Envie todos os arquivos do projeto para `public_html/` mantendo a estrutura.
- Garanta escrita em `config/`, `public/uploads/` e `logs/`.
- Acesse `https://seu-dominio.com/install.php` para configurar o banco e criar o administrador.
- Após a instalação, o sistema detecta automaticamente a raiz pública e serve assets corretamente.

### Observações
- Se o domínio estiver em um subcaminho (ex.: `seu-dominio.com/bdo`), a URL base é calculada dinamicamente.
- Os arquivos sensíveis de `config/` ficam protegidos por `.htaccess`.
- Em caso de erro 500, verifique logs do PHP no painel da Hostinger.

### Banco de Dados
- Hostinger fornece um host de banco específico (não use `localhost`).
- Configure `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` conforme o painel.
- Em XAMPP, prefira `DB_HOST=127.0.0.1` e `DB_PORT=3306`.

## 🔧 Configuração

### Configurações Principais

Edite o arquivo `config/config.php`:

```php
// Banco de Dados
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'appmadeplant');
define('DB_USER', 'usuario');
define('DB_PASS', 'senha');
define('BASE_URL', 'http://localhost:8000');

// Segurança
define('JWT_SECRET', 'sua_chave_secreta');
define('ENCRYPTION_KEY', 'sua_chave_criptografia');

// Sistema
define('SYSTEM_NAME', 'Sistema BDO');
define('TIMEZONE', 'America/Sao_Paulo');
```

### Configuração no XAMPP (Windows)

1. Instale o XAMPP com Apache e MySQL.
2. Clone o repositório em `C:\xampp\htdocs\app`.
3. No arquivo `apache\conf\extra\httpd-vhosts.conf`, adicione:

   ```apache
   <VirtualHost *:80>
       ServerName bdo.local
       DocumentRoot "C:/xampp/htdocs/app/public"
       <Directory "C:/xampp/htdocs/app/public">
           AllowOverride All
           Require all granted
       </Directory>
       SetEnv DB_HOST 127.0.0.1
       SetEnv DB_PORT 3306
       SetEnv DB_NAME appmadeplant
       SetEnv DB_USER root
       SetEnv DB_PASS ""
   </VirtualHost>
   ```

4. Reinicie o Apache pelo painel do XAMPP.
5. Acesse `http://bdo.local/` ou, sem VirtualHost, `http://localhost/app/login.php`.
6. No `php.ini`, certifique‑se de que:
   - `extension=pdo_mysql` está habilitada.
   - `date.timezone = "America/Sao_Paulo"` está configurado.
   - `upload_max_filesize` e `post_max_size` são compatíveis com `MAX_UPLOAD_SIZE` (por padrão 10M).

### Variáveis de ambiente (.env)

- O arquivo `.env.example` lista as variáveis usadas pelo projeto.
- Para produção ou staging, defina as variáveis no ambiente (Apache `SetEnv`, painel de hospedagem ou variáveis do sistema).
- O arquivo [`config/Database.php`](file:///c:/xampp/htdocs/app/config/Database.php) prioriza `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` e `DB_CHARSET` definidos no ambiente, ou `DATABASE_URL`, antes de usar as constantes de `config/config.php`.

### Configuração do Apache

Crie um arquivo `.htaccess` na raiz:

```apache
RewriteEngine On

# Redirecionar para HTTPS (opcional)
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Proteger arquivos sensíveis
<Files "*.sql">
    Require all denied
</Files>

<Files "*.log">
    Require all denied
</Files>

# Configurações de segurança
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
```

## 📊 Uso do Sistema

### Primeiro Acesso

1. **Login como Administrador**
   - Use as credenciais criadas na instalação
   - Acesse: `http://seudominio.com/bdo/login.php`

2. **Configuração Inicial**
   - Criar metas de Produção e/ou Entrega.
   - Lançar produção diária em `production-entry.php`.
   - Lançar entrega diária em `delivery-entry.php`.
   - Acompanhar eficiência em `goals-production.php` e `goals-delivery.php`.

## 📜 Licença

Este projeto está licenciado sob a licença MIT. Veja `LICENSE` para mais detalhes.
   - Cadastre as frentes de serviço
   - Defina os tipos de ocorrência
   - Registre os equipamentos
   - Crie os usuários operadores

### Fluxo de Trabalho

1. **Operadores** registram início de atividades
2. **Líderes** monitoram e validam ocorrências
3. **Supervisores** acompanham relatórios
4. **Administradores** gerenciam todo o sistema

### Importação de Dados

1. **Acesse**: Importação de Dados
2. **Baixe** os templates CSV
3. **Preencha** com seus dados
4. **Importe** os arquivos

## 📈 Relatórios Disponíveis

### Relatório de Ocorrências
- Filtros por período, equipamento, operador
- Detalhes de todas as atividades
- Exportação para CSV

### Relatório de Uso de Equipamentos
- Horas de produção, manutenção e parada
- Cálculo de disponibilidade
- Análise por frente de serviço

### Relatório de Produtividade
- Eficiência por equipamento
- Comparativo entre períodos
- Indicadores de performance

### Relatório de Manutenção
- Histórico de manutenções
- Tempo médio de reparo
- Planejamento preventivo

## 🔒 Segurança

### Medidas Implementadas
- Autenticação baseada em sessões
- Controle de acesso por roles (RBAC)
- Validação de entrada de dados
- Proteção contra SQL Injection
- Proteção contra XSS
- Log de auditoria completo

### Recomendações
- Use HTTPS em produção
- Mantenha o PHP atualizado
- Configure backups regulares
- Monitore logs de acesso
- Use senhas fortes

## 🐛 Solução de Problemas

### Problemas Comuns

**Erro de Conexão com Banco**
```
Verifique se o MySQL está rodando.
Confirme DB_HOST e DB_PORT.
Em XAMPP, use DB_HOST=127.0.0.1 e DB_PORT=3306.
```

**Permissões de Arquivo**
```bash
chmod 755 config/
chmod 755 public/uploads/
chmod 755 logs/
```

**Erro 500**
```
Verifique os logs do Apache/PHP
Ative display_errors temporariamente
```

### Verificações de Saúde do Ambiente

Use os scripts abaixo para validar configuração e carregamento de arquivos:

```bash
# Verifica arquivos críticos, permissões e .htaccess
php scripts/precheck.php

# Verifica o funcionamento do autoload de classes
php tests/autoload_check.php

# Testes de autenticação
php tests/run.php

# Testes de distribuição de metas
php tests/goals_distribution.php
```

### Logs do Sistema

Os logs são armazenados em:
- `logs/system.log` - Logs gerais
- `logs/error.log` - Logs de erro
- Tabela `audit_logs` - Log de auditoria

## 📞 Suporte

### Documentação Técnica
- Comentários no código fonte
- Schema do banco documentado
- APIs documentadas inline

### Manutenção
- Backup regular do banco de dados
- Limpeza periódica de logs antigos
- Atualização de dependências

## 📄 Licença

Este sistema foi desenvolvido para uso interno. Todos os direitos reservados.

## 🔄 Atualizações

### Versão 1.0.0
- Sistema completo de controle de maquinários
- Interface responsiva com Tailwind CSS
- Sistema de relatórios avançado
- Importação de dados via CSV
- Sistema de auditoria completo

---

**Desenvolvido com ❤️ para otimizar o controle de maquinários**
