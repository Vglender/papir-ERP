/* ══ INIT STATE ══ */
var _orderId = _PAGE.orderId;
var _isNew   = _PAGE.isNew;

var _stateItems = _PAGE.items.map(function(it) {
    var copy = JSON.parse(JSON.stringify(it));
    copy._localId = String(it.id);
    copy.id       = parseInt(it.id) || null;
    return copy;
});
var _state    = { order: _PAGE.order, items: _stateItems };
var _original = JSON.parse(JSON.stringify(_state));
var _deliveryMethods = _PAGE.deliveryMethods;

/* ══ HELPERS ══ */
function toFloat(v, fallback) {
    var n = parseFloat(String(v || '').replace(',', '.').trim());
    return isNaN(n) ? fallback : n;
}
function fmt2(v) { return (Math.round(v * 100) / 100).toFixed(2); }
function fmt3(v) { return (Math.round(v * 1000) / 1000).toFixed(3); }

function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

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
    var count = 0, totalQty = 0, totalWeight = 0, totalNet = 0, totalVat = 0, totalSum = 0;
    items.forEach(function(it) {
        count++;
        var qty    = toFloat(it.quantity, 0);
        var sumRow = toFloat(it.sum_row, 0);
        var vatAmt = toFloat(it.vat_amount, 0);
        totalQty    += qty;
        totalWeight += qty * toFloat(it.weight, 0);
        totalVat    += vatAmt;
        totalSum    += sumRow;
        totalNet    += sumRow - vatAmt;
    });
    var g = function(id) { return document.getElementById(id); };
    if (g('summary-total-items'))  g('summary-total-items').textContent  = count;
    if (g('summary-total-qty'))    g('summary-total-qty').textContent    = fmt3(totalQty);
    if (g('summary-total-weight')) g('summary-total-weight').textContent = fmt3(totalWeight);
    if (g('summary-total-net'))    g('summary-total-net').textContent    = fmt2(totalNet);
    if (g('summary-total-vat'))    g('summary-total-vat').textContent    = fmt2(totalVat);
    if (g('summary-total-sum'))    g('summary-total-sum').textContent    = fmt2(totalSum);
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

