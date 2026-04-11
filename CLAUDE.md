# Papir ERP — Project Instructions

## ⚡ Local IDs are the source of truth

Papir — джерело правди. Локальні int FK (`counterparty_id`, `organization_id`,
`customerorder_id` тощо) — головні поля у всіх таблицях. Поля `id_ms`/`agent_ms`/
`organization_ms` та схожі — суто **маппінг** для синку зі застарілою МойСклад.
Коли МС зникне, нічого в Papir не повинно зламатися.

**Правила для будь-яких нових таблиць/колонок:**
- FK завжди як локальний int (`counterparty_id`, не `agent_ms`).
- UUID-маппінг — окрема nullable-колонка (`id_ms`), не FK.
- JOIN-и в репозиторіях завжди по локальному FK, не по `id_ms`.
- При імпорті з МС: резолв `agent.href` UUID → локальний `counterparty.id`,
  створювати локального cp **на льоту** через `mswhk_cp_resolve()` якщо нема (B1).
- При пуші в МС: читати локальний `counterparty.id_ms` як маппінг; якщо `id_ms`
  ще NULL — push скіпається (не блокуючий, локальна операція вже коммічена).

Приклад розгортання у `finance_cash` (міграція 023): додано `counterparty_id` /
`organization_id` як основні FK; `agent_ms`/`organization_ms` лишилися як
маппінг-поля для МС. Та сама модель уже працювала в `finance_bank` (`cp_id`).

## Architecture: Integration Module System

### Core Principle
Every external service (Nova Poshta, MoySklad, Telegram, banks, etc.) connects to Papir through the **Integrations Hub** (`/modules/integrations/`). This is the single source of truth for:
- API keys and credentials (stored in `integration_settings` / `integration_connections` DB tables)
- Active/inactive state per app
- Exchange rules (CRUD-level control for bidirectional syncs)
- Default settings per app

### App Manifest Pattern
Each integration module declares everything it brings into the system via `app.manifest.php`:

```php
// /modules/{module}/app.manifest.php
return [
    'key'      => 'module_key',
    'routes'   => [...],        // registered only if app is active
    'nav'      => [...],        // menu items, shown only if active
    'cron'     => [...],        // cron jobs, skip if inactive
    'webhooks' => [...],        // incoming webhooks with guard
    'tables'   => [...],        // DB tables owned by this app
];
```

**AppRegistry** (`/modules/integrations/AppRegistry.php`) discovers manifests, checks `is_active`, and provides routes/nav/cron for active apps only.

### How to Add a New Integration
1. Create the module in `/modules/{name}/`
2. Add `app.manifest.php` declaring routes, nav, cron, webhooks, tables
3. Add entry to `/modules/integrations/registry.php` (icon, category, settings schema)
4. Seed `is_active` in `integration_settings`
5. If the app has API keys — store in `integration_settings` (single) or `integration_connections` (multiple accounts)
6. Add guards: `AppRegistry::guard('app_key')` at top of webhooks/crons
7. For CRUD-level control: use `MsExchangeGuard` pattern (per-operation toggles)

### Key Rules
- **Never hardcode API keys** in service classes. Read from `IntegrationSettingsService`.
- **Never hardcode nav items** for manifest-managed modules. They come from `AppRegistry::getNavItems()`.
- **Webhook handlers must always respond 200 OK** even when inactive (to prevent external service retries). Use `AppRegistry::guard()`.
- **Cron scripts for integrations** must check `AppRegistry::guard()` or `AppRegistry::isActive()` before doing work.
- **API base URLs** that never change (e.g. `api.moysklad.ru/api/remap/1.2/`) should be hardcoded, not stored in settings.
- **Never hardcode organization_id in order importers.** Use `OrderOrgResolver::resolve($cpId, $edrpou, $description)` — він читає дефолти з `integration_settings` (`order_import.default_org_vat` / `default_org_novat`) та обирає за VAT-статусом контрагента. Правила: юрособа/EDRPOU=8 → VAT; ФОП/ІПН=10 → noVAT; fallback парсинг опису на маркер `ЄДРПОУ|ЭДРПОУ|ЕДРПОУ` + 8 цифр.

### Database Tables
- `integration_settings` — key-value settings per app (credentials, defaults, CRUD toggles, is_active)
- `integration_connections` — API keys/accounts with metadata (multiple per app, e.g. NP has 4 senders)

