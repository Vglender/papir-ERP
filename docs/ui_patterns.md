# UI Patterns — Papir CRM

## Структура над таблицей (три зоны)

```
┌──────────────────────────────────────────────────────────────┐
│ Тулбар  │ Заголовок │ + Додати │ ── Chip Search ── │ Дії ▾ │✕│
├──────────────────────────────────────────────────────────────┤
│ Фільтри │ [Магазин: ☐ off  ☐ mff]  │  [Статус: ☐ ...]   │⚙│
├──────────────────────────────────────────────────────────────┤
│                         Таблиця                              │
└──────────────────────────────────────────────────────────────┘
```

**Правило:** фільтри (включно з чекбоксами) — тільки у `.filter-bar`, не в тулбарі.

---

## Chip Search

### Концепція
- Чіпи: OR між чіпами, AND між токенами всередині чіпу (пробіл)
- Ціле число → точний збіг по ID
- `noComma: true` → кома не створює чіп, розділювач `|||`

### HTML (Варіант A — form-GET)
```html
<form method="get" action="/catalog">
<div class="xxx-toolbar">
  <h1>Каталог</h1>
  <a href="/catalog/new" class="btn btn-primary">+ Додати</a>
  <div class="xxx-search-wrap">
    <div class="chip-input" id="searchChipBox">
      <input type="text" class="chip-typer" id="searchChipTyper"
             placeholder="ID, назва…" autocomplete="off">
      <div class="chip-actions">
        <button type="button" class="chip-act-btn chip-act-clear hidden"
                id="chipClearBtn" title="Очистити">&#x2715;</button>
        <button type="submit" class="chip-act-btn chip-act-submit" title="Пошук">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
            <circle cx="6.5" cy="6.5" r="4.5" stroke="currentColor" stroke-width="1.6"/>
            <path d="M10 10l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
          </svg>
        </button>
      </div>
    </div>
    <input type="hidden" name="search" id="searchHidden" value="<?= h($search) ?>">
  </div>
</div>
</form>
```

### HTML (Варіант B — AJAX)
```html
<!-- те саме, але submit → type="button" з id для addEventListener -->
<button type="button" class="chip-act-btn chip-act-submit" id="catChipSubmit" ...>
<input type="hidden" id="catSearchHidden" value="">
```

### JS ініціалізація
```javascript
// Підключити перед основним скриптом:
// <script src="/modules/shared/chip-search.js?v=..."></script>

// Варіант A (form-GET):
ChipSearch.init('searchChipBox', 'searchChipTyper', 'searchHidden', null, {noComma: true});

// Варіант B (AJAX):
var filterForm = document.getElementById('myFilterForm');
filterForm.submit = function() { loadList(); };
ChipSearch.init('searchChipBox', 'searchChipTyper', 'searchHidden', filterForm);
filterForm.addEventListener('submit', function(e) { e.preventDefault(); loadList(); });
document.querySelectorAll('.js-filter-check').forEach(function(cb) {
    cb.addEventListener('change', loadList);
});
```

### JS кнопка × (показувати/приховувати)
```javascript
(function () {
    var clearBtn = document.getElementById('chipClearBtn');
    var chipBox  = document.getElementById('searchChipBox');
    var typer    = document.getElementById('searchChipTyper');
    var hidden   = document.getElementById('searchHidden');
    var form     = hidden ? hidden.closest('form') : null;
    if (!clearBtn || !chipBox || !typer || !hidden) return;

    function updateClearBtn() {
        var hasChips = chipBox.querySelectorAll('.chip').length > 0;
        var hasText  = typer.value.trim() !== '';
        clearBtn.classList.toggle('hidden', !hasChips && !hasText);
    }
    new MutationObserver(updateClearBtn).observe(chipBox, { childList: true });
    typer.addEventListener('input', updateClearBtn);

    clearBtn.addEventListener('click', function () {
        chipBox.querySelectorAll('.chip').forEach(function(c) { c.remove(); });
        typer.value = ''; hidden.value = '';
        clearBtn.classList.add('hidden');
        if (form) { var p = form.querySelector('input[name="page"]'); if (p) p.value = 1; form.submit(); }
    });
    updateClearBtn();
}());
```

---

## Filter Bar

```html
<div class="filter-bar">
    <div class="filter-bar-group">
        <span class="filter-bar-label">Магазин</span>
        <label class="filter-pill"><input type="checkbox" class="js-filter-check"> off</label>
        <label class="filter-pill active"><input type="checkbox" class="js-filter-check" checked> mff</label>
    </div>
    <div class="filter-bar-sep"></div>
    <div class="filter-bar-group">
        <span class="filter-bar-label">Статус</span>
        <label class="filter-pill"><input type="checkbox" class="js-filter-check"> Активні</label>
    </div>
    <button type="button" class="filter-bar-gear" title="Налаштувати фільтри">
        <svg viewBox="0 0 16 16" fill="none"><!-- gear icon --></svg>
    </button>
</div>
```

---

## CSS тулбару

```css
.xxx-toolbar {
    display: flex; align-items: center;
    gap: 8px; margin-bottom: 10px;
}
.xxx-toolbar h1 { margin: 0; font-size: 18px; font-weight: 700; flex-shrink: 0; }
.xxx-search-wrap { flex: 1; min-width: 160px; }
.xxx-toolbar .btn        { height: 34px; padding: 0 12px; }
.xxx-toolbar .chip-input { min-height: 34px; max-height: 34px; overflow: hidden; }
```

---

## buildWhere PHP (шаблон репозиторію)

```php
$search = trim((string)$search);
if ($search !== '') {
    $chipSep = (strpos($search, '|||') !== false) ? '/\s*\|\|\|\s*/u' : '/\s*,\s*/u';
    $rawChips = preg_split($chipSep, $search);
    $chipConditions = array();

    foreach ($rawChips as $chip) {
        $chip = trim($chip);
        if ($chip === '') continue;

        if (preg_match('/^\d+$/', $chip)) {
            $chipConditions[] = "pp.`product_id` = " . (int)$chip;
            continue;
        }

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
        $where .= ' AND ' . (count($chipConditions) === 1
            ? $chipConditions[0]
            : '(' . implode(' OR ', $chipConditions) . ')');
    }
}
```

Поля пошуку для товарів: `product_id`, `product_article`, `name` (COALESCE pd2/pd1, UK пріоритет).

---

## Паттерн sidebar (реєстр + панель)

- Ліво: `.crm-table` з clickable рядками, `?selected=ID` в URL
- Право: sticky `.card` з формою
- Клік → `window.location = url` з `selected=ID`
- AJAX save → redirect з новим ID; delete → redirect без `selected`
- Виділений рядок: клас `row-selected` на `<tr>`
- Picker завжди містить «Управляти →» у новій вкладці

## Паттерн з деревом (категорії)

```css
.layout-2col {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 20px;
    align-items: start;
}
```

- Ліво: `CategoryTree`, `height: calc(100vh - 112px)`
- Право: дві sticky картки (базові поля + SEO)
- Навігація через `history.pushState` + AJAX
- Початкові дані вбудовані в PHP (`INITIAL_DATA`)

## Мультитокенний пошук у JS (dropdown/модалки)

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
