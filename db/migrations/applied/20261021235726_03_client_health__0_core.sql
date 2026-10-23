CREATE TABLE `customer_touchpoints` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `customer_id` INT UNSIGNED NOT NULL,
  `contact_date` DATE NOT NULL,
  `channel` VARCHAR(20) NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `mood` VARCHAR(20) NOT NULL,
  `summary` VARCHAR(255) NOT NULL,
  `next_step` VARCHAR(255) NULL,
  `next_date` DATE NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_customer_touchpoints_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_customer_touchpoints_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

