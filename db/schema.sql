-- Schema base del framework "uno" (ex brains): utenti, profili,
-- permessi (con categorie gerarchiche), configurazioni (con gruppi
-- gerarchici e dipendenze) e allegati.
--
-- Convenzioni valide su ogni tabella:
--   - status TINYINT NOT NULL DEFAULT 1 (0 = eliminato logicamente, 1 = attivo;
--     altri valori ammessi per tabelle specifiche, da definire quando servira')
--   - created_at/created_by, updated_at/updated_by: audit di chi/quando ha
--     scritto la riga. Nessun vincolo FK su created_by/updated_by (sono solo
--     informativi, evitano dipendenze circolari fra tabelle)
--   - l'unicita' di campi come username/code/key deve valere solo finche'
--     la riga e' attiva (altrimenti una riga eliminata bloccherebbe per
--     sempre il riuso del valore): si usa una colonna generata "*_active"
--     che vale NULL quando status = 0, con indice UNIQUE su quella colonna
--     (MySQL permette piu' NULL in un indice UNIQUE)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- profiles (ruoli)
-- ============================================================
DROP TABLE IF EXISTS profiles;
CREATE TABLE profiles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    status TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    created_by INT UNSIGNED NULL,
    updated_at DATETIME NULL,
    updated_by INT UNSIGNED NULL,
    name_active VARCHAR(100) GENERATED ALWAYS AS (IF(status <> 0, name, NULL)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_profiles_name_active (name_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- users
-- ============================================================
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    profile_id INT UNSIGNED NOT NULL,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    last_login_at DATETIME NULL,
    -- Quando l'utente ha chiuso il tour di benvenuto con "Ho capito, non
    -- mostrarmelo piu'" (vedi onboarding.js) - NULL = non ancora visto,
    -- riappare a ogni accesso finche' non viene valorizzato.
    onboarding_dismissed_at DATETIME NULL,
    status TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    created_by INT UNSIGNED NULL,
    updated_at DATETIME NULL,
    updated_by INT UNSIGNED NULL,
    username_active VARCHAR(100) GENERATED ALWAYS AS (IF(status <> 0, username, NULL)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username_active (username_active),
    KEY idx_users_email (email),
    KEY idx_users_profile_id (profile_id),
    CONSTRAINT fk_users_profile FOREIGN KEY (profile_id) REFERENCES profiles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- password_resets (token di recupero password, Fase 1)
-- ============================================================
DROP TABLE IF EXISTS password_resets;
CREATE TABLE password_resets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    status TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    created_by INT UNSIGNED NULL,
    updated_at DATETIME NULL,
    updated_by INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_password_resets_user (user_id),
    KEY idx_password_resets_token_hash (token_hash),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- permission_groups (categorie gerarchiche dei permessi)
-- ============================================================
DROP TABLE IF EXISTS permission_groups;
CREATE TABLE permission_groups (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id INT UNSIGNED NULL,
    code VARCHAR(100) NULL,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    created_by INT UNSIGNED NULL,
    updated_at DATETIME NULL,
    updated_by INT UNSIGNED NULL,
    code_active VARCHAR(100) GENERATED ALWAYS AS (IF(status <> 0, code, NULL)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permission_groups_code_active (code_active),
    KEY idx_permission_groups_parent (parent_id),
    CONSTRAINT fk_permission_groups_parent FOREIGN KEY (parent_id) REFERENCES permission_groups (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- permissions
-- ============================================================
DROP TABLE IF EXISTS permissions;
CREATE TABLE permissions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    permission_group_id INT UNSIGNED NOT NULL,
    code VARCHAR(150) NOT NULL,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    -- Asse trasversale rispetto a permission_group_id (il "dominio",
    -- un'entita'): questo e' il "che tipo di operazione", uguale per
    -- permessi di domini diversi (es. tutti i 'delete' di ogni entita').
    -- Vocabolario fisso, non libero: view/create/edit/delete.
    category VARCHAR(20) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    created_by INT UNSIGNED NULL,
    updated_at DATETIME NULL,
    updated_by INT UNSIGNED NULL,
    code_active VARCHAR(150) GENERATED ALWAYS AS (IF(status <> 0, code, NULL)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_code_active (code_active),
    KEY idx_permissions_group (permission_group_id),
    CONSTRAINT fk_permissions_group FOREIGN KEY (permission_group_id) REFERENCES permission_groups (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- profile_permissions (permessi assegnati a un profilo)
-- ============================================================
DROP TABLE IF EXISTS profile_permissions;
CREATE TABLE profile_permissions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    profile_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    status TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    created_by INT UNSIGNED NULL,
    updated_at DATETIME NULL,
    updated_by INT UNSIGNED NULL,
    pair_active VARCHAR(64) GENERATED ALWAYS AS (IF(status <> 0, CONCAT(profile_id, '-', permission_id), NULL)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_profile_permissions_pair_active (pair_active),
    KEY idx_profile_permissions_profile (profile_id),
    KEY idx_profile_permissions_permission (permission_id),
    CONSTRAINT fk_profile_permissions_profile FOREIGN KEY (profile_id) REFERENCES profiles (id),
    CONSTRAINT fk_profile_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- user_permissions (override diretto su singolo utente)
-- ============================================================
DROP TABLE IF EXISTS user_permissions;
CREATE TABLE user_permissions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    effect ENUM('grant', 'revoke') NOT NULL,
    status TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    created_by INT UNSIGNED NULL,
    updated_at DATETIME NULL,
    updated_by INT UNSIGNED NULL,
    pair_active VARCHAR(64) GENERATED ALWAYS AS (IF(status <> 0, CONCAT(user_id, '-', permission_id), NULL)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_permissions_pair_active (pair_active),
    KEY idx_user_permissions_user (user_id),
    KEY idx_user_permissions_permission (permission_id),
    CONSTRAINT fk_user_permissions_user FOREIGN KEY (user_id) REFERENCES users (id),
    CONSTRAINT fk_user_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- config_groups (categorie gerarchiche delle configurazioni)
-- ============================================================
DROP TABLE IF EXISTS config_groups;
CREATE TABLE config_groups (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id INT UNSIGNED NULL,
    code VARCHAR(100) NULL,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    created_by INT UNSIGNED NULL,
    updated_at DATETIME NULL,
    updated_by INT UNSIGNED NULL,
    code_active VARCHAR(100) GENERATED ALWAYS AS (IF(status <> 0, code, NULL)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_config_groups_code_active (code_active),
    KEY idx_config_groups_parent (parent_id),
    CONSTRAINT fk_config_groups_parent FOREIGN KEY (parent_id) REFERENCES config_groups (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- configs
-- type: 1=string 2=integer 3=numeric 4=switch 5=combo (vedi App\Model\ConfigType)
-- options: JSON [{"value":...,"label":...}], usato solo se type=5 e non
--          fornito da un provider in codice
-- depends_on_config_id/depends_on_value: questa riga va mostrata in UI
--          solo se la config puntata ha quel valore
-- ============================================================
DROP TABLE IF EXISTS configs;
CREATE TABLE configs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    config_group_id INT UNSIGNED NOT NULL,
    `key` VARCHAR(150) NOT NULL,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    type TINYINT UNSIGNED NOT NULL,
    value TEXT NULL,
    options TEXT NULL,
    depends_on_config_id INT UNSIGNED NULL,
    depends_on_value VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    created_by INT UNSIGNED NULL,
    updated_at DATETIME NULL,
    updated_by INT UNSIGNED NULL,
    key_active VARCHAR(150) GENERATED ALWAYS AS (IF(status <> 0, `key`, NULL)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_configs_key_active (key_active),
    KEY idx_configs_group (config_group_id),
    KEY idx_configs_depends_on (depends_on_config_id),
    CONSTRAINT fk_configs_group FOREIGN KEY (config_group_id) REFERENCES config_groups (id),
    CONSTRAINT fk_configs_depends_on FOREIGN KEY (depends_on_config_id) REFERENCES configs (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- attachments (allegato polimorfico: entity + entityId puntano alla
-- riga proprietaria di un'altra tabella qualsiasi)
-- ============================================================
DROP TABLE IF EXISTS attachments;
CREATE TABLE attachments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity VARCHAR(100) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    type VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(150) NULL,
    size INT UNSIGNED NOT NULL,
    status TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    created_by INT UNSIGNED NULL,
    updated_at DATETIME NULL,
    updated_by INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_attachments_entity (entity, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
