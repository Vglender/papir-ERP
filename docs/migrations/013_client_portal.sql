-- 013_client_portal.sql
-- Клієнтський портал: публічна сторінка замовлення за токеном
-- 2026-04-10

-- ── 1. Таблиця токенів доступу ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `client_portal_tokens` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`       INT UNSIGNED NOT NULL,
    `short_code`     VARCHAR(16)  NOT NULL,
    `token`          VARCHAR(64)  NOT NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_viewed_at` DATETIME     NULL DEFAULT NULL,
    `view_count`     INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_short_code` (`short_code`),
    UNIQUE KEY `uk_token` (`token`),
    KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 2. Seed integration_settings ─────────────────────────────────────────────
INSERT IGNORE INTO `integration_settings` (`app_key`, `setting_key`, `setting_value`, `is_secret`) VALUES
('client_portal', 'is_active',            '1',                           0),
('client_portal', 'portal_base_url',      'https://papir.officetorg.com.ua', 0),
('client_portal', 'telegram_contact_url', 'https://t.me/offtorg_bot',    0);