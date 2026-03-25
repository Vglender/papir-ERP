<?php
$title = 'Атрибути';
require_once __DIR__ . '/../../shared/layout.php';
?>
<style>
.attr-layout {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 20px;
    align-items: start;
}
.attr-toolbar {
    display: flex;
    gap: 10px;
    align-items: center;
    margin-bottom: 14px;
    flex-wrap: wrap;
}
.attr-toolbar .search-input { flex: 1; min-width: 160px; max-width: 300px; }
.group-filter {
    padding: 7px 10px; border: 1px solid var(--border-input);
    border-radius: var(--radius); font-size: 13px; font-family: var(--font);
    outline: none; background: #fff; cursor: pointer;
}
.group-filter:focus { border-color: var(--blue-light); }
.attr-stats { font-size: 12px; color: var(--text-muted); margin-left: auto; white-space: nowrap; }
.crm-table td.td-name { font-weight: 500; }
.crm-table td.td-muted { color: var(--text-muted); font-size: 12px; }
.badge-off  { background: #dbeafe; color: #1d4ed8; }
.badge-mff  { background: #d1fae5; color: #065f46; }
.badge-new  { background: #fef9c3; color: #854d0e; }
.panel-empty {
    display: flex; flex-direction: column; align-items: center;
    justify-content: center; min-height: 200px; color: var(--text-faint);
    text-align: center; padding: 32px;
}
.panel-empty-icon { font-size: 40px; margin-bottom: 12px; opacity: .3; }
.card-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.card-head-title { font-size: 15px; font-weight: 600; }
.form-row { display: flex; flex-direction: column; gap: 4px; margin-bottom: 10px; }
.form-row label { font-size: 12px; color: var(--text-muted); font-weight: 500; }
.form-row input, .form-row select { padding: 7px 10px; border: 1px solid var(--border-input); border-radius: var(--radius); font-size: 13px; font-family: var(--font); outline: none; }
.form-row input:focus, .form-row select:focus { border-color: var(--blue-light); }
.attr-section-title {
    font-size: 11px; font-weight: 600; text-transform: uppercase;
    letter-spacing: .5px; color: var(--text-muted);
    margin: 14px 0 8px; padding-bottom: 4px;
    border-bottom: 1px solid var(--border);
}
/* Дубли */
.dup-list { display: flex; flex-direction: column; gap: 6px; }
.dup-item {
    display: flex; align-items: center; gap: 8px;
    padding: 7px 10px; border: 1px solid var(--border);
    border-radius: var(--radius); font-size: 12px; background: #fafafa;
    cursor: default;
}
.dup-item-name { flex: 1; font-weight: 500; }
.dup-item-cnt  { color: var(--text-muted); flex-shrink: 0; }
.dup-merge-btn {
    padding: 3px 10px; font-size: 11px; border-radius: 4px;
    border: 1px solid var(--border); background: #fff;
    cursor: pointer; color: var(--red); font-weight: 500; flex-shrink: 0;
}
.dup-merge-btn:hover { background: var(--red); color: #fff; border-color: var(--red); }
.no-dupes { font-size: 12px; color: var(--text-faint); font-style: italic; }
.form-error { color: var(--red); font-size: 12px; margin-top: 4px; display: none; }
/* Values section */
.val-section-head {
    display: flex; align-items: center; justify-content: space-between;
    cursor: pointer; user-select: none;
}
.val-section-head:hover .attr-section-title { color: var(--blue); }
.val-section-head .toggle-icon { font-size: 11px; color: var(--text-muted); }
.val-toolbar {
    display: flex; gap: 6px; align-items: center; margin: 8px 0;
}
.val-toolbar input { flex:1; padding:5px 8px; border:1px solid var(--border-input); border-radius:var(--radius); font-size:12px; outline:none; }
.val-toolbar input:focus { border-color:var(--blue-light); }
.val-lang-tabs { display:flex; gap:0; margin-bottom:8px; border-bottom:1px solid var(--border); }
.val-lang-tab { padding:4px 12px; font-size:12px; font-weight:500; cursor:pointer; color:var(--text-muted); border-bottom:2px solid transparent; margin-bottom:-1px; }
.val-lang-tab.active { color:var(--blue); border-bottom-color:var(--blue); }
.val-list { display:flex; flex-direction:column; gap:3px; max-height:320px; overflow-y:auto; }
.val-item {
    display:flex; align-items:center; gap:6px; padding:5px 8px;
    border:1px solid var(--border); border-radius:var(--radius);
    font-size:12px; background:#fafafa;
}
.val-item.selected { border-color:var(--blue-light); background:#eff6ff; }
.val-item-text { flex:1; cursor:pointer; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.val-item-cnt  { color:var(--text-muted); flex-shrink:0; font-size:11px; }
.val-edit-row  { margin-top:8px; display:none; gap:6px; align-items:center; }
.val-edit-row input { flex:1; padding:5px 8px; border:1px solid var(--border-input); border-radius:var(--radius); font-size:12px; outline:none; }
.val-edit-row input:focus { border-color:var(--blue-light); }
.val-more { text-align:center; margin-top:6px; }
.val-merge-hint { font-size:11px; color:var(--text-muted); margin-top:6px; font-style:italic; }
</style>

<div class="page-wrap-lg">

    <div class="page-head">
        <div class="breadcrumb">
            <a href="/catalog">Каталог</a>
            <span>Атрибути</span>
        </div>
    </div>

    <div class="attr-layout">

        <!-- ── Ліво: список ── -->
        <div>
            <div class="attr-toolbar">
                <input type="text" class="search-input" id="attrSearch" placeholder="Пошук за назвою або ID…" autocomplete="off">
                <select class="group-filter" id="groupFilter">
                    <option value="0">Всі групи</option>
                    <?php foreach ($groups as $g): ?>
                    <option value="<?php echo (int)$g['group_id']; ?>">
                        <?php echo htmlspecialchars($g['name_uk'] ?: $g['name_ru']); ?>
                        (<?php echo (int)$g['attrs_count']; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="attr-stats" id="attrStats"></div>
                <button class="btn btn-primary btn-sm" id="btnNewAttr">+ Новий</button>
            </div>

            <table class="crm-table" id="attrTable">
                <thead>
                    <tr>
                        <th style="width:50px">ID</th>
                        <th>Назва (UK)</th>
                        <th>Назва (RU)</th>
                        <th>Група</th>
                        <th style="width:80px">Сайти</th>
                        <th style="width:70px">Значень</th>
                    </tr>
                </thead>
                <tbody id="attrTbody">
                    <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:24px">Завантаження…</td></tr>
                </tbody>
            </table>
        </div>

        <!-- ── Право: панель ── -->
        <div id="attrFormPanel" style="position:sticky;top:16px">

            <div class="card" id="panelEmpty">
                <div class="panel-empty">
                    <div class="panel-empty-icon">⚙️</div>
                    <p>Оберіть атрибут у списку</p>
                </div>
            </div>

            <div class="card" id="panelForm" style="display:none">
                <div class="card-head">
                    <div class="card-head-title" id="panelTitle">Атрибут</div>
                    <button type="button" class="btn-icon" id="btnSaveAttr" title="Зберегти">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    </button>
                </div>

                <input type="hidden" id="fAttrId" value="0">

                <div class="form-row">
                    <label>Назва (UK) *</label>
                    <input type="text" id="fNameUk" autocomplete="off">
                </div>
                <div class="form-row">
                    <label>Назва (RU)</label>
                    <input type="text" id="fNameRu" autocomplete="off">
                </div>
                <div class="form-row">
                    <label>Група</label>
                    <select id="fGroupId">
                        <?php foreach ($groups as $g): ?>
                        <option value="<?php echo (int)$g['group_id']; ?>">
                            <?php echo htmlspecialchars($g['name_uk'] ?: $g['name_ru']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="attr-section-title">Маппінг сайтів</div>
                <div class="form-row">
                    <label>off.oc_attribute_id (Офісторг)</label>
                    <input type="number" id="fOffId" min="0" placeholder="0 = не маппінг">
                </div>
                <div class="form-row">
                    <label>mff.oc_attribute_id (Menu Folder)</label>
                    <input type="number" id="fMffId" min="0" placeholder="0 = не маппінг">
                </div>

                <div class="form-error" id="fError"></div>

                <div class="attr-section-title">Можливі дублі</div>
                <div id="dupList" class="dup-list">
                    <div class="no-dupes">Завантаження…</div>
                </div>

                <!-- ── Значення ── -->
                <div class="val-section-head" id="valSectionHead">
                    <div class="attr-section-title" style="margin:0">Значення <span id="valTotalBadge" class="badge badge-gray" style="margin-left:4px"></span></div>
                    <span class="toggle-icon" id="valToggleIcon">▼</span>
                </div>
                <div id="valSection" style="display:none;margin-top:8px">
                    <div class="val-lang-tabs" id="valLangTabs"></div>
                    <div class="val-toolbar">
                        <input type="text" id="valSearch" placeholder="Фільтр значень…" autocomplete="off">
                    </div>
                    <div class="val-list" id="valList"></div>
                    <div class="val-more" id="valMore" style="display:none">
                        <button class="btn btn-ghost btn-sm" id="btnValMore">Завантажити ще</button>
                    </div>
                    <!-- Edit/rename row -->
                    <div style="margin-top:8px;padding:8px;border:1px solid var(--border);border-radius:var(--radius);background:#f8fafc;display:none" id="valEditBox">
                        <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px">Редагувати значення:</div>
                        <div style="font-size:12px;color:var(--text-muted);margin-bottom:6px">Обрано: <strong id="valEditOld"></strong></div>
                        <div style="display:flex;gap:6px;align-items:center">
                            <input type="text" id="valEditNew" style="flex:1;padding:5px 8px;border:1px solid var(--border-input);border-radius:var(--radius);font-size:12px;outline:none">
                            <button class="btn btn-primary btn-sm" id="btnValSave">Зберегти</button>
                            <button class="btn btn-ghost btn-sm" id="btnValCancel">✕</button>
                        </div>
                        <div style="margin-top:6px;font-size:11px;color:var(--text-muted)">Зміна застосується до всіх товарів з цим значенням</div>
                        <div class="form-error" id="valEditErr"></div>
                    </div>
                    <!-- Merge info -->
                    <div class="val-merge-hint" id="valMergeHint" style="display:none">
                        Виберіть ще одне значення щоб об'єднати → клік ПКМ або кнопка "Злити"
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
var ALL_GROUPS = <?php echo json_encode($groups); ?>;
var SELECTED_ID = <?php echo (int)$selected; ?>;
var _rows = [];
var _currentId = 0;

function showToast(msg) {
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(function() { t.classList.remove('show'); }, 2200);
}

function groupName(gid) {
    for (var i = 0; i < ALL_GROUPS.length; i++) {
        if (parseInt(ALL_GROUPS[i].group_id,10) === parseInt(gid,10)) {
            return ALL_GROUPS[i].name_uk || ALL_GROUPS[i].name_ru || '—';
        }
    }
    return '—';
}

// ── Загрузка списка ──────────────────────────────────────────────────────────
function loadList() {
    var search  = document.getElementById('attrSearch').value;
    var groupId = document.getElementById('groupFilter').value;
    var url = '/attributes/api/get?search=' + encodeURIComponent(search) + '&group_id=' + groupId;

    fetch(url)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            _rows = d.rows || [];
            renderTable(_rows);
            document.getElementById('attrStats').textContent = 'Всього: ' + _rows.length;
        })
        .catch(function() { showToast('Помилка завантаження'); });
}

function renderTable(rows) {
    var tbody = document.getElementById('attrTbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:24px">Нічого не знайдено</td></tr>';
        return;
    }
    var html = '';
    for (var i = 0; i < rows.length; i++) {
        var r   = rows[i];
        var aid = parseInt(r.attribute_id, 10);
        var sel = aid === _currentId ? ' class="row-selected"' : '';
        var badges = '';
        if (r.off_attr_id) badges += '<span class="badge badge-off">off</span> ';
        if (r.mff_attr_id) badges += '<span class="badge badge-mff">mff</span>';
        if (!r.off_attr_id && !r.mff_attr_id) badges = '<span class="badge badge-gray">—</span>';

        html += '<tr' + sel + ' data-id="' + aid + '" style="cursor:pointer">'
            + '<td class="td-muted">' + aid + '</td>'
            + '<td class="td-name">' + esc(r.name_uk || '') + '</td>'
            + '<td class="td-muted">' + esc(r.name_ru || '') + '</td>'
            + '<td class="td-muted fs-12">' + esc(groupName(r.group_id)) + '</td>'
            + '<td>' + badges + '</td>'
            + '<td class="td-muted">' + (r.values_count || 0) + '</td>'
            + '</tr>';
    }
    tbody.innerHTML = html;

    // Click handlers
    var trs = tbody.querySelectorAll('tr[data-id]');
    for (var j = 0; j < trs.length; j++) {
        trs[j].addEventListener('click', (function(tr) {
            return function() { selectAttr(parseInt(tr.getAttribute('data-id'), 10)); };
        })(trs[j]));
    }
}

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Выбор атрибута ────────────────────────────────────────────────────────────
function selectAttr(id) {
    _currentId = id;
    history.pushState({id:id}, '', '/attributes?selected=' + id);

    // Highlight row
    var trs = document.getElementById('attrTbody').querySelectorAll('tr');
    for (var i = 0; i < trs.length; i++) {
        trs[i].classList.toggle('row-selected', parseInt(trs[i].getAttribute('data-id'),10) === id);
    }

    document.getElementById('panelEmpty').style.display = 'none';
    document.getElementById('panelForm').style.display  = 'block';
    document.getElementById('dupList').innerHTML = '<div class="no-dupes">Завантаження…</div>';
    // Reset values section
    _valRows = []; _valOffset = 0; _valSelected = null; _valTotal = 0;
    document.getElementById('valTotalBadge').textContent = '';
    document.getElementById('valList').innerHTML = '';
    document.getElementById('valMore').style.display = 'none';
    closeValEdit();
    document.getElementById('valMergeHint').style.display = 'none';
    if (_valOpen) loadValues(true);

    fetch('/attributes/api/get_one?id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.ok) { showToast('Помилка'); return; }
            fillForm(d.data);
        })
        .catch(function() { showToast('Помилка мережі'); });
}

function fillForm(data) {
    document.getElementById('fAttrId').value  = data.attribute_id;
    document.getElementById('fNameUk').value  = data.name_uk  || '';
    document.getElementById('fNameRu').value  = data.name_ru  || '';
    document.getElementById('fGroupId').value = data.group_id || '';
    document.getElementById('fOffId').value   = data.off_attr_id || '';
    document.getElementById('fMffId').value   = data.mff_attr_id || '';
    document.getElementById('fError').style.display = 'none';
    document.getElementById('panelTitle').textContent =
        'Атрибут #' + data.attribute_id;

    renderDuplicates(data.duplicates || []);
}

function renderDuplicates(dupes) {
    var el = document.getElementById('dupList');
    if (!dupes.length) {
        el.innerHTML = '<div class="no-dupes">Схожих не знайдено</div>';
        return;
    }
    var html = '';
    for (var i = 0; i < dupes.length; i++) {
        var d = dupes[i];
        html += '<div class="dup-item">'
            + '<div class="dup-item-name">'
            +   '#' + d.attribute_id + ' ' + esc(d.name_uk || d.name_ru || '—')
            + '</div>'
            + '<div class="dup-item-cnt">' + (d.values_count||0) + ' зн.</div>'
            + '<button class="dup-merge-btn" data-src="' + d.attribute_id + '" title="Об\'єднати: цей → поточний (поточний залишається)">→ Об\'єднати</button>'
            + '</div>';
    }
    el.innerHTML = html;

    var btns = el.querySelectorAll('.dup-merge-btn');
    for (var j = 0; j < btns.length; j++) {
        btns[j].addEventListener('click', (function(btn) {
            return function() {
                var srcId = parseInt(btn.getAttribute('data-src'), 10);
                var tgtId = _currentId;
                var srcName = btn.parentNode.querySelector('.dup-item-name').textContent;
                if (!confirm('Об\'єднати ' + srcName + ' → #' + tgtId + '?\nДжерело буде видалено, всі значення перенесено.')) return;
                mergeAttr(srcId, tgtId);
            };
        })(btns[j]));
    }
}

function mergeAttr(sourceId, targetId) {
    fetch('/attributes/api/merge', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
        body: 'source_id=' + sourceId + '&target_id=' + targetId
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (!d.ok) { showToast(d.error || 'Помилка'); return; }
        showToast('Об\'єднано');
        loadList();
        selectAttr(targetId);
    })
    .catch(function() { showToast('Помилка мережі'); });
}

// ── Новий атрибут ────────────────────────────────────────────────────────────
document.getElementById('btnNewAttr').addEventListener('click', function() {
    _currentId = 0;
    history.pushState({}, '', '/attributes');
    document.getElementById('panelEmpty').style.display = 'none';
    document.getElementById('panelForm').style.display  = 'block';
    document.getElementById('fAttrId').value  = 0;
    document.getElementById('fNameUk').value  = '';
    document.getElementById('fNameRu').value  = '';
    document.getElementById('fOffId').value   = '';
    document.getElementById('fMffId').value   = '';
    document.getElementById('fError').style.display = 'none';
    document.getElementById('panelTitle').textContent = 'Новий атрибут';
    document.getElementById('dupList').innerHTML = '';
    document.getElementById('fNameUk').focus();
});

// ── Зберегти ─────────────────────────────────────────────────────────────────
document.getElementById('btnSaveAttr').addEventListener('click', function() {
    var nameUk = document.getElementById('fNameUk').value.trim();
    var err    = document.getElementById('fError');
    if (!nameUk) {
        err.textContent = 'Назва (UK) обов\'язкова';
        err.style.display = 'block';
        return;
    }
    err.style.display = 'none';
    var btn = this;
    btn.disabled = true;

    var params = 'attribute_id=' + document.getElementById('fAttrId').value
        + '&name_uk='    + encodeURIComponent(nameUk)
        + '&name_ru='    + encodeURIComponent(document.getElementById('fNameRu').value)
        + '&group_id='   + document.getElementById('fGroupId').value
        + '&off_attr_id='+ encodeURIComponent(document.getElementById('fOffId').value || '0')
        + '&mff_attr_id='+ encodeURIComponent(document.getElementById('fMffId').value || '0')
        + '&status=1';

    fetch('/attributes/api/save', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
        body: params
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        btn.disabled = false;
        if (!d.ok) { err.textContent = d.error || 'Помилка'; err.style.display='block'; return; }
        showToast('Збережено');
        loadList();
        if (d.attribute_id) selectAttr(d.attribute_id);
    })
    .catch(function() {
        btn.disabled = false;
        err.textContent = 'Помилка мережі';
        err.style.display = 'block';
    });
});

// ── Поиск и фильтр ───────────────────────────────────────────────────────────
var _searchTimer = null;
document.getElementById('attrSearch').addEventListener('input', function() {
    clearTimeout(_searchTimer);
    _searchTimer = setTimeout(loadList, 300);
});
document.getElementById('groupFilter').addEventListener('change', loadList);

// ── Values section ───────────────────────────────────────────────────────────
var _valLangId   = 2;   // UK default
var _valOffset   = 0;
var _valSearch   = '';
var _valTotal    = 0;
var _valRows     = [];
var _valSelected = null; // { text, cnt } — первый клик (для мержа)
var _valOpen     = false;
var _valTimer    = null;

// Toggle открытия
document.getElementById('valSectionHead').addEventListener('click', function() {
    _valOpen = !_valOpen;
    document.getElementById('valSection').style.display = _valOpen ? 'block' : 'none';
    document.getElementById('valToggleIcon').textContent = _valOpen ? '▲' : '▼';
    if (_valOpen && _currentId) loadValues(true);
});

// Lang tabs
function buildValLangTabs(langs) {
    var el = document.getElementById('valLangTabs');
    el.innerHTML = '';
    for (var i = 0; i < langs.length; i++) {
        var tab = document.createElement('div');
        tab.className = 'val-lang-tab' + (langs[i].language_id == _valLangId ? ' active' : '');
        tab.textContent = langs[i].code.toUpperCase();
        tab.setAttribute('data-lang', langs[i].language_id);
        el.appendChild(tab);
    }
    el.querySelectorAll('.val-lang-tab').forEach(function(t) {
        t.addEventListener('click', function() {
            _valLangId = parseInt(t.getAttribute('data-lang'), 10);
            el.querySelectorAll('.val-lang-tab').forEach(function(x) { x.classList.remove('active'); });
            t.classList.add('active');
            loadValues(true);
        });
    });
}

function loadValues(reset) {
    if (!_currentId) return;
    if (reset) { _valOffset = 0; _valRows = []; _valSelected = null; closeValEdit(); }

    var url = '/attributes/api/get_values?attribute_id=' + _currentId
        + '&language_id=' + _valLangId
        + '&offset=' + _valOffset
        + '&search=' + encodeURIComponent(_valSearch);

    fetch(url)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.ok) return;
            _valTotal = d.total;
            _valRows  = reset ? d.rows : _valRows.concat(d.rows);
            _valOffset = _valOffset + d.rows.length;
            document.getElementById('valTotalBadge').textContent = _valTotal;
            renderValList();
            document.getElementById('valMore').style.display =
                _valRows.length < _valTotal ? 'block' : 'none';
        });
}

