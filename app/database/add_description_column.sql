-- Script para adicionar a coluna description na tabela fronts
-- Execute este script no seu banco de dados MySQL

USE bdo_system;

-- Adicionar a coluna description na tabela fronts
ALTER TABLE fronts 
ADD COLUMN description TEXT NULL 
AFTER name;

-- Verificar se a coluna foi adicionada corretamente
DESCRIBE fronts;