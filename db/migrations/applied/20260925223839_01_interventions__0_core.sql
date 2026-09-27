CREATE TABLE `interventions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `title` VARCHAR(190) NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `scheduled_at` DATETIME NOT NULL,
  `stage` VARCHAR(50) NOT NULL,
  `operation_type` VARCHAR(50) NOT NULL,
  `assigned_user_id` INT UNSIGNED NOT NULL,
  `call_out_fee` DECIMAL(10,2) NOT NULL,
  `labor_cost` DECIMAL(10,2) NOT NULL,
  `total` DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `notes` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_interventions_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_interventions_assigned_user_id` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

