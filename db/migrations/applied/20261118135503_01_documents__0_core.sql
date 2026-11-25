CREATE TABLE `document_folders` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `external_id` VARCHAR(120) NOT NULL,
  `parent_id` INT UNSIGNED NULL,
  `name` VARCHAR(255) NOT NULL,
  `customer_id` INT UNSIGNED NULL,
  `work_order_id` INT UNSIGNED NULL,
  `web_link` VARCHAR(500) NULL,
  `drive_modified_at` DATETIME NULL,
  `trashed_at` DATETIME NULL,
  `synced_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `documents` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `external_id` VARCHAR(120) NOT NULL,
  `folder_id` INT UNSIGNED NULL,
  `name` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(150) NULL,
  `size_bytes` BIGINT UNSIGNED NULL,
  `web_link` VARCHAR(500) NULL,
  `md5` VARCHAR(64) NULL,
  `drive_modified_at` DATETIME NULL,
  `description` TEXT NULL,
  `tags` VARCHAR(500) NULL,
  `customer_id` INT UNSIGNED NULL,
  `work_order_id` INT UNSIGNED NULL,
  `uploaded_by` INT UNSIGNED NULL,
  `origin` VARCHAR(10) NOT NULL,
  `trashed_at` DATETIME NULL,
  `synced_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


ALTER TABLE `document_folders` ADD KEY `idx_document_folders_ext` (`external_id`), ADD KEY `idx_document_folders_parent` (`parent_id`), ADD KEY `idx_document_folders_wo` (`work_order_id`), ADD KEY `idx_document_folders_customer` (`customer_id`);
ALTER TABLE `documents` ADD KEY `idx_documents_ext` (`external_id`), ADD KEY `idx_documents_folder` (`folder_id`), ADD KEY `idx_documents_customer` (`customer_id`);
