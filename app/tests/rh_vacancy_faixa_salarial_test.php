<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
$db = Database::getInstance()->getConnection();
echo "Testing faixa_salarial create/update/read...\n";
try {
  // Ensure column
  $db->exec("ALTER TABLE job_vacancies ADD COLUMN faixa_salarial VARCHAR(100) NOT NULL DEFAULT ''");
} catch (Throwable $e) { /* ignore */ }
try {
  $db->exec("DELETE FROM job_vacancies");
  $db->prepare("INSERT INTO job_vacancies (title, status, faixa_salarial) VALUES ('Teste 1','ATIVA','A combinar')")->execute();
  $db->prepare("INSERT INTO job_vacancies (title, status, faixa_salarial) VALUES ('Teste 2','ATIVA','Nível Pleno')")->execute();
  $faixa = $db->query("SELECT faixa_salarial FROM job_vacancies WHERE title='Teste 2'")->fetchColumn();
  echo "faixa: $faixa\n";
  $db->prepare("UPDATE job_vacancies SET faixa_salarial='Salário competitivo' WHERE title='Teste 2'")->execute();
  $faixa2 = $db->query("SELECT faixa_salarial FROM job_vacancies WHERE title='Teste 2'")->fetchColumn();
  echo "faixa2: $faixa2\n";
  exit($faixa==='Nível Pleno' && $faixa2==='Salário competitivo' ? 0 : 1);
} catch (Throwable $e) { echo "Error: ".$e->getMessage()."\n"; exit(1); }
