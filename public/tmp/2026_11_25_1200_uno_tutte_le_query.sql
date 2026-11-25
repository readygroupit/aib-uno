-- Uno produzione (prod_aib_uno): TUTTE le query, in un solo file.
-- Si puo' rilanciare per intero: ogni parte crea o aggiunge solo cio' che manca,
-- quindi le parti gia' eseguite in passato non cambiano nulla.
-- Da lanciare PRIMA di pubblicare il codice. Chi e' collegato deve uscire e rientrare.
-- Parti: 21/10 clienti, servizi, scadenzario - 21/10 commesse e attivita' - 22/10 calendario Google -
-- 22/10 organizer - 22/10 registro attivita' - 22/10 editoriale, fatture, pagamenti -
-- 23/10 preventivi, richieste, archivio, ferie, spese - 25/11 processi, agenti, documenti.


-- ======================================================================
-- Parte: 2026_10_21_1330_uno_clienti_servizi_scadenzario
-- ======================================================================
-- Uno produzione (prod_aib_uno): pacchetti clienti, servizi, servizi dei clienti, scadenzario.
-- Le migrazioni arrivano da git gia' in db/migrations/applied: qui le stesse CREATE TABLE + i permessi.
-- Dopo, caricare i comuni: mysql prod_aib_uno < packages/geo/seed.sql
-- Chi e' collegato deve uscire e rientrare per vedere le voci nuove.

SET NAMES utf8mb4;

