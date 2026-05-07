-- Script de migração: adicionar coluna goal_type na tabela goals
USE appmadeplant;

ALTER TABLE goals 
    ADD COLUMN goal_type VARCHAR(20) DEFAULT 'production' AFTER total_goal;

-- Opcional: criar índice para consultas por tipo de meta
CREATE INDEX IF NOT EXISTS idx_goals_type ON goals(goal_type);

-- Observação: execute este script uma única vez no ambiente atual.