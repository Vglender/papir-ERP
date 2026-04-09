-- 011_customerorder_shipping.sql
-- Дані доставки замовлення (одержувач, адреса, відділення).
-- Одне замовлення → один рядок shipping.
-- Джерело: сайт (oc_order), ручне введення, МС-імпорт.

CREATE TABLE IF NOT EXISTS `customerorder_shipping` (
    `id`                    INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `customerorder_id`      INT             NOT NULL,
    `counterparty_id`       INT             NULL     COMMENT 'FK counterparty — для пошуку останньої доставки',

    -- Одержувач
    `recipient_first_name`  VARCHAR(64)     NULL,
    `recipient_last_name`   VARCHAR(64)     NULL,
    `recipient_middle_name` VARCHAR(64)     NULL,
    `recipient_phone`       VARCHAR(32)     NULL,

    -- Адреса
    `city_name`             VARCHAR(128)    NULL,
    `branch_name`           VARCHAR(255)    NULL     COMMENT 'Назва відділення / поштомату',
    `np_warehouse_ref`      VARCHAR(64)     NULL     COMMENT 'Ref відділення НП (для API)',
    `street`                VARCHAR(128)    NULL,
    `house`                 VARCHAR(128)    NULL,
    `flat`                  VARCHAR(128)    NULL,
    `postcode`              VARCHAR(10)     NULL     COMMENT 'Поштовий індекс (Укрпошта)',

    -- Мета
    `delivery_code`         VARCHAR(32)     NULL     COMMENT 'novaposhta.warehouse, novaposhta.doors, ukrposhta, pickup, courier',
    `delivery_method_name`  VARCHAR(128)    NULL     COMMENT 'Людська назва способу доставки',
    `no_call`               TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '1 = не телефонувати',
    `comment`               TEXT            NULL,
    `source`                VARCHAR(16)     NOT NULL DEFAULT 'manual' COMMENT 'site_off, site_mff, manual, moysklad',

    `created_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_order` (`customerorder_id`),
    KEY `idx_counterparty` (`counterparty_id`),
    KEY `idx_city` (`city_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;