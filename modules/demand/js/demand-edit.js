/* ══════════════════════════════════════════════════════════════════════
   demand-edit.js — Edit page logic for Demand (Відвантаження)
   Based on customerorder-edit.js pattern
   ══════════════════════════════════════════════════════════════════════ */

/* ══ INIT STATE ══ */
var _demandId = _PAGE.demandId;
var _version  = _PAGE.version;

var _stateItems = _PAGE.items.map(function(it) {
    var copy = JSON.parse(JSON.stringify(it));
    copy._localId = String(it.id);
    copy.id       = parseInt(it.id) || null;
    copy.quantity         = parseFloat(copy.quantity) || 0;
    copy.price            = parseFloat(copy.price) || 0;
    copy.discount_percent = parseFloat(copy.discount_percent) || 0;
    copy.vat_rate         = parseFloat(copy.vat_rate) || 0;
    copy.sum_row          = parseFloat(copy.sum_row) || 0;
    // Calculate vat_amount for initial state
    copy.vat_amount = (copy.vat_rate > 0)
        ? Math.round((copy.sum_row - copy.sum_row / (1 + copy.vat_rate / 100)) * 100) / 100 : 0;
    return copy;
});
var _state    = { demand: _PAGE.demand, items: _stateItems };
var _original = JSON.parse(JSON.stringify(_state));

/* ══ HELPERS ══ */
function toFloat(v, fallback) {
    var n = parseFloat(String(v || '').replace(',', '.').trim());
    return isNaN(n) ? fallback : n;
}
function fmt2(v) { return (Math.round(v * 100) / 100).toFixed(2); }
function fmt3(v) { return (Math.round(v * 1000) / 1000).toFixed(3); }
function esc(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function stateItemByLocalId(localId) {
    for (var i = 0; i < _state.items.length; i++) {
        if (String(_state.items[i]._localId) === String(localId)) return _state.items[i];
    }
    return null;
}

/* ══ ITEM CALCULATION ══ */
function calcItem(item) {
    var gross   = Math.round(item.quantity * item.price * 100) / 100;
    var discAmt = Math.round(gross * (item.discount_percent || 0) / 100 * 100) / 100;
    item.sum_row         = Math.round((gross - discAmt) * 100) / 100;
    item.discount_amount = discAmt;
    item.vat_amount      = (item.vat_rate > 0)
        ? Math.round((item.sum_row - item.sum_row / (1 + item.vat_rate / 100)) * 100) / 100 : 0;
}

/* ══ SYNC DOM ROW → STATE ══ */
function syncRowToState(tr) {
    var item = stateItemByLocalId(tr.dataset.localId);
    if (!item) return;
    var qInp = tr.querySelector('[data-field="quantity"]');
    var pInp = tr.querySelector('[data-field="price"]');
    var dInp = tr.querySelector('[data-field="discount_percent"]');
    var vSel = tr.querySelector('[data-field="vat_rate"]');
    var sInp = tr.querySelector('[data-field="sum_row"]');
    item.quantity         = toFloat(qInp ? qInp.value : 1, 1);
    item.price            = toFloat(pInp ? pInp.value : 0, 0);
    item.discount_percent = toFloat(dInp ? dInp.value : 0, 0);
    item.vat_rate         = toFloat(vSel ? vSel.value : 0, 0);
    // Back-calculate price from sum if user edited sum directly
    if (tr.dataset.sumChanged === '1') {
        var enteredSum = toFloat(sInp ? sInp.value : 0, 0);
        var q = item.quantity, d = item.discount_percent;
        item.price = (q > 0) ? (enteredSum / q / (1 - d / 100)) : 0;
        item.price = Math.round(item.price * 100) / 100;
        if (pInp) pInp.value = item.price.toFixed(2);
        tr.dataset.sumChanged = '0';
    }
    calcItem(item);
    if (sInp) sInp.value = item.sum_row.toFixed(2);
    renderDocTotals();
    markDirty();
}

/* ══ RENDER TOTALS ══ */
function renderDocTotals() {
    var items = _state.items.filter(function(it) { return !it._deleted; });
    var totalNet = 0, totalVat = 0, totalSum = 0, totalDisc = 0;
    items.forEach(function(it) {
        var sumRow = toFloat(it.sum_row, 0);
        var vatAmt = toFloat(it.vat_amount, 0);
        var gross  = Math.round(toFloat(it.quantity, 0) * toFloat(it.price, 0) * 100) / 100;
        totalDisc += Math.round(gross * toFloat(it.discount_percent, 0) / 100 * 100) / 100;
        totalVat  += vatAmt;
        totalSum  += sumRow;
        totalNet  += sumRow - vatAmt;
    });
    var g = function(id) { return document.getElementById(id); };
    if (g('summary-total-net'))  g('summary-total-net').textContent  = fmt2(totalNet);
    if (g('summary-total-disc')) g('summary-total-disc').textContent = fmt2(totalDisc);
    if (g('summary-total-vat'))  g('summary-total-vat').textContent  = fmt2(totalVat);
    if (g('summary-total-sum'))  g('summary-total-sum').textContent  = fmt2(totalSum);

    renderMargin(totalSum);
}

function renderMargin(totalSum) {
    var marginEl = document.getElementById('summary-margin');
    if (!marginEl) return;

    var costTotal = _PAGE.marginData ? (_PAGE.marginData.cost_total || 0) : 0;
    var overhead  = toFloat((document.getElementById('overheadCosts') || {}).value, 0);
    var delivery  = _PAGE.marginData ? (_PAGE.marginData.delivery_cost_deduct || 0) : 0;

    if (typeof totalSum === 'undefined') {
        totalSum = 0;
        _state.items.filter(function(it) { return !it._deleted; }).forEach(function(it) {
            totalSum += toFloat(it.sum_row, 0);
        });
    }

    var margin    = totalSum - costTotal - overhead - delivery;
    var marginPct = totalSum > 0 ? (margin / totalSum * 100).toFixed(1) : '0.0';

    marginEl.className = 'totals-row-value ' + (margin >= 0 ? 'text-green' : 'text-red');
    marginEl.innerHTML = fmt2(margin) + ' <span style="font-size:11px;font-weight:500;opacity:.7">(' + marginPct + '%)</span>';
}

/* ══ DIRTY FLAG ══ */
function markDirty() {
    var btn = document.getElementById('btnSave');
    if (btn) btn.classList.add('btn-save-dirty');
}
function clearDirty() {
    var btn = document.getElementById('btnSave');
    if (btn) btn.classList.remove('btn-save-dirty');
}

/* ══ TOAST ══ */
function showToast(msg, isError) {
    var el = document.createElement('div');
    el.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);z-index:99999;'
        + 'padding:10px 20px;border-radius:8px;font-size:13px;font-weight:500;font-family:inherit;'
        + 'box-shadow:0 4px 16px rgba(0,0,0,.15);transition:opacity .3s;'
        + (isError ? 'background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;'
                   : 'background:#dcfce7;color:#166534;border:1px solid #86efac;');
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(function() { el.style.opacity = '0'; }, 2800);
    setTimeout(function() { el.remove(); }, 3200);
}

