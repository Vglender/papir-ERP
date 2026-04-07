-- 010_next_action.sql
-- Наступна дія оператора: інфраструктура для сценаріїв

-- 1. Поля на замовленні
ALTER TABLE `customerorder`
  ADD COLUMN `next_action` VARCHAR(64) DEFAULT NULL COMMENT 'Код наступної дії (ship, call, confirm...)',
  ADD COLUMN `next_action_label` VARCHAR(255) DEFAULT NULL COMMENT 'Підпис кнопки наступної дії';

-- 2. Нові типи подій для тригерів
ALTER TABLE `cp_triggers` MODIFY COLUMN `event_type`
  ENUM('order_created','order_status_changed','order_cancelled',
       'task_done','task_created','document_created',
       'order_payment_changed','order_shipment_changed',
       'order_ttn_created','order_delivery_created') NOT NULL;

-- 3. Новий тип дії для кроків сценарію
ALTER TABLE `cp_scenario_steps` MODIFY COLUMN `action_type`
  ENUM('send_message','send_invoice','create_task','change_status',
       'create_demand','set_next_action','wait') NOT NULL DEFAULT 'send_message';