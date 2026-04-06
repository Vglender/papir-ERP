# Чеклист: новий інтерфейс редагування документа

Детальний паттерн: `docs/document_edit_pattern.md`  
Еталонна реалізація: `modules/counterparties/views/workspace.php` + `api/save_order.php`

---

## JS — ініціалізація

- [ ] `_state` ініціалізовано при завантаженні (`JSON.parse(JSON.stringify(data))`)
- [ ] `_original` — окрема глибока копія `_state`
- [ ] `_localId = String(item.id)` для кожного елементу (завжди рядок)

## JS — режим редагування

- [ ] `enterEditMode` / `exitEditMode` через **один** CSS-клас на контейнері
- [ ] Поля `<input>` / `<select>` в таблиці заблоковані поза режимом (`pointer-events: none`)
- [ ] Dirty-флаг на кнопці 💾 при будь-якому `input`
- [ ] Пошук товару вимкнений поза режимом редагування

## JS — рядки таблиці

- [ ] Кожен `<tr>` має `data-local-id` атрибут
- [ ] `syncRowToState` прив'язаний до `input` кожного поля
- [ ] `calcItem` викликається в `syncRowToState`
- [ ] Видалення: `item._deleted = true` + `tr.remove()`, **не** splice з масиву
- [ ] Нова строка: `_localId = 'n' + Date.now()`
- [ ] Додавання через `mousedown` (не `click`) в dropdown щоб не втратити blur

## JS — збереження

- [ ] POST передає повний `_state.items` (включно з `_deleted`)
- [ ] POST передає `version` з `_state.doc.version`
- [ ] Після успіху: `_state` і `_original` оновлюються з відповіді сервера (не з локальних даних)
- [ ] При конфлікті: `confirm` → `loadData()`
- [ ] `exitEditMode()` + `renderForm()` після успішного збереження

## JS — скасування

- [ ] `_cancelEdit`: `_state = JSON.parse(JSON.stringify(_original))`
- [ ] `renderForm()` після скасування

## JS — перемальовка форми в режимі редагування

- [ ] `_restoreEditMode` зберігається перед `renderForm`
- [ ] В кінці `bindForm`: якщо `_restoreEditMode` → `enterEditMode()` + scroll

## PHP (сервер)

- [ ] Перевірка `version`: якщо не збігається → `{'ok':false,'conflict':true}`
- [ ] Вся робота з БД у транзакції (`begin` / `commit` / `rollback`)
- [ ] `_deleted=true && id>0` → DELETE
- [ ] `_localId` починається з `'n'` → INSERT (ігнорувати id)
- [ ] Інакше → UPDATE
- [ ] `version++` в тій самій транзакції
- [ ] Відповідь містить СВІЖІ дані з БД (не echo клієнтський payload)
- [ ] `customerorder_history` при зміні статусу: `is_auto=0` (ручне), `is_auto=1` (авто)

## Загальне

- [ ] Формат відповіді: `{'ok':true,'doc':...,'items':[...]}` / `{'ok':false,'error':'...'}`
- [ ] `showToast('Збережено ✓')` після успіху
- [ ] `showToast('Помилка: ...', true)` при помилці
