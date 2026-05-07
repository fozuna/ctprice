-- Sistema BDO - Controle de Maquinários
-- Script de criação do banco de dados

CREATE DATABASE IF NOT EXISTS appmadeplant CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE appmadeplant;

-- Tabela de perfis/roles
CREATE TABLE IF NOT EXISTS roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de frentes de serviço
CREATE TABLE IF NOT EXISTS fronts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    code VARCHAR(10) NOT NULL UNIQUE,
    value_per_ton DECIMAL(10,2) DEFAULT 0.00,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de centros de custo
CREATE TABLE IF NOT EXISTS cost_centers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    code VARCHAR(50) NULL,
    parent_id INT NULL,
    department VARCHAR(120) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cc_parent FOREIGN KEY (parent_id) REFERENCES cost_centers(id) ON DELETE SET NULL ON UPDATE CASCADE
);

-- Tabela de usuários
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    functional_profile ENUM('SOLICITANTE', 'APROVADOR') NOT NULL DEFAULT 'SOLICITANTE',
    front_id INT NULL,
    cost_center_id INT NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (front_id) REFERENCES fronts(id),
    CONSTRAINT fk_users_cost_center FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- Tabela de equipamentos
CREATE TABLE IF NOT EXISTS equipments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tag VARCHAR(20) NOT NULL UNIQUE,
    description VARCHAR(200) NOT NULL,
    model VARCHAR(100),
    initial_hours DECIMAL(10,2) DEFAULT 0.00,
    current_hours DECIMAL(10,2) DEFAULT 0.00,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de tipos de ocorrência
