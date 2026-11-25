-- Qualificazione del cliente (fase 5): stato commerciale, temperatura, com'e' da seguire, quando risentirlo.
ALTER TABLE `customers`
  ADD COLUMN `commercial_stage` VARCHAR(20) NULL,
  ADD COLUMN `temperature` VARCHAR(10) NULL,
  ADD COLUMN `temperament` VARCHAR(20) NULL,
  ADD COLUMN `next_contact_date` DATE NULL,
  ADD COLUMN `next_contact_note` VARCHAR(255) NULL;
