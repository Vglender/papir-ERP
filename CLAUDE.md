# Papir CRM — Архитектура и соглашения

## Язык общения

Язык общения с пользователем — **русский**.

## Обзор

Внутренняя CRM/ERP система для управления товарами, ценами, заказами и интеграциями с внешними сервисами (МойСклад, PrivatBank, Моно, UKRSIB, Google). Управляет двумя активными сайтами. Основной модуль — управление ценами с каскадным обновлением на сайты и в ERP.

## Архитектура серверов

**Два активных сайта:**

| Сайт | Сервер | Файлы | DB alias |
|------|--------|-------|----------|
| https://officetorg.com.ua | **Этот сервер** | `/var/www/menufold/data/www/officetorg.com.ua/` | `off` |
| https://menufolder.com.ua | **Удалённый сервер** (FTP доступен) | FTP: `menufold.ftp.tools:21` | `mff` |

> ⚠️ Папка `/var/www/menufold/` — историческое название, **не связана** с сайтом menufolder.com.ua. Это просто папка на нашем сервере.

**Изображения:** мастер-копии хранятся на этом сервере по пути `/var/www/menufold/data/www/officetorg.com.ua/image/`. Для отображения на menufolder.com.ua файлы синхронизируются на mff-сервер через FTP — `MffFtpSync` (`modules/shared/MffFtpSync.php`). FTP root на mff — `/`, путь к изображениям: `/menufolder.com.ua/www/image/`.

**Прочие DB aliases** (`mf`, `prm`, `trend`, `mf_2022`) — старые удалённые серверы, не используются в активной разработке. `ms` — зеркало МойСклад, временно ещё участвует в проекте.

**Точка входа**: `index.php` → `src/Router.php` → нужный модуль/страница
**Namespace**: `Papir\Crm\`
**PHP**: 5.6-совместимый код (см. соглашения ниже)

---

## Структура проекта

```
/var/www/papir/
├── index.php               # Точка входа, запускает Router
├── src/                    # Ядро приложения
│   ├── Router.php          # Маршрутизатор (73 маршрута)
│   ├── Request.php         # Обёртка над $_GET/$_POST
│   ├── ViewHelper.php      # h() для экранирования вывода
│   ├── lib_stock_update.php # Функции обновления остатков
│   ├── CatalogRepository.php
│   └── PriceRepository.php
├── modules/                # Функциональные модули
├── pages/                  # HTML/PHP шаблоны страниц
├── assets/                 # CSS, JS, шрифты, изображения
└── vendor/                 # Composer зависимости
```

---

## Модули

### `prices` — Управление ценами (основной модуль)

Самый сложный модуль. Строит цепочку: загрузка прайсов поставщиков → расчёт цен → выгрузка на сайты и в МойСклад.

```
modules/prices/
├── prices_bootstrap.php        # Подключает все классы модуля
├── index.php                   # Контроллер страницы /prices
├── suppliers.php               # Контроллер страницы /prices/suppliers
├── domain/                     # Бизнес-логика расчёта цен
│   ├── PriceEngine.php         # Оркестратор расчёта (фасад)
│   ├── PurchasePriceResolver.php   # Определяет закупочную цену
│   ├── BasePriceCalculator.php     # Базовый расчёт продажной цены
│   ├── RrpPriceAdjuster.php        # Корректировка по RRP
│   ├── DiscountStrategyResolver.php
│   ├── QuantityThresholdResolver.php  # Пороги кол-ва для скидок
│   ├── DiscountPriceCalculator.php
│   └── PriceConsistencyValidator.php
├── repositories/               # Доступ к данным
│   ├── ProductPriceRepository.php
│   ├── PricelistItemRepository.php   # getBestCostPrice() — ключевой метод
│   ├── ProductDiscountProfileRepository.php
│   ├── DiscountStrategyRepository.php
│   ├── QuantityStrategyRepository.php
│   ├── GlobalSettingsRepository.php
│   ├── ProductPackageRepository.php
│   ├── SupplierRepository.php
│   └── PricelistRepository.php
├── services/                   # Высокоуровневые операции
│   ├── DiscountProfileBuilder.php  # Полный цикл: расчёт → сохранение
│   ├── OpenCartPriceExport.php     # Выгрузка в OpenCart (off, mff)
│   ├── MoySkladPriceExport.php     # Выгрузка в МойСклад через API
│   ├── MoySkladPriceSync.php       # Импорт себестоимости из ms.stock_
│   ├── GoogleSheetsPriceSync.php
│   ├── PriceRecalculationService.php
│   ├── PriceSyncService.php
│   └── PriceStrategyAutoSelector.php
├── api/                        # API эндпоинты (POST → JSON)
│   ├── recalculate_all.php     # Массовый пересчёт (батчи)
│   ├── recalculate_one.php
│   ├── push_prices.php         # Выгрузка на сайты + МС (phase=sites|ms)
│   ├── save_pricelist_item.php # Сохранение строки прайса + каскад
│   ├── sync_supplier.php
│   ├── update_stock.php
│   └── ...
└── views/                      # Шаблоны (включаются из контроллеров)
    ├── index.php
    └── suppliers.php
