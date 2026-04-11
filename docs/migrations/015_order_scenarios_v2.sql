-- 015_order_scenarios_v2.sql
-- Нові сценарії на event order_created для різних способів оплати
-- + оновлення існуючих сценаріїв (ренейм label, прибирання застарілих умов)
-- 2026-04-10

-- ── 1. Оновлення існуючого Сценарію 2 (НП — є в наявності) ─────────────────
-- Склад у Papir з остатками поки не запущено — прибираємо умову all_items_in_stock.
-- Лишаємо тільки: payment_method=НП (4) AND wait_call=0.

UPDATE `cp_triggers`
SET `conditions` = '{"logic":"AND","rules":[{"key":"order.payment_method_id","op":"in","value":[4]},{"key":"order.wait_call","op":"=","value":"0"}]}'
WHERE `id` = 1 AND `event_type` = 'order_created';

-- Також прибираємо умову all_items_in_stock з кроку №2 Сценарію 2 (set_next_action).
UPDATE `cp_scenario_steps`
SET `conditions` = NULL
WHERE `scenario_id` = 2
  AND `action_type` = 'set_next_action'
  AND `conditions` LIKE '%all_items_in_stock%';

-- ── 2. Ренейм label "Відправити" → "Створити ТТН" ──────────────────────────
-- У всіх існуючих set_next_action кроках, де machine code = 'ship'.

UPDATE `cp_scenario_steps`
SET `action_params` = REPLACE(`action_params`, '"label":"Відправити"', '"label":"Створити ТТН"')
WHERE `action_type` = 'set_next_action'
  AND `action_params` LIKE '%"action":"ship"%'
  AND `action_params` LIKE '%"label":"Відправити"%';

-- ── 3. Сценарій A — Новий заказ: чекає дзвінка ─────────────────────────────

INSERT INTO `cp_scenarios` (`name`, `description`, `status`) VALUES
  ('Новий: чекає дзвінка',
   'Клієнт запросив дзвінок — вітаємо через priority+email та ставимо дію «Подзвонити». Дія автоматично знімається при зміні статусу оператором.',
   1);
SET @sc_a := LAST_INSERT_ID();

INSERT INTO `cp_triggers`
  (`scenario_id`, `name`, `event_type`, `delay_minutes`, `conditions`, `status`)
VALUES
  (@sc_a,
   'Новий: чекає дзвінка',
   'order_created',
   0,
   '{"logic":"AND","rules":[{"key":"order.wait_call","op":"=","value":"1"}]}',
   1);

INSERT INTO `cp_scenario_steps`
  (`scenario_id`, `step_order`, `executor`, `action_type`, `action_params`)
VALUES
  (@sc_a, 0, 'robot', 'send_message',
   '{"mode":"priority","priority_channels":["telegram","viber","sms","email"],"also_email":true,"text":"Доброго дня, {{counterparty.name}}! Ваше замовлення №{{order.number}} надійшло. Дякуємо! Ви запросили дзвінок — ми зв''яжемось з вами пізніше. А поки можете переглянути своє замовлення та зв''язатись з нами он-лайн: {{portal.link}}"}'),
  (@sc_a, 1, 'robot', 'set_next_action',
   '{"action":"call","label":"Подзвонити"}');

-- ── 4. Сценарій B — Новий заказ: безнал юр (рахунок) ───────────────────────

INSERT INTO `cp_scenarios` (`name`, `description`, `status`) VALUES
  ('Новий: безнал юр (рахунок)',
   'Клієнт юрособа — вітаємо, обіцяємо рахунок, надсилаємо рахунок (поки без PDF), статус → чекаємо оплату.',
   1);
SET @sc_b := LAST_INSERT_ID();

INSERT INTO `cp_triggers`
  (`scenario_id`, `name`, `event_type`, `delay_minutes`, `conditions`, `status`)
VALUES
  (@sc_b,
   'Новий: безнал юр',
   'order_created',
   0,
   '{"logic":"AND","rules":[{"key":"order.wait_call","op":"=","value":"0"},{"key":"order.payment_method_id","op":"in","value":[1]}]}',
   1);

INSERT INTO `cp_scenario_steps`
  (`scenario_id`, `step_order`, `executor`, `action_type`, `action_params`)
