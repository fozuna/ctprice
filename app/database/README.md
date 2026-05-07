# Scripts de Banco de Dados

Este diretório contém scripts SQL para criação e manutenção do banco `appmadeplant`.

## Ordem recomendada de execução

1. `schema.sql` — Estrutura base do sistema (tabelas principais como `users`, `fronts`, `clients`).
2. `goals_schema.sql` — Tabelas relacionadas a metas (`goals`, `daily_goals`).
3. `add_goal_type_column.sql` — Adiciona `goal_type` em `goals` (ex.: `production`, `delivery`).
4. `add_daily_production_table.sql` — Cria `daily_production` para registros de produção diária.
5. `add_daily_delivery_table.sql` — Cria `daily_delivery` para registros de entrega diária.
6. `seeds.sql` (opcional) — Dados de exemplo para facilitar testes locais.

## Convenções

- Charset padrão: `utf8mb4`.
- Chaves estrangeiras com `ON DELETE CASCADE` quando aplicável.
- Índices por data e por chaves compostas (`front_id`, `client_id`) para consultas eficientes.

## Observações

- Execute estes scripts apenas uma vez por ambiente.
- Ajuste usuários/credenciais conforme seu setup local (`mysql -u root appmadeplant < arquivo.sql`).
- Para ambientes Windows/XAMPP, o `mysql.exe` costuma estar em `C:\xampp\mysql\bin\mysql.exe`.