```

#### Каскад обновления цен (save_pricelist_item.php)

При изменении цены в прайсе поставщика (через UI):

1. **Синхронизация RRP** — `MAX(price_rrp)` из всех активных прайсов → `product_papir.price_rrp` (только если manual_rrp_enabled=0)
2. **Пересчёт** — `DiscountProfileBuilder::build($productId)` → `PriceEngine::calculate()`
3. **Сохранение** — `product_papir` (все цены) + `product_discount_profile` (скидки по кол-ву) + `action_prices`
4. **Выгрузка на сайты** — `OpenCartPriceExport` → `off` (offtorg) + `mff`
5. **Выгрузка в МойСклад** — `MoySkladPriceExport` → API PUT `/entity/product/{id_ms}`

При изменении остатка (stock) в прайсе поставщика:
1. Пересчитывается `product_papir.quantity = SUM(psi.stock WHERE not ignored)`
2. Обновляется `oc_product.quantity` в off + mff

#### Приоритет закупочной цены (`PurchasePriceResolver`)

1. `manual_cost` (ручное значение, если включено)
2. `price_supplier_items` — `ORDER BY is_cost_source DESC, price_cost ASC` (is_cost_source=1 имеет приоритет)
3. `product_papir.price_supplier` / `price_accounting_cost`
4. legacy `price_cost`

#### Источники RRP

- Только из реальных прайсов поставщиков (`price_supplier_items.price_rrp`)
- Прайс "Склад" (source_type=moy_sklad) **не импортирует** price_rrp — это была бы цикличная зависимость (наша же цена → МойСклад salePrice → RRP → наша цена)
- При `manual_rrp_enabled=1` — RRP из `product_price_settings.manual_rrp`

---

### `moysklad` — МойСклад ERP

```
modules/moysklad/
├── moysklad_api.php        # Класс MoySkladApi
└── storage/moysklad_auth.php  # Basic auth credentials
```

**MoySkladApi** — REST клиент с rate limiting (66700 мкс = ~15 req/s):
- `query($url)` — GET запрос
- `querySend($url, $data, 'PUT'|'POST')` — отправка данных, gzip-aware
- `getEntityBaseUrl()` — возвращает `https://api.moysklad.ru/api/remap/1.2/entity/` (с завершающим `/entity/`)

**Важно**: `getEntityBaseUrl()` уже содержит `entity/`. Для построения URL:
```php
$entityBase = $ms->getEntityBaseUrl();          // .../entity/
$rootBase   = substr($entityBase, 0, -strlen('entity/')); // .../1.2/
$url = $entityBase . 'product/' . $idMs;        // правильно
$url = $entityBase . 'entity/product/' . $idMs; // НЕПРАВИЛЬНО — двойной entity
```

**ID типов цен** (MoySklad):
- retail:    `41b88405-d29a-11ea-0a80-0517000f0d2d`
- dealer:    `7f25e9bf-74d7-11eb-0a80-00ab002702ef`
- wholesale: `7f25e83a-74d7-11eb-0a80-00ab002702ee`
- cost:      `cc096389-8a9b-11eb-0a80-076100219825`
- currency:  `41b7ab2b-d29a-11ea-0a80-0517000f0d2c`

**Атрибуты продукта** (MoySklad):
- link_off:     `04c14a89-7d03-11ee-0a80-0f6b001ba2ae`
- links_mf:     `6df01ab4-254f-11f1-0a80-1452001ab090`
- links_prom:   `ea90c75e-c5cf-11ee-0a80-173a0031191b`

**Цены в МойСклад в копейках** (умножать на 100, округлять до int).

---

### `database` — Слой БД

```
modules/database/
├── database.php            # Точка подключения
├── config/databases.php    # Конфигурация всех соединений
└── src/Database.php        # Класс Database (статические методы)
```

**Database API:**
```php
Database::fetchRow($db, $sql)     // ['ok'=>bool, 'row'=>array|null]
Database::fetchAll($db, $sql)     // ['ok'=>bool, 'rows'=>array]
Database::query($db, $sql)        // ['ok'=>bool, 'affected_rows'=>int]
Database::update($db, $table, $data, $where)  // $where — массив ['col'=>val]
Database::insert($db, $table, $data)
Database::exists($db, $table, $where)         // ['ok'=>bool, 'exists'=>bool]
Database::upsertOne($db, $table, $data, $key) // INSERT ... ON DUPLICATE KEY UPDATE
Database::escape($db, $value)
```

**Базы данных:**

