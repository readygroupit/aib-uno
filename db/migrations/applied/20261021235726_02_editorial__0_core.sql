CREATE TABLE `editorial_posts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `customer_id` INT UNSIGNED NOT NULL,
  `channel` VARCHAR(20) NOT NULL,
  `format` VARCHAR(20) NOT NULL,
  `publish_date` DATE NOT NULL,
  `publish_time` VARCHAR(5) NULL,
  `title` VARCHAR(200) NOT NULL,
  `caption` TEXT NULL,
  `media_notes` VARCHAR(255) NULL,
  `stage` VARCHAR(20) NOT NULL,
  `assigned_user_id` INT UNSIGNED NULL,
  `client_feedback` TEXT NULL,
  `approved_at` DATETIME NULL,
  `published_url` VARCHAR(255) NULL,
  `task_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_editorial_posts_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_editorial_posts_assigned_user_id` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `editorial_quotas` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `customer_id` INT UNSIGNED NOT NULL,
  `channel` VARCHAR(20) NOT NULL,
  `per_week` TINYINT UNSIGNED NOT NULL,
  `formats` VARCHAR(100) NULL,
  `default_user_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_editorial_quotas_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_editorial_quotas_default_user_id` FOREIGN KEY (`default_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

