-- 021_scenario4_status_filter.sql
-- Тригери 2 і 3 сценарію 4 ("Наступна дія: Створити ТТН") не фільтрували
-- кінцеві статуси замовлення (completed/cancelled/received/return). Через це
-- при пізньому order_payment_changed (напр. ретро-імпорт LiqPay receipts)
-- на вже завершене замовлення ставився next_action='ship', що збиває менеджера.
--
-- Додаємо умову order.status NOT IN кінцеві статуси.
-- 2026-04-11

UPDATE `cp_triggers`
SET `conditions` = '{"logic":"AND","rules":[{"key":"order.payment_method_id","op":"in","value":[1,2,5]},{"key":"order.payment_status","op":"!=","value":"not_paid"},{"key":"order.shipment_status","op":"!=","value":"not_shipped"},{"key":"order.has_shipment_tracking","op":"=","value":"0"},{"key":"order.status","op":"not_in","value":["completed","cancelled","received","return"]}]}'
WHERE id = 2;

UPDATE `cp_triggers`
SET `conditions` = '{"logic":"AND","rules":[{"key":"order.payment_method_id","op":"in","value":[1,2,5]},{"key":"order.payment_status","op":"!=","value":"not_paid"},{"key":"order.shipment_status","op":"!=","value":"not_shipped"},{"key":"order.has_shipment_tracking","op":"=","value":"0"},{"key":"order.status","op":"not_in","value":["completed","cancelled","received","return"]}]}'
WHERE id = 3;