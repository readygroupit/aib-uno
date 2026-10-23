-- Uno produzione (prod_aib_uno): calendario (appuntamenti), connettori (Google Calendar), scheduler.
-- Da lanciare DOPO gli script 2026_10_21_1330 e 2026_10_21_1700. Si puo' rilanciare.
-- Poi: cron di www-data per bin/run-scheduler.php (sincronizzazione Google ogni 5 minuti, rinnovi una volta al giorno).
-- Chi e' collegato deve uscire e rientrare per vedere il Calendario.

SET NAMES utf8mb4;

-- 20261021224438_01_appointments__0_core.sql
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `title` VARCHAR(190) NOT NULL,
  `kind` VARCHAR(30) NULL,
  `start_at` DATETIME NOT NULL,
  `end_at` DATETIME NULL,
  `location` VARCHAR(190) NULL,
  `travel_minutes` SMALLINT UNSIGNED NULL,
  `is_online` TINYINT(1) NULL,
  `stage` VARCHAR(50) NOT NULL,
  `assigned_user_id` INT UNSIGNED NULL,
  `attendee_user_ids` VARCHAR(255) NULL,
  `customer_id` INT UNSIGNED NULL,
  `entity_type` VARCHAR(50) NULL,
  `entity_id` INT UNSIGNED NULL,
  `checklist` TEXT NULL,
  `reminder_minutes_before` SMALLINT UNSIGNED NULL,
  `notes` TEXT NULL,
  `meet_url` VARCHAR(255) NULL,
  `google_event_id` VARCHAR(255) NULL,
  `google_synced_at` DATETIME NULL,
  `source` VARCHAR(20) NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_appointments_assigned_user_id` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_appointments_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 20261021224438_02_connectors__0_core.sql
CREATE TABLE IF NOT EXISTS `connector_credentials` (
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


-- 20261021224438_03_scheduler__0_core.sql
CREATE TABLE IF NOT EXISTS `scheduled_jobs` (
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

CREATE TABLE IF NOT EXISTS `scheduled_job_runs` (
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


-- Permessi (stessa convenzione di db/seed.sql), dati al profilo amministratore (id 1).
INSERT INTO permission_groups (parent_id, code, name, description, sort_order, status, created_at, created_by)
SELECT (SELECT id FROM permission_groups WHERE code = 'administration' AND status <> 0), g.code, g.name, g.description, 0, 1, NOW(), 1
FROM (
    SELECT 'appointments' AS code, 'Appuntamenti' AS name, 'Gestione appuntamenti' AS description
) g
WHERE NOT EXISTS (SELECT 1 FROM permission_groups x WHERE x.code = g.code AND x.status <> 0);

INSERT INTO permissions (permission_group_id, code, name, description, category, sort_order, status, created_at, created_by)
SELECT pg.id, CONCAT(pg.code, '.', a.action), CONCAT(a.verb, ' ', LOWER(pg.name)), a.description, a.action, 0, 1, NOW(), 1
FROM permission_groups pg
JOIN (
    SELECT 'view' AS action, 'Visualizzare' AS verb, 'Vedere l''elenco e aprire il dettaglio.' AS description
    UNION ALL SELECT 'create', 'Creare', 'Aggiungere nuovi elementi.'
    UNION ALL SELECT 'edit', 'Modificare', 'Modificare elementi esistenti.'
    UNION ALL SELECT 'delete', 'Eliminare', 'Eliminare elementi esistenti.'
) a
WHERE pg.code IN ('appointments') AND pg.status <> 0
  AND NOT EXISTS (SELECT 1 FROM permissions x WHERE x.code = CONCAT(pg.code, '.', a.action) AND x.status <> 0);

INSERT INTO profile_permissions (profile_id, permission_id, status, created_at, created_by)
SELECT 1, p.id, 1, NOW(), 1
FROM permissions p
WHERE p.status <> 0 AND SUBSTRING_INDEX(p.code, '.', 1) IN ('appointments')
  AND NOT EXISTS (SELECT 1 FROM profile_permissions x WHERE x.profile_id = 1 AND x.permission_id = p.id AND x.status <> 0);
