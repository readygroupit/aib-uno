CREATE TABLE `scheduled_jobs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `job_type` VARCHAR(100) NOT NULL,
  `payload` TEXT NOT NULL,
  `entity_type` VARCHAR(50) NULL,
  `entity_id` INT UNSIGNED NULL,
  `run_at` DATETIME NOT NULL,
  `interval_minutes` INT UNSIGNED NULL,
  `last_run_at` DATETIME NULL,
  `last_run_status` VARCHAR(20) NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `scheduled_job_runs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `scheduled_job_id` INT UNSIGNED NOT NULL,
  `started_at` DATETIME NOT NULL,
  `finished_at` DATETIME NOT NULL,
  `run_status` VARCHAR(20) NOT NULL,
  `result` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_scheduled_job_runs_scheduled_job_id` FOREIGN KEY (`scheduled_job_id`) REFERENCES `scheduled_jobs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

