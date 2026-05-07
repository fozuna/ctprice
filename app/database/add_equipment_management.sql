USE appmadeplant;

CREATE TABLE IF NOT EXISTS equipment_hour_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    equipment_id INT NOT NULL,
    log_timestamp DATETIME NOT NULL,
    hour_meter DECIMAL(12,2) NOT NULL,
    source VARCHAR(50) NOT NULL,
    note VARCHAR(255) NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipments(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);
CREATE INDEX idx_hour_logs_equipment_time ON equipment_hour_logs(equipment_id, log_timestamp);

CREATE TABLE IF NOT EXISTS equipment_hour_cleanup_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    performed_by INT NOT NULL,
    performed_at DATETIME NOT NULL,
    affected_equipments INT NOT NULL,
    affected_occurrences INT NOT NULL,
    affected_daily_production INT NOT NULL,
    backup_path VARCHAR(255) NULL,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (performed_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS production_rates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    equipment_id INT NOT NULL,
    unit VARCHAR(20) NOT NULL,
    rate DECIMAL(12,3) NOT NULL,
    created_by INT NOT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipments(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id),
    UNIQUE KEY unique_equipment_rate (equipment_id)
);

CREATE TABLE IF NOT EXISTS production_rate_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    equipment_id INT NOT NULL,
    unit VARCHAR(20) NOT NULL,
    rate DECIMAL(12,3) NOT NULL,
    changed_by INT NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipments(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id)
);
CREATE INDEX idx_rate_hist_equipment_time ON production_rate_history(equipment_id, changed_at);

CREATE TABLE IF NOT EXISTS equipment_allocations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    equipment_id INT NOT NULL,
    front_id INT NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    priority INT NOT NULL DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipments(id) ON DELETE CASCADE,
    FOREIGN KEY (front_id) REFERENCES fronts(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);
CREATE INDEX idx_alloc_equipment_period ON equipment_allocations(equipment_id, start_datetime, end_datetime);
CREATE INDEX idx_alloc_front_period ON equipment_allocations(front_id, start_datetime, end_datetime);

CREATE TABLE IF NOT EXISTS forecast_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    equipment_id INT NOT NULL,
    front_id INT NULL,
    efficiency_factor DECIMAL(6,4) NOT NULL DEFAULT 1.0000,
    created_by INT NOT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipments(id) ON DELETE CASCADE,
    FOREIGN KEY (front_id) REFERENCES fronts(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id),
    UNIQUE KEY unique_efficiency (equipment_id, front_id)
);