/* ══ BIND ROW EVENTS ══ */
function bindItemRow(tr) {
    var qInp = tr.querySelector('[data-field="quantity"]');
    var pInp = tr.querySelector('[data-field="price"]');
    var dInp = tr.querySelector('[data-field="discount_percent"]');
    var vSel = tr.querySelector('[data-field="vat_rate"]');
    var sInp = tr.querySelector('[data-field="sum_row"]');

    if (sInp) {
        sInp.addEventListener('input', function() { tr.dataset.sumChanged = '1'; markDirty(); });
        sInp.addEventListener('blur',  function() { syncRowToState(tr); });
    }
    if (qInp) qInp.addEventListener('blur',   function() { tr.dataset.sumChanged = '0'; syncRowToState(tr); });
    if (pInp) pInp.addEventListener('blur',   function() { tr.dataset.sumChanged = '0'; syncRowToState(tr); });
    if (vSel) vSel.addEventListener('change', function() { tr.dataset.sumChanged = '0'; syncRowToState(tr); });
    if (dInp) dInp.addEventListener('blur',   function() { tr.dataset.sumChanged = '0'; syncRowToState(tr); });

    // Duplicate button
    var dupBtn = tr.querySelector('.row-menu-item:not(.danger)');
    if (dupBtn) {
        dupBtn.addEventListener('click', function() {
            var item = stateItemByLocalId(tr.dataset.localId);
            if (!item) return;
            var clone = JSON.parse(JSON.stringify(item));
            clone.id = null;
            clone._localId = 'new_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5);
            clone._isNew = true;
            _state.items.push(clone);
            var newTr = document.createElement('tr');
            newTr.setAttribute('data-item-row', '1');
            newTr.setAttribute('data-local-id', clone._localId);
            newTr.setAttribute('data-sum-changed', '0');
            newTr.innerHTML = buildNewRowHtml({
                product_id: clone.product_id,
                product_article: clone.sku || '',
                name: clone.product_name || clone.name || '',
                price: clone.price,
                unit: clone.unit || 'шт',
                weight: clone.weight || 0,
            });
            // Set values from clone
            var qI = newTr.querySelector('[data-field="quantity"]'); if (qI) qI.value = clone.quantity;
            var dI = newTr.querySelector('[data-field="discount_percent"]'); if (dI) dI.value = clone.discount_percent || 0;
            var vS = newTr.querySelector('[data-field="vat_rate"]'); if (vS) vS.value = clone.vat_rate || 0;
            var sI = newTr.querySelector('[data-field="sum_row"]'); if (sI) sI.value = fmt2(clone.sum_row);
            tr.parentNode.insertBefore(newTr, tr.nextSibling);
            bindItemRow(newTr);
            renderDocTotals();
            markDirty();
            // Close menu
            tr.querySelectorAll('.row-menu.open').forEach(function(m) { m.classList.remove('open'); });
        });
    }

    // Delete button
    var delBtn = tr.querySelector('.item-del-btn');
    if (delBtn) {
        delBtn.addEventListener('click', function() {
            if (!confirm('Видалити рядок?')) return;
            tr.querySelectorAll('.row-menu.open').forEach(function(m) { m.classList.remove('open'); });
            var item = stateItemByLocalId(tr.dataset.localId);
            if (item) item._deleted = true;
            tr.remove();
            renderDocTotals();
            markDirty();
        });
    }

    // Price picker dropdown
    if (pInp) {
        var priceDd = tr.querySelector('.price-dd');
        if (priceDd) bindPricePicker(tr, pInp, priceDd);
    }
}

/* ══ PRICE PICKER ══ */
var _priceCache = {};

function bindPricePicker(tr, inp, dd) {
    var _mouseInDd = false;
    inp.addEventListener('focus', function() {
        var pid = parseInt((tr.querySelector('[data-field="product_id"]') || {}).value) || 0;
        if (!pid) return;
        openPriceDd(pid, tr, inp, dd);
    });
    inp.addEventListener('blur', function() {
        if (_mouseInDd) return;
        closePriceDd(dd);
    });
    dd.addEventListener('mouseenter', function() { _mouseInDd = true; });
    dd.addEventListener('mouseleave', function() { _mouseInDd = false; });
}

function openPriceDd(pid, tr, inp, dd) {
    dd.innerHTML = '<div class="price-dd-loading">Завантаження…</div>';
    dd.classList.add('open');
    if (_priceCache[pid]) {
        renderPriceDd(pid, tr, inp, dd, _priceCache[pid]);
        return;
    }
    fetch('/customerorder/api/get_product_prices?product_id=' + pid)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.ok) { dd.innerHTML = ''; dd.classList.remove('open'); return; }
            _priceCache[pid] = data;
            renderPriceDd(pid, tr, inp, dd, data);
        })
        .catch(function() { dd.innerHTML = ''; dd.classList.remove('open'); });
}

