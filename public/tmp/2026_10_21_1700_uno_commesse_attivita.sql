-- Uno produzione (prod_aib_uno): commesse (tipi a modello, fasi) e attivita'.
-- Da lanciare DOPO 2026_10_21_1330_uno_clienti_servizi_scadenzario.sql. Si puo' rilanciare.
-- Chi e' collegato deve uscire e rientrare per vedere le voci nuove.

SET NAMES utf8mb4;

-- 20261021221303_01_tasks__0_core.sql
CREATE TABLE IF NOT EXISTS `tasks` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NULL,
  `due_at` DATETIME NULL,
  `stage` VARCHAR(50) NOT NULL,
  `assigned_user_id` INT UNSIGNED NULL,
  `planned_date` DATE NULL,
  `priority` VARCHAR(10) NULL,
  `estimated_minutes` SMALLINT UNSIGNED NULL,
  `actual_minutes` SMALLINT UNSIGNED NULL,
  `position` SMALLINT UNSIGNED NULL,
  `waits_for_client` TINYINT(1) NULL,
  `checklist` TEXT NULL,
  `recurrence` VARCHAR(20) NULL,
  `completed_at` DATETIME NULL,
  `entity_type` VARCHAR(50) NULL,
  `entity_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_tasks_assigned_user_id` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 20261021221303_02_work_orders__0_core.sql
CREATE TABLE IF NOT EXISTS `work_order_types` (
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

CREATE TABLE IF NOT EXISTS `work_order_type_steps` (
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

CREATE TABLE IF NOT EXISTS `work_orders` (
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


-- Permessi (stessa convenzione di db/seed.sql), dati al profilo amministratore (id 1).
INSERT INTO permission_groups (parent_id, code, name, description, sort_order, status, created_at, created_by)
SELECT (SELECT id FROM permission_groups WHERE code = 'administration' AND status <> 0), g.code, g.name, g.description, 0, 1, NOW(), 1
FROM (
    SELECT 'tasks' AS code, 'Attivita' AS name, 'Gestione attivita/promemoria' AS description
    UNION ALL SELECT 'work_orders', 'Commesse', 'Commesse per i clienti, tipi di commessa e fasi'
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
WHERE pg.code IN ('tasks', 'work_orders') AND pg.status <> 0
  AND NOT EXISTS (SELECT 1 FROM permissions x WHERE x.code = CONCAT(pg.code, '.', a.action) AND x.status <> 0);

INSERT INTO profile_permissions (profile_id, permission_id, status, created_at, created_by)
SELECT 1, p.id, 1, NOW(), 1
FROM permissions p
WHERE p.status <> 0 AND SUBSTRING_INDEX(p.code, '.', 1) IN ('tasks', 'work_orders')
  AND NOT EXISTS (SELECT 1 FROM profile_permissions x WHERE x.profile_id = 1 AND x.permission_id = p.id AND x.status <> 0);
