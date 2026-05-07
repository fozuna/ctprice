-- Procurement Module Schema
CREATE TABLE IF NOT EXISTS suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  razao_social VARCHAR(255) NOT NULL,
  cnpj VARCHAR(20) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  telefone VARCHAR(50) NULL,
  nome_contato VARCHAR(120) NULL,
  status TINYINT(1) NOT NULL DEFAULT 1,
  portal_password_hash VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(255) NOT NULL,
  descricao TEXT NULL,
  unidade_medida VARCHAR(20) NOT NULL,
  categoria VARCHAR(100) NULL,
  status TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rfqs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(255) NOT NULL,
  descricao TEXT NULL,
  prazo_resposta DATETIME NOT NULL,
  criado_por INT NOT NULL,
  status ENUM('aberta','encerrada','aprovada') NOT NULL DEFAULT 'aberta',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (status),
  CONSTRAINT fk_rfqs_criado_por FOREIGN KEY (criado_por) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rfq_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rfq_id INT NOT NULL,
  produto_id INT NOT NULL,
  quantidade DECIMAL(15,3) NOT NULL,
  observacoes TEXT NULL,
  CONSTRAINT fk_rfq_items_rfq FOREIGN KEY (rfq_id) REFERENCES rfqs(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_rfq_items_prod FOREIGN KEY (produto_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rfq_suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rfq_id INT NOT NULL,
  fornecedor_id INT NOT NULL,
  data_convite DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('pendente','respondeu') NOT NULL DEFAULT 'pendente',
  UNIQUE KEY uniq_invite (rfq_id, fornecedor_id),
  CONSTRAINT fk_rfq_suppliers_rfq FOREIGN KEY (rfq_id) REFERENCES rfqs(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_rfq_suppliers_supplier FOREIGN KEY (fornecedor_id) REFERENCES suppliers(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_quotes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rfq_id INT NOT NULL,
  fornecedor_id INT NOT NULL,
  data_envio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_quote (rfq_id, fornecedor_id),
  CONSTRAINT fk_quotes_rfq FOREIGN KEY (rfq_id) REFERENCES rfqs(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_quotes_supplier FOREIGN KEY (fornecedor_id) REFERENCES suppliers(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quote_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cotacao_id INT NOT NULL,
  produto_id INT NOT NULL,
  preco_unitario DECIMAL(15,4) NOT NULL,
  prazo_entrega INT NULL,
  observacoes TEXT NULL,
  CONSTRAINT fk_quote_items_quote FOREIGN KEY (cotacao_id) REFERENCES supplier_quotes(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_quote_items_prod FOREIGN KEY (produto_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