/* ══ BIND ROW EVENTS ══ */
function bindItemRow(tr) {
    var qInp = tr.querySelector('[data-field="quantity"]');
    var pInp = tr.querySelector('[data-field="price"]');
    var dInp = tr.querySelector('[data-field="discount_percent"]');
    var vSel = tr.querySelector('[data-field="vat_rate"]');
    var sInp = tr.querySelector('[data-field="sum_row"]');

    if (sInp) {
        sInp.addEventListener('input',  function() { tr.dataset.sumChanged = '1'; markDirty(); });
        sInp.addEventListener('blur',   function() { syncRowToState(tr); });
    }
    if (qInp) qInp.addEventListener('blur',   function() { tr.dataset.sumChanged = '0'; syncRowToState(tr); });
    if (pInp) pInp.addEventListener('blur',   function() { tr.dataset.sumChanged = '0'; syncRowToState(tr); });
    if (vSel) vSel.addEventListener('change', function() { tr.dataset.sumChanged = '0'; syncRowToState(tr); });
    if (dInp) dInp.addEventListener('blur',   function() { tr.dataset.sumChanged = '0'; syncRowToState(tr); });

    var delBtn = tr.querySelector('.item-del-btn');
    if (delBtn) {
        delBtn.addEventListener('click', function() {
            if (!confirm('Видалити рядок?')) return;
            // Close dropdown menu if open
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

function positionPriceDd(inp, dd) {
    var rect = inp.getBoundingClientRect();
    dd.style.top  = (rect.bottom + 2) + 'px';
    dd.style.left = '';
    dd.style.right = '';
    // align right edge of dropdown to right edge of input
    var ddW = dd.offsetWidth || 200;
    var left = rect.right - ddW;
    if (left < 4) left = 4;
    dd.style.left = left + 'px';
}

function openPriceDd(pid, tr, inp, dd) {
    dd.innerHTML = '<div class="price-dd-loading">Завантаження…</div>';
    dd.classList.add('open');
    positionPriceDd(inp, dd);

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

function closePriceDd(dd) {
    dd.classList.remove('open');
    dd.innerHTML = '';
}

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
            + '<span class="price-dd-label">' + label + '</span>'
            + badge
            + '<span class="price-dd-val">' + fmtVal + '</span>'
            + '</div>';
    }

    rows += makeSelectable('Роздрібна', data.price_sale, '', '');
    if (data.price_act) {
        rows += makeSelectable('Акційна', data.price_act, 'акція', 'act');
    }
    rows += makeSelectable('Оптова', data.price_wholesale, '', '');
    rows += makeSelectable('Дилерська', data.price_dealer, '', '');

    var tiers = data.qty_tiers || [];
    if (tiers.length > 0) {
        if (hasAny) rows += '<hr class="price-dd-sep">';
        rows += '<div class="price-dd-row"><span class="price-dd-label price-dd-info">Знижки від кількості</span></div>';
        for (var i = 0; i < tiers.length; i++) {
            var t = tiers[i];
            var pct = t.discount_percent > 0 ? ' <span class="price-dd-badge">−' + parseFloat(t.discount_percent).toFixed(0) + '%</span>' : '';
            rows += '<div class="price-dd-row">'
                + '<span class="price-dd-label price-dd-info">від ' + t.qty + ' шт.</span>'
                + pct
                + '<span class="price-dd-val price-dd-info">' + fmt2n(t.price) + '</span>'
                + '</div>';
        }
    }

    if (!hasAny && tiers.length === 0) {
        dd.classList.remove('open');
        dd.innerHTML = '';
        return;
    }

    dd.innerHTML = rows;
    positionPriceDd(inp, dd);

    // Bind click on selectable rows
    dd.querySelectorAll('.price-dd-row.selectable').forEach(function(row) {
        row.addEventListener('mousedown', function(e) {
            e.preventDefault();
            var val = parseFloat(row.dataset.pick || 0).toFixed(2);
            inp.value = val;
            inp.dispatchEvent(new Event('input'));
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
        +   (art ? ('<a href="/catalog?selected=' + pid + '" class="prod-name-link" style="font-size:11px;color:#9ca3af;margin-right:4px" target="_blank">' + art + '</a>') : '')
        +   '<span class="prod-name-link">' + nm + '</span>'
        +   '<input type="hidden" data-field="item_id" value="">'
        +   '<input type="hidden" data-field="product_id" value="' + pid + '">'
        +   '<input type="hidden" data-field="weight" value="' + wt + '">'
        + '</td>'
        + '<td class="text-c"><input type="text" data-field="unit" value="' + esc(p.unit||'') + '" style="width:42px;text-align:center;" readonly></td>'
        + '<td class="text-r"><input type="text" data-field="quantity" value="1" style="width:72px;text-align:right;"></td>'
        + '<td class="text-r price-cell"><input type="text" data-field="price" value="' + pr + '" style="width:82px;text-align:right;"><div class="price-dd"></div></td>'
        + '<td class="text-c"><select data-field="vat_rate" style="width:82px;text-align:center;"><option value="0">Без ПДВ</option><option value="20">20%</option></select></td>'
        + '<td class="text-r"><input type="text" data-field="discount_percent" value="" placeholder="0" style="width:58px;text-align:right;"></td>'
        + '<td class="text-r"><input type="text" data-field="sum_row" value="' + pr + '" style="width:90px;text-align:right;font-weight:500;"></td>'
        + '<td class="text-r">0.000</td>'
        + '<td class="text-r">' + (parseFloat(p.quantity||0)).toFixed(3) + '</td>'
        + '<td class="text-r">' + (parseFloat(p.quantity||0)).toFixed(3) + '</td>'
        + '<td class="text-r">0.000</td>'
        + '<td class="text-r">0.000</td>'
        + '<td class="row-actions text-c">'
        +   '<button type="button" class="row-dots" title="Дії">···</button>'
        +   '<div class="row-menu">'
        +     '<button class="row-menu-item danger item-del-btn" type="button">'
        +       '<svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M2 4h12M6 4V2h4v2M3 4l1 10h8l1-10" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>'
        +       ' Видалити'
        +     '</button>'
        +   '</div>'
        + '</td>';
}

/* ══ SAVE ORDER (AJAX) ══ */
function saveOrder() {
    if (_isNew) {
        // New order — use regular form submit
        var form = document.querySelector('form[action="/customerorder/save"]');
        if (form) form.submit();
        return;
    }

    var saveBtn = document.getElementById('btnSave');
    if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Збереження…'; }

    // Sync all visible rows to state first
    document.querySelectorAll('#positionsTable tbody tr[data-local-id]').forEach(function(tr) {
        syncRowToState(tr);
    });

    function getVal(id) { var el = document.getElementById(id); return el ? el.value : ''; }
    function getChk(id) { var el = document.getElementById(id); return (el && el.checked) ? '1' : '0'; }

    var body = 'order_id='            + encodeURIComponent(_orderId)
        + '&version='                 + encodeURIComponent(parseInt(_state.order.version) || 0)
        + '&status='                  + encodeURIComponent(getVal('statusHidden'))
        + '&organization_id='         + encodeURIComponent(getVal('organization_id'))
        + '&manager_employee_id='     + encodeURIComponent(getVal('manager_employee_id'))
        + '&delivery_method_id='      + encodeURIComponent(getVal('delivery_method_id'))
        + '&payment_method_id='       + encodeURIComponent(getVal('payment_method_id'))
        + '&counterparty_id='         + encodeURIComponent(getVal('counterparty_id'))
        + '&contact_person_id='       + encodeURIComponent(getVal('contact_person_id'))
        + '&organization_bank_account_id=' + encodeURIComponent(getVal('organization_bank_account_id'))
        + '&contract_id='             + encodeURIComponent(getVal('contract_id'))
        + '&project_id='              + encodeURIComponent(getVal('project_id'))
        + '&sales_channel='           + encodeURIComponent(getVal('sales_channel'))
        + '&currency_code='           + encodeURIComponent(getVal('currency_code'))
        + '&store_id='                + encodeURIComponent(getVal('store_id'))
        + '&planned_shipment_at='     + encodeURIComponent(getVal('planned_shipment_at'))
        + '&applicable='              + encodeURIComponent(getChk('applicable'))
        + '&wait_call='               + encodeURIComponent(getChk('wait_call'))
        + '&description='             + encodeURIComponent(getVal('order_description'))
        + '&items='                   + encodeURIComponent(JSON.stringify(_state.items));

    fetch('/counterparties/api/save_order', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: body
    }).then(function(r) { return r.json(); }).then(function(res) {
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Зберегти'; }
        if (res.conflict) {
            if (confirm('Замовлення було змінено іншим користувачем.\nОновити сторінку та втратити незбережені зміни?')) {
                window.location.reload();
            }
            return;
        }
        if (!res.ok) { showToast('Помилка: ' + (res.error || ''), true); return; }
        clearDirty();
        showToast('Збережено ✓');
        // Reload to show fresh totals, history, new item IDs
        window.location.href = '/customerorder/edit?id=' + _orderId + '&saved=1';
    }).catch(function() {
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Зберегти'; }
        showToast('Помилка з\'єднання', true);
    });
}

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
            RelDocsGraph.load(_orderId);
            ShipmentsPanel.load(_orderId);
        }
    });
});

/* ══ RELATED DOCS GRAPH ══ */
var RelDocsGraph = (function() {

    var _currentOrderId = 0;

    // ── visual constants ──────────────────────────────────────────────────────
    var NW = 190, NH = 96, STATUS_H = 22; // node width / height / status bar
    var COL_W = 240;                       // column step
    var ROW_H = 114;                       // row step
    var PAD_X = 20, PAD_Y = 30;           // canvas padding

    var TYPE_NAME = {
        customerorder: 'Замовлення покупця',
        demand:        'Відвантаження',
        ttn_np:        'ТТН Нова Пошта',
        ttn_up:        'ТТН Укрпошта',
        cashin:        'Касовий ордер',
        paymentin:     'Вхідний платіж',
        cashout:       'Видатковий ордер',
        paymentout:    'Вихідний платіж',
        salesreturn:   'Повернення покупця',
        overflow:      '…',
    };

    // status → bottom-bar hex color (generated from StatusColors.php)
    var STATUS_COLOR = _PAGE.statusColorMap;

    // status → label
    var STATUS_LABEL_MAP = _PAGE.statusLabelMap;

    var NS = 'http://www.w3.org/2000/svg';

    function svgEl(tag, attrs) {
        var el = document.createElementNS(NS, tag);
        if (attrs) Object.keys(attrs).forEach(function(k) { el.setAttribute(k, attrs[k]); });
        return el;
    }

    // ── layout ────────────────────────────────────────────────────────────────
    function assignPositions(nodes) {
        // Group by column
        var cols = {};
        nodes.forEach(function(n) {
            var c = n.col || 0;
            if (!cols[c]) cols[c] = [];
            cols[c].push(n);
        });

        // Find max column count to determine SVG height
        var maxRows = 0;
        Object.keys(cols).forEach(function(c) { if (cols[c].length > maxRows) maxRows = cols[c].length; });
        var svgH = Math.max(maxRows * ROW_H + PAD_Y * 2, NH + PAD_Y * 2);

        // For each column assign x (fixed) and y (centered)
        Object.keys(cols).forEach(function(c) {
            var colNodes = cols[c];
            var colH = colNodes.length * ROW_H - (ROW_H - NH);
            var startY = Math.round((svgH - colH) / 2);
            colNodes.forEach(function(n, i) {
                n._x = PAD_X + parseInt(c, 10) * COL_W;
                n._y = startY + i * ROW_H;
            });
        });

        // Determine columns used to size SVG width
        var maxCol = 0;
        nodes.forEach(function(n) { if ((n.col || 0) > maxCol) maxCol = n.col || 0; });
        var svgW = PAD_X * 2 + (maxCol + 1) * COL_W - (COL_W - NW);

        return { w: svgW, h: svgH };
    }

    // ── helpers ───────────────────────────────────────────────────────────────
    function trunc(s, max) {
        s = String(s || '');
        return s.length > max ? s.slice(0, max - 1) + '…' : s;
    }
    function fmtMoment(m) {
        if (!m) return '';
        // "2025-12-31 14:05:00" → "31.12.2025"
        var p = String(m).split(' ')[0].split('-');
        if (p.length < 3) return m;
        return p[2] + '.' + p[1] + '.' + p[0];
    }

    // ── rendering ─────────────────────────────────────────────────────────────
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
        svg.setAttribute('width',  dim.w);
        svg.setAttribute('height', dim.h);
        svg.setAttribute('viewBox', '0 0 ' + dim.w + ' ' + dim.h);
        svg.style.display = 'block';

        // ── defs: clipPaths for rounded status bars ───────────────────────────
        var defs = svgEl('defs');
        data.nodes.forEach(function(node) {
            var cp = svgEl('clipPath', { id: 'clip-' + node.id });
            var cr = svgEl('rect', {
                x: node._x, y: node._y,
                width: NW, height: NH,
                rx: '8', ry: '8',
            });
            cp.appendChild(cr);
            defs.appendChild(cp);
        });
        svg.appendChild(defs);

        // ── edges (orthogonal, dashed) ────────────────────────────────────────
        var edgeGroup = svgEl('g', { 'class': 'edges' });
        svg.appendChild(edgeGroup);
        var edgeEls = {};

        data.edges.forEach(function(edge, idx) {
            var src = nodeMap[edge.from];
            var tgt = nodeMap[edge.to];
            if (!src || !tgt) return;

            var x1, y1, x2, y2, d;
            if (src._x < tgt._x) {
                // forward: right-center → left-center, orthogonal
                x1 = src._x + NW;
                y1 = src._y + Math.round(NH / 2);
                x2 = tgt._x;
                y2 = tgt._y + Math.round(NH / 2);
                var mx = Math.round((x1 + x2) / 2);
                d = 'M' + x1 + ',' + y1
                  + ' H' + mx
                  + ' V' + y2
                  + ' H' + x2;
            } else {
                // backward: bottom-center → bottom-center
                x1 = src._x + Math.round(NW / 2);
                y1 = src._y + NH;
                x2 = tgt._x + Math.round(NW / 2);
                y2 = tgt._y + NH;
                var my = Math.max(y1, y2) + 18;
                d = 'M' + x1 + ',' + y1
                  + ' V' + my
                  + ' H' + x2
                  + ' V' + y2;
            }

            var path = svgEl('path', {
                d: d,
                fill: 'none',
                stroke: '#c5ccd6',
                'stroke-width': '1.5',
                'stroke-dasharray': '5,3',
                'class': 'edge-path',
                'data-edge': idx,
            });
            edgeGroup.appendChild(path);
            edgeEls[idx] = path;
        });

        // ── nodes ─────────────────────────────────────────────────────────────
        var nodeGroup = svgEl('g', { 'class': 'nodes' });
        svg.appendChild(nodeGroup);

        data.nodes.forEach(function(node) {
            var x = node._x, y = node._y;
            var isCurrent  = node.current  === true;
            var isOverflow = node.type === 'overflow';

            var statusStr  = String(node.status || '');
            var statusCol  = STATUS_COLOR[statusStr] || '#9ca3af';
            var statusLbl  = STATUS_LABEL_MAP[statusStr] || statusStr;

            var bgFill     = isCurrent ? '#1a1d23' : '#ffffff';
            var textMain   = isCurrent ? '#ffffff' : '#1a1d23';
            var textMuted  = isCurrent ? '#9ca3af' : '#6b7280';
            var borderCol  = isCurrent ? '#1a1d23' : '#e2e7ef';

            var g = svgEl('g', {
                'data-node': node.id,
                style: 'cursor:' + (node.url ? 'pointer' : 'default'),
            });

            // Card background
            var rect = svgEl('rect', {
                x: x, y: y,
                width: NW, height: NH,
                rx: '8', ry: '8',
                fill: bgFill,
                stroke: borderCol,
                'stroke-width': '1',
                'class': 'node-rect',
            });
            g.appendChild(rect);

            if (isOverflow) {
                // Simple overflow node
                var ovt = svgEl('text', {
                    x: x + NW / 2, y: y + NH / 2,
                    'text-anchor': 'middle',
                    'dominant-baseline': 'middle',
                    'font-size': '16',
                    fill: '#9ca3af',
                    'font-family': 'inherit',
                });
                ovt.textContent = '…';
                g.appendChild(ovt);
            } else {
                // ── top row: type name + checkmark ────────────────────────────
                var typeName = trunc(TYPE_NAME[node.type] || node.type, 22);
                var tnEl = svgEl('text', {
                    x: x + 10, y: y + 16,
                    'font-size': '10',
                    'font-weight': '600',
                    fill: textMuted,
                    'font-family': 'inherit',
                });
                tnEl.textContent = typeName;
                g.appendChild(tnEl);

                // checkmark if has status 'shipped','arrived','delivered','completed','paid'
                var doneStatuses = {shipped:1,arrived:1,delivered:1,completed:1,paid:1};
                if (doneStatuses[statusStr]) {
                    var ck = svgEl('text', {
                        x: x + NW - 10, y: y + 16,
                        'text-anchor': 'end',
                        'font-size': '11',
                        fill: statusCol,
                        'font-family': 'inherit',
                    });
                    ck.textContent = '✓';
                    g.appendChild(ck);
                }

                // ── divider ───────────────────────────────────────────────────
                var div = svgEl('line', {
                    x1: x + 10, y1: y + 22,
                    x2: x + NW - 10, y2: y + 22,
                    stroke: isCurrent ? '#3a3f4a' : '#eaecf0',
                    'stroke-width': '1',
                });
                g.appendChild(div);

                // ── number ────────────────────────────────────────────────────
                var numStr = node.number ? '№' + trunc(node.number, 14) : '—';
                var numEl = svgEl('text', {
                    x: x + 10, y: y + 36,
                    'font-size': '11',
                    'font-weight': '700',
                    fill: textMain,
                    'font-family': 'inherit',
                });
                numEl.textContent = numStr;
                g.appendChild(numEl);

                // ── date ──────────────────────────────────────────────────────
                var dateStr = fmtMoment(node.moment);
                if (dateStr) {
                    var dateEl = svgEl('text', {
                        x: x + NW - 10, y: y + 36,
                        'text-anchor': 'end',
                        'font-size': '10',
                        fill: textMuted,
                        'font-family': 'inherit',
                    });
                    dateEl.textContent = dateStr;
                    g.appendChild(dateEl);
                }

                // ── amount ────────────────────────────────────────────────────
                if (node.amount) {
                    var amtEl = svgEl('text', {
                        x: x + 10, y: y + NH - STATUS_H - 8,
                        'font-size': '12',
                        'font-weight': '700',
                        fill: textMain,
                        'font-family': 'inherit',
                    });
                    amtEl.textContent = trunc(node.amount, 18);
                    g.appendChild(amtEl);
                }

                // ── status bar (bottom, clipped to card corners) ───────────────
                var barY = y + NH - STATUS_H;
                var barGroup = svgEl('g', { 'clip-path': 'url(#clip-' + node.id + ')' });

                var bar = svgEl('rect', {
                    x: x, y: barY,
                    width: NW, height: STATUS_H,
                    fill: statusCol,
                    opacity: isCurrent ? '0.85' : '1',
                });
                barGroup.appendChild(bar);

                if (statusLbl) {
                    var barTxt = svgEl('text', {
                        x: x + NW / 2, y: barY + STATUS_H / 2 + 1,
                        'text-anchor': 'middle',
                        'dominant-baseline': 'middle',
                        'font-size': '9.5',
                        'font-weight': '600',
                        fill: '#ffffff',
                        'font-family': 'inherit',
                    });
                    barTxt.textContent = trunc(statusLbl, 24);
                    barGroup.appendChild(barTxt);
                }
                g.appendChild(barGroup);
            }

            // ── hover & click ─────────────────────────────────────────────────
            g.addEventListener('mouseenter', function() {
                if (!isCurrent) rect.setAttribute('fill', '#f5f7ff');
                rect.setAttribute('stroke-width', '2');
                data.edges.forEach(function(edge, idx) {
                    if (edge.from === node.id || edge.to === node.id) {
                        if (edgeEls[idx]) {
                            edgeEls[idx].setAttribute('stroke', '#6b7280');
                            edgeEls[idx].setAttribute('stroke-width', '2');
                        }
                    }
                });
            });

            g.addEventListener('mouseleave', function() {
                rect.setAttribute('fill', bgFill);
                rect.setAttribute('stroke-width', '1');
                Object.keys(edgeEls).forEach(function(k) {
                    edgeEls[k].setAttribute('stroke', '#c5ccd6');
                    edgeEls[k].setAttribute('stroke-width', '1.5');
                });
            });

            (function(n) {
                g.addEventListener('click', function(e) {
                    e.stopPropagation();
                    if (n.type === 'ttn_np' && n.entity_id && typeof TtnDetailModal !== 'undefined') {
                        TtnDetailModal.open(n.entity_id);
                    } else if (n.url) {
                        window.location.href = n.url;
                    }
                });
            }(node));

            // ── unlink button (×) for non-current, non-overflow nodes ─────────
            if (!isCurrent && !isOverflow) {
                var unlinkBtn = svgEl('g', {
                    'class': 'node-unlink-btn',
                    style: 'cursor:pointer; opacity:0; transition:opacity .15s;',
                });
                var unlinkCircle = svgEl('circle', {
                    cx: x + NW - 8, cy: y + 8, r: '7',
                    fill: '#ef4444',
                });
                var unlinkX = svgEl('text', {
                    x: x + NW - 8, y: y + 8,
                    'text-anchor': 'middle',
                    'dominant-baseline': 'middle',
                    'font-size': '9',
                    'font-weight': '700',
                    fill: '#fff',
                    'font-family': 'inherit',
                    style: 'pointer-events:none;',
                });
                unlinkX.textContent = '×';
                unlinkBtn.appendChild(unlinkCircle);
                unlinkBtn.appendChild(unlinkX);
                g.appendChild(unlinkBtn);

                // Show on node hover
                g.addEventListener('mouseenter', function() { unlinkBtn.style.opacity = '1'; });
                g.addEventListener('mouseleave', function() { unlinkBtn.style.opacity = '0'; });

                (function(n, ub) {
                    ub.addEventListener('click', function(e) {
                        e.stopPropagation();
                        if (!confirm('Відв\'язати документ від замовлення?')) return;
                        var body = 'order_id=' + _currentOrderId
                                 + '&doc_type=' + encodeURIComponent(n.type)
                                 + '&doc_id='   + encodeURIComponent(n.entity_id);
                        fetch('/customerorder/api/unlink_document', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: body
                        }).then(function(r) { return r.json(); }).then(function(res) {
                            if (!res.ok) { alert('Помилка: ' + (res.error || '')); return; }
                            _relDocsLoaded = false;
                            RelDocsGraph.load(_currentOrderId);
                            showToast('Документ відв\'язано');
                        }).catch(function() { alert('Помилка з\'єднання'); });
                    });
                }(node, unlinkBtn));
            }

            nodeGroup.appendChild(g);
        });

        // legend removed
    }

    // ── public API ────────────────────────────────────────────────────────────
    function load(orderId) {
        if (!orderId) return;
        _currentOrderId = orderId;
        var loading = document.getElementById('reldocs-loading');
        var wrap    = document.getElementById('reldocs-graph-wrap');
        var empty   = document.getElementById('reldocs-empty');

        loading.style.display = 'block';
        wrap.style.display    = 'none';
        empty.style.display   = 'none';

        fetch('/customerorder/api/get_linked_docs?order_id=' + orderId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                loading.style.display = 'none';
                if (!data.ok) {
                    empty.style.display = 'block';
                    empty.textContent = 'Помилка: ' + (data.error || '');
                    return;
                }
                wrap.style.display = 'block';
                render(data);
                // update badge with actual count (nodes minus the order itself)
                var cnt = (data.nodes || []).filter(function(n){ return !n.current && n.type !== 'overflow'; }).length;
                var badge = document.getElementById('relatedDocsBadge');
                if (badge) {
                    badge.textContent = cnt;
                    badge.style.display = cnt > 0 ? '' : 'none';
                } else if (cnt > 0) {
                    var tabBtn = document.querySelector('.tab-btn[data-tab="related"]');
                    if (tabBtn) {
                        var sp = document.createElement('span');
                        sp.className = 'tab-badge'; sp.id = 'relatedDocsBadge';
                        sp.textContent = cnt;
                        tabBtn.appendChild(sp);
                    }
                }
            })
            .catch(function() {
                loading.style.display = 'none';
                empty.style.display   = 'block';
                empty.textContent     = 'Помилка завантаження';
            });
    }

    return { load: load, reload: function(id) { _relDocsLoaded = false; load(id); } };
}());

