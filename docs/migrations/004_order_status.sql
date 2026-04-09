-- Migration 004: Order status dictionary + mappings
-- 2026-03-29

-- ── 1. Справочник статусов ────────────────────────────────────────────────────

CREATE TABLE `order_status` (
    `code`       VARCHAR(32)  NOT NULL,
    `sort_order` TINYINT      NOT NULL,
    `is_archive` TINYINT(1)   NOT NULL DEFAULT 0,
    `color`      VARCHAR(16)  NOT NULL DEFAULT 'gray',
    PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `order_status` (`code`, `sort_order`, `is_archive`, `color`) VALUES
('draft',           1, 0, 'gray'),
('new',             2, 0, 'blue'),
('confirmed',       3, 0, 'indigo'),
('waiting_payment', 4, 0, 'orange'),
('in_progress',     5, 0, 'yellow'),
('shipped',         6, 0, 'purple'),
('completed',       7, 1, 'green'),
('cancelled',       8, 1, 'red');

-- ── 2. Переводы (language_id из Papir.languages: 1=ru, 2=uk) ─────────────────

CREATE TABLE `order_status_i18n` (
    `code`        VARCHAR(32)  NOT NULL,
    `language_id` TINYINT      NOT NULL,
    `name`        VARCHAR(128) NOT NULL,
    PRIMARY KEY (`code`, `language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `order_status_i18n` (`code`, `language_id`, `name`) VALUES
('draft',           1, 'Черновик'),
('draft',           2, 'Чернетка'),
('new',             1, 'Новый'),
('new',             2, 'Новий'),
('confirmed',       1, 'Принят'),
('confirmed',       2, 'Прийнятий'),
('waiting_payment', 1, 'Ждём оплату'),
('waiting_payment', 2, 'Очікуємо оплату'),
('in_progress',     1, 'В сборке'),
('in_progress',     2, 'В збірці'),
('shipped',         1, 'Доставляется'),
('shipped',         2, 'Доставляється'),
('completed',       1, 'Выполнен'),
('completed',       2, 'Виконаний'),
('cancelled',       1, 'Отменён'),
('cancelled',       2, 'Скасовано');

-- ── 3. МойСклад UUID → Papir code ────────────────────────────────────────────

CREATE TABLE `order_status_ms_mapping` (
    `ms_state_id`   VARCHAR(36)  NOT NULL,
    `ms_state_name` VARCHAR(128) NOT NULL,
    `papir_code`    VARCHAR(32)  NOT NULL,
    PRIMARY KEY (`ms_state_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `order_status_ms_mapping` (`ms_state_id`, `ms_state_name`, `papir_code`) VALUES
('c2fc692f-dd59-11ea-0a80-03fa00051f8e', 'Новый',                    'new'),
('fc7df394-e69c-11ea-0a80-0140003a2624', 'Тендер',                   'draft'),
('34fe603e-f5be-11eb-0a80-0d4800058862', 'Не смогли связаться',      'new'),
('8b9e1475-dce9-11ea-0a80-006100019351', 'Принят',                   'confirmed'),
('76eb0a35-d752-11ea-0a80-03cf00010e80', 'Принят (2)',                'confirmed'),
('bc41b6b0-d2ad-11ea-0a80-02ef0007cc90', 'Принят (3)',                'confirmed'),
('0ad0421b-64d6-11eb-0a80-095b00002e3b', 'Ждем товар',               'confirmed'),
('34fe6465-f5be-11eb-0a80-0d4800058863', 'Ждем оплату',              'waiting_payment'),
('ad2d88b8-7abf-11eb-0a80-03f80037a302', 'Оплаченный',               'in_progress'),
('cb14819a-d5ca-11ea-0a80-03cc0000f986', 'Оплаченный (2)',            'in_progress'),
('5f821bb6-0877-11eb-0a80-049300051d5e', 'Передан в сборку',         'in_progress'),
('8b254fcf-5f64-11ec-0a80-05b1002d087c', 'Передан в сборку (2)',     'in_progress'),
('fde33ac6-53eb-11eb-0a80-01b2004a71d4', 'Комплект',                 'in_progress'),
('41c486a9-d29a-11ea-0a80-0517000f0d4a', 'Передан в доставку',       'in_progress'),
('023eff4b-7aca-11eb-0a80-03f8003813ff', 'Наложка выкуплена',        'completed'),
('bc5a77c2-d2ad-11ea-0a80-02ef0007cc9f', 'Выполнен',                 'completed'),
('da89dea4-179c-11ec-0a80-09820031f9b6', 'Выполнен (2)',              'completed'),
('41c488a7-d29a-11ea-0a80-0517000f0d4d', 'Отменен',                  'cancelled'),
('41c487f8-d29a-11ea-0a80-0517000f0d4c', 'Отменен (2)',               'cancelled'),
('e394d392-816f-11ec-0a80-021d002fbf0c', 'Потерянный',               'cancelled');

-- ── 4. Papir code × site_id → OC status_id ───────────────────────────────────
-- site_id: 1 = off (officetorg.com.ua), 2 = mff (menufolder.com.ua)

CREATE TABLE `order_status_site_mapping` (
    `papir_code`     VARCHAR(32) NOT NULL,
    `site_id`        TINYINT     NOT NULL,
    `site_status_id` INT         NOT NULL,
    PRIMARY KEY (`papir_code`, `site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `order_status_site_mapping` (`papir_code`, `site_id`, `site_status_id`) VALUES
-- off (site_id=1)
('draft',           1,  0),
('new',             1,  1),
('confirmed',       1,  2),
('waiting_payment', 1, 27),
('in_progress',     1, 20),
('shipped',         1,  3),
('completed',       1,  5),
('cancelled',       1, 16),
-- mff (site_id=2)
('draft',           2,  0),
('new',             2,  1),
('confirmed',       2, 20),
('waiting_payment', 2, 25),
('in_progress',     2, 21),
('shipped',         2,  3),
('completed',       2,  5),
('cancelled',       2, 10);
