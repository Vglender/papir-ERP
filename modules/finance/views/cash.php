<?php require_once __DIR__ . '/../../shared/layout.php'; ?>
<style>
/* ── Layout ─────────────────────────────────────────────────────────── */
.fin-outer  { max-width:1700px; margin:0 auto; padding:20px 16px 40px; }
.fin-layout { display:grid; grid-template-columns:1fr 390px; gap:16px; align-items:start; }
@media(max-width:1100px){ .fin-layout{ grid-template-columns:1fr; } .fin-panel-col{ display:none!important; } }

/* ── Toolbar ─────────────────────────────────────────────────────────── */
.fin-toolbar { display:flex; align-items:center; gap:8px; margin-bottom:10px; }
.fin-toolbar h1 { margin:0; font-size:18px; font-weight:700; flex-shrink:0; }
.fin-search-wrap { flex:1; min-width:160px; }
.fin-toolbar .btn        { height:34px; padding:0 12px; flex-shrink:0; }
.fin-toolbar .chip-input { min-height:34px; max-height:34px; overflow:hidden; }

/* Split bulk btn */
.fin-split-btn { display:inline-flex; align-items:stretch; border:1px solid var(--border-input); border-radius:var(--radius-sm); overflow:visible; position:relative; flex-shrink:0; }
.fin-split-count { display:flex; align-items:center; padding:0 10px; font-size:13px; font-weight:600; color:var(--text-muted); border-right:1px solid var(--border-input); min-width:30px; justify-content:center; height:34px; background:var(--bg-card); }
.fin-split-count.active { color:var(--blue); background:var(--blue-bg); }
.fin-split-trigger { display:flex; align-items:center; gap:5px; padding:0 10px; height:34px; font-size:13px; background:var(--bg-card); border:none; cursor:pointer; white-space:nowrap; }
.fin-split-trigger:hover { background:var(--bg-hover); }
.fin-split-dd { display:none; position:absolute; top:calc(100% + 4px); right:0; min-width:180px; background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius); box-shadow:0 8px 24px rgba(0,0,0,.12); z-index:100; padding:4px 0; }
.fin-split-dd.open { display:block; }
.fin-dd-item { display:flex; align-items:center; gap:8px; padding:7px 14px; font-size:13px; cursor:pointer; background:none; border:none; width:100%; text-align:left; }
.fin-dd-item:hover { background:var(--bg-hover); }
.fin-clear-sel { display:flex; align-items:center; justify-content:center; width:28px; height:34px; background:none; border:none; cursor:pointer; color:var(--text-muted); border-radius:var(--radius-sm); flex-shrink:0; }
.fin-clear-sel:hover { background:var(--bg-hover); color:var(--text); }

/* ── Filter bar ──────────────────────────────────────────────────────── */
.fin-quick-dates { display:flex; align-items:center; gap:3px; }
.fin-qd-btn { padding:3px 8px; font-size:12px; border:1px solid var(--border-input); border-radius:10px; background:var(--bg-card); cursor:pointer; color:var(--text-muted); white-space:nowrap; }
.fin-qd-btn:hover  { border-color:var(--blue-light); color:var(--blue); }
.fin-qd-btn.active { background:var(--blue-bg); border-color:var(--blue); color:var(--blue); font-weight:600; }
.fin-date-input { height:26px; font-size:12px; padding:0 6px; border:1px solid var(--border-input); border-radius:var(--radius-sm); font-family:var(--font); }

