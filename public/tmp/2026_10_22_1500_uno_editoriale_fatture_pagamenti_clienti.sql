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
