ALTER TABLE `customers` ADD COLUMN `preferred_payment_method` VARCHAR(30) NULL;
ALTER TABLE `customers` ADD COLUMN `auto_pay` TINYINT(1) NULL;
ALTER TABLE `customers` ADD COLUMN `portal_token` VARCHAR(64) NULL;
ALTER TABLE `customers` ADD COLUMN `stripe_customer_id` VARCHAR(64) NULL;
ALTER TABLE `customers` ADD COLUMN `payment_mandate` VARCHAR(255) NULL;
