-- 017_scenarios_fired_at.sql
-- Маркер на customerorder: коли вже запускались сценарії (order_created).
-- Використовується для ручних заказів через cron fire_manual_orders.php,
-- щоб fire'ити order_created після 5-хвилинної тиші (якщо оператор не
-- встиг видалити/перевести в інший статус).
-- 2026-04-11

ALTER TABLE `customerorder`
  ADD COLUMN `scenarios_fired_at` DATETIME NULL DEFAULT NULL
  COMMENT 'Момент коли було викликано TriggerEngine::fire(order_created) для цього замовлення',
  ADD INDEX `idx_scenarios_pending` (`source`, `status`, `scenarios_fired_at`);