function renderValList() {
    var el = document.getElementById('valList');
    if (!_valRows.length) {
        el.innerHTML = '<div class="no-dupes">Значень не знайдено</div>';
        return;
    }
    el.innerHTML = '';
    for (var i = 0; i < _valRows.length; i++) {
        var row  = _valRows[i];
        var item = document.createElement('div');
        item.className = 'val-item';
        if (_valSelected && _valSelected.text === row.text) item.classList.add('selected');

        var span = document.createElement('span');
        span.className = 'val-item-text';
        span.textContent = row.text;
        span.title = 'Клік — редагувати | Shift+клік — позначити для злиття';

        var cnt = document.createElement('span');
        cnt.className = 'val-item-cnt';
        cnt.textContent = row.cnt + ' тов.';

        item.appendChild(span);
        item.appendChild(cnt);

        // Кнопка мерж (якщо обрано перший елемент)
        if (_valSelected && _valSelected.text !== row.text) {
            var mb = document.createElement('button');
            mb.className = 'dup-merge-btn';
            mb.textContent = '← Злити';
            mb.title = 'Злити "' + _valSelected.text + '" → "' + row.text + '"';
            mb.setAttribute('data-target', row.text);
            mb.addEventListener('click', (function(tgt) {
                return function(e) {
                    e.stopPropagation();
                    doMergeValue(_valSelected.text, tgt);
                };
            })(row.text));
            item.appendChild(mb);
        }

        span.addEventListener('click', (function(r2) {
            return function(e) {
                if (e.shiftKey) {
                    // Shift+клік — позначити як джерело для мержу
                    _valSelected = r2;
                    renderValList();
                    document.getElementById('valMergeHint').style.display = 'block';
                    closeValEdit();
                } else {
                    // Звичайний клік — редагувати
                    _valSelected = null;
                    document.getElementById('valMergeHint').style.display = 'none';
                    openValEdit(r2.text);
                    renderValList();
                }
            };
        })(row));

        el.appendChild(item);
    }
}

