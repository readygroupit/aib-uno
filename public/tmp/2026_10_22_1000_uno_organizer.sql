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
