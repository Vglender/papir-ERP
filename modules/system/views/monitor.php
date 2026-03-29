<?php
require_once __DIR__ . '/../../../modules/shared/layout.php';
?>

<style>
/* ── Monitor layout ─────────────────────────────── */
.monitor-wrap    { max-width: 1200px; margin: 0 auto; padding: 20px 24px; }
.monitor-tabs    { display: flex; gap: 4px; border-bottom: 2px solid #e5e7eb; margin-bottom: 24px; }
.mtab            { padding: 8px 18px; font-size: 13px; font-weight: 500; color: #64748b;
                   border: none; background: none; cursor: pointer; border-radius: 6px 6px 0 0;
                   border-bottom: 2px solid transparent; margin-bottom: -2px; transition: color .15s; }
.mtab:hover      { color: #1e293b; background: #f8fafc; }
.mtab.active     { color: #0d9488; border-bottom-color: #0d9488; background: #f0fdfa; }
.mtab-stub       { color: #c4c9d4 !important; cursor: default !important; }
.mtab-stub:hover { background: none !important; }

.tab-pane        { display: none; }
.tab-pane.active { display: block; }

/* ── Status bar ─────────────────────────────────── */
.monitor-bar     { display: flex; align-items: center; gap: 12px; margin-bottom: 20px;
                   padding: 10px 14px; background: #f8fafc; border: 1px solid #e5e7eb;
                   border-radius: 8px; font-size: 13px; }
.monitor-bar-host{ font-weight: 700; font-size: 14px; color: #0f172a; }
.monitor-bar-sep { color: #cbd5e1; }
.monitor-bar-ts  { color: #64748b; }
.monitor-bar-right{ margin-left: auto; display: flex; align-items: center; gap: 8px; }
.refresh-btn     { display: flex; align-items: center; gap: 5px; font-size: 12px; color: #64748b;
                   border: 1px solid #e2e8f0; background: #fff; border-radius: 6px;
                   padding: 4px 10px; cursor: pointer; transition: all .15s; }
.refresh-btn:hover { color: #0d9488; border-color: #99f6e4; background: #f0fdfa; }
.refresh-btn svg { transition: transform .4s; }
.refresh-btn.spinning svg { animation: spin .6s linear infinite; }
@keyframes spin  { to { transform: rotate(360deg); } }
.countdown       { font-size: 12px; color: #94a3b8; }

/* ── Alert banner ───────────────────────────────── */
.alert-list      { margin-bottom: 20px; display: flex; flex-direction: column; gap: 6px; }
.alert-item      { display: flex; align-items: center; gap: 8px; padding: 9px 14px;
                   border-radius: 7px; font-size: 13px; font-weight: 500; }
.alert-item.error{ background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.alert-item.warn { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
.alert-item.ok   { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
.alert-icon      { flex-shrink: 0; }

/* ── Cards grid ─────────────────────────────────── */
.cards-grid      { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
.cards-grid-3    { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 16px; }
.mon-card        { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 18px 20px; }
.mon-card-full   { grid-column: 1 / -1; }
.mon-card h3     { margin: 0 0 14px; font-size: 12px; font-weight: 700; text-transform: uppercase;
                   letter-spacing: .6px; color: #94a3b8; }

/* ── Resource bars ──────────────────────────────── */
.res-row         { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
.res-row:last-child { margin-bottom: 0; }
.res-label       { width: 90px; font-size: 12px; color: #64748b; flex-shrink: 0; }
.res-bar-wrap    { flex: 1; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; }
.res-bar         { height: 100%; border-radius: 4px; transition: width .4s ease; background: #0d9488; }
.res-bar.warn    { background: #f59e0b; }
.res-bar.danger  { background: #ef4444; }
.res-val         { width: 80px; text-align: right; font-size: 12px; font-weight: 600; color: #334155; flex-shrink: 0; }
.res-sub         { font-size: 11px; color: #94a3b8; margin-top: 2px; }

/* ── KV info list ───────────────────────────────── */
.kv-list         { display: flex; flex-direction: column; gap: 7px; }
.kv-row          { display: flex; justify-content: space-between; align-items: baseline;
                   font-size: 13px; gap: 10px; }
.kv-key          { color: #64748b; }
.kv-val          { font-weight: 600; color: #1e293b; text-align: right; word-break: break-all; }

/* ── Services ───────────────────────────────────── */
.svc-list        { display: flex; flex-direction: column; gap: 8px; }
.svc-row         { display: flex; align-items: center; gap: 10px; padding: 9px 12px;
                   border-radius: 7px; background: #f8fafc; border: 1px solid #e5e7eb; }
.svc-dot         { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.svc-dot.running { background: #22c55e; box-shadow: 0 0 0 3px #dcfce7; }
.svc-dot.stopped,
.svc-dot.failed  { background: #ef4444; box-shadow: 0 0 0 3px #fee2e2; }
.svc-dot.unknown { background: #94a3b8; box-shadow: 0 0 0 3px #e2e8f0; }
.svc-name        { font-weight: 600; font-size: 13px; color: #1e293b; flex: 1; }
.svc-ver         { font-size: 11px; color: #94a3b8; }
.svc-status-lbl  { font-size: 12px; font-weight: 500; }
.svc-status-lbl.running { color: #16a34a; }
.svc-status-lbl.stopped,
.svc-status-lbl.failed  { color: #dc2626; }
.svc-status-lbl.unknown { color: #64748b; }

/* ── Disk table ─────────────────────────────────── */
.disk-table      { width: 100%; border-collapse: collapse; font-size: 13px; }
.disk-table th   { text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase;
                   letter-spacing: .5px; color: #94a3b8; padding: 0 8px 8px; }
.disk-table td   { padding: 6px 8px; }
.disk-table tr + tr td { border-top: 1px solid #f1f5f9; }
.disk-bar-cell   { width: 160px; }

/* ── PHP extensions ─────────────────────────────── */
.ext-grid        { display: flex; flex-wrap: wrap; gap: 6px; }
.ext-pill        { font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 12px; }
.ext-pill.on     { background: #dcfce7; color: #15803d; }
.ext-pill.off    { background: #fee2e2; color: #dc2626; }

/* ── Terminal tab ───────────────────────────────── */
.term-layout     { display: grid; grid-template-columns: 220px 1fr; gap: 16px; align-items: start; }
.term-sidebar    { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
                   padding: 12px; display: flex; flex-direction: column; gap: 4px; }
.term-group      { margin-bottom: 8px; }
.term-group-label{ font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px;
                   color: #94a3b8; padding: 4px 6px 6px; }
.term-btn        { width: 100%; text-align: left; padding: 7px 10px; font-size: 12px; font-weight: 500;
                   color: #334155; background: #f8fafc; border: 1px solid #e5e7eb;
                   border-radius: 6px; cursor: pointer; margin-bottom: 3px; transition: all .12s; }
.term-btn:hover  { background: #f0fdfa; border-color: #99f6e4; color: #0d9488; }
.term-btn.active { background: #f0fdfa; border-color: #0d9488; color: #0d9488; }
.term-btn-action { color: #d97706 !important; background: #fffbeb !important; border-color: #fde68a !important; }
.term-btn-action:hover { background: #fef3c7 !important; border-color: #f59e0b !important; }
.term-btn-run    { color: #7c3aed !important; background: #f5f3ff !important; border-color: #ddd6fe !important; }
.term-btn-run:hover { background: #ede9fe !important; border-color: #a78bfa !important; }
.term-hint       { display: block; font-size: 10px; font-weight: 400; color: #94a3b8; margin-top: 1px; }
.term-output-wrap{ display: flex; flex-direction: column; background: #0f172a;
                   border-radius: 10px; overflow: hidden; min-height: 520px; }
.term-output-head{ display: flex; align-items: center; gap: 8px; padding: 10px 14px;
                   background: #1e293b; border-bottom: 1px solid #334155; }
.term-clear-btn  { margin-left: 8px; background: none; border: none; color: #64748b;
                   cursor: pointer; font-size: 14px; padding: 2px 6px; border-radius: 4px; }
.term-clear-btn:hover { color: #ef4444; background: #1e293b; }
.term-output     { flex: 1; padding: 16px; font-family: 'Courier New', monospace; font-size: 12px;
                   line-height: 1.6; color: #e2e8f0; white-space: pre-wrap; word-break: break-all;
                   overflow-y: auto; min-height: 480px; }
.term-output.running { color: #94a3b8; }

/* ── Crons tab ──────────────────────────────────── */
.cron-sched      { font-family: monospace; font-size: 11px; color: #0d9488;
                   background: #f0fdfa; padding: 2px 6px; border-radius: 4px; }
.cron-active     { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 600; }
.cron-active.yes { color: #15803d; }
.cron-active.no  { color: #dc2626; }
.job-status      { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
.job-status.running { background: #dbeafe; color: #1d4ed8; }
.job-status.done    { background: #dcfce7; color: #15803d; }
.job-status.failed  { background: #fee2e2; color: #dc2626; }
/* ── Jobs toolbar / filter ──────────────────────── */
.jobs-toolbar { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.jobs-toolbar h3 { margin: 0; flex-shrink: 0; }
.jobs-search { flex: 1; min-width: 160px; height: 34px; padding: 0 10px;
               border: 1px solid var(--border, #e2e8f0); border-radius: 6px;
               font-size: 13px; color: var(--text, #1e293b); background: #fff; }
.jobs-search:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 2px #eff6ff; }
.jobs-date-input { height: 28px; font-size: 12px; border: 1px solid var(--border, #e2e8f0);
                   border-radius: 4px; padding: 0 6px; color: var(--text, #1e293b); background: #fff; }
.jobs-date-input:focus { outline: none; border-color: #3b82f6; }
.jobs-total { font-size: 12px; color: #94a3b8; flex-shrink: 0; }

/* ── Logs tab ───────────────────────────────────── */
.log-toolbar     { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; }
.log-file-select { height: 32px; padding: 0 10px; border: 1px solid #e2e8f0; border-radius: 6px;
                   font-size: 13px; min-width: 220px; background: #fff; }
.log-lines-select{ height: 32px; padding: 0 8px; border: 1px solid #e2e8f0; border-radius: 6px;
                   font-size: 13px; background: #fff; }
.log-filter-input{ height: 32px; padding: 0 10px; border: 1px solid #e2e8f0; border-radius: 6px;
                   font-size: 13px; width: 180px; }
.log-auto-label  { display: flex; align-items: center; gap: 5px; font-size: 13px; color: #475569; cursor: pointer; }
.log-output      { background: #0f172a; border-radius: 10px; padding: 16px;
                   font-family: 'Courier New', monospace; font-size: 11.5px; line-height: 1.55;
                   color: #e2e8f0; white-space: pre-wrap; word-break: break-all;
                   overflow-y: auto; min-height: 520px; max-height: 70vh; }
.log-line-err    { color: #f87171; }
.log-line-warn   { color: #fbbf24; }
.log-line-hi     { background: rgba(253,224,71,.15); }

/* ── AI кнопка в тулбарі ────────────────────────── */
.btn-ai          { background: #7c3aed; color: #fff; border-color: #7c3aed; }
.btn-ai:hover    { background: #6d28d9; border-color: #6d28d9; }
.btn-ai:disabled { background: #a78bfa; border-color: #a78bfa; cursor: default; }
.btn-ai.loading  { background: #a78bfa; border-color: #a78bfa; }

/* ── AI панель ──────────────────────────────────── */
.ai-panel        { background: #fff; border: 1px solid #ddd6fe; border-radius: 10px;
                   margin-top: 12px; overflow: hidden; }
.ai-panel-head   { display: flex; align-items: center; justify-content: space-between;
                   padding: 12px 16px; background: #faf5ff; border-bottom: 1px solid #ddd6fe; gap: 10px; }
.ai-panel-title  { font-weight: 700; font-size: 13px; color: #6d28d9; }
.ai-panel-file   { font-size: 12px; color: #a78bfa; }
.ai-close-btn    { background: none; border: none; color: #a78bfa; cursor: pointer;
                   font-size: 16px; padding: 2px 6px; border-radius: 4px; }
.ai-close-btn:hover { color: #7c3aed; background: #ede9fe; }
.ai-panel-body   { padding: 20px; }

/* Анімація "думає" */
.ai-thinking     { display: flex; align-items: center; padding: 12px 0; }
.ai-dot          { width: 8px; height: 8px; border-radius: 50%; background: #7c3aed;
                   margin: 0 3px; animation: aiPulse 1.2s ease-in-out infinite; }
.ai-dot:nth-child(2) { animation-delay: .2s; }
.ai-dot:nth-child(3) { animation-delay: .4s; }
@keyframes aiPulse { 0%,80%,100% { opacity:.2; transform:scale(.8); } 40% { opacity:1; transform:scale(1); } }

/* Markdown-like рендер відповіді */
.ai-result       { font-size: 14px; line-height: 1.7; color: #1e293b; }
.ai-result h2    { font-size: 14px; font-weight: 700; color: #6d28d9; margin: 16px 0 6px;
                   padding-bottom: 4px; border-bottom: 1px solid #ede9fe; }
.ai-result h2:first-child { margin-top: 0; }
.ai-result p     { margin: 0 0 10px; }
.ai-result ul    { margin: 0 0 10px; padding-left: 20px; }
.ai-result li    { margin-bottom: 4px; }
.ai-result code  { background: #f1f5f9; padding: 1px 5px; border-radius: 4px;
                   font-family: monospace; font-size: 12px; color: #0f172a; }
.ai-result pre   { background: #0f172a; color: #e2e8f0; padding: 12px 14px; border-radius: 8px;
                   overflow-x: auto; font-family: monospace; font-size: 12px; line-height: 1.5; margin: 8px 0 12px; }
.ai-result pre code { background: none; padding: 0; color: inherit; font-size: inherit; }
.ai-result strong{ font-weight: 600; }
</style>

<div class="monitor-wrap">

    <!-- Tabs -->
    <div class="monitor-tabs">
        <button class="mtab active" data-tab="overview">Огляд</button>
        <button class="mtab" data-tab="terminal">Термінал</button>
        <button class="mtab" data-tab="crons">Крони</button>
        <button class="mtab" data-tab="logs">Логи</button>
    </div>

    <!-- Status bar -->
    <div class="monitor-bar">
        <span class="monitor-bar-host" id="barHostname">—</span>
        <span class="monitor-bar-sep">·</span>
        <span id="barOs" style="color:#475569;font-size:13px">—</span>
        <span class="monitor-bar-sep">·</span>
        <span style="color:#475569;font-size:13px">Uptime: <strong id="barUptime">—</strong></span>
        <div class="monitor-bar-right">
            <span class="countdown" id="cdTimer"></span>
            <button class="refresh-btn" id="refreshBtn">
                <svg width="13" height="13" viewBox="0 0 16 16" fill="none">
                    <path d="M13.5 2.5A6.5 6.5 0 1 1 8 1.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                    <path d="M8 1.5V4.5L11 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Оновити
            </button>
            <span class="monitor-bar-ts" id="barTs">—</span>
        </div>
    </div>

    <!-- ═══ TAB: Огляд ════════════════════════════════════════════ -->
    <div class="tab-pane active" id="tab-overview">

        <!-- Alerts -->
        <div class="alert-list" id="alertList" style="display:none"></div>

        <!-- Row 1: CPU + Memory -->
        <div class="cards-grid">

            <div class="mon-card">
                <h3>Навантаження CPU</h3>
                <div class="res-row">
                    <span class="res-label">Поточне</span>
                    <div class="res-bar-wrap"><div class="res-bar" id="loadBar" style="width:0%"></div></div>
                    <span class="res-val"><span id="loadVal">—</span></span>
                </div>
                <div style="display:flex;gap:20px;margin-top:10px">
                    <div>
                        <div style="font-size:11px;color:#94a3b8">1 хв</div>
                        <div style="font-size:18px;font-weight:700;color:#1e293b" id="load1">—</div>
                    </div>
                    <div>
                        <div style="font-size:11px;color:#94a3b8">5 хв</div>
                        <div style="font-size:18px;font-weight:700;color:#1e293b" id="load5">—</div>
                    </div>
                    <div>
                        <div style="font-size:11px;color:#94a3b8">15 хв</div>
                        <div style="font-size:18px;font-weight:700;color:#1e293b" id="load15">—</div>
                    </div>
                    <div style="margin-left:auto;text-align:right">
                        <div style="font-size:11px;color:#94a3b8">Ядер</div>
                        <div style="font-size:18px;font-weight:700;color:#0d9488" id="cpuCores">—</div>
                    </div>
                </div>
                <div style="margin-top:12px;font-size:11px;color:#94a3b8" id="cpuModel">—</div>
            </div>

            <div class="mon-card">
                <h3>Пам'ять</h3>
                <div class="res-row">
                    <span class="res-label">RAM</span>
                    <div class="res-bar-wrap"><div class="res-bar" id="ramBar" style="width:0%"></div></div>
                    <span class="res-val"><span id="ramPct">—</span>%</span>
                </div>
                <div class="res-sub" id="ramSub" style="margin-left:100px;margin-bottom:10px">—</div>
                <div class="res-row">
                    <span class="res-label">Swap</span>
                    <div class="res-bar-wrap"><div class="res-bar" id="swapBar" style="width:0%"></div></div>
                    <span class="res-val"><span id="swapPct">—</span>%</span>
                </div>
                <div class="res-sub" id="swapSub" style="margin-left:100px">—</div>
            </div>

        </div>

        <!-- Row 2: Disk + Services -->
        <div class="cards-grid">

            <div class="mon-card">
                <h3>Диски</h3>
                <table class="disk-table">
                    <thead>
                        <tr>
                            <th>Пристрій</th>
                            <th>Точка монтування</th>
                            <th>Всього</th>
                            <th>Використано</th>
                            <th class="disk-bar-cell"></th>
                        </tr>
                    </thead>
                    <tbody id="diskBody">
                        <tr><td colspan="5" style="color:#94a3b8;font-size:13px">Завантаження…</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="mon-card">
                <h3>Сервіси</h3>
                <div class="svc-list" id="svcList">
                    <div style="color:#94a3b8;font-size:13px">Завантаження…</div>
                </div>
            </div>

        </div>

        <!-- Row 3: System info + PHP -->
        <div class="cards-grid">

            <div class="mon-card">
                <h3>Система</h3>
                <div class="kv-list" id="sysInfo">
                    <div style="color:#94a3b8;font-size:13px">Завантаження…</div>
                </div>
            </div>

            <div class="mon-card">
                <h3>PHP</h3>
                <div class="kv-list" id="phpInfo">
                    <div style="color:#94a3b8;font-size:13px">Завантаження…</div>
                </div>
                <div style="margin-top:14px">
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#94a3b8;margin-bottom:8px">Розширення</div>
                    <div class="ext-grid" id="phpExt"></div>
                </div>
            </div>

        </div>

    </div><!-- /tab-overview -->

    <!-- ═══ TAB: Термінал ════════════════════════════════════════ -->
    <div class="tab-pane" id="tab-terminal">
        <div class="term-layout">

            <div class="term-sidebar">
                <div class="term-group">
                    <div class="term-group-label">Nginx</div>
                    <button class="term-btn" data-cmd="nginx_test">nginx -t <span class="term-hint">перевірити конфіг</span></button>
                    <button class="term-btn term-btn-action" data-cmd="nginx_reload">reload nginx <span class="term-hint">перезавантажити</span></button>
                    <button class="term-btn" data-cmd="nginx_status">status nginx</button>
                </div>
                <div class="term-group">
                    <div class="term-group-label">PHP-FPM</div>
                    <button class="term-btn term-btn-action" data-cmd="phpfpm_reload">reload php-fpm <span class="term-hint">після змін Router</span></button>
                    <button class="term-btn" data-cmd="phpfpm_status">status php-fpm</button>
                </div>
                <div class="term-group">
                    <div class="term-group-label">MySQL</div>
                    <button class="term-btn" data-cmd="mysql_status">status mysqld</button>
                </div>
                <div class="term-group">
                    <div class="term-group-label">Система</div>
                    <button class="term-btn" data-cmd="uptime">uptime</button>
                    <button class="term-btn" data-cmd="df">df -h</button>
                    <button class="term-btn" data-cmd="free">free -h</button>
                    <button class="term-btn" data-cmd="top_cpu">top processes (CPU)</button>
                    <button class="term-btn" data-cmd="top_mem">top processes (RAM)</button>
                    <button class="term-btn" data-cmd="ports">listening ports</button>
                </div>
                <div class="term-group">
                    <div class="term-group-label">PHP</div>
                    <button class="term-btn" data-cmd="php_version">php -v</button>
                    <button class="term-btn" data-cmd="php_modules">php -m</button>
                </div>
                <div class="term-group">
                    <div class="term-group-label">Запустити крон</div>
                    <button class="term-btn term-btn-run" data-cmd="cron_sync_stock">sync_stock.php</button>
                    <button class="term-btn term-btn-run" data-cmd="cron_sync_quantity">sync_quantity.php</button>
                    <button class="term-btn term-btn-run" data-cmd="cron_sync_prices">sync_prices.php</button>
                    <button class="term-btn term-btn-run" data-cmd="cron_sync_action">sync_action.php</button>
                </div>
            </div>

            <div class="term-output-wrap">
                <div class="term-output-head">
                    <span id="termCmdLabel" style="font-size:13px;color:#94a3b8">← Виберіть команду</span>
                    <span id="termTime" style="font-size:12px;color:#475569;margin-left:auto"></span>
                    <button class="term-clear-btn" id="termClear" title="Очистити">&#x2715;</button>
                </div>
                <div class="term-output" id="termOutput">
                    <span style="color:#475569">Натисніть кнопку команди ліворуч</span>
                </div>
            </div>

        </div>
    </div>

    <!-- ═══ TAB: Крони ══════════════════════════════════════════ -->
    <div class="tab-pane" id="tab-crons">

        <div class="mon-card">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
                <h3 style="margin:0">Крони Papir</h3>
                <button class="btn btn-ghost btn-sm" id="cronRefreshBtn">Оновити</button>
            </div>
            <table class="crm-table" id="cronsTable" style="font-size:12px">
                <thead>
                    <tr>
                        <th>Назва</th>
                        <th>Розклад</th>
                        <th style="text-align:center">Активний</th>
                        <th>Останній запуск</th>
                    </tr>
                </thead>
                <tbody><tr><td colspan="4" style="color:#94a3b8">Завантаження…</td></tr></tbody>
            </table>
        </div>

        <div class="mon-card" style="margin-top:0">
            <!-- Toolbar -->
            <div class="jobs-toolbar">
                <h3>Фонові процеси</h3>
                <input type="text" class="jobs-search" id="jobsSearch" placeholder="Пошук за назвою або скриптом…" autocomplete="off">
                <button class="btn btn-ghost btn-sm" id="jobsRefreshBtn" style="height:34px;flex-shrink:0">Оновити</button>
                <span class="jobs-total" id="jobsTotal"></span>
            </div>
            <!-- Filter bar -->
            <div class="filter-bar" style="margin-bottom:10px">
                <div class="filter-bar-group">
                    <span class="filter-bar-label">Статус</span>
                    <label class="filter-pill active" id="jsPillAll">
                        <input type="radio" name="jobsStatus" value=""> Всі
                    </label>
                    <label class="filter-pill" id="jsPillRunning">
                        <input type="radio" name="jobsStatus" value="running"> running
                    </label>
                    <label class="filter-pill" id="jsPillDone">
                        <input type="radio" name="jobsStatus" value="done"> done
                    </label>
                    <label class="filter-pill" id="jsPillFailed">
                        <input type="radio" name="jobsStatus" value="failed"> failed
                    </label>
                </div>
                <div class="filter-bar-sep"></div>
                <div class="filter-bar-group">
                    <span class="filter-bar-label">Дата</span>
                    <input type="date" class="jobs-date-input" id="jobsDateFrom" title="Від">
                    <span style="color:#94a3b8;font-size:12px">—</span>
                    <input type="date" class="jobs-date-input" id="jobsDateTo" title="До">
                    <button type="button" class="btn btn-ghost btn-xs" id="jobsDateClear" style="height:28px;display:none" title="Скинути дати">&#x2715;</button>
                </div>
                <button type="button" class="filter-bar-gear" title="Налаштувати фільтри">
                    <svg viewBox="0 0 16 16" fill="none"><path d="M8 10a2 2 0 100-4 2 2 0 000 4z" stroke="currentColor" stroke-width="1.3"/><path d="M8 1v1.5M8 13.5V15M1 8h1.5M13.5 8H15M3.05 3.05l1.06 1.06M11.89 11.89l1.06 1.06M3.05 12.95l1.06-1.06M11.89 4.11l1.06-1.06" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                </button>
            </div>
            <!-- Table -->
            <table class="crm-table" id="jobsTable" style="font-size:12px">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Задача</th>
                        <th>Статус</th>
                        <th>Запущено</th>
                        <th>Завершено</th>
                        <th>Лог</th>
                    </tr>
                </thead>
                <tbody><tr><td colspan="6" style="color:#94a3b8">Завантаження…</td></tr></tbody>
            </table>
            <!-- Pagination -->
            <div class="pagination" id="jobsPagination"></div>
        </div>

    </div>

    <!-- ═══ TAB: Логи ════════════════════════════════════════════ -->
    <div class="tab-pane" id="tab-logs">

        <div class="log-toolbar">
            <select id="logFileSelect" class="log-file-select">
                <option value="">— Виберіть файл —</option>
            </select>
            <select id="logLinesSelect" class="log-lines-select">
                <option value="100">100 рядків</option>
                <option value="200" selected>200 рядків</option>
                <option value="500">500 рядків</option>
                <option value="1000">1000 рядків</option>
                <option value="2000">2000 рядків</option>
            </select>
            <button class="btn btn-ghost btn-sm" id="logRefreshBtn">Оновити</button>
            <button class="btn btn-ghost btn-sm" id="logCopyBtn" title="Копіювати лог">Копіювати</button>
            <button class="btn btn-ghost btn-sm" id="logClearBtn" title="Очистити вивід">&#x2715; Очистити</button>
            <button class="btn btn-ai btn-sm" id="logAiBtn" title="Аналізувати лог через AI">AI Аналіз</button>
            <label class="log-auto-label">
                <input type="checkbox" id="logAutoRefresh"> авто (10с)
            </label>
            <input type="text" class="log-filter-input" id="logFilter" placeholder="Фільтр…">
            <span style="margin-left:auto;font-size:12px;color:#94a3b8" id="logMeta"></span>
        </div>

        <div class="log-output" id="logOutput">
            <span style="color:#475569">Виберіть файл лога вище</span>
        </div>

        <!-- AI панель -->
        <div class="ai-panel" id="aiPanel" style="display:none">
            <div class="ai-panel-head">
                <div style="display:flex;align-items:center;gap:8px">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="#7c3aed" stroke-width="1.7"/><path d="M8 12s1.5-3 4-3 4 3 4 3-1.5 3-4 3-4-3-4-3z" stroke="#7c3aed" stroke-width="1.5"/><circle cx="12" cy="12" r="1.5" fill="#7c3aed"/></svg>
                    <span class="ai-panel-title">AI Аналіз</span>
                    <span class="ai-panel-file" id="aiPanelFile"></span>
                </div>
                <div style="display:flex;align-items:center;gap:8px">
                    <button class="btn btn-ghost btn-sm" id="aiCopyBtn" title="Копіювати відповідь AI">Копіювати</button>
                    <button class="btn btn-danger btn-sm" id="aiClearFileBtn" style="display:none">Очистити файл лога</button>
                    <button class="ai-close-btn" id="aiPanelClose">&#x2715;</button>
                </div>
            </div>
            <div class="ai-panel-body" id="aiPanelBody">
                <div class="ai-thinking" id="aiThinking" style="display:none">
                    <span class="ai-dot"></span><span class="ai-dot"></span><span class="ai-dot"></span>
                    <span style="margin-left:8px;color:#7c3aed;font-size:13px">Аналізую лог…</span>
                </div>
                <div class="ai-result" id="aiResult"></div>
            </div>
        </div>

    </div>

</div><!-- /monitor-wrap -->

<script>
(function () {

var REFRESH_INTERVAL = 30; // seconds
var countdown = REFRESH_INTERVAL;
var timer     = null;
var cdEl      = document.getElementById('cdTimer');
var refreshBtn= document.getElementById('refreshBtn');

// ── Tabs ─────────────────────────────────────────────────────────────────────
document.querySelectorAll('.mtab:not(.mtab-stub)').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.mtab').forEach(function (b) { b.classList.remove('active'); });
        document.querySelectorAll('.tab-pane').forEach(function (p) { p.classList.remove('active'); });
        btn.classList.add('active');
        var target = 'tab-' + btn.dataset.tab;
        var pane = document.getElementById(target);
        if (pane) pane.classList.add('active');
    });
});

// ── Render helpers ────────────────────────────────────────────────────────────
function barClass(pct) {
    if (pct >= 90) return 'res-bar danger';
    if (pct >= 70) return 'res-bar warn';
    return 'res-bar';
}

function svcStatusLabel(status) {
    var map = { running: 'Активний', stopped: 'Зупинено', failed: 'Помилка', unknown: 'Невідомо' };
    return map[status] || status;
}

function kvRow(key, val) {
    return '<div class="kv-row"><span class="kv-key">' + key + '</span><span class="kv-val">' + val + '</span></div>';
}

function diskBar(pct) {
    var cls = barClass(pct);
    return '<div class="res-bar-wrap" style="height:6px"><div class="' + cls + '" style="width:' + pct + '%"></div></div>';
}

function formatDiskKb(kb) {
    if (kb >= 1048576) return (kb / 1048576).toFixed(1) + ' GB';
    if (kb >= 1024)    return (kb / 1024).toFixed(1)    + ' MB';
    return kb + ' KB';
}

// ── Render data ───────────────────────────────────────────────────────────────
function render(d) {
    // Status bar
    document.getElementById('barHostname').textContent = d.system.hostname;
    document.getElementById('barOs').textContent       = d.system.os;
    document.getElementById('barUptime').textContent   = d.system.uptime;
    document.getElementById('barTs').textContent       = 'оновлено ' + d.timestamp.split(' ')[1];

    // Alerts
    var alertList = document.getElementById('alertList');
    if (d.alerts && d.alerts.length > 0) {
        var html = '';
        d.alerts.forEach(function (a) {
            var icon = a.level === 'error'
                ? '<svg class="alert-icon" width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5"/><path d="M8 5v3M8 10.5v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>'
                : '<svg class="alert-icon" width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 2L14 13H2L8 2z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M8 7v2.5M8 11v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';
            html += '<div class="alert-item ' + a.level + '">' + icon + a.msg + '</div>';
        });
        alertList.innerHTML = html;
        alertList.style.display = 'flex';
    } else {
        alertList.innerHTML = '<div class="alert-item ok"><svg class="alert-icon" width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5"/><path d="M5 8l2.5 2.5L11 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>Все в нормі</div>';
        alertList.style.display = 'flex';
    }

    // CPU
    var lBar = document.getElementById('loadBar');
    lBar.style.width = d.cpu.load_pct + '%';
    lBar.className = barClass(d.cpu.load_pct);
    document.getElementById('loadVal').textContent = d.cpu.load_pct + '%';
    document.getElementById('load1').textContent   = d.cpu.load1;
    document.getElementById('load5').textContent   = d.cpu.load5;
    document.getElementById('load15').textContent  = d.cpu.load15;
    document.getElementById('cpuCores').textContent= d.cpu.cores;
    document.getElementById('cpuModel').textContent= d.cpu.model;

    // RAM
    var rBar = document.getElementById('ramBar');
    rBar.style.width = d.memory.pct + '%';
    rBar.className = barClass(d.memory.pct);
    document.getElementById('ramPct').textContent = d.memory.pct;
    document.getElementById('ramSub').textContent = d.memory.used + ' / ' + d.memory.total + ' (доступно: ' + d.memory.available + ')';
    var sBar = document.getElementById('swapBar');
    sBar.style.width = d.memory.swap_pct + '%';
    sBar.className = barClass(d.memory.swap_pct);
    document.getElementById('swapPct').textContent = d.memory.swap_pct;
    document.getElementById('swapSub').textContent = d.memory.swap_used + ' / ' + d.memory.swap_total;

    // Disks
    var diskHtml = '';
    d.disks.forEach(function (disk) {
        diskHtml += '<tr>'
            + '<td style="font-size:12px;color:#475569">' + disk.dev + '</td>'
            + '<td style="font-weight:600">' + disk.mount + '</td>'
            + '<td>' + formatDiskKb(disk.total) + '</td>'
            + '<td>' + formatDiskKb(disk.used) + ' <span style="color:#94a3b8;font-size:11px">(' + disk.pct + '%)</span></td>'
            + '<td class="disk-bar-cell">' + diskBar(disk.pct) + '</td>'
            + '</tr>';
    });
    document.getElementById('diskBody').innerHTML = diskHtml || '<tr><td colspan="5" style="color:#94a3b8">Немає даних</td></tr>';

    // Services
    var svcHtml = '';
    d.services.forEach(function (svc) {
        svcHtml += '<div class="svc-row">'
            + '<span class="svc-dot ' + svc.status + '"></span>'
            + '<span class="svc-name">' + svc.label + '</span>'
            + '<span class="svc-ver">' + svc.version + '</span>'
            + '<span class="svc-status-lbl ' + svc.status + '">' + svcStatusLabel(svc.status) + '</span>'
            + '</div>';
    });
    document.getElementById('svcList').innerHTML = svcHtml || '<div style="color:#94a3b8;font-size:13px">Немає даних</div>';

    // System info
    var s = d.system;
    document.getElementById('sysInfo').innerHTML =
        kvRow('Хост', s.hostname) +
        kvRow('ОС', s.os) +
        kvRow('Ядро', s.kernel) +
        kvRow('Архітектура', s.arch) +
        kvRow('Uptime', s.uptime) +
        kvRow('CPU', d.cpu.cores + ' ядер');

    // PHP
    var p = d.php;
    var phpHtml =
        kvRow('Версія', '<span style="color:#0d9488;font-weight:700">' + p.version + '</span>') +
        kvRow('memory_limit', p.memory_limit) +
        kvRow('max_execution_time', p.max_execution_time) +
        kvRow('upload_max_filesize', p.upload_max_filesize) +
        kvRow('post_max_size', p.post_max_size) +
        kvRow('max_input_vars', p.max_input_vars) +
        kvRow('display_errors', p.display_errors) +
        kvRow('Часовий пояс', p.date_timezone) +
        kvRow('OPcache', p.opcache_enabled);
    if (p.opcache_hit_rate) phpHtml += kvRow('OPcache hit rate', p.opcache_hit_rate);
    document.getElementById('phpInfo').innerHTML = phpHtml;

    // PHP extensions
    var extHtml = '';
    for (var ext in d.php_ext) {
        if (d.php_ext.hasOwnProperty(ext)) {
            extHtml += '<span class="ext-pill ' + (d.php_ext[ext] ? 'on' : 'off') + '">' + ext + '</span>';
        }
    }
    document.getElementById('phpExt').innerHTML = extHtml;
}

// ── Load data ─────────────────────────────────────────────────────────────────
function loadStats(showSpinner) {
    if (showSpinner) refreshBtn.classList.add('spinning');

    fetch('/system/api/get_stats', { method: 'GET' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            refreshBtn.classList.remove('spinning');
            if (d.ok) render(d);
            resetCountdown();
        })
        .catch(function () {
            refreshBtn.classList.remove('spinning');
            resetCountdown();
        });
}

// ── Countdown ─────────────────────────────────────────────────────────────────
function resetCountdown() {
    countdown = REFRESH_INTERVAL;
    if (timer) clearInterval(timer);
    timer = setInterval(function () {
        countdown--;
        if (cdEl) cdEl.textContent = countdown + 'с';
        if (countdown <= 0) {
            clearInterval(timer);
            loadStats(false);
        }
    }, 1000);
}

refreshBtn.addEventListener('click', function () {
    if (timer) clearInterval(timer);
    loadStats(true);
});

// Initial load
loadStats(true);

}());

// ══════════════════════════════════════════════════════ TERMINAL TAB
(function () {

var output    = document.getElementById('termOutput');
var cmdLabel  = document.getElementById('termCmdLabel');
var timeEl    = document.getElementById('termTime');
var clearBtn  = document.getElementById('termClear');

document.querySelectorAll('.term-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var cmd = btn.dataset.cmd;
        document.querySelectorAll('.term-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');

        var label = btn.textContent.replace(/\s+/g, ' ').trim();
        cmdLabel.textContent = '$ ' + label;
        output.textContent   = 'Виконується…';
        output.className     = 'term-output running';

        var fd = new FormData();
        fd.append('cmd', cmd);
        fetch('/system/api/run_command', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                output.className = 'term-output';
                if (d.ok) {
                    output.textContent = d.output || '(немає виводу)';
                    timeEl.textContent = d.time;
                } else {
                    output.textContent = 'Помилка: ' + (d.error || 'unknown');
                }
                output.scrollTop = output.scrollHeight;
            })
            .catch(function (e) {
                output.className = 'term-output';
                output.textContent = 'Мережева помилка: ' + e;
            });
    });
});

clearBtn.addEventListener('click', function () {
    output.textContent = 'Натисніть кнопку команди ліворуч';
    cmdLabel.textContent = '← Виберіть команду';
    timeEl.textContent   = '';
    document.querySelectorAll('.term-btn').forEach(function (b) { b.classList.remove('active'); });
});

}());

// ══════════════════════════════════════════════════════ CRONS TAB
(function () {

function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Крони Papir ──────────────────────────────────────────────────────────────

function loadCrons() {
    fetch('/system/api/get_crons')
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.ok) return;
            renderKnownCrons(d.known_crons);
        });
}

function formatGap(gapMin) {
    if (gapMin === null || gapMin === undefined) return '';
    if (gapMin < 60)   return gapMin + ' хв тому';
    if (gapMin < 1440) return Math.floor(gapMin / 60) + ' год тому';
    return Math.floor(gapMin / 1440) + ' д тому';
}

function renderKnownCrons(crons) {
    var tb = document.querySelector('#cronsTable tbody');
    if (!crons || !crons.length) {
        tb.innerHTML = '<tr><td colspan="4" style="color:#94a3b8">Немає кронів</td></tr>';
        return;
    }
    var html = '';
    crons.forEach(function (c) {
        var schedHtml = c.schedule
            ? '<span class="cron-sched" title="' + esc(c.schedule) + '">' + esc(c.label || c.schedule) + '</span>'
            : '<span style="color:#94a3b8">—</span>';
        var activeHtml = c.active
            ? '<span class="cron-active yes">✓ Активний</span>'
            : '<span class="cron-active no">✗ Не працює</span>';
        var gapStr = c.gap_min !== null ? ' <span style="color:#94a3b8;font-size:11px">(' + formatGap(c.gap_min) + ')</span>' : '';
        var lastRun = c.last_run
            ? '<span style="color:#475569">' + esc(c.last_run) + '</span>' + gapStr
            : '<span style="color:#94a3b8">немає лога</span>';
        var rowStyle = !c.active && c.last_run ? ' style="background:#fff5f5"' : '';
        html += '<tr' + rowStyle + '>'
              + '<td>' + esc(c.name) + '</td>'
              + '<td>' + schedHtml + '</td>'
              + '<td style="text-align:center">' + activeHtml + '</td>'
              + '<td>' + lastRun + '</td>'
              + '</tr>';
    });
    tb.innerHTML = html;
}

document.getElementById('cronRefreshBtn').addEventListener('click', function () {
    loadCrons();
    loadJobs(1);
});

// ── Фонові процеси ───────────────────────────────────────────────────────────

var jobsCurrentPage = 1;
var jobsSearchTimer = null;

var searchEl   = document.getElementById('jobsSearch');
var dateFromEl = document.getElementById('jobsDateFrom');
var dateToEl   = document.getElementById('jobsDateTo');
var dateClearEl= document.getElementById('jobsDateClear');
var totalEl    = document.getElementById('jobsTotal');
var pagEl      = document.getElementById('jobsPagination');

function getJobsParams(page) {
    var params = 'page=' + (page || 1);
    var s = searchEl ? searchEl.value.trim() : '';
    if (s) params += '&search=' + encodeURIComponent(s);
    var status = '';
    document.querySelectorAll('input[name="jobsStatus"]').forEach(function (r) {
        if (r.checked) status = r.value;
    });
    if (status) params += '&status=' + encodeURIComponent(status);
    var df = dateFromEl ? dateFromEl.value : '';
    var dt = dateToEl   ? dateToEl.value   : '';
    if (df) params += '&date_from=' + encodeURIComponent(df);
    if (dt) params += '&date_to='   + encodeURIComponent(dt);
    return params;
}

function loadJobs(page) {
    jobsCurrentPage = page || 1;
    var tb = document.querySelector('#jobsTable tbody');
    tb.innerHTML = '<tr><td colspan="6" style="color:#94a3b8">Завантаження…</td></tr>';
    fetch('/system/api/get_jobs?' + getJobsParams(jobsCurrentPage))
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.ok) {
                tb.innerHTML = '<tr><td colspan="6" style="color:#dc2626">Помилка завантаження</td></tr>';
                return;
            }
            renderJobsTable(d.jobs);
            renderJobsTotal(d.total);
            renderJobsPagination(d.page, d.total_pages);
        });
}

function renderJobsTable(jobs) {
    var tb = document.querySelector('#jobsTable tbody');
    if (!jobs || !jobs.length) {
        tb.innerHTML = '<tr><td colspan="6" style="color:#94a3b8">Немає задач</td></tr>';
        return;
    }
    var html = '';
    jobs.forEach(function (j) {
        html += '<tr>'
              + '<td style="color:#94a3b8">' + esc(j.job_id) + '</td>'
              + '<td>' + esc(j.title) + '</td>'
              + '<td><span class="job-status ' + esc(j.status) + '">' + esc(j.status) + '</span></td>'
              + '<td style="color:#475569;white-space:nowrap">' + esc(j.started_at || '—') + '</td>'
              + '<td style="color:#475569;white-space:nowrap">' + esc(j.finished_at || '—') + '</td>'
              + '<td style="font-family:monospace;font-size:11px;color:#64748b">'
              + esc((j.log_file || '').replace('/var/www/papir/', '').replace('/var/log/papir/', ''))
              + '</td>'
              + '</tr>';
    });
    tb.innerHTML = html;
}

function renderJobsTotal(total) {
    if (totalEl) totalEl.textContent = total + ' записів';
}

function renderJobsPagination(page, totalPages) {
    if (!pagEl) return;
    if (totalPages <= 1) { pagEl.innerHTML = ''; return; }

    var html = '';
    // Попередня
    if (page > 1) {
        html += '<a href="#" data-page="' + (page - 1) + '">‹</a>';
    } else {
        html += '<span style="opacity:.4">‹</span>';
    }

    // Номери сторінок
    var from = Math.max(1, page - 2);
    var to   = Math.min(totalPages, page + 2);
    if (from > 1) { html += '<a href="#" data-page="1">1</a>'; if (from > 2) html += '<span class="dots">…</span>'; }
    for (var i = from; i <= to; i++) {
        if (i === page) {
            html += '<span class="cur">' + i + '</span>';
        } else {
            html += '<a href="#" data-page="' + i + '">' + i + '</a>';
        }
    }
    if (to < totalPages) { if (to < totalPages - 1) html += '<span class="dots">…</span>'; html += '<a href="#" data-page="' + totalPages + '">' + totalPages + '</a>'; }

    // Наступна
    if (page < totalPages) {
        html += '<a href="#" data-page="' + (page + 1) + '">›</a>';
    } else {
        html += '<span style="opacity:.4">›</span>';
    }

    pagEl.innerHTML = html;

    pagEl.querySelectorAll('a[data-page]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            loadJobs(parseInt(a.dataset.page, 10));
        });
    });
}

// Пошук — debounce 400мс
if (searchEl) {
    searchEl.addEventListener('input', function () {
        clearTimeout(jobsSearchTimer);
        jobsSearchTimer = setTimeout(function () { loadJobs(1); }, 400);
    });
    searchEl.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { clearTimeout(jobsSearchTimer); loadJobs(1); }
    });
}

// Фільтр по статусу — миттєво при зміні
document.querySelectorAll('input[name="jobsStatus"]').forEach(function (r) {
    r.addEventListener('change', function () {
        // Оновлюємо active-клас на pills
        document.querySelectorAll('label.filter-pill').forEach(function (lbl) {
            var inp = lbl.querySelector('input[name="jobsStatus"]');
            if (inp) lbl.classList.toggle('active', inp.checked);
        });
        loadJobs(1);
    });
});

// Фільтр по даті
function onDateChange() {
    var hasDates = (dateFromEl && dateFromEl.value) || (dateToEl && dateToEl.value);
    if (dateClearEl) dateClearEl.style.display = hasDates ? '' : 'none';
    loadJobs(1);
}
if (dateFromEl) dateFromEl.addEventListener('change', onDateChange);
if (dateToEl)   dateToEl.addEventListener('change', onDateChange);

if (dateClearEl) {
    dateClearEl.addEventListener('click', function () {
        if (dateFromEl) dateFromEl.value = '';
        if (dateToEl)   dateToEl.value   = '';
        dateClearEl.style.display = 'none';
        loadJobs(1);
    });
}

// Оновити вручну
var refreshJobsBtn = document.getElementById('jobsRefreshBtn');
if (refreshJobsBtn) {
    refreshJobsBtn.addEventListener('click', function () { loadJobs(jobsCurrentPage); });
}

// Load when tab is clicked
document.querySelectorAll('.mtab').forEach(function (btn) {
    if (btn.dataset.tab === 'crons') {
        btn.addEventListener('click', function () {
            loadCrons();
            loadJobs(1);
        });
    }
});

}());

// ══════════════════════════════════════════════════════ LOGS TAB
(function () {

var selectEl   = document.getElementById('logFileSelect');
var linesEl    = document.getElementById('logLinesSelect');
var filterEl   = document.getElementById('logFilter');
var outputEl   = document.getElementById('logOutput');
var metaEl     = document.getElementById('logMeta');
var autoCheck  = document.getElementById('logAutoRefresh');
var autoTimer  = null;
var logFilesInit = false;

function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function loadFiles() {
    if (logFilesInit) return;
    fetch('/system/api/get_log?file=list')
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.ok) return;
            selectEl.innerHTML = '<option value="">— Виберіть файл —</option>';
            for (var key in d.files) {
                if (!d.files.hasOwnProperty(key)) continue;
                var f = d.files[key];
                var sz = f.size > 1048576 ? (f.size/1048576).toFixed(1)+'MB'
                       : f.size > 1024    ? (f.size/1024).toFixed(0)+'KB'
                       : f.size+'B';
                var opt = document.createElement('option');
                opt.value = key;
                opt.textContent = f.label + '  (' + sz + ')';
                selectEl.appendChild(opt);
            }
            logFilesInit = true;
        });
}

function loadLog() {
    var file  = selectEl.value;
    var lines = linesEl.value;
    if (!file) return;

    fetch('/system/api/get_log?file=' + encodeURIComponent(file) + '&lines=' + lines)
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.ok) {
                outputEl.innerHTML = '<span style="color:#f87171">Помилка: ' + esc(d.error) + '</span>';
                return;
            }
            var filterVal = filterEl.value.trim().toLowerCase();
            var content   = d.content || '';
            var lines2    = content.split('\n');
            var html      = '';

            lines2.forEach(function (line) {
                if (filterVal && line.toLowerCase().indexOf(filterVal) === -1) return;
                var cls = '';
                var ll  = line.toLowerCase();
                if (ll.indexOf('[error]') !== -1 || ll.indexOf('fatal') !== -1 || ll.indexOf('crit') !== -1) cls = 'log-line-err';
                else if (ll.indexOf('[warn') !== -1 || ll.indexOf('warning') !== -1) cls = 'log-line-warn';
                else if (filterVal && ll.indexOf(filterVal) !== -1) cls = 'log-line-hi';
                html += '<span' + (cls ? ' class="' + cls + '"' : '') + '>' + esc(line) + '\n</span>';
            });

            outputEl.innerHTML = html || '<span style="color:#94a3b8">(порожньо)</span>';
            outputEl.scrollTop = outputEl.scrollHeight;

            var mtime = d.mtime ? new Date(d.mtime * 1000).toLocaleTimeString('uk') : '';
            metaEl.textContent = d.label + ' · ' + lines2.length + ' рядків · ' + mtime;
        });
}

selectEl.addEventListener('change', loadLog);
linesEl.addEventListener('change', loadLog);
function copyToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(function () {
        var orig = btn.textContent;
        btn.textContent = 'Скопійовано ✓';
        setTimeout(function () { btn.textContent = orig; }, 1800);
    });
}

document.getElementById('logRefreshBtn').addEventListener('click', loadLog);
document.getElementById('logCopyBtn').addEventListener('click', function () {
    var text = outputEl.innerText || outputEl.textContent;
    copyToClipboard(text, this);
});
document.getElementById('logClearBtn').addEventListener('click', function () {
    outputEl.innerHTML = '<span style="color:#475569">Вивід очищено</span>';
    metaEl.textContent = '';
});
filterEl.addEventListener('input', loadLog);

autoCheck.addEventListener('change', function () {
    if (autoTimer) clearInterval(autoTimer);
    if (autoCheck.checked) {
        autoTimer = setInterval(loadLog, 10000);
    }
});

// ── AI Analysis ───────────────────────────────────────────────────────────────
var aiBtn        = document.getElementById('logAiBtn');
var aiPanel      = document.getElementById('aiPanel');
var aiPanelFile  = document.getElementById('aiPanelFile');
var aiThinking   = document.getElementById('aiThinking');
var aiResult     = document.getElementById('aiResult');
var aiPanelClose = document.getElementById('aiPanelClose');
var aiClearFile  = document.getElementById('aiClearFileBtn');

function renderMarkdown(text) {
    // Minimal markdown: ## headers, **bold**, `code`, ```blocks```, - lists
    var html = text
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        // code blocks
        .replace(/```[\w]*\n?([\s\S]*?)```/g, function(_, c) {
            return '<pre><code>' + c.trim() + '</code></pre>';
        })
        // inline code
        .replace(/`([^`]+)`/g, '<code>$1</code>')
        // ## headers
        .replace(/^## (.+)$/gm, '<h2>$1</h2>')
        // **bold**
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        // bullet lists (process contiguous - lines)
        .replace(/((?:^- .+\n?)+)/gm, function(block) {
            var items = block.trim().split('\n').map(function(l) {
                return '<li>' + l.replace(/^- /, '') + '</li>';
            });
            return '<ul>' + items.join('') + '</ul>';
        })
        // paragraphs (double newline)
        .replace(/\n\n+/g, '</p><p>')
        // single newlines (inside paragraphs)
        .replace(/\n/g, '<br>');
    return '<p>' + html + '</p>';
}

aiBtn.addEventListener('click', function () {
    var fileVal = selectEl.value;
    var content = outputEl.innerText || outputEl.textContent;
    if (!fileVal || !content.trim()) {
        alert('Спочатку завантажте лог');
        return;
    }

    // Show panel
    aiPanel.style.display = 'block';
    aiPanelFile.textContent = selectEl.options[selectEl.selectedIndex].textContent;
    aiThinking.style.display = 'flex';
    aiResult.innerHTML = '';
    aiClearFile.style.display = 'none';
    aiBtn.disabled = true;
    aiBtn.classList.add('loading');
    aiBtn.textContent = 'Аналізую…';

    // Scroll to panel
    setTimeout(function () { aiPanel.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 100);

    var fd = new FormData();
    fd.append('log_content', content);
    fd.append('log_label',   selectEl.options[selectEl.selectedIndex].textContent);

    fetch('/system/api/analyze_log', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            aiThinking.style.display = 'none';
            aiBtn.disabled = false;
            aiBtn.classList.remove('loading');
            aiBtn.textContent = 'AI Аналіз';

            if (!d.ok) {
                aiResult.innerHTML = '<p style="color:#dc2626">Помилка: ' + d.error + '</p>';
                return;
            }

            aiResult.innerHTML = renderMarkdown(d.analysis);
            if (d.truncated) {
                aiResult.innerHTML += '<p style="color:#94a3b8;font-size:12px;margin-top:12px">⚠ Лог обрізано до останніх ~12000 символів для аналізу</p>';
            }

            // Show "clear file" only for clearable logs (tmp_ and phpfpm_)
            var key = selectEl.value;
            if (/^(tmp_|phpfpm_)/.test(key)) {
                aiClearFile.style.display = '';
                aiClearFile.dataset.fileKey = key;
            }
        })
        .catch(function (e) {
            aiThinking.style.display = 'none';
            aiBtn.disabled = false;
            aiBtn.classList.remove('loading');
            aiBtn.textContent = 'AI Аналіз';
            aiResult.innerHTML = '<p style="color:#dc2626">Мережева помилка: ' + e + '</p>';
        });
});

aiPanelClose.addEventListener('click', function () {
    aiPanel.style.display = 'none';
});

document.getElementById('aiCopyBtn').addEventListener('click', function () {
    var text = aiResult.innerText || aiResult.textContent;
    copyToClipboard(text, this);
});

aiClearFile.addEventListener('click', function () {
    var key = aiClearFile.dataset.fileKey;
    if (!key || !confirm('Очистити файл лога ' + selectEl.options[selectEl.selectedIndex].text + '?\nДані буде втрачено безповоротно.')) return;

    var fd = new FormData();
    fd.append('file', key);
    fetch('/system/api/clear_log', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.ok) {
                aiPanel.style.display = 'none';
                outputEl.innerHTML = '<span style="color:#22c55e">Файл очищено ✓</span>';
                metaEl.textContent = '';
                // Reload the log list to update file sizes
                logFilesInit = false;
                loadFiles();
            } else {
                alert('Помилка: ' + d.error);
            }
        });
});

// Load file list + log when tab is clicked
document.querySelectorAll('.mtab').forEach(function (btn) {
    if (btn.dataset.tab === 'logs') {
        btn.addEventListener('click', function () {
            loadFiles();
        });
    }
});

}());
</script>

<?php require_once __DIR__ . '/../../../modules/shared/layout_end.php'; ?>