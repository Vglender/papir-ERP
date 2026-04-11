-- =====================================================================
-- Migration 001: Prom.ua status, delivery, payment mappings
-- 2026-04-10
-- =====================================================================

-- ── 1. Order status mapping: Prom status → Papir code ────────────────
-- Prom statuses: pending, received, delivered, canceled, paid
-- Папір:         new, confirmed, completed, cancelled, in_progress, shipped, ...

INSERT INTO `order_status_site_mapping` (`papir_code`, `site_id`, `site_status_id`) VALUES
-- site_id=3 (prom), site_status_id = Prom status id
('new',             3,  0),   -- pending (Новый)
('confirmed',       3,  1),   -- received (Принят)
('in_progress',     3,  1),   -- received (Принят) — Prom has no "in_progress"
('shipped',         3,  1),   -- received — Prom tracks delivery via TTN, not status
('completed',       3,  3),   -- delivered (Выполнен)
('cancelled',       3,  4),   -- canceled (Отменен)
('waiting_payment', 3,  0),   -- pending — no direct Prom equivalent
('received',        3,  3),   -- delivered (Выполнен)
('return',          3,  4)    -- canceled — Prom has no return status
ON DUPLICATE KEY UPDATE site_status_id = VALUES(site_status_id);

-- ── 2. Prom delivery type → Papir delivery_method_id ─────────────────
-- Prom:  nova_poshta, ukrposhta, meest, meest_express, delivery_auto,
--        rozetka_delivery, pickup
-- Папір: 1=pickup, 2=courier, 3=novaposhta, 4=ukrposhta

INSERT INTO `site_delivery_method_map` (`shipping_code`, `delivery_method_id`, `delivery_code`) VALUES
('prom.nova_poshta',       3, 'novaposhta.warehouse'),
('prom.ukrposhta',         4, 'ukrposhta'),
('prom.meest',             3, 'novaposhta.warehouse'),
('prom.meest_express',     3, 'novaposhta.warehouse'),
('prom.delivery_auto',     2, 'courier'),
('prom.rozetka_delivery',  1, 'pickup'),
('prom.pickup',            1, 'pickup')
ON DUPLICATE KEY UPDATE delivery_method_id = VALUES(delivery_method_id);

-- ── 3. Prom payment name → Papir payment_method_id ───────────────────
-- Prom payments (by id, no code):
--   Оплата на счет → bank (1 or 2)
--   Наложенный платеж → cash_on_delivery (4)
--   Пром-оплата → online (5)
--   Оплатить частями → online (5)

INSERT INTO `site_payment_method_map` (`payment_code`, `payment_method_id`) VALUES
('prom.bank',              1),
('prom.cash_on_delivery',  4),
('prom.online',            5),
('prom.installment',       5)
ON DUPLICATE KEY UPDATE payment_method_id = VALUES(payment_method_id);
