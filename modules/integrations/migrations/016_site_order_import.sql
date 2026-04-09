-- =====================================================================
-- Site Order Import: payment receipts, OC shipping/payment code mapping
-- =====================================================================

-- LiqPay and other payment receipts from sites
CREATE TABLE IF NOT EXISTS order_payment_receipt (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customerorder_id INT          NOT NULL COMMENT 'FK customerorder.id',
    site_id         INT          NOT NULL COMMENT 'FK sites.site_id',
    site_order_id   INT UNSIGNED NOT NULL COMMENT 'OC order_id on the site',
    provider        VARCHAR(32)  NOT NULL DEFAULT 'liqpay' COMMENT 'liqpay, fondy, wayforpay, etc.',
    payment_id      VARCHAR(64)  NULL     COMMENT 'Provider payment ID',
    provider_order_id VARCHAR(64) NULL    COMMENT 'Provider order reference (liqpay_order_id)',
    status          VARCHAR(32)  NOT NULL DEFAULT 'unknown' COMMENT 'success, failure, processing, etc.',
    paytype         VARCHAR(32)  NULL     COMMENT 'gpay, card, privat24, etc.',
    amount          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    currency        CHAR(3)      NOT NULL DEFAULT 'UAH',
    raw_json        MEDIUMTEXT   NULL     COMMENT 'Full provider response JSON',
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_order   (customerorder_id),
    KEY idx_site    (site_id, site_order_id),
    KEY idx_payment (provider, payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- OC shipping_code → Papir delivery_method_id mapping
CREATE TABLE IF NOT EXISTS site_delivery_method_map (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shipping_code   VARCHAR(64)  NOT NULL COMMENT 'OC shipping_code (e.g. novaposhta.warehouse)',
    delivery_method_id INT       NOT NULL COMMENT 'FK delivery_method.id',
    delivery_code   VARCHAR(32)  NULL     COMMENT 'Subtype: novaposhta.warehouse, novaposhta.doors, etc.',
    UNIQUE KEY uq_code (shipping_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- OC payment_code → Papir payment_method_id mapping
CREATE TABLE IF NOT EXISTS site_payment_method_map (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_code    VARCHAR(64)  NOT NULL COMMENT 'OC payment_code (e.g. revpay2, cod)',
    payment_method_id INT        NOT NULL COMMENT 'FK payment_method.id',
    UNIQUE KEY uq_code (payment_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Site customer link on counterparty
ALTER TABLE counterparty
    ADD COLUMN site_customer_ids JSON NULL COMMENT '{"off": 123, "mff": 456} — OC customer_id per site' AFTER id_ms;

-- Seed delivery method mapping
INSERT INTO site_delivery_method_map (shipping_code, delivery_method_id, delivery_code) VALUES
('novaposhta.warehouse',  3, 'novaposhta.warehouse'),
('novaposhta.poshtomat',  3, 'novaposhta.warehouse'),
('novaposhta.doors',      3, 'novaposhta.doors'),
('novaposhta.courier',    2, 'novaposhta.doors'),
('ukrposhta.standard',    4, 'ukrposhta'),
('ukrposhta.express',     4, 'ukrposhta'),
('justin.warehouse',      3, 'novaposhta.warehouse'),
('pickup.pickup',         1, 'pickup'),
('flat.flat',             1, 'pickup')
ON DUPLICATE KEY UPDATE delivery_method_id = VALUES(delivery_method_id);

-- Seed payment method mapping
INSERT INTO site_payment_method_map (payment_code, payment_method_id) VALUES
('revpay2',       5),
('liqpay',        5),
('cod',           4),
('bank_transfer', 1),
('cash',          3),
('free_checkout',  3)
ON DUPLICATE KEY UPDATE payment_method_id = VALUES(payment_method_id);
