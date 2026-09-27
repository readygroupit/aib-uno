CREATE TABLE `ai_agent_tasks` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `agent_code` VARCHAR(50) NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `requires_approval` TINYINT(1) NOT NULL,
  `approval_status` VARCHAR(20) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

