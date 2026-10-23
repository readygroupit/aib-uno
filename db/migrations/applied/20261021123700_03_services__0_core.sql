CREATE TABLE `services` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `name` VARCHAR(190) NOT NULL,
  `category` VARCHAR(100) NULL,
  `description` TEXT NULL,
  `stage` VARCHAR(50) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `tax_rate_percent` DECIMAL(5,2) NULL,
  `duration_minutes` SMALLINT UNSIGNED NULL,
  `billing_type` VARCHAR(20) NULL,
  `recurrence` VARCHAR(20) NULL,
  `cost_price` DECIMAL(10,2) NULL,
  `supplier_customer_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_services_supplier_customer_id` FOREIGN KEY (`supplier_customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

