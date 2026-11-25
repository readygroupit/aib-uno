-- I file caricati da una fase: a quale attivita' appartengono e, finche' il
-- Drive non e' collegato, dove sono nel gestionale (poi partono per il Drive).
ALTER TABLE `documents` ADD COLUMN `task_id` INT UNSIGNED NULL;
ALTER TABLE `documents` ADD COLUMN `local_path` VARCHAR(500) NULL;
ALTER TABLE `documents` ADD INDEX `idx_documents_task` (`task_id`);