CREATE TABLE IF NOT EXISTS occurrence_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(10) NOT NULL UNIQUE,
    description VARCHAR(200) NOT NULL,
    category ENUM('OPERACAO', 'MANUTENCAO', 'PARADA', 'APOIO') DEFAULT 'OPERACAO',
    color VARCHAR(7) DEFAULT '#3e5c76',
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de ocorrências
CREATE TABLE IF NOT EXISTS occurrences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    equipment_id INT NOT NULL,
    operator_id INT NOT NULL,
    front_id INT NOT NULL,
    occurrence_type_id INT NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NULL,
    duration_minutes INT NULL,
    start_hours DECIMAL(10,2) NULL,
    end_hours DECIMAL(10,2) NULL,
    hours_worked DECIMAL(10,2) NULL,
    observations TEXT,
    created_by INT NOT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipments(id),
    FOREIGN KEY (operator_id) REFERENCES users(id),
    FOREIGN KEY (front_id) REFERENCES fronts(id),
    FOREIGN KEY (occurrence_type_id) REFERENCES occurrence_types(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Tabela de logs de alterações
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    table_name VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    action ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Tabela de mobilizações de equipamentos
CREATE TABLE IF NOT EXISTS equipment_mobilizations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    equipment_id INT NOT NULL,
    from_front_id INT NULL,
    to_front_id INT NOT NULL,
    mobilization_date DATE NOT NULL,
    mobilization_time TIME DEFAULT '08:00:00',
    requested_by INT NOT NULL,
    approved_by INT NULL,
    reason VARCHAR(500) NOT NULL,
    observations TEXT NULL,
    transport_type ENUM('PRANCHA', 'PROPRIO', 'GUINCHO', 'OUTRO') DEFAULT 'PRANCHA',
    transport_company VARCHAR(100) NULL,
    transport_cost DECIMAL(10,2) NULL,
    departure_datetime DATETIME NULL,
    arrival_datetime DATETIME NULL,
    status ENUM('SOLICITADA', 'APROVADA', 'EM_TRANSITO', 'CONCLUIDA', 'CANCELADA') DEFAULT 'SOLICITADA',
    created_by INT NOT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipments(id),
    FOREIGN KEY (from_front_id) REFERENCES fronts(id),
    FOREIGN KEY (to_front_id) REFERENCES fronts(id),
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Inserir dados iniciais dos perfis
INSERT INTO roles (id, name, description) VALUES
(1, 'Administrador', 'Acesso total ao sistema'),
(2, 'Supervisor', 'Relatórios e gestão de frentes'),
(3, 'Líder', 'Cadastros e lançamentos'),
(4, 'Operador', 'Lançar ocorrências'),
(5, 'Coordenador de RH', 'Gestão dos módulos de RH'),
(6, 'Compras', 'Gestão do módulo de compras');

INSERT INTO cost_centers (id, name, code, department, active) VALUES
(1, 'Centro de Custo Geral', 'GERAL', 'Administrativo', 1);

-- Inserir frentes de serviço
INSERT INTO fronts (name, code, value_per_ton) VALUES
('FAZENDA CANAÃ', 'F001', 85.00),
('FAZENDA MORRINHO', 'F002', 55.00),
('FAZENDA POMBAL', 'F003', 120.00),
('FAZENDA SANTA ROSA ARAMIS', 'F004', 120.00),
('FAZENDA SANTA ROSA', 'F005', 120.00),
('FAZENDA SANTO ANTÔNIO', 'F006', 80.00),
('FAZENDA SANTA ANA', 'F007', 80.00),
('FAZENDA NOVA ARVORE GRANDE', 'F008', 99.62),
('FAZENDA GUANANDI', 'F009', 0.00),
('FAZENDA CAMPO BOM', 'F010', 0.00),
('FAZENDA APARECIDINHA', 'F011', 150.00),
('FAZENDA BOA ESPERANÇA', 'F012', 0.00),
('FAZENDA VITORIA', 'F013', 0.00),
('CD DOURADOS', 'F014', 0.00);

-- Inserir equipamentos
INSERT INTO equipments (tag, description, model) VALUES
('MC213', 'CARREGADEIRA L60 MC213', 'L60'),
('MC215', 'CARREGADEIRA L60 MC215', 'L60'),
('MC216', 'ESCAVADEIRA HIDRAULICA 160G LC MC216', '160G LC'),
('MC217', 'SKIDDER 648L-II MC217', '648L-II'),
('MC219', 'FELLER BUNCHER 643L II MC219', '643L II'),
('MC221', 'FELLER BUNCHER 643L II MC221', '643L II'),
('MC222', 'ESCAVADEIRA HIDRÁULICA EC140DL MC222', 'EC140DL'),
('MC223', 'SKIDDER 648L-II MC223', '648L-II'),
('MC224', 'PICADOR PETERSON - 4310B MC224', '4310B'),
('MC226', 'PICADOR PETERSON-4310 B MC226', '4310 B'),
('MC227', 'PICADOR BANDIT 3680 MC227', '3680'),
('MC228', 'FELLER BUNCHER 803 M MC228', '803 M'),
('MC229', 'SKIDDER 768L-II MC229', '768L-II'),
('MC230', 'CARREGADEIRA L60 MC230', 'L60'),
('MC231', 'PICADOR BANDIT 3680 MC231', '3680'),
('MC232', 'PICADOR PETERSON - 4310B MC232', '4310B'),
('MC233', 'SKIDDER 768L-II MC233', '768L-II'),
('MC235', 'ESCAVADEIRA HIDRAULICA 160G LC MC235', '160G LC'),
('MC238', 'FORWARDERS MC238', 'FORWARDERS'),
('MC241', 'ESCAVADEIRA HIDRAULICA 160G LC MC241', '160G LC'),
('MC242', 'PENEIRA ROTATIVA CRIBUS 3800 MC242', 'CRIBUS 3800'),
('MC243', 'PÁ CARREGADEIRA 444G MC243', '444G'),
('MC244', 'ESCAVADEIRA HIDRAULICA 160G LC MC244', '160G LC'),
('MC245', 'PICADOR FLORESTAL BRUNO TITAN MC245', 'BRUNO TITAN'),
('MC248', 'PÁ CARREGADEIRA 524K MC248', '524K'),
('MC249', 'ESCAVADEIRA HIDRAULICA 160G LC MC249', '160G LC'),
('MC250', 'PICADOR THOR 450/600 X 1000 MC250', 'THOR 450/600 X 1000'),
('MC251', 'SKIDDER 648L-II MC251', '648L-II'),
('MC252', 'FELLER BUNCHER 643L II MC252', '643L II'),
('MC253', 'ESCAVADEIRA HIDRAULICA 210G MC253', '210G'),
('MC254', 'PÁ CARREGADEIRA 524K MC254', '524K'),
('MC255', 'PICADOR PBFT 600/800 X 1200 D TITAN MC255', 'PBFT 600/800 X 1200 D TITAN'),
('MC256', 'MAQUINA FLORESTAL 2144G MC256', '2144G');

-- Inserir tipos de ocorrência
INSERT INTO occurrence_types (id, code, description, category, color) VALUES
(1, 'ABAST', 'ABASTECIMENTO', 'PARADA', '#f59e0b'),
(2, 'FCOMB', 'FALTA DE COMBUSTIVEL', 'PARADA', '#ef4444'),
(3, 'FFREN', 'FALTA DE FRENTE DE SERVIÇO', 'PARADA', '#ef4444'),
(4, 'FOPER', 'FALTA DE OPERADOR', 'PARADA', '#ef4444'),
(5, 'REFEI', 'REFEIÇÃO', 'PARADA', '#8b5cf6'),
(6, 'LAVAG', 'LAVAGEM DO EQUIPAMENTO', 'MANUTENCAO', '#06b6d4'),
(7, 'LUBRI', 'LUBRIFICAÇÃO DO EQUIPAMENTO', 'MANUTENCAO', '#06b6d4'),
(8, 'MCORR', 'MANUTENÇÃO CORRETIVA', 'MANUTENCAO', '#dc2626'),
(9, 'MPREV', 'MANUTENÇÃO PREVENTIVA', 'MANUTENCAO', '#16a34a'),
(10, 'OPERA', 'EM OPERAÇÃO', 'OPERACAO', '#22c55e'),
(11, 'MTEMP', 'MAU TEMPO', 'PARADA', '#6b7280'),
(12, 'BORR', 'BORRACHARIA (PNEU FURADO OU OUTROS)', 'MANUTENCAO', '#f97316'),
(13, 'REVEZ', 'REVEZAMENTO DE OPERADOR', 'PARADA', '#8b5cf6'),
(14, 'TRANS', 'TRANSPORTE NA PRANCHA, MUDANÇA DE FRENTE', 'PARADA', '#3b82f6'),
(15, 'TFACA', 'TROCAR FACAS', 'MANUTENCAO', '#f59e0b'),
(16, 'APROG', 'AGUARDANDO PROGRAMAÇÃO DO LIDER', 'PARADA', '#ef4444'),
(17, 'ACARR', 'AGUARDANDO EQUIPAMENTO DE TRANSPORTE (CARRETAS)', 'PARADA', '#ef4444'),
(18, 'AGRUA', 'AGUARDANDO GRUA PARA ALIMENTAÇÃO', 'PARADA', '#ef4444'),
(19, 'APICA', 'AGUARDANDO PICADOR', 'PARADA', '#ef4444'),
(20, 'AMARR', 'AGUARDANDO MADEIRA ARRASTADA', 'PARADA', '#ef4444'),
(21, 'AMCOL', 'AGUARDANDO MADEIRA COLIDA', 'PARADA', '#ef4444'),
(22, 'ATALIB', 'AGUARDANDO TALHÃO LIBERADO', 'PARADA', '#ef4444'),
(23, 'APMAN', 'APOIO A MANUTENÇÃO', 'APOIO', '#8b5cf6'),
(24, 'APINF', 'APOIO A INFRAESTRUTURA (MANUTENÇÃO DE ESTRADA)', 'APOIO', '#8b5cf6');

-- Criar usuário administrador padrão (senha: admin123)
INSERT INTO users (name, email, password, role_id, functional_profile, cost_center_id) VALUES
('Administrador', 'admin@bdo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'APROVADOR', 1);

-- Índices para otimização
CREATE INDEX idx_occurrences_equipment ON occurrences(equipment_id);
CREATE INDEX idx_occurrences_operator ON occurrences(operator_id);
CREATE INDEX idx_occurrences_front ON occurrences(front_id);
CREATE INDEX idx_occurrences_type ON occurrences(occurrence_type_id);
CREATE INDEX idx_occurrences_datetime ON occurrences(start_datetime, end_datetime);
CREATE INDEX idx_audit_logs_table_record ON audit_logs(table_name, record_id);
CREATE INDEX idx_mobilizations_equipment ON equipment_mobilizations(equipment_id);
CREATE INDEX idx_mobilizations_from_front ON equipment_mobilizations(from_front_id);
CREATE INDEX idx_mobilizations_to_front ON equipment_mobilizations(to_front_id);
CREATE INDEX idx_mobilizations_date ON equipment_mobilizations(mobilization_date);
CREATE INDEX idx_mobilizations_status ON equipment_mobilizations(status);
CREATE INDEX idx_cost_centers_code ON cost_centers(code);
CREATE INDEX idx_users_cost_center_id ON users(cost_center_id);
CREATE INDEX idx_users_functional_profile ON users(functional_profile);
