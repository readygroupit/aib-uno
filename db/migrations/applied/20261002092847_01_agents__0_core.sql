CREATE TABLE `agents` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `code` VARCHAR(40) NOT NULL,
  `name` VARCHAR(80) NOT NULL,
  `role` VARCHAR(120) NOT NULL,
  `bio` TEXT NOT NULL,
  `is_enabled` TINYINT UNSIGNED NOT NULL,
  `schedule` VARCHAR(30) NOT NULL,
  `autonomy` VARCHAR(20) NOT NULL,
  `rule_text` TEXT NULL,
  `last_run_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `agent_messages` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `agent_id` INT UNSIGNED NOT NULL,
  `dedupe_key` VARCHAR(80) NOT NULL,
  `body` TEXT NOT NULL,
  `detail` TEXT NULL,
  `action_type` VARCHAR(30) NULL,
  `action_label` VARCHAR(60) NULL,
  `link_href` VARCHAR(190) NULL,
  `entity_type` VARCHAR(30) NULL,
  `entity_id` INT UNSIGNED NULL,
  `state` VARCHAR(20) NOT NULL,
  `result_text` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_agent_messages_agent_id` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

