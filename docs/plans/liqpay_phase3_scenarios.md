# LiqPay Фаза 3 — Сценарії онлайн-платежів

**Контекст:** Фаза 1 (trigger-based order paid) і Фаза 2 (bank gross+commission split) — готові станом на 2026-04-11. `order_payment_changed` стріляє одразу по webhook, `order.is_paid` 100% reliable, sum_paid=gross на April 2026 (Папір-сторона). Фаза 3 — це вже не фінансова логіка, а UX: як сценарії використовують онлайн-платежі.

**Принцип, узгоджений з користувачем:** LiqPay-підтвердження достатнє щоб вважати order paid. Не дублювати логіку: `markOrderPaid` → `order_payment_changed` → сценарій реагує. Більш нічого не треба вимірювати/очікувати.

---

## 1. Template-vars у повідомленнях

Додати в `TaskQueueRunner::resolveVars` (`modules/counterparties/services/TaskQueueRunner.php`) — аналогічно до `{{calendar.next_business_day}}`.

### Нові плейсхолдери

| Плейсхолдер | Джерело | Приклад | Fallback |
|---|---|---|---|
| `{{order.payment.status}}` | `customerorder.payment_status` | `paid` / `partially_paid` / `not_paid` | `—` |
| `{{order.payment.gross}}` | `order_payment_receipt.amount` (liqpay, success, latest) | `173.00` | `customerorder.sum_paid` |
| `{{order.payment.method}}` | `order_payment_receipt.paytype` → людська назва | `карткою`, `Google Pay`, `Приват24`, `Apple Pay` | `онлайн` |
| `{{order.payment.date}}` | `order_payment_receipt.created_at` → formatDate | `10 квітня 2026` | `—` |
| `{{order.payment.liqpay_id}}` | `order_payment_receipt.payment_id` | `2834094663` | `—` |

### Маппінг paytype → людська назва

```php
$PAYTYPE_LABELS = [
    'card'       => 'карткою',
    'googlepay'  => 'Google Pay',
    'gpay'       => 'Google Pay',
    'applepay'   => 'Apple Pay',
    'privat24'   => 'Приват24',
    'masterpass' => 'Masterpass',
    'invoice'    => 'інвойсом',
    'qr'         => 'QR-кодом',
];
```

### Файли

- `modules/counterparties/services/TaskQueueRunner.php` — додати resolver у `resolveVars()`.
- `modules/counterparties/services/PaymentInfoResolver.php` (новий, опціонально) — один метод `getInfoForOrder($orderId)`, повертає масив `[status, gross, method, date, liqpay_id]`.

### Тест

Unit-подібний скрипт: fake order з receipt → resolveVars → перевірка рядка.

---

## 2. Сценарій «Оплата отримана — подякувати»

**Тригер:** `order_payment_changed` + `new_payment_status='paid'` + `source='liqpay'` (опціонально).

**Дія:**
1. Надіслати клієнту повідомлення через telegram/viber/email:
   > Дякуємо! Отримали вашу оплату {{order.payment.gross}} грн {{order.payment.method}}. Починаємо формувати замовлення №{{order.number}}. Передамо в НП {{calendar.next_business_day}}.
2. Створити внутрішню задачу «Зібрати замовлення» (якщо ще немає next_action).