| Алиас       | Хост                    | БД                  | Назначение                  |
|-------------|-------------------------|---------------------|-----------------------------|
| `Papir`     | localhost               | Papir               | Основная CRM БД             |
| `off`/`offtorg` | localhost           | menufold_offtorg    | OpenCart — officetorg.com.ua (файлы на этом сервере) |
| `ms`        | localhost               | ms                  | Зеркало МойСклад (stock_) — временно участвует |
| `mff`       | menufold.mysql.tools    | menufold_mff        | OpenCart — menufolder.com.ua (удалённый сервер, только DB) |
| `mf`        | menufold.mysql.tools    | menufold_new        | Старый сервер, не используется |
| `prm`       | menufold.mysql.tools    | menufold_prm        | Старый сервер, не используется |
| `trend`     | menufold.mysql.tools    | menufold_trends     | Аналитика/тренды            |

---

### Остальные модули

| Модуль           | Назначение                                         |
|------------------|----------------------------------------------------|
| `action`         | Акционные скидки, публикация в Merchant. **При discount=0 и super_discont=0 — запись удаляется из action_products, action_prices и oc_product_special.** |
| `customerorder`  | Управление заказами клиентов                       |
| `payments_sync`  | Сверка банковских платежей с заказами              |
| `bank_monobank`  | API Monobank                                       |
| `bank_privat`    | API PrivatBank (выписки, балансы)                  |
| `bank_ukrsib`    | API UKRSIBBANK (OAuth2 + RSA SHA512)               |
| `merchant`       | Google Shopping (Merchant Center)                  |
| `catalog`        | Каталог товаров (просмотр, фильтрация, детали по товару) |

---

## Ключевые таблицы БД (Papir)

| Таблица                      | Назначение                                           |
|------------------------------|------------------------------------------------------|
| `product_papir`              | Товары: цены, остатки, связи с сайтами и МС          |
| `product_discount_profile`   | Профиль скидок по кол-ву (qty_1/price_1..3)          |
| `product_price_settings`     | Ручные переопределения цен по товару                 |
| `price_suppliers`            | Поставщики (`is_cost_source` — приоритет закупки)    |
| `price_supplier_pricelists`  | Прайс-листы поставщиков                              |
| `price_supplier_items`       | Строки прайсов (price_cost, price_rrp, stock)        |
| `price_discount_strategy`    | Стратегии наценки (small/medium/large %)             |
| `price_quantity_strategy`    | Стратегии порогов кол-ва для скидок                  |
| `product_package`            | Упаковки товара (уровни 1-3 для кол-ва скидок)       |
| `action_prices`              | Акционные цены (price_act, price_base, price_cost)   |
| `product_stock`              | Остатки из МойСклад (зеркало ms.stock_)              |
| `product_site`               | Привязка товаров к сайтам: product_id, site_id(1=off,2=mff), site_product_id. Уникальный: (product_id,site_id) и (site_id,site_product_id) |
| `product_image`              | Фото товаров (мастер): image_id, product_id, path, sort_order |
| `product_image_site`         | Привязка фото к сайтам: image_id, site_id=1(off)/2(mff), sort_order |

**Ключевые поля `product_papir`:**
- `id_off` — ⚠️ **устаревшее поле**, product_id в OpenCart offtorg. Использовать только для legacy-кода и ms-интеграции (ms.stock_.model = id_off). **В новом коде брать из `product_site`.**
- `id_mf`  — ⚠️ **устаревшее поле**, product_id в OpenCart mff. **В новом коде брать из `product_site`.**
- `id_ms`  — UUID товара в МойСклад
- `id_off` используется как `code` при выгрузке в МойСклад (поэтому ms.stock_.model = id_off — это внешняя зависимость, менять нельзя)

**Получение site_product_id (новый способ):**
```php
// Вместо product_papir.id_off — использовать product_site:
// site_id=1 → off (officetorg), site_id=2 → mff (menufolder)
$r = Database::fetchRow('Papir',
    "SELECT site_product_id FROM product_site
     WHERE product_id = {$productId} AND site_id = 1 LIMIT 1");
$idOff = ($r['ok'] && $r['row']) ? (int)$r['row']['site_product_id'] : 0;

// Или при JOIN в списочных запросах:
LEFT JOIN product_site ps_off ON ps_off.product_id = pp.product_id AND ps_off.site_id = 1
LEFT JOIN product_site ps_mff ON ps_mff.product_id = pp.product_id AND ps_mff.site_id = 2
// → ps_off.site_product_id = id_off, ps_mff.site_product_id = id_mff
```

---

## Маршрутизация

`src/Router.php` — единственный `index.php` принимает все запросы через nginx `try_files`.

```php
// Пример добавления маршрута:
'/prices/api/push_prices' => '/modules/prices/api/push_prices.php',
```

Все API эндпоинты — POST, возвращают JSON. Начинаются с `/prices/api/`.

**Request.php** — получение параметров:
```php
Request::postInt('item_id', 0)      // $_POST, приводит к int
Request::postString('phase', '')    // $_POST, строка
Request::getInt('show_all', 0)      // $_GET
```

---

