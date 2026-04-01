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

/* ── Automated Discounts section ── */
.mc-ad-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 4px; }
.mc-ad-site-label {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .4px; color: var(--text-muted);
    display: flex; align-items: center; gap: 6px; margin-bottom: 10px;
}
.mc-ad-bars { display: flex; flex-direction: column; gap: 6px; }
.mc-ad-bar-row { display: flex; align-items: center; gap: 8px; font-size: 12px; }
.mc-ad-bar-label { width: 130px; flex-shrink: 0; color: var(--text-muted); }
.mc-ad-bar-track { flex: 1; height: 8px; border-radius: 4px; background: #f1f5f9; overflow: hidden; }
.mc-ad-bar-fill { height: 100%; border-radius: 4px; transition: width .4s; }
.mc-ad-bar-val { width: 50px; text-align: right; font-weight: 600; flex-shrink: 0; }
.mc-ad-bar-pct { width: 38px; text-align: right; color: var(--text-muted); flex-shrink: 0; }
.mc-ad-bar-fill.green  { background: #16a34a; }
.mc-ad-bar-fill.orange { background: #d97706; }
.mc-ad-bar-fill.red    { background: #dc2626; }
.mc-ad-divider { grid-column: 1/-1; border: none; border-top: 1px solid var(--border); margin: 4px 0 10px; }
.mc-ad-problems { margin-top: 12px; }
.mc-ad-prob-tabs { display: flex; gap: 0; border-bottom: 1px solid var(--border); margin-bottom: 0; }
.mc-ad-prob-tab {
    padding: 6px 14px; font-size: 12px; font-weight: 600; cursor: pointer;
    border: none; background: none; color: var(--text-muted);
    border-bottom: 2px solid transparent; margin-bottom: -1px;
}
.mc-ad-prob-tab.active { color: var(--blue); border-bottom-color: var(--blue); }
.mc-ad-prob-pane { display: none; }
.mc-ad-prob-pane.active { display: block; }
.mc-ad-prob-table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 0; }
.mc-ad-prob-table th { text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; color: var(--text-muted); padding: 8px 8px 6px 0; border-bottom: 1px solid var(--border); }
.mc-ad-prob-table td { padding: 5px 8px 5px 0; border-top: 1px solid var(--border); vertical-align: middle; }
.mc-ad-prob-table tr:first-child td { border-top: none; }
.mc-ad-prob-table .td-name { max-width: 220px; }
.mc-ad-prob-table .td-name span { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mc-ad-prob-table .td-reason { color: var(--text-muted); font-size: 11px; max-width: 200px; }
.mc-ad-load-more { display: block; width: 100%; margin-top: 8px; font-size: 12px; text-align: center; padding: 5px; }

/* ── GAD Google data ── */
.mc-gad-status-bar {
    display: flex; align-items: center; gap: 10px; padding: 10px 14px;
    border-radius: 8px; border: 1px solid var(--border);
    background: var(--bg-soft, #f8f9fa); font-size: 13px; margin-bottom: 14px;
}
.mc-gad-status-bar.active  { border-color: #bbf7d0; background: #f0fdf4; }
.mc-gad-status-bar.pending { border-color: #fed7aa; background: #fff7ed; }
.mc-gad-status-bar.not_configured { border-color: #fecaca; background: #fef2f2; }
.mc-gad-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
.mc-gad-dot.active  { background: #16a34a; box-shadow: 0 0 0 3px #dcfce7; }
.mc-gad-dot.pending { background: #f97316; box-shadow: 0 0 0 3px #ffedd5; }
.mc-gad-dot.spin    { background: #94a3b8; box-shadow: 0 0 0 3px #e2e8f0; animation: spin .8s linear infinite; }
.mc-gad-dot.error   { background: #dc2626; box-shadow: 0 0 0 3px #fee2e2; }
.mc-gad-metrics { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 14px; }
.mc-gad-metric { text-align: center; }
.mc-gad-metric-val { font-size: 22px; font-weight: 700; line-height: 1.1; }
.mc-gad-metric-label { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
.mc-gad-sample-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.mc-gad-sample-table th { text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; color: var(--text-muted); padding: 0 10px 6px 0; border-bottom: 1px solid var(--border); }
.mc-gad-sample-table td { padding: 5px 10px 5px 0; border-top: 1px solid var(--border); vertical-align: middle; }
.mc-gad-section-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--text-muted); margin: 14px 0 6px; }
.mc-gad-toggle { cursor: pointer; user-select: none; display: inline-flex; align-items: center; gap: 2px; }
.mc-gad-toggle:hover { color: var(--blue); }
.mc-gad-arrow { font-style: normal; opacity: .6; }

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

/* ── Page-level tabs ── */
.mc-page-tabs { display:flex; border-bottom: 2px solid var(--border); margin-bottom: 20px; gap: 0; }
.mc-page-tab {
    padding: 9px 18px; font-size: 14px; font-weight: 600; cursor: pointer;
    border: none; background: none; color: var(--text-muted);
    border-bottom: 3px solid transparent; margin-bottom: -2px; transition: color .15s;
}
.mc-page-tab.active { color: var(--blue, #2563eb); border-bottom-color: var(--blue, #2563eb); }
.mc-page-tab:hover:not(.active) { color: #374151; }
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

    <!-- Page-level tab bar -->
    <div class="mc-page-tabs">
        <button type="button" class="mc-page-tab active" data-mctab="overview">Огляд</button>
        <button type="button" class="mc-page-tab" data-mctab="autodiscount">⭐ Automated Discounts</button>
    </div>

    <!-- Auth status bar -->
    <div class="mc-auth-bar" id="mcAuthBar" data-mctab="overview">
        <div class="mc-auth-dot spin" id="mcAuthDot"></div>
        <span class="mc-auth-label" id="mcAuthLabel">Перевірка авторизації...</span>
        <span class="mc-auth-detail" id="mcAuthDetail"></span>
        <div class="mc-auth-action" id="mcAuthAction"></div>
    </div>

    <!-- Product status by destination -->
    <div class="mc-section" id="secProducts" data-mctab="overview">
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
    <div class="mc-section" id="secIssues" data-mctab="overview">
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
    <div class="mc-section" id="secAcct" data-mctab="overview">
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
    <div class="mc-section" id="secFeeds" data-mctab="overview">
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

    <!-- Automated Discounts Analysis -->
    <div class="mc-section" id="secAutodiscount" data-mctab="autodiscount" style="display:none">
        <div class="mc-section-head">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <path d="M8 1.5l1.6 3.2 3.5.5-2.55 2.48.6 3.5L8 9.5l-3.15 1.68.6-3.5L2.9 5.2l3.5-.5L8 1.5z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/>
            </svg>
            Automated Discounts
            <span class="badge" id="adGadBadge" style="display:none"></span>
            <button type="button" class="mc-refresh-btn" id="adRefreshBtn" style="margin-left:auto">
                <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M13.5 2.5A7 7 0 1 0 14.9 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M14 2.5V6h-3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Оновити
            </button>
        </div>
        <!-- Вкладки -->
        <div style="display:flex; border-bottom:1px solid var(--border); padding: 0 18px;">
            <button type="button" class="mc-ad-prob-tab active" id="adTabGoogle" data-adtab="google">📡 Google API</button>
            <button type="button" class="mc-ad-prob-tab" id="adTabLocal" data-adtab="local">🗄 Наш фід</button>
        </div>
        <!-- Панель Google API -->
        <div id="adPaneGoogle">
            <div class="mc-section-body">
                <div class="mc-skel-row"><div class="mc-skeleton" style="width:40%"></div><div class="mc-skeleton" style="width:40%"></div></div>
                <div class="mc-skel-row"><div class="mc-skeleton" style="width:55%"></div><div class="mc-skeleton" style="width:30%"></div></div>
            </div>
        </div>
        <!-- Панель локального аналізу -->
        <div id="adPaneLocal" style="display:none">
            <div id="adBody">
                <div class="mc-section-body">
                    <div class="mc-skel-row"><div class="mc-skeleton" style="width:40%"></div><div class="mc-skeleton" style="width:40%"></div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Feed -->
    <div class="mc-section" data-mctab="overview">
        <div class="mc-section-head">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M2 2h5v5H2zM9 2h5v5H9zM2 9h5v5H2z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><circle cx="11.5" cy="11.5" r="2.5" stroke="currentColor" stroke-width="1.3"/></svg>
            XML-фіди для Merchant Center
        </div>
        <div class="mc-section-body" style="padding-bottom:8px">
            <p style="font-size:13px; color:var(--text-muted); margin:0 0 14px">
                Додайте URL до Merchant Center: <b>Feeds → + Add feed → Scheduled fetch</b>.
            </p>

            <!-- Офіс Торг -->
            <div style="margin-bottom:18px">
                <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--text-muted); margin-bottom:8px; display:flex; align-items:center; gap:6px">
                    <span class="badge badge-blue">off</span> Офіс Торг — officetorg.com.ua
                </div>
                <?php
                $offFeeds = array(
                    array(
                        'label' => 'Статичний (щодня 5:00, тільки в наявності)',
                        'url'   => 'https://officetorg.com.ua/merchant_feed.xml',
                        'note'  => 'основний',
                    ),
                    array(
                        'label' => 'Live — всі активні товари',
                        'url'   => 'https://papir.officetorg.com.ua/integr/merchant/feed',
                    ),
                    array(
                        'label' => 'Live — тільки в наявності',
                        'url'   => 'https://papir.officetorg.com.ua/integr/merchant/feed?only_stock=1',
                    ),
                );
                foreach ($offFeeds as $f): ?>
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px">
                    <span style="font-size:12px; width:220px; color:var(--text-muted); flex-shrink:0">
                        <?php echo htmlspecialchars($f['label']); ?>
                        <?php if (!empty($f['note'])): ?>
                            <span class="badge badge-green" style="margin-left:4px"><?php echo htmlspecialchars($f['note']); ?></span>
                        <?php endif; ?>
                    </span>
                    <code style="flex:1; background:var(--bg-soft,#f8f9fa); border:1px solid var(--border); border-radius:5px; padding:5px 10px; font-size:12px; overflow:auto; white-space:nowrap"><?php echo htmlspecialchars($f['url']); ?></code>
                    <button type="button" class="btn btn-ghost btn-xs feed-copy-btn" data-url="<?php echo htmlspecialchars($f['url']); ?>">Копіювати</button>
                    <a href="<?php echo htmlspecialchars($f['url']); ?>" target="_blank" class="btn btn-ghost btn-xs">Відкрити</a>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Menu Folder -->
            <div>
                <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--text-muted); margin-bottom:8px; display:flex; align-items:center; gap:6px">
                    <span class="badge badge-orange">mff</span> Menu Folder — menufolder.com.ua
                </div>
                <?php
                $mffFeeds = array(
                    array(
                        'label' => 'Статичний (щодня 5:30, тільки в наявності)',
                        'url'   => 'https://officetorg.com.ua/merchant_feed_mff.xml',
                        'note'  => 'основний',
                    ),
                    array(
                        'label' => 'Live — всі активні товари',
                        'url'   => 'https://papir.officetorg.com.ua/integr/merchant/feed_mff',
                    ),
                    array(
                        'label' => 'Live — тільки в наявності',
                        'url'   => 'https://papir.officetorg.com.ua/integr/merchant/feed_mff?only_stock=1',
                    ),
                );
                foreach ($mffFeeds as $f): ?>
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px">
                    <span style="font-size:12px; width:220px; color:var(--text-muted); flex-shrink:0">
                        <?php echo htmlspecialchars($f['label']); ?>
                        <?php if (!empty($f['note'])): ?>
                            <span class="badge badge-green" style="margin-left:4px"><?php echo htmlspecialchars($f['note']); ?></span>
                        <?php endif; ?>
                    </span>
                    <code style="flex:1; background:var(--bg-soft,#f8f9fa); border:1px solid var(--border); border-radius:5px; padding:5px 10px; font-size:12px; overflow:auto; white-space:nowrap"><?php echo htmlspecialchars($f['url']); ?></code>
                    <button type="button" class="btn btn-ghost btn-xs feed-copy-btn" data-url="<?php echo htmlspecialchars($f['url']); ?>">Копіювати</button>
                    <a href="<?php echo htmlspecialchars($f['url']); ?>" target="_blank" class="btn btn-ghost btn-xs">Відкрити</a>
                </div>
                <?php endforeach; ?>
            </div>

            <p style="font-size:12px; color:var(--text-muted); margin:10px 0 0">
                Параметри Live-фідів: <code>?only_stock=1</code> — тільки в наявності &nbsp;·&nbsp;
                <code>?category_id=N</code> — тільки категорія &nbsp;·&nbsp;
                <code>?limit=100</code> — перші N товарів (для тесту)
            </p>
        </div>
    </div>

    <!-- Static info -->
    <div class="mc-section" data-mctab="overview">
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

    // ── Page-level tabs ───────────────────────────────────────────────────
    function mcShowTab(tab) {
        document.querySelectorAll('[data-mctab]').forEach(function(el) {
            el.style.display = el.getAttribute('data-mctab') === tab ? '' : 'none';
        });
        document.querySelectorAll('.mc-page-tab').forEach(function(btn) {
            btn.classList.toggle('active', btn.getAttribute('data-mctab') === tab);
        });
    }
    document.querySelectorAll('.mc-page-tab').forEach(function(btn) {
        btn.addEventListener('click', function() {
            mcShowTab(this.getAttribute('data-mctab'));
        });
    });

    // Header title — click to go back to Огляд
    var headH1 = document.querySelector('.mc-head h1');
    if (headH1) {
        headH1.style.cursor = 'pointer';
        headH1.title = 'Огляд';
        headH1.addEventListener('click', function() { mcShowTab('overview'); });
    }

    // ── Inner tabs: Automated Discounts ───────────────────────────────────
    document.querySelectorAll('[data-adtab]').forEach(function(btn) {
        btn.addEventListener('click', function() { adShowTab(this.getAttribute('data-adtab')); });
    });

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

    // ── Automated Discounts — перемикання вкладок ─────────────────────────
    function adShowTab(tab) {
        document.getElementById('adPaneGoogle').style.display = tab === 'google' ? '' : 'none';
        document.getElementById('adPaneLocal').style.display  = tab === 'local'  ? '' : 'none';
        document.querySelectorAll('[data-adtab]').forEach(function(b) {
            b.classList.toggle('active', b.getAttribute('data-adtab') === tab);
        });
    }

    // ── GAD: Google API data ──────────────────────────────────────────────
    function loadGadStatus() {
        fetch('/integr/merchant/api/get_gad_status')
            .then(function(r) { return r.json(); })
            .then(function(d) { renderGadStatus(d); })
            .catch(function(e) {
                document.getElementById('adPaneGoogle').innerHTML =
                    '<div class="mc-section-empty text-red">' + escHtml(String(e)) + '</div>';
            });
    }

    // ── GAD collapsible sections ──────────────────────────────────────────
    var _gadSections = {}; // id → {rows, renderFn, pageSize, shown}

    function gadCollapsibleSection(id, label, rows, theadHtml, renderFn, pageSize) {
        _gadSections[id] = { rows: rows, renderFn: renderFn, pageSize: pageSize, shown: pageSize };
        var firstRows = rows.slice(0, pageSize).map(renderFn).join('');
        return '<div class="mc-gad-section-label mc-gad-toggle" id="gadLabel_' + id + '" onclick="gadToggle(\'' + id + '\')">'
             + label + '<span class="mc-gad-arrow" id="gadArrow_' + id + '"> ▼</span></div>'
             + '<div id="gadSec_' + id + '">'
             + '<table class="mc-gad-sample-table"><thead>' + theadHtml + '</thead>'
             + '<tbody id="gadBody_' + id + '">' + firstRows + '</tbody></table>'
             + gadCtrlBar(id, rows.length, pageSize)
             + '</div>';
    }

    function gadCtrlBar(id, total, shown) {
        var remaining = total - shown;
        var html = '<div id="gadCtrl_' + id + '" style="display:flex;gap:6px;margin-top:6px">';
        if (remaining > 0) {
            html += '<button type="button" class="btn btn-ghost btn-xs" id="gadMore_' + id + '">'
                  + 'Показати ще ' + remaining + ' ↓</button>';
        }
        if (shown > 0 && total > 0) {
            html += '<button type="button" class="btn btn-ghost btn-xs" id="gadCollapse_' + id + '">'
                  + 'Згорнути ↑</button>';
        }
        return html + '</div>';
    }

    function gadToggle(id) {
        var sec   = document.getElementById('gadSec_' + id);
        var arrow = document.getElementById('gadArrow_' + id);
        if (!sec) return;
        var collapsed = sec.style.display === 'none';
        sec.style.display   = collapsed ? '' : 'none';
        arrow.textContent   = collapsed ? ' ▼' : ' ▶';
    }

    function gadBindSection(id) {
        var moreBtn     = document.getElementById('gadMore_' + id);
        var collapseBtn = document.getElementById('gadCollapse_' + id);
        var sec         = _gadSections[id];
        if (!sec) return;

        if (moreBtn) {
            moreBtn.addEventListener('click', function() {
                var tbody     = document.getElementById('gadBody_' + id);
                var nextChunk = sec.rows.slice(sec.shown, sec.shown + sec.pageSize);
                var frag      = document.createDocumentFragment();
                nextChunk.forEach(function(row) {
                    var tmp = document.createElement('tbody');
                    tmp.innerHTML = sec.renderFn(row);
                    while (tmp.firstChild) { frag.appendChild(tmp.firstChild); }
                });
                tbody.appendChild(frag);
                sec.shown += nextChunk.length;

                var remaining = sec.rows.length - sec.shown;
                if (remaining > 0) {
                    moreBtn.textContent = 'Показати ще ' + remaining + ' ↓';
                } else {
                    moreBtn.style.display = 'none';
                }
            });
        }

        if (collapseBtn) {
            collapseBtn.addEventListener('click', function() {
                var tbody = document.getElementById('gadBody_' + id);
                var rows  = tbody.querySelectorAll('tr');
                // Залишаємо перші pageSize рядків
                for (var i = sec.pageSize; i < rows.length; i++) {
                    rows[i].parentNode.removeChild(rows[i]);
                }
                sec.shown = sec.pageSize;
                // Відновлюємо кнопку "показати ще"
                var moreB = document.getElementById('gadMore_' + id);
                var remaining = sec.rows.length - sec.pageSize;
                if (remaining > 0) {
                    if (moreB) {
                        moreB.style.display = '';
                        moreB.textContent   = 'Показати ще ' + remaining + ' ↓';
                    }
                }
                // Скрол до заголовку секції
                var label = document.getElementById('gadLabel_' + id);
                if (label) { label.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
            });
        }
    }

    function renderGadStatus(d) {
        var pane  = document.getElementById('adPaneGoogle');
        var badge = document.getElementById('adGadBadge');
        _gadSections = {};

        if (!d.ok) {
            pane.innerHTML = '<div class="mc-section-body"><div class="mc-section-empty text-red">' + escHtml(d.error || 'Помилка API') + '</div></div>';
            return;
        }

        var statusLabels = { active: '✅ Активні знижки', pending: '⏳ Очікування', not_configured: '❌ Не налаштовано', has_issues: '⚠️ Є проблеми', api_error: '🔴 Помилка API' };
        var statusColors = { active: 'badge-green', pending: 'badge-orange', not_configured: 'badge-red', has_issues: 'badge-orange', api_error: 'badge-red' };
        badge.style.display = '';
        badge.textContent   = statusLabels[d.status] || d.status;
        badge.className     = 'badge ' + (statusColors[d.status] || 'badge-gray');

        var dotClass    = { active: 'active', pending: 'pending', not_configured: 'error', has_issues: 'pending', api_error: 'error' }[d.status] || 'spin';
        var statusTexts = {
            active:         'Google активно застосовує знижки (' + fmtNum(d.has_gad_price) + ' товарів зараз)',
            pending:        'Програму налаштовано, Google ще не застосовує знижки',
            not_configured: 'Жоден товар не має autoPricingMinPrice — фід не підключено до програми',
            has_issues:     'Є товари з проблемами, знижки можуть не працювати',
            api_error:      'Помилка API: ' + escHtml(d.error || ''),
        };
        var statusBar = '<div class="mc-gad-status-bar ' + d.status + '">'
            + '<div class="mc-gad-dot ' + dotClass + '"></div>'
            + '<span>' + (statusTexts[d.status] || d.status) + '</span>'
            + '<span style="margin-left:auto;font-size:11px;color:var(--text-muted)">Перевірено ' + fmtNum(d.total) + ' товарів (' + d.pages_fetched + ' стор.)</span>'
            + '</div>';

        var pctOpted = d.total > 0 ? Math.round(d.opted_in / d.total * 100) : 0;
        var pctPrior = d.total > 0 ? Math.round(d.has_prior_price / d.total * 100) : 0;
        var metrics = '<div class="mc-gad-metrics">'
            + gadMetric(d.opted_in,        pctOpted + '%', 'з autoPricingMinPrice', '#2563eb')
            + gadMetric(d.has_gad_price,   '',             'GAD-ціна активна (зараз)', '#16a34a')
            + gadMetric(d.has_prior_price, pctPrior + '%', 'Google моніторить (prior)', '#d97706')
            + gadMetric(d.issues_count,    '',             'disapproved (Shopping/Free)', d.issues_count > 0 ? '#dc2626' : '#16a34a')
            + '</div>';

        var html = '<div class="mc-section-body">' + statusBar + metrics;

        // Активні GAD
        if (d.sample_active && d.sample_active.length) {
            var theadActive = '<tr><th>ID</th><th>Назва</th><th style="text-align:right">Ціна</th>'
                + '<th style="text-align:right">Prior</th><th style="text-align:right">GAD ціна</th><th style="text-align:right">Min</th></tr>';
            html += gadCollapsibleSection('active',
                '🟢 Товари з активною GAD-ціною (' + d.sample_active.length + ')',
                d.sample_active, theadActive,
                function(p) {
                    var n = p.link ? '<a href="' + escHtml(p.link) + '" target="_blank" style="color:var(--blue)">' + escHtml(p.title) + '</a>' : escHtml(p.title);
                    return '<tr>'
                        + '<td style="color:var(--text-muted);font-size:11px">' + escHtml(p.offer_id) + '</td>'
                        + '<td style="max-width:200px"><span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + escHtml(p.title) + '">' + n + '</span></td>'
                        + '<td style="text-align:right">' + (p.price ? number2(p.price) : '—') + '</td>'
                        + '<td style="text-align:right;color:var(--text-muted)">' + (p.prior_price ? number2(p.prior_price) : '—') + '</td>'
                        + '<td style="text-align:right;color:#16a34a;font-weight:700">' + number2(p.gad_price) + '</td>'
                        + '<td style="text-align:right;color:var(--text-muted);font-size:11px">' + (p.min_price ? number2(p.min_price) : '—') + '</td>'
                        + '</tr>';
                }, 5);
        }

        // Pending (prior price)
        if (d.sample_pending && d.sample_pending.length) {
            var theadPending = '<tr><th>ID</th><th>Назва</th><th style="text-align:right">Ціна</th>'
                + '<th style="text-align:right">Sale</th><th style="text-align:right">Prior</th><th style="text-align:right">Min</th></tr>';
            html += gadCollapsibleSection('pending',
                '⏳ Google моніторить — prior price є (' + d.sample_pending.length + ')',
                d.sample_pending, theadPending,
                function(p) {
                    var n = p.link ? '<a href="' + escHtml(p.link) + '" target="_blank" style="color:var(--blue)">' + escHtml(p.title) + '</a>' : escHtml(p.title);
                    return '<tr>'
                        + '<td style="color:var(--text-muted);font-size:11px">' + escHtml(p.offer_id) + '</td>'
                        + '<td style="max-width:200px"><span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + escHtml(p.title) + '">' + n + '</span></td>'
                        + '<td style="text-align:right">' + (p.price ? number2(p.price) : '—') + '</td>'
                        + '<td style="text-align:right;color:var(--text-muted)">' + (p.sale_price ? number2(p.sale_price) : '—') + '</td>'
                        + '<td style="text-align:right;color:#d97706">' + (p.prior_price ? number2(p.prior_price) : '—') + '</td>'
                        + '<td style="text-align:right;font-size:11px">' + (p.min_price ? number2(p.min_price) : '—') + '</td>'
                        + '</tr>';
                }, 5);
        }

        // Disapproved
        if (d.issues && d.issues.length) {
            var theadIssues = '<tr><th>ID</th><th>Назва</th><th>Контексти</th></tr>';
            html += gadCollapsibleSection('issues',
                '⚠️ Disapproved у Shopping / Free Listings (' + d.issues_count + ')',
                d.issues, theadIssues,
                function(iss) {
                    return '<tr>'
                        + '<td style="color:var(--text-muted);font-size:11px;white-space:nowrap"><a href="/catalog?search=' + escHtml(iss.offer_id) + '" target="_blank" style="color:var(--blue)">' + escHtml(iss.offer_id) + '</a></td>'
                        + '<td style="max-width:220px"><span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + escHtml(iss.title) + '</span></td>'
                        + '<td style="font-size:11px;color:#dc2626">' + iss.contexts.join(', ') + '</td>'
                        + '</tr>';
                }, 20);
        }

        html += '</div>';
        pane.innerHTML = html;

        // Bind events для всіх секцій
        ['active', 'pending', 'issues'].forEach(gadBindSection);
    }

    function gadMetric(val, sub, label, color) {
        return '<div class="mc-gad-metric">'
            + '<div class="mc-gad-metric-val" style="color:' + color + '">' + fmtNum(val) + '</div>'
            + (sub ? '<div style="font-size:11px; color:var(--text-muted)">' + escHtml(sub) + '</div>' : '')
            + '<div class="mc-gad-metric-label">' + escHtml(label) + '</div>'
            + '</div>';
    }

    // ── Automated Discounts (local feed analysis) ─────────────────────────
    var adProblemCache = {};   // site+type → rows[]

    function loadAutodiscountStats() {
        fetch('/integr/merchant/api/get_autodiscount_stats')
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.ok) {
                    document.getElementById('adBody').innerHTML =
                        '<div class="mc-section-empty text-red">' + escHtml(d.error || 'Помилка') + '</div>';
                    return;
                }
                renderAutodiscount(d);
            })
            .catch(function(e) {
                document.getElementById('adBody').innerHTML =
                    '<div class="mc-section-empty text-red">' + escHtml(String(e)) + '</div>';
            });
    }

    function renderAutodiscount(d) {
        var badge = document.getElementById('adBadge');
        var body  = document.getElementById('adBody');

        // Загальна кількість без авто-прайсингу
        var totalNoCoverage = (d.off ? d.off.no_cost + d.off.cost_exceeds : 0)
                            + (d.mff ? d.mff.no_cost + d.mff.cost_exceeds : 0);

        if (totalNoCoverage > 0) {
            badge.style.display = '';
            badge.textContent   = totalNoCoverage + ' без покриття';
            badge.className     = 'badge badge-orange';
        } else {
            badge.style.display = '';
            badge.textContent   = '100%';
            badge.className     = 'badge badge-green';
        }

        function siteBlock(stats, siteCode, siteLabel, badgeClass) {
            if (!stats || stats.total === 0) {
                return '<div><div class="mc-ad-site-label"><span class="badge ' + badgeClass + '">' + siteLabel + '</span></div>'
                     + '<div style="font-size:12px; color:var(--text-muted)">Товарів у фіді не знайдено</div></div>';
            }
            var total    = stats.total;
            var withAuto = stats.with_auto;
            var costOnly = stats.with_cost_only;
            var noCost   = stats.no_cost;

            function bar(val, cssClass) {
                var pct = total > 0 ? Math.round(val / total * 100) : 0;
                return '<div class="mc-ad-bar-row">'
                     + '<div class="mc-ad-bar-track"><div class="mc-ad-bar-fill ' + cssClass + '" style="width:' + pct + '%"></div></div>'
                     + '<div class="mc-ad-bar-val">' + fmtNum(val) + '</div>'
                     + '<div class="mc-ad-bar-pct">' + pct + '%</div>'
                     + '</div>';
            }

            var html = '<div>'
                + '<div class="mc-ad-site-label"><span class="badge ' + badgeClass + '">' + siteLabel + '</span>'
                + '<span style="color:var(--text-muted); font-size:11px">' + fmtNum(total) + ' товарів у фіді</span></div>'
                + '<div class="mc-ad-bars">'
                + '<div class="mc-ad-bar-row"><div class="mc-ad-bar-label" style="color:#16a34a; font-weight:600">auto_pricing</div>' + bar(withAuto, 'green') + '</div>'
                + '<div class="mc-ad-bar-row"><div class="mc-ad-bar-label" style="color:#d97706; font-weight:600">тільки cost</div>' + bar(costOnly, 'orange') + '</div>'
                + '<div class="mc-ad-bar-row"><div class="mc-ad-bar-label" style="color:#dc2626; font-weight:600">немає cost</div>' + bar(noCost, 'red') + '</div>'
                + '</div>'
                + (noCost > 0 || costOnly > 0
                    ? '<button type="button" class="btn btn-ghost btn-xs mc-ad-load-more" data-site="' + siteCode + '" style="margin-top:8px">Переглянути проблемні товари ↓</button>'
                    : '<div style="font-size:12px; color:#16a34a; margin-top:8px">Всі товари мають auto_pricing_min_price ✓</div>')
                + '<div class="mc-ad-problems" id="adProblems_' + siteCode + '" style="display:none"></div>'
                + '</div>';
            return html;
        }

        body.innerHTML = '<div class="mc-section-body">'
            + '<div style="font-size:12px; color:var(--text-muted); margin-bottom:14px">'
            + 'Показує скільки товарів отримують поля <code>g:auto_pricing_min_price</code> і <code>g:cost_of_goods_sold</code> у фіді. '
            + 'Умова: <code>price_purchase > 0</code> і <code>max(price×0.60, cost×1.20) ≤ price×0.95</code>.'
            + '</div>'
            + '<div class="mc-ad-grid">'
            + siteBlock(d.off, 'off', 'off', 'badge-blue')
            + siteBlock(d.mff, 'mff', 'mff', 'badge-orange')
            + '</div>'
            + '</div>';

        // Клік "переглянути проблемні"
        body.querySelectorAll('.mc-ad-load-more').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var site = this.getAttribute('data-site');
                this.style.display = 'none';
                loadAdProblems(site, d[site]);
            });
        });
    }

    function loadAdProblems(site, stats) {
        var container = document.getElementById('adProblems_' + site);
        if (!container) return;
        container.style.display = '';

        // Вкладки
        var tabs = [
            { type: 'no_cost',      label: 'Немає собівартості', count: stats.no_cost },
            { type: 'cost_exceeds', label: 'Собівартість > ліміт', count: stats.cost_exceeds },
        ].filter(function(t) { return t.count > 0; });

        if (tabs.length === 0) { container.innerHTML = ''; return; }

        var activeType = tabs[0].type;
        var tabsHtml = '<div class="mc-ad-prob-tabs">';
        tabs.forEach(function(t) {
            tabsHtml += '<button type="button" class="mc-ad-prob-tab' + (t.type === activeType ? ' active' : '')
                + '" data-type="' + t.type + '">' + escHtml(t.label) + ' (' + fmtNum(t.count) + ')</button>';
        });
        tabsHtml += '</div>';

        var panesHtml = '';
        tabs.forEach(function(t) {
            panesHtml += '<div class="mc-ad-prob-pane' + (t.type === activeType ? ' active' : '') + '" id="adPane_' + site + '_' + t.type + '">'
                + '<div class="mc-section-body"><div class="mc-skeleton" style="width:70%; height:10px"></div></div>'
                + '</div>';
        });

        container.innerHTML = tabsHtml + panesHtml;

        // Перемикання вкладок
        container.querySelectorAll('.mc-ad-prob-tab').forEach(function(tab) {
            tab.addEventListener('click', function() {
                container.querySelectorAll('.mc-ad-prob-tab').forEach(function(t) { t.classList.remove('active'); });
                container.querySelectorAll('.mc-ad-prob-pane').forEach(function(p) { p.classList.remove('active'); });
                this.classList.add('active');
                var type = this.getAttribute('data-type');
                var pane = document.getElementById('adPane_' + site + '_' + type);
                if (pane) {
                    pane.classList.add('active');
                    if (!pane.dataset.loaded) { fetchAdProblems(site, type, pane); }
                }
            });
        });

        // Завантажити першу вкладку
        var firstPane = document.getElementById('adPane_' + site + '_' + activeType);
        if (firstPane) { fetchAdProblems(site, activeType, firstPane); }
    }

    function fetchAdProblems(site, type, pane) {
        pane.dataset.loaded = '1';
        fetch('/integr/merchant/api/get_autodiscount_stats?site=' + site + '&type=' + type + '&limit=50')
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.ok || !d.rows || !d.rows.length) {
                    pane.innerHTML = '<div class="mc-section-empty">Товарів не знайдено</div>';
                    return;
                }
                var html = '<div style="padding:0 18px 12px"><table class="mc-ad-prob-table">'
                    + '<thead><tr>'
                    + '<th style="width:50px">ID</th>'
                    + '<th style="width:90px">Артикул</th>'
                    + '<th>Назва</th>'
                    + '<th style="width:80px; text-align:right">Ціна</th>'
                    + '<th style="width:80px; text-align:right">Собів.</th>'
                    + '<th style="width:80px; text-align:right">Мін_ціна</th>'
                    + '<th>Причина</th>'
                    + '</tr></thead><tbody>';

                d.rows.forEach(function(row) {
                    var priceCell    = row.price_sale   ? number2(row.price_sale)   : '—';
                    var costCell     = row.price_cost   ? number2(row.price_cost)   : '<span class="num-red">—</span>';
                    var minPriceCell = row.min_price    ? number2(row.min_price)    : '—';
                    var nameLink     = row.site_pid
                        ? '<a href="https://officetorg.com.ua/index.php?route=product/product&product_id=' + row.site_pid + '" target="_blank" style="color:var(--blue); text-decoration:none">' + escHtml(row.name || '—') + '</a>'
                        : escHtml(row.name || '—');

                    html += '<tr>'
                        + '<td><a href="/catalog?search=' + row.product_id + '" target="_blank" style="color:var(--blue)">' + row.product_id + '</a></td>'
                        + '<td style="font-size:11px; color:var(--text-muted)">' + escHtml(row.article || '—') + '</td>'
                        + '<td class="td-name"><span title="' + escHtml(row.name || '') + '">' + nameLink + '</span></td>'
                        + '<td style="text-align:right">' + priceCell + '</td>'
                        + '<td style="text-align:right">' + costCell + '</td>'
                        + '<td style="text-align:right; color:#d97706">' + minPriceCell + '</td>'
                        + '<td class="td-reason">' + escHtml(row.reason) + '</td>'
                        + '</tr>';
                });

                html += '</tbody></table>';
                if (d.count >= 50) {
                    html += '<div style="font-size:11px; color:var(--text-muted); padding-top:6px">Показано перші 50. Повний список — через фільтр у Каталозі.</div>';
                }
                html += '</div>';
                pane.innerHTML = html;
            })
            .catch(function(e) {
                pane.innerHTML = '<div class="mc-section-empty text-red">' + escHtml(String(e)) + '</div>';
            });
    }

    function number2(n) {
        return Number(n).toLocaleString('uk-UA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
        adProblemCache = {};
        loadStatus();
        loadGadStatus();
        loadAutodiscountStats();
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

    // ── Refresh кнопка секції Automated Discounts ─────────────────────────
    document.getElementById('adRefreshBtn').addEventListener('click', function() {
        var btn = this;
        btn.classList.add('loading');
        adProblemCache = {};
        _gadSections   = {};
        // Скидаємо скелетони
        document.getElementById('adPaneGoogle').innerHTML =
            '<div class="mc-section-body"><div class="mc-skel-row"><div class="mc-skeleton" style="width:40%"></div><div class="mc-skeleton" style="width:40%"></div></div></div>';
        document.getElementById('adBody').innerHTML =
            '<div class="mc-section-body"><div class="mc-skel-row"><div class="mc-skeleton" style="width:40%"></div></div></div>';
        loadGadStatus();
        loadAutodiscountStats();
        setTimeout(function() { btn.classList.remove('loading'); }, 1500);
    });

    // ── Init ──────────────────────────────────────────────────────────────
    // Локальний аналіз і GAD — запускаємо одразу (приховані вкладки)
    loadAutodiscountStats();
    loadGadStatus();
    // Performance завантажується при першому відкритті вкладки
    loadStatus();
}());
</script>
