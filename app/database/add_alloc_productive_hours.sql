USE appmadeplant;

ALTER TABLE equipment_allocations
    ADD COLUMN daily_productive_hours DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER priority;

