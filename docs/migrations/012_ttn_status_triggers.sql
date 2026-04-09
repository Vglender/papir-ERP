-- 012_ttn_status_triggers.sql
-- Гранулярні статуси доставки + подія ttn_status_changed для автоматизації сценаріїв
-- 2026-04-09

-- ── 0. Розширити ENUM customerorder.status ────────────────────────────────────

ALTER TABLE `customerorder` MODIFY COLUMN `status`
  ENUM('draft','new','confirmed','in_progress','waiting_payment','paid','partially_shipped',
       'shipped','received','return','completed','cancelled')
  NOT NULL DEFAULT 'draft';

-- ── 1. Нові статуси замовлення ───────────────────────────────────────────────

INSERT INTO `order_status` (`code`, `sort_order`, `is_archive`, `color`) VALUES
('received', 7, 0, 'teal'),
('return',   8, 0, 'rose');

-- Зсунути completed і cancelled
UPDATE `order_status` SET `sort_order` = 9  WHERE `code` = 'completed';
UPDATE `order_status` SET `sort_order` = 10 WHERE `code` = 'cancelled';

-- ── 2. Переклади ─────────────────────────────────────────────────────────────

INSERT INTO `order_status_i18n` (`code`, `language_id`, `name`) VALUES
('received', 1, 'Получено'),
('received', 2, 'Отримано'),
('return',   1, 'Возврат'),
('return',   2, 'Повернення');

-- ── 3. Перемаппінг МойСклад статусів ──────────────────────────────────────────
-- 'Выполнен'/'Наложка выкуплена' → received (замість completed)
-- 'Передан в доставку' → shipped (замість in_progress)
-- Papir тепер джерело правди для статусів — webhook МС НЕ перезаписує статус.

UPDATE `order_status_ms_mapping` SET `papir_code` = 'received' WHERE `ms_state_id` = '023eff4b-7aca-11eb-0a80-03f8003813ff'; -- Наложка выкуплена
UPDATE `order_status_ms_mapping` SET `papir_code` = 'received' WHERE `ms_state_id` = 'bc5a77c2-d2ad-11ea-0a80-02ef0007cc9f'; -- Выполнен
UPDATE `order_status_ms_mapping` SET `papir_code` = 'received' WHERE `ms_state_id` = 'da89dea4-179c-11ec-0a80-09820031f9b6'; -- Выполнен (2)
UPDATE `order_status_ms_mapping` SET `papir_code` = 'shipped'  WHERE `ms_state_id` = '41c486a9-d29a-11ea-0a80-0517000f0d4a'; -- Передан в доставку

-- ── 4. Маппінг на сайти (OC status_id) ───────────────────────────────────────
-- TODO: створити відповідні статуси на off/mff і вказати їх id
-- INSERT INTO `order_status_site_mapping` (`papir_code`, `site_id`, `site_status_id`) VALUES
-- ('received', 1, ???), ('received', 2, ???),
-- ('return',   1, ???), ('return',   2, ???);

-- ── 5. Нова подія для тригерів ───────────────────────────────────────────────

ALTER TABLE `cp_triggers` MODIFY COLUMN `event_type`
  ENUM('order_created','order_status_changed','order_cancelled',
       'task_done','task_created','document_created',
       'order_payment_changed','order_shipment_changed',
       'order_ttn_created','order_delivery_created',
       'ttn_status_changed') NOT NULL;

-- ── 6. Новий тип дії для кроків сценарію ─────────────────────────────────────

ALTER TABLE `cp_scenario_steps` MODIFY COLUMN `action_type`
  ENUM('send_message','send_invoice','create_task','change_status',
       'create_demand','set_next_action','wait',
       'create_salesreturn') NOT NULL DEFAULT 'send_message';

-- ── 7. Дозволений перехід demand → salesreturn ───────────────────────────────

INSERT IGNORE INTO `document_type_transition` (`from_type`, `to_type`, `link_type`, `description`) VALUES
('demand', 'salesreturn', 'return', 'Повернення по відвантаженню');

-- ── 8. Поле return_ttn_number в return_logistics (якщо ще немає) ─────────────

-- Вже є в таблиці return_logistics, додаємо тип auto_ttn
ALTER TABLE `return_logistics` MODIFY COLUMN `return_type`
  ENUM('novaposhta_ttn','ukrposhta_ttn','manual','left_with_client','auto_return') NOT NULL
  COMMENT 'Спосіб повернення (auto_return = автоповернення НП)';
