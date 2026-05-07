USE appmadeplant;

ALTER TABLE job_candidates
  MODIFY COLUMN stage VARCHAR(32) NOT NULL DEFAULT 'RECEBIDO';

UPDATE job_candidates
   SET stage = 'RECEBIDO'
 WHERE stage IS NULL OR stage = '';