-- 20261021123700_01_geo__0_core.sql
CREATE TABLE IF NOT EXISTS `regions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `name` VARCHAR(50) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `districts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `region_id` INT UNSIGNED NOT NULL,
  `code` VARCHAR(2) NOT NULL,
  `name` VARCHAR(70) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_districts_region_id` FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `municipalities` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `region_id` INT UNSIGNED NOT NULL,
  `district_id` INT UNSIGNED NOT NULL,
  `cadastral_code` VARCHAR(5) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_municipalities_region_id` FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`),
  CONSTRAINT `fk_municipalities_district_id` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `postal_codes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `region_id` INT UNSIGNED NULL,
  `district_id` INT UNSIGNED NULL,
  `municipality_id` INT UNSIGNED NULL,
  `code` VARCHAR(5) NOT NULL,
  `municipality_code` VARCHAR(4) NOT NULL,
  `district_code` VARCHAR(2) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_postal_codes_region_id` FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`),
  CONSTRAINT `fk_postal_codes_district_id` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`),
  CONSTRAINT `fk_postal_codes_municipality_id` FOREIGN KEY (`municipality_id`) REFERENCES `municipalities` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 20261021123700_02_customers__0_core.sql
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `first_name` VARCHAR(100) NULL,
  `last_name` VARCHAR(100) NULL,
  `company_name` VARCHAR(150) NULL,
  `email` VARCHAR(190) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `mobile_phone` VARCHAR(30) NULL,
  `website` VARCHAR(190) NULL,
  `address` VARCHAR(190) NULL,
  `municipality_id` INT UNSIGNED NULL,
  `postal_code` VARCHAR(10) NULL,
  `vat_number` VARCHAR(20) NULL,
  `sdi_code` VARCHAR(10) NULL,
  `pec` VARCHAR(190) NULL,
  `iban` VARCHAR(34) NULL,
  `payment_terms_days` SMALLINT UNSIGNED NULL,
  `notes` TEXT NULL,
  `relationship` VARCHAR(30) NULL,
  `importance` VARCHAR(20) NULL,
  `sector` VARCHAR(100) NULL,
  `customer_since` DATE NULL,
  `account_user_id` INT UNSIGNED NULL,
  `referrer_customer_id` INT UNSIGNED NULL,
  `referral_percent` DECIMAL(5,2) NULL,
  `referral_terms` VARCHAR(190) NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_customers_municipality_id` FOREIGN KEY (`municipality_id`) REFERENCES `municipalities` (`id`),
  CONSTRAINT `fk_customers_account_user_id` FOREIGN KEY (`account_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_customers_referrer_customer_id` FOREIGN KEY (`referrer_customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `customer_contacts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `customer_id` INT UNSIGNED NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `role` VARCHAR(100) NULL,
  `email` VARCHAR(190) NULL,
  `phone` VARCHAR(30) NULL,
  `is_decision_maker` TINYINT(1) NULL,
  `notes` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_customer_contacts_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 20261021123700_03_services__0_core.sql
CREATE TABLE IF NOT EXISTS `services` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `name` VARCHAR(190) NOT NULL,
  `category` VARCHAR(100) NULL,
  `description` TEXT NULL,
  `stage` VARCHAR(50) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `tax_rate_percent` DECIMAL(5,2) NULL,
  `duration_minutes` SMALLINT UNSIGNED NULL,
  `billing_type` VARCHAR(20) NULL,
  `recurrence` VARCHAR(20) NULL,
  `cost_price` DECIMAL(10,2) NULL,
  `supplier_customer_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_services_supplier_customer_id` FOREIGN KEY (`supplier_customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 20261021123700_04_customer_services__0_core.sql
CREATE TABLE IF NOT EXISTS `customer_services` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `customer_id` INT UNSIGNED NOT NULL,
  `service_id` INT UNSIGNED NULL,
  `title` VARCHAR(190) NOT NULL,
  `stage` VARCHAR(20) NOT NULL,
  `billing_type` VARCHAR(20) NOT NULL,
  `recurrence` VARCHAR(20) NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `cost_price` DECIMAL(10,2) NULL,
  `start_date` DATE NOT NULL,
  `renewal_date` DATE NULL,
  `end_date` DATE NULL,
  `auto_renew` TINYINT(1) NULL,
  `notice_days` SMALLINT UNSIGNED NULL,
  `payment_plan` VARCHAR(20) NOT NULL,
  `deposit_percent` DECIMAL(5,2) NULL,
  `balance_date` DATE NULL,
  `installments_count` TINYINT UNSIGNED NULL,
  `payment_method` VARCHAR(30) NULL,
  `supplier_customer_id` INT UNSIGNED NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_customer_services_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_customer_services_service_id` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `fk_customer_services_supplier_customer_id` FOREIGN KEY (`supplier_customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 20261021123700_05_installments__0_core.sql
CREATE TABLE IF NOT EXISTS `installments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `customer_id` INT UNSIGNED NOT NULL,
  `customer_service_id` INT UNSIGNED NULL,
  `kind` VARCHAR(20) NOT NULL,
  `description` VARCHAR(190) NOT NULL,
  `due_date` DATE NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `stage` VARCHAR(20) NOT NULL,
  `paid_at` DATE NULL,
  `payment_method` VARCHAR(30) NULL,
  `period_start` DATE NULL,
  `period_end` DATE NULL,
  `reminder_sent_at` DATETIME NULL,
  `notes` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_installments_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_installments_customer_service_id` FOREIGN KEY (`customer_service_id`) REFERENCES `customer_services` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Permessi (stessa convenzione di db/seed.sql), dati al profilo amministratore (id 1).
INSERT INTO permission_groups (parent_id, code, name, description, sort_order, status, created_at, created_by)
SELECT (SELECT id FROM permission_groups WHERE code = 'administration' AND status <> 0), g.code, g.name, g.description, 0, 1, NOW(), 1
FROM (
    SELECT 'customers' AS code, 'Clienti' AS name, 'Gestione clienti' AS description
    UNION ALL SELECT 'services', 'Servizi', 'Gestione servizi'
    UNION ALL SELECT 'customer_services', 'Servizi dei clienti', 'Servizi venduti ai clienti, rinnovi e piano di pagamento'
    UNION ALL SELECT 'installments', 'Scadenzario incassi', 'Incassi attesi dai clienti: acconti, saldi, rate, rinnovi'
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
WHERE pg.code IN ('customers', 'services', 'customer_services', 'installments') AND pg.status <> 0
  AND NOT EXISTS (SELECT 1 FROM permissions x WHERE x.code = CONCAT(pg.code, '.', a.action) AND x.status <> 0);

INSERT INTO profile_permissions (profile_id, permission_id, status, created_at, created_by)
SELECT 1, p.id, 1, NOW(), 1
FROM permissions p
WHERE p.status <> 0 AND SUBSTRING_INDEX(p.code, '.', 1) IN ('customers', 'services', 'customer_services', 'installments')
  AND NOT EXISTS (SELECT 1 FROM profile_permissions x WHERE x.profile_id = 1 AND x.permission_id = p.id AND x.status <> 0);


-- ======================================================================
-- Parte: 2026_10_21_1700_uno_commesse_attivita
-- ======================================================================
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


-- ======================================================================
-- Parte: 2026_10_22_0900_uno_calendario_google
-- ======================================================================
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


-- ======================================================================
-- Parte: 2026_10_22_1000_uno_organizer
-- ======================================================================
-- Uno produzione (prod_aib_uno): organizer (orari di lavoro e permessi propri).
-- Da lanciare DOPO gli script del 21 e del 22 ottobre (calendario). Si puo' rilanciare.
-- Chi e' collegato deve uscire e rientrare per vedere 'La mia giornata' e 'Il team'.

SET NAMES utf8mb4;

-- 20261021231805_01_planner__0_core.sql
CREATE TABLE IF NOT EXISTS `work_schedules` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `user_id` INT UNSIGNED NOT NULL,
  `start_time` VARCHAR(5) NOT NULL,
  `end_time` VARCHAR(5) NOT NULL,
  `break_start` VARCHAR(5) NULL,
  `break_end` VARCHAR(5) NULL,
  `workdays` VARCHAR(20) NULL,
  `focus_percent` TINYINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_work_schedules_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Permessi propri dell'organizer, dati al profilo amministratore (id 1).
INSERT INTO permission_groups (parent_id, code, name, description, sort_order, status, created_at, created_by)
SELECT (SELECT id FROM permission_groups WHERE code = 'administration' AND status <> 0), 'planner', 'Organizer', 'La giornata di ognuno, il carico del team, gli orari', 0, 1, NOW(), 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM permission_groups WHERE code = 'planner' AND status <> 0);

INSERT INTO permissions (permission_group_id, code, name, description, category, sort_order, status, created_at, created_by)
SELECT pg.id, p.code, p.name, p.description, p.category, 0, 1, NOW(), 1
FROM permission_groups pg
JOIN (
    SELECT 'planner.view' AS code, 'Vedere la propria giornata' AS name, 'L''organizer con le proprie attivita'' e i propri appuntamenti.' AS description, 'view' AS category
    UNION ALL SELECT 'planner.team', 'Organizzare il lavoro del team', 'Vedere la giornata e il carico di tutti, spostare e riassegnare il lavoro, impostare gli orari.', 'edit'
) p
WHERE pg.code = 'planner' AND pg.status <> 0
  AND NOT EXISTS (SELECT 1 FROM permissions x WHERE x.code = p.code AND x.status <> 0);

INSERT INTO profile_permissions (profile_id, permission_id, status, created_at, created_by)
SELECT 1, p.id, 1, NOW(), 1
FROM permissions p
WHERE p.status <> 0 AND p.code IN ('planner.view', 'planner.team')
  AND NOT EXISTS (SELECT 1 FROM profile_permissions x WHERE x.profile_id = 1 AND x.permission_id = p.id AND x.status <> 0);


-- ======================================================================
-- Parte: 2026_10_22_1100_uno_registro_attivita
-- ======================================================================
-- Uno produzione (prod_aib_uno): registro attivita' (voci, risposte), campo nuovo sulle attivita', permessi propri.
-- Da lanciare DOPO gli script precedenti (fino a 2026_10_22_1000_uno_organizer.sql). Si puo' rilanciare.
-- Chi e' collegato deve uscire e rientrare.

SET NAMES utf8mb4;

-- Attivita': la fase del modello da cui nascono (per i tempi medi). Solo se manca.
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'source_step_id') = 0, 'ALTER TABLE `tasks` ADD COLUMN `source_step_id` INT UNSIGNED NULL', 'DO 0') INTO @add_step;
PREPARE add_step FROM @add_step;
EXECUTE add_step;
DEALLOCATE PREPARE add_step;

-- 20261021233455_01_worklog__0_core.sql
CREATE TABLE IF NOT EXISTS `work_entries` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `user_id` INT UNSIGNED NOT NULL,
  `work_date` DATE NOT NULL,
  `minutes` SMALLINT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `task_id` INT UNSIGNED NULL,
  `customer_id` INT UNSIGNED NULL,
  `source` VARCHAR(20) NULL,
  `notes` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_work_entries_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_work_entries_task_id` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`),
  CONSTRAINT `fk_work_entries_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `work_checkins` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `user_id` INT UNSIGNED NOT NULL,
  `checked_at` DATETIME NOT NULL,
  `period` VARCHAR(20) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_work_checkins_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Permessi propri del registro, dati al profilo amministratore (id 1).
INSERT INTO permission_groups (parent_id, code, name, description, sort_order, status, created_at, created_by)
SELECT (SELECT id FROM permission_groups WHERE code = 'administration' AND status <> 0), 'worklog', 'Registro attivita', 'Cosa ha fatto ognuno e quanto ci ha messo, tempi medi', 0, 1, NOW(), 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM permission_groups WHERE code = 'worklog' AND status <> 0);

INSERT INTO permissions (permission_group_id, code, name, description, category, sort_order, status, created_at, created_by)
SELECT pg.id, p.code, p.name, p.description, p.category, 0, 1, NOW(), 1
FROM permission_groups pg
JOIN (
    SELECT 'worklog.view' AS code, 'Segnare il proprio lavoro' AS name, 'Rispondere a "cosa hai fatto?" e vedere il proprio registro.' AS description, 'view' AS category
    UNION ALL SELECT 'worklog.team', 'Vedere tempi e andamento del team', 'Tempi medi per tipo di lavoro, scarti, chi non ha segnato, carichi.', 'edit'
) p
WHERE pg.code = 'worklog' AND pg.status <> 0
  AND NOT EXISTS (SELECT 1 FROM permissions x WHERE x.code = p.code AND x.status <> 0);

INSERT INTO profile_permissions (profile_id, permission_id, status, created_at, created_by)
SELECT 1, p.id, 1, NOW(), 1
FROM permissions p
WHERE p.status <> 0 AND p.code IN ('worklog.view', 'worklog.team')
  AND NOT EXISTS (SELECT 1 FROM profile_permissions x WHERE x.profile_id = 1 AND x.permission_id = p.id AND x.status <> 0);


-- ======================================================================
-- Parte: 2026_10_22_1500_uno_editoriale_fatture_pagamenti_clienti
-- ======================================================================
-- Uno produzione (prod_aib_uno): piano editoriale, fatture elettroniche, pagamenti online,
-- temperatura dei clienti, area cliente. Campi nuovi su clienti, servizi venduti e scadenzario;
-- tabelle nuove; permessi del piano editoriale e della temperatura.
-- Da lanciare DOPO gli script precedenti (fino a 2026_10_22_1100_uno_registro_attivita.sql). Si puo' rilanciare.
-- Chi e' collegato deve uscire e rientrare.

SET NAMES utf8mb4;

-- 20261021235726_01_customers__1_portal_payments.sql: colonne nuove, solo se mancano.
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'preferred_payment_method') = 0, 'ALTER TABLE `customers` ADD COLUMN `preferred_payment_method` VARCHAR(30) NULL', 'DO 0') INTO @c1;
PREPARE s1 FROM @c1;
EXECUTE s1;
DEALLOCATE PREPARE s1;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'auto_pay') = 0, 'ALTER TABLE `customers` ADD COLUMN `auto_pay` TINYINT(1) NULL', 'DO 0') INTO @c2;
PREPARE s2 FROM @c2;
EXECUTE s2;
DEALLOCATE PREPARE s2;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'portal_token') = 0, 'ALTER TABLE `customers` ADD COLUMN `portal_token` VARCHAR(64) NULL', 'DO 0') INTO @c3;
PREPARE s3 FROM @c3;
EXECUTE s3;
DEALLOCATE PREPARE s3;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'stripe_customer_id') = 0, 'ALTER TABLE `customers` ADD COLUMN `stripe_customer_id` VARCHAR(64) NULL', 'DO 0') INTO @c4;
PREPARE s4 FROM @c4;
EXECUTE s4;
DEALLOCATE PREPARE s4;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'payment_mandate') = 0, 'ALTER TABLE `customers` ADD COLUMN `payment_mandate` VARCHAR(255) NULL', 'DO 0') INTO @c5;
PREPARE s5 FROM @c5;
EXECUTE s5;
DEALLOCATE PREPARE s5;

-- 20261021235726_02_customer_services__1_results.sql: colonne nuove, solo se mancano.
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_services' AND COLUMN_NAME = 'results') = 0, 'ALTER TABLE `customer_services` ADD COLUMN `results` VARCHAR(20) NULL', 'DO 0') INTO @c6;
PREPARE s6 FROM @c6;
EXECUTE s6;
DEALLOCATE PREPARE s6;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_services' AND COLUMN_NAME = 'results_note') = 0, 'ALTER TABLE `customer_services` ADD COLUMN `results_note` VARCHAR(255) NULL', 'DO 0') INTO @c7;
PREPARE s7 FROM @c7;
EXECUTE s7;
DEALLOCATE PREPARE s7;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_services' AND COLUMN_NAME = 'results_updated_at') = 0, 'ALTER TABLE `customer_services` ADD COLUMN `results_updated_at` DATE NULL', 'DO 0') INTO @c8;
PREPARE s8 FROM @c8;
EXECUTE s8;
DEALLOCATE PREPARE s8;