/* ══ LINK DOCUMENT MODAL ══ */
(function() {
    var orderId  = _orderId;
    var modal    = document.getElementById('linkDocModal');
    if (!modal) return;

    var openBtn  = document.getElementById('linkDocBtn');
    var closeBtn = document.getElementById('linkDocModalClose');
    var cancelBtn= document.getElementById('ldCancelBtn');
    var searchBtn= document.getElementById('ldSearchBtn');
    var linkBtn  = document.getElementById('ldLinkBtn');
    var checkAll = document.getElementById('ldCheckAll');
    var tbody    = document.getElementById('ldResultsTbody');
    var table    = document.getElementById('ldResultsTable');
    var emptyEl  = document.getElementById('ldResultsEmpty');
    var loadEl   = document.getElementById('ldResultsLoading');
    var errorEl  = document.getElementById('ldError');
    var countEl  = document.getElementById('ldSelectedCount');

    var ldCpInput = document.getElementById('ldCounterparty');
    var ldDocTypeSelect = document.getElementById('ldDocType');
    var cpNameForLink = _PAGE.cpNameForLink;

    function openModal() {
        modal.style.display = 'flex';
        errorEl.style.display = 'none';
        emptyEl.style.display = 'none';
        loadEl.style.display  = 'none';
        table.style.display   = 'none';
        linkBtn.disabled = true;
        countEl.textContent  = '';
        ldCpInput.value = cpNameForLink;
        updateCpPlaceholder();
    }
    var ldTtnNumWrap = document.getElementById('ldTtnNumWrap');
    var ldTtnNumber  = document.getElementById('ldTtnNumber');
    function updateCpPlaceholder() {
        var dt = ldDocTypeSelect.value;
        var isTtn = (dt === 'ttn_np' || dt === 'ttn_up');
        ldTtnNumWrap.style.display = isTtn ? '' : 'none';
        if (!isTtn) ldTtnNumber.value = '';
    }
    ldDocTypeSelect.addEventListener('change', updateCpPlaceholder);
    function closeModal() { modal.style.display = 'none'; }

    if (openBtn) openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e) { if (e.target === modal) closeModal(); });

    function updateSelection() {
        var checked = tbody.querySelectorAll('input[type=checkbox]:checked');
        var n = checked.length;
        linkBtn.disabled = n === 0;
        countEl.textContent = n > 0 ? ('Обрано: ' + n) : '';
        checkAll.indeterminate = false;
        var all = tbody.querySelectorAll('input[type=checkbox]');
        if (n === 0) checkAll.checked = false;
        else if (n === all.length) checkAll.checked = true;
        else { checkAll.checked = false; checkAll.indeterminate = true; }
    }

    checkAll.addEventListener('change', function() {
        tbody.querySelectorAll('input[type=checkbox]').forEach(function(cb) { cb.checked = checkAll.checked; });
        updateSelection();
    });

    function fmtDate(m) {
        if (!m) return '—';
        var p = String(m).split(' ')[0].split('-');
        return p.length >= 3 ? p[2] + '.' + p[1] + '.' + p[0] : m;
    }
    function h(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function doSearch() {
        var docType = document.getElementById('ldDocType').value;
        var dateFrom= document.getElementById('ldDateFrom').value;
        var dateTo  = document.getElementById('ldDateTo').value;
        var cpQ     = document.getElementById('ldCounterparty').value;

        emptyEl.style.display = 'none';
        table.style.display   = 'none';
        errorEl.style.display = 'none';
        loadEl.style.display  = 'block';
        linkBtn.disabled = true;
        checkAll.checked = false;
        countEl.textContent = '';

        var ttnNum = ldTtnNumber ? ldTtnNumber.value.trim() : '';
        var qs = 'order_id=' + orderId
               + '&doc_type=' + encodeURIComponent(docType)
               + '&date_from=' + encodeURIComponent(dateFrom)
               + '&date_to='   + encodeURIComponent(dateTo)
               + '&cp_q='      + encodeURIComponent(cpQ)
               + '&ttn_num='   + encodeURIComponent(ttnNum);

        fetch('/customerorder/api/search_linkable_docs?' + qs)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                loadEl.style.display = 'none';
                if (!data.ok) { errorEl.textContent = data.error || 'Помилка'; errorEl.style.display='block'; return; }
                if (!data.rows || data.rows.length === 0) { emptyEl.style.display = 'block'; return; }
                tbody.innerHTML = '';
                data.rows.forEach(function(row) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = '<td><input type="checkbox" data-id="' + h(row.id) + '" data-type="' + h(row.type) + '"></td>'
                        + '<td>' + h(row.type_name) + '</td>'
                        + '<td style="font-weight:600;">' + h(row.number || ('—')) + '</td>'
                        + '<td style="color:var(--text-muted);">' + fmtDate(row.moment) + '</td>'
                        + '<td>' + h(row.counterparty || '—') + '</td>'
                        + '<td style="text-align:right; font-weight:600;">' + h(row.amount || '—') + '</td>';
                    tbody.appendChild(tr);
                    tr.querySelector('input[type=checkbox]').addEventListener('change', updateSelection);
                });
                table.style.display = '';
            })
            .catch(function() { loadEl.style.display='none'; errorEl.textContent='Помилка завантаження'; errorEl.style.display='block'; });
    }

    searchBtn.addEventListener('click', doSearch);
    document.getElementById('ldDocType').addEventListener('change', function() {
        emptyEl.style.display='none'; table.style.display='none'; errorEl.style.display='none'; loadEl.style.display='none';
        linkBtn.disabled=true; checkAll.checked=false; countEl.textContent=''; tbody.innerHTML='';
    });

    linkBtn.addEventListener('click', function() {
        var checked = tbody.querySelectorAll('input[type=checkbox]:checked');
        if (checked.length === 0) return;
        var docs = [];
        checked.forEach(function(cb) { docs.push({ type: cb.dataset.type, id: cb.dataset.id }); });

        linkBtn.disabled = true;
        linkBtn.textContent = 'Зберігаємо…';

        var body = 'order_id=' + orderId + '&docs=' + encodeURIComponent(JSON.stringify(docs));
        fetch('/customerorder/api/link_documents', { method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                linkBtn.textContent = 'Прив\'язати';
                if (!data.ok) { errorEl.textContent = data.error || 'Помилка'; errorEl.style.display='block'; linkBtn.disabled=false; return; }
                closeModal();
                _relDocsLoaded = false;
                RelDocsGraph.load(orderId);
                showToast('Прив\'язано: ' + data.linked + ' документ(ів)');
            })
            .catch(function() { linkBtn.textContent='Прив\'язати'; linkBtn.disabled=false; errorEl.textContent='Помилка'; errorEl.style.display='block'; });
    });
}());

