# Operações de banco com isolamento estrito em `ctprice`

## Objetivo

Executar alterações **somente** no banco configurado como `ctprice` no `.env`, com validação do contexto antes de qualquer comando SQL e bloqueio de referências a outros schemas.

## Executor

- Script: `app/scripts/ctprice_guarded_sql.php`
- Log: `app/logs/ctprice_db_ops.jsonl` (JSON Lines)

### Regras de segurança

- Antes de executar qualquer SQL, valida `SELECT DATABASE()` e compara com `--expect-db`.
- Bloqueia `USE` para qualquer banco diferente do esperado.
- Bloqueia referências explícitas a `information_schema`, `mysql`, `performance_schema`, `sys` e `appmadeplant`.
- Bloqueia criação de `TRIGGER/PROCEDURE/FUNCTION/EVENT`.

## Aplicação do schema `site_*`

- Criação e cópia inicial: `database/ctprice_site_schema.sql`
- Cópia de dados legados (opcional): `database/ctprice_site_copy_from_legacy.sql`
- Rollback: `database/ctprice_site_schema_rollback.sql`

## Código do site

Os models do site foram ajustados para consumir exclusivamente `site_*`:

- `app/models/Banner.php` → `site_banners` + `site_assets`
- `app/models/Service.php` → `site_services`
- `app/models/Testimonial.php` → `site_testimonials` + `site_assets`
- `app/models/Partner.php` → `site_partners` + `site_assets`
- `app/models/News.php` → `site_news_posts` + `site_assets`

## Exemplos

Dry-run (não executa, apenas loga):

```bash
php app/scripts/ctprice_guarded_sql.php --file database/ctprice_site_schema.sql --expect-db ctprice
```

Aplicar (executa):

```bash
php app/scripts/ctprice_guarded_sql.php --file database/ctprice_site_schema.sql --expect-db ctprice --apply
```

Bootstrap (cria o banco `ctprice` se não existir, então executa):

```bash
php app/scripts/ctprice_guarded_sql.php --file database/ctprice_site_schema.sql --expect-db ctprice --db ctprice --bootstrap-db --apply
```

Rollback (executa):

```bash
php app/scripts/ctprice_guarded_sql.php --file database/ctprice_site_schema_rollback.sql --expect-db ctprice --apply
```
