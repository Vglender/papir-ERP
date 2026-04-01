<?php // All data loaded via AJAX ?>
<style>
.gs-wrap  { max-width: 1000px; margin: 0 auto; padding: 24px 20px 48px; }

/* toolbar */
.gs-toolbar { display:flex; align-items:center; gap:10px; margin-bottom:18px; }
.gs-toolbar h1 { margin:0; font-size:20px; font-weight:700; flex:1; }
.gs-period-btns { display:flex; gap:4px; }
.gs-period-btn {
    padding:5px 14px; border-radius:6px; border:1px solid #d1d5db;
    background:#fff; cursor:pointer; font-size:13px; font-weight:500;
    color:#374151; transition:all .15s;
}
.gs-period-btn:hover  { border-color:#2563eb; color:#2563eb; }
.gs-period-btn.active { background:#2563eb; border-color:#2563eb; color:#fff; }
.gs-period-range { font-size:12px; color:#9ca3af; }

/* cards */
.gs-card {
    background:#fff; border:1px solid #e5e7eb; border-radius:10px;
    margin-bottom:16px; overflow:hidden;
}
.gs-card-head {
    display:flex; align-items:center; gap:8px;
    padding:12px 18px; border-bottom:1px solid #e5e7eb;
    background:#f8f9fa; font-weight:600; font-size:14px;
}
.gs-card-body { padding:16px 18px; }

/* inner tabs */
.gs-tabs { display:flex; border-bottom:1px solid #e5e7eb; padding:0 18px; }
.gs-tab {
    padding:8px 16px; font-size:13px; font-weight:600; cursor:pointer;
    border:none; background:none; color:#6b7280;
    border-bottom:2px solid transparent; margin-bottom:-1px; transition:color .15s;
}
.gs-tab.active { color:#2563eb; border-bottom-color:#2563eb; }
.gs-tab:hover:not(.active) { color:#374151; }

/* summary table */
.gs-sum-table { width:100%; border-collapse:collapse; font-size:13px; margin-top:14px; }
.gs-sum-table th { text-align:left; font-size:11px; font-weight:600; text-transform:uppercase;
                    letter-spacing:.4px; color:#6b7280; padding:0 12px 8px 0; }
.gs-sum-table td { padding:7px 12px 7px 0; border-top:1px solid #f3f4f6; }

/* top products table */
.gs-prod-table { width:100%; border-collapse:collapse; font-size:13px; }
.gs-prod-table th { text-align:left; font-size:11px; font-weight:600; text-transform:uppercase;
                     letter-spacing:.4px; color:#6b7280; padding:0 12px 8px 0; border-bottom:1px solid #e5e7eb; }
.gs-prod-table th.r { text-align:right; }
.gs-prod-table td   { padding:6px 12px 6px 0; border-top:1px solid #f3f4f6; }
.gs-prod-table td.r { text-align:right; }

.gs-loading { text-align:center; padding:24px; color:#9ca3af; font-size:13px; }
.gs-error   { background:#fef2f2; border:1px solid #fecaca; border-radius:8px;
               padding:10px 14px; font-size:13px; color:#991b1b; margin:12px 0; }
</style>

<div class="gs-wrap">

    <!-- Toolbar -->
    <div class="gs-toolbar">
        <h1>Google Shopping</h1>
        <span class="gs-period-range" id="gsPeriodRange"></span>
        <div class="gs-period-btns">
            <button type="button" class="gs-period-btn" data-days="7">7 днів</button>
            <button type="button" class="gs-period-btn active" data-days="28">28 днів</button>
            <button type="button" class="gs-period-btn" data-days="90">90 днів</button>
        </div>
    </div>

    <!-- 1. Зведення -->
    <div class="gs-card">
        <div class="gs-card-head">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="1" y="9" width="3" height="6" rx="1" fill="currentColor"/><rect x="6" y="5" width="3" height="10" rx="1" fill="currentColor"/><rect x="11" y="1" width="3" height="14" rx="1" fill="currentColor"/></svg>
            Зведення по програмах
        </div>
        <div class="gs-card-body" id="gsSummaryBody">
            <div class="gs-loading">Завантаження…</div>
        </div>
    </div>

    <!-- 2. Топ товарів -->
    <div class="gs-card">
        <div class="gs-card-head">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M2 13h2v2H2zM6 8h2v7H6zM10 4h2v11h-2zM14 1h2v14h-2z" fill="currentColor" opacity=".7"/></svg>
            Топ товарів
        </div>
        <div class="gs-tabs">
            <button type="button" class="gs-tab active" data-gstab="shopping">🛍 Shopping Ads</button>
            <button type="button" class="gs-tab" data-gstab="free">🆓 Безкоштовні оголошення</button>
        </div>
        <div id="gsPane_shopping" class="gs-card-body">
            <div class="gs-loading">Завантаження…</div>
        </div>
        <div id="gsPane_free" class="gs-card-body" style="display:none">
            <div class="gs-loading">Завантаження…</div>
        </div>
    </div>

</div>

<script>
(function () {
    var _days    = 28;
    var _daily   = [];
    var _dr      = { from: '', to: '' };
    var _sections = {};
    var _sort    = { col: 'clicks', dir: 'desc' };

    // ── Period buttons ────────────────────────────────────────────────────
    document.querySelectorAll('.gs-period-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.gs-period-btn').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            _days = parseInt(btn.getAttribute('data-days'), 10);
            load();
        });
    });

    // ── Inner tabs ────────────────────────────────────────────────────────
    document.querySelectorAll('[data-gstab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tab = btn.getAttribute('data-gstab');
            document.querySelectorAll('[data-gstab]').forEach(function (b) {
                b.classList.toggle('active', b.getAttribute('data-gstab') === tab);
            });
            document.getElementById('gsPane_shopping').style.display = tab === 'shopping' ? '' : 'none';
            document.getElementById('gsPane_free').style.display     = tab === 'free'     ? '' : 'none';
        });
    });

    // ── Load ──────────────────────────────────────────────────────────────
    function load() {
        document.getElementById('gsSummaryBody').innerHTML = '<div class="gs-loading">Завантаження…</div>';
        ['shopping','free'].forEach(function (t) {
            document.getElementById('gsPane_' + t).innerHTML = '<div class="gs-loading">Завантаження…</div>';
        });

        fetch('/integr/merchant/api/get_performance?days=' + _days)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) {
                    document.getElementById('gsSummaryBody').innerHTML = '<div class="gs-error">' + esc(d.error || 'Помилка API') + '</div>';
                    return;
                }
                _daily = d.daily || [];
                _dr    = { from: d.date_from, to: d.date_to };
                document.getElementById('gsPeriodRange').textContent = d.date_from + ' — ' + d.date_to;

                renderSummary(d.summary);
                renderTopTab('shopping', d.shopping_ads);
                renderTopTab('free',     d.free_listings);
            })
            .catch(function (e) {
                document.getElementById('gsSummaryBody').innerHTML = '<div class="gs-error">' + esc(String(e)) + '</div>';
            });
    }

    // ── Summary ───────────────────────────────────────────────────────────
    function renderSummary(summary) {
        var el  = document.getElementById('gsSummaryBody');
        var html = '';

        // chart
        if (_daily && _daily.length) {
            html += buildChartHtml(_daily, _dr.from, _dr.to);
        }

        // table
        var progLabels = {
            'SHOPPING_ADS':              'Shopping Ads',
            'FREE_PRODUCT_LISTING':      'Free Listings',
            'FREE_LOCAL_PRODUCT_LISTING':'Free Local Listings',
        };
        if (summary && summary.length) {
            html += '<table class="gs-sum-table"><thead><tr>'
                + '<th>Програма</th><th style="text-align:right">Кліки</th>'
                + '<th style="text-align:right">Покази</th><th style="text-align:right">CTR</th>'
                + '</tr></thead><tbody>';
            summary.forEach(function (row) {
                var c = row.ctr >= 1 ? '#16a34a' : (row.ctr >= 0.3 ? '#d97706' : '#dc2626');
                html += '<tr>'
                    + '<td style="font-weight:600">' + esc(progLabels[row.program] || row.program) + '</td>'
                    + '<td style="text-align:right;font-weight:700">' + fmt(row.clicks) + '</td>'
                    + '<td style="text-align:right;color:#6b7280">'   + fmt(row.impressions) + '</td>'
                    + '<td style="text-align:right;font-weight:600;color:' + c + '">' + row.ctr + '%</td>'
                    + '</tr>';
            });
            html += '</tbody></table>';
        } else {
            html += '<div style="color:#9ca3af;font-size:13px;margin-top:12px">Даних немає за цей період</div>';
        }

        el.innerHTML = html;
    }

    // ── Top products tab ──────────────────────────────────────────────────
    function renderTopTab(tabKey, origRows) {
        var pane = document.getElementById('gsPane_' + tabKey);
        if (!origRows || !origRows.length) {
            pane.innerHTML = '<div style="color:#9ca3af;font-size:13px;padding:8px 0">Даних немає за цей період</div>';
            return;
        }

        var secId  = 'gs_' + tabKey;
        var rows   = sortRows(origRows);
        var pgSize = 20;
        _sections[secId] = { rows: rows, pageSize: pgSize, shown: pgSize };

        var thead = '<tr>'
            + '<th style="width:54px">Offer ID</th>'
            + '<th>Назва товару</th>'
            + '<th class="r gs-sort-th" data-col="clicks"    style="width:72px">Кліки'    + sortArrow('clicks')    + '</th>'
            + '<th class="r gs-sort-th" data-col="impressions" style="width:84px">Покази'  + sortArrow('impressions') + '</th>'
            + '<th class="r gs-sort-th" data-col="ctr"        style="width:60px">CTR'      + sortArrow('ctr')      + '</th>'
            + '</tr>';

        var firstRows = rows.slice(0, pgSize).map(renderRow).join('');
        var remaining = rows.length - pgSize;
        var ctrlHtml  = '';
        if (remaining > 0) {
            ctrlHtml = '<div style="display:flex;gap:6px;margin-top:8px">'
                + '<button type="button" class="btn btn-ghost btn-xs" id="gsMore_' + tabKey + '">Показати ще ' + remaining + ' ↓</button>'
                + '<button type="button" class="btn btn-ghost btn-xs" id="gsCollapse_' + tabKey + '" style="display:none">Згорнути ↑</button>'
                + '</div>';
        }

        pane.innerHTML = '<table class="gs-prod-table"><thead>' + thead + '</thead>'
            + '<tbody id="gsBody_' + tabKey + '">' + firstRows + '</tbody></table>'
            + ctrlHtml;

        // Sort clicks
        pane.querySelectorAll('.gs-sort-th').forEach(function (th) {
            th.addEventListener('click', function () {
                var col = th.getAttribute('data-col');
                if (_sort.col === col) { _sort.dir = _sort.dir === 'desc' ? 'asc' : 'desc'; }
                else { _sort.col = col; _sort.dir = 'desc'; }
                renderTopTab(tabKey, origRows);
            });
        });

        bindSection(tabKey);
    }

    function bindSection(tabKey) {
        var secId     = 'gs_' + tabKey;
        var sec       = _sections[secId];
        var moreBtn   = document.getElementById('gsMore_' + tabKey);
        var collapseB = document.getElementById('gsCollapse_' + tabKey);
        if (!sec) return;

        if (moreBtn) {
            moreBtn.addEventListener('click', function () {
                var tbody = document.getElementById('gsBody_' + tabKey);
                var chunk = sec.rows.slice(sec.shown, sec.shown + sec.pageSize);
                var frag  = document.createDocumentFragment();
                chunk.forEach(function (row) {
                    var tmp = document.createElement('tbody');
                    tmp.innerHTML = renderRow(row);
                    while (tmp.firstChild) { frag.appendChild(tmp.firstChild); }
                });
                tbody.appendChild(frag);
                sec.shown += chunk.length;
                var rem = sec.rows.length - sec.shown;
                if (rem > 0) { moreBtn.textContent = 'Показати ще ' + rem + ' ↓'; }
                else         { moreBtn.style.display = 'none'; }
                if (collapseB) collapseB.style.display = '';
            });
        }
        if (collapseB) {
            collapseB.addEventListener('click', function () {
                var tbody = document.getElementById('gsBody_' + tabKey);
                var trs   = tbody.querySelectorAll('tr');
                for (var i = sec.pageSize; i < trs.length; i++) { trs[i].remove(); }
                sec.shown = sec.pageSize;
                if (moreBtn) { moreBtn.style.display = ''; moreBtn.textContent = 'Показати ще ' + (sec.rows.length - sec.pageSize) + ' ↓'; }
                collapseB.style.display = 'none';
            });
        }
    }

    function renderRow(row) {
        var nameCell;
        if (row.product_id) {
            var art = row.article
                ? '<span style="font-size:11px;color:#9ca3af;margin-right:4px">' + esc(row.article) + '</span>'
                : '';
            nameCell = art + '<a href="/catalog?search=' + row.product_id + '" target="_blank" style="color:#2563eb">'
                + esc(row.name || ('Товар ' + row.offer_id)) + '</a>';
        } else {
            nameCell = '<span style="color:#9ca3af">offer_id: ' + esc(String(row.offer_id)) + '</span>';
        }
        var ctrColor = row.ctr >= 1 ? '#16a34a' : (row.ctr >= 0.3 ? '#d97706' : '#dc2626');
        return '<tr>'
            + '<td style="color:#9ca3af;font-size:11px;white-space:nowrap">' + esc(String(row.offer_id)) + '</td>'
            + '<td style="max-width:340px"><span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + esc(row.name || '') + '">' + nameCell + '</span></td>'
            + '<td class="r" style="font-weight:700">'                              + fmt(row.clicks)      + '</td>'
            + '<td class="r" style="color:#6b7280">'                               + fmt(row.impressions) + '</td>'
            + '<td class="r" style="font-weight:600;color:' + ctrColor + '">'      + row.ctr + '%</td>'
            + '</tr>';
    }

    // ── Chart ─────────────────────────────────────────────────────────────
    function buildChartHtml(daily, dateFrom, dateTo) {
        var shopByDate = {}, freeByDate = {};
        daily.forEach(function (r) {
            if (r.program === 'SHOPPING_ADS')           { shopByDate[r.date] = r.clicks; }
            else if (r.program === 'FREE_PRODUCT_LISTING') { freeByDate[r.date] = r.clicks; }
        });

        var dates = [], cur = new Date(dateFrom), end = new Date(dateTo);
        while (cur <= end) {
            dates.push(cur.toISOString().slice(0, 10));
            cur.setDate(cur.getDate() + 1);
        }
        if (!dates.length) return '';

        var shopVals = dates.map(function (d) { return shopByDate[d] || 0; });
        var freeVals = dates.map(function (d) { return freeByDate[d] || 0; });

        var legend = '<div style="display:flex;align-items:center;gap:16px;margin-bottom:6px;font-size:12px">'
            + '<span><svg width="20" height="10" style="vertical-align:middle;margin-right:3px"><line x1="0" y1="5" x2="20" y2="5" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round"/></svg>Shopping Ads</span>'
            + '<span><svg width="20" height="10" style="vertical-align:middle;margin-right:3px"><line x1="0" y1="5" x2="20" y2="5" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round"/></svg>Free Listings</span>'
            + '<span style="margin-left:auto;color:#9ca3af">кліки / день</span>'
            + '</div>';

        return '<div>' + legend + buildSvgChart(
            [
                { values: shopVals, color: '#2563eb', fill: 'rgba(37,99,235,.07)' },
                { values: freeVals, color: '#16a34a', fill: 'rgba(22,163,74,.07)'  },
            ],
            dates
        ) + '</div>';
    }

    function buildSvgChart(datasets, dates) {
        var n = dates.length;
        if (!n) return '';
        var W = 760, H = 160, pL = 44, pR = 10, pT = 12, pB = 28;
        var plotW = W - pL - pR, plotH = H - pT - pB;

        var maxVal = 1;
        datasets.forEach(function (ds) { ds.values.forEach(function (v) { if (v > maxVal) maxVal = v; }); });
        var mag = Math.pow(10, Math.floor(Math.log(maxVal) / Math.LN10));
        maxVal = Math.ceil(maxVal / mag) * mag;

        function px(i) { return pL + (n > 1 ? i / (n - 1) * plotW : plotW / 2); }
        function py(v)  { return pT + plotH * (1 - v / maxVal); }

        var svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" style="width:100%;display:block">';

        for (var gl = 0; gl <= 4; gl++) {
            var yv = pT + plotH * (1 - gl / 4);
            var lv = Math.round(maxVal * gl / 4);
            svg += '<line x1="' + pL + '" y1="' + yv.toFixed(1) + '" x2="' + (pL + plotW) + '" y2="' + yv.toFixed(1)
                + '" stroke="' + (gl === 0 ? '#e5e7eb' : '#f3f4f6') + '" stroke-width="1"/>';
            svg += '<text x="' + (pL - 4) + '" y="' + (yv + 3.5).toFixed(1) + '" font-size="9" fill="#9ca3af" text-anchor="end">' + fmt(lv) + '</text>';
        }

        var step = Math.ceil(n / 8);
        for (var i = 0; i < n; i++) {
            if (i % step === 0 || i === n - 1) {
                svg += '<text x="' + px(i).toFixed(1) + '" y="' + (H - 4) + '" font-size="9" fill="#9ca3af" text-anchor="middle">'
                    + dates[i].slice(5) + '</text>';
            }
        }

        datasets.forEach(function (ds) {
            var p = '';
            ds.values.forEach(function (v, i) { p += (i ? 'L' : 'M') + px(i).toFixed(1) + ',' + py(v).toFixed(1) + ' '; });
            p += 'L' + px(n - 1).toFixed(1) + ',' + (pT + plotH) + ' L' + pL + ',' + (pT + plotH) + ' Z';
            svg += '<path d="' + p + '" fill="' + ds.fill + '"/>';
        });
        datasets.forEach(function (ds) {
            var p = '';
            ds.values.forEach(function (v, i) { p += (i ? 'L' : 'M') + px(i).toFixed(1) + ',' + py(v).toFixed(1) + ' '; });
            svg += '<path d="' + p + '" fill="none" stroke="' + ds.color + '" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>';
        });
        if (n <= 60) {
            datasets.forEach(function (ds) {
                ds.values.forEach(function (v, i) {
                    if (v > 0) svg += '<circle cx="' + px(i).toFixed(1) + '" cy="' + py(v).toFixed(1) + '" r="2.5" fill="' + ds.color + '"/>';
                });
            });
        }

        return svg + '</svg>';
    }

    // ── Helpers ───────────────────────────────────────────────────────────
    function sortRows(rows) {
        var col = _sort.col, dir = _sort.dir;
        return rows.slice().sort(function (a, b) {
            var av = parseFloat(a[col]) || 0;
            var bv = parseFloat(b[col]) || 0;
            return dir === 'desc' ? bv - av : av - bv;
        });
    }
    function sortArrow(col) {
        if (_sort.col !== col) return '<span style="opacity:.25;margin-left:2px">↕</span>';
        return _sort.dir === 'desc'
            ? '<span style="color:#2563eb;margin-left:2px">↓</span>'
            : '<span style="color:#2563eb;margin-left:2px">↑</span>';
    }
    function fmt(n) { return (n === null || n === undefined) ? '—' : Number(n).toLocaleString('uk-UA'); }
    function esc(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    // ── Init ─────────────────────────────────────────────────────────────
    load();
}());
</script>