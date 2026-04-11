-- 020_ttn_handed_to_courier.sql
-- Нова подія `ttn_handed_to_courier` + сценарій очищення next_action.
--
-- Точка істини: момент, коли посилка фізично передається в руки НП
-- (сканування штрих-коду на реєстр + виклик кур'єра, або ручне додавання
-- ТТН до реєстру). Клієнта не турбуємо (другий пуш за добу — спам),
-- тільки знімаємо next_action='hand_over'. Це дає менеджеру прогрес у UI.
--
-- Fire-точки:
--   1. CourierCallService::processScan (scan_for_registry.php)
--   2. ScanSheetService::addDocuments  (add_ttn_to_scansheet.php)
-- Обидві через TtnService::fireTtnHandedToCourier().
--
-- 2026-04-11

-- ── Розширити enum подій триггерів ───────────────────────────────────────────

ALTER TABLE `cp_triggers` MODIFY COLUMN `event_type`
  ENUM('order_created','order_status_changed','order_cancelled',
       'task_done','task_created','document_created',
       'order_payment_changed','order_shipment_changed',
       'order_ttn_created','order_delivery_created',
       'ttn_status_changed','ttn_handed_to_courier') NOT NULL;

-- ── Сценарій: очистити next_action 'hand_over' ───────────────────────────────

INSERT INTO `cp_scenarios` (`name`, `description`, `status`) VALUES
  ('ТТН передано кур''єру — зняти next_action',
   'Коли ТТН фактично передається в НП (сканування на реєстр або додавання до scan sheet) — знімаємо next_action=hand_over "Передати кур''єру". Клієнта не повідомляємо (наступне повідомлення піде на реальному переході в in_transit, сценарій 6).',
   1);
SET @sc := LAST_INSERT_ID();

-- ── Тригер ────────────────────────────────────────────────────────────────────
-- Умова: next_action = 'hand_over'. Якщо next_action уже був інший — не чіпаємо.

INSERT INTO `cp_triggers`
  (`scenario_id`, `name`, `event_type`, `delay_minutes`, `conditions`, `status`)
VALUES
  (@sc,
   'ТТН передано кур''єру',
   'ttn_handed_to_courier',
   0,
   '{"logic":"AND","rules":[{"key":"order.next_action","op":"=","value":"hand_over"}]}',
   1);

-- ── Крок: очистити next_action ───────────────────────────────────────────────

INSERT INTO `cp_scenario_steps`
  (`scenario_id`, `step_order`, `executor`, `action_type`, `action_params`)
VALUES
  (@sc, 1, 'robot', 'set_next_action', '{"action":"","label":""}');