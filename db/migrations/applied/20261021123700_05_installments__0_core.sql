CREATE TABLE `installments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `customer_id` INT UNSIGNED NOT NULL,
  `customer_service_id` INT UNSIGNED NULL,
  `kind` VARCHAR(20) NOT NULL,
  `description` VARCHAR(190) NOT NULL,
  `due_date` DATE NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `stage` VARCHAR(20) NOT NULL,
  `paid_at` DATE NULL,
  `payment_method` VARCHAR(30) NULL,
  `period_start` DATE NULL,
  `period_end` DATE NULL,
  `reminder_sent_at` DATETIME NULL,
  `notes` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_installments_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_installments_customer_service_id` FOREIGN KEY (`customer_service_id`) REFERENCES `customer_services` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

