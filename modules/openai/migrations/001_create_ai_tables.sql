-- AI module tables
-- Run once in Papir DB

CREATE TABLE IF NOT EXISTS `ai_instructions` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `entity_type`   ENUM('site','category','product') NOT NULL,
    `entity_id`     INT UNSIGNED NOT NULL,
    `use_case`      VARCHAR(32) NOT NULL DEFAULT 'content',
    `instruction`   TEXT,
    `context`       TEXT,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_entity_usecase` (`entity_type`, `entity_id`, `use_case`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ai_generation_log` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `use_case`      VARCHAR(32) NOT NULL DEFAULT 'content',
    `entity_type`   VARCHAR(32) NOT NULL DEFAULT '',
    `entity_id`     INT UNSIGNED NOT NULL DEFAULT 0,
    `site_id`       INT UNSIGNED NOT NULL DEFAULT 0,
    `language_id`   INT UNSIGNED NOT NULL DEFAULT 0,
    `prompt`        MEDIUMTEXT,
    `response_raw`  MEDIUMTEXT,
    `result_json`   TEXT,
    `status`        ENUM('generated','applied','rejected') NOT NULL DEFAULT 'generated',
    `tokens_used`   INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_entity` (`entity_type`, `entity_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
