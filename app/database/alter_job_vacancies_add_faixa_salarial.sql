ALTER TABLE job_vacancies
  ADD COLUMN faixa_salarial VARCHAR(100) NOT NULL DEFAULT '' AFTER salary,
  ADD INDEX idx_job_vacancies_faixa (faixa_salarial);
