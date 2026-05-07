-- Tabela de entrega diária por frente/cliente
USE appmadeplant;

CREATE TABLE IF NOT EXISTS daily_delivery (
    id INT PRIMARY KEY AUTO_INCREMENT,
    front_id INT NOT NULL,
    client_id INT NOT NULL,
    delivery_date DATE NOT NULL,
    delivered_value DECIMAL(12,2) NOT NULL,
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
    UNIQUE KEY unique_delivery_day (front_id, client_id, delivery_date)
);

-- Índices
CREATE INDEX idx_daily_delivery_date ON daily_delivery(delivery_date);
CREATE INDEX idx_daily_delivery_front_client ON daily_delivery(front_id, client_id);