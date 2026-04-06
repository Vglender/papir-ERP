# Нова Пошта API — нотатки та пастки

## Клієнт (NovaPoshta.php)

```php
// Конструктор: кожен відправник має свій api_key
$np = new NovaPoshta($apiKey);

// Одиночний запит
$result = $np->call($modelName, $calledMethod, $methodProperties);
// → ['ok' => bool, 'data' => array, 'error' => string]

// Пагінований обхід (PageSize=500)
$result = $np->callAllPages($modelName, $calledMethod, $methodProperties);
```

Endpoint: `https://api.novaposhta.ua/v2.0/json/`

---

## CounterpartyGeneral.save — критична особливість

**⚠️ Не використовувати `Counterparty.save` — правильна модель: `CounterpartyGeneral.save`**

Для `PrivatePerson` відповідь виглядає так:

```json
{
  "data": [{
    "Ref": "fc5a82d2-...",
    "Description": "Приватна особа",
    "CounterpartyType": "PrivatePerson",
    "ContactPerson": {
      "data": [{
        "Ref": "ea7b1e1b-...",
        "LastName": "Іванов",
        "FirstName": "Іван"
      }]
    }
  }]
}
```

| Поле | Значення | Використання |
|------|----------|-------------|
| `data[0].Ref` | **Однаковий** для всіх фізосіб цього відправника | `Recipient` в InternetDocument |
| `data[0].ContactPerson.data[0].Ref` | **Унікальний** реф конкретної людини | `ContactRecipient` в InternetDocument |

**В БД `ttn_novaposhta.recipient_np_ref` зберігати `ContactPerson.data[0].Ref` (унікальний).**

**⚠️ Не передавати `CityRef`** в `CounterpartyGeneral.save` для PrivatePerson — не потрібно і відсутнє в документації.

---

## InternetDocument.save / update

```json
{
  "Recipient": "fc5a82d2-...",
  "ContactRecipient": "ea7b1e1b-...",
  "RecipientName": "Іванов Іван Іванович",
  "RecipienPhone": "380XXXXXXXXX"
}
```

---

## Телефон

Завжди `380XXXXXXXXX` (12 цифр). `TtnService::normalizePhone()` повертає цей формат.

`AlphaSmsService::normalizePhone($phone)` → те саме для SMS/Viber.

---

## Фінальні статуси трекінгу

`state_define` IN: **9** (доставлено), **10** (повернено), **106** (скасовано).  
Після досягнення — більше не відстежувати.

---

## Довідники (local-first)

Таблиці: `np_warehouses`, `areas_np`, `street_np`.  
Алгоритм: шукаємо локально → якщо порожньо → запит до НП API → upsert → повертаємо.

Ендпоінти: `search_city.php`, `search_warehouse.php`, `search_street.php`.

---

## Прив'язка ТТН до замовлення

Через `document_link`: `from_type='ttn_np'`, `to_type='customerorder'`, `link_type='shipment'`.

---

## Префіл форми ТТН

Парсимо номер замовлення (напр. `"98267OFF"`) → `oc_order.order_id=98267` (site=off) → беремо `telephone`, `shipping_firstname`, `shipping_lastname` з `oc_order`.

---

## np_sender — поля

| Поле | Тип | Призначення |
|------|-----|------------|
| `Ref` | UUID | NP UUID відправника |
| `api_key` | varchar | Ключ API цього відправника |
| `organization_id` | int | → organization.id |
| `is_default` | tinyint | Відправник за замовч. |
| `use_payment_control` | tinyint | 0=готівка/Money, 1=NovaPay/AfterpaymentOnGoodsCost |
| `default_description` | varchar | Опис відправлення за замовч. |

---

## Реєстри (ScanSheet)

- `addDocuments`: POST `sender_ref` + `ttn_refs[]`
- `syncList`: POST `sender_ref` → оновлює локальну БД з НП
- `delete`: POST `sender_ref` + `scan_sheet_ref`
- Статуси: `open` / `closed`

---

## Типові помилки

| Помилка | Причина |
|---------|---------|
| "Recipient not found" | Передано `data[0].Ref` як `ContactRecipient` замість `ContactPerson.data[0].Ref` |
| "CityRef is required" | Передано зайвий `CityRef` в `CounterpartyGeneral.save` |
| Дублювання контрагентів | Використано `Counterparty.save` замість `CounterpartyGeneral.save` |
| Телефон відхилено | Формат не `380XXXXXXXXX` |
