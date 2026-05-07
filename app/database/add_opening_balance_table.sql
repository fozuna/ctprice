-- Tabela de saldos iniciais por frente e data
USE appmadeplant;

CREATE TABLE IF NOT EXISTS front_opening_balance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    front_id INT NOT NULL,
    balance_date DATE NOT NULL,
    opening_balance_value DECIMAL(12,2) NOT NULL,
    unit VARCHAR(20) DEFAULT 'toneladas',
    notes VARCHAR(255) NULL,
    created_by INT NOT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (front_id) REFERENCES fronts(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id),
    UNIQUE KEY uniq_front_balance (front_id, balance_date)
);

-- Índices
CREATE INDEX idx_fob_balance_date ON front_opening_balance(balance_date);

