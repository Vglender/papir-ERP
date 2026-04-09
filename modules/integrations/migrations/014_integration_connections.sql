-- Connections: API keys / accounts per integration app.
-- One app can have multiple connections (e.g. Nova Poshta has 4 senders, each with own API key).
CREATE TABLE IF NOT EXISTS `integration_connections` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `app_key`      VARCHAR(50)  NOT NULL COMMENT 'Registry app key (e.g. novaposhta)',
    `name`         VARCHAR(200) NOT NULL COMMENT 'Human label (e.g. ФОП Чумаченко)',
    `api_key`      TEXT         NULL     COMMENT 'API key / token',
    `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
    `is_default`   TINYINT(1)   NOT NULL DEFAULT 0,
    `metadata`     JSON         NULL     COMMENT 'App-specific extra data (sender_ref, organization_id, ...)',
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_app` (`app_key`),
    KEY `idx_app_default` (`app_key`, `is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate NP sender API keys into integration_connections
INSERT INTO integration_connections (app_key, name, api_key, is_active, is_default, metadata)
SELECT
    'novaposhta',
    s.Description,
    s.api,
    1,
    s.is_default,
    JSON_OBJECT(
        'sender_ref', s.Ref,
        'organization_id', IFNULL(s.organization_id, 0),
        'edrpou', IFNULL(s.EDRPOU, '')
    )
FROM np_sender s
ORDER BY s.is_default DESC, s.Description;

-- Add is_active per app as a standard setting
INSERT IGNORE INTO integration_settings (app_key, setting_key, setting_value, is_secret)
VALUES ('novaposhta', 'is_active', '1', 0);