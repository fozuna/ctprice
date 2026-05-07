-- Script para criar tabela fases e relacionar com equipamentos
-- Criado para implementar as fases dos equipamentos

-- 1. Criar tabela fases
CREATE TABLE IF NOT EXISTS fases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(50) NOT NULL UNIQUE,
    descricao TEXT NULL,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. Inserir as fases especificadas
INSERT INTO fases (nome) VALUES 
('Expedição'),
('Aux Picagem'),
('Arraste'),
('Colheita'),
('Picagem'),
('Enfardadeira'),
('Peneira'),
('Harvester');

-- 3. Adicionar coluna fase_id na tabela equipments
ALTER TABLE equipments ADD COLUMN fase_id INT NULL;

-- 4. Adicionar foreign key constraint
ALTER TABLE equipments ADD CONSTRAINT fk_equipments_fase 
    FOREIGN KEY (fase_id) REFERENCES fases(id) ON DELETE SET NULL;

-- 5. Atualizar equipamentos com suas respectivas fases
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Expedição') WHERE tag = 'MC213';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Expedição') WHERE tag = 'MC215';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Aux Picagem') WHERE tag = 'MC216';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Arraste') WHERE tag = 'MC217';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Colheita') WHERE tag = 'MC219';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Colheita') WHERE tag = 'MC221';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Aux Picagem') WHERE tag = 'MC222';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Arraste') WHERE tag = 'MC223';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Picagem') WHERE tag = 'MC224';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Picagem') WHERE tag = 'MC226';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Picagem') WHERE tag = 'MC227';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Colheita') WHERE tag = 'MC228';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Arraste') WHERE tag = 'MC229';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Expedição') WHERE tag = 'MC230';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Picagem') WHERE tag = 'MC231';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Picagem') WHERE tag = 'MC232';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Arraste') WHERE tag = 'MC233';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Aux Picagem') WHERE tag = 'MC235';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Enfardadeira') WHERE tag = 'MC238';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Aux Picagem') WHERE tag = 'MC241';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Peneira') WHERE tag = 'MC242';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Expedição') WHERE tag = 'MC243';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Aux Picagem') WHERE tag = 'MC244';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Picagem') WHERE tag = 'MC245';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Expedição') WHERE tag = 'MC248';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Aux Picagem') WHERE tag = 'MC249';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Picagem') WHERE tag = 'MC250';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Arraste') WHERE tag = 'MC251';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Colheita') WHERE tag = 'MC252';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Aux Picagem') WHERE tag = 'MC253';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Expedição') WHERE tag = 'MC254';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Picagem') WHERE tag = 'MC255';
UPDATE equipments SET fase_id = (SELECT id FROM fases WHERE nome = 'Harvester') WHERE tag = 'MC256';