-- 20261021235726_03_installments__1_payments.sql: colonne nuove, solo se mancano.
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installments' AND COLUMN_NAME = 'payment_provider') = 0, 'ALTER TABLE `installments` ADD COLUMN `payment_provider` VARCHAR(20) NULL', 'DO 0') INTO @c9;
PREPARE s9 FROM @c9;
EXECUTE s9;
DEALLOCATE PREPARE s9;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installments' AND COLUMN_NAME = 'payment_reference') = 0, 'ALTER TABLE `installments` ADD COLUMN `payment_reference` VARCHAR(255) NULL', 'DO 0') INTO @c10;
PREPARE s10 FROM @c10;
EXECUTE s10;
DEALLOCATE PREPARE s10;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installments' AND COLUMN_NAME = 'payment_link_url') = 0, 'ALTER TABLE `installments` ADD COLUMN `payment_link_url` VARCHAR(500) NULL', 'DO 0') INTO @c11;
PREPARE s11 FROM @c11;
EXECUTE s11;
DEALLOCATE PREPARE s11;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installments' AND COLUMN_NAME = 'invoice_id') = 0, 'ALTER TABLE `installments` ADD COLUMN `invoice_id` INT UNSIGNED NULL', 'DO 0') INTO @c12;
PREPARE s12 FROM @c12;
EXECUTE s12;
DEALLOCATE PREPARE s12;

-- 20261021235726_01_invoices__0_core.sql
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `invoice_number` VARCHAR(50) NULL,
  `number_year` SMALLINT UNSIGNED NULL,
  `number_seq` INT UNSIGNED NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `issue_date` DATE NOT NULL,
  `due_date` DATE NULL,
  `stage` VARCHAR(50) NOT NULL,
  `description` TEXT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  `tax_rate_percent` DECIMAL(5,2) NULL,
  `tax_amount` DECIMAL(10,2) NULL,
  `total` DECIMAL(10,2) NULL,
  `payment_method` VARCHAR(50) NULL,
  `customer_service_id` INT UNSIGNED NULL,
  `installment_id` INT UNSIGNED NULL,
  `sdi_status` VARCHAR(20) NULL,
  `sdi_provider` VARCHAR(30) NULL,
  `sdi_reference` VARCHAR(100) NULL,
  `sent_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_invoices_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `company_profiles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `company_name` VARCHAR(150) NOT NULL,
  `vat_number` VARCHAR(20) NOT NULL,
  `tax_code` VARCHAR(20) NULL,
  `tax_regime` VARCHAR(4) NOT NULL,
  `address` VARCHAR(190) NOT NULL,
  `postal_code` VARCHAR(10) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `province` VARCHAR(2) NOT NULL,
  `country` VARCHAR(2) NULL,
  `email` VARCHAR(190) NULL,
  `pec` VARCHAR(190) NULL,
  `phone` VARCHAR(30) NULL,
  `iban` VARCHAR(34) NULL,
  `default_tax_rate` DECIMAL(5,2) NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 20261021235726_02_editorial__0_core.sql
CREATE TABLE IF NOT EXISTS `editorial_posts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `customer_id` INT UNSIGNED NOT NULL,
  `channel` VARCHAR(20) NOT NULL,
  `format` VARCHAR(20) NOT NULL,
  `publish_date` DATE NOT NULL,
  `publish_time` VARCHAR(5) NULL,
  `title` VARCHAR(200) NOT NULL,
  `caption` TEXT NULL,
  `media_notes` VARCHAR(255) NULL,
  `stage` VARCHAR(20) NOT NULL,
  `assigned_user_id` INT UNSIGNED NULL,
  `client_feedback` TEXT NULL,
  `approved_at` DATETIME NULL,
  `published_url` VARCHAR(255) NULL,
  `task_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_editorial_posts_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_editorial_posts_assigned_user_id` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `editorial_quotas` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `customer_id` INT UNSIGNED NOT NULL,
  `channel` VARCHAR(20) NOT NULL,
  `per_week` TINYINT UNSIGNED NOT NULL,
  `formats` VARCHAR(100) NULL,
  `default_user_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_editorial_quotas_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_editorial_quotas_default_user_id` FOREIGN KEY (`default_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 20261021235726_03_client_health__0_core.sql
CREATE TABLE IF NOT EXISTS `customer_touchpoints` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `customer_id` INT UNSIGNED NOT NULL,
  `contact_date` DATE NOT NULL,
  `channel` VARCHAR(20) NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `mood` VARCHAR(20) NOT NULL,
  `summary` VARCHAR(255) NOT NULL,
  `next_step` VARCHAR(255) NULL,
  `next_date` DATE NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_customer_touchpoints_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_customer_touchpoints_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Permessi di fatture, piano editoriale e temperatura dei clienti, dati al profilo amministratore (id 1).
INSERT INTO permission_groups (parent_id, code, name, description, sort_order, status, created_at, created_by)
SELECT (SELECT id FROM permission_groups WHERE code = 'administration' AND status <> 0), g.code, g.name, g.description, 0, 1, NOW(), 1
FROM (
    SELECT 'invoices' AS code, 'Fatture' AS name, 'Gestione fatture' AS description
    UNION ALL SELECT 'editorial', 'Piano editoriale', 'Contenuti social per cliente e canale'
    UNION ALL SELECT 'client_health', 'Temperatura dei clienti', 'Registro dei contatti e clienti a rischio'
) g
WHERE NOT EXISTS (SELECT 1 FROM permission_groups x WHERE x.code = g.code AND x.status <> 0);

INSERT INTO permissions (permission_group_id, code, name, description, category, sort_order, status, created_at, created_by)
SELECT pg.id, p.code, p.name, p.description, p.category, 0, 1, NOW(), 1
FROM permission_groups pg
JOIN (
    SELECT 'invoices' AS grp, 'invoices.view' AS code, 'Visualizzare fatture' AS name, 'Vedere l''elenco e aprire il dettaglio.' AS description, 'view' AS category
    UNION ALL SELECT 'invoices', 'invoices.create', 'Creare fatture', 'Aggiungere nuovi elementi.', 'create'
    UNION ALL SELECT 'invoices', 'invoices.edit', 'Modificare fatture', 'Modificare elementi esistenti.', 'edit'
    UNION ALL SELECT 'invoices', 'invoices.delete', 'Eliminare fatture', 'Eliminare elementi esistenti.', 'delete'
    UNION ALL SELECT 'editorial', 'editorial.view', 'Visualizzare piano editoriale', 'Vedere l''elenco e aprire il dettaglio.', 'view'
    UNION ALL SELECT 'editorial', 'editorial.create', 'Creare piano editoriale', 'Aggiungere nuovi elementi.', 'create'
    UNION ALL SELECT 'editorial', 'editorial.edit', 'Modificare piano editoriale', 'Modificare elementi esistenti.', 'edit'
    UNION ALL SELECT 'editorial', 'editorial.delete', 'Eliminare piano editoriale', 'Eliminare elementi esistenti.', 'delete'
    UNION ALL SELECT 'client_health', 'client_health.view', 'Visualizzare temperatura dei clienti', 'Vedere l''elenco e aprire il dettaglio.', 'view'
    UNION ALL SELECT 'client_health', 'client_health.create', 'Creare temperatura dei clienti', 'Aggiungere nuovi elementi.', 'create'
    UNION ALL SELECT 'client_health', 'client_health.edit', 'Modificare temperatura dei clienti', 'Modificare elementi esistenti.', 'edit'
    UNION ALL SELECT 'client_health', 'client_health.delete', 'Eliminare temperatura dei clienti', 'Eliminare elementi esistenti.', 'delete'
) p ON p.grp = pg.code
WHERE pg.status <> 0
  AND NOT EXISTS (SELECT 1 FROM permissions x WHERE x.code = p.code AND x.status <> 0);

INSERT INTO profile_permissions (profile_id, permission_id, status, created_at, created_by)
SELECT 1, p.id, 1, NOW(), 1
FROM permissions p
WHERE p.status <> 0 AND (p.code LIKE 'invoices.%' OR p.code LIKE 'editorial.%' OR p.code LIKE 'client_health.%')
  AND NOT EXISTS (SELECT 1 FROM profile_permissions x WHERE x.profile_id = 1 AND x.permission_id = p.id AND x.status <> 0);


-- ======================================================================
-- Parte: 2026_10_23_0300_uno_preventivi_richieste_archivio_ferie_spese
-- ======================================================================
-- Uno produzione (prod_aib_uno): piano editoriale con approvazione interna e strategie, squadra dei clienti,
-- archivio foto e video con descrizione IA, richieste dei clienti, ferie e permessi, spese, preventivi, pro forma.
-- Da lanciare DOPO gli script precedenti (fino a 2026_10_22_1500_uno_editoriale_fatture_pagamenti_clienti.sql). Si puo' rilanciare.
-- Chi e' collegato deve uscire e rientrare.