## Соглашения по коду

### Поиск товаров

Поиск товара в любом модуле всегда выполняется по трём полям одновременно:

1. **`product_id`** — внутренний Papir ID (`product_papir.product_id`)
2. **`product_article`** — артикул товара
3. **`name`** / **`raw_name`** — контекстный поиск по названию (`LIKE '%...%'`)

Если таблица не содержит `product_id` напрямую — делать `LEFT JOIN product_papir pp ON pp.product_id = ...` и искать по `CAST(pp.product_id AS CHAR)`.

> ⚠️ Старый код может использовать `id_off` вместо `product_id` в поиске — при правке переводить на `product_id`.

---

### PHP 5.6 совместимость

```php
// НЕЛЬЗЯ использовать:
$x = $arr['key'] ?? 'default';   // null coalescing ??
$arr = ['a', 'b'];               // короткий синтаксис массивов в старых местах
function foo(int $x): string {}  // type hints для скалярных типов

// НУЖНО:
$x = isset($arr['key']) ? $arr['key'] : 'default';
$arr = array('a', 'b');
```

### Структура API эндпоинта

```php
<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../prices_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

// ... логика ...

echo json_encode(array('ok' => true, 'data' => $result));
```

### Стандартный формат ответа

```json
{ "ok": true,  "data": ... }
{ "ok": false, "error": "описание ошибки" }
```

Батчевые операции возвращают также:
```json
{ "ok": true, "processed": 50, "errors": [], "total": 4190, "next_offset": 50 }
```

### Батчевая обработка (offset/limit)

JS вызывает API в цикле, передавая `next_offset` пока он не `null`:

```javascript
function runBatch(offset) {
    fetch('/prices/api/recalculate_all', { method: 'POST', body: 'offset='+offset+'&limit=100' })
    .then(r => r.json()).then(d => {
        if (d.next_offset !== null) runBatch(d.next_offset);
    });
}
```

### Экранирование в HTML

```php
<?php echo ViewHelper::h($value); ?>  // всегда для пользовательских данных
```

### Работа с БД

```php
// Правильно: проверяем ok и наличие данных
$r = Database::fetchRow('Papir', "SELECT ...");
if ($r['ok'] && !empty($r['row'])) {
    $val = $r['row']['field'];
}

// WHERE всегда массив (не строка):
Database::update('Papir', 'product_papir',
    array('price_sale' => 100.0),
    array('product_id' => $productId)  // массив, не строка!
);
```

### Добавление нового API эндпоинта

1. Создать файл `modules/prices/api/my_action.php`
2. Добавить маршрут в `src/Router.php`:
   ```php
   '/prices/api/my_action' => '/modules/prices/api/my_action.php',
   ```
3. После изменений — перезагрузить PHP-FPM: `systemctl reload php-fpm`

---

## OpenCart — структура скидок

Таблица `oc_product_discount` (в базах `off` и `mff`):

| customer_group_id | Тип          | qty | price              |
|-------------------|--------------|-----|--------------------|
| 1, 4              | Объёмные     | qty_1/2/3 | price_1/2/3  |
| 2                 | Оптовая      | 1   | price_wholesale    |
| 3                 | Дилерская    | 1   | price_dealer       |

Даты: `date_start = сегодня`, `date_end = сегодня + 365 дней`.
При обновлении: DELETE все строки для product_id, затем INSERT новые.

---

## Остатки — цикл обновления

### Кронзадачи
- `cron/sync_stock.php` — каждый час :00 (7-22): полный цикл обновления остатков
- `cron/sync_quantity.php` — каждый час :05 (7-22): выгрузка quantity на сайты

### Цикл sync_stock.php
```
МойСклад API (stock report)
    → ms.stock_  (updateStockFromMs)
    → Papir.product_stock  (зеркало)
    → price_supplier_items.stock для "Склад"  (syncWarehouseStock)
    → price_supplier_items.stock для "Виробництво"  (syncVirtualStock из ms.virtual)
    → product_papir.quantity = SUM(price_supplier_items.stock WHERE match_type != 'ignored')  (recalcQuantity)
```

Функции в `src/lib_stock_update.php`, вызываются из `cron/sync_stock.php` и `/prices/api/update_stock`.

### Ручное изменение stock в прайсе поставщика (save_pricelist_item.php)
При ручном изменении остатка строки прайса (через UI /prices/suppliers):
1. Пересчитывается `product_papir.quantity = SUM(psi.stock WHERE not ignored)`
2. Обновляется `oc_product.quantity` в off + mff (OpenCart)

### sync_quantity.php
Читает `product_papir.quantity` (status=1) → обновляет `oc_product.quantity` в off + mff.
Запускается после sync_stock.php.

---

## Важные особенности и ловушки

1. **`is_cost_source DESC`** в `getBestCostPrice()` — поставщик с `is_cost_source=1` имеет приоритет для закупочной цены (не ASC!).

