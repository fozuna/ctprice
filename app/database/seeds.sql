-- Seeds de exemplo para o Sistema BDO
-- Este arquivo popula dados mínimos para testar metas, produção e entrega

USE appmadeplant;

-- Usuários adicionais (senha: admin123)
INSERT INTO users (name, email, password, role_id, front_id, active)
SELECT 'Supervisor', 'supervisor@bdo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, f.id, 1
FROM fronts f WHERE f.code = 'F001'
ON DUPLICATE KEY UPDATE email = email;

-- Metas de PRODUÇÃO (jan/2025) para Frente F001 e Cliente NEOM_MAR
INSERT INTO goals (front_id, client_id, start_date, end_date, total_goal, goal_type, unit, description, created_by)
SELECT 
    f.id,
    c.id,
    '2025-01-01',
    '2025-01-31',
    3000.00,
    'production',
    'toneladas',
    'Meta de produção para janeiro/2025',
    u.id
FROM fronts f
JOIN clients c ON c.code = 'NEOM_MAR'
JOIN (SELECT id AS id FROM users ORDER BY id LIMIT 1) u
WHERE f.code = 'F001'
ON DUPLICATE KEY UPDATE total_goal = VALUES(total_goal);

-- Metas de ENTREGA (jan/2025) para Frente F001 e Cliente INPASA_DOU
INSERT INTO goals (front_id, client_id, start_date, end_date, total_goal, goal_type, unit, description, created_by)
SELECT 
    f.id,
    c.id,
    '2025-01-01',
    '2025-01-31',
    2000.00,
    'delivery',
    'toneladas',
    'Meta de entrega para janeiro/2025',
    u.id
FROM fronts f
JOIN clients c ON c.code = 'INPASA_DOU'
JOIN (SELECT id AS id FROM users ORDER BY id LIMIT 1) u
WHERE f.code = 'F001'
ON DUPLICATE KEY UPDATE total_goal = VALUES(total_goal);

-- Daily goals simplificados (primeira semana de jan/2025) para a meta de produção
INSERT INTO daily_goals (goal_id, goal_date, week_number, week_start_date, daily_goal, weekly_goal, monthly_goal)
SELECT 
    g.id,
    dt.goal_date,
    1,
    '2024-12-30',
    CASE WHEN DAYOFWEEK(dt.goal_date) = 1 THEN 0 ELSE 100 END, -- domingos = 0 para produção
    600.00, -- aprox 6 dias úteis x 100
    3000.00
FROM goals g
JOIN (
    SELECT '2025-01-01' AS goal_date UNION ALL
    SELECT '2025-01-02' UNION ALL
    SELECT '2025-01-03' UNION ALL
    SELECT '2025-01-04' UNION ALL
    SELECT '2025-01-05' UNION ALL
    SELECT '2025-01-06' UNION ALL
    SELECT '2025-01-07'
) dt
WHERE g.goal_type = 'production'
  AND g.start_date <= '2025-01-07' AND g.end_date >= '2025-01-01'
ON DUPLICATE KEY UPDATE daily_goal = VALUES(daily_goal);

-- Daily goals simplificados (primeira semana de jan/2025) para a meta de entrega
INSERT INTO daily_goals (goal_id, goal_date, week_number, week_start_date, daily_goal, weekly_goal, monthly_goal)
SELECT 
    g.id,
    dt.goal_date,
    1,
    '2024-12-30',
    70.00,
    490.00,
    2000.00
FROM goals g
JOIN (
    SELECT '2025-01-01' AS goal_date UNION ALL
    SELECT '2025-01-02' UNION ALL
    SELECT '2025-01-03' UNION ALL
    SELECT '2025-01-04' UNION ALL
    SELECT '2025-01-05' UNION ALL
    SELECT '2025-01-06' UNION ALL
    SELECT '2025-01-07'
) dt
WHERE g.goal_type = 'delivery'
  AND g.start_date <= '2025-01-07' AND g.end_date >= '2025-01-01'
ON DUPLICATE KEY UPDATE daily_goal = VALUES(daily_goal);

-- Produção diária de exemplo na primeira semana de jan/2025
INSERT INTO daily_production (front_id, client_id, production_date, produced_value, unit, notes, created_by)
SELECT 
    f.id,
    c.id,
    dp.production_date,
    dp.produced_value,
    'toneladas',
    'Produção registrada (exemplo)',
    u.id
FROM fronts f
JOIN clients c ON c.code = 'NEOM_MAR'
JOIN (SELECT id AS id FROM users ORDER BY id LIMIT 1) u
JOIN (
    SELECT '2025-01-01' AS production_date, 95.00 AS produced_value UNION ALL
    SELECT '2025-01-02', 102.00 UNION ALL
    SELECT '2025-01-03', 88.00 UNION ALL
    SELECT '2025-01-04', 110.00 UNION ALL
    SELECT '2025-01-05', 0.00  UNION ALL
    SELECT '2025-01-06', 120.00 UNION ALL
    SELECT '2025-01-07', 99.00
) dp
WHERE f.code = 'F001'
ON DUPLICATE KEY UPDATE produced_value = VALUES(produced_value);

-- Entrega diária de exemplo na primeira semana de jan/2025
INSERT INTO daily_delivery (front_id, client_id, delivery_date, delivered_value, unit, notes, created_by)
SELECT 
    f.id,
    c.id,
    dd.delivery_date,
    dd.delivered_value,
    'toneladas',
    'Entrega registrada (exemplo)',
    u.id
FROM fronts f
JOIN clients c ON c.code = 'INPASA_DOU'
JOIN (SELECT id AS id FROM users ORDER BY id LIMIT 1) u
JOIN (
    SELECT '2025-01-01' AS delivery_date, 60.00 AS delivered_value UNION ALL
    SELECT '2025-01-02', 72.00 UNION ALL
    SELECT '2025-01-03', 65.50 UNION ALL
    SELECT '2025-01-04', 80.00 UNION ALL
    SELECT '2025-01-05', 50.00 UNION ALL
    SELECT '2025-01-06', 90.00 UNION ALL
    SELECT '2025-01-07', 72.00
) dd
WHERE f.code = 'F001'
ON DUPLICATE KEY UPDATE delivered_value = VALUES(delivered_value);

-- Observação: estes dados são fictícios e servem apenas para validação visual
-- Remova-os em produção ou substitua por dados reais