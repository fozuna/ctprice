# Configuração por Ambiente

## Objetivo
Evitar sobrescrita do `config/config.php` em produção a cada deploy e padronizar a configuração por ambiente.

## Arquivos
- `config/config.php` (ignorado pelo Git): arquivo real usado em runtime.
- `config/config.php.example`: exemplo para desenvolvimento local.
- `config/config.staging.php.example`: exemplo para staging.
- `config/config.production.php.example`: exemplo para produção.

## Como usar
### Desenvolvimento
1. Copie o exemplo:
   - Linux/macOS: `cp config/config.php.example config/config.php`
   - Windows: copie o arquivo manualmente.
2. Ajuste `DB_*`, `BASE_URL` e chaves.

### Staging
1. Crie `config/config.staging.php` a partir do exemplo.
2. Primeiro deploy:
   - Linux/macOS: `bash scripts/deploy-staging.sh`
   - Windows: `powershell -ExecutionPolicy Bypass -File scripts/deploy-staging.ps1`
3. Nas próximas atualizações, o `config.php` será preservado.

### Produção
1. Crie `config/config.production.php` a partir do exemplo.
2. Primeiro deploy:
   - Linux/macOS: `bash scripts/deploy-production.sh`
   - Windows: `powershell -ExecutionPolicy Bypass -File scripts/deploy-production.ps1`
3. Nas próximas atualizações, o `config.php` será preservado.
4. Antes de concluir o deploy, execute `php scripts/migrate.php check`.
5. Se houver pendências, execute `php scripts/migrate.php up` e repita o `check` até receber `ok	no_pending_migrations`.

## Variáveis de Ambiente
Opcionalmente, defina:
- `DATABASE_URL` (ex.: `mysql://user:pass@host:3306/dbname?charset=utf8mb4`)
- ou `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`.

O `config/database.php` prioriza variáveis de ambiente e valida `DB_HOST`, `DB_NAME` e `DB_USER` antes de conectar.

## Validações
- Se `DB_HOST`, `DB_NAME` ou `DB_USER` estiverem ausentes, a aplicação falha com mensagem clara.
- Em `DEBUG_MODE=false`, erros não são exibidos e são enviados para `logs/php_errors.log`.

## Teste do Fluxo em Staging
1. Garanta que `config/config.php` exista no servidor de staging.
2. Execute `bash scripts/deploy-staging.sh` (ou `.ps1` no Windows).
3. Confirme que:
   - `git diff` não mostra alterações em `config/config.php`.
   - A aplicação funciona e conecta no banco de staging.
   - `php scripts/migrate.php check` retorna `ok	no_pending_migrations`.
4. Repita o teste após um novo `git pull` para confirmar preservação.

## Observações
- `config/config.php` permanece fora do versionamento e não será sobrescrito em deploys.
- O instalador (`install.php`) continua capaz de criar `config/config.php` em instalações novas.
