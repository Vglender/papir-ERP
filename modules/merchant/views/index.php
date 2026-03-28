<?php
// All data loaded via AJAX
?>
<style>
.mc-wrap { max-width: 900px; margin: 0 auto; padding: 28px 0 48px; }

/* ── Header ── */
.mc-head { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; }
.mc-head h1 { margin: 0; font-size: 20px; font-weight: 700; flex: 1; }
.mc-logo {
    width: 36px; height: 36px; border-radius: 8px;
    background: #fff; border: 1px solid var(--border);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.mc-logo svg { width: 22px; height: 22px; }
.mc-refresh-btn {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 12px; color: var(--text-muted); background: none;
    border: none; cursor: pointer; padding: 4px 8px; border-radius: 6px;
}
.mc-refresh-btn:hover { color: var(--blue); background: var(--bg-soft, #f1f5f9); }
.mc-refresh-btn svg { transition: transform .4s; }
.mc-refresh-btn.loading svg { animation: spin .7s linear infinite; }

/* ── Auth status bar ── */
.mc-auth-bar {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;
    border: 1px solid var(--border); background: var(--bg-soft, #f8f9fa);
    font-size: 13px;
}
.mc-auth-bar.ok    { border-color: #bbf7d0; background: #f0fdf4; }
.mc-auth-bar.error { border-color: #fecaca; background: #fef2f2; }
.mc-auth-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
.mc-auth-dot.ok    { background: #16a34a; box-shadow: 0 0 0 3px #dcfce7; }
.mc-auth-dot.error { background: #dc2626; box-shadow: 0 0 0 3px #fee2e2; }
.mc-auth-dot.spin  { background: #94a3b8; box-shadow: 0 0 0 3px #e2e8f0; }
.mc-auth-label { font-weight: 600; }
.mc-auth-detail { color: var(--text-muted); margin-left: 4px; }
.mc-auth-action { margin-left: auto; }

/* ── Metric cards ── */
.mc-metrics { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 20px; }
.mc-metric {
    border: 1px solid var(--border); border-radius: 10px;
    padding: 16px 18px; background: #fff;
}
.mc-metric-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: var(--text-muted); margin-bottom: 6px; }
.mc-metric-val   { font-size: 26px; font-weight: 700; line-height: 1; }
.mc-metric-sub   { font-size: 11px; color: var(--text-muted); margin-top: 4px; }
.mc-metric-val.green  { color: #16a34a; }
.mc-metric-val.red    { color: #dc2626; }
.mc-metric-val.orange { color: #d97706; }
.mc-metric-val.blue   { color: #2563eb; }

/* ── Section cards ── */
.mc-section { border: 1px solid var(--border); border-radius: 10px; margin-bottom: 16px; overflow: hidden; }
.mc-section-head {
    display: flex; align-items: center; gap: 8px;
    padding: 12px 18px; border-bottom: 1px solid var(--border);
    background: var(--bg-soft, #f8f9fa); font-weight: 600; font-size: 14px;
}
.mc-section-head .badge { margin-left: auto; }
.mc-section-body { padding: 16px 18px; }
.mc-section-empty { padding: 16px 18px; color: var(--text-muted); font-size: 13px; }

/* ── Product stats table ── */
.mc-prod-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.mc-prod-table th { text-align: left; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: .4px; color: var(--text-muted); padding: 0 12px 8px 0; }
.mc-prod-table td { padding: 6px 12px 6px 0; border-top: 1px solid var(--border); }
.mc-prod-table td:first-child { font-weight: 500; }
.num-green  { color: #16a34a; font-weight: 600; }
.num-red    { color: #dc2626; font-weight: 600; }
.num-orange { color: #d97706; font-weight: 600; }
.num-muted  { color: var(--text-muted); }

/* ── Issues table ── */
.mc-issues-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.mc-issues-table th { text-align: left; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: .4px; color: var(--text-muted); padding: 0 12px 8px 0; }
.mc-issues-table td { padding: 7px 12px 7px 0; border-top: 1px solid var(--border); vertical-align: top; }
.mc-issues-table td:last-child { padding-right: 0; }
.mc-issue-title { font-weight: 600; font-size: 13px; margin-bottom: 2px; }
.mc-issue-detail { color: var(--text-muted); font-size: 12px; }
.mc-issue-doc { font-size: 11px; }
.mc-issue-doc a { color: var(--blue); }
.sev-critical { color: #dc2626; }
.sev-error    { color: #d97706; }
.sev-warning  { color: #ca8a04; }
.sev-info     { color: #2563eb; }

/* ── Feed row ── */
.mc-feed-row {
    display: flex; align-items: center; gap: 14px; padding: 10px 0;
    border-top: 1px solid var(--border); font-size: 13px;
}
.mc-feed-row:first-child { border-top: none; padding-top: 0; }
.mc-feed-status { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .3px; padding: 2px 7px; border-radius: 4px; flex-shrink: 0; }
.mc-feed-status.ok      { background: #dcfce7; color: #15803d; }
.mc-feed-status.warning { background: #fef9c3; color: #92400e; }
.mc-feed-status.error   { background: #fee2e2; color: #991b1b; }
.mc-feed-meta { color: var(--text-muted); font-size: 12px; margin-left: auto; }

/* ── Account issues ── */
.mc-acct-issue {
    padding: 10px 0; border-top: 1px solid var(--border); font-size: 13px;
}
.mc-acct-issue:first-child { border-top: none; padding-top: 0; }
.mc-acct-issue-head { display: flex; align-items: center; gap: 8px; margin-bottom: 3px; }
.mc-acct-issue-title { font-weight: 600; }

/* ── Skeleton ── */
.mc-skeleton { height: 14px; border-radius: 4px; background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%); background-size: 200%; animation: shimmer 1.2s infinite; }
@keyframes shimmer { from { background-position: 200% 0; } to { background-position: -200% 0; } }
.mc-skel-row { display: flex; gap: 8px; margin-bottom: 8px; }

@keyframes spin { to { transform: rotate(360deg); } }
</style>

<div class="mc-wrap">

    <!-- Header -->
    <div class="mc-head">
        <div class="mc-logo">
            <svg viewBox="0 0 24 24" fill="none">
                <rect x="3" y="3" width="18" height="18" rx="3" fill="#4285F4" opacity=".12"/>
                <rect x="3" y="3" width="18" height="18" rx="3" stroke="#4285F4" stroke-width="1.5"/>
                <path d="M7 9h10M7 13h7" stroke="#4285F4" stroke-width="1.5" stroke-linecap="round"/>
                <circle cx="16" cy="16" r="2" fill="#34A853"/>
            </svg>
        </div>
        <h1>Google Merchant Center</h1>
        <button type="button" class="mc-refresh-btn" id="mcRefreshBtn">
            <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M13.5 2.5A7 7 0 1 0 14.9 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M14 2.5V6h-3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Оновити
        </button>
    </div>

    <!-- Auth status bar -->
    <div class="mc-auth-bar" id="mcAuthBar">
        <div class="mc-auth-dot spin" id="mcAuthDot"></div>
        <span class="mc-auth-label" id="mcAuthLabel">Перевірка авторизації...</span>
        <span class="mc-auth-detail" id="mcAuthDetail"></span>
        <div class="mc-auth-action" id="mcAuthAction"></div>
    </div>

    <!-- Performance metrics (28 days) -->
    <div class="mc-metrics" id="mcMetrics">
        <div class="mc-metric"><div class="mc-metric-label">Покази</div><div class="mc-metric-val blue" id="mImpr">—</div><div class="mc-metric-sub">за 28 днів</div></div>
        <div class="mc-metric"><div class="mc-metric-label">Кліки</div><div class="mc-metric-val blue" id="mClicks">—</div><div class="mc-metric-sub">за 28 днів</div></div>
        <div class="mc-metric"><div class="mc-metric-label">CTR</div><div class="mc-metric-val" id="mCtr">—</div><div class="mc-metric-sub">за 28 днів</div></div>
        <div class="mc-metric"><div class="mc-metric-label">Замовлення</div><div class="mc-metric-val green" id="mOrders">—</div><div class="mc-metric-sub">за 28 днів</div></div>
    </div>

    <!-- Product status by destination -->
    <div class="mc-section" id="secProducts">
        <div class="mc-section-head">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="1" y="1" width="6" height="6" rx="1.5" fill="currentColor" opacity=".9"/><rect x="9" y="1" width="6" height="6" rx="1.5" fill="currentColor" opacity=".5"/><rect x="1" y="9" width="6" height="6" rx="1.5" fill="currentColor" opacity=".5"/><rect x="9" y="9" width="6" height="6" rx="1.5" fill="currentColor" opacity=".3"/></svg>
            Статус товарів
        </div>
        <div class="mc-section-body" id="prodStatsBody">
            <div class="mc-skel-row"><div class="mc-skeleton" style="width:30%"></div><div class="mc-skeleton" style="width:10%"></div><div class="mc-skeleton" style="width:10%"></div><div class="mc-skeleton" style="width:10%"></div></div>
            <div class="mc-skel-row"><div class="mc-skeleton" style="width:30%"></div><div class="mc-skeleton" style="width:10%"></div><div class="mc-skeleton" style="width:10%"></div><div class="mc-skeleton" style="width:10%"></div></div>
        </div>
    </div>

    <!-- Top product issues -->
    <div class="mc-section" id="secIssues">
        <div class="mc-section-head">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.4"/><path d="M8 5v4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="8" cy="11.5" r=".8" fill="currentColor"/></svg>
            Проблеми товарів
            <span class="badge badge-red" id="issuesBadge" style="display:none"></span>
        </div>
        <div id="issuesBody">
            <div class="mc-section-body">
                <div class="mc-skel-row"><div class="mc-skeleton" style="width:60%"></div><div class="mc-skeleton" style="width:10%"></div></div>
                <div class="mc-skel-row"><div class="mc-skeleton" style="width:50%"></div><div class="mc-skeleton" style="width:10%"></div></div>
            </div>
        </div>
    </div>

    <!-- Account-level issues -->
    <div class="mc-section" id="secAcct">
        <div class="mc-section-head">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 1.5L14.5 13H1.5L8 1.5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M8 6v3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="8" cy="11" r=".7" fill="currentColor"/></svg>
            Проблеми акаунту
            <span class="badge badge-red" id="acctBadge" style="display:none"></span>
        </div>
        <div id="acctBody">
            <div class="mc-section-body">
                <div class="mc-skel-row"><div class="mc-skeleton" style="width:55%"></div></div>
            </div>
        </div>
    </div>

    <!-- Datafeed statuses -->
    <div class="mc-section" id="secFeeds">
        <div class="mc-section-head">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="2" y="4" width="12" height="9" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M5 4V3a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v1" stroke="currentColor" stroke-width="1.4"/><path d="M5 8h6M5 10.5h4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" opacity=".6"/></svg>
            Фіди даних
        </div>
        <div id="feedsBody">
            <div class="mc-section-body">
                <div class="mc-skel-row"><div class="mc-skeleton" style="width:70%"></div></div>
            </div>
        </div>
    </div>

    <!-- Feed -->
    <div class="mc-section">
        <div class="mc-section-head">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M2 2h5v5H2zM9 2h5v5H9zM2 9h5v5H2z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><circle cx="11.5" cy="11.5" r="2.5" stroke="currentColor" stroke-width="1.3"/></svg>
            XML-фід для Merchant Center
        </div>
        <div class="mc-section-body">
            <p style="font-size:13px; color:var(--text-muted); margin:0 0 12px">
                Фід генерується з таблиць Papir у реальному часі. Додайте URL до Merchant Center: <b>Feeds → + Add feed → Scheduled fetch</b>.
            </p>
            <div style="display:flex; flex-direction:column; gap:8px">
                <?php
                $feedUrls = array(
                    array('label' => 'Всі товари',           'url' => 'https://papir.officetorg.com.ua/integr/merchant/feed'),
                    array('label' => 'Тільки в наявності',   'url' => 'https://papir.officetorg.com.ua/integr/merchant/feed?only_stock=1'),
                );
                foreach ($feedUrls as $f): ?>
                <div style="display:flex; align-items:center; gap:8px">
                    <span style="font-size:12px; width:150px; color:var(--text-muted); flex-shrink:0"><?php echo htmlspecialchars($f['label']); ?></span>
                    <code style="flex:1; background:var(--bg-soft,#f8f9fa); border:1px solid var(--border); border-radius:5px; padding:5px 10px; font-size:12px; overflow:auto; white-space:nowrap"><?php echo htmlspecialchars($f['url']); ?></code>
                    <button type="button" class="btn btn-ghost btn-xs feed-copy-btn" data-url="<?php echo htmlspecialchars($f['url']); ?>">Копіювати</button>
                    <a href="<?php echo htmlspecialchars($f['url']); ?>" target="_blank" class="btn btn-ghost btn-xs">Відкрити</a>
                </div>
                <?php endforeach; ?>
            </div>
            <p style="font-size:12px; color:var(--text-muted); margin:12px 0 0">
                Параметри: <code>?only_stock=1</code> — тільки в наявності &nbsp;·&nbsp;
                <code>?category_id=N</code> — тільки категорія &nbsp;·&nbsp;
                <code>?limit=100</code> — перші N товарів (для тесту)
            </p>
        </div>
    </div>

    <!-- Static info -->
    <div class="mc-section">
        <div class="mc-section-head">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.4"/><path d="M8 7v5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="8" cy="4.5" r=".8" fill="currentColor"/></svg>
            Конфігурація
        </div>
        <div class="mc-section-body">
            <dl style="display:grid; grid-template-columns: 160px 1fr; gap: 5px 12px; font-size:13px; margin:0">
                <dt style="color:var(--text-muted)">Merchant ID</dt>     <dd style="margin:0; font-family:monospace">121039527</dd>
                <dt style="color:var(--text-muted)">Redirect URI</dt>    <dd style="margin:0; font-size:12px">https://officetorg.com.ua/webhooks/oauth2callback.php</dd>
                <dt style="color:var(--text-muted)">Токен</dt>           <dd style="margin:0; font-size:12px">/var/sqript/Merchant/token.json</dd>
                <dt style="color:var(--text-muted)">Credentials</dt>     <dd style="margin:0; font-size:12px">/var/sqript/Merchant/credentials.json</dd>
            </dl>
        </div>
    </div>

</div>

<script>
(function () {
    var statusLoaded = false;
    var statsLoaded  = false;

    // ── helpers ───────────────────────────────────────────────────────────
    function fmtNum(n) {
        return n === null || n === undefined ? '—'
            : Number(n).toLocaleString('uk-UA');
    }
    function sevClass(s) {
        var m = {'critical':'sev-critical','error':'sev-error','warning':'sev-warning','suggestion':'sev-info'};
        return m[s] || 'sev-info';
    }
    function feedStatusClass(s) {
        if (s === 'success') return 'ok';
        if (s === 'in progress' || s === 'pending') return 'warning';
        return 'error';
    }
    function escHtml(s) {
        return String(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // ── Auth status ───────────────────────────────────────────────────────
    function loadStatus() {
        var bar    = document.getElementById('mcAuthBar');
        var dot    = document.getElementById('mcAuthDot');
        var label  = document.getElementById('mcAuthLabel');
        var detail = document.getElementById('mcAuthDetail');
        var action = document.getElementById('mcAuthAction');

        bar.className = 'mc-auth-bar';
        dot.className = 'mc-auth-dot spin';
        label.textContent = 'Перевірка авторизації...';
        detail.textContent = '';
        action.innerHTML   = '';

        fetch('/integr/merchant/api/get_status')
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.authorized) {
                    bar.className  = 'mc-auth-bar ok';
                    dot.className  = 'mc-auth-dot ok';
                    label.textContent = 'Авторизований';
                    if (d.access_expires) {
                        var exp  = new Date(d.access_expires.replace(' ','T'));
                        var diff = Math.round((exp - new Date()) / 1000);
                        detail.textContent = diff > 0
                            ? '· access token ще ' + fmtTtl(diff)
                            : '· access token протух (буде оновлено)';
                    }
                    if (!statsLoaded) { loadStats(); }
                } else {
                    bar.className  = 'mc-auth-bar error';
                    dot.className  = 'mc-auth-dot error';
                    label.textContent = d.error || 'Не авторизовано';
                    if (d.auth_url) {
                        action.innerHTML =
                            '<a href="' + escHtml(d.auth_url) + '" target="_blank" class="btn btn-primary btn-sm">Авторизуватись через Google</a>' +
                            ' <button type="button" class="btn btn-ghost btn-sm" id="recheckBtn">Перевірити знову</button>';
                        document.getElementById('recheckBtn').addEventListener('click', loadStatus);
                    }
                }
                statusLoaded = true;
            })
            .catch(function(e) {
                bar.className = 'mc-auth-bar error';
                dot.className = 'mc-auth-dot error';
                label.textContent = 'Помилка: ' + String(e);
            });
    }

    // ── Stats ─────────────────────────────────────────────────────────────
    function loadStats() {
        fetch('/integr/merchant/api/get_stats')
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.ok && d.error) {
                    showStatsError(d.error);
                    return;
                }
                renderPerformance(d.performance);
                renderProducts(d.products);
                renderIssues(d.top_issues);
                renderAcctIssues(d.account);
                renderFeeds(d.feeds);
                statsLoaded = true;
            })
            .catch(function(e) { showStatsError(String(e)); });
    }

    function showStatsError(msg) {
        ['prodStatsBody','issuesBody','acctBody','feedsBody'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.innerHTML = '<div class="mc-section-empty text-red">' + escHtml(msg) + '</div>';
        });
    }

    // ── Performance ───────────────────────────────────────────────────────
    function renderPerformance(p) {
        if (!p || p.error) {
            document.getElementById('mImpr').textContent = '—';
            document.getElementById('mClicks').textContent = '—';
            document.getElementById('mCtr').textContent = '—';
            document.getElementById('mOrders').textContent = '—';
            return;
        }
        document.getElementById('mImpr').textContent   = fmtNum(p.impressions);
        document.getElementById('mClicks').textContent = fmtNum(p.clicks);
        document.getElementById('mCtr').textContent    = p.ctr + '%';
        document.getElementById('mOrders').textContent = fmtNum(p.orders);
        // Color CTR
        var ctrEl = document.getElementById('mCtr');
        ctrEl.className = 'mc-metric-val ' + (p.ctr >= 1 ? 'green' : p.ctr >= 0.3 ? 'orange' : 'red');
    }

    // ── Product stats ─────────────────────────────────────────────────────
    function renderProducts(products) {
        var el = document.getElementById('prodStatsBody');
        if (!products || !products.length) {
            el.innerHTML = '<div class="mc-section-empty">Даних немає</div>';
            return;
        }
        var html = '<table class="mc-prod-table"><thead><tr>'
            + '<th>Призначення</th><th>Активні</th><th>На модерації</th><th>Відхилені</th><th>Закінчуються</th>'
            + '</tr></thead><tbody>';
        for (var i = 0; i < products.length; i++) {
            var p = products[i];
            var dest = p.destination === 'Shopping' ? 'Shopping Ads' : p.destination;
            html += '<tr>'
                + '<td>' + escHtml(dest) + '</td>'
                + '<td class="num-green">'  + fmtNum(p.active) + '</td>'
                + '<td class="num-orange">' + fmtNum(p.pending) + '</td>'
                + '<td class="num-red">'    + fmtNum(p.disapproved) + '</td>'
                + '<td class="num-muted">'  + fmtNum(p.expiring) + '</td>'
                + '</tr>';
        }
        html += '</tbody></table>';
        el.innerHTML = html;
    }

    // ── Product issues ────────────────────────────────────────────────────
    function renderIssues(issues) {
        var el    = document.getElementById('issuesBody');
        var badge = document.getElementById('issuesBadge');

        if (!issues || !issues.length) {
            el.innerHTML = '<div class="mc-section-empty" style="color:#16a34a">Проблем не виявлено</div>';
            return;
        }

        var totalItems = 0;
        issues.forEach(function(i) { totalItems += i.num_items; });
        badge.style.display = '';
        badge.textContent   = issues.length;

        var html = '<div style="padding:16px 18px"><table class="mc-issues-table"><thead><tr>'
            + '<th>Проблема</th><th>Товарів</th><th>Тип</th><th style="width:90px">Сервіс</th>'
            + '</tr></thead><tbody>';

        for (var i = 0; i < issues.length; i++) {
            var iss = issues[i];
            var sev = iss.servability || '';
            html += '<tr>'
                + '<td>'
                +   '<div class="mc-issue-title">' + escHtml(iss.description || iss.code) + '</div>'
                +   (iss.detail ? '<div class="mc-issue-detail">' + escHtml(iss.detail) + '</div>' : '')
                +   (iss.doc ? '<div class="mc-issue-doc"><a href="' + escHtml(iss.doc) + '" target="_blank">Довідка →</a></div>' : '')
                + '</td>'
                + '<td style="white-space:nowrap; font-weight:600">' + fmtNum(iss.num_items) + '</td>'
                + '<td class="' + sevClass(sev) + '" style="white-space:nowrap">' + escHtml(sev) + '</td>'
                + '<td style="font-size:11px; color:var(--text-muted)">' + escHtml(iss.resolution || '') + '</td>'
                + '</tr>';
        }
        html += '</tbody></table></div>';
        el.innerHTML = html;
    }

    // ── Account issues ────────────────────────────────────────────────────
    function renderAcctIssues(account) {
        var el    = document.getElementById('acctBody');
        var badge = document.getElementById('acctBadge');

        if (!account || account.error) {
            el.innerHTML = '<div class="mc-section-empty">' + escHtml((account && account.error) || 'Немає даних') + '</div>';
            return;
        }

        var issues = account.issues || [];

        if (!issues.length) {
            el.innerHTML = '<div class="mc-section-empty" style="color:#16a34a">Проблем акаунту не виявлено</div>';
            return;
        }

        badge.style.display = '';
        badge.textContent   = issues.length;

        var html = '<div style="padding:0 18px 4px">';
        for (var i = 0; i < issues.length; i++) {
            var iss = issues[i];
            html += '<div class="mc-acct-issue">'
                + '<div class="mc-acct-issue-head">'
                +   '<span class="' + sevClass(iss.severity) + '" style="font-size:11px; font-weight:700; text-transform:uppercase">' + escHtml(iss.severity) + '</span>'
                +   '<span class="mc-acct-issue-title">' + escHtml(iss.title) + '</span>'
                + '</div>'
                + (iss.detail ? '<div style="font-size:12px; color:var(--text-muted)">' + escHtml(iss.detail) + '</div>' : '')
                + (iss.doc ? '<div style="font-size:11px; margin-top:3px"><a href="' + escHtml(iss.doc) + '" target="_blank" style="color:var(--blue)">Довідка →</a></div>' : '')
                + '</div>';
        }
        html += '</div>';
        el.innerHTML = html;
    }

    // ── Feeds ─────────────────────────────────────────────────────────────
    function renderFeeds(feeds) {
        var el = document.getElementById('feedsBody');

        if (!feeds || feeds.error || !feeds.length) {
            el.innerHTML = '<div class="mc-section-empty">' + escHtml((feeds && feeds.error) || 'Фідів не знайдено') + '</div>';
            return;
        }

        var html = '<div style="padding:4px 18px">';
        for (var i = 0; i < feeds.length; i++) {
            var f = feeds[i];
            var cls = feedStatusClass(f.status);
            var statusLabel = {
                'success'    : 'Успішно',
                'in progress': 'В обробці',
                'pending'    : 'Очікує',
                'fetch error': 'Помилка',
            }[f.status] || f.status;

            html += '<div class="mc-feed-row">'
                + '<span class="mc-feed-status ' + cls + '">' + escHtml(statusLabel) + '</span>'
                + '<span>Фід #' + escHtml(String(f.id)) + '</span>'
                + '<span style="font-size:12px; color:var(--text-muted)">'
                +   fmtNum(f.items_valid) + ' / ' + fmtNum(f.items_total) + ' товарів'
                + '</span>'
                + (f.errors   ? '<span class="badge badge-red">'    + f.errors   + ' помилок</span>' : '')
                + (f.warnings ? '<span class="badge badge-orange">'  + f.warnings + ' попереджень</span>' : '')
                + '<span class="mc-feed-meta">'
                +   (f.last_upload ? 'Оновлено: ' + escHtml(f.last_upload) : '')
                + '</span>'
                + '</div>';
        }
        html += '</div>';
        el.innerHTML = html;
    }

    function fmtTtl(sec) {
        if (sec < 60)    return sec + 'с';
        if (sec < 3600)  return Math.round(sec / 60) + 'хв';
        if (sec < 86400) return Math.round(sec / 3600) + 'г';
        return Math.round(sec / 86400) + 'д';
    }

    // ── Refresh button ────────────────────────────────────────────────────
    document.getElementById('mcRefreshBtn').addEventListener('click', function() {
        var btn = this;
        btn.classList.add('loading');
        statusLoaded = false;
        statsLoaded  = false;
        loadStatus();
        setTimeout(function() { btn.classList.remove('loading'); }, 1500);
    });

    // ── Feed copy buttons ─────────────────────────────────────────────────
    document.querySelectorAll('.feed-copy-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var url = this.getAttribute('data-url');
            var self = this;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function() {
                    self.textContent = 'Скопійовано!';
                    setTimeout(function() { self.textContent = 'Копіювати'; }, 1500);
                });
            }
        });
    });

    // ── Init ──────────────────────────────────────────────────────────────
    loadStatus();
}());
</script>
