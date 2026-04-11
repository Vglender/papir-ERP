-- 018_scenario_notify_ttn_created.sql
-- Новий сценарій: повідомити клієнта про створення ТТН Нової Пошти.
-- Подія order_ttn_created тепер fire'иться з 3-х точок:
--   1. novaposhta/api/create_ttn.php         (ручне створення з UI заказу)
--   2. TtnService::autoMatchOrder            (auto-link через cron sync_ttns_from_np)
--   3. customerorder/api/link_documents.php  (ручне лінкування ttn_np)
-- Номер ТТН приходить в контексті як ttn.int_doc_number, плейсхолдер резолвиться
-- через TaskQueueRunner::resolveVars (root='ttn', читає з context[ttn][field]).
-- 2026-04-11

-- ── Сценарій ─────────────────────────────────────────────────────────────────

INSERT INTO `cp_scenarios` (`name`, `description`, `status`) VALUES
  ('ТТН створено — повідомити клієнта',
   'При появі ТТН НП на замовленні (створення з UI, cron auto-match, або ручне лінкування) — надсилаємо клієнту номер ТТН і посилання на портал, де він бачить повну інформацію про доставку.',
   1);
SET @sc := LAST_INSERT_ID();

-- ── Тригер ────────────────────────────────────────────────────────────────────
-- Умова: не слати якщо заказ уже в фінальному стані (cancelled/completed/return).

INSERT INTO `cp_triggers`
  (`scenario_id`, `name`, `event_type`, `delay_minutes`, `conditions`, `status`)
VALUES
  (@sc,
   'ТТН створено — повідомити клієнта',
   'order_ttn_created',
   0,
   '{"logic":"AND","rules":[{"key":"order.status","op":"not_in","value":["cancelled","completed","return"]}]}',
   1);

-- ── Крок: send_message ────────────────────────────────────────────────────────
-- Whitelist "Деталі замовлення:" — в email URL буде згорнутий у клікабельний
-- анкор, в Telegram — теж HTML-режим з анкором. Повна інфа про доставку
-- (стан ТТН, трекінг, адреса відділення) уже доступна на порталі у закладці
-- "Доставка" — не дублюємо в тексті.

INSERT INTO `cp_scenario_steps`
  (`scenario_id`, `step_order`, `executor`, `action_type`, `action_params`)
VALUES
  (@sc, 0, 'robot', 'send_message',
   '{"mode":"priority","priority_channels":["telegram","viber","sms","email"],"also_email":true,"text":"Доброго дня, {{counterparty.name}}! Ваше замовлення №{{order.number}} відправлено. Номер ТТН Нової Пошти: {{ttn.int_doc_number}}. Деталі замовлення та стан доставки: {{portal.link}}"}');