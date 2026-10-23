CREATE TABLE `work_entries` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `user_id` INT UNSIGNED NOT NULL,
  `work_date` DATE NOT NULL,
  `minutes` SMALLINT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `task_id` INT UNSIGNED NULL,
  `customer_id` INT UNSIGNED NULL,
  `source` VARCHAR(20) NULL,
  `notes` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_work_entries_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_work_entries_task_id` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`),
  CONSTRAINT `fk_work_entries_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `work_checkins` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `user_id` INT UNSIGNED NOT NULL,
  `checked_at` DATETIME NOT NULL,
  `period` VARCHAR(20) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_work_checkins_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

