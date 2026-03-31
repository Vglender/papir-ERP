# Papir — Структура базы данных

> Обновлять при любом изменении схемы: добавлении/удалении таблиц, изменении назначения.
> Последнее обновление: 2026-03-29

---

## Кластеры таблиц

### 1. Товары и каталог

Основной кластер. Papir — единственный источник правды, каскад идёт в off → mff.

| Таблица | Назначение |
|---------|-----------|
| `product_papir` | Главная таблица товаров: цены, остатки, связи с МойСклад/сайтами |
| `product_description` | Названия, описания, SEO-тексты (language_id: 1=RU, 2=UK) |
| `product_seo` | SEO per-site (meta_title, meta_description, seo_url, h1) |
| `product_site` | Привязка товара к сайту: product_id + site_id(1=off,2=mff) + site_product_id |
| `product_image` | Фото товаров — мастер-копии |
| `product_image_site` | Привязка фото к сайтам: image_id + site_id + sort_order |
| `product_stock` | Остатки из МойСклад (зеркало ms.stock_) |
| `product_discount_profile` | Профили скидок по кол-ву (qty_1/price_1..3) |
| `product_price_settings` | Ручные переопределения цен по товару |
| `action_prices` | Акционные цены (price_act, price_base, price_cost) |
| `action_products` | Товары участвующие в акциях |

> ⚠️ `product_papir.id_off` и `id_mf` — устаревшие поля. В новом коде использовать `product_site`.

---

### 2. Атрибуты товаров

| Таблица | Назначение |
|---------|-----------|
| `product_attribute` | Атрибуты: attribute_id, group_id, sort_order, status |
| `product_attribute_description` | Названия атрибутов: attribute_id + language_id + attribute_name |
| `product_attribute_value` | Значения: product_id + attribute_id + language_id + site_id + text |
| `attribute_group` | Группы атрибутов |
| `attribute_group_description` | Названия групп (language_id: 1=RU, 2=UK) |
| `attribute_group_site_mapping` | Маппинг групп Papir → сайт |
| `attribute_site_mapping` | Маппинг атрибутов Papir → сайт (site_attribute_id) |
| `weight_class` / `weight_class_description` / `weight_class_site_mapping` | Единицы веса (1=кг, 2=г) |
| `length_class` / `length_class_description` / `length_class_site_mapping` | Единицы длины (1=см, 2=мм) |

---

### 3. Категории

| Таблица | Назначение |
|---------|-----------|
| `categoria` | Дерево категорий Papir (parent_id, category_off, category_mf, image) |
| `category_description` | Названия категорий (language_id: 1=RU, 2=UK) |
| `category_seo` | SEO per-site per-language (meta_title, meta_description, seo_h1, seo_url) |
| `category_site_mapping` | Маппинг категорий Papir → сайт (site_category_id) |
| `category_images` | Фото категорий |

---

### 4. Цены и поставщики

| Таблица | Назначение |
|---------|-----------|
| `price_suppliers` | Поставщики (is_cost_source — приоритет закупочной цены) |
| `price_supplier_pricelists` | Прайс-листы поставщиков |
| `price_supplier_items` | Строки прайсов (price_cost, price_rrp, stock) |
| `price_supplier_sheet_config` | Конфиг импорта Google Sheets |
| `price_discount_strategy` | Стратегии наценки (small/medium/large %) |
| `price_markup_tiers` | Пороги наценки |
| `price_settings_global` | Глобальные настройки расчёта цен |
| `price_import_run` | Журнал запусков импорта прайсов |
| `product_package` | Упаковки товара (уровни для кол-ва скидок) |

#### Пустые — задел для будущего
`price_strategy`, `price_strategy_rule`, `price_calculation_preview`, `price_calculation_log`, `price_change_log`, `price_quantity_strategy`

---

### 5. Заказы (customerorder)

Строгая финансовая логика — все таблицы связаны FK.