SET NAMES utf8mb4;

-- 20261023010000_01_editorial__1_roles_approvals.sql
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'editorial_posts' AND COLUMN_NAME = 'writer_user_id') = 0, 'ALTER TABLE `editorial_posts` ADD COLUMN `writer_user_id` INT UNSIGNED NULL', 'DO 0') INTO @m1;
PREPARE s1 FROM @m1;
EXECUTE s1;
DEALLOCATE PREPARE s1;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'editorial_posts' AND COLUMN_NAME = 'source_link') = 0, 'ALTER TABLE `editorial_posts` ADD COLUMN `source_link` VARCHAR(500) NULL', 'DO 0') INTO @m2;
PREPARE s2 FROM @m2;
EXECUTE s2;
DEALLOCATE PREPARE s2;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'editorial_posts' AND COLUMN_NAME = 'output_link') = 0, 'ALTER TABLE `editorial_posts` ADD COLUMN `output_link` VARCHAR(500) NULL', 'DO 0') INTO @m3;
PREPARE s3 FROM @m3;
EXECUTE s3;
DEALLOCATE PREPARE s3;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'editorial_posts' AND COLUMN_NAME = 'parent_post_id') = 0, 'ALTER TABLE `editorial_posts` ADD COLUMN `parent_post_id` INT UNSIGNED NULL', 'DO 0') INTO @m4;
PREPARE s4 FROM @m4;
EXECUTE s4;
DEALLOCATE PREPARE s4;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'editorial_posts' AND COLUMN_NAME = 'review_note') = 0, 'ALTER TABLE `editorial_posts` ADD COLUMN `review_note` TEXT NULL', 'DO 0') INTO @m5;
PREPARE s5 FROM @m5;
EXECUTE s5;
DEALLOCATE PREPARE s5;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'editorial_posts' AND COLUMN_NAME = 'internal_approved_by') = 0, 'ALTER TABLE `editorial_posts` ADD COLUMN `internal_approved_by` INT UNSIGNED NULL', 'DO 0') INTO @m6;
PREPARE s6 FROM @m6;
EXECUTE s6;
DEALLOCATE PREPARE s6;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'editorial_posts' AND COLUMN_NAME = 'internal_approved_at') = 0, 'ALTER TABLE `editorial_posts` ADD COLUMN `internal_approved_at` DATETIME NULL', 'DO 0') INTO @m7;
PREPARE s7 FROM @m7;
EXECUTE s7;
DEALLOCATE PREPARE s7;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'editorial_posts' AND COLUMN_NAME = 'approved_via') = 0, 'ALTER TABLE `editorial_posts` ADD COLUMN `approved_via` VARCHAR(60) NULL', 'DO 0') INTO @m8;
PREPARE s8 FROM @m8;
EXECUTE s8;
DEALLOCATE PREPARE s8;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'editorial_posts' AND COLUMN_NAME = 'revision_count') = 0, 'ALTER TABLE `editorial_posts` ADD COLUMN `revision_count` SMALLINT UNSIGNED NULL', 'DO 0') INTO @m9;
PREPARE s9 FROM @m9;
EXECUTE s9;
DEALLOCATE PREPARE s9;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'editorial_posts' AND COLUMN_NAME = 'writer_task_id') = 0, 'ALTER TABLE `editorial_posts` ADD COLUMN `writer_task_id` INT UNSIGNED NULL', 'DO 0') INTO @m10;
PREPARE s10 FROM @m10;
EXECUTE s10;
DEALLOCATE PREPARE s10;

-- 20261023010000_02_editorial__2_strategies.sql
CREATE TABLE IF NOT EXISTS `editorial_strategies` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `customer_id` INT UNSIGNED NOT NULL,
  `objectives` TEXT NULL,
  `audience` TEXT NULL,
  `tone` VARCHAR(255) NULL,
  `pillars` TEXT NULL,
  `dos_donts` TEXT NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_editorial_strategies_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 20261023010000_03_customers__2_team.sql
