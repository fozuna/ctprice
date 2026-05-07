USE appmadeplant;

CREATE TABLE IF NOT EXISTS stages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL,
  name VARCHAR(80) NOT NULL,
  color VARCHAR(16) NULL,
  position INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_stages_code (code)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO stages (code, name, color, position, active) VALUES
('RECEBIDO', 'Recebidos', '#6b7280', 10, 1),
('IA', 'Analisados por IA', '#3b82f6', 20, 1),
('RH', 'Analisados RH', '#6366f1', 30, 1),
('REPROV_PESQ', 'Reprovados na Pesquisa', '#a855f7', 35, 1),
('ENTREVISTA', 'Entrevistas Marcadas', '#f59e0b', 40, 1),
('ENT_AGENDADA', 'Entrevista Agendada', '#f59e0b', 42, 1),
('ENT_CONFIRMADA', 'Entrevista Confirmada', '#10b981', 44, 1),
('POS_ENTREVISTA', 'Pós-Entrevista', '#3b82f6', 46, 1),
('REPROV_ENT', 'Reprovados na Entrevista', '#fb7185', 70, 1),
('DISPENSADO', 'Dispensados', '#ef4444', 90, 1),
('TALENTOS', 'Banco de Talentos', '#14b8a6', 80, 1),
('CONTRATADO', 'Contratados', '#10b981', 100, 1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  color = VALUES(color),
  position = VALUES(position),
  active = VALUES(active);

ALTER TABLE job_candidates
  ADD COLUMN stage_id INT NULL;

UPDATE job_candidates c
JOIN stages s ON s.code COLLATE utf8mb4_general_ci = c.stage COLLATE utf8mb4_general_ci
   SET c.stage_id = s.id
 WHERE c.stage_id IS NULL;

UPDATE job_candidates c
JOIN stages s ON s.code = 'RECEBIDO'
   SET c.stage_id = s.id,
       c.stage = 'RECEBIDO'
 WHERE c.stage_id IS NULL;

ALTER TABLE job_candidates
  MODIFY COLUMN stage_id INT NOT NULL;

ALTER TABLE job_candidates
  ADD INDEX idx_job_candidates_stage_id (stage_id);

ALTER TABLE job_candidates
  ADD CONSTRAINT fk_job_candidates_stage_id
    FOREIGN KEY (stage_id) REFERENCES stages(id);

CREATE TABLE IF NOT EXISTS job_candidate_stage_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  candidate_id INT NOT NULL,
  from_stage_id INT NULL,
  to_stage_id INT NULL,
  from_stage VARCHAR(32) NULL,
  to_stage VARCHAR(32) NULL,
  note TEXT NOT NULL,
  interview_at DATETIME NULL,
  interview_link VARCHAR(255) NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_candidate(created_at, candidate_id)
);

ALTER TABLE job_candidate_stage_logs
  ADD COLUMN from_stage_id INT NULL,
  ADD COLUMN to_stage_id INT NULL;

UPDATE job_candidate_stage_logs l
LEFT JOIN stages fs ON fs.code COLLATE utf8mb4_general_ci = l.from_stage COLLATE utf8mb4_general_ci
LEFT JOIN stages ts ON ts.code COLLATE utf8mb4_general_ci = l.to_stage COLLATE utf8mb4_general_ci
   SET l.from_stage_id = fs.id,
       l.to_stage_id = ts.id
 WHERE l.from_stage_id IS NULL OR l.to_stage_id IS NULL;

ALTER TABLE job_candidate_stage_logs
  ADD INDEX idx_stage_ids (candidate_id, created_at, to_stage_id);