function closePriceDd(dd) { dd.classList.remove('open'); dd.innerHTML = ''; }

function fmt2n(v) {
    if (v === null || v === undefined || v === '') return null;
    var n = parseFloat(v);
    return isNaN(n) ? null : n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0');
}

function renderPriceDd(pid, tr, inp, dd, data) {
    var rows = '';
    var hasAny = false;
    function makeSelectable(label, val, badgeText, badgeCls) {
        var fmtVal = fmt2n(val);
        if (fmtVal === null) return '';
        hasAny = true;
        var badge = badgeText ? '<span class="price-dd-badge ' + (badgeCls||'') + '">' + badgeText + '</span>' : '';
        return '<div class="price-dd-row selectable" data-pick="' + parseFloat(val).toFixed(4) + '">'
            + '<span class="price-dd-label">' + label + '</span>' + badge
            + '<span class="price-dd-val">' + fmtVal + '</span></div>';
    }
    rows += makeSelectable('Роздрібна', data.price_sale, '', '');
    if (data.price_act) rows += makeSelectable('Акційна', data.price_act, 'акція', 'act');
    rows += makeSelectable('Оптова', data.price_wholesale, '', '');
    rows += makeSelectable('Дилерська', data.price_dealer, '', '');
    var tiers = data.qty_tiers || [];
    if (tiers.length > 0) {
        if (hasAny) rows += '<hr class="price-dd-sep">';
        rows += '<div class="price-dd-row"><span class="price-dd-label price-dd-info">Знижки від кількості</span></div>';
        for (var i = 0; i < tiers.length; i++) {
            var t = tiers[i];
            var pct = t.discount_percent > 0 ? ' <span class="price-dd-badge">−' + parseFloat(t.discount_percent).toFixed(0) + '%</span>' : '';
            rows += '<div class="price-dd-row"><span class="price-dd-label price-dd-info">від ' + t.qty + ' шт.</span>'
                + pct + '<span class="price-dd-val price-dd-info">' + fmt2n(t.price) + '</span></div>';
        }
    }
    if (!hasAny && tiers.length === 0) { dd.classList.remove('open'); dd.innerHTML = ''; return; }
    dd.innerHTML = rows;
    dd.querySelectorAll('.price-dd-row.selectable').forEach(function(row) {
        row.addEventListener('mousedown', function(e) {
            e.preventDefault();
            inp.value = parseFloat(row.dataset.pick || 0).toFixed(2);
            tr.dataset.sumChanged = '0';
            syncRowToState(tr);
            markDirty();
            closePriceDd(dd);
            inp.focus();
        });
    });
}

/* ══ BUILD NEW ROW HTML ══ */
function buildNewRowHtml(p) {
    var pid = parseInt(p.product_id) || 0;
    var art = esc(p.product_article || '');
    var nm  = esc(p.name || '');
    var pr  = parseFloat(p.price || 0).toFixed(2);
    var wt  = parseFloat(p.weight || 0);
    return '<td class="text-c"><input type="checkbox" class="row-check"></td>'
        + '<td>'
        +   (art ? '<a href="/catalog?selected=' + pid + '" style="font-size:11px;color:#9ca3af;margin-right:4px" target="_blank">' + art + '</a>' : '')
        +   '<span class="prod-name-link">' + nm + '</span>'
        +   '<input type="hidden" data-field="item_id" value="">'
        +   '<input type="hidden" data-field="product_id" value="' + pid + '">'
        +   '<input type="hidden" data-field="weight" value="' + wt + '">'
        + '</td>'
        + '<td class="text-c"><input type="text" data-field="unit" value="' + esc(p.unit||'шт') + '" style="width:42px;text-align:center;" readonly></td>'
        + '<td class="text-r"><input type="text" data-field="quantity" value="1" style="width:72px;text-align:right;"></td>'
        + '<td class="text-r price-cell"><input type="text" data-field="price" value="' + pr + '" style="width:82px;text-align:right;"><div class="price-dd"></div></td>'
        + '<td class="text-c"><select data-field="vat_rate" style="width:82px;text-align:center;"><option value="0">Без ПДВ</option><option value="20">20%</option></select></td>'
        + '<td class="text-r"><input type="text" data-field="discount_percent" value="" placeholder="0" style="width:58px;text-align:right;"></td>'
        + '<td class="text-r"><input type="text" data-field="sum_row" value="' + pr + '" style="width:90px;text-align:right;font-weight:500;"></td>'
        + '<td class="row-actions text-c">'
        +   '<button type="button" class="row-dots" title="Дії">···</button>'
        +   '<div class="row-menu">'
        +     '<button class="row-menu-item" type="button">'
        +       '<svg width="13" height="13" viewBox="0 0 16 16" fill="none"><rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M5 8h6M8 5v6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>'
        +       ' Дублювати'
        +     '</button>'
        +     '<button class="row-menu-item danger item-del-btn" type="button">'
        +       '<svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M2 4h12M6 4V2h4v2M3 4l1 10h8l1-10" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>'
        +       ' Видалити'
        +     '</button>'
        +   '</div>'
        + '</td>';
}