/* ══ ROW MENUS — fixed positioning ══ */
document.addEventListener('click', function(e) {
    var dotsBtn = e.target.closest('.row-dots');
    if (dotsBtn) {
        e.stopPropagation();
        var menu = dotsBtn.nextElementSibling;
        var isOpen = menu.classList.contains('open');
        document.querySelectorAll('.row-menu.open').forEach(function(m) { m.classList.remove('open'); });
        if (!isOpen) {
            var rect   = dotsBtn.getBoundingClientRect();
            var menuW  = 160;
            var left   = rect.right - menuW;
            if (left < 8) left = 8;
            menu.classList.add('open');
            var spaceBelow = window.innerHeight - rect.bottom;
            menu.style.top   = (spaceBelow < 140 ? (rect.top + window.scrollY - menu.offsetHeight - 4) : (rect.bottom + window.scrollY + 4)) + 'px';
            menu.style.left  = left + 'px';
            menu.style.width = menuW + 'px';
        }
        return;
    }
    document.querySelectorAll('.row-menu.open').forEach(function(m) { m.classList.remove('open'); });
});

/* ══ CHECKBOXES ══ */
var checkAll = document.getElementById('checkAll');
if (checkAll) {
    checkAll.addEventListener('change', function() {
        document.querySelectorAll('.row-check').forEach(function(cb) {
            cb.checked = checkAll.checked;
            cb.closest('tr').classList.toggle('row-selected', checkAll.checked);
        });
        updateBulkBar();
    });
}
document.querySelectorAll('.row-check').forEach(function(cb) {
    cb.addEventListener('change', function() {
        cb.closest('tr').classList.toggle('row-selected', cb.checked);
        updateBulkBar();
    });
});
function updateBulkBar() {
    var any = document.querySelectorAll('.row-check:checked').length > 0;
    var bDel = document.getElementById('bulkDeleteBtn');
    var bDup = document.getElementById('bulkDuplicateBtn');
    if (bDel) bDel.disabled = !any;
    if (bDup) bDup.disabled = !any;
}

