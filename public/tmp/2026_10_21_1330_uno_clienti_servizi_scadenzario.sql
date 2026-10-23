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