/* ══ SAVE DEMAND (AJAX) ══ */
function saveDemand() {
    var saveBtn = document.getElementById('btnSave');
    if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Збереження…'; }

    // Sync all visible rows to state
    document.querySelectorAll('#positionsTable tbody tr[data-local-id]').forEach(function(tr) {
        syncRowToState(tr);
    });

    function getVal(id) { var el = document.getElementById(id); return el ? el.value : ''; }
    function getChk(id) { var el = document.getElementById(id); return (el && el.checked) ? '1' : '0'; }

    var body = 'id='                  + encodeURIComponent(_demandId)
        + '&version='                 + encodeURIComponent(_version)
        + '&status='                  + encodeURIComponent(getVal('statusHidden'))
        + '&applicable='              + encodeURIComponent(getChk('applicable'))
        + '&description='             + encodeURIComponent(getVal('descriptionField'))
        + '&counterparty_id='         + encodeURIComponent(getVal('counterparty_id'))
        + '&organization_id='         + encodeURIComponent(getVal('organization_id'))
        + '&store_id='                + encodeURIComponent(getVal('store_id'))
        + '&manager_employee_id='     + encodeURIComponent(getVal('manager_employee_id'))
        + '&delivery_method_id='      + encodeURIComponent(getVal('delivery_method_id'))
        + '&overhead_costs='          + encodeURIComponent(getVal('overheadCosts') || '0')
        + '&items='                   + encodeURIComponent(JSON.stringify(_state.items));

    fetch('/demand/api/save', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: body
    }).then(function(r) { return r.json(); }).then(function(res) {
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Зберегти'; }
        if (res.conflict) {
            if (confirm('Документ було змінено іншим користувачем.\nОновити сторінку та втратити незбережені зміни?')) {
                window.location.reload();
            }
            return;
        }
        if (!res.ok) { showToast('Помилка: ' + (res.error || ''), true); return; }
        clearDirty();
        _version = res.version || (_version + 1);

        // Update sync badge
        var d = res.demand || {};
        var ss = d.sync_state || 'synced';
        var tag = document.getElementById('syncTagInline');
        if (tag && _PAGE.syncStyles[ss]) {
            tag.style.cssText = _PAGE.syncStyles[ss];
            tag.textContent = _PAGE.syncLabels[ss] || ss;
        }

        showToast('Збережено ✓');
        // Reload to show fresh data
        window.location.href = '/demand/edit?id=' + _demandId + '&saved=1';
    }).catch(function() {
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Зберегти'; }
        showToast('Помилка з\'єднання', true);
    });
}

/* ══ STATUS DROPDOWN ══ */
(function() {
    var btn    = document.getElementById('statusDdBtn');
    var menu   = document.getElementById('statusDdMenu');
    var hidden = document.getElementById('statusHidden');
    var label  = document.getElementById('statusDdLabel');
    if (!btn) return;
    btn.addEventListener('click', function(e) { e.stopPropagation(); menu.classList.toggle('open'); });
    document.addEventListener('click', function() { menu.classList.remove('open'); });
    menu.querySelectorAll('.status-dd-opt').forEach(function(opt) {
        opt.addEventListener('click', function() {
            hidden.value = opt.dataset.value;
            btn.style.cssText = opt.dataset.style;
            label.textContent = _PAGE.statusLabels[opt.dataset.value] || opt.dataset.value;
            menu.classList.remove('open');
            markDirty();
        });
    });
}());

/* ══ BIND SAVE BUTTON ══ */
document.getElementById('btnSave').addEventListener('click', saveDemand);

/* ══ DIRTY LISTENERS ══ */
(function() {
    var descEl = document.getElementById('descriptionField');
    if (descEl) descEl.addEventListener('input', markDirty);
    var applEl = document.getElementById('applicable');
    if (applEl) applEl.addEventListener('change', markDirty);

    // Dropdowns
    ['organization_id','store_id','manager_employee_id','delivery_method_id'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('change', markDirty);
    });

    // Overhead inputs
    var ohEl = document.getElementById('overheadCosts');
    if (ohEl) {
        ohEl.addEventListener('input', markDirty);
        ohEl.addEventListener('blur', function() { renderMargin(); });
    }
}());

/* ══ BIND EXISTING ROWS ══ */
document.querySelectorAll('#positionsTable tbody tr[data-item-row]').forEach(bindItemRow);

/* ══ ROW DOTS MENU ══ */
(function() {
    document.addEventListener('click', function(e) {
        document.querySelectorAll('.row-menu.open').forEach(function(m) { m.classList.remove('open'); });
    });
    document.querySelectorAll('.row-dots').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var menu = btn.nextElementSibling;
            if (!menu) return;
            document.querySelectorAll('.row-menu.open').forEach(function(m) { if (m !== menu) m.classList.remove('open'); });
            var rect = btn.getBoundingClientRect();
            menu.style.top = rect.bottom + 'px';
            menu.style.left = (rect.left - 100) + 'px';
            menu.classList.toggle('open');
        });
    });
}());

/* ══ CHECKBOX ALL ══ */
(function() {
    var checkAll = document.getElementById('checkAll');
    var bulkDel  = document.getElementById('bulkDeleteBtn');
    if (!checkAll) return;
    checkAll.addEventListener('change', function() {
        document.querySelectorAll('.row-check').forEach(function(cb) { cb.checked = checkAll.checked; });
        updateBulkBar();
    });
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('row-check')) updateBulkBar();
    });
    function updateBulkBar() {
        var cnt = document.querySelectorAll('.row-check:checked').length;
        if (bulkDel) bulkDel.disabled = cnt === 0;
    }
    if (bulkDel) {
        bulkDel.addEventListener('click', function() {
            if (!confirm('Видалити вибрані рядки?')) return;
            document.querySelectorAll('.row-check:checked').forEach(function(cb) {
                var tr = cb.closest('tr[data-local-id]');
                if (!tr) return;
                var item = stateItemByLocalId(tr.dataset.localId);
                if (item) item._deleted = true;
                tr.remove();
            });
            checkAll.checked = false;
            updateBulkBar();
            renderDocTotals();
            markDirty();
        });
    }
}());