2. **RRP из МойСклад не импортируется** — `MoySkladPriceSync` устанавливает `price_rrp=null`, потому что `ms.stock_.salePrice` — это наша же розничная цена, цикличная зависимость.

3. **`getEntityBaseUrl()` содержит `/entity/`** — не добавлять `entity/` при построении URL.

4. **`push_prices.php` фазовый**: `phase=sites` (off+mff, быстро) → `phase=ms` (МойСклад, ~15 req/s). При ошибке в фазе — стоп.

5. **PHP-FPM кэширует Router.php** — после изменений маршрутов нужен `systemctl reload php-fpm`.

6. **Цены в МойСклад в копейках** — `(int)round($price * 100)`.

7. **`product_stock`** — зеркало `ms.stock_` в Papir. Warehouse stock для деталей товара в каталоге (не `ms.stock_` напрямую).

8. **action module**: при нулевых дисконтах (discount=0, super_discont=0) — действие удаляется. `ActionRepository::getAll()` фильтрует `WHERE discount > 0 OR super_discont > 0` чтобы не считать нулевые записи.

9. **Фото товаров** — новая система: `product_image` (мастер) + `product_image_site` (per-site). `ProductImageService` в `modules/shared/`. Каскад: upload/delete/replace → syncToSite(productId, siteId) → `off.oc_product.image` + `off.oc_product_image` (и mff аналогично). `oc_product_image` требует `uuid varchar(36) NOT NULL` — при INSERT генерировать через `sprintf('%04x%04x-...')`.

10. **`image_audit.php --fix-broken`** — проверяет файлы только по off-пути (`/var/www/menufold/data/www/officetorg.com.ua/image/`). **Не применять к mff** — mff сервер отдельный, его файлы недоступны локально.

11. **Два отдельных сервера**:
    - `officetorg.com.ua` (off) — на ЭТОМ сервере. Файлы изображений: `/var/www/menufold/data/www/officetorg.com.ua/image/`. Папка `menufold` в пути — историческое название, не связано с menufolder.com.ua.
    - `menufolder.com.ua` (mff) — на ДРУГОМ сервере. БД доступна через `menufold.mysql.tools`. FTP: `menufold.ftp.tools:21`, user `menufold_vas`. Образы хранятся в `/menufolder.com.ua/www/image/` (относительно FTP root `/`).
    - **Синхронизация изображений**: `ProductImageService` при загрузке/удалении/замене фото автоматически вызывает `MffFtpSync` для зеркалирования на mff. Bulk sync: `scripts/sync_images_to_mff.php` (только для товаров в product_site site_id=2).

---

### `catalog` — Каталог товаров

```
modules/catalog/
├── catalog_bootstrap.php           # Подключает классы модуля
├── index.php                       # Контроллер страницы /catalog
├── manufacturers.php               # Контроллер страницы /manufacturers
├── category_mapping.php            # Контроллер страницы /category-mapping
├── categories.php                  # Контроллер страницы /categories
├── repositories/
│   └── CatalogRepository.php      # Только Papir DB — список, детали, stock
├── api/
│   ├── get_manufacturers.php
│   ├── save_manufacturer.php       # Привязка производителя к товару (каскад off+mff)
│   ├── save_manufacturer_record.php # CRUD производителя (каскад off+mff oc_manufacturer)
│   ├── delete_manufacturer.php
│   ├── get_site_categories.php     # Категории выбранного сайта (для панели маппинга)
│   ├── save_category_mapping.php   # Сохранение маппинга категорий
│   ├── get_category.php            # GET ?id= → {basic, ua, ru, seo[site][lang], sites, languages}
│   ├── save_category.php           # POST: names + status + sort_order → каскад off+mff
│   └── save_category_seo.php       # POST: SEO per site → upsert category_seo + каскад url aliases
└── views/
    ├── index.php                   # HTML шаблон каталога
    ├── manufacturers.php           # Реестр производителей (sidebar pattern)
    ├── category_mapping.php        # Инструмент маппинга категорий
    └── categories.php              # CRUD категорий: дерево слева, 2 карточки справа
```

**`/categories` — інтерфейс категорій:**
- Ліво: `CategoryTree` на всю висоту (`calc(100vh - 112px)`), з пошуком
- Право: дві картки — Картка 1 (назви UA/RU, статус, порядок) + Картка 2 (SEO per сайт × мова)
- Навігація через `history.pushState` + AJAX (щоб зберегти стан дерева)
- Картка SEO: вкладки сайтів → вкладки мов → SEO URL, H1, meta title, meta description + посилання `cat_url`
- Збереження картки 1: POST `/categories/api/save` (каскадує назви в off+mff)
- Збереження картки 2: POST `/categories/api/save_seo` (каскадує meta в oc_category_description + url aliases)