/* ══ STATUS CUSTOM DROPDOWN ══ */
(function() {
    var _statusColors = _PAGE.statusInlineStyles;
    var dd     = document.getElementById('statusDd');
    var btn    = document.getElementById('statusDdBtn');
    var menu   = document.getElementById('statusDdMenu');
    var label  = document.getElementById('statusDdLabel');
    var hidden = document.getElementById('statusHidden');
    if (!dd || !btn || !menu || !hidden) return;

    function closeMenu() { menu.classList.remove('open'); }
    function openMenu()  { menu.classList.add('open'); }

    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        if (menu.classList.contains('open')) { closeMenu(); } else { openMenu(); }
    });

    menu.addEventListener('click', function(e) {
        var opt = e.target.closest('.status-dd-opt');
        if (!opt) return;
        var val   = opt.getAttribute('data-value');
        var style = opt.getAttribute('data-style');
        hidden.value      = val;
        label.textContent = opt.textContent.trim();
        btn.style.cssText = style;
        closeMenu();
        if (window._state && _state.order) _state.order.status = val;
        markDirty();
    });

    document.addEventListener('click', function(e) {
        if (!dd.contains(e.target)) closeMenu();
    });
}());

/* ══ HISTORY PANEL ══ */
var historyToggle = document.getElementById('historyToggle');
if (historyToggle) {
    historyToggle.addEventListener('click', function(e) {
        e.preventDefault();
        if (_orderId) HistoryModal.open('customerorder', _orderId);
    });
}

/* ══ SAVE BUTTON ══ */
var btnSave = document.getElementById('btnSave');
if (btnSave) btnSave.addEventListener('click', saveOrder);

