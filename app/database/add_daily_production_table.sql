-- Tabela de produção diária por frente/cliente
USE appmadeplant;

CREATE TABLE IF NOT EXISTS daily_production (
    id INT PRIMARY KEY AUTO_INCREMENT,
    front_id INT NOT NULL,
    client_id INT NULL,
    production_date DATE NOT NULL,
    produced_value DECIMAL(12,2) NOT NULL,
    unit VARCHAR(20) DEFAULT 'toneladas',
    notes VARCHAR(255) NULL,
    created_by INT NOT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (front_id) REFERENCES fronts(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id),
    UNIQUE KEY unique_production_day (front_id, client_id, production_date)
);

-- Índices
CREATE INDEX idx_daily_production_date ON daily_production(production_date);
CREATE INDEX idx_daily_production_front_client ON daily_production(front_id, client_id);