**Источники данных (только Papir):**
- `product_papir` — товары, цены, статус, quantity
- `product_description` — названия, описания (language_id=1 RU, language_id=2 UA)
- `product_stock` — склад (зеркало ms.stock_); warehouse stock для детального просмотра
- `action_prices` — акционные цены (вместо off.oc_product_special)
- `product_discount_profile` — скидки по количеству (вместо ms.discount)
- `category_description` — названия категорий
- `image` — доп. фото товара

**Фильтры:** all / with_stock (quantity > 0) / with_action (price_act не null)

---

## UI — Shared система стилів

### Принцип: Papir як єдине джерело правди

Всі зовнішні системи (off, mff, МойСклад) — похідні від Papir. Напрям каскаду завжди: **Papir → off → mff → МойСклад**. Ніколи у зворотному напрямку (крім імпорту/синхронізації як окремої операції).

### Принцип: одна сутність — один інтерфейс

Кожна сутність редагується тільки в **одному** виділеному інтерфейсі. В інших місцях — лише вибір/прив'язка через picker.

- ✅ `/manufacturers` — CRUD виробників
- ✅ `/catalog` — лише вибір виробника через picker-модалку
- ❌ Не можна редагувати назву виробника прямо в каталозі

Picker завжди містить посилання «Управляти →» на інтерфейс-власника у новій вкладці.

### Де знаходяться нові модулі

Всі нові модулі та сторінки — тільки в `modules/` і `pages/`. Директорія `assets/` — залишки старої системи, яка буде видалена. Не брати звідти стилі, не використовувати як зразок.

### Shared UI файли

```
modules/shared/
├── ui.css          # Всі базові компоненти (змінні, кнопки, таблиця, форми, модалка, пагінація, toast)
├── layout.php      # Відкриваючий <head> + <body> (підключає ui.css)
├── layout_end.php  # Закриваючий </body></html>
└── api/
    ├── upload_image.php   # POST entity_type+entity_id+image → GD resize 1200px JPEG85% → DB+каскад
    ├── delete_image.php   # POST entity_type+image_id → DB+диск+каскад
    └── replace_image.php  # POST entity_type+image_id+image → заміна файлу+DB+каскад
```

**Shared Image API** (`/shared/api/upload_image`, `/shared/api/delete_image`, `/shared/api/replace_image`):
- Обробка: resize до 1200×1200px, JPEG 85%, білий фон для прозорих, max 5MB вхід
- Зберігання: `/var/www/menufold/data/www/officetorg.com.ua/image/`
- Шляхи: `catalog/category/`, `catalog/product/{hex}/{hex}/`, `catalog/manufacturer/`
- Іменування: `{entity_type}_{entity_id}_{uniqid()}.jpg`
- Каскад (category): `categoria.image` → `off.oc_category.image` → `mff.oc_category.image`
- Видалення/заміна: автоматично оновлює `categoria.image` на перше фото що залишилось
- Існуючі `/categories/api/upload_image` і `delete_image` делегують до shared з `entity_type=category`

Підключення у кожному новому view:
```php
<?php $title = 'Назва сторінки'; require_once __DIR__ . '/../../shared/layout.php'; ?>
... html ...
<?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>
```

Опціональні змінні для `layout.php`:
- `$title` — заголовок вкладки (без суфіксу «— Papir CRM»)
- `$extraCss` — рядок або масив додаткових `<link>` тегів
- `$bodyClass` — CSS клас для `<body>`

### Стандартні CSS класи (не дублювати в `<style>`)

| Група | Класи |
|-------|-------|
| Обгортка | `.page-wrap-sm`, `.page-wrap`, `.page-wrap-lg` |
| Заголовок | `.page-head`, `.breadcrumb`, `.toolbar` |
| Кнопки | `.btn`, `.btn-primary`, `.btn-danger`, `.btn-ghost`, `.btn-sm`, `.btn-xs`, `.btn-row` |
| Картка | `.card` |
| Таблиця | `.crm-table` |
| Форми | `.form-row` + стандартні `input`/`select`/`textarea` |
| Пошук | `.search-input` |
| Модалка | `.modal-overlay`, `.modal-box`, `.modal-head`, `.modal-body`, `.modal-footer`, `.modal-close`, `.modal-error` |
| Пагінація | `.pagination` |
| Toast | `.toast` + JS `showToast(msg)` |
| Бейджі | `.badge`, `.badge-green`, `.badge-red`, `.badge-blue`, `.badge-orange`, `.badge-gray` |
| Утиліти | `.text-muted`, `.text-green`, `.text-red`, `.fw-600`, `.nowrap`, `.truncate`, `.fs-12` |

Свій `<style>` блок у view — тільки для унікальних елементів сторінки, яких немає в `ui.css`. Перед додаванням нового стилю — перевірити чи немає вже відповідного класу. Якщо патерн зустрівся двічі — додати в `ui.css`.

### Патерн: реєстр + sidebar панель

