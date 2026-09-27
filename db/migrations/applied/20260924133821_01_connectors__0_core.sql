CREATE TABLE `connector_credentials` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `connector_code` VARCHAR(50) NOT NULL,
  `auth_type` ENUM('api_key','oauth') NOT NULL,
  `api_key` VARCHAR(255) NULL,
  `access_token` TEXT NULL,
  `refresh_token` TEXT NULL,
  `token_expires_at` DATETIME NULL,
  `extra_config` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