CREATE TABLE IF NOT EXISTS `customer_team_members` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `customer_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `role` VARCHAR(20) NOT NULL,
  `notes` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_customer_team_members_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_customer_team_members_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 20261023010000_04_customer_services__2_reference.sql
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_services' AND COLUMN_NAME = 'reference') = 0, 'ALTER TABLE `customer_services` ADD COLUMN `reference` VARCHAR(190) NULL', 'DO 0') INTO @m11;
PREPARE s11 FROM @m11;
EXECUTE s11;
DEALLOCATE PREPARE s11;

-- 20261023010000_05_invoices__1_proforma.sql
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND COLUMN_NAME = 'document_type') = 0, 'ALTER TABLE `invoices` ADD COLUMN `document_type` VARCHAR(20) NULL', 'DO 0') INTO @m12;
PREPARE s12 FROM @m12;
EXECUTE s12;
DEALLOCATE PREPARE s12;

-- 20261023001159_01_media_library__0_core.sql
CREATE TABLE IF NOT EXISTS `media_assets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `source` VARCHAR(20) NOT NULL,
  `external_id` VARCHAR(190) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `kind` VARCHAR(10) NOT NULL,
  `mime_type` VARCHAR(100) NULL,
  `folder` VARCHAR(500) NULL,
  `customer_id` INT UNSIGNED NULL,
  `web_link` VARCHAR(500) NULL,
  `size_bytes` BIGINT UNSIGNED NULL,
  `width` INT UNSIGNED NULL,
  `height` INT UNSIGNED NULL,
  `duration_seconds` INT UNSIGNED NULL,
  `modified_at` DATETIME NULL,
  `analysis_status` VARCHAR(20) NOT NULL,
  `analysis_error` VARCHAR(255) NULL,
  `description` TEXT NULL,
  `tags` VARCHAR(500) NULL,
  `people_count` SMALLINT UNSIGNED NULL,
  `setting` VARCHAR(20) NULL,
  `occasion` VARCHAR(100) NULL,
  `orientation` VARCHAR(12) NULL,
  `analysis` JSON NULL,
  `thumb_path` VARCHAR(255) NULL,
  `analyzed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_media_assets_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 20261023011500_01_media_library__1_indexes.sql
SELECT IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'media_assets' AND INDEX_NAME = 'uq_media_assets_source_external') = 0, 'ALTER TABLE `media_assets` ADD UNIQUE KEY `uq_media_assets_source_external` (`source`, `external_id`)', 'DO 0') INTO @m13;
PREPARE s13 FROM @m13;
EXECUTE s13;
DEALLOCATE PREPARE s13;
SELECT IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'media_assets' AND INDEX_NAME = 'ix_media_assets_status') = 0, 'ALTER TABLE `media_assets` ADD KEY `ix_media_assets_status` (`analysis_status`)', 'DO 0') INTO @m14;
PREPARE s14 FROM @m14;
EXECUTE s14;
DEALLOCATE PREPARE s14;

-- 20261023004210_01_client_requests__0_core.sql
CREATE TABLE IF NOT EXISTS `client_requests` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `customer_id` INT UNSIGNED NOT NULL,
  `summary` VARCHAR(255) NOT NULL,
  `details` TEXT NULL,
  `kind` VARCHAR(30) NULL,
  `channel` VARCHAR(20) NOT NULL,
  `received_at` DATETIME NOT NULL,
  `received_by_user_id` INT UNSIGNED NULL,
  `assigned_user_id` INT UNSIGNED NULL,
  `stage` VARCHAR(20) NOT NULL,
  `priority` VARCHAR(10) NULL,
  `due_date` DATE NULL,
  `estimated_minutes` SMALLINT UNSIGNED NULL,
  `is_extra` TINYINT(1) NULL,
  `extra_amount` DECIMAL(10,2) NULL,
  `installment_id` INT UNSIGNED NULL,
  `task_id` INT UNSIGNED NULL,
  `resolution` VARCHAR(255) NULL,
  `done_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_client_requests_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_client_requests_received_by_user_id` FOREIGN KEY (`received_by_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_client_requests_assigned_user_id` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 20261023004450_01_leaves__0_core.sql
CREATE TABLE IF NOT EXISTS `leave_requests` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `user_id` INT UNSIGNED NOT NULL,
  `kind` VARCHAR(20) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NULL,
  `start_time` VARCHAR(5) NULL,
  `end_time` VARCHAR(5) NULL,
  `stage` VARCHAR(20) NOT NULL,
  `note` VARCHAR(255) NULL,
  `decided_by` INT UNSIGNED NULL,
  `decided_at` DATETIME NULL,
  `reply` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_leave_requests_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_leave_requests_decided_by` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 20261023004450_02_expenses__0_core.sql
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `user_id` INT UNSIGNED NOT NULL,
  `expense_date` DATE NOT NULL,
  `kind` VARCHAR(20) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `description` VARCHAR(255) NULL,
  `customer_id` INT UNSIGNED NULL,
  `work_order_id` INT UNSIGNED NULL,
  `paid_by` VARCHAR(20) NULL,
  `rebill` TINYINT(1) NULL,
  `receipt_link` VARCHAR(500) NULL,
  `stage` VARCHAR(20) NOT NULL,
  `decided_by` INT UNSIGNED NULL,
  `decided_at` DATETIME NULL,
  `reimbursed_at` DATE NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_expenses_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_expenses_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_expenses_decided_by` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 20261023004833_01_quotes__0_core.sql
CREATE TABLE IF NOT EXISTS `quotes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `customer_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `quote_number` VARCHAR(30) NULL,
  `number_year` SMALLINT UNSIGNED NULL,
  `number_seq` INT UNSIGNED NULL,
  `quote_date` DATE NOT NULL,
  `valid_until` DATE NULL,
  `stage` VARCHAR(20) NOT NULL,
  `owner_user_id` INT UNSIGNED NULL,
  `intro` TEXT NULL,
  `conditions` TEXT NULL,
  `payment_terms` VARCHAR(255) NULL,
  `included_revisions` TINYINT UNSIGNED NULL,
  `discount_percent` DECIMAL(5,2) NULL,
  `total_one_off` DECIMAL(10,2) NULL,
  `total_yearly` DECIMAL(10,2) NULL,
  `approved_by` INT UNSIGNED NULL,
  `approved_at` DATETIME NULL,
  `sent_at` DATETIME NULL,
  `accepted_at` DATETIME NULL,
  `accepted_via` VARCHAR(60) NULL,
  `rejected_reason` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_quotes_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_quotes_owner_user_id` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_quotes_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `quote_lines` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `quote_id` INT UNSIGNED NOT NULL,
  `service_id` INT UNSIGNED NULL,
  `description` VARCHAR(255) NOT NULL,
  `details` TEXT NULL,
  `quantity` DECIMAL(10,2) NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `billing_type` VARCHAR(20) NULL,
  `recurrence` VARCHAR(20) NULL,
  `payment_plan` VARCHAR(30) NULL,
  `deposit_percent` DECIMAL(5,2) NULL,
  `installments_count` TINYINT UNSIGNED NULL,
  `start_date` DATE NULL,
  `position` SMALLINT UNSIGNED NULL,
  `customer_service_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_quote_lines_quote_id` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`),
  CONSTRAINT `fk_quote_lines_service_id` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Permessi nuovi, dati al profilo amministratore (id 1).
INSERT INTO permission_groups (parent_id, code, name, description, sort_order, status, created_at, created_by)
SELECT (SELECT id FROM permission_groups WHERE code = 'administration' AND status <> 0), g.code, g.name, g.description, 0, 1, NOW(), 1
FROM (
    SELECT 'editorial' AS code, 'Piano editoriale' AS name, 'Contenuti social per cliente e canale' AS description
    UNION ALL SELECT 'media_library', 'Archivio foto e video', 'Foto e video descritti dall''IA e ricercabili a parole'
    UNION ALL SELECT 'client_requests', 'Richieste dei clienti', 'Richieste da telefono, WhatsApp, email e lavori extra'
    UNION ALL SELECT 'leaves', 'Ferie e permessi', 'Richieste di ferie e permessi con approvazione'
    UNION ALL SELECT 'expenses', 'Spese', 'Spese dei lavori con approvazione e rimborso'
    UNION ALL SELECT 'quotes', 'Preventivi', 'Preventivi con approvazione e accettazione del cliente'
) g
WHERE NOT EXISTS (SELECT 1 FROM permission_groups x WHERE x.code = g.code AND x.status <> 0);

INSERT INTO permissions (permission_group_id, code, name, description, category, sort_order, status, created_at, created_by)
SELECT pg.id, p.code, p.name, p.description, p.category, 0, 1, NOW(), 1
FROM permission_groups pg
JOIN (
    SELECT 'editorial' AS grp, 'editorial.approve' AS code, 'Approvare i contenuti' AS name, 'Revisione interna: approvare i contenuti prima che li veda il cliente.' AS description, 'edit' AS category
    UNION ALL SELECT 'media_library', 'media_library.view', 'Cercare nell''archivio', 'Cercare foto e video e aprirli.', 'view'
    UNION ALL SELECT 'media_library', 'media_library.edit', 'Correggere l''archivio', 'Correggere descrizioni e parole chiave, rifare l''analisi, sincronizzare.', 'edit'
    UNION ALL SELECT 'client_requests', 'client_requests.view', 'Vedere le richieste', 'Le richieste dei clienti e a chi sono assegnate.', 'view'
    UNION ALL SELECT 'client_requests', 'client_requests.create', 'Segnare le richieste', 'Registrare una richiesta arrivata da un cliente.', 'create'
    UNION ALL SELECT 'client_requests', 'client_requests.edit', 'Lavorare le richieste', 'Prenderle in carico, chiuderle, modificarle.', 'edit'
    UNION ALL SELECT 'client_requests', 'client_requests.delete', 'Eliminare le richieste', 'Eliminare richieste segnate per errore.', 'delete'
    UNION ALL SELECT 'client_requests', 'client_requests.assign', 'Smistare le richieste', 'Assegnare le richieste nuove: le vede anche nell''organizer.', 'edit'
    UNION ALL SELECT 'leaves', 'leaves.view', 'Chiedere ferie e permessi', 'Chiedere le proprie ferie e i propri permessi e vederli.', 'view'
    UNION ALL SELECT 'leaves', 'leaves.approve', 'Approvare ferie e permessi', 'Vedere quelle di tutti, approvarle o rifiutarle.', 'edit'
    UNION ALL SELECT 'expenses', 'expenses.view', 'Segnare le spese', 'Segnare le proprie spese e vederle.', 'view'
    UNION ALL SELECT 'expenses', 'expenses.approve', 'Approvare e rimborsare le spese', 'Vedere le spese di tutti, approvarle, rifiutarle, segnarle rimborsate.', 'edit'
    UNION ALL SELECT 'quotes', 'quotes.view', 'Visualizzare preventivi', 'Vedere l''elenco e aprire il dettaglio.', 'view'
    UNION ALL SELECT 'quotes', 'quotes.create', 'Creare preventivi', 'Preparare preventivi nuovi.', 'create'
    UNION ALL SELECT 'quotes', 'quotes.edit', 'Modificare preventivi', 'Modificare preventivi e righe, segnarli inviati.', 'edit'
    UNION ALL SELECT 'quotes', 'quotes.delete', 'Eliminare preventivi', 'Eliminare preventivi.', 'delete'
    UNION ALL SELECT 'quotes', 'quotes.approve', 'Approvare preventivi', 'Approvare i preventivi prima che partano.', 'edit'
) p ON p.grp = pg.code
WHERE pg.status <> 0
  AND NOT EXISTS (SELECT 1 FROM permissions x WHERE x.code = p.code AND x.status <> 0);

INSERT INTO profile_permissions (profile_id, permission_id, status, created_at, created_by)
SELECT 1, p.id, 1, NOW(), 1
FROM permissions p
WHERE p.status <> 0 AND p.code IN ('editorial.approve', 'media_library.view', 'media_library.edit', 'client_requests.view', 'client_requests.create', 'client_requests.edit', 'client_requests.delete', 'client_requests.assign', 'leaves.view', 'leaves.approve', 'expenses.view', 'expenses.approve', 'quotes.view', 'quotes.create', 'quotes.edit', 'quotes.delete', 'quotes.approve')
  AND NOT EXISTS (SELECT 1 FROM profile_permissions x WHERE x.profile_id = 1 AND x.permission_id = p.id AND x.status <> 0);


-- ======================================================================
-- Parte: 2026_11_25_1200_uno_rilascio_processi_agenti_documenti
-- ======================================================================
-- Uno produzione (prod_aib_uno): tutte le query da eseguire per il rilascio del 25/11/2026.
-- Processi dei servizi (fasi, ruoli, date chiave, consegne, squadra), avvisi, agenti e periodi forti,
-- conoscenza del cliente e roadmap, documenti sul Drive, qualificazione dei clienti, foto delle persone,
-- prezzo facoltativo dei servizi, spazio di lavoro delle fasi.
-- Da lanciare DOPO gli script precedenti (fino a 2026_10_23_0300_uno_preventivi_richieste_archivio_ferie_spese.sql)
-- e PRIMA di pubblicare il codice. Si puo' rilanciare. Chi e' collegato deve uscire e rientrare.

SET NAMES utf8mb4;


-- 20261118120000_01_work_orders__2_processes.sql
-- Processi dei servizi (design/processi-servizi.md): il tipo di commessa
-- diventa il processo di un servizio, con ruoli, date chiave e cose da
-- consegnare; la commessa diventa il progetto, dal primo contatto.
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'work_order_types' AND COLUMN_NAME = 'service_id') = 0, 'ALTER TABLE `work_order_types` ADD COLUMN `service_id` INT UNSIGNED NULL', 'DO 0') INTO @m1;
PREPARE s1 FROM @m1;
EXECUTE s1;
DEALLOCATE PREPARE s1;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'work_order_type_steps' AND COLUMN_NAME = 'phase') = 0, 'ALTER TABLE `work_order_type_steps` ADD COLUMN `phase` VARCHAR(60) NULL', 'DO 0') INTO @m2;
PREPARE s2 FROM @m2;
EXECUTE s2;
DEALLOCATE PREPARE s2;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'work_order_type_steps' AND COLUMN_NAME = 'kind') = 0, 'ALTER TABLE `work_order_type_steps` ADD COLUMN `kind` VARCHAR(30) NULL', 'DO 0') INTO @m3;
PREPARE s3 FROM @m3;
EXECUTE s3;
DEALLOCATE PREPARE s3;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'work_order_type_steps' AND COLUMN_NAME = 'role_id') = 0, 'ALTER TABLE `work_order_type_steps` ADD COLUMN `role_id` INT UNSIGNED NULL', 'DO 0') INTO @m4;
PREPARE s4 FROM @m4;
EXECUTE s4;
DEALLOCATE PREPARE s4;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'work_order_type_steps' AND COLUMN_NAME = 'milestone_id') = 0, 'ALTER TABLE `work_order_type_steps` ADD COLUMN `milestone_id` INT UNSIGNED NULL', 'DO 0') INTO @m5;
PREPARE s5 FROM @m5;
EXECUTE s5;
DEALLOCATE PREPARE s5;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'work_order_type_steps' AND COLUMN_NAME = 'completes_on') = 0, 'ALTER TABLE `work_order_type_steps` ADD COLUMN `completes_on` VARCHAR(40) NULL', 'DO 0') INTO @m6;
PREPARE s6 FROM @m6;
EXECUTE s6;
DEALLOCATE PREPARE s6;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'work_order_type_steps' AND COLUMN_NAME = 'reject_step_id') = 0, 'ALTER TABLE `work_order_type_steps` ADD COLUMN `reject_step_id` INT UNSIGNED NULL', 'DO 0') INTO @m7;
PREPARE s7 FROM @m7;
EXECUTE s7;
DEALLOCATE PREPARE s7;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'work_order_type_steps' AND COLUMN_NAME = 'is_repeatable') = 0, 'ALTER TABLE `work_order_type_steps` ADD COLUMN `is_repeatable` TINYINT(1) NULL', 'DO 0') INTO @m8;
PREPARE s8 FROM @m8;
EXECUTE s8;
DEALLOCATE PREPARE s8;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'work_order_type_steps' AND COLUMN_NAME = 'is_optional') = 0, 'ALTER TABLE `work_order_type_steps` ADD COLUMN `is_optional` TINYINT(1) NULL', 'DO 0') INTO @m9;
PREPARE s9 FROM @m9;
EXECUTE s9;
DEALLOCATE PREPARE s9;
ALTER TABLE `work_order_type_steps` MODIFY COLUMN `offset_days` SMALLINT UNSIGNED NULL;
CREATE TABLE IF NOT EXISTS `work_order_type_roles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `work_order_type_id` INT UNSIGNED NOT NULL,
  `position` SMALLINT UNSIGNED NOT NULL,
  `label` VARCHAR(80) NOT NULL,
  `description` TEXT NULL,
  `default_user_id` INT UNSIGNED NULL,
  `customer_team_role` VARCHAR(20) NULL,
  `is_multiple` TINYINT(1) NULL,
  `is_required` TINYINT(1) NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_work_order_type_roles_work_order_type_id` FOREIGN KEY (`work_order_type_id`) REFERENCES `work_order_types` (`id`),
  CONSTRAINT `fk_work_order_type_roles_default_user_id` FOREIGN KEY (`default_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `work_order_type_milestones` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `work_order_type_id` INT UNSIGNED NOT NULL,
  `position` SMALLINT UNSIGNED NOT NULL,
  `label` VARCHAR(80) NOT NULL,
  `set_by_role_id` INT UNSIGNED NULL,
  `after_step_id` INT UNSIGNED NULL,
  `creates_appointment` TINYINT(1) NULL,
  `client_email_subject` VARCHAR(200) NULL,
  `client_email_body` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_work_order_type_milestones_work_order_type_id` FOREIGN KEY (`work_order_type_id`) REFERENCES `work_order_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `work_order_type_deliverables` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `work_order_type_id` INT UNSIGNED NOT NULL,
  `position` SMALLINT UNSIGNED NOT NULL,
  `label` VARCHAR(120) NOT NULL,
  `description` TEXT NULL,
  `always_included` TINYINT(1) NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_work_order_type_deliverables_work_order_type_id` FOREIGN KEY (`work_order_type_id`) REFERENCES `work_order_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'work_orders' AND COLUMN_NAME = 'quote_id') = 0, 'ALTER TABLE `work_orders` ADD COLUMN `quote_id` INT UNSIGNED NULL', 'DO 0') INTO @m10;
