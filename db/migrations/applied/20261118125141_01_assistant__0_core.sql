CREATE TABLE `assistant_rules` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `code` VARCHAR(50) NOT NULL,
  `is_enabled` TINYINT(1) NOT NULL,
  `days` SMALLINT UNSIGNED NULL,
  `autonomy` VARCHAR(20) NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `assistant_actions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `rule_code` VARCHAR(50) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `entity_type` VARCHAR(50) NULL,
  `entity_id` INT UNSIGNED NULL,
  `dedupe_key` VARCHAR(190) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `body` TEXT NULL,
  `email_to` VARCHAR(255) NULL,
  `email_subject` VARCHAR(200) NULL,
  `email_body` TEXT NULL,
  `stage` VARCHAR(20) NOT NULL,
  `decided_at` DATETIME NULL,
  `decided_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_assistant_actions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


ALTER TABLE `assistant_actions` ADD KEY `idx_assistant_actions_key` (`dedupe_key`), ADD KEY `idx_assistant_actions_user` (`user_id`, `stage`);
ALTER TABLE `assistant_rules` ADD KEY `idx_assistant_rules_code` (`code`);
