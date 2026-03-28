# Papir — Структура базы данных

> Обновлять при любом изменении схемы: добавлении/удалении таблиц, изменении назначения.
> Последнее обновление: 2026-03-27

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
| `customerorder_link` | Связи с другими документами — задел, не используется |
| `customerorder_attr_value` | Доп. атрибуты заказа — задел, не используется |
| `document_number_counter` | Счётчик номеров документов |

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

### 9. Служебные

| Таблица | Назначение |
|---------|-----------|
| `background_jobs` | Мониторинг фоновых CLI-скриптов (страница /jobs) |
| `Documents_attr` | Атрибуты документов МойСклад (импорт) |
| `customentity` | Кастомные сущности МойСклад (импорт) |

---

### 10. Модули доставки — не удалять

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

### 11. Требуют внимания

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
