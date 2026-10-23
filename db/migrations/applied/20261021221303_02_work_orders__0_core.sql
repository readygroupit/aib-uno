CREATE TABLE `work_order_types` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `default_days` SMALLINT UNSIGNED NULL,
  `stage` VARCHAR(20) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `work_order_type_steps` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `work_order_type_id` INT UNSIGNED NOT NULL,
  `position` SMALLINT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NULL,
  `offset_days` SMALLINT UNSIGNED NULL,
  `estimated_minutes` SMALLINT UNSIGNED NULL,
  `waits_for_client` TINYINT(1) NULL,
  `checklist` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_work_order_type_steps_work_order_type_id` FOREIGN KEY (`work_order_type_id`) REFERENCES `work_order_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `work_orders` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `customer_id` INT UNSIGNED NOT NULL,
  `work_order_type_id` INT UNSIGNED NULL,
  `customer_service_id` INT UNSIGNED NULL,
  `title` VARCHAR(200) NOT NULL,
  `stage` VARCHAR(30) NOT NULL,
  `priority` VARCHAR(10) NULL,
  `owner_user_id` INT UNSIGNED NULL,
  `supplier_customer_id` INT UNSIGNED NULL,
  `start_date` DATE NOT NULL,
  `due_date` DATE NULL,
  `delivered_at` DATE NULL,
  `waiting_reason` VARCHAR(255) NULL,
  `waiting_since` DATE NULL,
  `last_reminder_at` DATETIME NULL,
  `last_activity_at` DATETIME NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_work_orders_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_work_orders_work_order_type_id` FOREIGN KEY (`work_order_type_id`) REFERENCES `work_order_types` (`id`),
  CONSTRAINT `fk_work_orders_customer_service_id` FOREIGN KEY (`customer_service_id`) REFERENCES `customer_services` (`id`),
  CONSTRAINT `fk_work_orders_owner_user_id` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_work_orders_supplier_customer_id` FOREIGN KEY (`supplier_customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

