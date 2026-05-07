CREATE TABLE IF NOT EXISTS job_candidate_stage_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  candidate_id INT NOT NULL,
  from_stage VARCHAR(32) NULL,
  to_stage VARCHAR(32) NOT NULL,
  note TEXT NOT NULL,
  interview_at DATETIME NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_candidate(created_at, candidate_id)
);