/* ══ PRODUCT SEARCH ══ */
(function() {
    var input     = document.getElementById('productSearchInput');
    var resultsEl = document.getElementById('productSearchResults');
    if (!input || !resultsEl) return;

    var _searchTimeout = null;
    var _lastQuery     = '';

    input.addEventListener('input', function() {
        var q = input.value.trim();
        if (q.length < 2) { resultsEl.style.display = 'none'; resultsEl.innerHTML = ''; return; }
        if (q === _lastQuery) return;
        _lastQuery = q;
        clearTimeout(_searchTimeout);
        _searchTimeout = setTimeout(function() { doSearch(q); }, 250);
    });

    input.addEventListener('blur', function() {
        setTimeout(function() { resultsEl.style.display = 'none'; }, 200);
    });

    function doSearch(q) {
        fetch('/customerorder/search_product?q=' + encodeURIComponent(q) + '&limit=15')
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (!res.ok || !res.items || res.items.length === 0) {
                    resultsEl.innerHTML = '<div style="padding:12px;color:#9ca3af;font-size:13px;">Нічого не знайдено</div>';
                    resultsEl.style.display = 'block';
                    return;
                }
                var html = '';
                res.items.forEach(function(p) {
                    html += '<div class="product-search-item" data-product=\'' + esc(JSON.stringify(p)) + '\''
                        + ' style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #f3f4f6;font-size:13px;">'
                        + '<span style="font-size:11px;color:#9ca3af;margin-right:6px;">' + esc(p.product_article || '') + '</span>'
                        + '<span>' + esc(p.name || '') + '</span>'
                        + '<span style="float:right;font-weight:600;font-size:12px;">' + fmt2n(p.price || 0) + '</span>'
                        + '</div>';
                });
                resultsEl.innerHTML = html;
                resultsEl.style.display = 'block';

                resultsEl.querySelectorAll('.product-search-item').forEach(function(el) {
                    el.addEventListener('mousedown', function(e) {
                        e.preventDefault();
                        var p = JSON.parse(el.dataset.product);
                        addProductRow(p);
                        input.value = '';
                        resultsEl.style.display = 'none';
                    });
                });
            })
            .catch(function() {
                resultsEl.innerHTML = '<div style="padding:12px;color:#dc2626;font-size:13px;">Помилка пошуку</div>';
                resultsEl.style.display = 'block';
            });
    }
}());

function addProductRow(p) {
    var localId = 'new_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5);
    var price = parseFloat(p.price || 0);
    var newItem = {
        _localId: localId,
        _isNew: true,
        id: null,
        product_id: parseInt(p.product_id) || 0,
        product_name: p.name || '',
        sku: p.product_article || '',
        unit: p.unit || 'шт',
        quantity: 1,
        price: price,
        discount_percent: 0,
        vat_rate: 0,
        sum_row: price,
        weight: parseFloat(p.weight || 0),
    };
    calcItem(newItem);
    _state.items.push(newItem);

    var tbody = document.querySelector('#positionsTable tbody');
    var addRow = tbody.querySelector('.add-row');
    var emptyRow = tbody.querySelector('.empty-box');
    if (emptyRow) emptyRow.closest('tr').remove();

    var tr = document.createElement('tr');
    tr.setAttribute('data-item-row', '1');
    tr.setAttribute('data-local-id', localId);
    tr.setAttribute('data-sum-changed', '0');
    tr.innerHTML = buildNewRowHtml(p);
    tbody.insertBefore(tr, addRow);
    bindItemRow(tr);

    // Bind dots menu for new row
    var dotsBtn = tr.querySelector('.row-dots');
    if (dotsBtn) {
        dotsBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            var menu = dotsBtn.nextElementSibling;
            if (!menu) return;
            document.querySelectorAll('.row-menu.open').forEach(function(m) { if (m !== menu) m.classList.remove('open'); });
            var rect = dotsBtn.getBoundingClientRect();
            menu.style.top = rect.bottom + 'px';
            menu.style.left = (rect.left - 100) + 'px';
            menu.classList.toggle('open');
        });
    }

    renderDocTotals();
    markDirty();
}

/* ══ COUNTERPARTY PICKER ══ */
(function() {
    var inp    = document.getElementById('cpPickerInput');
    var hidden = document.getElementById('counterparty_id');
    var dd     = document.getElementById('cpPickerDd');
    var clear  = document.getElementById('cpPickerClear');
    var link   = document.getElementById('cpCardLink');
    if (!inp || !hidden || !dd) return;

    var _savedName = inp.value;
    var _savedId   = hidden.value;
    var _picked    = false;

    inp.addEventListener('focus', function() {
        _savedName = inp.value;
        _savedId   = hidden.value;
        _picked    = false;
    });

    inp.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            dd.style.display = 'none';
            clearTimeout(_timeout);
            inp.value    = _savedName;
            hidden.value = _savedId;
            _picked = true; // prevent blur from overriding
            inp.blur();
        }
    });

    var _timeout = null;
    inp.addEventListener('input', function() {
        _picked = false;
        var q = inp.value.trim();
        if (q.length < 2) { dd.style.display = 'none'; return; }
        clearTimeout(_timeout);
        _timeout = setTimeout(function() {
            fetch('/counterparties/api/search?q=' + encodeURIComponent(q) + '&limit=10')
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    var items = res.items || res.rows || [];
                    if (!res.ok || items.length === 0) {
                        dd.innerHTML = '<div class="cp-picker-opt" style="color:#9ca3af">Нічого не знайдено</div>';
                        dd.style.display = 'block';
                        return;
                    }
                    var html = '';
                    items.forEach(function(r) {
                        var typeBadge = '';
                        if (r.type === 'company') typeBadge = '<span class="cp-picker-type-badge t-company">юр.</span>';
                        else if (r.type === 'fop') typeBadge = '<span class="cp-picker-type-badge t-fop">ФОП</span>';
                        else typeBadge = '<span class="cp-picker-type-badge t-person">фіз.</span>';
                        var sub = '';
                        if (r.phone) sub += esc(r.phone);
                        if (r.okpo) sub += (sub ? ' · ' : '') + 'ЄДРПОУ ' + esc(r.okpo);
                        html += '<div class="cp-picker-opt" data-id="' + r.id + '" data-name="' + esc(r.name) + '">'
                            + '<div class="cp-picker-opt-name">' + esc(r.name)
                            + (sub ? '<div class="cp-picker-opt-sub">' + sub + '</div>' : '')
                            + '</div>' + typeBadge + '</div>';
                    });
                    dd.innerHTML = html;
                    dd.style.display = 'block';
                    dd.querySelectorAll('.cp-picker-opt[data-id]').forEach(function(opt) {
                        opt.addEventListener('mousedown', function(e) {
                            e.preventDefault();
                            _picked = true;
                            hidden.value = opt.dataset.id;
                            inp.value = opt.dataset.name;
                            _savedName = inp.value;
                            _savedId   = hidden.value;
                            dd.style.display = 'none';
                            if (clear) clear.style.display = '';
                            if (link) { link.href = '/counterparties/view?id=' + opt.dataset.id; link.style.display = ''; }
                            markDirty();
                        });
                    });
                })
                .catch(function() { dd.style.display = 'none'; });
        }, 250);
    });

    inp.addEventListener('blur', function() {
        setTimeout(function() {
            dd.style.display = 'none';
            if (!_picked) {
                inp.value    = _savedName;
                hidden.value = _savedId;
            }
        }, 200);
    });

    if (clear) {
        clear.addEventListener('click', function() {
            hidden.value = '';
            inp.value = '';
            clear.style.display = 'none';
            if (link) link.style.display = 'none';
            markDirty();
        });
    }

    // Add new counterparty button — opens counterparties list where user can create
    var addBtn = document.getElementById('cpAddBtn');
    if (addBtn) {
        addBtn.addEventListener('click', function() {
            window.open('/counterparties', '_blank');
        });
    }
}());

