-- Schema CT Price - Gestão de Currículos

CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  senha_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','rh','viewer') NOT NULL DEFAULT 'viewer',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vagas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(150) NOT NULL,
  descricao TEXT NOT NULL,
  requisitos TEXT NOT NULL,
  area VARCHAR(100) DEFAULT NULL,
  local VARCHAR(100) DEFAULT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS candidaturas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vaga_id INT NOT NULL,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(120) NOT NULL,
  telefone VARCHAR(40) NOT NULL,
  cpf VARCHAR(11) NOT NULL UNIQUE,
  cargo_pretendido VARCHAR(120) NOT NULL,
  experiencia TEXT NOT NULL,
  pdf_path VARCHAR(255) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'novo',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cand_vaga FOREIGN KEY (vaga_id) REFERENCES vagas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS candidatura_historico (
  id INT AUTO_INCREMENT PRIMARY KEY,
  candidatura_id INT NOT NULL,
  status_anterior VARCHAR(30) DEFAULT NULL,
  status_novo VARCHAR(30) NOT NULL,
  observacoes TEXT DEFAULT NULL,
  usuario_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_hist_candidatura FOREIGN KEY (candidatura_id) REFERENCES candidaturas(id) ON DELETE CASCADE,
  CONSTRAINT fk_hist_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuário admin padrão (ajuste e remova em produção)
-- UPDATE este bloco após criação para definir senha com bcrypt em PHP.