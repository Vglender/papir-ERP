-- 009_delivery_method.sql
-- Справочник способов доставки + таблица фактов доставки (курьер/самовывоз)
-- Расширяет систему доставки за пределы только ТТН

-- Справочник способов доставки
CREATE TABLE `delivery_method` (
  `id`         int NOT NULL AUTO_INCREMENT,
  `code`       varchar(32)  NOT NULL,
  `name_uk`    varchar(128) NOT NULL,
  `name_ru`    varchar(128) NOT NULL,
  `has_ttn`    tinyint(1)   NOT NULL DEFAULT 0
                 COMMENT '1 = потрібен TTN-номер (НП/УП), 0 = факт без ТТН (кур\'єр/самовивіз)',
  `sort_order` int          NOT NULL DEFAULT 0,
  `status`     tinyint(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ru_0900_ai_ci
  COMMENT='Способи доставки замовлень';

INSERT INTO `delivery_method` (`code`, `name_uk`, `name_ru`, `has_ttn`, `sort_order`) VALUES
('pickup',     'Самовивіз',  'Самовывоз',  0, 10),
('courier',    'Кур\'єр',    'Курьер',     0, 20),
('novaposhta', 'Нова Пошта', 'Нова Пошта', 1, 30),
('ukrposhta',  'Укрпошта',   'Укрпошта',   1, 40);

-- Таблица фактов доставки (для курьера и самовывоза; НП/УП используют ttn_novaposhta/ttn_ukrposhta)
-- Можно использовать и для НП/УП как верхнеуровневый агрегат, но основной сценарий — non-TTN
CREATE TABLE `order_delivery` (
  `id`                  int NOT NULL AUTO_INCREMENT,
  `customerorder_id`    int NOT NULL          COMMENT 'FK → customerorder.id',
  `demand_id`           int DEFAULT NULL      COMMENT 'FK → demand.id (какое отгрузочное доставляется)',
  `delivery_method_id`  int NOT NULL          COMMENT 'FK → delivery_method.id',
  `status`              enum('pending','sent','delivered','cancelled') NOT NULL DEFAULT 'pending'
                          COMMENT 'pending=ожидает, sent=отправлено/передано, delivered=доставлено',
  `sent_at`             datetime DEFAULT NULL COMMENT 'Дата/время отправки или передачи курьеру',
  `delivered_at`        datetime DEFAULT NULL COMMENT 'Дата/время факта получения',
  `comment`             text DEFAULT NULL,
  `created_by`          int DEFAULT NULL      COMMENT 'employee.id',
  `created_at`          datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_co`  (`customerorder_id`),
  KEY `idx_dm`  (`delivery_method_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ru_0900_ai_ci
  COMMENT='Факти доставки замовлень (кур\'єр, самовивіз тощо)';

-- Привязать способ доставки к заказу
ALTER TABLE `customerorder`
  ADD COLUMN `delivery_method_id` INT DEFAULT NULL
    COMMENT 'FK → delivery_method.id'
    AFTER `sales_channel`,
  ADD KEY `idx_co_delivery_method` (`delivery_method_id`);