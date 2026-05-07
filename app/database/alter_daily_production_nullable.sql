-- Tornar client_id opcional em daily_production
USE appmadeplant;
ALTER TABLE daily_production 
    MODIFY client_id INT NULL;

-- Observação: o índice único existente (front_id, client_id, production_date) permanece.
-- Quando client_id for NULL, a aplicação fará upsert manual para evitar duplicidades.

