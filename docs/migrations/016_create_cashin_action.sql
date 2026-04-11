-- 016_create_cashin_action.sql
-- Нова сценарна дія create_cashin — автоматичне створення ПКО по накладеному
-- платежу при отриманні ТТН клієнтом. Papir стає джерелом правди для cashin;
-- імпорт/вебхук cashin з МС вимикається у коді.
-- 2026-04-11

-- ── 1. Розширити ENUM action_type у cp_scenario_steps ────────────────────────

ALTER TABLE `cp_scenario_steps` MODIFY COLUMN `action_type`
  ENUM('send_message','send_invoice','create_task','change_status',
       'create_demand','set_next_action','wait',
       'create_salesreturn','create_cashin') NOT NULL DEFAULT 'send_message';

-- ── 2. Оновлення Сценарію 8 «ТТН: Отримано» ──────────────────────────────────
-- Зараз: received → clear next_action → completed (step_order 1,2,3).
-- Стає:  received → clear next_action → create_cashin (якщо накладенка) → completed.
-- Зсуваємо existing крок 'completed' з order=3 на order=4 і вставляємо
-- create_cashin з order=3 і умовою payment_method_id=4.

-- 2a. Зсуваємо крок "completed" на order=4
UPDATE `cp_scenario_steps`
SET `step_order` = 4
WHERE `scenario_id` = 8
  AND `step_order`  = 3
  AND `action_type` = 'change_status';

-- 2b. Новий крок create_cashin (order=3, умова payment_method=4=накладенка)
INSERT INTO `cp_scenario_steps`
  (`scenario_id`, `step_order`, `executor`, `action_type`, `action_params`, `conditions`)
VALUES
  (8, 3, 'robot', 'create_cashin',
   '{"description":"Автоматичне ПКО по накладеному платежу"}',
   '{"logic":"AND","rules":[{"key":"order.payment_method_id","op":"in","value":[4]}]}');

-- ── 3. Оновлення опису Сценарію 8 ────────────────────────────────────────────

UPDATE `cp_scenarios`
SET `description` = 'Клієнт забрав посилку (state 9) → статус received → для накладенки автоматично ПКО (номер=ТТН) → статус completed'
WHERE `id` = 8;