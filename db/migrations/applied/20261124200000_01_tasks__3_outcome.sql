-- Quello che si segna in una fase: esito e note, la data dell'esito (es.
-- l'approvazione del cliente), una nota per ogni voce della checklist (JSON).
ALTER TABLE `tasks` ADD COLUMN `outcome` TEXT NULL;
ALTER TABLE `tasks` ADD COLUMN `outcome_date` DATE NULL;
ALTER TABLE `tasks` ADD COLUMN `item_notes` TEXT NULL;