function openValEdit(text) {
    var box = document.getElementById('valEditBox');
    box.style.display = 'block';
    document.getElementById('valEditOld').textContent = text;
    document.getElementById('valEditNew').value = text;
    document.getElementById('valEditErr').style.display = 'none';
    document.getElementById('valEditNew').focus();
    document.getElementById('valEditNew').select();
}

function closeValEdit() {
    document.getElementById('valEditBox').style.display = 'none';
    document.getElementById('valEditErr').style.display = 'none';
}

document.getElementById('btnValCancel').addEventListener('click', function() {
    closeValEdit();
    _valSelected = null;
    document.getElementById('valMergeHint').style.display = 'none';
    renderValList();
});

document.getElementById('btnValSave').addEventListener('click', function() {
    var oldText = document.getElementById('valEditOld').textContent;
    var newText = document.getElementById('valEditNew').value.trim();
    var err     = document.getElementById('valEditErr');
    if (!newText) { err.textContent = 'Не може бути порожнім'; err.style.display='block'; return; }
    if (newText === oldText) { closeValEdit(); return; }

    fetch('/attributes/api/save_value', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: 'attribute_id=' + _currentId
            + '&old_text=' + encodeURIComponent(oldText)
            + '&new_text=' + encodeURIComponent(newText)
            + '&language_id=' + _valLangId
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (!d.ok) { err.textContent = d.error || 'Помилка'; err.style.display='block'; return; }
        closeValEdit();
        showToast('Оновлено: ' + d.affected + ' товарів');
        loadValues(true);
    });
});

