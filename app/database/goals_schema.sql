-- Sistema BDO - Controle de Maquinários
-- Script para adicionar tabelas de metas e clientes

USE appmadeplant;

-- Tabela de clientes/destinos
CREATE TABLE IF NOT EXISTS clients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    description TEXT NULL,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de metas principais
CREATE TABLE IF NOT EXISTS goals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    front_id INT NOT NULL,
    client_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_goal DECIMAL(12,2) NOT NULL,
    goal_type VARCHAR(20) DEFAULT 'production',
    unit VARCHAR(20) DEFAULT 'toneladas',
    description TEXT NULL,
    created_by INT NOT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (front_id) REFERENCES fronts(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Tabela de metas diárias (geradas automaticamente)
CREATE TABLE IF NOT EXISTS daily_goals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    goal_id INT NOT NULL,
    goal_date DATE NOT NULL,
    week_number INT NOT NULL,
    week_start_date DATE NOT NULL,
    daily_goal DECIMAL(12,2) NOT NULL,
    weekly_goal DECIMAL(12,2) NOT NULL,
    monthly_goal DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE,
    UNIQUE KEY unique_goal_date (goal_id, goal_date)
);

-- Inserir clientes baseados na imagem fornecida
INSERT INTO clients (name, code, description) VALUES
('NEOMILLE MARACAJU', 'NEOM_MAR', 'Cliente Neomille - Unidade Maracaju'),
('INPASA DOURADOS', 'INPASA_DOU', 'Cliente Inpasa - Unidade Dourados'),
('SECADORES', 'SECADORES', 'Cliente Secadores'),
('COAMO', 'COAMO', 'Cliente Coamo'),
('INPASA SIDROLÂNDIA', 'INPASA_SID', 'Cliente Inpasa - Unidade Sidrolândia'),
('RIO PARDO', 'RIO_PARDO', 'Cliente Rio Pardo'),
('COAMO TORAS', 'COAMO_TOR', 'Cliente Coamo - Toras');

-- Índices para otimização
CREATE INDEX idx_goals_front ON goals(front_id);
CREATE INDEX idx_goals_client ON goals(client_id);
CREATE INDEX idx_goals_dates ON goals(start_date, end_date);
CREATE INDEX idx_daily_goals_goal ON daily_goals(goal_id);
CREATE INDEX idx_daily_goals_date ON daily_goals(goal_date);
CREATE INDEX idx_daily_goals_week ON daily_goals(week_number, week_start_date);