PREPARE s10 FROM @m10;
EXECUTE s10;
DEALLOCATE PREPARE s10;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'work_orders' AND COLUMN_NAME = 'source_channel') = 0, 'ALTER TABLE `work_orders` ADD COLUMN `source_channel` VARCHAR(30) NULL', 'DO 0') INTO @m11;
PREPARE s11 FROM @m11;
EXECUTE s11;
DEALLOCATE PREPARE s11;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'work_orders' AND COLUMN_NAME = 'lost_reason') = 0, 'ALTER TABLE `work_orders` ADD COLUMN `lost_reason` VARCHAR(255) NULL', 'DO 0') INTO @m12;
PREPARE s12 FROM @m12;
EXECUTE s12;
DEALLOCATE PREPARE s12;
CREATE TABLE IF NOT EXISTS `work_order_members` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `work_order_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `role_id` INT UNSIGNED NULL,
  `notes` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_work_order_members_work_order_id` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`),
  CONSTRAINT `fk_work_order_members_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `work_order_milestones` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `work_order_id` INT UNSIGNED NOT NULL,
  `milestone_id` INT UNSIGNED NULL,
  `position` SMALLINT UNSIGNED NOT NULL,
  `label` VARCHAR(80) NOT NULL,
  `scheduled_at` DATETIME NULL,
  `send_client_email` TINYINT(1) NULL,
  `client_email_to` VARCHAR(255) NULL,
  `client_email_subject` VARCHAR(200) NULL,
  `client_email_body` TEXT NULL,
  `client_email_sent_at` DATETIME NULL,
  `appointment_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_work_order_milestones_work_order_id` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `work_order_deliverables` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `work_order_id` INT UNSIGNED NOT NULL,
  `position` SMALLINT UNSIGNED NOT NULL,
  `label` VARCHAR(120) NOT NULL,
  `description` TEXT NULL,
  `is_done` TINYINT(1) NULL,
  `done_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_work_order_deliverables_work_order_id` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `work_order_change_requests` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `work_order_id` INT UNSIGNED NOT NULL,
  `requested_at` DATE NOT NULL,
  `description` TEXT NOT NULL,
  `assigned_user_id` INT UNSIGNED NULL,
  `is_extra` TINYINT(1) NULL,
  `stage` VARCHAR(20) NOT NULL,
  `quote_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_work_order_change_requests_work_order_id` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`),
  CONSTRAINT `fk_work_order_change_requests_assigned_user_id` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'iteration') = 0, 'ALTER TABLE `tasks` ADD COLUMN `iteration` SMALLINT UNSIGNED NULL', 'DO 0') INTO @m13;
PREPARE s13 FROM @m13;
EXECUTE s13;
DEALLOCATE PREPARE s13;

-- 20261118120000_02_notifications__0_core.sql
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(40) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `body` TEXT NULL,
  `url` VARCHAR(255) NULL,
  `entity_type` VARCHAR(50) NULL,
  `entity_id` INT UNSIGNED NULL,
  `in_app` TINYINT(1) NOT NULL DEFAULT 1,
  `read_at` DATETIME NULL,
  `email_due` TINYINT(1) NULL,
  `emailed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  KEY `idx_notifications_user_unread` (`user_id`, `read_at`),
  KEY `idx_notifications_email` (`email_due`, `emailed_at`),
  CONSTRAINT `fk_notifications_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `notification_preferences` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(40) NOT NULL,
  `in_app` TINYINT(1) NOT NULL DEFAULT 1,
  `email` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  UNIQUE KEY `uq_notification_preferences_user_type` (`user_id`, `type`),
  CONSTRAINT `fk_notification_preferences_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 20261118125141_01_assistant__0_core.sql