## Робочий календар (`modules/calendar/`)
- `calendar_days(date, kind, title, country)` — зберігає ТІЛЬКИ винятки (свята + перенесені дні). Дефолт: Пн-Пт=робочий, Сб-Нд=вихідний. Свята України 2026-2028 засіяно в міграції `001_calendar_days.sql`.
- API: `Calendar::isBusinessDay($date)`, `::nextBusinessDay($from, $includeToday=false)`, `::addBusinessDays($from, $n)`, `::formatNextBusinessDay($dateTime, $cutoffHour=15)`.
- **Плейсхолдер у повідомленнях**: `{{calendar.next_business_day}}` — резолвиться в `TaskQueueRunner::resolveVars` з поточного моменту виконання кроку. Повертає фрази: "сьогодні до кінця дня" / "завтра 15 квітня" / "у понеділок 14 квітня". Cutoff 15:00 (після цього часу перекидає на наступний день).
- Використовувати в шаблонах сценаріїв там, де потрібно обіцяти клієнту дедлайн з урахуванням вихідних (наприклад, "передамо ТТН в НП {{calendar.next_business_day}}").

## ТТН-пайплайн: 3 точки істини
- **TTN створено** (`order_ttn_created`) → статус `in_progress`, `next_action='hand_over'` "Передати кур'єру". Повідомлення клієнту (scen#14): "Сформували відправку, передамо в НП {{calendar.next_business_day}}". **Не казати "відправлено"** — посилка ще фізично в офісі.
- **ТТН передано кур'єру** (`ttn_handed_to_courier`) → тільки знімаємо next_action (scen#15). Клієнта НЕ повідомляємо — другий пуш за добу зайвий. Fire-точки: `CourierCallService::processScan` (сканування штрих-коду на реєстр) + `ScanSheetService::addDocuments` (ручне додавання). Helper: `TtnService::fireTtnHandedToCourier($ttnId, $orderId=0)`.
- **НП сканувала на терміналі** (`ttn_status_changed`, state_define in 4,5,6) → статус `shipped` (scen#6). Повідомлення клієнту: "вирушило з міста відправлення". **Це єдина точка, де можна казати "відправлено"** — реальна зміна стану від НП, 100% посилка в дорозі.

### File Structure
```
/modules/integrations/
├── AppRegistry.php              — manifest discovery + active state
├── IntegrationSettingsService.php — DB read/write for settings
├── MsExchangeGuard.php          — CRUD-level guard for MoySklad
├── registry.php                  — catalog of all apps (metadata, icons, settings schema)
├── index.php / app.php           — UI entry points
├── views/
│   ├── catalog.php               — tile catalog of all apps
│   ├── app_settings.php          — universal settings page
│   ├── novaposhta_settings.php   — custom NP settings
│   ├── ukrposhta_settings.php    — custom UP settings (connections + defaults)
│   └── moysklad_settings.php     — custom MS settings with CRUD matrix
├── api/                          — save_settings, save_connection, toggle_app, toggle_ms_webhook
└── migrations/
```

### Ukrposhta module (`/modules/ukrposhta/`)
Повноцінний manifest-модуль, паритетний з NovaPoshta.

**Реальні таблиці (НЕ перевпорядковувати):**
- `ttn_ukrposhta` — 13k+ ТТН, **camelCase схема** (senderAddressId, deliveryType, lifecycle_status, recipient_phoneNumber, тощо). Прибита в `customerorder_repository`, `save_ttn_manual`, `TriggerEngine`, `PackGenerator`, `document_link from_type='ttn_up'`. **НЕ створювати дублікати зі snake_case.**
- `shipment_groups` — реєстри, uuid=PK (не int), type=EXPRESS|STANDARD, closed/printed/byCourier.
- `shipment_group_links` — ТТН↔реєстр (group_uuid + shipment_uuid UNIQUE).
- `sender_ukr` — довідник відправників УП (всі записи — варіанти Гльондер ФОП).

**Токени в integration_settings** (single account):
`ecom_token`, `user_token`, `tracking_token`. Дефолти для ТТН (тип, вага, розміри, опис, senderAddressId, returnAddressId) — теж з префіксом `default_*` в integration_settings. Читаються через `UpDefaults::*`.

**Класи:**
- `UkrposhtaApi::getDefault()` — високорівневі методи (createAddress/Client/Shipment, addShipmentToGroup, trackBatch тощо).
- `services/TtnService` (create/update/delete/refresh), `TrackingService` (event-code → lifecycle, fires `ttn_status_changed`), `GroupService` (create/add/remove/close/delete/syncFromApi/downloadForm103a/addToOrCreate).
- `repositories/` UpTtnRepository, UpGroupRepository, UpGroupLinkRepository, UpSenderRepository, UpClassifierRepository.

**UI:** `index.php` (TTN list NP-style з chip search, lifecycle-фільтр, draft toggle, registry filter), `groups.php` (реєстри з expand-row з ТТН, sync, друк Form-103a). Модал `/modules/shared/up-ttn-create-modal.js`.

**Сканування:** barcode `05…` у `/novaposhta/api/scan_for_registry` → `GroupService::addToOrCreate()` + `TriggerEngine::fire('ttn_handed_to_courier')`.

**API routes** (через manifest): `get_ttn_form`, `create_ttn`, `update_ttn`, `delete_ttn`, `refresh_ttn`, `track_ttn`, `print_sticker`, `create_group`, `add_to_group`, `remove_from_group`, `close_group`, `reopen_group`, `delete_group`, `get_group_shipments`, `sync_groups`, `print_registry`.

**Cron:** `cron/refresh_tracking.php` (`*/30`), `AppRegistry::guard('ukrposhta')`. Трекає тільки активну фазу: DELIVERING/IN_DEPARTMENT/STORAGE/FORWARDING/RETURNING/UNKNOWN (≤90 днів), REGISTERED (≤35 днів), CREATED лише в реєстрі (≤35 днів). **Ніколи не трекати CREATED без реєстру** — UP повертає 404 на весь батч. На 404 батча — fallback per-barcode + bump lastModified (не UNKNOWN, не каскад).

**Канонічні статуси Папір (`ShipmentStatus`, `/modules/shared/ShipmentStatus.php`):** єдиний словник доставки для всіх перевізників: `draft / in_transit / at_branch / received / returning / returned / cancelled`. UP `lifecycle_status` і NP `state_define` мапляться на ці 7 значень через `ShipmentStatus::fromUp()` / `fromNp()`. UI показує канонічний badge основним, карєр-специфіку (STORAGE, FORWARDING, eventName) — як subtitle/tooltip. Сценарії і тригери через TriggerEngine отримують однакові ключі від обох перевізників.

**UP event-мапа** (`TrackingService::getLifecycleFromEvent`) покриває 1xxxx (прийом) → REGISTERED/CANCELLED, 2xxxx (сортування/склад) → DELIVERING/STORAGE/IN_DEPARTMENT, 3xxxx (транзит/переадресація) → DELIVERING/RETURNING/FORWARDING, 4xxxx → DELIVERED/RETURNED. Невідомий код → 'UNKNOWN' (не null — щоб applyStatus не перетирав робочий стан, і рядок підхопився наступним проходом). Фінальні стани: DELIVERED/RETURNED/STORAGE/CANCELLED/DELETED.

## LiqPay module (`/modules/liqpay/`)
- **Принцип**: сценарії стріляють одразу по receipt'у LiqPay (webhook або backfill), не чекаючи приходу грошей на банк. LiqPay-підтвердження = order paid.
- **`LiqpayCallbackService::processPaymentData($lp, $fireScenarios=true)`** — upsert у `order_payment_receipt` + якщо success, `markOrderPaid()` ставить `payment_status='paid'`, `sum_paid=gross`, fire `order_payment_changed`.
- **`OrderFinanceHelper::resolvePaymentStatus($sumPaid, $sumTotal, $orderId)`** — LiqPay-aware. 3%-толерантність залишається як safety net; після Фази 2 sum_paid=gross і вона рідко задіяна.
- **`TriggerEngine` `order.is_paid`** — фолбеки: document_link>0 / `payment_status='paid'` / успішний LiqPay receipt.
- **Backfill cron** (`modules/liqpay/cron/backfill_transactions.php`): `--days=N`, дефолт 2. Завжди `fireScenarios=false`.
- **Webhook URL**: `/liqpay/webhook/callback` (один на всі merchant'и, ідентифікація по `public_key`).
- **Ключі** — в `integration_connections` (app_key='liqpay'), `api_key`=private. Не в коді/git.

### Фаза 2 — bank gross+commission split (2026-04-11, Папір-сторона)
- **`services/BankLiqpaySplitter.php`** — `splitRows(array)` вставляється у `PaymentsSyncService::sync()` одразу після `collector->collect()`, ДО dup-check. Для кожного рядка з `/LIQPAY ID (\d+)/` lookup у `order_payment_receipt` → розщеплює на gross (in, sum=receipt.amount) + commission (out, sum=gross-net) з description `Комiсiя LiqPay ID N`. Remaining fields підставляє matcher через існуюче правило `payment_match_rules.php` 'Комiсiя' (bank_fee_agent_id + bank_fee_expense_item_id).
- **Ідемпотентно** через PaymentDuplicateChecker: gross — оригінальний id_paid, commission — суфікс `_liqpay_fee`.
- **Fallback**: якщо receipt нема → row як є, старий matcher `/LiqPay/` у searchOrder.
- **Guard**: `AppRegistry::isActive('liqpay')` — splitter no-op при вимкненому модулі.
- **Backfill квітня 2026**: `cron/phase2_backfill_april.php` `--dry-run|--from|--to|--limit`. Оновлює Папір-сторону (finance_bank.sum, document_link.linked_sum, вставляє commission row, recalc замовлень). **MS-сторона не оновлюється** — 42 з 48 квітневих paymentin уже `is_posted=1`, потребують un-post→PUT→post окремим кроком.

## Code Conventions
- PHP 5.6 compatible (no `??`, no `[]=` short syntax in some contexts)
- `Database::` static methods for all DB access
- Module pattern: `index.php` sets `$title`, `$activeNav`, `$subNav`, requires `layout.php` → `views/` → `layout_end.php`
- API endpoints return JSON with `{ok: true/false, ...}`