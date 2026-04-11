-- ── Ukrposhta address classifier cache ──────────────────────────────────────
-- Lazy cache for Ukrposhta address classifier API. On first query the service
-- calls `/address-classifier-ws/` and populates these tables. Subsequent
-- lookups hit the DB only (refresh via /cron/refresh_classifier.php у Фазі 4+).

CREATE TABLE IF NOT EXISTS `ukrposhta_cities` (
    `city_id`        INT UNSIGNED NOT NULL PRIMARY KEY COMMENT 'Ukrposhta CITY_ID',
    `city_name`      VARCHAR(150) NOT NULL,
    `city_type_ua`   VARCHAR(30)  NULL COMMENT 'м./смт/с. тощо',
    `region_id`      INT UNSIGNED NULL,
    `region_name`    VARCHAR(100) NULL,
    `district_id`    INT UNSIGNED NULL,
    `district_name`  VARCHAR(100) NULL,
    `postcode`       VARCHAR(10)  NULL,
    `koatuu`         VARCHAR(20)  NULL,
    `fetched_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_name`   (`city_name`),
    KEY `idx_region` (`region_id`),
    FULLTEXT KEY `ft_city_name` (`city_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ukrposhta_postoffices` (
    `postoffice_id`   INT UNSIGNED NOT NULL PRIMARY KEY COMMENT 'Ukrposhta POSTOFFICE_ID',
    `city_id`         INT UNSIGNED NOT NULL,
    `name`            VARCHAR(200) NOT NULL,
    `long_name`       VARCHAR(255) NULL,
    `type_long`       VARCHAR(50)  NULL COMMENT 'Тип відділення (ВПЗ/ВЦ/ПА/...)',
    `postindex`       VARCHAR(10)  NULL,
    `street_vpz`      VARCHAR(255) NULL,
    `longitude`       DECIMAL(10,6) NULL,
    `latitude`        DECIMAL(10,6) NULL,
    `is_automatic`    TINYINT(1)   NOT NULL DEFAULT 0,
    `fetched_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_city`    (`city_id`),
    KEY `idx_postindex` (`postindex`),
    KEY `idx_name`    (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ukrposhta_streets` (
    `street_id`     INT UNSIGNED NOT NULL PRIMARY KEY COMMENT 'Ukrposhta STREET_ID',
    `city_id`       INT UNSIGNED NOT NULL,
    `street_name`   VARCHAR(200) NOT NULL,
    `street_type`   VARCHAR(30)  NULL COMMENT 'вул./просп./пров. тощо',
    `fetched_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_city`  (`city_id`),
    KEY `idx_name`  (`street_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;