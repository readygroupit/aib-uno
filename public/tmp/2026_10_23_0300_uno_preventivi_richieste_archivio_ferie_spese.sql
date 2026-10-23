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
