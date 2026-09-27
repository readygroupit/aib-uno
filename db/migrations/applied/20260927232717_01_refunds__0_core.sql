CREATE TABLE `refunds` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `case_id` INT UNSIGNED NOT NULL,
  `amount_claimed` DECIMAL(10,2) NULL,
  `amount_accepted` DECIMAL(10,2) NULL,
  `payment_status` VARCHAR(20) NOT NULL,
  `paid_at` DATETIME NULL,
  `invoice_reference` VARCHAR(100) NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_refunds_case_id` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

