-- Tornar client_id opcional para metas de produção
USE appmadeplant;

ALTER TABLE goals 
    MODIFY COLUMN client_id INT NULL;

-- Índice permanece válido; FKs aceitam NULL
