# Deploy para Hostinger (base oficial)

## Requisitos

- PHP 8.1+ com `pdo_mysql`, `mbstring`, `intl`, `openssl`
- Apache com `mod_rewrite` habilitado
- MySQL 5.7+ ou MariaDB 10.3+

## Estrutura de pastas

```
public_html/
  ├── public/
  │   ├── assets/
  │   └── router.php
  ├── config/
  ├── includes/
  ├── classes/, models/, database/
  ├── index.php, router.php, install.php, login.php, ...
```

## Passo a passo

1. Criar banco no painel
   - Anote `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`.

2. Upload para `public_html/`
   - Faça upload de TODO o projeto mantendo a estrutura de pastas.

3. Configuração
   - Crie `config/config.php` com:
   ```php
   <?php
   session_start();
   define('DB_HOST', 'seu-host');
   define('DB_PORT', '3306');
   define('DB_NAME', 'sua-base');
   define('DB_USER', 'usuario');
   define('DB_PASS', 'senha');
   define('DB_CHARSET', 'utf8mb4');
   define('SITE_NAME', 'Madeplant Florestal');
   define('DEBUG_MODE', false);
   ```

4. Migração da base oficial
   - Se já existe base oficial, apenas aponte `config.php` e NÃO rode `install.php`.
   - Se é instalação nova:
     - Acesse `https://seu-dominio.com/install.php` e siga as etapas.
     - Ou importe manualmente:
       - `database/schema.sql`
       - Em seguida, aplique migrações necessárias: `add_*.sql`, `goals_schema.sql`.

5. Permissões
   - Habilite escrita em `config/`, `public/uploads/` e `logs/`.

6. Verificações
   - Abra `https://seu-dominio.com/login.php`.
   - Se falhar login, veja `logs/error.log` (criado automaticamente em erros).

## Notas

- O sistema detecta subcaminhos e serve assets via `includes/helpers.php` (`asset_url`, `base_url`).
- `.htaccess` já roteia para `router.php` e protege arquivos sensíveis.
- Em Hostinger, use o host de banco informado no painel (não `localhost`).