// Enter у полі редагування
document.getElementById('valEditNew').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') document.getElementById('btnValSave').click();
    if (e.key === 'Escape') document.getElementById('btnValCancel').click();
});

function doMergeValue(sourceText, targetText) {
    if (!confirm('Злити "' + sourceText + '" → "' + targetText + '"?\nВсі товари з першим значенням отримають друге.')) return;
    fetch('/attributes/api/merge_value', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: 'attribute_id=' + _currentId
            + '&source_text=' + encodeURIComponent(sourceText)
            + '&target_text=' + encodeURIComponent(targetText)
            + '&language_id=' + _valLangId
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (!d.ok) { showToast(d.error || 'Помилка'); return; }
        _valSelected = null;
        document.getElementById('valMergeHint').style.display = 'none';
        showToast('Злито: ' + d.affected + ' товарів');
        loadValues(true);
    });
}

// Load more
document.getElementById('btnValMore').addEventListener('click', function() {
    loadValues(false);
});

// Search filter
document.getElementById('valSearch').addEventListener('input', function() {
    _valSearch = this.value;
    clearTimeout(_valTimer);
    _valTimer = setTimeout(function() { loadValues(true); }, 300);
});

// Ініціалізація мовних вкладок (з ALL_GROUPS немає — беремо статично)
(function() {
    var langs = [{language_id:2, code:'uk'}, {language_id:1, code:'ru'}];
    buildValLangTabs(langs);
})();

// ── Init ─────────────────────────────────────────────────────────────────────
loadList();
if (SELECTED_ID) {
    setTimeout(function() { selectAttr(SELECTED_ID); }, 100);
}
</script>

<?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>
