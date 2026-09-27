CREATE TABLE `invoices` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `invoice_number` VARCHAR(50) NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `issue_date` DATE NOT NULL,
  `due_date` DATE NOT NULL,
  `stage` VARCHAR(50) NOT NULL,
  `description` TEXT NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  `tax_amount` DECIMAL(10,2) NOT NULL,
  `total` DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_invoices_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

