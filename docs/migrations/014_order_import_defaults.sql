-- 014_order_import_defaults.sql
-- Дефолтні організації для імпорту замовлень:
--   default_org_vat   — платник ПДВ (за замовчуванням ТОВ Архкор, id=8)
--   default_org_novat — неплатник ПДВ (за замовчуванням ФОП Чумаченко, id=6)
--
-- Ці налаштування читає OrderOrgResolver при імпорті замовлень з сайтів,
-- МойСклад (fallback), Prom тощо. Додаток керується через Integrations Hub
-- (app_key = 'order_import').

INSERT INTO `integration_settings` (`app_key`, `setting_key`, `setting_value`, `is_secret`)
VALUES
    ('order_import', 'is_active',         '1', 0),
    ('order_import', 'default_org_vat',   '8', 0),
    ('order_import', 'default_org_novat', '6', 0)
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);