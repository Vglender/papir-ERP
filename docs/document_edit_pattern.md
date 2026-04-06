# Паттерн редактирования документов (Optimistic Locking)

Эталон: `modules/counterparties/views/workspace.php` + `api/save_order.php`

---

## Концепция

```
loadData() → _state + _original (глубокие копии)
    ↓
enterEditMode() → CSS-класс на контейнере
    ↓
пользователь меняет → только _state, DOM отражает
    ↓
_save() → POST {state + version} → сервер проверяет version
    ↓
ok → обновить _state/_original из ответа сервера, re-render
conflict → confirm → reload с сервера
```

---

## JS — инициализация состояния

```javascript
var stateItems = (data.items || []).map(function(it) {
    var copy = JSON.parse(JSON.stringify(it));
    copy._localId = String(it.id);  // всегда строка!
    return copy;
});
this._state    = { doc: JSON.parse(JSON.stringify(data.doc)), items: stateItems };
this._original = JSON.parse(JSON.stringify(this._state));
```

**_localId:** `"123"` — существующая строка (id в БД), `"n" + Date.now()` — новая. Сравнивать через `String(a) === String(b)`.

---

## Режим редактирования (CSS-паттерн)

```javascript
function enterEditMode() {
    container.classList.add('doc-editing');
    editBtn.style.display = 'none';
    saveBtn.style.display = '';
    searchInput.disabled  = false;
}
function exitEditMode() {
    container.classList.remove('doc-editing');
    editBtn.style.display = '';
    saveBtn.style.display = 'none';
    saveBtn.classList.remove('doc-save-dirty');
    searchInput.disabled = true; searchInput.value = '';
}
```

```css
.doc-table input, .doc-table select   { pointer-events: none; background: transparent; border-color: transparent; }
.doc-editing .doc-table input,
.doc-editing .doc-table select        { pointer-events: auto; background: #fff; border-color: #d1d5db; }
.doc-edit-bar                         { display: none; }
.doc-editing .doc-edit-bar            { display: flex; }
.doc-editing .doc-head                { background: #fefce8; border-bottom-color: #fde68a; }
```

Dirty-флаг: `saveBtn.classList.add('doc-save-dirty')` при любом `input` в таблице.

---

## Синхронизация DOM → _state

```javascript
function syncRowToState(tr) {
    var item = _state.items.find(function(x) {
        return String(x._localId) === tr.dataset.localId;
    });
    if (!item) return;
    item.quantity         = parseFloat(tr.querySelector('[data-field="quantity"]').value)  || 0;
    item.price            = parseFloat(tr.querySelector('[data-field="price"]').value)     || 0;
    item.discount_percent = parseFloat(tr.querySelector('[data-field="discount_percent"]').value) || 0;
    calcItem(item);
    renderRowTotals(tr, item);
    renderDocTotals();
}
tr.querySelectorAll('.cell-input').forEach(function(inp) {
    inp.addEventListener('input', function() {
        saveBtn.classList.add('doc-save-dirty');
        syncRowToState(tr);
    });
});
```

---

## Расчёт производных полей

```javascript
function calcItem(item) {
    var gross   = Math.round(item.quantity * item.price * 100) / 100;
    var discAmt = Math.round(gross * item.discount_percent / 100 * 100) / 100;
    item.sum_row = Math.round((gross - discAmt) * 100) / 100;
    item.discount_amount = discAmt;
    item.vat_amount = item.vat_rate > 0
        ? Math.round((item.sum_row - item.sum_row / (1 + item.vat_rate / 100)) * 100) / 100 : 0;
    item.sum_without_discount = Math.round((item.sum_row - item.vat_amount) * 100) / 100;
}
```

**Двунаправленный ввод (sum_row ↔ price):**
```javascript
sumInp.addEventListener('input', function() { tr.dataset.sumChanged = '1'; });
priceInp.addEventListener('input', function() { tr.dataset.sumChanged = '0'; });
// в syncRowToState:
if (tr.dataset.sumChanged === '1') {
    item.price = item.quantity ? item.sum_row / item.quantity / (1 - item.discount_percent / 100) : 0;
}
```

---

## Добавление строки (поиск товара)