/* ══ TABS ══ */
var _relDocsLoaded = false;
document.querySelectorAll('.tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
        document.querySelectorAll('.tab-content').forEach(function(c) { c.classList.remove('active'); });
        btn.classList.add('active');
        var tab = document.getElementById('tab-' + btn.dataset.tab);
        if (tab) tab.classList.add('active');
        if (btn.dataset.tab === 'related' && !_relDocsLoaded) {
            _relDocsLoaded = true;
            RelDocsGraph.load(_demandId);
        }
    });
});

/* ══ HISTORY PANEL ══ */
(function() {
    var panel   = document.getElementById('historyPanel');
    var overlay = document.getElementById('historyOverlay');
    var toggle  = document.getElementById('historyToggle');
    var close   = document.getElementById('historyClose');
    if (!panel) return;
    function open() { panel.style.right = '0'; overlay.style.display = 'block'; }
    function closePanel() { panel.style.right = '-520px'; overlay.style.display = 'none'; }
    if (toggle) toggle.addEventListener('click', function(e) { e.preventDefault(); open(); });
    if (close)  close.addEventListener('click', closePanel);
    overlay.addEventListener('click', closePanel);
}());

/* ══ CREATE DOC DROPDOWN ══ */
(function() {
    var wrap = document.getElementById('createDocWrap');
    var btn  = document.getElementById('createDocBtn');
    var menu = document.getElementById('createDocMenu');
    if (!wrap || !btn || !menu) return;

    btn.addEventListener('click', function(e) { e.stopPropagation(); menu.classList.toggle('open'); });
    document.addEventListener('click', function() { menu.classList.remove('open'); });

    menu.querySelectorAll('.create-doc-item').forEach(function(item) {
        item.addEventListener('click', function() {
            menu.classList.remove('open');
            var toType   = item.dataset.toType;
            var linkType = item.dataset.linkType || '';
            if (toType === 'return_logistics') {
                openReturnLogisticsModal(linkType);
            } else {
                createDocument(toType, linkType, null);
            }
        });
    });
}());

function createDocument(toType, linkType, extraParams) {
    var btn = document.getElementById('createDocBtn');
    if (btn) btn.disabled = true;
    var params = new URLSearchParams();
    params.append('demand_id', _demandId);
    params.append('to_type', toType);
    params.append('link_type', linkType || '');
    if (extraParams) {
        Object.keys(extraParams).forEach(function(k) { params.append(k, extraParams[k]); });
    }
    fetch('/demand/api/create_document', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params.toString()
    }).then(function(r) { return r.json(); }).then(function(res) {
        if (btn) btn.disabled = false;
        if (!res.ok) { showToast('Помилка: ' + (res.error || ''), true); return; }
        showToast(res.msg || 'Документ створено ✓');
        _relDocsLoaded = false;
        var relBtn = document.querySelector('[data-tab="related"]');
        if (relBtn) relBtn.click();
    }).catch(function() {
        if (btn) btn.disabled = false;
        showToast('Помилка мережі', true);
    });
}

/* ══ RETURN LOGISTICS MODAL ══ */
function openReturnLogisticsModal(linkType) {
    var modal   = document.getElementById('returnLogisticsModal');
    var selType = document.getElementById('rlReturnType');
    var ttnWrap = document.getElementById('rlTtnWrap');
    var errEl   = document.getElementById('rlError');
    if (!modal) return;
    errEl.style.display = 'none';
    modal.style.display = 'flex';
    function toggleTtn() {
        var v = selType.value;
        ttnWrap.style.display = (v === 'novaposhta_ttn' || v === 'ukrposhta_ttn') ? '' : 'none';
    }
    selType.removeEventListener('change', toggleTtn);
    selType.addEventListener('change', toggleTtn);
    toggleTtn();
    document.getElementById('rlConfirmBtn').onclick = function() {
        errEl.style.display = 'none';
        var returnType  = selType.value;
        var ttnNumber   = document.getElementById('rlTtnNumber').value.trim();
        var description = document.getElementById('rlDescription').value.trim();
        if ((returnType === 'novaposhta_ttn' || returnType === 'ukrposhta_ttn') && ttnNumber === '') {
            errEl.textContent = 'Введіть номер ТТН';
            errEl.style.display = 'block';
            return;
        }
        modal.style.display = 'none';
        createDocument('return_logistics', linkType, {
            return_type: returnType,
            ttn_number:  ttnNumber,
            description: description,
        });
    };
    document.getElementById('rlCancelBtn').onclick =
    document.getElementById('rlModalClose').onclick = function() {
        modal.style.display = 'none';
    };
}