/* ── Summary strip ──────────────────────────────────────────────────── */
.fin-summary { display:flex; gap:0; margin-bottom:10px; background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
.fin-sum-item { display:flex; flex-direction:column; gap:2px; padding:10px 18px; flex:1; border-right:1px solid var(--border); }
.fin-sum-item:last-child { border-right:none; }
.fin-sum-label { font-size:10px; color:var(--text-muted); text-transform:uppercase; letter-spacing:.4px; font-weight:600; }
.fin-sum-val { font-size:16px; font-weight:700; font-variant-numeric:tabular-nums; }
.fin-sum-val.green { color:var(--green); }
.fin-sum-val.red   { color:var(--red); }
.fin-sum-val.blue  { color:var(--blue); }
.fin-sum-count { display:flex; flex-direction:column; justify-content:center; align-items:flex-end; padding:10px 16px; flex-shrink:0; font-size:12px; color:var(--text-muted); }

/* ── Table ───────────────────────────────────────────────────────────── */
.fin-table-wrap { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
.fin-table-wrap .crm-table td { vertical-align:middle; padding:7px 10px; }
.crm-table th.th-cb, .crm-table td.td-cb { width:36px; padding:0 0 0 12px; }
.crm-table td.td-cb input { cursor:pointer; }
.crm-table td.td-act { width:36px; padding:0 4px; text-align:right; }
.crm-table th.th-act { width:36px; }
.fin-row { cursor:pointer; transition:background .1s; }
.fin-row:hover { background:var(--bg-hover); }
.fin-row.selected { background:var(--blue-bg); }
.fin-row.moving { background:#f4f4f5; }
.fin-row.moving:hover { background:#eaeaeb; }
.fin-row.moving td { color:#9ca3af; }
.fin-row.moving .fin-sum-cell { color:#9ca3af !important; font-weight:500; }
.fin-row.moving.selected { background:#ddeaf7; }
.fin-moment-date { font-size:12px; font-weight:600; white-space:nowrap; }
.fin-moment-time { font-size:11px; color:var(--text-muted); }
.fin-doc { font-size:12px; font-family:monospace; color:var(--text-muted); white-space:nowrap; max-width:120px; overflow:hidden; text-overflow:ellipsis; display:block; }
.fin-badge-in  { display:inline-flex; align-items:center; padding:2px 6px; border-radius:8px; font-size:10px; font-weight:700; background:#dcfce7; color:#166534; white-space:nowrap; }
.fin-badge-out { display:inline-flex; align-items:center; padding:2px 6px; border-radius:8px; font-size:10px; font-weight:700; background:#fee2e2; color:#991b1b; white-space:nowrap; }
.fin-badge-mov   { display:inline-flex; align-items:center; padding:2px 6px; border-radius:8px; font-size:10px; font-weight:700; background:#e5e7eb; color:#6b7280; white-space:nowrap; }
.fin-badge-draft { display:inline-flex; align-items:center; padding:2px 6px; border-radius:8px; font-size:10px; font-weight:600; border:1px dashed #9ca3af; color:#6b7280; white-space:nowrap; margin-left:4px; }
.fin-cp a { font-weight:600; font-size:13px; color:var(--text); text-decoration:none; }
.fin-cp a:hover { color:var(--blue); }
.fin-cp-none { color:var(--text-muted); font-size:12px; }
.fin-desc-cell { font-size:12px; color:var(--text-muted); max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.fin-sum-cell { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; font-weight:700; font-size:13px; }
.fin-sum-cell.in  { color:var(--green); }
.fin-sum-cell.out { color:var(--red); }

/* ── Row action button ───────────────────────────────────────────────── */
.fin-act-btn { display:flex; align-items:center; justify-content:center; width:26px; height:26px; border:none; background:none; cursor:pointer; border-radius:var(--radius-sm); color:var(--text-muted); font-size:16px; line-height:1; flex-shrink:0; }
.fin-act-btn:hover { background:var(--bg-hover); color:var(--text); }

/* ── Sidebar ─────────────────────────────────────────────────────────── */
.fin-panel-col { position:sticky; top:16px; }
.fin-panel { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); overflow:hidden; }
.fin-panel-head { display:flex; align-items:center; gap:8px; padding:12px 16px; border-bottom:1px solid var(--border); background:var(--bg-header); }
.fin-panel-title { flex:1; min-width:0; font-weight:700; font-size:14px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.fin-panel-head-btns { display:flex; align-items:center; gap:4px; flex-shrink:0; }
.fin-cp-head-link { font-size:12px; color:var(--blue); text-decoration:none; padding:3px 7px; border-radius:var(--radius-sm); display:none; white-space:nowrap; }
.fin-cp-head-link:hover { background:var(--blue-bg); }
.fin-panel-close { width:26px; height:26px; display:none; align-items:center; justify-content:center; border:none; background:none; cursor:pointer; border-radius:var(--radius-sm); color:var(--text-muted); }
.fin-panel-close:hover { background:var(--bg-hover); }
.fin-panel-empty { padding:48px 20px; text-align:center; color:var(--text-muted); }
.fin-panel-empty svg { opacity:.25; display:block; margin:0 auto 14px; }
.fin-panel-empty .emp-title { font-weight:600; color:var(--text); font-size:14px; margin:0 0 4px; }
.fin-panel-empty p { margin:4px 0; font-size:13px; }
.fin-panel-form { display:none; }
.fin-panel-form.visible { display:block; }
/* Direction toggle */
.fin-dir-wrap { display:flex; gap:6px; padding:12px 16px; border-bottom:1px solid var(--border); }
.fin-dir-btn { flex:1; padding:7px 0; border:1px solid var(--border-input); border-radius:20px; background:var(--bg-card); cursor:pointer; font-size:13px; font-weight:600; text-align:center; transition:all .15s; color:var(--text-muted); }
.fin-dir-btn.active-in  { border-color:#16a34a; background:#f0fdf4; color:#16a34a; }
.fin-dir-btn.active-out { border-color:#dc2626; background:#fef2f2; color:#dc2626; }
/* Fields */
.fin-panel-fields { padding:12px 16px; display:flex; flex-direction:column; gap:10px; }
.fin-pf label { display:block; font-size:10px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.4px; margin-bottom:3px; }
.fin-pf input[type=text],
.fin-pf input[type=number],
.fin-pf input[type=date],
.fin-pf textarea { width:100%; box-sizing:border-box; border:1px solid var(--border-input); border-radius:var(--radius-sm); padding:6px 8px; font-size:13px; font-family:var(--font); color:var(--text); background:var(--bg-card); }
.fin-pf input:focus, .fin-pf textarea:focus { outline:none; border-color:var(--blue); }
.fin-pf textarea { resize:vertical; min-height:58px; }
.fin-row2 { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
/* CP autocomplete */
.fin-cp-wrap { position:relative; }
.fin-cp-dd { display:none; position:absolute; top:calc(100% + 2px); left:0; right:0; background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius); box-shadow:0 4px 16px rgba(0,0,0,.12); z-index:300; max-height:220px; overflow-y:auto; }
.fin-cp-dd.open { display:block; }
.fin-cp-item { padding:7px 12px; font-size:13px; cursor:pointer; display:flex; align-items:center; gap:8px; }
.fin-cp-item:hover { background:var(--bg-hover); }
.fin-cp-item small { color:var(--text-muted); font-size:11px; margin-left:auto; }
/* Moving checkbox */
.fin-moving-label { display:flex; align-items:center; gap:7px; cursor:pointer; font-size:12px; color:var(--text-muted); }
.fin-moving-label input { width:14px; height:14px; cursor:pointer; flex-shrink:0; }
/* Source badge */
.fin-source-badge { font-size:10px; color:var(--text-muted); padding:2px 7px; border:1px solid var(--border); border-radius:8px; white-space:nowrap; }
/* Panel footer */
.fin-panel-footer { padding:12px 16px; border-top:1px solid var(--border); display:flex; align-items:center; gap:10px; }
.fin-panel-err { font-size:12px; color:var(--red); flex:1; min-width:0; }

/* ── Linked Orders ──────────────────────────────────────────────────────── */
.fin-linked-orders { padding:16px 16px 12px; border-top:1px solid var(--border); }
.fin-lo-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
.fin-lo-header h4 { margin:0; font-size:13px; font-weight:600; }
.fin-lo-list { display:flex; flex-direction:column; gap:4px; }
.lo-loading, .lo-empty { font-size:12px; color:var(--text-muted); padding:6px 0; }
.lo-item, .lo-result-item { display:flex; align-items:center; gap:6px; padding:5px 8px; border-radius:var(--radius-sm); background:var(--bg-hover); font-size:12px; }
.lo-item:hover { background:var(--border); }
.lo-num { font-weight:600; color:var(--blue); text-decoration:none; white-space:nowrap; }
.lo-num:hover { text-decoration:underline; }
.lo-date { color:var(--text-muted); white-space:nowrap; }
.lo-sum { font-variant-numeric:tabular-nums; white-space:nowrap; margin-left:auto; }
.lo-status { font-size:11px; padding:1px 6px; border-radius:8px; white-space:nowrap; }
.lo-st-paid { background:#dcfce7; color:#166534; }
.lo-st-partial { background:#fef9c3; color:#854d0e; }
.lo-st-none { background:#f3f4f6; color:#6b7280; }
.lo-unlink { background:none; border:none; cursor:pointer; color:var(--text-muted); font-size:16px; line-height:1; padding:0 2px; flex-shrink:0; }
.lo-unlink:hover { color:var(--red); }
.fin-lo-search { margin-top:8px; }
.fin-lo-search-row { display:flex; gap:4px; margin-bottom:6px; }
.fin-lo-search-row input { flex:1; height:28px; font-size:12px; padding:0 8px; border:1px solid var(--border-input); border-radius:var(--radius-sm); font-family:var(--font); }
.fin-lo-results { display:flex; flex-direction:column; gap:3px; max-height:200px; overflow-y:auto; }
.lo-result-item { background:var(--bg-card); border:1px solid var(--border); }
.lo-result-item:hover { border-color:var(--blue-light); }
.lo-cp-hint { font-size:11px; color:var(--text-muted); max-width:100px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.lo-link-btn { flex-shrink:0; }

/* ── Unmatched counterparty ─────────────────────────────────────────────── */
tr.fin-row.unmatched { background:rgba(250,204,21,.08); }
tr.fin-row.unmatched:hover { background:rgba(250,204,21,.15); }
tr.fin-row.unmatched.selected { background:rgba(250,204,21,.15); }
.fin-cp-unmatched { display:inline-block; font-size:11px; padding:1px 8px; border-radius:8px; background:#fef3c7; color:#92400e; font-weight:500; }
.filter-pill-warn.active { background:#fef3c7; border-color:#f59e0b; color:#92400e; }
</style>

<div class="fin-outer">

    <form method="get" action="/finance/cash" id="finForm">
        <input type="hidden" name="page"   id="finPage"         value="<?php echo (int)$page; ?>">
        <input type="hidden" name="search" id="finSearchHidden" value="<?php echo ViewHelper::h($search); ?>">

        <!-- Toolbar -->
        <div class="fin-toolbar">
            <h1>Каса</h1>
            <button type="button" class="btn btn-primary" id="btnAddDoc">+ Додати</button>
            <button type="button" class="btn" onclick="location.reload()" title="Оновити"><svg width="14" height="14" viewBox="0 0 16 16" fill="none" style="vertical-align:-2px"><path d="M2 8a6 6 0 0110.5-4M14 2v4h-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 8a6 6 0 01-10.5 4M2 14v-4h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>

            <div class="fin-search-wrap">
                <div class="chip-input" id="finChipBox">
                    <input type="text" class="chip-typer" id="finChipTyper"
                           placeholder="Документ, опис, контрагент…" autocomplete="off">
                    <div class="chip-actions">
                        <button type="button" class="chip-act-btn chip-act-clear hidden" id="finChipClear" title="Очистити">&#x2715;</button>
                        <button type="submit" class="chip-act-btn chip-act-submit" title="Пошук">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="6.5" cy="6.5" r="4.5" stroke="currentColor" stroke-width="1.6"/><path d="M10 10l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <div class="fin-split-btn" id="finSplitBtn">
                <span class="fin-split-count" id="finSelCount">0</span>
                <button type="button" class="fin-split-trigger" id="finSplitTrigger">
                    Дія
                    <svg viewBox="0 0 12 12" fill="none" width="10" height="10"><path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                </button>
                <div class="fin-split-dd" id="finSplitDd">
                    <button type="button" class="fin-dd-item" id="bulkCopyDocs">
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><rect x="5" y="5" width="9" height="9" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M11 5V3a2 2 0 0 0-2-2H3a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2" stroke="currentColor" stroke-width="1.4"/></svg>
                        Копіювати № документів
                    </button>
                    <button type="button" class="fin-dd-item" id="bulkCopySums">
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M8 2v12M5 5h4.5a2.5 2.5 0 0 1 0 5H5M5 10h6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                        Копіювати суми
                    </button>
                    <button type="button" class="fin-dd-item" id="bulkDelete" style="color:var(--red)">
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M3 4h10M6 4V3h4v1M5 4l.5 9h5L11 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Видалити вибрані
                    </button>
                </div>
            </div>
            <button type="button" class="fin-clear-sel hidden" id="finClearSel" title="Скинути вибір">✕</button>
        </div>

        <!-- Filter bar -->
        <div class="filter-bar">
            <div class="filter-bar-group">
                <span class="filter-bar-label">Напрям</span>
                <?php foreach (array('' => 'Всі', 'in' => '↓ Прихід', 'out' => '↑ Витрати') as $val => $label): ?>
                    <label class="filter-pill<?php echo ($direction === $val) ? ' active' : ''; ?>">
                        <input type="radio" name="direction" value="<?php echo $val; ?>"
                               class="js-filter-instant" <?php echo ($direction === $val) ? 'checked' : ''; ?>>
                        <?php echo $label; ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="filter-bar-sep"></div>
            <div class="filter-bar-group">
                <span class="filter-bar-label">Період</span>
                <div class="fin-quick-dates" id="finQuickDates">
                    <?php
                    $quickPeriods = array('вч' => 'yesterday', 'сег' => 'today', 'нед' => 'week', 'мес' => 'month');
                    foreach ($quickPeriods as $label => $key):
                        $isActive = isset($_GET['_period']) && $_GET['_period'] === $key;
                    ?>
                    <button type="button" class="fin-qd-btn<?php echo $isActive ? ' active' : ''; ?>"
                            data-period="<?php echo $key; ?>"><?php echo $label; ?></button>
                    <?php endforeach; ?>
                </div>
                <input type="date" name="date_from" id="finDateFrom"
                       value="<?php echo ViewHelper::h($dateFrom); ?>" class="fin-date-input">
                <span style="color:var(--text-muted);font-size:12px;">—</span>
                <input type="date" name="date_to" id="finDateTo"
                       value="<?php echo ViewHelper::h($dateTo); ?>" class="fin-date-input">
                <input type="hidden" name="_period" id="finPeriodHidden"
                       value="<?php echo ViewHelper::h(isset($_GET['_period']) ? $_GET['_period'] : ''); ?>">
            </div>
            <div class="filter-bar-sep"></div>
            <div class="filter-bar-group">
                <label class="filter-pill<?php echo $showDrafts ? ' active' : ''; ?>">
                    <input type="checkbox" name="show_drafts" value="1" class="js-filter-instant"
                           <?php echo $showDrafts ? 'checked' : ''; ?>>
                    Чернетки
                </label>
                <label class="filter-pill filter-pill-warn<?php echo $unmatched ? ' active' : ''; ?>">
                    <input type="checkbox" name="unmatched" value="1"
                           class="js-filter-instant" <?php echo $unmatched ? 'checked' : ''; ?>>
                    Нерозпізнані
                </label>
            </div>
                        <button type="button" class="filter-bar-gear" title="Налаштувати фільтри">
                <svg viewBox="0 0 16 16" fill="none"><path d="M6.5 2h3M8 2v1.5M13 6.5v3M13 8h-1.5M9.5 14h-3M8 14v-1.5M3 9.5v-3M3 8h1.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="8" cy="8" r="2.5" stroke="currentColor" stroke-width="1.4"/></svg>
            </button>
        </div>
    </form>

    <!-- Summary -->
    <div class="fin-summary">
        <div class="fin-sum-item">
            <span class="fin-sum-label">Прихід</span>
            <span class="fin-sum-val green">+ <?php echo number_format($summary['in'], 2, '.', ' '); ?></span>
        </div>
        <div class="fin-sum-item">
            <span class="fin-sum-label">Витрати</span>
            <span class="fin-sum-val red">− <?php echo number_format($summary['out'], 2, '.', ' '); ?></span>
        </div>
        <div class="fin-sum-item">
            <span class="fin-sum-label">Баланс</span>
            <?php $balance = $summary['in'] - $summary['out']; ?>
            <span class="fin-sum-val <?php echo $balance >= 0 ? 'blue' : 'red'; ?>">
                <?php echo ($balance >= 0 ? '+' : '−') . ' ' . number_format(abs($balance), 2, '.', ' '); ?>
            </span>
        </div>
        <div class="fin-sum-count">
            <span style="font-size:14px;font-weight:700;"><?php echo number_format($total, 0, '.', ' '); ?></span>
            <span>записів</span>
        </div>
    </div>

    <!-- Layout: table + sidebar -->
    <div class="fin-layout">

        <!-- Table -->
        <div>
            <div class="fin-table-wrap">
                <table class="crm-table">
                    <thead>
                        <tr>
                            <th class="th-cb"><input type="checkbox" id="finCheckAll" title="Вибрати всі"></th>
                            <th style="width:85px">Дата</th>
                            <th style="width:110px">Документ</th>
                            <th style="width:65px"></th>
                            <th>Контрагент</th>
                            <th>Опис</th>
                            <th style="width:110px;text-align:right">Сума</th>
                            <th class="th-act"></th>
                        </tr>
                    </thead>
                    <tbody id="finTableBody">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted);">Записів не знайдено</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                        <?php
                            $momentDate = $row['moment'] ? substr($row['moment'], 0, 10) : '—';
                            $momentTime = $row['moment'] ? substr($row['moment'], 11, 5) : '';
                            $cpName     = trim((string)$row['cp_name']);
                            $isUnmatched = ($cpName === '' || $cpName === 'НЕРАЗОБРАННОЕ');
                            $descText   = trim((string)$row['description']);
                            $isMoving   = !empty($row['is_moving']);

                            $panelData = json_encode(array(
                                'id'         => (int)$row['id'],
                                'dir'        => $row['direction'],
                                'moment'     => $row['moment'],
                                'doc'        => $row['doc_number'],
                                'sum'        => $row['sum'],
                                'cp_id'      => $row['cp_id'],
                                'cp_name'    => $cpName,
                                'desc'       => $descText,
                                'purpose'    => $row['payment_purpose'],
                                'moving'     => $isMoving,
                                'source'     => $row['source'],
                                'exp_cat_id' => $row['expense_category_id'] ? (int)$row['expense_category_id'] : null,
                                'is_posted'  => (int)$row['is_posted'],
                            ));
                        ?>
                        <tr class="fin-row<?php echo $isMoving ? ' moving' : ''; ?><?php echo $isUnmatched ? ' unmatched' : ''; ?>"
                            data-id="<?php echo (int)$row['id']; ?>"
                            data-panel='<?php echo htmlspecialchars($panelData, ENT_QUOTES); ?>'>
                            <td class="td-cb" onclick="event.stopPropagation()">
                                <input type="checkbox" class="fin-row-check" value="<?php echo (int)$row['id']; ?>"
                                       data-doc="<?php echo ViewHelper::h($row['doc_number']); ?>"
                                       data-sum="<?php echo (float)$row['sum']; ?>">
                            </td>
                            <td>
                                <div class="fin-moment-date"><?php echo ViewHelper::h($momentDate); ?></div>
                                <div class="fin-moment-time"><?php echo ViewHelper::h($momentTime); ?></div>
                            </td>
                            <td>
                                <span class="fin-doc" title="<?php echo ViewHelper::h($row['doc_number']); ?>">
                                    <?php echo ViewHelper::h($row['doc_number'] ?: '—'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($isMoving): ?>
                                    <span class="fin-badge-mov" title="Внутрішній переказ">⇄</span>
                                <?php elseif ($row['direction'] === 'in'): ?>
                                    <span class="fin-badge-in">↓</span>
                                <?php else: ?>
                                    <span class="fin-badge-out">↑</span>
                                <?php endif; ?>
                                <?php if (empty($row['is_posted'])): ?>
                                    <span class="fin-badge-draft">чернетка</span>
                                <?php endif; ?>
                            </td>
                            <td class="fin-cp">
                                <?php if ($isUnmatched): ?>
                                    <span class="fin-cp-unmatched">Нерозпізнано</span>
                                <?php elseif ($cpName !== ''): ?>
                                    <?php if ($row['cp_id']): ?>
                                        <a href="/counterparties/view?id=<?php echo (int)$row['cp_id']; ?>"
                                           target="_blank" onclick="event.stopPropagation()"><?php echo ViewHelper::h($cpName); ?></a>
                                    <?php else: ?>
                                        <?php echo ViewHelper::h($cpName); ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="fin-cp-none">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="fin-desc-cell" title="<?php echo ViewHelper::h($descText); ?>">
                                    <?php echo ViewHelper::h($descText ?: '—'); ?>
                                </span>
                            </td>
                            <td class="fin-sum-cell <?php echo $row['direction']; ?>">
                                <?php echo $row['direction'] === 'in' ? '+' : '−'; ?>
                                <?php echo number_format((float)$row['sum'], 2, '.', ' '); ?>
                            </td>
                            <td class="td-act" onclick="event.stopPropagation()">
                                <button type="button" class="fin-act-btn"
                                        data-id="<?php echo (int)$row['id']; ?>"
                                        title="Дії">⋮</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($pages > 1): ?>
            <div class="pagination" style="margin-top:14px;">
                <?php
                $qp = array(
                    'search'    => $search,
                    'direction' => $direction,
                    'date_from' => $dateFrom,
                    'date_to'   => $dateTo,
                    'unmatched' => $unmatched ? '1' : '',
                );
                $base = '/finance/cash?' . http_build_query(array_filter($qp));
                $from = max(1, $page - 3);
                $to   = min($pages, $page + 3);
                ?>
                <a href="<?php echo $base; ?>&page=<?php echo max(1,$page-1); ?>"
                   class="pag-nav<?php echo $page<=1?' pag-nav-dis':''; ?>">&#8592;</a>
                <?php if ($from>1): ?><a href="<?php echo $base; ?>&page=1">1</a><span>…</span><?php endif; ?>
                <?php for($p=$from;$p<=$to;$p++): ?>
                    <a href="<?php echo $base; ?>&page=<?php echo $p; ?>"
                       <?php echo $p===$page?'class="active"':''; ?>><?php echo $p; ?></a>
                <?php endfor; ?>
                <?php if ($to<$pages): ?><span>…</span><a href="<?php echo $base; ?>&page=<?php echo $pages; ?>"><?php echo $pages; ?></a><?php endif; ?>
                <a href="<?php echo $base; ?>&page=<?php echo min($pages,$page+1); ?>"
                   class="pag-nav<?php echo $page>=$pages?' pag-nav-dis':''; ?>">&#8594;</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar panel -->
        <div class="fin-panel-col">
            <div class="fin-panel" id="finPanel">
                <div class="fin-panel-head">
                    <span class="fin-panel-title" id="finPanelTitle">Документ</span>
                    <div class="fin-panel-head-btns">
                        <a href="#" class="fin-cp-head-link" id="finCpLink" target="_blank">Контрагент →</a>
                        <button type="button" class="fin-panel-close" id="finPanelClose">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M3 3l10 10M13 3L3 13" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Empty state -->
                <div class="fin-panel-empty" id="finPanelEmpty">
                    <svg width="44" height="44" viewBox="0 0 44 44" fill="none"><rect x="8" y="6" width="28" height="32" rx="3" stroke="currentColor" stroke-width="1.5"/><path d="M15 15h14M15 21h14M15 27h9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                    <p class="emp-title">Оберіть документ</p>
                    <p>або натисніть «+ Додати»</p>
                </div>

                <!-- Editable form -->
                <form class="fin-panel-form" id="finPanelForm">
                    <input type="hidden" id="panelId" name="id" value="">

                    <div class="fin-dir-wrap">
                        <button type="button" class="fin-dir-btn" id="dirBtnIn"  data-dir="in">↓ Прихід</button>
                        <button type="button" class="fin-dir-btn" id="dirBtnOut" data-dir="out">↑ Витрати</button>
                        <input type="hidden" id="panelDir" name="direction" value="in">
                    </div>

                    <div class="fin-panel-fields">
                        <div class="fin-row2">
                            <div class="fin-pf">
                                <label>Сума</label>
                                <input type="number" id="panelSum" name="sum" step="0.01" min="0.01" placeholder="0.00">
                            </div>
                            <div class="fin-pf">
                                <label>Дата</label>
                                <input type="date" id="panelDate" name="moment">
                            </div>
                        </div>

                        <div class="fin-pf">
                            <label>Номер документа</label>
                            <input type="text" id="panelDoc" name="doc_number" placeholder="—">
                        </div>

                        <div class="fin-pf">
                            <label>Контрагент</label>
                            <div class="fin-cp-wrap">
                                <input type="text" id="panelCpName" name="cp_name"
                                       placeholder="Пошук контрагента…" autocomplete="off">
                                <input type="hidden" id="panelCpId" name="cp_id">
                                <div class="fin-cp-dd" id="panelCpDd"></div>
                            </div>
                        </div>

                        <div class="fin-pf">
                            <label>Призначення платежу</label>
                            <textarea id="panelPurpose" name="payment_purpose" placeholder="—"></textarea>
                        </div>

                        <div class="fin-pf">
                            <label>Опис</label>
                            <input type="text" id="panelDesc" name="description" placeholder="—">
                        </div>

                        <div class="fin-pf" id="panelExpCatWrap" style="display:none">
                            <label>Стаття витрат</label>
                            <select id="panelExpCat" name="expense_category_id">
                                <option value="">— не вказано —</option>
                                <?php foreach ($expenseCategories as $cat): ?>
                                <option value="<?php echo (int)$cat['id']; ?>"><?php echo ViewHelper::h($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <label class="fin-moving-label">
                            <input type="checkbox" id="panelIsMoving" name="is_moving" value="1">
                            <span>Внутрішній переказ між рахунками</span>
                        </label>
                        <label class="fin-moving-label">
                            <input type="checkbox" id="panelIsPosted" name="is_posted" value="1">
                            <span>Активний (проведений)</span>
                        </label>
                    </div>

                    <div class="fin-panel-footer">
                        <button type="submit" class="btn btn-primary btn-sm" id="panelSaveBtn">Зберегти</button>
                        <span class="fin-panel-err" id="panelErr"></span>
                        <span class="fin-source-badge" id="panelSrcBadge" style="display:none"></span>
                    </div>
                </form>

                <!-- Linked Orders section (visible for saved direction=in payments) -->
                <div class="fin-linked-orders" id="linkedOrdersSection" style="display:none">
                    <div class="fin-lo-header">
                        <h4>Замовлення</h4>
                        <button type="button" class="btn btn-outline btn-xs" id="linkOrderBtn">+ Прив'язати</button>
                    </div>
                    <div class="fin-lo-list" id="linkedOrdersList"></div>

                    <div class="fin-lo-search" id="linkOrderSearch" style="display:none">
                        <div class="fin-lo-search-row">
                            <input type="text" id="loSearchQ" placeholder="Номер замовлення…" autocomplete="off">
                            <button type="button" class="btn btn-primary btn-xs" id="loSearchBtn">Знайти</button>
                        </div>
                        <div class="fin-lo-results" id="loSearchResults"></div>
                    </div>
                </div>

            </div>
        </div>

    </div><!-- /.fin-layout -->
</div>

<div class="toast" id="toast"></div>

<!-- Context menu -->
<div class="fin-ctx-menu" id="finCtxMenu" style="display:none;position:fixed;z-index:500;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:0 4px 16px rgba(0,0,0,.12);padding:4px 0;min-width:140px;">
    <button type="button" class="fin-dd-item" id="ctxDelete" style="color:var(--red)">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M3 4h10M6 4V3h4v1M5 4l.5 9h5L11 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Видалити
    </button>
</div>

<script src="/modules/shared/chip-search.js?v=<?php echo filemtime(__DIR__ . '/../../shared/chip-search.js'); ?>"></script>
<script>
(function () {

// ── Chip Search ───────────────────────────────────────────────────────────
ChipSearch.init('finChipBox', 'finChipTyper', 'finSearchHidden', null, {noComma: true});

var clearBtn = document.getElementById('finChipClear');
var chipBox  = document.getElementById('finChipBox');
var typer    = document.getElementById('finChipTyper');
var hidden   = document.getElementById('finSearchHidden');
var form     = document.getElementById('finForm');

function updateClearBtn() {
    var has = chipBox.querySelectorAll('.chip').length > 0 || typer.value.trim() !== '';
    clearBtn.classList.toggle('hidden', !has);
}
new MutationObserver(updateClearBtn).observe(chipBox, {childList: true});
typer.addEventListener('input', updateClearBtn);
clearBtn.addEventListener('click', function () {
    chipBox.querySelectorAll('.chip').forEach(function(c){ c.remove(); });
    typer.value = ''; hidden.value = '';
    clearBtn.classList.add('hidden');
    submitFilter();
});
updateClearBtn();

function submitFilter() { document.getElementById('finPage').value = 1; form.submit(); }

document.querySelectorAll('.js-filter-instant').forEach(function(el) {
    el.addEventListener('change', submitFilter);
});

// ── Quick date buttons ────────────────────────────────────────────────────
var dateFrom = document.getElementById('finDateFrom');
var dateTo   = document.getElementById('finDateTo');
var periodH  = document.getElementById('finPeriodHidden');

function fmtDate(d) {
    return d.getFullYear() + '-' +
           String(d.getMonth()+1).padStart(2,'0') + '-' +
           String(d.getDate()).padStart(2,'0');
}

document.querySelectorAll('.fin-qd-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var period = btn.getAttribute('data-period');
        var today  = new Date(); today.setHours(0,0,0,0);
        var from, to;
        if (period === 'today') {
            from = to = fmtDate(today);
        } else if (period === 'yesterday') {
            var yd = new Date(today); yd.setDate(yd.getDate()-1); from = to = fmtDate(yd);
        } else if (period === 'week') {
            var wd = new Date(today); wd.setDate(wd.getDate()-6); from = fmtDate(wd); to = fmtDate(today);
        } else if (period === 'month') {
            var md = new Date(today); md.setDate(md.getDate()-29); from = fmtDate(md); to = fmtDate(today);
        }
        dateFrom.value = from; dateTo.value = to; periodH.value = period;
        submitFilter();
    });
});
dateFrom.addEventListener('change', function() { periodH.value=''; submitFilter(); });
dateTo.addEventListener('change',   function() { periodH.value=''; submitFilter(); });

// ── Bulk selection ────────────────────────────────────────────────────────
var selCount  = document.getElementById('finSelCount');
var clearSel  = document.getElementById('finClearSel');
var checkAll  = document.getElementById('finCheckAll');
var splitTrig = document.getElementById('finSplitTrigger');
var splitDd   = document.getElementById('finSplitDd');

function getChecked() { return Array.from(document.querySelectorAll('.fin-row-check:checked')); }
function updateBulkBar() {
    var n = getChecked().length;
    selCount.textContent = n;
    selCount.classList.toggle('active', n > 0);
    clearSel.classList.toggle('hidden', n === 0);
}
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('fin-row-check')) updateBulkBar();
});
checkAll.addEventListener('change', function() {
    document.querySelectorAll('.fin-row-check').forEach(function(cb){ cb.checked = checkAll.checked; });
    updateBulkBar();
});
clearSel.addEventListener('click', function() {
    document.querySelectorAll('.fin-row-check').forEach(function(cb){ cb.checked=false; });
    checkAll.checked = false; updateBulkBar();
});
splitTrig.addEventListener('click', function(e) { e.stopPropagation(); splitDd.classList.toggle('open'); });
document.addEventListener('click', function() { splitDd.classList.remove('open'); });
document.getElementById('bulkCopyDocs').addEventListener('click', function() {
    var docs = getChecked().map(function(cb){ return cb.getAttribute('data-doc'); }).filter(Boolean);
    if (docs.length) { navigator.clipboard.writeText(docs.join('\n')); showToast('Скопійовано ' + docs.length + ' номерів'); }
    splitDd.classList.remove('open');
});
document.getElementById('bulkCopySums').addEventListener('click', function() {
    var sums = getChecked().map(function(cb){ return cb.getAttribute('data-sum'); });
    if (sums.length) { navigator.clipboard.writeText(sums.join('\n')); showToast('Скопійовано ' + sums.length + ' сум'); }
    splitDd.classList.remove('open');
});

// ── Direction toggle ──────────────────────────────────────────────────────
var dirBtnIn     = document.getElementById('dirBtnIn');
var dirBtnOut    = document.getElementById('dirBtnOut');
var panelDir     = document.getElementById('panelDir');
var panelExpCat  = document.getElementById('panelExpCat');
var panelExpWrap = document.getElementById('panelExpCatWrap');

function setDir(dir) {
    panelDir.value = dir;
    dirBtnIn.className  = 'fin-dir-btn' + (dir === 'in'  ? ' active-in'  : '');
    dirBtnOut.className = 'fin-dir-btn' + (dir === 'out' ? ' active-out' : '');
    panelExpWrap.style.display = (dir === 'out') ? '' : 'none';
}
dirBtnIn.addEventListener('click',  function() { setDir('in'); });
dirBtnOut.addEventListener('click', function() { setDir('out'); });

// ── CP Autocomplete ───────────────────────────────────────────────────────
var cpNameInput = document.getElementById('panelCpName');
var cpIdInput   = document.getElementById('panelCpId');
var cpDd        = document.getElementById('panelCpDd');
var cpTimer     = null;

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

cpNameInput.addEventListener('input', function() {
    cpIdInput.value = ''; updateCpLink();
    clearTimeout(cpTimer);
    var q = cpNameInput.value.trim();
    if (q.length < 2) { cpDd.innerHTML=''; cpDd.classList.remove('open'); return; }
    cpTimer = setTimeout(function() {
        fetch('/counterparties/api/search?q=' + encodeURIComponent(q) + '&limit=8')
            .then(function(r){ return r.json(); })
            .then(function(data) {
                if (!data.ok || !data.items || !data.items.length) {
                    cpDd.innerHTML=''; cpDd.classList.remove('open'); return;
                }
                cpDd.innerHTML = data.items.map(function(item) {
                    return '<div class="fin-cp-item" data-id="'+item.id+'" data-name="'+esc(item.name)+'">'
                         + esc(item.name) + '<small>' + esc(item.type_label||'') + '</small></div>';
                }).join('');
                cpDd.classList.add('open');
            }).catch(function(){});
    }, 220);
});
cpDd.addEventListener('click', function(e) {
    var item = e.target.closest('.fin-cp-item');
    if (!item) return;
    cpNameInput.value = item.getAttribute('data-name');
    cpIdInput.value   = item.getAttribute('data-id');
    cpDd.innerHTML=''; cpDd.classList.remove('open');
    updateCpLink();
});
document.addEventListener('click', function(e) {
    if (!cpNameInput.contains(e.target) && !cpDd.contains(e.target)) {
        cpDd.innerHTML=''; cpDd.classList.remove('open');
    }
});

// ── Panel ─────────────────────────────────────────────────────────────────
var panelEmpty    = document.getElementById('finPanelEmpty');
var panelForm     = document.getElementById('finPanelForm');
var panelTitle    = document.getElementById('finPanelTitle');
var panelId       = document.getElementById('panelId');
var panelSum      = document.getElementById('panelSum');
var panelDate     = document.getElementById('panelDate');
var panelDoc      = document.getElementById('panelDoc');
var panelPurp     = document.getElementById('panelPurpose');
var panelDesc     = document.getElementById('panelDesc');
var panelIsMove   = document.getElementById('panelIsMoving');
var panelIsPosted = document.getElementById('panelIsPosted');
var panelErr      = document.getElementById('panelErr');
var panelSrcBadge = document.getElementById('panelSrcBadge');
var cpLink        = document.getElementById('finCpLink');
var panelClose    = document.getElementById('finPanelClose');
var activeRow     = null;

function updateCpLink() {
    var id = cpIdInput.value;
    cpLink.style.display = id ? '' : 'none';
    if (id) cpLink.href = '/counterparties/view?id=' + id;
}

function showForm() {
    panelEmpty.style.display = 'none';
    panelForm.classList.add('visible');
    panelClose.style.display = 'flex';
}

function showEmpty() {
    panelEmpty.style.display = '';
    panelForm.classList.remove('visible');
    panelClose.style.display = 'none';
    panelTitle.textContent   = 'Документ';
    cpLink.style.display     = 'none';
    document.getElementById('linkedOrdersSection').style.display = 'none';
    if (activeRow) { activeRow.classList.remove('selected'); activeRow = null; }
}

function openPanel(row) {
    var d;
    try { d = JSON.parse(row.getAttribute('data-panel')); } catch(e) { return; }

    if (activeRow) activeRow.classList.remove('selected');
    row.classList.add('selected');
    activeRow = row;

    panelTitle.textContent  = d.doc || 'Без номера';
    panelId.value           = d.id   || '';
    panelSum.value          = d.sum  ? parseFloat(d.sum).toFixed(2) : '';
    panelDate.value         = d.moment ? d.moment.slice(0,10) : '';
    panelDoc.value          = d.doc     || '';
    panelPurp.value         = d.purpose || '';
    panelDesc.value         = d.desc    || '';
    panelIsMove.checked     = !!d.moving;
    panelIsPosted.checked   = d.is_posted !== undefined ? !!d.is_posted : true;
    cpNameInput.value       = d.cp_name || '';
    cpIdInput.value         = d.cp_id   || '';
    panelExpCat.value       = d.exp_cat_id ? String(d.exp_cat_id) : '';
    panelErr.textContent    = '';

    setDir(d.dir || 'in');
    updateCpLink();

    panelSrcBadge.style.display = (d.source && d.source !== 'manual') ? '' : 'none';
    if (d.source && d.source !== 'manual') panelSrcBadge.textContent = 'МойСклад';

    showForm();
    updateLinkedOrders();
}

function openNewPanel() {
    if (activeRow) { activeRow.classList.remove('selected'); activeRow = null; }
    panelTitle.textContent      = 'Новий документ';
    panelId.value               = '';
    panelSum.value              = '';
    panelDate.value             = fmtDate(new Date());
    panelDoc.value              = String(Math.floor(Date.now() / 1000));
    panelPurp.value             = '';
    panelDesc.value             = '';
    panelIsMove.checked         = false;
    panelIsPosted.checked       = true;
    cpNameInput.value           = '';
    cpIdInput.value             = '';
    panelExpCat.value           = '';
    panelErr.textContent        = '';
    panelSrcBadge.style.display = 'none';
    cpLink.style.display        = 'none';
    document.getElementById('linkedOrdersSection').style.display = 'none';
    setDir('in');
    showForm();
    panelSum.focus();
}

document.getElementById('btnAddDoc').addEventListener('click', openNewPanel);
panelClose.addEventListener('click', showEmpty);

document.getElementById('finTableBody').addEventListener('click', function(e) {
    var row = e.target.closest('tr.fin-row');
    if (!row) return;
    if (activeRow === row) return;
    openPanel(row);
});

// ── Save ──────────────────────────────────────────────────────────────────
document.getElementById('finPanelForm').addEventListener('submit', function(e) {
    e.preventDefault();
    panelErr.textContent = '';

    var id  = panelId.value;
    var dir = panelDir.value;
    var sum = parseFloat(panelSum.value);
    var dt  = panelDate.value;

    if (!dir)              { panelErr.textContent = 'Вкажіть напрям'; return; }
    if (isNaN(sum)||sum<=0){ panelErr.textContent = 'Сума має бути > 0'; return; }
    if (!dt)               { panelErr.textContent = 'Вкажіть дату'; return; }

    var saveBtn = document.getElementById('panelSaveBtn');
    saveBtn.disabled = true; saveBtn.textContent = '…';

    var body = new URLSearchParams();
    body.append('id',              id);
    body.append('direction',       dir);
    body.append('sum',             panelSum.value);
    body.append('moment',          dt);
    body.append('doc_number',      panelDoc.value);
    body.append('cp_id',           cpIdInput.value);
    body.append('payment_purpose', panelPurp.value);
    body.append('description',         panelDesc.value);
    body.append('expense_category_id', panelExpCat.value);
    if (panelIsMove.checked) body.append('is_moving', '1');
    body.append('is_posted', panelIsPosted.checked ? '1' : '0');

    fetch('/finance/api/save_cash', { method:'POST', body: body, credentials:'same-origin' })
        .then(function(r){
            return r.text().then(function(txt){
                try { return JSON.parse(txt); }
                catch(e) { throw new Error(txt.slice(0,200)); }
            });
        })
        .then(function(data) {
            if (!data.ok) { panelErr.textContent = data.error || 'Помилка'; return; }

            if (!id) {
                showToast('Документ додано');
                setTimeout(function(){ window.location.reload(); }, 700);
                return;
            }

            showToast('Збережено');

            if (activeRow) {
                var d;
                try { d = JSON.parse(activeRow.getAttribute('data-panel')); } catch(ex) { d = {}; }
                d.dir       = dir;   d.sum  = sum;
                d.doc       = panelDoc.value;
                d.purpose   = panelPurp.value;
                d.desc      = panelDesc.value;
                d.moving    = panelIsMove.checked;
                d.is_posted = panelIsPosted.checked ? 1 : 0;
                d.cp_name   = (data.cp_name !== undefined) ? data.cp_name : cpNameInput.value;
                d.cp_id     = cpIdInput.value || '';
                d.exp_cat_id = panelExpCat.value ? parseInt(panelExpCat.value) : null;
                activeRow.setAttribute('data-panel', JSON.stringify(d));

                var badge = activeRow.querySelector('.fin-badge-in,.fin-badge-out,.fin-badge-mov');
                if (badge) {
                    if (d.moving)        { badge.className='fin-badge-mov'; badge.textContent='⇄'; }
                    else if (dir==='in') { badge.className='fin-badge-in';  badge.textContent='↓'; }
                    else                 { badge.className='fin-badge-out'; badge.textContent='↑'; }
                }
                var draftBadge = activeRow.querySelector('.fin-badge-draft');
                if (d.is_posted) {
                    if (draftBadge) draftBadge.remove();
                } else {
                    if (!draftBadge) {
                        var nb = document.createElement('span');
                        nb.className = 'fin-badge-draft'; nb.textContent = 'чернетка';
                        badge.parentNode.appendChild(nb);
                    }
                }
                var sumCell = activeRow.querySelector('.fin-sum-cell');
                if (sumCell) {
                    sumCell.className   = 'fin-sum-cell ' + dir;
                    sumCell.textContent = (dir==='in'?'+':'−') + ' ' +
                        sum.toLocaleString('uk-UA',{minimumFractionDigits:2,maximumFractionDigits:2});
                }
                var descCell = activeRow.querySelector('.fin-desc-cell');
                if (descCell) {
                    descCell.textContent = panelDesc.value || '—';
                    descCell.title       = panelDesc.value || '';
                }
                activeRow.classList.toggle('moving', !!d.moving);
                panelTitle.textContent = d.doc || 'Без номера';
                updateCpLink();
            }
        })
        .catch(function(err){ panelErr.textContent = err.message || 'Помилка мережі'; console.error('save_cash error:', err); })
        .finally(function(){ saveBtn.disabled=false; saveBtn.textContent='Зберегти'; });
});

// ── Toast ────────────────────────────────────────────────────────────────
function showToast(msg) {
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(function() { t.classList.remove('show'); }, 1800);
}

// ── Linked Orders ────────────────────────────────────────────────────────
var loSection    = document.getElementById('linkedOrdersSection');
var loList       = document.getElementById('linkedOrdersList');
var loSearchWrap = document.getElementById('linkOrderSearch');
var loSearchQ    = document.getElementById('loSearchQ');
var loResults    = document.getElementById('loSearchResults');
var PAYMENT_TYPE = 'cashin';

function loStatusLabel(s) {
    var m = {not_paid:'Не оплачено',partially_paid:'Частково',paid:'Оплачено'};
    return m[s] || s || '';
}
function loStatusClass(s) {
    if (s === 'paid') return 'lo-st-paid';
    if (s === 'partially_paid') return 'lo-st-partial';
    return 'lo-st-none';
}

function updateLinkedOrders() {
    var id = panelId.value;
    var dir = panelDir.value;
    if (!id || dir !== 'in') { loSection.style.display = 'none'; return; }
    loSection.style.display = '';
    loSearchWrap.style.display = 'none';
    loList.innerHTML = '<span class="lo-loading">Завантаження…</span>';

    fetch('/finance/api/get_linked_orders?payment_id=' + id + '&payment_type=' + PAYMENT_TYPE)
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (!data.ok || !data.rows.length) {
                loList.innerHTML = '<span class="lo-empty">Немає прив\'язаних замовлень</span>';
                return;
            }
            loList.innerHTML = data.rows.map(function(o) {
                return '<div class="lo-item">'
                    + '<a href="/customerorder/edit?id=' + o.id + '" target="_blank" class="lo-num">'
                    + esc(o.number) + '</a>'
                    + '<span class="lo-date">' + (o.moment ? o.moment.slice(0,10) : '') + '</span>'
                    + '<span class="lo-sum">' + esc(o.sum_total_fmt) + '</span>'
                    + '<span class="lo-status ' + loStatusClass(o.payment_status) + '">'
                    + loStatusLabel(o.payment_status) + '</span>'
                    + '<button type="button" class="lo-unlink" data-order-id="' + o.id + '" title="Відв\'язати">×</button>'
                    + '</div>';
            }).join('');
        })
        .catch(function(){ loList.innerHTML = '<span class="lo-empty">Помилка</span>'; });
}

document.getElementById('linkOrderBtn').addEventListener('click', function() {
    var visible = loSearchWrap.style.display !== 'none';
    loSearchWrap.style.display = visible ? 'none' : '';
    if (!visible) { loSearchQ.value = ''; loResults.innerHTML = ''; loSearchQ.focus(); }
});

function loDoSearch() {
    var cpId = cpIdInput.value;
    var payId = panelId.value;
    var q = loSearchQ.value.trim();
    if (!cpId && !q) { loResults.innerHTML = '<div class="lo-empty">Введіть номер замовлення</div>'; return; }
    var url = '/finance/api/search_orders?cp_id=' + (cpId || '0')
        + '&payment_id=' + payId
        + '&payment_type=' + PAYMENT_TYPE
        + '&q=' + encodeURIComponent(q);
    loResults.innerHTML = '<span class="lo-loading">Пошук…</span>';
    fetch(url)
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (!data.ok || !data.rows.length) {
                loResults.innerHTML = '<div class="lo-empty">Нічого не знайдено</div>';
                return;
            }
            loResults.innerHTML = data.rows.map(function(o) {
                return '<div class="lo-result-item">'
                    + '<span class="lo-num">' + esc(o.number) + '</span>'
                    + (o.cp_name ? '<span class="lo-cp-hint">' + esc(o.cp_name) + '</span>' : '')
                    + '<span class="lo-date">' + (o.moment ? o.moment.slice(0,10) : '') + '</span>'
                    + '<span class="lo-sum">' + esc(o.sum_total_fmt) + '</span>'
                    + '<span class="lo-status ' + loStatusClass(o.payment_status) + '">'
                    + loStatusLabel(o.payment_status) + '</span>'
                    + '<button type="button" class="btn btn-primary btn-xs lo-link-btn" data-order-id="' + o.id + '">Прив\'язати</button>'
                    + '</div>';
            }).join('');
        })
        .catch(function(){ loResults.innerHTML = '<div class="lo-empty">Помилка</div>'; });
}

document.getElementById('loSearchBtn').addEventListener('click', loDoSearch);
loSearchQ.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); loDoSearch(); } });

// Link order
loResults.addEventListener('click', function(e) {
    var btn = e.target.closest('.lo-link-btn');
    if (!btn) return;
    var orderId = btn.getAttribute('data-order-id');
    btn.disabled = true; btn.textContent = '…';
    var body = new URLSearchParams();
    body.append('payment_id', panelId.value);
    body.append('payment_type', PAYMENT_TYPE);
    body.append('order_id', orderId);
    fetch('/finance/api/link_order', { method:'POST', body:body, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (!data.ok) { showToast(data.error || 'Помилка'); btn.disabled=false; btn.textContent='Прив\'язати'; return; }
            if (data.cp_updated && data.cp_name) {
                cpNameInput.value = data.cp_name;
                cpIdInput.value   = data.cp_id || '';
                updateCpLink();
                if (activeRow) {
                    var d; try { d = JSON.parse(activeRow.getAttribute('data-panel')); } catch(ex) { d = {}; }
                    d.cp_id = data.cp_id; d.cp_name = data.cp_name;
                    activeRow.setAttribute('data-panel', JSON.stringify(d));
                    var cpCell = activeRow.querySelector('.fin-cp');
                    if (cpCell) cpCell.innerHTML = '<a href="/counterparties/view?id=' + data.cp_id + '" target="_blank" onclick="event.stopPropagation()">' + esc(data.cp_name) + '</a>';
                    activeRow.classList.remove('unmatched');
                }
                showToast('Замовлення прив\'язано, контрагента оновлено');
            } else {
                showToast('Замовлення прив\'язано');
            }
            loSearchWrap.style.display = 'none';
            updateLinkedOrders();
        })
        .catch(function(){ btn.disabled=false; btn.textContent='Прив\'язати'; });
});

// Unlink order
loList.addEventListener('click', function(e) {
    var btn = e.target.closest('.lo-unlink');
    if (!btn) return;
    if (!confirm('Відв\'язати це замовлення?')) return;
    var orderId = btn.getAttribute('data-order-id');
    var body = new URLSearchParams();
    body.append('payment_id', panelId.value);
    body.append('payment_type', PAYMENT_TYPE);
    body.append('order_id', orderId);
    fetch('/finance/api/unlink_order', { method:'POST', body:body, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (!data.ok) { showToast(data.error || 'Помилка'); return; }
            showToast('Замовлення відв\'язано');
            updateLinkedOrders();
        });
});

// ── Auto-open first row ───────────────────────────────────────────────────
var firstRow = document.querySelector('#finTableBody tr.fin-row');
if (firstRow) { openPanel(firstRow); } else { showEmpty(); }

// ── Context menu ──────────────────────────────────────────────────────────
var ctxMenu     = document.getElementById('finCtxMenu');
var ctxTargetId = 0;

document.querySelectorAll('.fin-act-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        ctxMenu.style.display = 'none';
        ctxTargetId = parseInt(btn.getAttribute('data-id'), 10);
        var rect = btn.getBoundingClientRect();
        ctxMenu.style.display = 'block';
        var menuW = ctxMenu.offsetWidth || 160;
        var left  = Math.max(4, rect.right - menuW);
        ctxMenu.style.top  = (rect.bottom + 4) + 'px';
        ctxMenu.style.left = left + 'px';
    });
});

document.addEventListener('click', function(e) {
    if (!ctxMenu.contains(e.target)) {
        ctxMenu.style.display = 'none';
        ctxTargetId = 0;
    }
});

function deleteRows(ids, onDone) {
    var body = new URLSearchParams();
    if (ids.length === 1) {
        body.append('id', ids[0]);
    } else {
        body.append('ids', ids.join(','));
    }
    fetch('/finance/api/delete_cash', { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.ok) { showToast(data.error || 'Помилка видалення'); return; }
            onDone(data.deleted);
        })
        .catch(function() { showToast('Помилка мережі'); });
}

document.getElementById('ctxDelete').addEventListener('click', function() {
    ctxMenu.style.display = 'none';
    if (!ctxTargetId) return;
    var id = ctxTargetId;
    ctxTargetId = 0;
    if (!confirm('Видалити цей запис?')) return;
    deleteRows([id], function() {
        var row = document.querySelector('tr.fin-row[data-id="' + id + '"]');
        if (row) {
            if (activeRow === row) showEmpty();
            row.remove();
        }
        showToast('Видалено');
    });
});

document.getElementById('bulkDelete').addEventListener('click', function() {
    splitDd.classList.remove('open');
    var checked = getChecked();
    if (!checked.length) return;
    if (!confirm('Видалити ' + checked.length + ' записів?')) return;
    var ids = checked.map(function(cb) { return parseInt(cb.value, 10); });
    deleteRows(ids, function(deleted) {
        ids.forEach(function(id) {
            var row = document.querySelector('tr.fin-row[data-id="' + id + '"]');
            if (row) {
                if (activeRow === row) showEmpty();
                row.remove();
            }
        });
        checkAll.checked = false;
        updateBulkBar();
        showToast('Видалено ' + deleted);
    });
});

}());
</script>
<?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>