-- 008_return_logistics.sql
-- Таблица логистики возвратов товаров
-- Регистрирует факт возврата физического товара (ТТН возврата или ручной возврат)
-- по заказам, которые были в статусе "отправлен"

-- Добавить отсутствующие типы документов (они используются в document_link, но не зарегистрированы)
INSERT IGNORE INTO `document_type` (`code`, `name_uk`, `name_ru`, `direction`, `sort_order`) VALUES
('ttn_np',          'ТТН Нова Пошта',       'ТТН Нова Пошта',        'neutral', 105),
('ttn_up',          'ТТН Укрпошта',          'ТТН Укрпошта',           'neutral', 106),
('salesreturn',     'Повернення товару',      'Возврат товара',         'neutral', 110);

-- Таблица логистики возвратов
-- Каждая запись = один факт возврата физического товара по заказу
-- return_type определяет, как возвращается товар:
--   novaposhta_ttn — ТТН возврата Нова Пошта (ttn_np_id → ttn_novaposhta)
--   ukrposhta_ttn  — ТТН возврата Укрпошта (ttn_up_id → ttn_ukrposhta)
--   manual         — ручной возврат (такси, сам привіз, кур'єр...)
CREATE TABLE `return_logistics` (
  `id`                  int NOT NULL AUTO_INCREMENT,
  `customerorder_id`    int NOT NULL           COMMENT 'Замовлення (що повертається)',
  `demand_id`           int DEFAULT NULL       COMMENT 'Відвантаження, яке повертають (demand.id)',
  `salesreturn_id`      int DEFAULT NULL       COMMENT 'Повернення товару (salesreturn.id, якщо є)',
  `return_type`         enum('novaposhta_ttn','ukrposhta_ttn','manual') NOT NULL
                                               COMMENT 'Спосіб повернення',
  `ttn_np_id`           int DEFAULT NULL       COMMENT 'FK → ttn_novaposhta.id (для novaposhta_ttn)',
  `ttn_up_id`           int DEFAULT NULL       COMMENT 'FK → ttn_ukrposhta.id (для ukrposhta_ttn)',
  `manual_description`  varchar(500) DEFAULT NULL
                                               COMMENT 'Опис способу повернення (такси, сам привіз...)',
  `status`              enum('expected','in_transit','received','cancelled') NOT NULL DEFAULT 'expected'
                                               COMMENT 'expected=очікується, in_transit=в дорозі, received=отримано',
  `received_at`         date DEFAULT NULL      COMMENT 'Дата фактичного отримання товару',
  `comment`             text DEFAULT NULL      COMMENT 'Коментар',
  `created_by`          int DEFAULT NULL       COMMENT 'employee.id — хто зареєстрував',
  `created_at`          datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_co`   (`customerorder_id`),
  KEY `idx_dem`  (`demand_id`),
  KEY `idx_sr`   (`salesreturn_id`),
  KEY `idx_np`   (`ttn_np_id`),
  KEY `idx_up`   (`ttn_up_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ru_0900_ai_ci
  COMMENT='Логістика повернень: ТТН на повернення або ручний факт повернення товару';

-- Добавить return_logistics в document_type
INSERT IGNORE INTO `document_type` (`code`, `name_uk`, `name_ru`, `direction`, `sort_order`) VALUES
('return_logistics', 'Логістика повернення', 'Логистика возврата', 'neutral', 115);

-- Разрешённые переходы: customerorder → salesreturn, salesreturn → return_logistics
INSERT IGNORE INTO `document_type_transition` (`from_type`, `to_type`, `link_type`, `description`) VALUES
('customerorder',    'salesreturn',     'return',    'Повернення по замовленню'),
('salesreturn',      'return_logistics','logistics', 'Логістика повернення товару'),
('demand',           'return_logistics','logistics', 'Логістика повернення відвантаження');