Стандартний патерн для сторінок з CRUD (виробники і т.д.):
- Ліво: `crm-table` з clickable рядками, `?selected=ID` в URL
- Право: sticky `.card` панель з формою редагування
- Клік по рядку → `window.location = url` з `selected=ID`
- AJAX save → redirect з новим ID; AJAX delete → redirect без `selected`
- Виділений рядок: клас `row-selected` на `<tr>`

**Варіант з деревом (категорії `/categories`):**
- Ліво: `CategoryTree` замість таблиці, `height: calc(100vh - 112px)`
- Право: дві sticky картки (базові поля + SEO)
- Навігація через `history.pushState` + AJAX (зберігає стан дерева без перезавантаження)
- Початкові дані вбудовані в PHP (`INITIAL_DATA`) щоб уникнути зайвого AJAX при першому завантаженні

```php
// CSS для двоколонкового layout:
.layout-2col {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 20px;
    align-items: start;
}
```

### Порядок мовних вкладок

У всіх інтерфейсах CRM першою завжди йде вкладка **UK** (українська, `language_id=2`), потім **RU** (російська, `language_id=1`). При рендері мовних вкладок використовувати порядок `array(2=>'UK', 1=>'RU')`, активною за замовчуванням — `language_id=2`.

---

### Стандарт поиска — Chip Search

**Это стандартный подход поиска во всех табличных интерфейсах CRM.**

#### Концепция

Поле поиска — контейнер с чипами. Каждый чип — одна поисковая единица. Между чипами логика **OR**, внутри чипа (пробелы) — **AND**. Чистое целое число — точное совпадение по ID.

Примеры:
- `638` → `product_id = 638`
- `638, 961, карт пс А4` → `id=638 OR id=961 OR (name LIKE '%карт%' AND name LIKE '%пс%' AND name LIKE '%А4%')`

#### Shared компоненты

| Файл | Назначение |
|------|-----------|
| `modules/shared/ui.css` | CSS классы: `.chip-input`, `.chip`, `.chip-x`, `.chip-typer` |
| `modules/shared/chip-search.js` | JS виджет: `ChipSearch.init(boxId, typerId, hiddenId)` |

#### Разметка (копировать в каждый новый view)

```html
<div>
    <label>Поиск</label>
    <div class="chip-input" id="searchChipBox">
        <input type="text" class="chip-typer" id="searchChipTyper"
               placeholder="ID, артикул или название…" autocomplete="off">
    </div>
    <input type="hidden" name="search" id="search" value="<?php echo ViewHelper::h($search); ?>">
</div>
```

Подключить скрипт (после `category-tree.js` или в конце `<body>`):
```html
<script src="/modules/shared/chip-search.js?v=<?php echo filemtime(...); ?>"></script>
```

Инициализация в конце `<script>` блока страницы:
```javascript
ChipSearch.init('searchChipBox', 'searchChipTyper', 'search');
```

#### PHP buildWhere (шаблон для репозитория)

```php
$search = trim((string)$search);
if ($search !== '') {
    $rawChips = preg_split('/\s*,\s*/u', $search);
    $chipConditions = array();

    foreach ($rawChips as $chip) {
        $chip = trim($chip);
        if ($chip === '') continue;

        // Чистый ID — точное совпадение
        if (preg_match('/^\d+$/', $chip)) {
            $chipConditions[] = "pp.`product_id` = " . (int)$chip;
            continue;
        }

        // Текст — AND по токенам (пробел) по ключевым полям таблицы
        $tokens = preg_split('/\s+/u', mb_strtolower($chip, 'UTF-8'));
        $tokens = array_filter($tokens, function($t) { return $t !== ''; });
        $tokenParts = array();
        foreach ($tokens as $token) {
            $t = Database::escape('Papir', $token);
            $tokenParts[] = "(CAST(pp.`product_id` AS CHAR) LIKE '%{$t}%'
                OR LOWER(COALESCE(pp.`product_article`,'')) LIKE '%{$t}%'
                OR LOWER(COALESCE(NULLIF(pd2.`name`,''),NULLIF(pd1.`name`,''),'')) LIKE '%{$t}%')";
        }
        if (!empty($tokenParts)) {
            $chipConditions[] = '(' . implode(' AND ', $tokenParts) . ')';
        }
    }

    if (!empty($chipConditions)) {
        $searchClause = count($chipConditions) === 1
            ? $chipConditions[0]
            : '(' . implode(' OR ', $chipConditions) . ')';
        $base .= ' AND ' . $searchClause;
    }
}
```

> Адаптировать поля поиска под конкретную таблицу (product_id / article / name и т.д.).

#### Поля поиска по умолчанию (для товаров)

1. `pp.product_id` — внутренний Papir ID
2. `pp.product_article` — артикул
3. `name` (COALESCE из pd2/pd1) — название (UK приоритет)

#### Пошук у JS-списках (dropdown/модалки)

Мультитокенний (AND по словам через пробіл):

