CREATE TABLE `communications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `channel` VARCHAR(20) NOT NULL,
  `direction` VARCHAR(10) NOT NULL,
  `delivery_status` VARCHAR(20) NOT NULL,
  `template_code` VARCHAR(50) NULL,
  `sent_at` DATETIME NULL,
  `read_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `communication_contents` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `communication_id` INT UNSIGNED NOT NULL,
  `subject` VARCHAR(200) NULL,
  `body` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_communication_contents_communication_id` FOREIGN KEY (`communication_id`) REFERENCES `communications` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

