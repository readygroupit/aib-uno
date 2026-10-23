ALTER TABLE `installments` ADD COLUMN `payment_provider` VARCHAR(20) NULL;
ALTER TABLE `installments` ADD COLUMN `payment_reference` VARCHAR(255) NULL;
ALTER TABLE `installments` ADD COLUMN `payment_link_url` VARCHAR(500) NULL;
ALTER TABLE `installments` ADD COLUMN `invoice_id` INT UNSIGNED NULL;
