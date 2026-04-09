-- Migration 009: Print pack profiles & jobs
-- Комплект документів на відвантаження

CREATE TABLE IF NOT EXISTS `print_pack_profiles` (
    `id`         INT NOT NULL AUTO_INCREMENT,
    `org_id`     INT NULL COMMENT 'NULL = глобальний дефолт',
    `name`       VARCHAR(128) NOT NULL,
    `items_json` JSON NOT NULL,
    `is_default` TINYINT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_org` (`org_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `print_pack_jobs` (
    `id`         INT NOT NULL AUTO_INCREMENT,
    `demand_id`  INT NOT NULL,
    `profile_id` INT NULL,
    `status`     ENUM('pending','ready','error') NOT NULL DEFAULT 'pending',
    `items_json` JSON NOT NULL COMMENT 'Згенеровані документи з URLs',
    `error_msg`  VARCHAR(512) NULL,
    `created_by` INT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_demand` (`demand_id`),
    KEY `idx_status` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default profile: накладна з печаткою + ТТН стікер
INSERT INTO `print_pack_profiles` (`org_id`, `name`, `items_json`, `is_default`)
VALUES (NULL, 'Стандартний пакет', '[{"type":"template","template_id":3,"label":"Накладна з печаткою"},{"type":"ttn_sticker","format":"100x100","label":"ТТН стікер"}]', 1);
