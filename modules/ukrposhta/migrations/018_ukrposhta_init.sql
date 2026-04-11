-- ── Ukrposhta integration: schema reference ─────────────────────────────────
-- Tables used by /modules/ukrposhta already exist in production since the old
-- ukrposhta scripts (/var/sqript/Ukrposhta, /var/sqript/UP). This migration
-- does NOT create them — it documents the real schemas and seeds the app state
-- in integration_settings. Running it is safe (idempotent).
--
--   ttn_ukrposhta          — 13k+ real TTN rows, camelCase columns
--   shipment_groups        — реєстри (uuid PK, type EXPRESS|STANDARD)
--   shipment_group_links   — TTN ↔ group (group_uuid + shipment_uuid)
--   sender_ukr             — directory of Ukrposhta client/counterparty UUIDs
--
-- These tables are referenced in many places outside the module
-- (customerorder_repository, save_ttn_manual, TriggerEngine, etc.).
-- Do NOT rename or recreate.

-- Register the app as active by default
INSERT IGNORE INTO `integration_settings` (`app_key`, `setting_key`, `setting_value`, `is_secret`)
VALUES ('ukrposhta', 'is_active', '1', 0);

-- Default sender (Гльондер Василь Васильович — один аккаунт, договір у Укрпошти)
-- Ці дефолти читаються IntegrationSettingsService::get('ukrposhta', ...) при створенні ТТН.
INSERT IGNORE INTO `integration_settings` (`app_key`, `setting_key`, `setting_value`, `is_secret`) VALUES
('ukrposhta', 'default_sender_uuid',       '95f1f441-1e5b-4a5b-8bd4-66826a5042f7', 0),
('ukrposhta', 'default_sender_address_id', '645116149', 0),
('ukrposhta', 'default_return_address_id', '645116149', 0),
('ukrposhta', 'default_client_uuid',       '95f1f441-1e5b-4a5b-8bd4-66826a5042f7', 0),
('ukrposhta', 'default_shipment_type',     'STANDARD',  0),
('ukrposhta', 'default_delivery_type',     'W2W',       0),
('ukrposhta', 'default_payer',             'recipient', 0),
('ukrposhta', 'default_weight',            '1',         0),
('ukrposhta', 'default_length',            '30',        0),
('ukrposhta', 'default_width',             '20',        0),
('ukrposhta', 'default_height',            '2',         0),
('ukrposhta', 'default_description',       'Канцелярські приладдя', 0),
('ukrposhta', 'on_fail_receive_type',      'RETURN',    0),
('ukrposhta', 'return_after_storage_days', '10',        0);