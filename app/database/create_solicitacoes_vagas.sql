CREATE TABLE IF NOT EXISTS solicitacoes_vagas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  area_departamento VARCHAR(120) NOT NULL,
  quantidade_vagas INT NOT NULL,
  cargo VARCHAR(120) NOT NULL,
  maquina_florestal VARCHAR(120) NULL,
  gestor_solicitante VARCHAR(120) NOT NULL,

  tipo_vaga ENUM('nova_posicao','substituicao','aumento_quadro','projeto_temporario') NOT NULL,
  nome_substituido VARCHAR(120) NULL,
  data_desligamento DATE NULL,
  motivo_saida ENUM('desligamento','promocao','transferencia','outros') NULL,
  motivo_outros VARCHAR(255) NULL,

  tipo_contratacao ENUM('clt','temporario','terceiro','pj') NOT NULL,
  salario_previsto DECIMAL(10,2) NULL,
  beneficios TEXT NULL,
  centro_custo VARCHAR(120) NOT NULL,
  previsto_orcamento ENUM('sim','nao') NOT NULL,
  justificativa_nao_previsto TEXT NULL,

  jornada_trabalho VARCHAR(120) NULL,
  escala VARCHAR(120) NULL,
  turno ENUM('diurno','noturno','misto') NOT NULL,

  escolaridade_minima VARCHAR(120) NULL,
  formacao_academica VARCHAR(120) NULL,
  experiencia TEXT NULL,
  entregas_esperadas TEXT NULL,
  competencias_tecnicas TEXT NULL,
  competencias_comportamentais TEXT NULL,
  nivel_responsabilidade ENUM('operacional','tecnico','analitico','estrategico') NOT NULL,

  data_inicio DATE NOT NULL,
  urgencia ENUM('baixa','media','alta','critica') NOT NULL,
  data_limite DATE NULL,

  lider_imediato VARCHAR(120) NOT NULL,
  rh_responsavel VARCHAR(120) NOT NULL,

  status ENUM('aberta','em_analise','aprovada','reprovada') NOT NULL DEFAULT 'em_analise',
  created_by INT NULL,

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_tipo_vaga (tipo_vaga),
  INDEX idx_tipo_contratacao (tipo_contratacao),
  INDEX idx_turno (turno),
  INDEX idx_nivel_resp (nivel_responsabilidade),
  INDEX idx_urgencia (urgencia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
