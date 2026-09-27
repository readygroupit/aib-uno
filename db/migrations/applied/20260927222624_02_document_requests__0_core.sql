CREATE TABLE `document_requests` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `case_id` INT UNSIGNED NOT NULL,
  `document_type` VARCHAR(50) NOT NULL,
  `completeness_status` VARCHAR(20) NOT NULL,
  `attachment_id` INT UNSIGNED NULL,
  `requested_at` DATETIME NULL,
  `reviewed_at` DATETIME NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_document_requests_case_id` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`),
  CONSTRAINT `fk_document_requests_attachment_id` FOREIGN KEY (`attachment_id`) REFERENCES `attachments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

