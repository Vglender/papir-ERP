/**
 * ChipSearch — стандартный поиск с чипами для Papir CRM.
 *
 * Логика на сервере (PHP, buildWhere):
 *   - Значение hidden-input передаётся как GET/POST параметр search.
 *   - Разбивается по запятой → OR между чипами.
 *   - Чистое целое число → точное совпадение product_id = N.
 *   - Текст → AND по пробельным токенам (LIKE '%...%') по ключевым полям таблицы.
 *
 * Разметка:
 *   <div class="chip-input" id="searchChipBox">
 *       <input type="text" class="chip-typer" id="searchChipTyper"
 *              placeholder="ID, артикул или название…" autocomplete="off">
 *   </div>
 *   <input type="hidden" name="search" id="search" value="…">
 *
 * Инициализация:
 *   ChipSearch.init('searchChipBox', 'searchChipTyper', 'search');
 *   // form — опционально; если не передан, ищется ближайший form от hidden-input
 *
 * Поведение:
 *   - Запятая или Enter при непустом поле → новый чип
 *   - Enter при пустом поле → submit формы (сбрасывает page=1)
 *   - Вставка текста → разбивается по запятым/переносам строк → несколько чипов
 *   - Backspace в пустом поле → удаляет последний чип
 *   - × на чипе → удаляет чип
 *   - При reload: hidden-value разбирается обратно в чипы
 */
var ChipSearch = (function () {

    function init(boxId, typerId, hiddenId, form) {
        var box    = document.getElementById(boxId);
        var typer  = document.getElementById(typerId);
        var hidden = document.getElementById(hiddenId);
        if (!box || !typer || !hidden) return;

        var searchForm = form || hidden.closest('form');
        if (!searchForm) return;

        var chips = [];

        function escHtml(s) {
            return String(s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function render() {
            box.querySelectorAll('.chip').forEach(function (el) { el.remove(); });
            chips.forEach(function (text, idx) {
                var chip = document.createElement('span');
                chip.className = 'chip';
                chip.innerHTML =
                    escHtml(text) +
                    '<span class="chip-x" data-idx="' + idx + '" title="Удалить">&#x2715;</span>';
                box.insertBefore(chip, typer);
            });
            hidden.value = chips.join(',');
            typer.placeholder = chips.length ? '' : (typer.getAttribute('data-placeholder') || '');
        }

        function addRaw(raw) {
            raw.split(',').forEach(function (p) {
                p = p.trim();
                if (p !== '' && chips.indexOf(p) === -1) chips.push(p);
            });
            render();
        }

        function remove(idx) {
            chips.splice(idx, 1);
            render();
        }

        function submitForm() {
            var pageInput = searchForm.querySelector('input[name="page"]');
            if (pageInput) pageInput.value = 1;
            searchForm.submit();
        }

        // ── Init from existing value (page reload) ──────────────────────────
        typer.setAttribute('data-placeholder', typer.placeholder);
        var initVal = hidden.value.trim();
        if (initVal) {
            initVal.split(',').forEach(function (p) {
                p = p.trim();
                if (p) chips.push(p);
            });
            render();
        }

        // ── Click on box → focus typer; click × → remove chip ───────────────
        box.addEventListener('click', function (e) {
            var x = e.target.closest('.chip-x');
            if (x) { remove(parseInt(x.getAttribute('data-idx'), 10)); return; }
            typer.focus();
        });

        // ── Keyboard ─────────────────────────────────────────────────────────
        typer.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (typer.value.trim() !== '') { addRaw(typer.value); typer.value = ''; }
                else                           { submitForm(); }
                return;
            }
            if (e.key === ',' && typer.value.trim() !== '') {
                e.preventDefault();
                addRaw(typer.value);
                typer.value = '';
                return;
            }
            if (e.key === 'Backspace' && typer.value === '' && chips.length) {
                remove(chips.length - 1);
            }
        });

        // ── Paste ─────────────────────────────────────────────────────────────
        typer.addEventListener('paste', function (e) {
            e.preventDefault();
            var text = (e.clipboardData || window.clipboardData).getData('text');
            text.split(/[,\n]+/).forEach(function (p) {
                p = p.trim();
                if (p !== '' && chips.indexOf(p) === -1) chips.push(p);
            });
            typer.value = '';
            render();
        });

        // ── Inline comma ──────────────────────────────────────────────────────
        typer.addEventListener('input', function () {
            if (typer.value.indexOf(',') !== -1) {
                addRaw(typer.value.replace(/,/g, ' '));
                typer.value = '';
            }
        });

        // ── Form submit: flush current typer text ─────────────────────────────
        searchForm.addEventListener('submit', function () {
            if (typer.value.trim() !== '') { addRaw(typer.value); typer.value = ''; }
            hidden.value = chips.join(',');
        });
    }

    return { init: init };
}());