```javascript
function matchTokens(name, query) {
    var tokens = query.toLowerCase().split(/\s+/).filter(function(t) { return t.length > 0; });
    var lname  = name.toLowerCase();
    for (var i = 0; i < tokens.length; i++) {
        if (lname.indexOf(tokens[i]) === -1) return false;
    }
    return true;
}
```

---

## Довідники та маппінги

### `manufacturers` — Виробники

```
Papir.manufacturers
├── manufacturer_id  PK
├── name             varchar(128) UNIQUE
├── description      TEXT
├── image            varchar(512)   — відносний шлях або URL
├── off_id           INT → off.oc_manufacturer.manufacturer_id
└── mff_id           INT → mff.oc_manufacturer.manufacturer_id
```

При збереженні виробника — каскад в `off.oc_manufacturer` і `mff.oc_manufacturer` (якщо є mff_id). При зміні прив'язки виробника у товарі — каскад в `off.oc_product.manufacturer_id` і `mff.oc_product.manufacturer_id`.

`off.oc_manufacturer` вимагає поле `uuid varchar(36) NOT NULL` при INSERT — генерується через `sprintf('%04x%04x-...')`.

### `categoria` — Категорії

```
Papir.categoria
├── category_id   PK
├── parent_id     INT → categoria.category_id (дерево Papir, не залежить від off/mff)
├── category_off  INT → off.oc_category.category_id  (денормалізований кеш)
├── category_mf   INT → mff.oc_category.category_id  (денормалізований кеш)
├── image         varchar(256)  — відносний шлях (напр. catalog/category/...)
├── image_cloud_url varchar(256) — legacy, буде видалено
└── status, sort_order

Papir.category_description
├── category_id + language_id  PK
├── language_id=1  Russian (ru)
├── language_id=2  Ukrainian (uk)
└── name, description_full, name_full
    (SEO fields meta_title/meta_description/seo_h1/seo_url перенесено в category_seo)

Papir.languages                          # Довідник мов Papir
├── language_id  PK
├── code         UNIQUE ('ru', 'uk')
├── name
└── sort_order
    (language_id=1 ru/Русский, language_id=2 uk/Українська)

Papir.site_languages                     # Маппінг: Papir lang → site lang_id
├── site_id + language_id  PK
└── site_lang_id            — language_id у зовнішній БД сайту
    (off: ru(1)→1, uk(2)→4; mff: ru(1)→1, uk(2)→2)

Papir.category_seo                       # SEO per site+language
├── seo_id         PK
├── category_id    INT → categoria.category_id
├── site_id        INT → sites.site_id
├── language_id    INT → languages.language_id (Papir IDs: 1=ru, 2=uk)
├── meta_title, meta_description, seo_h1, seo_url
└── UNIQUE (category_id, site_id, language_id)
```

Дерево категорій на різних сайтах може відрізнятися від Papir — це нормально. `category_off` і `category_mf` — просто посилання, не обов'язково відображають ту саму ієрархію.

**language_id в зовнішніх БД** (використовувати `site_languages` замість хардкоду):
- `off` (menufold_offtorg): `language_id=1` RU, `language_id=4` UA
- `mff` (menufold_mff): `language_id=1` RU, `language_id=2` UA

**Ієрархічний cat_url**: будується як домен + `/` + seo_url всіх предків по `parent_id` (через `category_seo` де language_id=2/uk).

### `sites` + `category_site_mapping` — Маппінг категорій

```
Papir.sites
├── site_id     PK
├── code        VARCHAR(32) UNIQUE  — 'off', 'mff', ...
├── badge       VARCHAR(8)          — короткий ярлык для UI: 'off', 'mf'
├── name        VARCHAR(128)        — 'Офіс Торг', 'Menu Fold'
├── url         VARCHAR(256)
├── db_alias    VARCHAR(32)         — аліас для Database::fetchAll()
├── lang_id     TINYINT             — ⚠️ DEPRECATED, не використовувати. Використовувати site_languages для маппінгу мов
└── status, sort_order

Papir.category_site_mapping
├── mapping_id       PK
├── category_id      INT → categoria.category_id   (Papir)
├── site_id          INT → sites.site_id
└── site_category_id INT → oc_category.category_id (на сайті)
    UNIQUE (category_id, site_id)
```

`category_off` і `category_mf` в `categoria` — денормалізований кеш маппінгу для зворотної сумісності. При збереженні через `/catalog/api/save_category_mapping` оновлюються обидва.

Інтерфейс: `/category-mapping` — ліво: категорії сайту, право: вибір Papir-категорії.

---

## Планы

### Технический долг

- **Очистить код от `Papir.sites.lang_id`**: поле устарело, заменено таблицей `site_languages`. Найти все места где читается `sites.lang_id` и переписать на JOIN с `site_languages` для автоматического получения маппинга языков.

- **Автоматические языковые вкладки из `site_languages`**: вместо хардкода языков в UI (сейчас в ряде мест явно указаны RU/UA) — строить вкладки динамически из `site_languages JOIN languages`. Это позволит добавить новый язык без правки кода.
