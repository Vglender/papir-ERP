<?php require_once __DIR__ . '/../../shared/layout.php'; ?>

<style>
.dedup-layout {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 0;
    height: calc(100vh - 88px);
    overflow: hidden;
}

/* ── Left: groups list ──────────────────────────────────────────────── */
.dedup-list-col {
    border-right: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.dedup-list-head {
    padding: 12px 14px 10px;
    border-bottom: 1px solid #e5e7eb;
    flex-shrink: 0;
}
.dedup-list-head h2 {
    font-size: 14px;
    font-weight: 700;
    margin: 0 0 4px;
    color: #111827;
}
.dedup-list-stat {
    font-size: 12px;
    color: #6b7280;
}
.dedup-list-body {
    overflow-y: auto;
    flex: 1;
}
.dedup-group-card {
    padding: 10px 14px;
    border-bottom: 1px solid #f3f4f6;
    cursor: pointer;
    transition: background .12s;
}
.dedup-group-card:hover   { background: #f9fafb; }
.dedup-group-card.active  { background: #eff6ff; border-right: 3px solid #3b82f6; }
.dedup-group-card.done    { opacity: .45; pointer-events: none; }
.dedup-group-names {
    font-size: 13px;
    font-weight: 600;
    color: #111827;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 4px;
}
.dedup-group-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.dedup-badge {
    font-size: 11px;
    font-weight: 600;
    padding: 1px 6px;
    border-radius: 10px;
    white-space: nowrap;
}
.dedup-badge-phone  { background: #dbeafe; color: #1d4ed8; }
.dedup-badge-email  { background: #dcfce7; color: #15803d; }
.dedup-badge-okpo   { background: #fef3c7; color: #92400e; }
.dedup-badge-count  { background: #f3f4f6; color: #6b7280; }

/* ── Right: detail panel ────────────────────────────────────────────── */
.dedup-detail-col {
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.dedup-detail-body {
    flex: 1;
    overflow-y: auto;
    padding: 20px 24px;
}
.dedup-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #9ca3af;
    font-size: 15px;
}
.dedup-detail-title {
    font-size: 15px;
    font-weight: 700;
    margin: 0 0 6px;
    color: #111827;
}
.dedup-detail-reasons {
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 16px;
}
.dedup-members {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.dedup-member-card {
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 14px 16px;
    cursor: pointer;
    transition: border-color .15s, background .15s;
    position: relative;
}
.dedup-member-card:hover          { border-color: #93c5fd; background: #f8faff; }
.dedup-member-card.target-sel     { border-color: #3b82f6; background: #eff6ff; }
.dedup-member-card.source-excl    { opacity: .5; }
.dedup-member-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}
.dedup-member-radio {
    width: 16px; height: 16px;
    cursor: pointer;
    flex-shrink: 0;
    accent-color: #3b82f6;
}
.dedup-member-name {
    font-weight: 700;
    font-size: 14px;
    color: #111827;
    flex: 1;
}
.dedup-member-link {
    font-size: 11px;
    color: #6b7280;
    text-decoration: none;
    white-space: nowrap;
}
.dedup-member-link:hover { color: #3b82f6; }
.dedup-member-contacts {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 20px;
    font-size: 12px;
    color: #374151;
    padding-left: 26px;
}
.dedup-member-contacts span { white-space: nowrap; }
.dedup-member-contacts .match-val { font-weight: 700; color: #1d4ed8; }
.dedup-member-stats {
    display: flex;
    gap: 12px;
    font-size: 11px;
    color: #9ca3af;
    padding-left: 26px;
    margin-top: 6px;
}
.dedup-member-excl-btn {
    position: absolute;
    top: 8px; right: 10px;
    font-size: 11px;
    color: #9ca3af;
    background: none;
    border: none;
    cursor: pointer;
    padding: 2px 4px;
}
.dedup-member-excl-btn:hover { color: #ef4444; }

/* ── Actions bar (top of right panel) ───────────────────────────────── */
.dedup-actions {
    padding: 10px 16px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    background: #f9fafb;
}
.dedup-actions-info {
    font-size: 12px;
    color: #6b7280;
    flex: 1;
}

/* ── Merge result banner ─────────────────────────────────────────────── */
.dedup-merge-result {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 6px;
    padding: 8px 14px;
    font-size: 13px;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

/* ── Loading ────────────────────────────────────────────────────────── */
.dedup-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 200px;
    color: #9ca3af;
    font-size: 13px;
}

/* ── Toolbar ────────────────────────────────────────────────────────── */
.dedup-toolbar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    border-bottom: 1px solid #e5e7eb;
    flex-shrink: 0;
    background: #fff;
}
.dedup-toolbar h1 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
}
.dedup-search-wrap { flex: 1; }
.dedup-search-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    width: 360px;
    max-height: 300px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0,0,0,.12);
    z-index: 100;
}
.dedup-search-item {
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid #f3f4f6;
    font-size: 13px;
}
.dedup-search-item:hover { background: #eff6ff; }
.dedup-search-item-name { font-weight: 600; color: #111827; }
.dedup-search-item-meta { font-size: 11px; color: #6b7280; margin-top: 2px; }
.dedup-search-info {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 6px;
    padding: 3px 10px;
    font-size: 12px;
    color: #1d4ed8;
    margin-left: 8px;
}
.dedup-search-info button {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
    color: #6b7280;
    padding: 0 2px;
    line-height: 1;
}
.dedup-search-info button:hover { color: #ef4444; }
</style>

<div class="dedup-toolbar">
    <h1>Дедуплікація контрагентів</h1>
    <div class="dedup-search-wrap" style="position:relative;margin-left:12px;">
        <input type="text" id="dedupSearchInput" class="input input-sm" style="width:280px"
               placeholder="Пошук контрагента для перевірки…"
               autocomplete="off">
        <div id="dedupSearchDrop" class="dedup-search-dropdown" style="display:none"></div>
    </div>
    <button class="btn btn-ghost btn-sm" onclick="DEDUP.reload()">↺ Оновити</button>
    <a href="/counterparties" class="btn btn-ghost btn-sm">← Реєстр</a>
</div>

<div class="dedup-layout">

    <!-- ── Left: groups list ───────────────────────────────────────────── -->
    <div class="dedup-list-col">
        <div class="dedup-list-head">
            <h2>Групи дублікатів</h2>
            <div class="dedup-list-stat" id="dedupStat">Завантаження…</div>
        </div>
        <div class="dedup-list-body" id="dedupListBody">
            <div class="dedup-loading">Завантаження…</div>
        </div>
    </div>

    <!-- ── Right: detail panel ─────────────────────────────────────────── -->
    <div class="dedup-detail-col">
        <div class="dedup-actions" id="dedupActions" style="display:none">
            <div class="dedup-actions-info" id="dedupActInfo"></div>
            <button class="btn btn-ghost btn-sm" onclick="DEDUP.openRelationModal()" title="Не дублікати, але пов'язані контрагенти">🔗 Зв'язати</button>
            <button class="btn btn-ghost btn-sm" onclick="DEDUP.skipGroup()">Не дублікати</button>
            <button class="btn btn-primary btn-sm" onclick="DEDUP.doMerge()">Злити в обраний</button>
        </div>
        <div class="dedup-detail-body" id="dedupDetailBody">
            <div class="dedup-empty">← Виберіть групу зі списку</div>
        </div>
    </div>
</div>

<!-- ══ Modal: create relation ════════════════════════════════════════════════ -->
<div class="modal-overlay" id="dedupRelModal" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-head">
      <span>Зв'язати контрагентів</span>
      <button class="modal-close" onclick="document.getElementById('dedupRelModal').style.display='none'">×</button>
    </div>
    <div class="modal-body">
      <p style="margin:0 0 12px;font-size:12px;color:#6b7280">
        Записи не є дублікатами, але пов'язані (наприклад, фізособа — співробітник компанії).
        Оберіть батьківський запис, дочірній та тип зв'язку.
      </p>
      <div class="form-row">
        <label>Батьківський (компанія / відділ)</label>
        <select id="dedupRelParent" style="width:100%"></select>
      </div>
      <div class="form-row">
        <label>Дочірній (особа / контакт)</label>
        <select id="dedupRelChild" style="width:100%"></select>
      </div>
      <div class="form-row">
        <label>Тип зв'язку</label>
        <select id="dedupRelType" style="width:100%">
          <option value="employee">Співробітник</option>
          <option value="contact_person">Контактна особа</option>
          <option value="director">Директор</option>
          <option value="accountant">Бухгалтер</option>
          <option value="manager">Менеджер</option>
          <option value="signer">Підписант</option>
          <option value="buyer">Покупець</option>
          <option value="other">Інший</option>
        </select>
      </div>
      <div class="modal-error" id="dedupRelErr" style="display:none"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="document.getElementById('dedupRelModal').style.display='none'">Скасувати</button>
      <button class="btn btn-primary" onclick="DEDUP.doCreateRelation()">Зв'язати і закрити групу</button>
    </div>
  </div>
</div>

<script>
var DEDUP = (function () {
    var PAGE_SIZE = 50;

    var state = {
        groups:      [],    // all loaded groups (accumulated across pages)
        total:       0,     // total groups on server
        loadedOffset:0,     // how many we've fetched so far
        loading:     false, // fetch in progress
        skipped:     {},    // groupKey → true (from localStorage)
        activeIdx:   -1,    // currently selected group index
        targetId:    0,     // currently selected target counterparty id
        excluded:    {},    // counterparty id → true (excluded from merge within current group)
    };

    var SKIP_KEY = 'dedup_skipped_v1';

    // ── Init ────────────────────────────────────────────────────────────────

    function init() {
        _loadSkipped();
        _fetchGroups(0);
        _initSearch();
        // Infinite scroll: load more when list bottom is near visible
        var listBody = document.getElementById('dedupListBody');
        listBody.addEventListener('scroll', function() {
            if (state.loading) return;
            if (state.loadedOffset >= state.total) return;
            var threshold = 150; // px from bottom
            if (listBody.scrollHeight - listBody.scrollTop - listBody.clientHeight < threshold) {
                _fetchGroups(state.loadedOffset);
            }
        });
    }

    function _loadSkipped() {
        try {
            var raw = localStorage.getItem(SKIP_KEY);
            state.skipped = raw ? JSON.parse(raw) : {};
        } catch (e) {
            state.skipped = {};
        }
    }

    function _saveSkipped() {
        try { localStorage.setItem(SKIP_KEY, JSON.stringify(state.skipped)); } catch (e) {}
    }

    function reload() {
        document.getElementById('dedupStat').textContent = 'Завантаження…';
        document.getElementById('dedupListBody').innerHTML = '<div class="dedup-loading">Завантаження…</div>';
        document.getElementById('dedupDetailBody').innerHTML = '<div class="dedup-empty">← Виберіть групу зі списку</div>';
        document.getElementById('dedupActions').style.display = 'none';
        state.groups       = [];
        state.total        = 0;
        state.loadedOffset = 0;
        state.loading      = false;
        state.activeIdx    = -1;
        state.excluded     = {};
        _fetchGroups(0);
    }

    function _fetchGroups(offset) {
        if (state.loading) return;
        state.loading = true;

        // Show spinner at bottom of list if loading more pages
        if (offset > 0) {
            var spinner = document.createElement('div');
            spinner.id  = 'dedupSpinner';
            spinner.className = 'dedup-loading';
            spinner.style.height = '40px';
            spinner.textContent = 'Завантаження…';
            document.getElementById('dedupListBody').appendChild(spinner);
        }

        fetch('/counterparties/api/find_dedup_groups?offset=' + offset + '&limit=' + PAGE_SIZE)
            .then(function(r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function(d) {
                state.loading = false;
                var sp = document.getElementById('dedupSpinner');
                if (sp) sp.remove();

                if (!d.ok) { _showErr('Помилка: ' + (d.error || '')); return; }

                state.total        = d.total || 0;
                state.loadedOffset = offset + (d.groups ? d.groups.length : 0);
                state.groups       = state.groups.concat(d.groups || []);

                _renderList();
            })
            .catch(function(e) {
                state.loading = false;
                var sp = document.getElementById('dedupSpinner');
                if (sp) sp.remove();
                _showErr('Мережева помилка: ' + e.message);
            });
    }

    function _showErr(msg) {
        document.getElementById('dedupListBody').innerHTML =
            '<div class="dedup-loading" style="color:#ef4444">' + _esc(msg) + '</div>';
        document.getElementById('dedupStat').textContent = 'Помилка';
    }

    function _updateStat() {
        var visible      = _visibleGroups().length;
        var skippedCount = Object.keys(state.skipped).length;
        var stat = 'Всього: ' + state.total;
        if (skippedCount) stat += ' · Пропущено: ' + skippedCount;
        stat += ' · Залишилось: ' + visible;
        if (state.loadedOffset < state.total) {
            stat += ' (завантажено ' + state.loadedOffset + ')';
        }
        document.getElementById('dedupStat').textContent = stat;
    }

    // ── List rendering ───────────────────────────────────────────────────────

    function _visibleGroups() {
        return state.groups.filter(function(g, i) {
            return !state.skipped[_groupKey(g)];
        });
    }

    function _groupKey(g) {
        return g.ids.slice().sort(function(a,b){return a-b;}).join('-');
    }

    function _renderList() {
        var visible      = _visibleGroups();
        var skippedCount = Object.keys(state.skipped).length;

        _updateStat();

        if (state.loadedOffset > 0 && visible.length === 0 && state.loadedOffset >= state.total) {
            document.getElementById('dedupListBody').innerHTML =
                '<div class="dedup-loading" style="color:#16a34a">✓ Дублікатів не знайдено' +
                (skippedCount ? ' (або всі пропущені)' : '') + '</div>';
            document.getElementById('dedupDetailBody').innerHTML =
                '<div class="dedup-empty">Все перевірено</div>';
            document.getElementById('dedupActions').style.display = 'none';
            return;
        }

        var html = '';
        state.groups.forEach(function(g, i) {
            var key     = _groupKey(g);
            var skipped = !!state.skipped[key];
            var active  = (i === state.activeIdx);

            var names = g.members.slice(0, 3).map(function(m) {
                return m.name.length > 22 ? m.name.substring(0, 20) + '…' : m.name;
            }).join(', ');

            var badges = '';
            g.match_types.forEach(function(t) {
                var label = t === 'phone' ? '📞 Тел.' : (t === 'email' ? '✉ Email' : '🔢 ЄДРПОУ');
                var cls   = 'dedup-badge dedup-badge-' + t;
                badges += '<span class="' + cls + '">' + label + '</span>';
            });
            badges += '<span class="dedup-badge dedup-badge-count">' + g.members.length + ' записи</span>';

            html += '<div class="dedup-group-card' + (active ? ' active' : '') + (skipped ? ' done' : '') + '"'
                  + ' onclick="DEDUP.selectGroup(' + i + ')">'
                  + '<div class="dedup-group-names">' + _esc(names) + '</div>'
                  + '<div class="dedup-group-meta">' + badges + '</div>'
                  + '</div>';
        });

        document.getElementById('dedupListBody').innerHTML = html;
    }

    // ── Group selection ───────────────────────────────────────────────────────

    function selectGroup(idx) {
        state.activeIdx = idx;
        state.excluded  = {};
        // Default target = first member (highest activity, sorted by API)
        var g = state.groups[idx];
        state.targetId  = (g && g.members.length > 0) ? g.members[0].id : 0;
        _renderList();
        _renderDetail();
    }

    function _renderDetail() {
        var idx = state.activeIdx;
        if (idx < 0 || idx >= state.groups.length) return;
        var g = state.groups[idx];

        // Collect match values for highlighting
        var matchVals = {};
        g.reasons.forEach(function(r) {
            var parts = r.split(':');
            var type  = parts[0];
            var val   = parts.slice(1).join(':');
            if (!matchVals[type]) matchVals[type] = {};
            matchVals[type][val.toLowerCase()] = true;
        });

        // Reason label
        var reasonLabels = g.reasons.map(function(r) {
            var parts = r.split(':');
            var type  = parts[0];
            var val   = parts.slice(1).join(':');
            var prefix = type === 'phone' ? '📞' : (type === 'email' ? '✉' : '🔢');
            return prefix + ' ' + val;
        });

        var html = '<div class="dedup-detail-title">Група: ' + g.members.length + ' записи</div>';
        html += '<div class="dedup-detail-reasons">Збіг по: ' + _esc(reasonLabels.join(' · ')) + '</div>';
        html += '<div class="dedup-members">';

        g.members.forEach(function(m, mi) {
            var isTarget  = (m.id === state.targetId);
            var isExcl    = !isTarget && !!state.excluded[m.id];

            var typeLabel = {company:'Юрлице', fop:'ФОП', person:'Фіз. особа',
                             department:'Відділ', other:'Інший'}[m.type] || m.type;

            html += '<div class="dedup-member-card' + (isTarget ? ' target-sel' : '') + (isExcl ? ' source-excl' : '') + '"'
                  + ' id="mcard_' + m.id + '"'
                  + ' onclick="DEDUP.setTarget(' + m.id + ')">';

            // Exclude button — only on non-target cards
            if (!isTarget) {
                html += '<button class="dedup-member-excl-btn" '
                      + 'onclick="event.stopPropagation();DEDUP.toggleExclude(' + m.id + ')" '
                      + 'title="' + (isExcl ? 'Включити у злиття' : 'Виключити зі злиття') + '">'
                      + (isExcl ? '+ Включити' : '✕') + '</button>';
            }

            html += '<div class="dedup-member-header">'
                  + '<input type="radio" name="dedup_target" class="dedup-member-radio"'
                  + (isTarget ? ' checked' : '')
                  + ' onclick="event.stopPropagation();DEDUP.setTarget(' + m.id + ')">'
                  + '<span class="dedup-member-name">' + _esc(m.name) + '</span>'
                  + '<span class="badge ' + _typeBadgeClass(m.type) + '">' + _esc(typeLabel) + '</span>'
                  + '<a class="dedup-member-link" href="/counterparties/view?id=' + m.id + '" target="_blank">↗ Відкрити</a>'
                  + '</div>';

            html += '<div class="dedup-member-contacts">';

            if (m.phone) {
                var isMatch = matchVals['phone'] && matchVals['phone'][_normPhone(m.phone)];
                html += '<span' + (isMatch ? ' class="match-val"' : '') + '>📞 ' + _esc(m.phone) + '</span>';
            }
            if (m.email) {
                var isMatch = matchVals['email'] && matchVals['email'][m.email.toLowerCase()];
                html += '<span' + (isMatch ? ' class="match-val"' : '') + '>✉ ' + _esc(m.email) + '</span>';
            }
            if (m.okpo) {
                var isMatch = matchVals['okpo'] && (matchVals['okpo'][m.okpo] || matchVals['okpo'][m.okpo.replace(/\D/g,'')]);
                html += '<span' + (isMatch ? ' class="match-val"' : '') + '>🔢 ЄДРПОУ: ' + _esc(m.okpo) + '</span>';
            }
            if (m.inn) {
                html += '<span>ІПН: ' + _esc(m.inn) + '</span>';
            }
            if (m.telegram) {
                html += '<span>TG: ' + _esc(m.telegram) + '</span>';
            }
            html += '</div>';

            html += '<div class="dedup-member-stats">';
            html += '<span>💬 ' + m.msg_count + ' повідомлень</span>';
            html += '<span>🛒 ' + m.order_count + ' замовлень</span>';
            if (m.last_order_at) {
                html += '<span>Останнє замовлення: ' + _esc(m.last_order_at.substring(0, 10)) + '</span>';
            }
            html += '</div>';

            html += '</div>'; // .dedup-member-card
        });

        html += '</div>'; // .dedup-members
        document.getElementById('dedupDetailBody').innerHTML = html;
        _updateActionsBar(g);
    }

    function _updateActionsBar(g) {
        var targetId = _getTargetId(g);
        var sources  = g.members.filter(function(m) {
            return m.id !== targetId && !state.excluded[m.id];
        });

        var targetName = '';
        g.members.forEach(function(m) { if (m.id === targetId) targetName = m.name; });

        var info = 'Ціль: ' + targetName;
        if (sources.length > 0) {
            var srcNames = sources.map(function(m) { return m.name; }).join(', ');
            info += ' · Зливаємо: ' + srcNames;
        } else {
            info = 'Усі записи, крім цілі, виключено';
        }

        document.getElementById('dedupActInfo').textContent = info;
        document.getElementById('dedupActions').style.display = 'flex';
    }

    function _getTargetId(g) {
        if (state.targetId) return state.targetId;
        // Fallback: first non-excluded member
        for (var j = 0; j < g.members.length; j++) {
            if (!state.excluded[g.members[j].id]) return g.members[j].id;
        }
        return 0;
    }

    function setTarget(id) {
        if (state.targetId === id) return;  // already target
        state.targetId = id;
        // If this was excluded, un-exclude it
        delete state.excluded[id];
        _renderDetail();
    }

    function toggleExclude(id) {
        if (id === state.targetId) return;  // can't exclude target
        if (state.excluded[id]) {
            delete state.excluded[id];
        } else {
            state.excluded[id] = true;
        }
        _renderDetail();
    }

    // ── Relation actions ───────────────────────────────────────────────────────

    function openRelationModal() {
        var idx = state.activeIdx;
        if (idx < 0) return;
        var g = state.groups[idx];

        // Populate parent/child selects from group members
        var parentSel = document.getElementById('dedupRelParent');
        var childSel  = document.getElementById('dedupRelChild');
        var opts = '';
        g.members.forEach(function(m) {
            opts += '<option value="' + m.id + '">' + _esc(m.name) + ' (' + (m.type || '') + ')</option>';
        });
        parentSel.innerHTML = opts;
        childSel.innerHTML  = opts;
        // Default: first member = parent, second = child
        if (g.members.length >= 2) {
            childSel.selectedIndex = 1;
        }
        document.getElementById('dedupRelErr').style.display = 'none';
        document.getElementById('dedupRelModal').style.display = 'flex';
    }

    function doCreateRelation() {
        var parentId  = parseInt(document.getElementById('dedupRelParent').value, 10);
        var childId   = parseInt(document.getElementById('dedupRelChild').value, 10);
        var relType   = document.getElementById('dedupRelType').value;
        var errEl     = document.getElementById('dedupRelErr');

        if (!parentId || !childId || parentId === childId) {
            errEl.textContent = 'Оберіть різні записи для батьківського і дочірнього';
            errEl.style.display = 'block';
            return;
        }

        var fd = new FormData();
        fd.append('parent_id',     parentId);
        fd.append('child_id',      childId);
        fd.append('relation_type', relType);

        fetch('/counterparties/api/save_relation', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.ok) {
                    errEl.textContent = d.error || 'Помилка';
                    errEl.style.display = 'block';
                    return;
                }
                document.getElementById('dedupRelModal').style.display = 'none';
                showToast('Зв\'язок створено');
                var g = state.groups[state.activeIdx];
                _markGroupDone(g);
            });
    }

    // ── Merge actions ───────────────────────────────────────────────────────────

    function doMerge() {
        var idx = state.activeIdx;
        if (idx < 0) return;
        var g        = state.groups[idx];
        var targetId = _getTargetId(g);
        if (!targetId) { showToast('Оберіть ціль'); return; }

        var sourceIds = g.members
            .filter(function(m) { return m.id !== targetId && !state.excluded[m.id]; })
            .map(function(m) { return m.id; });

        if (sourceIds.length === 0) {
            showToast('Немає записів для злиття (всі виключені)');
            return;
        }

        if (!confirm('Злити ' + sourceIds.length + ' запис(ів) в обраний контрагент? Це незворотня дія.')) return;

        var fd = new FormData();
        fd.append('target_id', targetId);
        sourceIds.forEach(function(id) { fd.append('source_ids[]', id); });

        fetch('/counterparties/api/merge_dedup', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.ok) { showToast('Помилка: ' + (d.error || '')); return; }

                // ── Update local group state without page reload ────────────────
                var sourceSet = {};
                sourceIds.forEach(function(id) { sourceSet[id] = true; });

                // Find target member and accumulate stats
                var targetMember = null;
                var removedMsgCount = 0, removedOrdCount = 0;
                g.members.forEach(function(m) {
                    if (m.id === targetId) { targetMember = m; }
                    if (sourceSet[m.id])   { removedMsgCount += m.msg_count; removedOrdCount += m.order_count; }
                });
                if (targetMember) {
                    targetMember.msg_count   += removedMsgCount;
                    targetMember.order_count += removedOrdCount;
                }

                // Remove merged sources from member list
                g.members = g.members.filter(function(m) { return !sourceSet[m.id]; });

                // Clear excluded state for removed members
                sourceIds.forEach(function(id) { delete state.excluded[id]; });

                // Show inline result in detail body header
                showToast('Злито ' + d.merged + ' → контрагент #' + d.target_id);
                _showMergeResult(targetId, targetMember ? targetMember.name : '', d.merged);

                // If only 1 member remains → group fully resolved
                if (g.members.length < 2) {
                    _markGroupDone(g);
                } else {
                    // Group still has remaining members — re-render so user can continue
                    state.targetId = targetId;
                    _renderList();
                    _renderDetail();
                }
            });
    }

    function _showMergeResult(targetId, targetName, mergedCount) {
        // Inject a success notice at the top of the detail body
        var body = document.getElementById('dedupDetailBody');
        var existing = body.querySelector('.dedup-merge-result');
        if (existing) existing.remove();

        var div = document.createElement('div');
        div.className = 'dedup-merge-result';
        div.innerHTML = '<span style="color:#16a34a;font-weight:600">✓ Злито ' + mergedCount + ' запис(ів) в:</span> '
            + '<strong>' + _esc(targetName) + '</strong>'
            + ' <a href="/counterparties/view?id=' + targetId + '" target="_blank"'
            + ' style="font-size:12px;color:#3b82f6;text-decoration:none;margin-left:8px">'
            + '↗ Відкрити картку</a>';
        body.insertBefore(div, body.firstChild);

        // Auto-remove after 8 seconds
        setTimeout(function() { if (div.parentNode) div.remove(); }, 8000);
    }

    function skipGroup() {
        var idx = state.activeIdx;
        if (idx < 0) return;
        var g = state.groups[idx];
        state.skipped[_groupKey(g)] = true;
        _saveSkipped();
        state.activeIdx = -1;
        state.excluded  = {};
        document.getElementById('dedupDetailBody').innerHTML = '<div class="dedup-empty">← Виберіть групу зі списку</div>';
        document.getElementById('dedupActions').style.display = 'none';
        _renderList();
    }

    function _markGroupDone(g) {
        var key = _groupKey(g);
        state.skipped[key] = true;
        _saveSkipped();
        state.activeIdx = -1;
        state.excluded  = {};
        document.getElementById('dedupDetailBody').innerHTML = '<div class="dedup-empty">← Виберіть групу зі списку</div>';
        document.getElementById('dedupActions').style.display = 'none';
        // Auto-select next visible group
        var visible = _visibleGroups();
        _renderList();
        if (visible.length > 0) {
            // Find index of first visible group
            var firstVisibleIdx = -1;
            state.groups.forEach(function(grp, i) {
                if (firstVisibleIdx < 0 && !state.skipped[_groupKey(grp)]) {
                    firstVisibleIdx = i;
                }
            });
            if (firstVisibleIdx >= 0) {
                setTimeout(function() { selectGroup(firstVisibleIdx); }, 100);
            }
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    function _esc(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    function _normPhone(p) {
        if (!p) return '';
        return p.replace(/\D/g,'').slice(-9);
    }

    function _typeBadgeClass(type) {
        var map = {
            company:    'badge badge-blue',
            fop:        'badge badge-orange',
            person:     'badge badge-green',
            department: 'badge badge-gray',
            other:      'badge badge-gray',
        };
        return map[type] || 'badge badge-gray';
    }

    // ── Search for specific counterparty ─────────────────────────────────────

    var _searchTimer = null;

    function _initSearch() {
        var input = document.getElementById('dedupSearchInput');
        var drop  = document.getElementById('dedupSearchDrop');

        input.addEventListener('input', function() {
            clearTimeout(_searchTimer);
            var q = input.value.trim();
            if (q.length < 2) { drop.style.display = 'none'; return; }
            _searchTimer = setTimeout(function() { _doSearch(q); }, 250);
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') { drop.style.display = 'none'; }
        });

        document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && !drop.contains(e.target)) {
                drop.style.display = 'none';
            }
        });
    }

    function _doSearch(q) {
        var drop = document.getElementById('dedupSearchDrop');
        fetch('/counterparties/api/search?q=' + encodeURIComponent(q))
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.ok || !d.items || d.items.length === 0) {
                    drop.innerHTML = '<div class="dedup-search-item" style="color:#9ca3af;cursor:default">Нічого не знайдено</div>';
                    drop.style.display = 'block';
                    return;
                }
                var html = '';
                d.items.forEach(function(item) {
                    var meta = [];
                    if (item.phone) meta.push('📞 ' + item.phone);
                    if (item.email) meta.push('✉ ' + item.email);
                    if (item.okpo)  meta.push('ЄДРПОУ: ' + item.okpo);
                    html += '<div class="dedup-search-item" onclick="DEDUP.searchFor(' + item.id + ',\'' + _esc(item.name).replace(/'/g, "\\'") + '\')">'
                          + '<div class="dedup-search-item-name">' + _esc(item.name) + ' <span style="font-weight:400;color:#9ca3af">(' + _esc(item.type_label) + ')</span></div>'
                          + (meta.length ? '<div class="dedup-search-item-meta">' + meta.join(' · ') + '</div>' : '')
                          + '</div>';
                });
                drop.innerHTML = html;
                drop.style.display = 'block';
            });
    }

    function searchFor(cpId, cpName) {
        document.getElementById('dedupSearchDrop').style.display = 'none';
        document.getElementById('dedupSearchInput').value = '';

        // Show loading state
        document.getElementById('dedupListBody').innerHTML = '<div class="dedup-loading">Шукаю дублікати для «' + _esc(cpName) + '»…</div>';
        document.getElementById('dedupStat').textContent = 'Пошук…';
        document.getElementById('dedupDetailBody').innerHTML = '<div class="dedup-empty">Завантаження…</div>';
        document.getElementById('dedupActions').style.display = 'none';
        state.activeIdx = -1;

        fetch('/counterparties/api/find_dedup_for?id=' + cpId)
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.ok) { _showErr(d.error || 'Помилка'); return; }

                if (!d.groups || d.groups.length === 0 || d.groups[0].members.length < 2) {
                    // No duplicates found — show info with "back" button
                    document.getElementById('dedupStat').textContent = 'Пошук по контрагенту';
                    document.getElementById('dedupListBody').innerHTML =
                        '<div style="padding:14px;text-align:center">'
                        + '<div style="color:#16a34a;font-size:14px;font-weight:600;margin-bottom:8px">✓ Дублікатів не знайдено</div>'
                        + '<div style="color:#6b7280;font-size:12px;margin-bottom:12px">Для «' + _esc(cpName) + '» немає збігів по телефону, email чи ЄДРПОУ</div>'
                        + '<button class="btn btn-ghost btn-sm" onclick="DEDUP.reload()">← До всіх дублікатів</button>'
                        + '</div>';
                    document.getElementById('dedupDetailBody').innerHTML = '<div class="dedup-empty">Дублікатів немає</div>';
                    return;
                }

                // Show results — replace group list with search result
                var g = d.groups[0];
                state.groups    = [g];
                state.total     = 1;
                state.loadedOffset = 1;
                state.activeIdx = 0;
                state.excluded  = {};
                state.targetId  = g.members[0].id;

                document.getElementById('dedupStat').innerHTML =
                    'Результат пошуку для «' + _esc(cpName) + '» '
                    + '<button class="btn btn-ghost btn-sm" style="font-size:11px;padding:2px 8px;margin-left:6px" onclick="DEDUP.reload()">← Всі</button>';

                // Render list with single group
                var names = g.members.slice(0, 3).map(function(m) {
                    return m.name.length > 22 ? m.name.substring(0, 20) + '…' : m.name;
                }).join(', ');
                var badges = '';
                g.match_types.forEach(function(t) {
                    var label = t === 'phone' ? '📞 Тел.' : (t === 'email' ? '✉ Email' : '🔢 ЄДРПОУ');
                    badges += '<span class="dedup-badge dedup-badge-' + t + '">' + label + '</span>';
                });
                badges += '<span class="dedup-badge dedup-badge-count">' + g.members.length + ' записи</span>';

                document.getElementById('dedupListBody').innerHTML =
                    '<div class="dedup-group-card active" onclick="DEDUP.selectGroup(0)">'
                    + '<div class="dedup-group-names">' + _esc(names) + '</div>'
                    + '<div class="dedup-group-meta">' + badges + '</div>'
                    + '</div>';

                _renderDetail();
            })
            .catch(function(e) {
                _showErr('Мережева помилка: ' + e.message);
            });
    }

    // Public API
    return {
        init:               init,
        reload:             reload,
        selectGroup:        selectGroup,
        setTarget:          setTarget,
        toggleExclude:      toggleExclude,
        doMerge:            doMerge,
        skipGroup:          skipGroup,
        openRelationModal:  openRelationModal,
        doCreateRelation:   doCreateRelation,
        searchFor:          searchFor,
    };
}());

DEDUP.init();
</script>

<?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>
