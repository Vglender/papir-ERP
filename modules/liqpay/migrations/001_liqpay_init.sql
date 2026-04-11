-- 001_liqpay_init.sql
-- LiqPay модуль — ініціалізація.
-- Таблиця order_payment_receipt вже існує (створена раніше при SiteOrderImporter'і),
-- додаємо тільки UNIQUE index для ідемпотентності upsert'ів webhook'а
-- + реєструємо app в integration_settings (is_active=0, включається через UI).
-- 2026-04-11

-- ── 1. UNIQUE (provider, payment_id) — idempotent upsert ────────────────────
-- Якщо індекс вже є — ALTER падає. Перевіряємо через information_schema.
SET @idx := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name   = 'order_payment_receipt'
      AND index_name   = 'uq_provider_payment'
);
SET @sql := IF(@idx = 0,
    'ALTER TABLE `order_payment_receipt` ADD UNIQUE KEY `uq_provider_payment` (`provider`, `payment_id`)',
    'SELECT ''index exists'''
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 2. Реєстрація app у integration_settings ────────────────────────────────
-- is_active = 0 за замовчуванням — включається через catalog UI після
-- заповнення integration_connections (API ключі).

INSERT IGNORE INTO `integration_settings` (`app_key`, `setting_key`, `setting_value`, `is_secret`)
VALUES
    ('liqpay', 'is_active', '0', 0),
    ('liqpay', 'sandbox',   '0', 0);