/* ══ DIRTY FLAG ON HEADER FIELDS ══ */
['organization_id','manager_employee_id','delivery_method_id','payment_method_id',
 'counterparty_id','contact_person_id','organization_bank_account_id','contract_id',
 'project_id','sales_channel','currency_code','store_id','planned_shipment_at',
 'status','applicable','order_description'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.addEventListener('change', markDirty);
});
var descEl = document.getElementById('order_description');
if (descEl) descEl.addEventListener('input', markDirty);

/* ══ PLANNED DATE ICON CLICK ══ */
var _pdIcon = document.getElementById('plannedDateIcon');
var _pdInput = document.getElementById('planned_shipment_at');
if (_pdIcon && _pdInput) {
    _pdIcon.addEventListener('click', function() {
        if (_pdInput.showPicker) { _pdInput.showPicker(); } else { _pdInput.focus(); _pdInput.click(); }
    });
}

/* ══ BANK ACCOUNTS AJAX ══ */
var orgSelect  = document.getElementById('organization_id');
var bankSelect = document.getElementById('organization_bank_account_id');
if (orgSelect && bankSelect) {
    orgSelect.addEventListener('change', function() {
        var orgId = this.value;
        if (!orgId) { bankSelect.innerHTML = '<option value="">— Обрати рахунок —</option>'; return; }
        bankSelect.innerHTML = '<option value="">Завантаження...</option>';
        bankSelect.disabled  = true;
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/customerorder/ajax_get_bank_accounts?organization_id=' + orgId + '&t=' + Date.now(), true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;
            bankSelect.disabled = false;
            if (xhr.status === 200) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (!resp.ok) throw new Error(resp.error || '');
                    bankSelect.innerHTML = '<option value="">— Обрати рахунок —</option>';
                    (resp.accounts || []).forEach(function(acc) {
                        var opt  = document.createElement('option');
                        opt.value = acc.id;
                        var txt  = acc.iban;
                        if (acc.account_name) txt += ' — ' + acc.account_name;
                        txt += ' (' + acc.currency_code + ')';
                        if (acc.is_default == 1) { txt += ' [Основний]'; opt.selected = true; }
                        opt.text = txt;
                        bankSelect.appendChild(opt);
                    });
                } catch(ex) { bankSelect.innerHTML = '<option value="">— Помилка —</option>'; }
            } else { bankSelect.innerHTML = '<option value="">— Помилка сервера —</option>'; }
        };
        xhr.send();
    });
}

/* ══ BIND EXISTING ROWS + INIT TOTALS ══ */
document.querySelectorAll('#positionsTable tbody tr[data-local-id]').forEach(function(tr) {
    bindItemRow(tr);
});
renderDocTotals();

/* ══ PRODUCT SEARCH (state-based, no reload) ══ */
(function() {
    var input   = document.getElementById('productSearchInput');
    var results = document.getElementById('productSearchResults');
    if (!input || !results) return;
    var timer = null;
    var _lastList = [];

    function closeDd() { results.style.display = 'none'; results.innerHTML = ''; _lastList = []; }

    input.addEventListener('input', function() {
        var q = input.value.trim();
        clearTimeout(timer);
        if (q.length < 2) { closeDd(); return; }
        timer = setTimeout(function() {
            fetch('/customerorder/search_product?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    _lastList = (data.ok && data.items) ? data.items : [];
                    if (!_lastList.length) {
                        results.innerHTML = '<div style="padding:10px 12px;color:#666;font-size:12.5px;">Нічого не знайдено</div>';
                        results.style.display = 'block';
                        return;
                    }
                    var html = '';
                    _lastList.forEach(function(p) {
                        html += '<div class="product-search-item" data-pid="' + p.product_id + '" style="padding:9px 12px;border-bottom:1px solid #f0f2f5;cursor:pointer;font-size:12.5px;">'
                            + '<span style="font-weight:500;">' + esc(p.name || '') + '</span><br>'
                            + '<span style="font-size:11.5px;color:#9ca3af;">Артикул: ' + esc(p.product_article || '') + ' · Ціна: ' + (p.price || 0) + ' · Залишок: ' + (p.quantity || 0) + ' · ' + esc(p.unit || '—') + '</span>'
                            + '</div>';
                    });
                    results.innerHTML = html;
                    results.style.display = 'block';
                })
                .catch(function() {
                    results.innerHTML = '<div style="padding:10px 12px;color:#b91c1c;font-size:12.5px;">Помилка пошуку</div>';
                    results.style.display = 'block';
                });
        }, 250);
    });

    results.addEventListener('mousedown', function(e) {
        var row = e.target.closest('.product-search-item');
        if (!row) return;
        e.preventDefault();
        var pid = row.dataset.pid;
        var product = null;
        for (var j = 0; j < _lastList.length; j++) {
            if (String(_lastList[j].product_id) === String(pid)) { product = _lastList[j]; break; }
        }
        closeDd();
        input.value = '';
        if (!product) return;

        // Add to state
        var localId = 'n' + Date.now();
        var newItem = {
            _localId:          localId,
            id:                null,
            product_id:        parseInt(product.product_id) || null,
            product_name:      product.name || '',
            name:              product.name || '',
            sku:               product.product_article || '',
            article:           product.product_article || '',
            unit:              product.unit || '',
            quantity:          1,
            price:             parseFloat(product.price) || 0,
            discount_percent:  0,
            vat_rate:          0,
            stock_quantity:    parseFloat(product.quantity) || 0,
            shipped_quantity:  0,
            reserved_quantity: 0,
            weight:            parseFloat(product.weight) || 0,
            sum_row: 0, discount_amount: 0, vat_amount: 0
        };
        calcItem(newItem);
        _state.items.push(newItem);

        // Insert DOM row
        var tbody = document.querySelector('#positionsTable tbody');
        if (tbody) {
            var tr = document.createElement('tr');
            tr.dataset.itemRow  = '1';
            tr.dataset.localId  = localId;
            tr.dataset.sumChanged = '0';
            tr.innerHTML = buildNewRowHtml(product);
            tbody.appendChild(tr);
            bindItemRow(tr);
            tr.scrollIntoView({block: 'nearest'});
            var qtyInp = tr.querySelector('[data-field="quantity"]');
            if (qtyInp) { qtyInp.focus(); qtyInp.select(); }
        }
        renderDocTotals();
        markDirty();
    });

    // Enter key → pick first result
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            var first = results.querySelector('.product-search-item');
            if (first) first.dispatchEvent(new MouseEvent('mousedown', {bubbles: true}));
        }
        if (e.key === 'Escape') { closeDd(); input.value = ''; }
    });

    document.addEventListener('click', function(e) {
        if (!results.contains(e.target) && e.target !== input) closeDd();
    });

    // Hover effect
    document.addEventListener('mouseover', function(e) {
        var item = e.target.closest('.product-search-item');
        if (item) item.style.background = '#f8f9fb';
    });
    document.addEventListener('mouseout', function(e) {
        var item = e.target.closest('.product-search-item');
        if (item) item.style.background = '';
    });
}());