```javascript
// Debounce 250ms → fetch → dropdown
// mousedown (не click, чтоб не потерять blur у поля)

function pickProduct(p) {
    var localId = 'n' + Date.now();
    var newItem = {
        _localId: localId,
        product_id: p.product_id,
        quantity: 1,
        price: p.price_sale || 0,
        discount_percent: 0,
        vat_rate: 0
    };
    calcItem(newItem);
    _state.items.push(newItem);
    var tr = renderItemRow(newItem);
    tbody.appendChild(tr);
    bindRow(tr);
    tr.querySelector('[data-field="quantity"]').focus();
}

// Enter → выбрать первый вариант из dropdown
if (e.key === 'Enter') {
    var first = dd && dd.querySelector('.search-opt');
    if (first) first.dispatchEvent(new MouseEvent('mousedown'));
}
```

---

## Удаление строки

```javascript
// НЕ splice — помечать флагом
function deleteRow(tr) {
    var item = _state.items.find(function(x) { return String(x._localId) === tr.dataset.localId; });
    if (item) item._deleted = true;
    tr.remove();
    saveBtn.classList.add('doc-save-dirty');
    renderDocTotals();
}
```

---

## Сохранение (_save)

```javascript
function _save() {
    var payload = {
        doc_id:  _state.doc.id,
        version: _state.doc.version,
        items:   JSON.stringify(_state.items)
        // + другие поля заголовка из _state.doc
    };

    fetch('/module/api/save_doc', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: Object.keys(payload).map(function(k) {
            return k + '=' + encodeURIComponent(payload[k]);
        }).join('&')
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.conflict) {
            if (confirm('Документ змінено іншим користувачем. Оновити?')) loadData();
            return;
        }
        if (!res.ok) { showToast('Помилка: ' + (res.error || ''), true); return; }

        // Обновить state из ответа сервера (не из локальных данных!)
        _state = {
            doc: res.doc,
            items: res.items.map(function(it) {
                var c = JSON.parse(JSON.stringify(it));
                c._localId = String(it.id);
                return c;
            })
        };
        _original = JSON.parse(JSON.stringify(_state));
        exitEditMode();
        renderForm();
        showToast('Збережено ✓');
    });
}
```

---

## Отмена (_cancelEdit)

```javascript
function _cancelEdit() {
    _state = JSON.parse(JSON.stringify(_original));
    exitEditMode();
    renderForm();
    showToast('Зміни скасовано');
}
```

---

## Восстановление режима редактирования после renderForm

```javascript
// Перед renderForm:
this._restoreEditMode = container.classList.contains('doc-editing');

// В конце bindForm/renderForm:
if (this._restoreEditMode) {
    this._restoreEditMode = false;
    enterEditMode();
    lastRow && lastRow.scrollIntoView({block: 'nearest'});
}
```

---

## Серверная сторона (PHP)

```php
// 1. Проверка версии
$r = Database::fetchRow('Papir', "SELECT version FROM my_doc WHERE id={$docId}");
if ((int)$version > 0 && (int)$r['row']['version'] !== (int)$version) {
    echo json_encode(array('ok'=>false, 'conflict'=>true)); exit;
}

// 2. Транзакция
Database::begin('Papir');
try {
    // Обновить заголовок
    Database::update('Papir', 'my_doc',
        array('field' => $value, 'version' => (int)$version + 1),
        array('id' => $docId)
    );

    // Обработать строки
    foreach ($items as $item) {
        if (!empty($item['_deleted'])) {
            if (!empty($item['id'])) {
                Database::query('Papir', "DELETE FROM my_doc_items WHERE id=" . (int)$item['id']);
            }
            continue;
        }
        if (!empty($item['id'])) {
            Database::update('Papir', 'my_doc_items', $rowData, array('id' => (int)$item['id']));
        } else {
            Database::insert('Papir', 'my_doc_items', $rowData);
        }
    }

    Database::commit('Papir');

    // 3. Вернуть свежие данные (не echo клиентский payload!)
    $freshDoc   = /* SELECT ... */;
    $freshItems = /* SELECT ... */;
    echo json_encode(array('ok' => true, 'doc' => $freshDoc, 'items' => $freshItems));

} catch (Exception $e) {
    Database::rollback('Papir');
    echo json_encode(array('ok' => false, 'error' => $e->getMessage()));
}
```

**Правила сервера:**
- Проверять version → транзакция → возвращать СВЕЖИЕ данные из БД
- version++ в той же транзакции
- Строки: `_deleted=true && id>0` → DELETE; `_localId` начинается с `'n'` → INSERT; иначе → UPDATE