| Таблица | Назначение |
|---------|-----------|
| `customerorder` | Заказ клиента (статусы, суммы, связи с контрагентом/организацией) |
| `customerorder_item` | Строки заказа (товары, кол-во, цены) |
| `customerorder_history` | Журнал изменений заказа |
| `customerorder_party` | Стороны заказа по ролям — задел, не используется |
| `customerorder_attr_value` | Доп. атрибуты заказа — задел, не используется |
| `document_number_counter` | Счётчик номеров документов |

**Справочник статусов заказа** (миграция 004, 2026-03-29):

| Таблица | Назначение |
|---------|-----------|
| `order_status` | Справочник: code, sort_order, is_archive, color |
| `order_status_i18n` | Переводы: code + language_id(1=ru,2=uk) → name |
| `order_status_ms_mapping` | МойСклад state UUID → papir_code (20 записей) |
| `order_status_site_mapping` | papir_code × site_id(1=off,2=mff) → OC status_id |

**Жизненный цикл:** `draft` → `new` → `confirmed` → `waiting_payment` → `in_progress` → `shipped` → `completed` / `cancelled`

Архивные статусы (`is_archive=1`): `completed`, `cancelled`.

**Правила смены статуса (миграция 008, 2026-03-31):**
- → `shipped`: требует активного отгрузочного документа (demand) + активной ТТН
- → `completed`: требует demand + оплаты
- Назад из `completed`: только в `cancelled`
- Назад из `shipped`: заблокировано если есть demand, оплата или активная ТТН без зарегистрированного возврата (`return_logistics`)
- Назад из `in_progress`: заблокировано если есть demand или оплата
- Назад из `confirmed`/`waiting_payment`: разрешено с подтверждением

**Способи доставки** (міграція 009):