/* ══ RELATED DOCS GRAPH ══ */
var RelDocsGraph = (function() {
    var _currentDemandId = 0;
    var NW = 190, NH = 96, STATUS_H = 22;
    var COL_W = 240, ROW_H = 114, PAD_X = 20, PAD_Y = 30;
    var TYPE_NAME = {
        customerorder: 'Замовлення покупця',
        demand:        'Відвантаження',
        ttn_np:        'ТТН Нова Пошта',
        cashin:        'Касовий ордер',
        paymentin:     'Вхідний платіж',
        salesreturn:   'Повернення покупця',
        overflow:      '…',
    };
    var STATUS_COLOR = _PAGE.statusColorMap;
    var STATUS_LABEL_MAP = _PAGE.statusLabelMap;
    var NS = 'http://www.w3.org/2000/svg';

    function svgEl(tag, attrs) {
        var el = document.createElementNS(NS, tag);
        if (attrs) Object.keys(attrs).forEach(function(k) { el.setAttribute(k, attrs[k]); });
        return el;
    }

    function assignPositions(nodes) {
        var cols = {};
        nodes.forEach(function(n) { var c = n.col || 0; if (!cols[c]) cols[c] = []; cols[c].push(n); });
        var maxRows = 0;
        Object.keys(cols).forEach(function(c) { if (cols[c].length > maxRows) maxRows = cols[c].length; });
        var svgH = Math.max(maxRows * ROW_H + PAD_Y * 2, NH + PAD_Y * 2);
        Object.keys(cols).forEach(function(c) {
            var colNodes = cols[c];
            var colH = colNodes.length * ROW_H - (ROW_H - NH);
            var startY = Math.round((svgH - colH) / 2);
            colNodes.forEach(function(n, i) { n._x = PAD_X + parseInt(c, 10) * COL_W; n._y = startY + i * ROW_H; });
        });
        var maxCol = 0;
        nodes.forEach(function(n) { if ((n.col || 0) > maxCol) maxCol = n.col || 0; });
        return { w: PAD_X * 2 + (maxCol + 1) * COL_W - (COL_W - NW), h: svgH };
    }

    function trunc(s, max) { s = String(s || ''); return s.length > max ? s.slice(0, max - 1) + '…' : s; }
    function fmtMoment(m) {
        if (!m) return '';
        var p = String(m).split(' ')[0].split('-');
        return p.length < 3 ? m : p[2] + '.' + p[1] + '.' + p[0];
    }

    function render(data) {
        var svg  = document.getElementById('reldocs-svg');
        var wrap = document.getElementById('reldocs-graph-wrap');
        while (svg.firstChild) svg.removeChild(svg.firstChild);
        if (!data.nodes || data.nodes.length === 0) {
            document.getElementById('reldocs-empty').style.display = 'block';
            wrap.style.display = 'none';
            return;
        }
        document.getElementById('reldocs-empty').style.display = 'none';
        wrap.style.display = '';
        var nodeMap = {};
        data.nodes.forEach(function(n) { nodeMap[n.id] = n; });
        var dim = assignPositions(data.nodes);
        svg.setAttribute('width', dim.w);
        svg.setAttribute('height', dim.h);
        svg.setAttribute('viewBox', '0 0 ' + dim.w + ' ' + dim.h);
        svg.style.display = 'block';

        var defs = svgEl('defs');
        data.nodes.forEach(function(node) {
            var cp = svgEl('clipPath', { id: 'clip-' + node.id });
            cp.appendChild(svgEl('rect', { x: node._x, y: node._y, width: NW, height: NH, rx: '8', ry: '8' }));
            defs.appendChild(cp);
        });
        svg.appendChild(defs);

        var edgeGroup = svgEl('g', { 'class': 'edges' });
        svg.appendChild(edgeGroup);
        var edgeEls = {};
        data.edges.forEach(function(edge, idx) {
            var src = nodeMap[edge.from], tgt = nodeMap[edge.to];
            if (!src || !tgt) return;
            var x1, y1, x2, y2, d;
            if (src._x < tgt._x) {
                x1 = src._x + NW; y1 = src._y + Math.round(NH / 2);
                x2 = tgt._x; y2 = tgt._y + Math.round(NH / 2);
                var mx = Math.round((x1 + x2) / 2);
                d = 'M' + x1 + ',' + y1 + ' H' + mx + ' V' + y2 + ' H' + x2;
            } else {
                x1 = src._x + Math.round(NW / 2); y1 = src._y + NH;
                x2 = tgt._x + Math.round(NW / 2); y2 = tgt._y + NH;
                var my = Math.max(y1, y2) + 18;
                d = 'M' + x1 + ',' + y1 + ' V' + my + ' H' + x2 + ' V' + y2;
            }
            var path = svgEl('path', { d: d, fill: 'none', stroke: '#c5ccd6', 'stroke-width': '1.5', 'stroke-dasharray': '5,3' });
            edgeGroup.appendChild(path);
            edgeEls[idx] = path;
        });

        var nodeGroup = svgEl('g', { 'class': 'nodes' });
        svg.appendChild(nodeGroup);
        data.nodes.forEach(function(node) {
            var x = node._x, y = node._y;
            var isCurrent = node.current === true;
            var isOverflow = node.type === 'overflow';
            var statusStr = String(node.status || '');
            var statusCol = STATUS_COLOR[statusStr] || '#9ca3af';
            var statusLbl = STATUS_LABEL_MAP[statusStr] || statusStr;
            var bgFill = isCurrent ? '#1a1d23' : '#ffffff';
            var textMain = isCurrent ? '#ffffff' : '#1a1d23';
            var textMuted = isCurrent ? '#9ca3af' : '#6b7280';
            var borderCol = isCurrent ? '#1a1d23' : '#e2e7ef';

            var g = svgEl('g', { 'data-node': node.id, style: 'cursor:' + (node.url ? 'pointer' : 'default') });
            g.appendChild(svgEl('rect', { x: x, y: y, width: NW, height: NH, rx: '8', ry: '8', fill: bgFill, stroke: borderCol, 'stroke-width': '1' }));

            if (isOverflow) {
                var ovt = svgEl('text', { x: x + NW / 2, y: y + NH / 2, 'text-anchor': 'middle', 'dominant-baseline': 'middle', 'font-size': '16', fill: '#9ca3af', 'font-family': 'inherit' });
                ovt.textContent = '…'; g.appendChild(ovt);
            } else {
                var tn = svgEl('text', { x: x + 10, y: y + 16, 'font-size': '10', 'font-weight': '600', fill: textMuted, 'font-family': 'inherit' });
                tn.textContent = trunc(TYPE_NAME[node.type] || node.type, 22); g.appendChild(tn);
                var doneS = {shipped:1,arrived:1,delivered:1,completed:1,paid:1};
                if (doneS[statusStr]) {
                    var ck = svgEl('text', { x: x + NW - 10, y: y + 16, 'text-anchor': 'end', 'font-size': '11', fill: statusCol, 'font-family': 'inherit' });
                    ck.textContent = '✓'; g.appendChild(ck);
                }
                g.appendChild(svgEl('line', { x1: x + 10, y1: y + 22, x2: x + NW - 10, y2: y + 22, stroke: isCurrent ? '#3a3f4a' : '#eaecf0', 'stroke-width': '1' }));
                var numEl = svgEl('text', { x: x + 10, y: y + 36, 'font-size': '11', 'font-weight': '700', fill: textMain, 'font-family': 'inherit' });
                numEl.textContent = node.number ? '№' + trunc(node.number, 14) : '—'; g.appendChild(numEl);
                var ds = fmtMoment(node.moment);
                if (ds) { var de = svgEl('text', { x: x + NW - 10, y: y + 36, 'text-anchor': 'end', 'font-size': '10', fill: textMuted, 'font-family': 'inherit' }); de.textContent = ds; g.appendChild(de); }
                if (node.amount) { var ae = svgEl('text', { x: x + 10, y: y + NH - STATUS_H - 8, 'font-size': '12', 'font-weight': '700', fill: textMain, 'font-family': 'inherit' }); ae.textContent = trunc(node.amount, 18); g.appendChild(ae); }
                var barY = y + NH - STATUS_H;
                var bg = svgEl('g', { 'clip-path': 'url(#clip-' + node.id + ')' });
                bg.appendChild(svgEl('rect', { x: x, y: barY, width: NW, height: STATUS_H, fill: statusCol, opacity: isCurrent ? '0.85' : '1' }));
                if (statusLbl) { var bt = svgEl('text', { x: x + NW / 2, y: barY + STATUS_H / 2 + 1, 'text-anchor': 'middle', 'dominant-baseline': 'middle', 'font-size': '9.5', 'font-weight': '600', fill: '#ffffff', 'font-family': 'inherit' }); bt.textContent = trunc(statusLbl, 24); bg.appendChild(bt); }
                g.appendChild(bg);
            }

            g.addEventListener('mouseenter', function() {
                if (!isCurrent) g.querySelector('rect').setAttribute('fill', '#f5f7ff');
                g.querySelector('rect').setAttribute('stroke-width', '2');
                data.edges.forEach(function(edge, idx) {
                    if (edge.from === node.id || edge.to === node.id) {
                        if (edgeEls[idx]) { edgeEls[idx].setAttribute('stroke', '#6b7280'); edgeEls[idx].setAttribute('stroke-width', '2'); }
                    }
                });
            });
            g.addEventListener('mouseleave', function() {
                g.querySelector('rect').setAttribute('fill', bgFill);
                g.querySelector('rect').setAttribute('stroke-width', '1');
                Object.keys(edgeEls).forEach(function(k) { edgeEls[k].setAttribute('stroke', '#c5ccd6'); edgeEls[k].setAttribute('stroke-width', '1.5'); });
            });
            if (node.url) g.addEventListener('click', function(e) { e.stopPropagation(); window.location.href = node.url; });
            nodeGroup.appendChild(g);
        });
    }

    function load(demandId) {
        if (!demandId) return;
        _currentDemandId = demandId;
        var loading = document.getElementById('reldocs-loading');
        var wrap    = document.getElementById('reldocs-graph-wrap');
        var empty   = document.getElementById('reldocs-empty');
        loading.style.display = 'block';
        wrap.style.display    = 'none';
        empty.style.display   = 'none';
        fetch('/demand/api/get_linked_docs?demand_id=' + demandId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                loading.style.display = 'none';
                if (!data.ok) { empty.style.display = 'block'; empty.textContent = 'Помилка: ' + (data.error || ''); return; }
                wrap.style.display = 'block';
                render(data);
            })
            .catch(function() { loading.style.display = 'none'; empty.style.display = 'block'; empty.textContent = 'Помилка завантаження'; });
    }

    return { load: load };
}());

/* ══ SAVED TOAST (from URL param) ══ */
if (window.location.search.indexOf('saved=1') !== -1) {
    showToast('Збережено ✓');
    if (window.history.replaceState) {
        window.history.replaceState(null, '', '/demand/edit?id=' + _demandId);
    }
}