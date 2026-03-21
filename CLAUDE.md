# Papir CRM — Архитектура и соглашения

## Обзор

Внутренняя CRM система для управления товарами, ценами, заказами и интеграциями с внешними сервисами (МойСклад, PrivatBank, Моно, UKRSIB, Google). Основной модуль — управление ценами с каскадным обновлением на сайты и в ERP.

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
│   ├── PricelistRepository.php
│   └── SupplierPricesRepository.php
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

#### Каскад обновления цен

При изменении цены в прайсе поставщика запускается полный каскад:

1. **Синхронизация RRP** — `MAX(price_rrp)` из всех активных прайсов → `product_papir.price_rrp`
2. **Пересчёт** — `DiscountProfileBuilder::build($productId)` → `PriceEngine::calculate()`
3. **Сохранение** — `product_papir` (все цены) + `product_discount_profile` (скидки по кол-ву) + `action_prices`
4. **Выгрузка на сайты** — `OpenCartPriceExport` → `off` (offtorg) + `mff`
5. **Выгрузка в МойСклад** — `MoySkladPriceExport` → API PUT `/entity/product/{id_ms}`

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
| `off`/`offtorg` | localhost           | menufold_offtorg    | OpenCart — offtorg          |
| `ms`        | localhost               | ms                  | Зеркало МойСклад (stock_)   |
| `mff`       | menufold.mysql.tools    | menufold_mff        | OpenCart — mff (удалённо)   |
| `mf`        | menufold.mysql.tools    | menufold_new        | MenuFold система            |
| `prm`       | menufold.mysql.tools    | menufold_prm        | PRM система                 |
| `trend`     | menufold.mysql.tools    | menufold_trends     | Аналитика/тренды            |

---

### Остальные модули

| Модуль           | Назначение                                         |
|------------------|----------------------------------------------------|
| `action`         | Акционные скидки на товары, публикация в Merchant  |
| `customerorder`  | Управление заказами клиентов                       |
| `payments_sync`  | Сверка банковских платежей с заказами              |
| `bank_monobank`  | API Monobank                                       |
| `bank_privat`    | API PrivatBank (выписки, балансы)                  |
| `bank_ukrsib`    | API UKRSIBBANK (OAuth2 + RSA SHA512)               |
| `merchant`       | Google Shopping (Merchant Center)                  |

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

**Ключевые поля `product_papir`:**
- `id_off` — product_id в OpenCart offtorg
- `id_mf`  — product_id в OpenCart mff
- `id_ms`  — UUID товара в МойСклад
- `id_off` используется как `code` при выгрузке в МойСклад

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

```
МойСклад API (stock report)
    → ms.stock_  (updateStockFromMs)
    → Papir.product_stock  (зеркало)
    → price_supplier_items.stock для "Склад"  (syncWarehouseStock)
    → price_supplier_items.stock для "Производство"  (syncVirtualStock из ms.virtual)
    → product_papir.quantity  (recalcQuantity = product_stock + sum(psi.stock))
```

Функции в `src/lib_stock_update.php`, вызываются из `/prices/api/update_stock`.

---

## Важные особенности и ловушки

1. **`is_cost_source DESC`** в `getBestCostPrice()` — поставщик с `is_cost_source=1` имеет приоритет для закупочной цены (не ASC!).

2. **RRP из МойСклад не импортируется** — `MoySkladPriceSync` устанавливает `price_rrp=null`, потому что `ms.stock_.salePrice` — это наша же розничная цена, цикличная зависимость.

3. **`getEntityBaseUrl()` содержит `/entity/`** — не добавлять `entity/` при построении URL.

4. **`push_prices.php` фазовый**: `phase=sites` (off+mff, быстро) → `phase=ms` (МойСклад, ~15 req/s). При ошибке в фазе — стоп.

5. **PHP-FPM кэширует Router.php** — после изменений маршрутов нужен `systemctl reload php-fpm`.

6. **Цены в МойСклад в копейках** — `(int)round($price * 100)`.
