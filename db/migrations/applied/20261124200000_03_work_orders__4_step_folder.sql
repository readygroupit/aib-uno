-- La cartella del progetto dove vanno i file caricati da una fase (es. "03 Bozze").
ALTER TABLE `work_order_type_steps` ADD COLUMN `folder_name` VARCHAR(120) NULL;
