-- ============================================================
-- Attributes module — full redesign
-- Run in Papir DB
-- ============================================================

-- 1. Группы атрибутов (мастер Papir)
CREATE TABLE IF NOT EXISTS `attribute_group` (
    `group_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sort_order` SMALLINT     NOT NULL DEFAULT 0,
    `status`     TINYINT      NOT NULL DEFAULT 1,
    PRIMARY KEY (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `attribute_group_description` (
    `group_id`    INT UNSIGNED NOT NULL,
    `language_id` INT          NOT NULL,
    `name`        VARCHAR(128) NOT NULL DEFAULT '',
    PRIMARY KEY (`group_id`, `language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Маппинг групп на сайты
CREATE TABLE IF NOT EXISTS `attribute_group_site_mapping` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_id`      INT UNSIGNED NOT NULL,
    `site_id`       INT UNSIGNED NOT NULL,
    `site_group_id` INT          NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_group_site` (`group_id`, `site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Атрибуты — добавляем group_id (если нет)
ALTER TABLE `product_attribute`
    ADD COLUMN `group_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `attribute_id`;

-- 4. Маппинг атрибутов на сайты (заменяет attribute_off)
CREATE TABLE IF NOT EXISTS `attribute_site_mapping` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `attribute_id`      INT          NOT NULL,
    `site_id`           INT UNSIGNED NOT NULL,
    `site_attribute_id` INT          NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_attr_site` (`attribute_id`, `site_id`),
    KEY `idx_site_attr` (`site_id`, `site_attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Значения атрибутов товаров — добавляем site_id
ALTER TABLE `product_attribute_value`
    ADD COLUMN `site_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `language_id`;

ALTER TABLE `product_attribute_value`
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`product_id`, `attribute_id`, `language_id`, `site_id`);
