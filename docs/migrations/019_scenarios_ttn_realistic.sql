-- 019_scenarios_ttn_realistic.sql
-- Реалістичні сценарії ТТН НП: TTN-створено ≠ shipped.
--
-- Бізнес-логіка:
--   1. Створили ТТН → статус in_progress, next_action='hand_over' "Передати кур'єру",
--      повідомляємо клієнту що передамо в НП до {{calendar.next_business_day}}.
--   2. Фізично передали кур'єру (реєстр/виклик) → знімаємо next_action, клієнта не
--      турбуємо (другий пуш за добу — надлишок).
--   3. НП сканувала на терміналі (state_define in 4,5,6) → статус shipped,
--      повідомляємо "вирушило з міста відправлення".
--
-- Зміни:
--   - Scenario 5: change_status 'shipped' → 'in_progress' + next_action 'hand_over'
--   - Scenario 14: новий текст з {{calendar.next_business_day}}, не бреше про відправку
--   - Scenario 6: додано send_message на фактичному переході в_дорозі
--
-- 2026-04-11

-- ── Scenario 5: ТТН/demand створено → в роботі + передати кур'єру ────────────

UPDATE `cp_scenarios`
SET `name` = 'Після ТТН/demand: в роботі + передати кур''єру',
    `description` = 'При створенні ТТН або demand — замовлення переходить у статус in_progress (В роботі), наступна дія менеджера — передати посилку кур''єру. Не ставимо shipped — це відбувається тільки коли НП фізично сканує посилку на терміналі.'
WHERE `id` = 5;

DELETE FROM `cp_scenario_steps` WHERE `scenario_id` = 5;

INSERT INTO `cp_scenario_steps`
  (`scenario_id`, `step_order`, `executor`, `action_type`, `action_params`)
VALUES
  (5, 1, 'robot', 'change_status',   '{"status":"in_progress"}'),
  (5, 2, 'robot', 'set_next_action', '{"action":"hand_over","label":"Передати кур''єру"}');

-- ── Scenario 14: ТТН створено — повідомити клієнта (чесний текст) ────────────

DELETE FROM `cp_scenario_steps` WHERE `scenario_id` = 14;

INSERT INTO `cp_scenario_steps`
  (`scenario_id`, `step_order`, `executor`, `action_type`, `action_params`)
VALUES
  (14, 0, 'robot', 'send_message',
   '{"mode":"priority","priority_channels":["telegram","viber","sms","email"],"also_email":false,"text":"Доброго дня, {{counterparty.name}}! Ми сформували відправку по замовленню №{{order.number}}. Номер ТТН Нової Пошти: {{ttn.int_doc_number}}. Передамо посилку в НП {{calendar.next_business_day}}. Стан доставки: {{portal.link}}#delivery"}');

-- ── Scenario 6: ТТН в дорозі → статус shipped + повідомлення клієнту ─────────
-- Тригер 6 вже має умову ttn.new_state_define in [4,5,6] — момент істини,
-- НП сканувала посилку на терміналі відправлення. Тут уже чесно кажемо клієнту.

UPDATE `cp_scenarios`
SET `name` = 'ТТН: Відправлено (повідомити + статус)',
    `description` = 'Коли НП фактично сканувала посилку (state_define 4,5,6 — в дорозі / на шляху до міста отримувача) — переводимо замовлення у shipped і повідомляємо клієнта що посилка вирушила з міста відправлення. Це реальна точка істини, повідомлення клієнту — звідси.'
WHERE `id` = 6;

DELETE FROM `cp_scenario_steps` WHERE `scenario_id` = 6;

INSERT INTO `cp_scenario_steps`
  (`scenario_id`, `step_order`, `executor`, `action_type`, `action_params`)
VALUES
  (6, 1, 'robot', 'send_message',
   '{"mode":"priority","priority_channels":["telegram","viber","sms"],"also_email":false,"text":"Ваше замовлення №{{order.number}} вирушило з міста відправлення Новою Поштою. ТТН {{ttn.int_doc_number}}. Відстежити доставку: {{portal.link}}#delivery"}'),
  (6, 2, 'robot', 'change_status',   '{"status":"shipped"}'),
  (6, 3, 'robot', 'set_next_action', '{"action":"","label":""}');