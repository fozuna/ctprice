# Normalização do banco para o novo site (tabelas `site_*`)

## Objetivo

Criar um conjunto de tabelas **isoladas** (prefixo obrigatório `site_`) para o novo projeto do site, **sem depender** e **sem tocar** nas tabelas existentes do sistema de RH em produção.

## Garantias de isolamento

- Todas as tabelas novas usam o prefixo `site_`.
- Não há *foreign keys* apontando para tabelas do RH (ex.: `users`, `job_*`, `cost_centers`, etc.).
- O rollback proposto remove **apenas** tabelas `site_*`.

## Estrutura criada

- `site_assets`: normaliza URLs/imagens/arquivos usados por banners, notícias, parceiros e depoimentos.
- `site_banners`: banners do site (FK para `site_assets`).
- `site_services`: serviços do site.
- `site_testimonials`: depoimentos (FK opcional para `site_assets`).
- `site_partners`: parceiros (FK para `site_assets`).
- `site_news_posts`: posts/notícias (slug único; FK opcional para `site_assets`).
- `site_news_categories` e `site_news_post_categories`: categorização N:N de notícias.
- `site_tags` e `site_news_post_tags`: tags N:N de notícias.
- `site_settings`: configurações do site (chave/valor).

## Migração e rollback

### Opção 1: migration PHP (reversível)

- Migration: `app/database/migrations/20260506130000_create_site_schema.php`
- `up()`: cria as tabelas `site_*` e seus índices/constraints.
- `down()`: executa `DROP TABLE IF EXISTS` apenas nas `site_*` (ordem segura).

### Opção 2: SQL puro

- Criação: `app/database/site_schema.sql`
- Rollback: `app/database/site_schema_rollback.sql`

## Compatibilidade com legado (opcional)

A migration tenta copiar dados de tabelas legadas **sem prefixo** (`banners`, `services`, `testimonials`, `partners`, `news`) **somente se existirem** no mesmo schema e se as tabelas `site_*` estiverem vazias. A cópia cria registros em `site_assets` e referencia seus IDs.

## Notas de performance

- Índices compostos por `(active, sort_order, id)` foram adicionados nas listas do site.
- `site_news_posts.slug` é `UNIQUE`.
- `published_at` e `(status, active)` são indexados em `site_news_posts`.