| Таблиця | Призначення |
|---------|------------|
| `delivery_method` | Довідник: pickup, courier, novaposhta, ukrposhta. `has_ttn=1` → потрібен TTN-номер |
| `order_delivery` | Факт доставки (кур'єр/самовивіз): status, sent_at, delivered_at, comment |

`customerorder.delivery_method_id` → FK delivery_method (nullable).

**Логистика возвратов** (`return_logistics`, миграция 008):

| Поле | Тип | Назначение |
|------|-----|-----------|
| `customerorder_id` | int | Заказ, по которому возврат |
| `demand_id` | int | Отгрузка (demand.id), которую возвращают |
| `salesreturn_id` | int | Документ возврата (salesreturn.id, если есть) |
| `return_type` | enum | `novaposhta_ttn` / `ukrposhta_ttn` / `manual` |
| `ttn_np_id` | int | FK → ttn_novaposhta.id (для ТТН возврата НП) |
| `ttn_up_id` | int | FK → ttn_ukrposhta.id (для ТТН возврата УП) |
| `manual_description` | varchar(500) | Описание способа ручного возврата |
| `status` | enum | `expected` / `in_transit` / `received` / `cancelled` |
| `received_at` | date | Дата фактического получения товара |

**Использование в коде:**
```php
// Получить название статуса на нужном языке
$r = Database::fetchRow('Papir',
    "SELECT name FROM order_status_i18n
     WHERE code = 'shipped' AND language_id = 2");
// → 'Доставляється'

// Получить OC status_id для сайта
$r = Database::fetchRow('Papir',
    "SELECT site_status_id FROM order_status_site_mapping
     WHERE papir_code = 'shipped' AND site_id = 1");
// → 3

// Конвертировать МС UUID в Papir code
$r = Database::fetchRow('Papir',
    "SELECT papir_code FROM order_status_ms_mapping
     WHERE ms_state_id = '41c486a9-d29a-11ea-0a80-0517000f0d4a'");
// → 'shipped'
```

---

### 6. Контрагенты и организации

Все таблицы связаны FK. Создан в марте 2026.

| Таблица | Записей | Назначение |
|---------|---------|-----------|
| `counterparty` | 8 | Контрагенты (type: person/company/fop/department) |
| `counterparty_company` | 4 | Расширение для юр. лиц (okpo, inn, адреса, банк) |
| `counterparty_person` | 4 | Расширение для физ. лиц (ФИО, телефон, telegram) |
| `counterparty_relation` | 8 | Связи между контрагентами (contact_person, employee, director...) |
| `organization` | 4 | Наши организации |
| `organization_bank_account` | 0 | Банковские счета организаций |
| `employee` | 6 | Сотрудники |
| `store` | 3 | Склады |
| `contract` | 0 | Договора |
| `project` | 0 | Проекты |
| `vat_rate` | 2 | Ставки НДС |

> ⚠️ `counterparty_company`, `counterparty_person`, `counterparty_relation` — структура создана, данные есть, но в коде не читаются. Незавершённая часть модели контрагентов.

---

### 7. Справочники и настройки

| Таблица | Назначение |
|---------|-----------|
| `sites` | Сайты: off (officetorg), mff (menufolder) |
| `languages` | Языки Papir (1=ru, 2=uk) |
| `site_languages` | Маппинг языков Papir → языки сайтов |
| `manufacturers` | Производители (с off_id, mff_id для каскада) |

---

### 8. AI-генерация контента

| Таблица | Назначение |
|---------|-----------|
| `ai_generation_log` | Журнал запусков генерации |
| `ai_instructions` | Инструкции для генерации по типам контента |
| `ai_site_fields` | Поля per-site для генерации |

---

### 9. Система линкования документов

Три таблицы, реализующие бизнес-логику связей между документами (миграция 006, 2026-03-29).

| Таблица | Назначение |
|---------|-----------|
| `document_type` | Справочник типов документов (10 типов: customerorder, demand, paymentin, ...) |
| `document_type_transition` | Разрешённые переходы: `from_type → to_type`. **Чего нет — то запрещено.** Источник бизнес-правил для UI |
| `document_link` | Реальные связи между документами: `from_type/from_id/from_ms_id` → `to_type/to_id/to_ms_id` + `linked_sum` |

**Концепция:**
- `document_type_transition` — whitelist переходов. Из заказа можно создать отгрузку и платёж входящий, но нельзя расходный платёж. Из этой таблицы же строятся подсказки UI "что можно создать из документа".
- `document_link` — реальные связи. `from_id`/`to_id` — наши internal ID, `from_ms_id`/`to_ms_id` — UUID МойСклад (переходный период). Заполняются скриптом `scripts/sync_ms_document_links.php`.
- Нет FK-целостности намеренно: данные из МойСклад приходят в произвольном порядке.

**Синк (два скрипта):**
- `scripts/sync_ms_document_links_from_mirror.php` — заполняет из ms-зеркала (быстро, без API): demand→customerorder, supply→purchaseorder, salesreturn→demand, purchaseorder→customerorder/supply
- `scripts/sync_ms_document_links.php` — заполняет из МойСклад API напрямую (медленнее): operations[] из paymentin/paymentout/cashin/cashout

**Чтение связей (PHP):**

```php
// Все документы связанные с заказом (платежи, отгрузки и т.д.)
// — ищем где заказ является ЦЕЛЬЮ (to_id) или ИСТОЧНИКОМ (from_id)
$orderId = 12345;

$links = Database::fetchAll('Papir',
    "SELECT from_type, from_id, from_ms_id, to_type, to_id, to_ms_id, link_type, linked_sum
     FROM document_link
     WHERE (to_type = 'customerorder' AND to_id = {$orderId})
        OR (from_type = 'customerorder' AND from_id = {$orderId})"
);

// Платежи по заказу (входящие)
$payments = Database::fetchAll('Papir',
    "SELECT dl.from_type, dl.from_id, dl.from_ms_id, dl.linked_sum,
            fb.doc_number, fb.moment, fb.sum
     FROM document_link dl
     LEFT JOIN finance_bank fb ON fb.id = dl.from_id
     WHERE dl.to_type = 'customerorder'
       AND dl.to_id = {$orderId}
       AND dl.from_type IN ('paymentin', 'cashin')"
);

// Отгрузки по заказу
$demands = Database::fetchAll('Papir',
    "SELECT dl.from_ms_id, dl.link_type,
            d.name, d.moment, d.sum
     FROM document_link dl
     LEFT JOIN ms.demand d ON d.meta = dl.from_ms_id
     WHERE dl.to_type = 'customerorder'
       AND dl.to_id = {$orderId}
       AND dl.from_type = 'demand'"
);

// Что можно создать из заказа (для UI-кнопок)
$allowedNext = Database::fetchAll('Papir',
    "SELECT dtt.to_type, dt.name_uk, dt.direction
     FROM document_type_transition dtt
     JOIN document_type dt ON dt.code = dtt.to_type
     WHERE dtt.from_type = 'customerorder'
     ORDER BY dt.sort_order"
);
```

**Текущие данные (2026-03-29):**

| from_type | to_type | Записей |
|-----------|---------|---------|
| demand | customerorder | 70,950 |
| paymentin | customerorder | 44,030 |
| cashin | customerorder | 30,052 |
| purchaseorder | customerorder | 2,197 |
| purchaseorder | supply | 9,704 |
| supply | purchaseorder | 8,894 |
| salesreturn | demand | 2,842 |
| paymentout | purchaseorder | 2,264 |
| paymentin | demand | 1,276 |

---

### 10. Финансы (модуль finance)

| Таблица | Назначение |
|---------|-----------|
| `finance_bank` | Банковские платежи (прихід/витрати/переводи). Поля: direction, moment, doc_number, sum, cp_id, expense_category_id, payment_purpose, description, is_moving, source, expense_item_ms |
| `finance_cash` | Кассовые операции. Поля: direction, moment, doc_number, sum, agent_ms, expense_category_id, payment_purpose, description, is_moving, source |
| `finance_expense_category` | Статьи расходов: id, name, sort_order, status. Используется в finance_bank.expense_category_id (только для direction='out') |

---

### 11. Служебные

| Таблица | Назначение |
|---------|-----------|
| `background_jobs` | Мониторинг фоновых CLI-скриптов (страница /jobs) |
| `Documents_attr` | Атрибуты документов МойСклад (импорт) |
| `customentity` | Кастомные сущности МойСклад (импорт) |

---

### 12. Модули доставки — не удалять

Используются действующими модулями. Удалить после создания новых модулей доставки.

#### [УкрПошта]
| Таблица | Назначение |
|---------|-----------|
| `ttn_ukrposhta` | ТТН Укрпошты |
| `shipment_groups` | Группы отправок |
| `shipment_group_links` | Связи товаров с группами отправок |
| `sender_ukr` | Отправители |

#### [НоваПошта]
| Таблица | Назначение |
|---------|-----------|
| `Counterparties_np` | Контрагенты/получатели НП |
| `CounterpartyContactPersons` | Контактные лица контрагентов НП (Ref, Phones, Description, Ref_counterparty) |
| `novaposhta_cities` | Справочник городов |
| `np_warehouses` | Отделения и склады |
| `street_np` | Справочник улиц (адресная доставка) |
| `areas_np` | Справочник областей |
| `np_sender` | Отправители |

---

### 13. Требуют внимания

| Таблица | Проблема |
|---------|---------|
| `image` | Старая система фото (50К записей). Новая — `product_image`. Ещё читается в `src/CatalogRepository.php` и аудит-скриптах. Удалить после полной миграции. |
| `counterparty_person/company/relation` | Данные есть, в коде не используются. Завершить или удалить при рефакторинге модуля контрагентов. |

---

## Архивные дампы

| Файл | Дата | Удалить после |
|------|------|--------------|
| `/backup/papir_legacy_tables_20260327_DROP_AFTER_20260427.sql` | 2026-03-27 | 2026-04-27 |

---

## FK-связи

Внешние ключи есть только в кластере `customerorder` + `counterparty`. Остальные таблицы — без FK намеренно: данные синхронизируются с внешними системами (МойСклад, off, mff), порядок вставки непредсказуем, целостность контролируется кодом.