CREATE TABLE IF NOT EXISTS `assistant_rules` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `code` VARCHAR(50) NOT NULL,
  `is_enabled` TINYINT(1) NOT NULL,
  `days` SMALLINT UNSIGNED NULL,
  `autonomy` VARCHAR(20) NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `assistant_actions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `rule_code` VARCHAR(50) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `entity_type` VARCHAR(50) NULL,
  `entity_id` INT UNSIGNED NULL,
  `dedupe_key` VARCHAR(190) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `body` TEXT NULL,
  `email_to` VARCHAR(255) NULL,
  `email_subject` VARCHAR(200) NULL,
  `email_body` TEXT NULL,
  `stage` VARCHAR(20) NOT NULL,
  `decided_at` DATETIME NULL,
  `decided_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_assistant_actions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SELECT IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assistant_actions' AND INDEX_NAME = 'idx_assistant_actions_key') = 0, 'ALTER TABLE `assistant_actions` ADD KEY `idx_assistant_actions_key` (`dedupe_key`)', 'DO 0') INTO @m14;
PREPARE s14 FROM @m14;
EXECUTE s14;
DEALLOCATE PREPARE s14;
SELECT IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assistant_actions' AND INDEX_NAME = 'idx_assistant_actions_user') = 0, 'ALTER TABLE `assistant_actions` ADD KEY `idx_assistant_actions_user` (`user_id`, `stage`)', 'DO 0') INTO @m15;
PREPARE s15 FROM @m15;
EXECUTE s15;
DEALLOCATE PREPARE s15;
SELECT IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assistant_rules' AND INDEX_NAME = 'idx_assistant_rules_code') = 0, 'ALTER TABLE `assistant_rules` ADD KEY `idx_assistant_rules_code` (`code`)', 'DO 0') INTO @m16;
PREPARE s16 FROM @m16;
EXECUTE s16;
DEALLOCATE PREPARE s16;

-- 20261118133836_01_client_knowledge__0_core.sql
CREATE TABLE IF NOT EXISTS `customer_profiles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `customer_id` INT UNSIGNED NOT NULL,
  `history` TEXT NULL,
  `products` TEXT NULL,
  `mission` TEXT NULL,
  `audience` TEXT NULL,
  `competitors` TEXT NULL,
  `style_likes` TEXT NULL,
  `style_dislikes` TEXT NULL,
  `tone_of_voice` TEXT NULL,
  `brand_usage` TEXT NULL,
  `seasonality` TEXT NULL,
  `notes` TEXT NULL,
  `last_source` VARCHAR(60) NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_customer_profiles_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `customer_notes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `customer_id` INT UNSIGNED NOT NULL,
  `work_order_id` INT UNSIGNED NULL,
  `kind` VARCHAR(20) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `body` TEXT NULL,
  `noted_at` DATE NOT NULL,
  `source` VARCHAR(20) NULL,
  `on_roadmap` TINYINT(1) NULL,
  `roadmap_status` VARCHAR(30) NULL,
  `due_date` DATE NULL,
  `mentioned_user_ids` VARCHAR(255) NULL,
  `author_user_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_customer_notes_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_customer_notes_author_user_id` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 20261118135503_01_documents__0_core.sql
CREATE TABLE IF NOT EXISTS `document_folders` (
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
CREATE TABLE IF NOT EXISTS `documents` (
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
SELECT IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_folders' AND INDEX_NAME = 'idx_document_folders_ext') = 0, 'ALTER TABLE `document_folders` ADD KEY `idx_document_folders_ext` (`external_id`)', 'DO 0') INTO @m17;
PREPARE s17 FROM @m17;
EXECUTE s17;
DEALLOCATE PREPARE s17;
SELECT IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_folders' AND INDEX_NAME = 'idx_document_folders_parent') = 0, 'ALTER TABLE `document_folders` ADD KEY `idx_document_folders_parent` (`parent_id`)', 'DO 0') INTO @m18;
PREPARE s18 FROM @m18;
EXECUTE s18;
DEALLOCATE PREPARE s18;
SELECT IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_folders' AND INDEX_NAME = 'idx_document_folders_wo') = 0, 'ALTER TABLE `document_folders` ADD KEY `idx_document_folders_wo` (`work_order_id`)', 'DO 0') INTO @m19;
PREPARE s19 FROM @m19;
EXECUTE s19;
DEALLOCATE PREPARE s19;
SELECT IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_folders' AND INDEX_NAME = 'idx_document_folders_customer') = 0, 'ALTER TABLE `document_folders` ADD KEY `idx_document_folders_customer` (`customer_id`)', 'DO 0') INTO @m20;
PREPARE s20 FROM @m20;
EXECUTE s20;
DEALLOCATE PREPARE s20;
SELECT IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND INDEX_NAME = 'idx_documents_ext') = 0, 'ALTER TABLE `documents` ADD KEY `idx_documents_ext` (`external_id`)', 'DO 0') INTO @m21;
PREPARE s21 FROM @m21;
EXECUTE s21;
DEALLOCATE PREPARE s21;
SELECT IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND INDEX_NAME = 'idx_documents_folder') = 0, 'ALTER TABLE `documents` ADD KEY `idx_documents_folder` (`folder_id`)', 'DO 0') INTO @m22;
PREPARE s22 FROM @m22;
EXECUTE s22;
DEALLOCATE PREPARE s22;
SELECT IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND INDEX_NAME = 'idx_documents_customer') = 0, 'ALTER TABLE `documents` ADD KEY `idx_documents_customer` (`customer_id`)', 'DO 0') INTO @m23;
PREPARE s23 FROM @m23;
EXECUTE s23;
DEALLOCATE PREPARE s23;

-- 20261118160000_01_work_orders__3_folder_template.sql
-- Le cartelle di ogni progetto nuovo, una per riga (Documenti).
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'work_order_types' AND COLUMN_NAME = 'folder_template') = 0, 'ALTER TABLE `work_order_types` ADD COLUMN `folder_template` TEXT NULL', 'DO 0') INTO @m24;
PREPARE s24 FROM @m24;
EXECUTE s24;
DEALLOCATE PREPARE s24;

-- 20261118170000_01_customers__3_qualification.sql
-- Qualificazione del cliente (fase 5): stato commerciale, temperatura, com'e' da seguire, quando risentirlo.
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'commercial_stage') = 0, 'ALTER TABLE `customers` ADD COLUMN `commercial_stage` VARCHAR(20) NULL', 'DO 0') INTO @m25;
PREPARE s25 FROM @m25;
EXECUTE s25;
DEALLOCATE PREPARE s25;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'temperature') = 0, 'ALTER TABLE `customers` ADD COLUMN `temperature` VARCHAR(10) NULL', 'DO 0') INTO @m26;
PREPARE s26 FROM @m26;
EXECUTE s26;
DEALLOCATE PREPARE s26;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'temperament') = 0, 'ALTER TABLE `customers` ADD COLUMN `temperament` VARCHAR(20) NULL', 'DO 0') INTO @m27;
PREPARE s27 FROM @m27;
EXECUTE s27;
DEALLOCATE PREPARE s27;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'next_contact_date') = 0, 'ALTER TABLE `customers` ADD COLUMN `next_contact_date` DATE NULL', 'DO 0') INTO @m28;
PREPARE s28 FROM @m28;
EXECUTE s28;
DEALLOCATE PREPARE s28;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'next_contact_note') = 0, 'ALTER TABLE `customers` ADD COLUMN `next_contact_note` VARCHAR(255) NULL', 'DO 0') INTO @m29;
PREPARE s29 FROM @m29;
EXECUTE s29;
DEALLOCATE PREPARE s29;

-- 20261118170000_02_assistant__1_seasonal_events.sql
CREATE TABLE IF NOT EXISTS `seasonal_events` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `status` TINYINT NOT NULL DEFAULT 1,
  `name` VARCHAR(80) NOT NULL,
  `month` SMALLINT UNSIGNED NULL,
  `day` SMALLINT UNSIGNED NULL,
  `easter_offset` SMALLINT NULL,
  `lead_days` SMALLINT UNSIGNED NOT NULL,
  `keywords` VARCHAR(500) NOT NULL,
  `idea` TEXT NULL,
  `is_enabled` TINYINT(1) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL,
  `updated_by` INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 20261124120000_01_assistant__2_manage_permission.sql
-- "Configurare gli agenti": accendere e spegnere le regole, i giorni, cosa fanno da soli, i periodi forti.
INSERT INTO permission_groups (parent_id, code, name, description, sort_order, status, created_at, created_by)
SELECT 1, 'assistant', 'Agenti', 'Gli agenti dell''assistente', 0, 1, NOW(), 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM permission_groups WHERE code = 'assistant' AND status = 1);
INSERT INTO permissions (permission_group_id, code, name, description, category, sort_order, status, created_at, created_by)
SELECT g.id, 'assistant.manage', 'Configurare gli agenti', 'Accendere o spegnere le regole degli agenti, i giorni, cosa fanno da soli, i periodi forti.', 'edit', 0, 1, NOW(), 1
FROM permission_groups g WHERE g.code = 'assistant' AND g.status = 1
  AND NOT EXISTS (SELECT 1 FROM permissions WHERE code = 'assistant.manage' AND status = 1);
