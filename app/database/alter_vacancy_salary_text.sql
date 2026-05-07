USE appmadeplant;
ALTER TABLE job_vacancies MODIFY COLUMN salary VARCHAR(100) NULL;
UPDATE job_vacancies SET salary = 'Faixa salarial compatível com o mercado' WHERE salary IS NOT NULL;