**Крайовий випадок:** якщо сценарій «order_created» (scen#13) вже надіслав подібне повідомлення 5 хвилин тому — skip. Dedup через `scenario_execution_log` по `(order_id, scenario_id, within=10min)`.

**Файли:**
- Нова row у `scenario` таблиці (через admin UI або міграцію `022_liqpay_scenarios.sql`).
- Тригер/дії — декларативно, без коду.

---

## 3. Сценарій «Failed LiqPay — повторити»

**Тригер:** `order_payment_changed` або окремий `liqpay_receipt_failed` подія (треба додати fire у `LiqpayCallbackService::processPaymentData` коли status IN `failure|error|reversed|3ds_verify`).

**Дія:**
1. Повідомлення клієнту: «Оплата не пройшла. Надсилаємо нове посилання: {{liqpay.checkout_url}}».
2. Згенерувати нове checkout-посилання через `LiqpayClient::generateCheckoutLink($amount, $orderId)`.
3. Створити внутрішню задачу менеджеру: «Перевірити чому не проходить оплата» через 2 години якщо клієнт не оплатив.

**Нові компоненти:**
- У `LiqpayCallbackService::processPaymentData` — після upsert receipt якщо `status in [failure,error,reversed]` → fire `liqpay_receipt_failed` з контекстом `[order_id, payment_id, reason, paytype]`.
- У `LiqpayClient` — новий метод `generateCheckoutLink($amount, $description, $orderId, $resultUrl, $serverUrl)` — повертає URL виду `https://www.liqpay.ua/api/3/checkout?data=...&signature=...`.
- Нова дія у `TaskQueueRunner`: `send_liqpay_link` — викликає `LiqpayClient::generateCheckoutLink` і підставляє у повідомлення.
- Новий плейсхолдер `{{liqpay.checkout_url}}` — резолвиться action'ом безпосередньо, не через resolveVars.

**Файли:**
- `modules/liqpay/LiqpayClient.php` — +`generateCheckoutLink()`.
- `modules/liqpay/services/LiqpayCallbackService.php` — fire failed event.
- `modules/counterparties/services/TaskQueueRunner.php` — action `send_liqpay_link`.
- `modules/counterparties/services/TriggerEngine.php` — регістрація тригера `liqpay_receipt_failed`.
- Сценарій у БД.

---

## 4. Сценарій «Оплата не прийшла за 30 хв» (не критично)

**Тригер:** `order_created` з `source='online'` + тайм-аут 30 хв + `order.is_paid=0`.

**Дія:** нагадати клієнту → ще 30 хв → менеджеру задача «клієнт не оплатив».

Не обов'язково в першій ітерації — можна потім.

---

## 5. Чеклист Фази 3

- [ ] **Part A: Template vars**
  - [ ] Додати `PaymentInfoResolver` (опціонально) або inline у `TaskQueueRunner::resolveVars`
  - [ ] `{{order.payment.status|gross|method|date|liqpay_id}}`
  - [ ] Unit-тест
- [ ] **Part B: «Оплата отримана»**
  - [ ] Створити сценарій (через admin UI або міграцію)
  - [ ] Dedup з scen#13
  - [ ] Live-тест на тестовому LiqPay-мерчанті
- [ ] **Part C: «Failed → retry»**
  - [ ] `LiqpayClient::generateCheckoutLink()`
  - [ ] Fire `liqpay_receipt_failed` у callback service
  - [ ] Action `send_liqpay_link` у TaskQueueRunner
  - [ ] Сценарій
  - [ ] Тест
- [ ] **Part D: Docs**
  - [ ] Оновити `CLAUDE.md` секцію LiqPay
  - [ ] Оновити `project_liqpay_module.md` memory

---

## 6. Ризики/нюанси

1. **Dedup між scen#13 (order_created) і новим scen (order_payment_changed)**. Якщо замовлення одразу оплачене через LiqPay, обидва сценарії стріляють у коротку проміжок часу. Треба або (a) один сценарій з двома умовами, або (b) dedup у `scenario_execution_log`.

2. **Paytype mapping**: LiqPay документує ~10 paytype значень, треба зафіксувати повний список (https://www.liqpay.ua/documentation/api/information/parameters). Невідомі → fallback "онлайн".

3. **Повторний webhook від LiqPay**: `processPaymentData` ідемпотентно обробляє upsert, але `markOrderPaid` вже має guard `if ($oldStatus === 'paid') return false`. Для failed→paid транзакцій — треба перевірити що `order_payment_changed` fire'иться правильно.

4. **Тестовий мерчант**: LiqPay має sandbox (`https://www.liqpay.ua/sandbox`). Треба окремий public_key у `integration_connections` з прапорцем `sandbox=true` або окремий site. Не змішувати з продом.

5. **Checkout URL expiry**: LiqPay checkout посилання валідне 1 день за замовчуванням. Якщо клієнт не оплатив — наступне нагадування має регенерувати.

---

## 7. Порядок робіт (пропозиція)

Наступна сесія:
1. Part A (template vars) — найпростіше, immediate value.
2. Part B (сценарій «Оплата отримана») — перевіряє що template vars + dedup працюють.
3. Part C (failed + retry) — найскладніше, окремим підходом.
4. Part D (docs) — в кінці.

Part A+B за 1-2 години роботи. Part C — 2-3 години. Загалом Фаза 3 ≈ півдня.