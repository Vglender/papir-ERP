-- 006_document_linking.sql
-- Система линкования документов

-- Справочник типов документов
CREATE TABLE `document_type` (
  `code`        varchar(64) NOT NULL,
  `name_uk`     varchar(128) NOT NULL,
  `name_ru`     varchar(128) NOT NULL,
  `direction`   enum('in','out','neutral') NOT NULL DEFAULT 'neutral',
  `ms_type`     varchar(64) DEFAULT NULL COMMENT 'Тип в МойСклад API (переходный период)',
  `sort_order`  int NOT NULL DEFAULT 0,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ru_0900_ai_ci;

INSERT INTO `document_type` (`code`, `name_uk`, `name_ru`, `direction`, `ms_type`, `sort_order`) VALUES
('customerorder',  'Замовлення покупця',         'Заказ покупателя',        'neutral', 'customerorder',  10),
('demand',         'Відвантаження',              'Отгрузка',                'out',     'demand',         20),
('invoiceout',     'Рахунок покупцю',            'Счёт покупателю',         'neutral', 'invoiceout',     30),
('paymentin',      'Вхідний платіж',             'Входящий платёж',         'in',      'paymentin',      40),
('cashin',         'Прибутковий касовий ордер',  'Приходный кассовый ордер','in',      'cashin',         50),
('purchaseorder',  'Замовлення постачальнику',   'Заказ поставщику',        'neutral', 'purchaseorder',  60),
('supply',         'Приймання',                  'Приёмка',                 'in',      'supply',         70),
('invoicein',      'Рахунок від постачальника',  'Счёт от поставщика',      'neutral', 'invoicein',      80),
('paymentout',     'Вихідний платіж',            'Исходящий платёж',        'out',     'paymentout',     90),
('cashout',        'Видатковий касовий ордер',   'Расходный кассовый ордер','out',     'cashout',        100);

-- Разрешённые переходы между типами документов (бизнес-логика)
-- Чего нет в таблице — то запрещено
CREATE TABLE `document_type_transition` (
  `from_type`   varchar(64) NOT NULL,
  `to_type`     varchar(64) NOT NULL,
  `link_type`   varchar(64) NOT NULL COMMENT 'shipment, payment, invoice',
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`from_type`, `to_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ru_0900_ai_ci;

INSERT INTO `document_type_transition` (`from_type`, `to_type`, `link_type`, `description`) VALUES
('customerorder', 'demand',        'shipment', 'Відвантаження по замовленню'),
('customerorder', 'invoiceout',    'invoice',  'Рахунок по замовленню'),
('customerorder', 'paymentin',     'payment',  'Вхідний платіж по замовленню'),
('customerorder', 'cashin',        'payment',  'Касовий прихід по замовленню'),
('purchaseorder', 'supply',        'shipment', 'Приймання по замовленню постачальнику'),
('purchaseorder', 'invoicein',     'invoice',  'Рахунок від постачальника'),
('purchaseorder', 'paymentout',    'payment',  'Вихідний платіж постачальнику'),
('purchaseorder', 'cashout',       'payment',  'Касова витрата постачальнику');

-- Реальные связи между документами
CREATE TABLE `document_link` (
  `id`          int NOT NULL AUTO_INCREMENT,
  `from_type`   varchar(64) NOT NULL,
  `from_id`     int DEFAULT NULL     COMMENT 'Наш internal ID (когда импортирован)',
  `from_ms_id`  varchar(36) DEFAULT NULL COMMENT 'UUID МойСклад (переходный период)',
  `to_type`     varchar(64) NOT NULL,
  `to_id`       int DEFAULT NULL,
  `to_ms_id`    varchar(36) DEFAULT NULL,
  `link_type`   varchar(64) DEFAULT NULL,
  `linked_sum`  decimal(12,2) DEFAULT NULL COMMENT 'linkedSum из МойСклад operations[]',
  `created_at`  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ms_link`  (`from_ms_id`, `to_ms_id`),
  KEY `idx_from`    (`from_type`, `from_id`),
  KEY `idx_to`      (`to_type`,   `to_id`),
  KEY `idx_from_ms` (`from_ms_id`),
  KEY `idx_to_ms`   (`to_ms_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ru_0900_ai_ci;

-- customerorder_link была задел, пуста — заменяется document_link
DROP TABLE IF EXISTS `customerorder_link`;