/* ══ COUNTERPARTY PICKER ══ */
function makeCpPicker(inputId, hiddenId, ddId, clearId, cpType, onPick) {
    var inp    = document.getElementById(inputId);
    var hidden = document.getElementById(hiddenId);
    var dd     = document.getElementById(ddId);
    var clear  = document.getElementById(clearId);
    if (!inp || !hidden || !dd) return;

    var timer = null;
    var _list = [];
    var _savedName = inp.value;
    var _savedId   = hidden.value;
    var _picked    = false;

    function closeDd() { dd.style.display = 'none'; dd.innerHTML = ''; _list = []; }

    function pick(id, name, phone) {
        _picked = true;
        hidden.value = id;
        var display  = phone ? name + '  ·  ' + phone : name;
        inp.value    = display;
        _savedName   = display;
        _savedId     = id;
        if (clear) clear.style.display = id ? '' : 'none';
        closeDd();
        markDirty();
        if (onPick) onPick(id, name);
    }

    function doSearch(q, delay) {
        clearTimeout(timer);
        if (q.length < 1) { closeDd(); return; }
        timer = setTimeout(function() {
            var url = '/counterparties/api/search?q=' + encodeURIComponent(q)
                    + (cpType ? '&type=' + encodeURIComponent(cpType) : '');
            fetch(url)
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    _list = (res.ok && res.items) ? res.items : [];
                    if (!_list.length) {
                        dd.innerHTML = '<div class="cp-picker-opt" style="color:var(--text-muted)">Нічого не знайдено</div>';
                        dd.style.display = 'block';
                        return;
                    }
                    var typeCls = { company: 't-company', fop: 't-fop', person: 't-person' };
                    dd.innerHTML = _list.slice(0, 20).map(function(p) {
                        var badge = '<span class="cp-picker-type-badge ' + (typeCls[p.type] || '') + '">'
                                  + esc(p.type_label || p.type) + '</span>';
                        var sub = '';
                        if (p.okpo)  sub += 'ЄДРПОУ: ' + esc(p.okpo);
                        if (p.phone) sub += (sub ? ' · ' : '') + esc(p.phone);
                        return '<div class="cp-picker-opt" data-id="' + p.id + '" data-name="' + esc(p.name) + '" data-phone="' + esc(p.phone || '') + '">'
                            + '<div class="cp-picker-opt-name">' + esc(p.name)
                            + (sub ? '<div class="cp-picker-opt-sub">' + sub + '</div>' : '')
                            + '</div>'
                            + badge
                            + '</div>';
                    }).join('');
                    dd.style.display = 'block';
                })
                .catch(function() { closeDd(); });
        }, delay !== undefined ? delay : 200);
    }

    inp.addEventListener('input', function() {
        _picked = false;
        doSearch(inp.value.trim(), 200);
    });

    inp.addEventListener('focus', function() {
        _savedName = inp.value;
        _savedId   = hidden.value;
        _picked    = false;
        inp.select();
        var searchQ = inp.value.split('  ·  ')[0].trim();
        doSearch(searchQ, 0);
    });

    dd.addEventListener('mousedown', function(e) {
        var opt = e.target.closest('.cp-picker-opt[data-id]');
        if (!opt) return;
        e.preventDefault();
        pick(opt.dataset.id, opt.dataset.name, opt.dataset.phone || '');
    });

    inp.addEventListener('blur', function() {
        setTimeout(function() {
            closeDd();
            if (!_picked) {
                inp.value    = _savedName;
                hidden.value = _savedId;
            }
        }, 150);
    });

    inp.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            clearTimeout(timer);
            closeDd();
            inp.value    = _savedName;
            hidden.value = _savedId;
            _picked = true;
            inp.blur();
        }
    });

    // If user clears the text manually — reset hidden
    inp.addEventListener('change', function() {
        if (!inp.value.trim()) { pick('', ''); }
    });

    if (clear) {
        clear.addEventListener('click', function() { pick('', ''); inp.focus(); });
    }
}

/* ══ CONTACT PERSON PICKER (local list, linked contacts only) ══ */
var _personContacts = _PAGE.initialContacts;

function renderPersonDd(q) {
    var dd = document.getElementById('personPickerDd');
    if (!dd) return;
    var filtered = _personContacts.filter(function(c) {
        if (!q) return true;
        var tokens = q.toLowerCase().split(/\s+/).filter(function(t) { return t.length > 0; });
        var lname  = c.name.toLowerCase();
        for (var i = 0; i < tokens.length; i++) {
            if (lname.indexOf(tokens[i]) === -1) return false;
        }
        return true;
    });
    if (!filtered.length) {
        dd.innerHTML = '<div class="cp-picker-opt" style="color:var(--text-muted)">Нічого не знайдено</div>';
        dd.style.display = 'block';
        return;
    }
    dd.innerHTML = filtered.slice(0, 20).map(function(p) {
        var sub = p.position ? '<div class="cp-picker-opt-sub">' + esc(p.position) + '</div>' : '';
        return '<div class="cp-picker-opt" data-id="' + p.id + '" data-name="' + esc(p.name) + '">'
            + esc(p.name) + sub + '</div>';
    }).join('');
    dd.style.display = 'block';
}

(function () {
    var inp    = document.getElementById('personPickerInput');
    var hidden = document.getElementById('contact_person_id');
    var dd     = document.getElementById('personPickerDd');
    var clear  = document.getElementById('personPickerClear');
    var field  = document.getElementById('contactPersonField');
    if (!inp || !hidden || !dd || !field) return;

    function closeDd() { dd.style.display = 'none'; dd.innerHTML = ''; }

    function pickPerson(id, name) {
        hidden.value = id;
        inp.value    = name;
        if (clear) clear.style.display = id ? '' : 'none';
        closeDd();
        markDirty();
    }

    inp.addEventListener('focus', function() {
        if (_personContacts.length) renderPersonDd(inp.value.trim());
    });

    inp.addEventListener('input', function() {
        if (!_personContacts.length) { closeDd(); return; }
        renderPersonDd(inp.value.trim());
    });

    dd.addEventListener('mousedown', function(e) {
        var opt = e.target.closest('.cp-picker-opt[data-id]');
        if (!opt) return;
        e.preventDefault();
        pickPerson(opt.dataset.id, opt.dataset.name);
    });

    inp.addEventListener('blur', function() { setTimeout(closeDd, 150); });

    inp.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { closeDd(); }
    });

    inp.addEventListener('change', function() {
        if (!inp.value.trim()) { pickPerson('', ''); }
    });

    if (clear) {
        clear.addEventListener('click', function() { pickPerson('', ''); inp.focus(); });
    }
}());

function syncCpFieldWidth() {
    var cpMain = document.querySelector('.cp-field-main');
    var cpField = document.getElementById('contactPersonField');
    if (cpMain && cpField) {
        cpMain.classList.toggle('cp-field-full', cpField.style.display === 'none');
    }
}

function loadContactsForCp(cpId) {
    var field      = document.getElementById('contactPersonField');
    var personHidden = document.getElementById('contact_person_id');
    var personInp    = document.getElementById('personPickerInput');
    var personClear  = document.getElementById('personPickerClear');
    if (!field) return;

    // Clear person selection when counterparty changes
    if (personHidden) personHidden.value = '';
    if (personInp)    personInp.value    = '';
    if (personClear)  personClear.style.display = 'none';

    if (!cpId) {
        _personContacts = [];
        field.style.display = 'none';
        syncCpFieldWidth();
        return;
    }

    fetch('/counterparties/api/get_contacts?counterparty_id=' + encodeURIComponent(cpId))
        .then(function(r) { return r.json(); })
        .then(function(res) {
            _personContacts = (res.ok && res.items) ? res.items : [];
            field.style.display = _personContacts.length ? '' : 'none';
            syncCpFieldWidth();
        })
        .catch(function() {
            _personContacts = [];
            field.style.display = 'none';
            syncCpFieldWidth();
        });
}

// Initial sync on page load
syncCpFieldWidth();

makeCpPicker('cpPickerInput', 'counterparty_id', 'cpPickerDd', 'cpPickerClear', '', function(id) {
    loadContactsForCp(id);
    var cpLink = document.getElementById('cpCardLink');
    if (cpLink) {
        if (id) { cpLink.href = '/counterparties/view?id=' + id; cpLink.style.display = ''; }
        else { cpLink.style.display = 'none'; }
    }
    // Підтягнути дані доставки з останнього замовлення контрагента
    if (_isNew && id) prefillShippingFromCp(id);
});