INSERT IGNORE INTO profile_permissions (profile_id, permission_id, status, created_at, created_by)
SELECT pr.id, pe.id, 1, NOW(), 1 FROM profiles pr JOIN permissions pe
WHERE pr.status = 1 AND pe.status = 1 AND pe.code = 'assistant.manage'
  AND pr.name IN ('Amministratore', 'Direzione', 'Team leader e amministrazione');

-- 20261124130000_01_core__users_avatar.sql
-- La foto di ciascuno (Il mio profilo): file in public/uploads/avatars, qui l'indirizzo.
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'avatar_path') = 0, 'ALTER TABLE `users` ADD COLUMN `avatar_path` VARCHAR(255) NULL', 'DO 0') INTO @m30;
PREPARE s30 FROM @m30;
EXECUTE s30;
DEALLOCATE PREPARE s30;

-- 20261124140000_01_services__3_optional_price.sql
-- Il prezzo del servizio diventa indicativo e facoltativo: il prezzo vero si decide nel preventivo.
ALTER TABLE `services` MODIFY `price` DECIMAL(10,2) NULL;

-- 20261124200000_01_tasks__3_outcome.sql
-- Quello che si segna in una fase: esito e note, la data dell'esito (es.
-- l'approvazione del cliente), una nota per ogni voce della checklist (JSON).
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'outcome') = 0, 'ALTER TABLE `tasks` ADD COLUMN `outcome` TEXT NULL', 'DO 0') INTO @m31;
PREPARE s31 FROM @m31;
EXECUTE s31;
DEALLOCATE PREPARE s31;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'outcome_date') = 0, 'ALTER TABLE `tasks` ADD COLUMN `outcome_date` DATE NULL', 'DO 0') INTO @m32;
PREPARE s32 FROM @m32;
EXECUTE s32;
DEALLOCATE PREPARE s32;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'item_notes') = 0, 'ALTER TABLE `tasks` ADD COLUMN `item_notes` TEXT NULL', 'DO 0') INTO @m33;
PREPARE s33 FROM @m33;
EXECUTE s33;
DEALLOCATE PREPARE s33;

-- 20261124200000_02_documents__1_task_files.sql
-- I file caricati da una fase: a quale attivita' appartengono e, finche' il
-- Drive non e' collegato, dove sono nel gestionale (poi partono per il Drive).
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'task_id') = 0, 'ALTER TABLE `documents` ADD COLUMN `task_id` INT UNSIGNED NULL', 'DO 0') INTO @m34;
PREPARE s34 FROM @m34;
EXECUTE s34;
DEALLOCATE PREPARE s34;
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'local_path') = 0, 'ALTER TABLE `documents` ADD COLUMN `local_path` VARCHAR(500) NULL', 'DO 0') INTO @m35;
PREPARE s35 FROM @m35;
EXECUTE s35;
DEALLOCATE PREPARE s35;
SELECT IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND INDEX_NAME = 'idx_documents_task') = 0, 'ALTER TABLE `documents` ADD INDEX `idx_documents_task` (`task_id`)', 'DO 0') INTO @m36;
PREPARE s36 FROM @m36;
EXECUTE s36;
DEALLOCATE PREPARE s36;

-- 20261124200000_03_work_orders__4_step_folder.sql
-- La cartella del progetto dove vanno i file caricati da una fase (es. "03 Bozze").
SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'work_order_type_steps' AND COLUMN_NAME = 'folder_name') = 0, 'ALTER TABLE `work_order_type_steps` ADD COLUMN `folder_name` VARCHAR(120) NULL', 'DO 0') INTO @m37;
PREPARE s37 FROM @m37;
EXECUTE s37;
DEALLOCATE PREPARE s37;

-- Permessi dei pacchetti nuovi (li crea l'installazione dei pacchetti, non le migrazioni):
-- conoscenza del cliente e documenti, dati all'Amministratore.
INSERT INTO permission_groups (parent_id, code, name, description, sort_order, status, created_at, created_by)
SELECT 1, 'client_knowledge', 'Conoscenza del cliente', 'Gestione conoscenza del cliente', 0, 1, NOW(), 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM permission_groups WHERE code = 'client_knowledge' AND status = 1);
INSERT INTO permissions (permission_group_id, code, name, description, category, sort_order, status, created_at, created_by)
SELECT g.id, 'client_knowledge.view', 'Visualizzare conoscenza del cliente', 'Vedere l''elenco e aprire il dettaglio.', 'view', 0, 1, NOW(), 1
FROM permission_groups g WHERE g.code = 'client_knowledge' AND g.status = 1
  AND NOT EXISTS (SELECT 1 FROM permissions WHERE code = 'client_knowledge.view' AND status = 1);
INSERT INTO permissions (permission_group_id, code, name, description, category, sort_order, status, created_at, created_by)
SELECT g.id, 'client_knowledge.create', 'Creare conoscenza del cliente', 'Aggiungere nuovi elementi.', 'create', 0, 1, NOW(), 1
FROM permission_groups g WHERE g.code = 'client_knowledge' AND g.status = 1
  AND NOT EXISTS (SELECT 1 FROM permissions WHERE code = 'client_knowledge.create' AND status = 1);
INSERT INTO permissions (permission_group_id, code, name, description, category, sort_order, status, created_at, created_by)
SELECT g.id, 'client_knowledge.edit', 'Modificare conoscenza del cliente', 'Modificare elementi esistenti.', 'edit', 0, 1, NOW(), 1
FROM permission_groups g WHERE g.code = 'client_knowledge' AND g.status = 1
  AND NOT EXISTS (SELECT 1 FROM permissions WHERE code = 'client_knowledge.edit' AND status = 1);
INSERT INTO permissions (permission_group_id, code, name, description, category, sort_order, status, created_at, created_by)
SELECT g.id, 'client_knowledge.delete', 'Eliminare conoscenza del cliente', 'Eliminare elementi esistenti.', 'delete', 0, 1, NOW(), 1
FROM permission_groups g WHERE g.code = 'client_knowledge' AND g.status = 1
  AND NOT EXISTS (SELECT 1 FROM permissions WHERE code = 'client_knowledge.delete' AND status = 1);
INSERT INTO permission_groups (parent_id, code, name, description, sort_order, status, created_at, created_by)
SELECT 1, 'documents', 'Documenti', 'Gestione documenti', 0, 1, NOW(), 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM permission_groups WHERE code = 'documents' AND status = 1);
INSERT INTO permissions (permission_group_id, code, name, description, category, sort_order, status, created_at, created_by)
SELECT g.id, 'documents.view', 'Visualizzare documenti', 'Vedere l''elenco e aprire il dettaglio.', 'view', 0, 1, NOW(), 1
FROM permission_groups g WHERE g.code = 'documents' AND g.status = 1
  AND NOT EXISTS (SELECT 1 FROM permissions WHERE code = 'documents.view' AND status = 1);
INSERT INTO permissions (permission_group_id, code, name, description, category, sort_order, status, created_at, created_by)
SELECT g.id, 'documents.create', 'Creare documenti', 'Aggiungere nuovi elementi.', 'create', 0, 1, NOW(), 1
FROM permission_groups g WHERE g.code = 'documents' AND g.status = 1
  AND NOT EXISTS (SELECT 1 FROM permissions WHERE code = 'documents.create' AND status = 1);
INSERT INTO permissions (permission_group_id, code, name, description, category, sort_order, status, created_at, created_by)
SELECT g.id, 'documents.edit', 'Modificare documenti', 'Modificare elementi esistenti.', 'edit', 0, 1, NOW(), 1
FROM permission_groups g WHERE g.code = 'documents' AND g.status = 1
  AND NOT EXISTS (SELECT 1 FROM permissions WHERE code = 'documents.edit' AND status = 1);
INSERT INTO permissions (permission_group_id, code, name, description, category, sort_order, status, created_at, created_by)
SELECT g.id, 'documents.delete', 'Eliminare documenti', 'Eliminare elementi esistenti.', 'delete', 0, 1, NOW(), 1
FROM permission_groups g WHERE g.code = 'documents' AND g.status = 1
  AND NOT EXISTS (SELECT 1 FROM permissions WHERE code = 'documents.delete' AND status = 1);
INSERT IGNORE INTO profile_permissions (profile_id, permission_id, status, created_at, created_by)
SELECT pr.id, pe.id, 1, NOW(), 1 FROM profiles pr JOIN permissions pe
WHERE pr.status = 1 AND pe.status = 1 AND pr.name = 'Amministratore'
  AND (pe.code LIKE 'client\\_knowledge.%' OR pe.code LIKE 'documents.%' OR pe.code = 'assistant.manage')
  AND NOT EXISTS (SELECT 1 FROM profile_permissions x WHERE x.profile_id = pr.id AND x.permission_id = pe.id AND x.status = 1);

