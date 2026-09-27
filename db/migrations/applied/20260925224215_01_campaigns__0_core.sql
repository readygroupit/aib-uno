CREATE TABLE `campaigns` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `name` VARCHAR(190) NOT NULL,
  `subject` VARCHAR(190) NOT NULL,
  `body` TEXT NOT NULL,
  `stage` VARCHAR(50) NOT NULL,
  `scheduled_at` DATETIME NOT NULL,
  `target_segment` VARCHAR(255) NOT NULL,
  `sent_count` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `campaign_emails` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `campaign_id` INT UNSIGNED NOT NULL,
  `recipient_email` VARCHAR(190) NOT NULL,
  `lead_id` INT UNSIGNED NULL,
  `stage` VARCHAR(50) NOT NULL,
  `sent_at` DATETIME NULL,
  `error_message` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_campaign_emails_campaign_id` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`),
  CONSTRAINT `fk_campaign_emails_lead_id` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

