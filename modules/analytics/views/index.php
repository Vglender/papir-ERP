<?php
$sitesResult = Database::fetchAll('Papir', "SELECT site_id, code, name, badge FROM sites WHERE status=1 AND code IN ('off','mff') ORDER BY sort_order");
$gaSites = ($sitesResult['ok'] && !empty($sitesResult['rows'])) ? $sitesResult['rows'] : array(
    array('code' => 'off', 'name' => 'Офіс Торг', 'badge' => 'off'),
    array('code' => 'mff', 'name' => 'Menu Fold',  'badge' => 'mf'),
);
?>
<style>
.ga-wrap { max-width: 1280px; margin: 24px auto; padding: 0 20px; }

/* toolbar */
.ga-toolbar { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
.ga-toolbar h1 { margin: 0; font-size: 20px; font-weight: 700; flex-shrink: 0; }

.ga-site-tabs, .ga-period-tabs { display: flex; gap: 4px; }
.ga-period-tabs { margin-left: auto; }
.ga-site-tab, .ga-period-btn {
    padding: 6px 16px; border-radius: 6px; border: 1px solid #d1d5db;
    background: #fff; cursor: pointer; font-size: 13px; font-weight: 500;
    color: #374151; transition: all .15s;
}
.ga-site-tab:hover, .ga-period-btn:hover { border-color: #4f7ef8; color: #4f7ef8; }
.ga-site-tab.active, .ga-period-btn.active { background: #4f7ef8; border-color: #4f7ef8; color: #fff; }

/* metric cards */
.ga-cards { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 16px; }
@media(max-width:1000px){ .ga-cards { grid-template-columns: repeat(3,1fr); } }
.ga-card { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:14px 16px; }
.ga-card-label { font-size:11px; text-transform:uppercase; letter-spacing:.6px; color:#6b7280; margin-bottom:6px; }
.ga-card-value { font-size:24px; font-weight:700; color:#111827; line-height:1; }
.ga-card-delta { display:inline-flex; align-items:center; gap:3px; font-size:12px; font-weight:600;
                  margin-top:5px; padding:2px 6px; border-radius:4px; }
.ga-card-delta.up   { color:#16a34a; background:#f0fdf4; }
.ga-card-delta.down { color:#dc2626; background:#fef2f2; }
.ga-card-delta.zero { color:#6b7280; background:#f3f4f6; }
.ga-card-prev { font-size:11px; color:#9ca3af; margin-top:2px; }

/* e-commerce cards */
.ga-ecom-cards { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:16px; }
@media(max-width:1000px){ .ga-ecom-cards { grid-template-columns:repeat(2,1fr); } }
.ga-ecom-card { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:14px 16px;
                 border-left:3px solid #7c3aed; }

/* section title */
.ga-section { font-size:13px; font-weight:600; color:#6b7280; text-transform:uppercase;
               letter-spacing:.6px; margin:0 0 10px; }

/* row2: chart + channels */
.ga-row2 { display:grid; grid-template-columns:1fr 260px; gap:14px; margin-bottom:14px; }
@media(max-width:900px){ .ga-row2 { grid-template-columns:1fr; } }

/* row3: geography + top pages */
.ga-row3 { display:grid; grid-template-columns:300px 1fr; gap:14px; margin-bottom:14px; }
@media(max-width:900px){ .ga-row3 { grid-template-columns:1fr; } }

/* cards */
.ga-card-box { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:16px 18px; }
.ga-box-title { font-size:14px; font-weight:600; color:#111827; margin-bottom:12px; }

/* chart */
.ga-chart-head { display:flex; align-items:center; gap:10px; margin-bottom:12px; }
.ga-chart-legend { display:flex; gap:12px; margin-left:auto; }
.ga-leg-item { display:flex; align-items:center; gap:5px; font-size:12px; color:#6b7280;
                cursor:pointer; padding:3px 6px; border-radius:4px; transition:.1s; }
.ga-leg-item:hover { background:#f3f4f6; }
.ga-leg-item.active { background:#f3f4f6; color:#111827; font-weight:600; }
.ga-leg-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
#gaChart { width:100%; height:180px; }

/* channels */
.ga-channel-row { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
.ga-channel-name { font-size:13px; color:#374151; flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.ga-channel-bar-wrap { width:70px; background:#f3f4f6; border-radius:4px; height:5px; flex-shrink:0; }
.ga-channel-bar  { height:5px; border-radius:4px; background:#4f7ef8; }
.ga-channel-val  { font-size:12px; color:#6b7280; width:36px; text-align:right; flex-shrink:0; }

/* geography */
.ga-geo-row { display:flex; align-items:center; gap:8px; margin-bottom:7px; }
.ga-geo-num  { width:20px; font-size:11px; color:#9ca3af; text-align:right; flex-shrink:0; }
.ga-geo-name { font-size:13px; color:#374151; flex:1; }
.ga-geo-bar-wrap { width:60px; background:#f3f4f6; border-radius:4px; height:5px; flex-shrink:0; }
.ga-geo-bar  { height:5px; border-radius:4px; background:#db2777; }
.ga-geo-val  { font-size:12px; color:#6b7280; width:36px; text-align:right; flex-shrink:0; }

/* top pages table */
.ga-pages-table { width:100%; border-collapse:collapse; font-size:13px; }
.ga-pages-table th { text-align:left; padding:5px 10px; font-size:11px; text-transform:uppercase;
                      letter-spacing:.5px; color:#6b7280; border-bottom:1px solid #e5e7eb; }
.ga-pages-table td { padding:6px 10px; border-bottom:1px solid #f3f4f6; color:#374151; }
.ga-pages-table tr:last-child td { border-bottom:none; }
.ga-pages-table td.num { text-align:right; color:#6b7280; }
.ga-page-path  { max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ga-page-title { font-size:11px; color:#9ca3af; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:300px; }

/* ecom not configured */
.ga-ecom-empty { background:#fafafa; border:1px dashed #d1d5db; border-radius:8px;
                  padding:20px; text-align:center; color:#6b7280; font-size:13px; margin-bottom:14px; }

.ga-loading { text-align:center; padding:30px; color:#9ca3af; font-size:13px; }

/* orders by channel */
.ga-orders-card { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:16px 18px; margin-bottom:14px; }
.ga-orders-table { width:100%; border-collapse:collapse; font-size:13px; }
.ga-orders-table th { text-align:left; padding:6px 12px; font-size:11px; text-transform:uppercase;
                       letter-spacing:.5px; color:#6b7280; border-bottom:1px solid #e5e7eb; }
.ga-orders-table td { padding:7px 12px; border-bottom:1px solid #f3f4f6; color:#374151; }
.ga-orders-table tr:last-child td { border-bottom:none; }
.ga-orders-table td.num { text-align:right; font-variant-numeric:tabular-nums; }
.ga-ch-badge { display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:500;
                padding:2px 8px; border-radius:12px; }
.ga-ch-google-ads  { background:#e8f0fe; color:#4285f4; }
.ga-ch-facebook    { background:#e7f0ff; color:#1877f2; }
.ga-ch-organic     { background:#f0fdf4; color:#16a34a; }
.ga-ch-direct      { background:#f3f4f6; color:#6b7280; }
.ga-ch-other       { background:#fff7ed; color:#ea580c; }
.ga-orders-bar-wrap { width:80px; background:#f3f4f6; border-radius:4px; height:5px; display:inline-block; vertical-align:middle; }
.ga-orders-bar      { height:5px; border-radius:4px; background:#4f7ef8; }
.ga-error   { background:#fef2f2; border:1px solid #fecaca; border-radius:8px;
               padding:10px 14px; font-size:13px; color:#991b1b; margin-bottom:12px; }
</style>

<div class="ga-wrap">

    <!-- Toolbar -->
    <div class="ga-toolbar">
        <h1>Google Analytics</h1>
        <div class="ga-site-tabs">
            <?php foreach ($gaSites as $i => $s): ?>
            <button class="ga-site-tab<?php echo $i === 0 ? ' active' : ''; ?>"
                    data-site="<?php echo htmlspecialchars($s['code']); ?>">
                <?php echo htmlspecialchars($s['name']); ?>
            </button>
            <?php endforeach; ?>
        </div>
        <div class="ga-period-tabs">
            <button class="ga-period-btn active" data-period="7">7 днів</button>
            <button class="ga-period-btn" data-period="30">30 днів</button>
            <button class="ga-period-btn" data-period="90">90 днів</button>
            <button class="ga-period-btn" data-period="365">Рік</button>
        </div>
    </div>

    <!-- Summary cards -->
    <div id="gaCards" class="ga-cards">
        <div class="ga-loading" style="grid-column:1/-1">Завантаження…</div>
    </div>

    <!-- Orders by channel -->
    <div class="ga-orders-card">
        <div class="ga-box-title">Замовлення по каналах трафіку <span class="text-muted fs-12" style="font-weight:400">(з бази CRM)</span></div>
        <div id="gaOrdersChannels"><div class="ga-loading">Завантаження…</div></div>
    </div>

    <!-- E-commerce -->
    <div id="gaEcom">
        <div class="ga-loading">Завантаження e-commerce…</div>
    </div>

    <!-- Chart + Channels -->
    <div class="ga-row2">
        <div class="ga-card-box">
            <div class="ga-chart-head">
                <span class="ga-box-title" style="margin:0">Динаміка</span>
                <div class="ga-chart-legend">
                    <span class="ga-leg-item active" data-metric="sessions">
                        <span class="ga-leg-dot" style="background:#4f7ef8"></span>Сесії
                    </span>
                    <span class="ga-leg-item" data-metric="users">
                        <span class="ga-leg-dot" style="background:#16a34a"></span>Користувачі
                    </span>
                    <span class="ga-leg-item" data-metric="pageviews">
                        <span class="ga-leg-dot" style="background:#ea580c"></span>Перегляди
                    </span>
                </div>
            </div>
            <canvas id="gaChart"></canvas>
        </div>
        <div class="ga-card-box">
            <div class="ga-box-title">Джерела трафіку</div>
            <div id="gaChannels"><div class="ga-loading">…</div></div>
        </div>
    </div>

    <!-- Geography + Top pages -->
    <div class="ga-row3">
        <div class="ga-card-box">
            <div class="ga-box-title">Топ міст</div>
            <div id="gaGeo"><div class="ga-loading">…</div></div>
        </div>
        <div class="ga-card-box">
            <div class="ga-box-title">Топ сторінок</div>
            <div id="gaPages"><div class="ga-loading">…</div></div>
        </div>
    </div>


</div>

<script>
(function () {
    var currentSite   = '<?php echo htmlspecialchars($gaSites[0]['code']); ?>';
    var currentPeriod = 7;
    var chartMetric   = 'sessions';
    var chartData     = null;

    // ── tabs ─────────────────────────────────────────────────────────────────
    document.querySelectorAll('.ga-site-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.ga-site-tab').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            currentSite = btn.dataset.site;
            loadAll();
        });
    });
    document.querySelectorAll('.ga-period-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.ga-period-btn').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            currentPeriod = parseInt(btn.dataset.period);
            loadAll();
        });
    });
    document.querySelectorAll('.ga-leg-item').forEach(function (item) {
        item.addEventListener('click', function () {
            document.querySelectorAll('.ga-leg-item').forEach(function (i) { i.classList.remove('active'); });
            item.classList.add('active');
            chartMetric = item.dataset.metric;
            if (chartData) renderChart(chartData);
        });
    });

    function post(report, cb) {
        fetch('/analytics/api/get_report', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'site=' + encodeURIComponent(currentSite)
                + '&period=' + currentPeriod
                + '&report=' + encodeURIComponent(report)
        }).then(function (r) { return r.json(); })
          .then(cb)
          .catch(function (e) { cb({ ok: false, error: e.toString() }); });
    }

    // ── summary + comparison ─────────────────────────────────────────────────
    function loadSummary() {
        document.getElementById('gaCards').innerHTML = '<div class="ga-loading" style="grid-column:1/-1">Завантаження…</div>';
        post('summary', function (res) {
            if (!res.ok) {
                document.getElementById('gaCards').innerHTML = '<div class="ga-error" style="grid-column:1/-1">' + esc(res.error) + '</div>';
                return;
            }
            var d = res.data, p = res.prev, df = res.diff;
            document.getElementById('gaCards').innerHTML =
                metricCard('Користувачі',      fmtNum(d.users),                df.users,                fmtNum(p.users)                + ' попер.') +
                metricCard('Сесії',            fmtNum(d.sessions),             df.sessions,             fmtNum(p.sessions)             + ' попер.') +
                metricCard('Перегляди',        fmtNum(d.pageviews),            df.pageviews,            fmtNum(p.pageviews)            + ' попер.') +
                metricCard('Відмови',          d.bounce_rate + '%',            df.bounce_rate !== null ? -df.bounce_rate : null, p.bounce_rate + '% попер.') +
                metricCard('Сер. тривалість',  fmtDur(d.avg_session_duration), df.avg_session_duration, fmtDur(p.avg_session_duration) + ' попер.');
        });
    }

    function metricCard(label, value, diff, prev) {
        var deltaHtml = '';
        if (diff !== null && diff !== undefined) {
            var cls = diff > 0 ? 'up' : (diff < 0 ? 'down' : 'zero');
            var arrow = diff > 0 ? '▲' : (diff < 0 ? '▼' : '—');
            deltaHtml = '<div class="ga-card-delta ' + cls + '">' + arrow + ' ' + Math.abs(diff) + '%</div>';
        }
        return '<div class="ga-card">'
             + '<div class="ga-card-label">' + label + '</div>'
             + '<div class="ga-card-value">' + value + '</div>'
             + deltaHtml
             + '<div class="ga-card-prev">' + esc(prev) + '</div>'
             + '</div>';
    }

    // ── e-commerce ────────────────────────────────────────────────────────────
    function loadEcommerce() {
        document.getElementById('gaEcom').innerHTML = '<div class="ga-loading">Завантаження e-commerce…</div>';
        post('ecommerce', function (res) {
            if (!res.ok || res.data.transactions === 0 && res.data.revenue === 0) {
                document.getElementById('gaEcom').innerHTML =
                    '<div class="ga-ecom-empty">E-commerce не налаштований або немає транзакцій за цей період.<br>'
                    + '<small>Для відстеження покупок потрібно додати GA4 gtag.js на сайт з подією <code>purchase</code>.</small></div>';
                return;
            }
            var d = res.data, df = res.diff;
            document.getElementById('gaEcom').innerHTML =
                '<div style="margin-bottom:8px"><span class="ga-section">E-Commerce</span></div>'
                + '<div class="ga-ecom-cards">'
                + ecomCard('Транзакції',    fmtNum(d.transactions),          df.transactions)
                + ecomCard('Дохід',         fmtMoney(d.revenue) + ' ₴',      df.revenue)
                + ecomCard('Покупки',       fmtNum(d.purchases),             df.purchases)
                + ecomCard('Конверсія',     d.conversion_rate + '%',         df.conversion_rate)
                + '</div>';
        });
    }

    function ecomCard(label, value, diff) {
        var deltaHtml = '';
        if (diff !== null && diff !== undefined) {
            var cls = diff > 0 ? 'up' : (diff < 0 ? 'down' : 'zero');
            var arrow = diff > 0 ? '▲' : (diff < 0 ? '▼' : '—');
            deltaHtml = '<span class="ga-card-delta ' + cls + '" style="font-size:11px">' + arrow + ' ' + Math.abs(diff) + '%</span>';
        }
        return '<div class="ga-ecom-card">'
             + '<div class="ga-card-label">' + label + '</div>'
             + '<div class="ga-card-value" style="font-size:20px">' + value + '</div>'
             + deltaHtml + '</div>';
    }

    // ── chart ─────────────────────────────────────────────────────────────────
    function loadByDate() {
        post('by_date', function (res) {
            if (!res.ok || !res.data) return;
            chartData = res.data;
            renderChart(chartData);
        });
    }

    function renderChart(data) {
        var canvas = document.getElementById('gaChart');
        var ctx    = canvas.getContext('2d');
        var colors = { sessions: '#4f7ef8', users: '#16a34a', pageviews: '#ea580c' };
        var values = data.map(function (r) { return r[chartMetric] || 0; });
        var labels = data.map(function (r) { return r.date.slice(5); });
        var color  = colors[chartMetric] || '#4f7ef8';
        var maxV   = Math.max.apply(null, values) || 1;
        var W = canvas.offsetWidth || 560;
        var H = 180;
        canvas.width = W; canvas.height = H;
        var padL = 42, padR = 8, padT = 8, padB = 32;
        var plotW = W - padL - padR, plotH = H - padT - padB;
        ctx.clearRect(0, 0, W, H);

        // grid lines
        for (var g = 0; g <= 4; g++) {
            var y = padT + plotH - (g / 4) * plotH;
            ctx.strokeStyle = '#f3f4f6'; ctx.lineWidth = 1;
            ctx.beginPath(); ctx.moveTo(padL, y); ctx.lineTo(W - padR, y); ctx.stroke();
            ctx.fillStyle = '#9ca3af'; ctx.font = '10px sans-serif'; ctx.textAlign = 'right';
            ctx.fillText(fmtNum(Math.round(maxV * g / 4)), padL - 4, y + 3);
        }

        if (values.length < 2) return;
        var step = plotW / (values.length - 1);

        // fill
        ctx.beginPath(); ctx.moveTo(padL, padT + plotH);
        values.forEach(function (v, i) { ctx.lineTo(padL + i * step, padT + plotH - (v / maxV) * plotH); });
        ctx.lineTo(padL + (values.length - 1) * step, padT + plotH); ctx.closePath();
        var grad = ctx.createLinearGradient(0, padT, 0, padT + plotH);
        grad.addColorStop(0, color + '33'); grad.addColorStop(1, color + '00');
        ctx.fillStyle = grad; ctx.fill();

        // line
        ctx.beginPath(); ctx.strokeStyle = color; ctx.lineWidth = 2; ctx.lineJoin = 'round';
        values.forEach(function (v, i) {
            var x = padL + i * step, y = padT + plotH - (v / maxV) * plotH;
            i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        });
        ctx.stroke();

        // x labels
        var stepLbl = Math.ceil(values.length / 9);
        ctx.fillStyle = '#9ca3af'; ctx.font = '10px sans-serif'; ctx.textAlign = 'center';
        labels.forEach(function (lbl, i) {
            if (i % stepLbl !== 0 && i !== labels.length - 1) return;
            ctx.fillText(lbl, padL + i * step, H - 6);
        });
    }

    // ── channels ──────────────────────────────────────────────────────────────
    function loadChannels() {
        document.getElementById('gaChannels').innerHTML = '<div class="ga-loading">…</div>';
        post('channels', function (res) {
            if (!res.ok || !res.data || !res.data.length) {
                document.getElementById('gaChannels').innerHTML = '<div class="text-muted fs-12">Немає даних</div>'; return;
            }
            var maxS = res.data[0].sessions || 1;
            document.getElementById('gaChannels').innerHTML = res.data.map(function (row) {
                var pct = Math.round(row.sessions / maxS * 100);
                return '<div class="ga-channel-row">'
                     + '<span class="ga-channel-name">' + esc(row.channel) + '</span>'
                     + '<div class="ga-channel-bar-wrap"><div class="ga-channel-bar" style="width:' + pct + '%"></div></div>'
                     + '<span class="ga-channel-val">' + fmtNum(row.sessions) + '</span></div>';
            }).join('');
        });
    }

    // ── geography ─────────────────────────────────────────────────────────────
    function loadGeo() {
        document.getElementById('gaGeo').innerHTML = '<div class="ga-loading">…</div>';
        post('geography', function (res) {
            if (!res.ok || !res.data || !res.data.length) {
                document.getElementById('gaGeo').innerHTML = '<div class="text-muted fs-12">Немає даних</div>'; return;
            }
            var maxS = res.data[0].sessions || 1;
            document.getElementById('gaGeo').innerHTML = res.data.map(function (row, i) {
                var pct = Math.round(row.sessions / maxS * 100);
                return '<div class="ga-geo-row">'
                     + '<span class="ga-geo-num">' + (i + 1) + '</span>'
                     + '<span class="ga-geo-name">' + esc(row.city) + '</span>'
                     + '<div class="ga-geo-bar-wrap"><div class="ga-geo-bar" style="width:' + pct + '%"></div></div>'
                     + '<span class="ga-geo-val">' + fmtNum(row.sessions) + '</span></div>';
            }).join('');
        });
    }

    // ── top pages ─────────────────────────────────────────────────────────────
    function loadPages() {
        document.getElementById('gaPages').innerHTML = '<div class="ga-loading">…</div>';
        post('top_pages', function (res) {
            if (!res.ok || !res.data || !res.data.length) {
                document.getElementById('gaPages').innerHTML = '<div class="text-muted fs-12">Немає даних</div>'; return;
            }
            document.getElementById('gaPages').innerHTML =
                '<table class="ga-pages-table"><thead><tr>'
                + '<th>Сторінка</th><th class="num">Перегляди</th><th class="num">Сесії</th><th class="num">Корист.</th>'
                + '</tr></thead><tbody>'
                + res.data.map(function (row) {
                    return '<tr><td><div class="ga-page-path">' + esc(row.path) + '</div>'
                         + '<div class="ga-page-title">' + esc(row.title) + '</div></td>'
                         + '<td class="num">' + fmtNum(row.pageviews) + '</td>'
                         + '<td class="num">' + fmtNum(row.sessions)  + '</td>'
                         + '<td class="num">' + fmtNum(row.users)     + '</td></tr>';
                }).join('')
                + '</tbody></table>';
        });
    }

    // ── helpers ───────────────────────────────────────────────────────────────
    function fmtNum(n)   { return (n || 0).toLocaleString('uk-UA'); }
    function fmtMoney(n) { return (n || 0).toLocaleString('uk-UA', {minimumFractionDigits:2, maximumFractionDigits:2}); }
    function fmtDur(sec) { sec=sec||0; var m=Math.floor(sec/60),s=sec%60; return m+':'+(s<10?'0':'')+s; }
    function esc(s)      { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    // ── orders by channel ────────────────────────────────────────────────────
    function loadOrdersChannels() {
        var card = document.querySelector('.ga-orders-card');
        if (currentSite !== 'off') {
            card.style.display = 'none';
            return;
        }
        card.style.display = '';
        document.getElementById('gaOrdersChannels').innerHTML = '<div class="ga-loading">Завантаження…</div>';
        post('orders_by_channel', function (res) {
            if (!res.ok || !res.data || !res.data.length) {
                document.getElementById('gaOrdersChannels').innerHTML = '<div class="text-muted fs-12">Немає даних за цей період</div>';
                return;
            }
            var maxOrders = res.data[0].orders_count || 1;
            var totalOrders = res.data.reduce(function(s,r){ return s + r.orders_count; }, 0);
            var totalRevenue = res.data.reduce(function(s,r){ return s + r.revenue; }, 0);

            var chClass = {
                'Google Ads':       'ga-ch-google-ads',
                'Facebook Ads':     'ga-ch-facebook',
                'Google Organic':   'ga-ch-organic',
                'Прямий':           'ga-ch-direct',
            };

            var html = '<table class="ga-orders-table"><thead><tr>'
                     + '<th>Канал</th><th>Замовлень</th><th class="num">Частка</th>'
                     + '<th class="num">Дохід, ₴</th><th class="num">Сер. чек, ₴</th></tr></thead><tbody>';

            res.data.forEach(function (row) {
                var cls    = chClass[row.channel] || 'ga-ch-other';
                var share  = totalOrders > 0 ? Math.round(row.orders_count / totalOrders * 100) : 0;
                var barPct = Math.round(row.orders_count / maxOrders * 100);
                html += '<tr>'
                     + '<td><span class="ga-ch-badge ' + cls + '">' + esc(row.channel) + '</span></td>'
                     + '<td><span style="font-weight:600">' + fmtNum(row.orders_count) + '</span>'
                     + ' <span style="display:inline-block;margin-left:6px"><span class="ga-orders-bar-wrap"><span class="ga-orders-bar" style="width:' + barPct + '%;display:block"></span></span></span></td>'
                     + '<td class="num">' + share + '%</td>'
                     + '<td class="num">' + fmtMoney(row.revenue) + '</td>'
                     + '<td class="num">' + fmtMoney(row.avg_check) + '</td>'
                     + '</tr>';
            });

            html += '</tbody><tfoot><tr style="font-weight:600; border-top:2px solid #e5e7eb">'
                 + '<td>Всього</td><td>' + fmtNum(totalOrders) + '</td><td class="num">100%</td>'
                 + '<td class="num">' + fmtMoney(totalRevenue) + '</td><td class="num"></td></tr></tfoot></table>';

            document.getElementById('gaOrdersChannels').innerHTML = html;
        });
    }

    function loadAll() {
        loadSummary();
        loadEcommerce();
        loadByDate();
        loadChannels();
        loadGeo();
        loadPages();
        loadOrdersChannels();
    }

    loadAll();
    window.addEventListener('resize', function () { if (chartData) renderChart(chartData); });
}());
</script>
