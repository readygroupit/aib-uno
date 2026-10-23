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
