CREATE TABLE `leave_requests` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `user_id` INT UNSIGNED NOT NULL,
  `kind` VARCHAR(20) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NULL,
  `start_time` VARCHAR(5) NULL,
  `end_time` VARCHAR(5) NULL,
  `stage` VARCHAR(20) NOT NULL,
  `note` VARCHAR(255) NULL,
  `decided_by` INT UNSIGNED NULL,
  `decided_at` DATETIME NULL,
  `reply` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_leave_requests_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_leave_requests_decided_by` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

