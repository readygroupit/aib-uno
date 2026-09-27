CREATE TABLE `services` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `name` VARCHAR(190) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `stage` VARCHAR(50) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `tax_rate_percent` DECIMAL(5,2) NOT NULL,
  `duration_minutes` SMALLINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