/* ══ QUICK CREATE COUNTERPARTY ══ */
(function () {
    var modal    = document.getElementById('cpQuickCreateModal');
    var btnOpen  = document.getElementById('cpPickerAdd');
    var btnClose = document.getElementById('cpQuickCreateClose');
    var btnCancel= document.getElementById('cpqCancelBtn');
    var btnSave  = document.getElementById('cpqSaveBtn');
    var typeSel  = document.getElementById('cpqType');
    var errEl    = document.getElementById('cpqError');
    var personF  = document.getElementById('cpqPersonFields');
    var companyF = document.getElementById('cpqCompanyFields');
    if (!modal || !btnOpen) return;

    function showModal() {
        modal.style.display = 'flex';
        errEl.style.display = 'none';
        typeSel.value = 'person';
        toggleFields();
        document.getElementById('cpqLastName').value = '';
        document.getElementById('cpqFirstName').value = '';
        document.getElementById('cpqMiddleName').value = '';
        document.getElementById('cpqCompanyName').value = '';
        document.getElementById('cpqPhone').value = '';
        // pre-fill from search input if user typed a name
        var q = document.getElementById('cpPickerInput').value.trim();
        if (q) {
            var parts = q.split(/\s+/);
            if (parts.length >= 3) {
                // "Прізвище Ім'я По батькові"
                document.getElementById('cpqLastName').value = parts[0] || '';
                document.getElementById('cpqFirstName').value = parts[1] || '';
                document.getElementById('cpqMiddleName').value = parts[2] || '';
            } else if (parts.length === 2) {
                // "Ім'я Прізвище"
                document.getElementById('cpqLastName').value = parts[1] || '';
                document.getElementById('cpqFirstName').value = parts[0] || '';
            } else {
                document.getElementById('cpqLastName').value = parts[0] || '';
            }
        }
        setTimeout(function() { document.getElementById('cpqLastName').focus(); }, 50);
    }

    function hideModal() { modal.style.display = 'none'; }

    function toggleFields() {
        var isPerson = (typeSel.value === 'person');
        personF.style.display  = isPerson ? '' : 'none';
        companyF.style.display = isPerson ? 'none' : '';
    }

    typeSel.addEventListener('change', toggleFields);
    btnOpen.addEventListener('click', showModal);
    btnClose.addEventListener('click', hideModal);
    btnCancel.addEventListener('click', hideModal);
    modal.addEventListener('click', function(e) { if (e.target === modal) hideModal(); });

    btnSave.addEventListener('click', function () {
        errEl.style.display = 'none';
        var type = typeSel.value;
        var name, postData;

        if (type === 'person') {
            var ln = document.getElementById('cpqLastName').value.trim();
            var fn = document.getElementById('cpqFirstName').value.trim();
            var mn = document.getElementById('cpqMiddleName').value.trim();
            name = [ln, fn, mn].filter(Boolean).join(' ');
            if (!name) { errEl.textContent = 'Вкажіть прізвище'; errEl.style.display = ''; return; }
            postData = 'type=person&name=' + encodeURIComponent(name)
                     + '&last_name='   + encodeURIComponent(ln)
                     + '&first_name='  + encodeURIComponent(fn)
                     + '&middle_name=' + encodeURIComponent(mn);
        } else {
            name = document.getElementById('cpqCompanyName').value.trim();
            if (!name) { errEl.textContent = 'Вкажіть назву'; errEl.style.display = ''; return; }
            postData = 'type=' + encodeURIComponent(type) + '&name=' + encodeURIComponent(name);
        }

        var phone = document.getElementById('cpqPhone').value.trim();
        if (phone) postData += '&phone=' + encodeURIComponent(phone);

        btnSave.disabled = true;
        btnSave.textContent = 'Створюю…';

        fetch('/counterparties/api/save_counterparty', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: postData
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            btnSave.disabled = false;
            btnSave.textContent = 'Створити';
            if (!res.ok) {
                errEl.textContent = res.error || 'Помилка';
                errEl.style.display = '';
                return;
            }
            // Pick the new counterparty into the form
            var hidden = document.getElementById('counterparty_id');
            var inp    = document.getElementById('cpPickerInput');
            var clear  = document.getElementById('cpPickerClear');
            var cpLink = document.getElementById('cpCardLink');
            hidden.value = res.id;
            inp.value    = phone ? name + '  ·  ' + phone : name;
            if (clear) clear.style.display = '';
            if (cpLink) { cpLink.href = '/counterparties/view?id=' + res.id; cpLink.style.display = ''; }
            loadContactsForCp(res.id);
            markDirty();
            hideModal();
            showToast('Контрагента «' + name + '» створено');
        })
        .catch(function () {
            btnSave.disabled = false;
            btnSave.textContent = 'Створити';
            errEl.textContent = 'Помилка з\'єднання';
            errEl.style.display = '';
        });
    });
}());

/* ── Create document dropdown ── */
(function () {
    var btn  = document.getElementById('createDocBtn');
    var menu = document.getElementById('createDocMenu');
    if (!btn || !menu) return;

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        menu.classList.toggle('open');
    });

    document.addEventListener('click', function () {
        menu.classList.remove('open');
    });

    menu.addEventListener('click', function (e) {
        var item = e.target.closest('.create-doc-item');
        if (!item) return;
        menu.classList.remove('open');

        var toType   = item.dataset.toType;
        var linkType = item.dataset.linkType;
        var orderId  = item.dataset.orderId;
        var label    = item.textContent.trim();

        item.disabled = true;
        item.textContent = label + '…';

        var body = 'order_id=' + encodeURIComponent(orderId)
                 + '&to_type=' + encodeURIComponent(toType)
                 + '&link_type=' + encodeURIComponent(linkType);

        fetch('/customerorder/api/create_document', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            item.disabled = false;
            item.textContent = label;
            if (res.ok && res.redirect_url) {
                window.location.href = res.redirect_url;
            } else if (res.todo) {
                showToast(res.error, false);
            } else {
                showToast('Помилка: ' + (res.error || 'невідома'), true);
            }
        })
        .catch(function () {
            item.disabled = false;
            item.textContent = label;
            showToast('Помилка з\'єднання', true);
        });
    });
}());

/* ══ PREFILL SHIPPING FROM COUNTERPARTY ══ */
function prefillShippingFromCp(cpId) {
    fetch('/customerorder/api/get_last_shipping?counterparty_id=' + cpId)
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.ok || !res.shipping) return;
            var s = res.shipping;
            window._prefillShipping = s;
            // Показати кнопку
            var wrap = document.getElementById('shippingBtnWrap');
            if (wrap) wrap.style.display = '';
            // Заповнити поля форми
            var map = {
                shFirstName: 'recipient_first_name', shLastName: 'recipient_last_name',
                shPhone: 'recipient_phone', shCity: 'city_name', shBranch: 'branch_name',
                shStreet: 'street', shHouse: 'house', shFlat: 'flat', shPostcode: 'postcode',
                shMethod: 'delivery_method_name', shComment: 'comment',
                shNpRef: 'np_warehouse_ref', shDeliveryCode: 'delivery_code'
            };
            for (var elId in map) {
                var el = document.getElementById(elId);
                if (el) el.value = s[map[elId]] || '';
            }
            var noCall = document.getElementById('shNoCall');
            if (noCall) noCall.checked = !!s.no_call;
        });
}
