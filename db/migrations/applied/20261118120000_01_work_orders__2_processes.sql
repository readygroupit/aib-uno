-- Processi dei servizi (design/processi-servizi.md): il tipo di commessa
-- diventa il processo di un servizio, con ruoli, date chiave e cose da
-- consegnare; la commessa diventa il progetto, dal primo contatto.

ALTER TABLE `work_order_types`
  ADD COLUMN `service_id` INT UNSIGNED NULL;

ALTER TABLE `work_order_type_steps`
  ADD COLUMN `phase` VARCHAR(60) NULL,
  ADD COLUMN `kind` VARCHAR(30) NULL,
  ADD COLUMN `role_id` INT UNSIGNED NULL,
  ADD COLUMN `milestone_id` INT UNSIGNED NULL,
  ADD COLUMN `completes_on` VARCHAR(40) NULL,
  ADD COLUMN `reject_step_id` INT UNSIGNED NULL,
  ADD COLUMN `is_repeatable` TINYINT(1) NULL,
  ADD COLUMN `is_optional` TINYINT(1) NULL,
  MODIFY COLUMN `offset_days` SMALLINT UNSIGNED NULL;

CREATE TABLE `work_order_type_roles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `work_order_type_id` INT UNSIGNED NOT NULL,
  `position` SMALLINT UNSIGNED NOT NULL,
  `label` VARCHAR(80) NOT NULL,
  `description` TEXT NULL,
  `default_user_id` INT UNSIGNED NULL,
  `customer_team_role` VARCHAR(20) NULL,
  `is_multiple` TINYINT(1) NULL,
  `is_required` TINYINT(1) NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_work_order_type_roles_work_order_type_id` FOREIGN KEY (`work_order_type_id`) REFERENCES `work_order_types` (`id`),
  CONSTRAINT `fk_work_order_type_roles_default_user_id` FOREIGN KEY (`default_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `work_order_type_milestones` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `work_order_type_id` INT UNSIGNED NOT NULL,
  `position` SMALLINT UNSIGNED NOT NULL,
  `label` VARCHAR(80) NOT NULL,
  `set_by_role_id` INT UNSIGNED NULL,
  `after_step_id` INT UNSIGNED NULL,
  `creates_appointment` TINYINT(1) NULL,
  `client_email_subject` VARCHAR(200) NULL,
  `client_email_body` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_work_order_type_milestones_work_order_type_id` FOREIGN KEY (`work_order_type_id`) REFERENCES `work_order_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `work_order_type_deliverables` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `work_order_type_id` INT UNSIGNED NOT NULL,
  `position` SMALLINT UNSIGNED NOT NULL,
  `label` VARCHAR(120) NOT NULL,
  `description` TEXT NULL,
  `always_included` TINYINT(1) NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_work_order_type_deliverables_work_order_type_id` FOREIGN KEY (`work_order_type_id`) REFERENCES `work_order_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `work_orders`
  ADD COLUMN `quote_id` INT UNSIGNED NULL,
  ADD COLUMN `source_channel` VARCHAR(30) NULL,
  ADD COLUMN `lost_reason` VARCHAR(255) NULL;

CREATE TABLE `work_order_members` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `work_order_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `role_id` INT UNSIGNED NULL,
  `notes` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_work_order_members_work_order_id` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`),
  CONSTRAINT `fk_work_order_members_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `work_order_milestones` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `work_order_id` INT UNSIGNED NOT NULL,
  `milestone_id` INT UNSIGNED NULL,
  `position` SMALLINT UNSIGNED NOT NULL,
  `label` VARCHAR(80) NOT NULL,
  `scheduled_at` DATETIME NULL,
  `send_client_email` TINYINT(1) NULL,
  `client_email_to` VARCHAR(255) NULL,
  `client_email_subject` VARCHAR(200) NULL,
  `client_email_body` TEXT NULL,
  `client_email_sent_at` DATETIME NULL,
  `appointment_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_work_order_milestones_work_order_id` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `work_order_deliverables` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `work_order_id` INT UNSIGNED NOT NULL,
  `position` SMALLINT UNSIGNED NOT NULL,
  `label` VARCHAR(120) NOT NULL,
  `description` TEXT NULL,
  `is_done` TINYINT(1) NULL,
  `done_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_work_order_deliverables_work_order_id` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `work_order_change_requests` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `work_order_id` INT UNSIGNED NOT NULL,
  `requested_at` DATE NOT NULL,
  `description` TEXT NOT NULL,
  `assigned_user_id` INT UNSIGNED NULL,
  `is_extra` TINYINT(1) NULL,
  `stage` VARCHAR(20) NOT NULL,
  `quote_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_work_order_change_requests_work_order_id` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`),
  CONSTRAINT `fk_work_order_change_requests_assigned_user_id` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `tasks`
  ADD COLUMN `iteration` SMALLINT UNSIGNED NULL;
