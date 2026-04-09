-- Integration settings: stores API keys, tokens, and default config per integration app
CREATE TABLE IF NOT EXISTS `integration_settings` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `app_key`         VARCHAR(50)  NOT NULL COMMENT 'Integration key from registry (e.g. telegram, alphasms)',
    `setting_key`     VARCHAR(100) NOT NULL COMMENT 'Setting name (e.g. api_key, bot_token)',
    `setting_value`   TEXT         NULL     COMMENT 'Setting value (encrypted sensitive values prefixed with enc:)',
    `is_secret`       TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = masked in UI',
    `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by`      INT UNSIGNED NULL     COMMENT 'auth_users.id',
    UNIQUE KEY `uq_app_setting` (`app_key`, `setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
