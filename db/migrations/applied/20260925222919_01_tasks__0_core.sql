CREATE TABLE `tasks` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NULL,
  `due_at` DATETIME NULL,
  `stage` VARCHAR(50) NOT NULL,
  `assigned_user_id` INT UNSIGNED NULL,
  `completed_at` DATETIME NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_tasks_assigned_user_id` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

