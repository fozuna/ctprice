USE appmadeplant;

ALTER TABLE job_candidates
  ADD COLUMN cpf CHAR(11) NULL AFTER phone;

ALTER TABLE job_candidates
  ADD UNIQUE INDEX uq_job_candidates_vacancy_cpf (vacancy_id, cpf),
  ADD INDEX idx_job_candidates_cpf (cpf);