VALUES
  (@sc_b, 0, 'robot', 'send_message',
   '{"mode":"priority","priority_channels":["telegram","viber","sms","email"],"also_email":true,"text":"Доброго дня, {{counterparty.name}}! Ваше замовлення №{{order.number}} надійшло. Дякуємо! Висилаємо вам рахунок на оплату. Якщо дані неправильні — напишіть нам, ми виправимо: {{portal.link}}"}'),
  (@sc_b, 1, 'robot', 'send_invoice',
   '{"mode":"priority","priority_channels":["telegram","viber","sms","email"],"also_email":true,"text":"Рахунок на оплату замовлення №{{order.number}}: {{portal.link}}"}'),
  (@sc_b, 2, 'robot', 'change_status',
   '{"status":"waiting_payment"}');

-- ── 5. Сценарій C — Новий заказ: безнал фіз (реквізити) ────────────────────

INSERT INTO `cp_scenarios` (`name`, `description`, `status`) VALUES
  ('Новий: безнал фіз (реквізити)',
   'Клієнт фізособа, безготівкова оплата — надсилаємо реквізити через портал, статус → чекаємо оплату.',
   1);
SET @sc_c := LAST_INSERT_ID();

INSERT INTO `cp_triggers`
  (`scenario_id`, `name`, `event_type`, `delay_minutes`, `conditions`, `status`)
VALUES
  (@sc_c,
   'Новий: безнал фіз',
   'order_created',
   0,
   '{"logic":"AND","rules":[{"key":"order.wait_call","op":"=","value":"0"},{"key":"order.payment_method_id","op":"in","value":[2]}]}',
   1);

INSERT INTO `cp_scenario_steps`
  (`scenario_id`, `step_order`, `executor`, `action_type`, `action_params`)
VALUES
  (@sc_c, 0, 'robot', 'send_message',
   '{"mode":"priority","priority_channels":["telegram","viber","sms","email"],"also_email":true,"text":"Доброго дня, {{counterparty.name}}! Ваше замовлення №{{order.number}} надійшло. Дякуємо! Висилаємо вам реквізити на оплату. Якщо виникли питання — ви можете перевірити своє замовлення та зв''язатись з нами он-лайн: {{portal.link}}"}'),
  (@sc_c, 1, 'robot', 'change_status',
   '{"status":"waiting_payment"}');

-- ── 6. Сценарій D — Новий заказ: онлайн/Пром, передплата успішна ───────────

INSERT INTO `cp_scenarios` (`name`, `description`, `status`) VALUES
  ('Новий: онлайн/Пром (передплата)',
   'Онлайн-оплата або Пром-оплата, передплата успішна — дякуємо, робимо відвантаження, статус shipped, ставимо дію «Створити ТТН».',
   1);
SET @sc_d := LAST_INSERT_ID();

INSERT INTO `cp_triggers`
  (`scenario_id`, `name`, `event_type`, `delay_minutes`, `conditions`, `status`)
VALUES
  (@sc_d,
   'Новий: онлайн/Пром, оплачено',
   'order_created',
   0,
   '{"logic":"AND","rules":[{"key":"order.wait_call","op":"=","value":"0"},{"key":"order.payment_method_id","op":"in","value":[5]},{"key":"order.is_paid","op":"=","value":"1"}]}',
   1);

INSERT INTO `cp_scenario_steps`
  (`scenario_id`, `step_order`, `executor`, `action_type`, `action_params`)
VALUES
  (@sc_d, 0, 'robot', 'send_message',
   '{"mode":"priority","priority_channels":["telegram","viber","sms","email"],"also_email":true,"text":"Доброго дня, {{counterparty.name}}! Ваше замовлення №{{order.number}} надійшло. Дякуємо за передплату — ми надішлемо ваше замовлення найближчим часом. Деталі замовлення: {{portal.link}}"}'),
  (@sc_d, 1, 'robot', 'create_demand',
   '{"status":"new","description":"Авто-відвантаження (передплата онлайн)"}'),
  (@sc_d, 2, 'robot', 'change_status',
   '{"status":"shipped"}'),
  (@sc_d, 3, 'robot', 'set_next_action',
   '{"action":"ship","label":"Створити ТТН"}');