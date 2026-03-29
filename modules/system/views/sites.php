<?php require_once __DIR__ . '/../../../modules/shared/layout.php'; ?>

<style>
/* ── Sites page layout ──────────────────────────────── */
.sites-wrap     { max-width: 1200px; margin: 0 auto; padding: 20px 24px; }
.sites-tabs     { display: flex; gap: 4px; border-bottom: 2px solid #e5e7eb; margin-bottom: 24px; }
.stab           { padding: 8px 18px; font-size: 13px; font-weight: 500; color: #64748b;
                  border: none; background: none; cursor: pointer; border-radius: 6px 6px 0 0;
                  border-bottom: 2px solid transparent; margin-bottom: -2px; transition: color .15s; }
.stab:hover     { color: #1e293b; background: #f8fafc; }
.stab.active    { color: #0d9488; border-bottom-color: #0d9488; background: #f0fdfa; }

.tab-pane       { display: none; }
.tab-pane.active{ display: block; }

/* ── Stat cards row ─────────────────────────────────── */
.stat-cards     { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.stat-card      { flex: 1; min-width: 140px; background: #fff; border: 1px solid #e5e7eb;
                  border-radius: 10px; padding: 16px 18px; }
.stat-card-label{ font-size: 11px; font-weight: 700; text-transform: uppercase;
                  letter-spacing: .5px; color: #94a3b8; margin-bottom: 8px; }
.stat-card-val  { font-size: 24px; font-weight: 700; color: #0f172a; line-height: 1.1; }
.stat-card-val.loading { color: #cbd5e1; font-size: 18px; }

/* ── Recent orders table ────────────────────────────── */
.section-head   { font-size: 13px; font-weight: 700; color: #475569; text-transform: uppercase;
                  letter-spacing: .5px; margin-bottom: 10px; }

/* ── Log section ────────────────────────────────────── */
.log-toolbar    { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; }
.log-file-select{ height: 32px; padding: 0 10px; border: 1px solid #e2e8f0; border-radius: 6px;
                  font-size: 13px; min-width: 220px; background: #fff; }
.log-lines-select{ height: 32px; padding: 0 8px; border: 1px solid #e2e8f0; border-radius: 6px;
                   font-size: 13px; background: #fff; }
.log-output     { background: #0f172a; border-radius: 10px; padding: 16px;
                  font-family: 'Courier New', monospace; font-size: 11.5px; line-height: 1.55;
                  color: #e2e8f0; white-space: pre-wrap; word-break: break-all;
                  overflow-y: auto; min-height: 420px; max-height: 65vh; }
.log-line-err   { color: #f87171; }
.log-line-warn  { color: #fbbf24; }

/* ── AI кнопка ──────────────────────────────────────── */
.btn-ai         { background: #7c3aed; color: #fff; border-color: #7c3aed; }
.btn-ai:hover   { background: #6d28d9; border-color: #6d28d9; }
.btn-ai:disabled{ background: #a78bfa; border-color: #a78bfa; cursor: default; }
.btn-ai.loading { background: #a78bfa; border-color: #a78bfa; }

/* ── AI панель ──────────────────────────────────────── */
.ai-panel       { background: #fff; border: 1px solid #ddd6fe; border-radius: 10px;
                  margin-top: 12px; overflow: hidden; }
.ai-panel-head  { display: flex; align-items: center; justify-content: space-between;
                  padding: 12px 16px; background: #faf5ff; border-bottom: 1px solid #ddd6fe; gap: 10px; }
.ai-panel-title { font-weight: 700; font-size: 13px; color: #6d28d9; }
.ai-panel-file  { font-size: 12px; color: #a78bfa; }
.ai-close-btn   { background: none; border: none; color: #a78bfa; cursor: pointer;
                  font-size: 16px; padding: 2px 6px; border-radius: 4px; }
.ai-close-btn:hover { color: #7c3aed; background: #ede9fe; }
.ai-panel-body  { padding: 20px; }

.ai-thinking    { display: flex; align-items: center; padding: 12px 0; }
.ai-dot         { width: 8px; height: 8px; border-radius: 50%; background: #7c3aed;
                  margin: 0 3px; animation: aiPulse 1.2s ease-in-out infinite; }
.ai-dot:nth-child(2) { animation-delay: .2s; }
.ai-dot:nth-child(3) { animation-delay: .4s; }
@keyframes aiPulse { 0%,80%,100% { opacity:.2; transform:scale(.8); } 40% { opacity:1; transform:scale(1); } }

.ai-result      { font-size: 14px; line-height: 1.7; color: #1e293b; }
.ai-result h2   { font-size: 14px; font-weight: 700; color: #6d28d9; margin: 16px 0 6px;
                  padding-bottom: 4px; border-bottom: 1px solid #ede9fe; }
.ai-result h2:first-child { margin-top: 0; }
.ai-result p    { margin: 0 0 10px; }
.ai-result ul   { margin: 0 0 10px; padding-left: 20px; }
.ai-result li   { margin-bottom: 4px; }
.ai-result code { background: #f1f5f9; padding: 1px 5px; border-radius: 4px;
                  font-family: monospace; font-size: 12px; color: #0f172a; }
.ai-result pre  { background: #0f172a; color: #e2e8f0; padding: 12px 14px; border-radius: 8px;
                  overflow-x: auto; font-family: monospace; font-size: 12px; line-height: 1.5; margin: 8px 0 12px; }
.ai-result pre code { background: none; padding: 0; color: inherit; font-size: inherit; }
.ai-result strong { font-weight: 600; }

/* ── mff notice ─────────────────────────────────────── */
.mff-notice     { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 10px;
                  padding: 20px 24px; color: #0369a1; font-size: 14px; line-height: 1.6; }
.mff-notice strong { font-weight: 700; }
</style>

<div class="sites-wrap">

    <!-- Tabs -->
    <div class="sites-tabs">
        <button class="stab active" data-tab="off">off — Офіс Торг</button>
        <button class="stab" data-tab="mff">mff — Меню Фолдер</button>
    </div>

    <!-- ═══ TAB: off ════════════════════════════════════════════════ -->
    <div class="tab-pane active" id="tab-off">

        <!-- Stat cards -->
        <div class="stat-cards">
            <div class="stat-card">
                <div class="stat-card-label">Замовлень сьогодні</div>
                <div class="stat-card-val loading" id="off-orders-today">…</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-label">Замовлень за тиждень</div>
                <div class="stat-card-val loading" id="off-orders-week">…</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-label">Замовлень за місяць</div>
                <div class="stat-card-val loading" id="off-orders-month">…</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-label">Виручка тиждень</div>
                <div class="stat-card-val loading" id="off-rev-week">…</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-label">Виручка місяць</div>
                <div class="stat-card-val loading" id="off-rev-month">…</div>
            </div>
        </div>

        <!-- Recent orders -->
        <div class="mon-card" style="margin-bottom:20px">
            <div class="section-head" style="margin-bottom:14px">Останні замовлення</div>
            <table class="crm-table" id="off-recent-orders" style="font-size:13px">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Покупець</th>
                        <th style="text-align:right">Сума</th>
                        <th>Дата</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody><tr><td colspan="5" style="color:#94a3b8">Завантаження…</td></tr></tbody>
            </table>
        </div>

        <!-- Log section -->
        <div class="mon-card">
            <div class="section-head" style="margin-bottom:14px">Логи сайту</div>

            <div class="log-toolbar">
                <select id="offLogFileSelect" class="log-file-select">
                    <option value="">— Виберіть файл —</option>
                </select>
                <select id="offLogLinesSelect" class="log-lines-select">
                    <option value="100">100 рядків</option>
                    <option value="200" selected>200 рядків</option>
                    <option value="500">500 рядків</option>
                    <option value="1000">1000 рядків</option>
                </select>
                <button class="btn btn-ghost btn-sm" id="offLogRefreshBtn">Оновити</button>
                <button class="btn btn-ghost btn-sm" id="offLogCopyBtn">Копіювати</button>
                <button class="btn btn-ghost btn-sm" id="offLogClearBtn">&#x2715; Очистити</button>
                <button class="btn btn-ai btn-sm" id="offLogAiBtn">AI Аналіз</button>
                <span style="margin-left:auto;font-size:12px;color:#94a3b8" id="offLogMeta"></span>
            </div>

            <div class="log-output" id="offLogOutput">
                <span style="color:#475569">Виберіть файл лога вище</span>
            </div>

            <!-- AI панель -->
            <div class="ai-panel" id="offAiPanel" style="display:none">
                <div class="ai-panel-head">
                    <div style="display:flex;align-items:center;gap:8px">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="#7c3aed" stroke-width="1.7"/><path d="M8 12s1.5-3 4-3 4 3 4 3-1.5 3-4 3-4-3-4-3z" stroke="#7c3aed" stroke-width="1.5"/><circle cx="12" cy="12" r="1.5" fill="#7c3aed"/></svg>
                        <span class="ai-panel-title">AI Аналіз</span>
                        <span class="ai-panel-file" id="offAiPanelFile"></span>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px">
                        <button class="btn btn-ghost btn-sm" id="offAiCopyBtn">Копіювати</button>
                        <button class="ai-close-btn" id="offAiPanelClose">&#x2715;</button>
                    </div>
                </div>
                <div class="ai-panel-body" id="offAiPanelBody">
                    <div class="ai-thinking" id="offAiThinking" style="display:none">
                        <span class="ai-dot"></span><span class="ai-dot"></span><span class="ai-dot"></span>
                        <span style="margin-left:8px;color:#7c3aed;font-size:13px">Аналізую лог…</span>
                    </div>
                    <div class="ai-result" id="offAiResult"></div>
                </div>
            </div>
        </div>

    </div>

    <!-- ═══ TAB: mff ════════════════════════════════════════════════ -->
    <div class="tab-pane" id="tab-mff">

        <!-- Stat cards -->
        <div class="stat-cards">
            <div class="stat-card">
                <div class="stat-card-label">Замовлень сьогодні</div>
                <div class="stat-card-val loading" id="mff-orders-today">…</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-label">Замовлень за тиждень</div>
                <div class="stat-card-val loading" id="mff-orders-week">…</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-label">Замовлень за місяць</div>
                <div class="stat-card-val loading" id="mff-orders-month">…</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-label">Виручка тиждень</div>
                <div class="stat-card-val loading" id="mff-rev-week">…</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-label">Виручка місяць</div>
                <div class="stat-card-val loading" id="mff-rev-month">…</div>
            </div>
        </div>

        <!-- Recent orders -->
        <div class="mon-card" style="margin-bottom:20px">
            <div class="section-head" style="margin-bottom:14px">Останні замовлення</div>
            <table class="crm-table" id="mff-recent-orders" style="font-size:13px">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Покупець</th>
                        <th style="text-align:right">Сума</th>
                        <th>Дата</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody><tr><td colspan="5" style="color:#94a3b8">Завантаження…</td></tr></tbody>
            </table>
        </div>

        <!-- mff log notice -->
        <div class="mff-notice">
            <strong>Файли логів недоступні</strong> — сервер mff окремий.<br>
            Для перегляду логів потрібно встановити monitor-агент на сервері mff.
        </div>

    </div>

</div><!-- /sites-wrap -->

<script>
(function () {

// ── Tab switching ─────────────────────────────────────────────────────────────
document.querySelectorAll('.stab').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.stab').forEach(function (b) { b.classList.remove('active'); });
        document.querySelectorAll('.tab-pane').forEach(function (p) { p.classList.remove('active'); });
        btn.classList.add('active');
        var pane = document.getElementById('tab-' + btn.dataset.tab);
        if (pane) pane.classList.add('active');
    });
});

// ── Helpers ───────────────────────────────────────────────────────────────────
function esc(s) {
    return String(s == null ? '' : s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function fmtMoney(val) {
    var n = Math.round(parseFloat(val) || 0);
    return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0') + '\u00a0\u20b4';
}

function orderStatusLabel(id) {
    var map = {
        '1': 'В очікуванні',
        '2': 'Оброблено',
        '3': 'Відвантажено',
        '5': 'Виконано',
        '7': 'Скасовано',
        '14': 'В обробці',
        '15': 'Підтверджено'
    };
    return map[String(id)] ? map[String(id)] : 'Статус ' + id;
}

function renderStatCards(prefix, stats) {
    document.getElementById(prefix + '-orders-today').textContent  = stats.orders_today;
    document.getElementById(prefix + '-orders-week').textContent   = stats.orders_week;
    document.getElementById(prefix + '-orders-month').textContent  = stats.orders_month;
    document.getElementById(prefix + '-rev-week').textContent      = fmtMoney(stats.revenue_week);
    document.getElementById(prefix + '-rev-month').textContent     = fmtMoney(stats.revenue_month);

    // Remove loading style
    ['orders-today','orders-week','orders-month','rev-week','rev-month'].forEach(function (s) {
        var el = document.getElementById(prefix + '-' + s);
        if (el) el.classList.remove('loading');
    });
}

function renderRecentOrders(tableId, orders) {
    var tb = document.querySelector('#' + tableId + ' tbody');
    if (!orders || orders.length === 0) {
        tb.innerHTML = '<tr><td colspan="5" style="color:#94a3b8">Немає замовлень</td></tr>';
        return;
    }
    var html = '';
    orders.forEach(function (o) {
        var name = esc((o.firstname || '') + ' ' + (o.lastname || '')).trim();
        var date = esc((o.date_added || '').replace(' ', ' '));
        html += '<tr>'
             + '<td style="color:#94a3b8">' + esc(o.order_id) + '</td>'
             + '<td>' + (name || '<span style="color:#94a3b8">—</span>') + '</td>'
             + '<td style="text-align:right;font-weight:600">' + fmtMoney(o.total) + '</td>'
             + '<td style="color:#475569;white-space:nowrap">' + date + '</td>'
             + '<td><span class="badge badge-blue" style="font-size:11px">' + esc(orderStatusLabel(o.order_status_id)) + '</span></td>'
             + '</tr>';
    });
    tb.innerHTML = html;
}

// ── Load stats ────────────────────────────────────────────────────────────────
function loadStats() {
    fetch('/system/api/get_site_stats')
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.ok) return;
            renderStatCards('off', d.off);
            renderRecentOrders('off-recent-orders', d.off.recent_orders);
            renderStatCards('mff', d.mff);
            renderRecentOrders('mff-recent-orders', d.mff.recent_orders);

            // Update log file selector from log sizes returned in stats
            updateOffLogSelector(d.off.logs || []);
        })
        .catch(function (e) {
            console.error('get_site_stats error', e);
        });
}

// ── Off log file selector init ────────────────────────────────────────────────
function updateOffLogSelector(logs) {
    // Pre-fill from stats logs
    var sel = document.getElementById('offLogFileSelect');
    // Also do a full list fetch from API
    fetch('/system/api/get_site_log?file=list')
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.ok) return;
            sel.innerHTML = '<option value="">— Виберіть файл —</option>';
            for (var key in d.files) {
                if (!d.files.hasOwnProperty(key)) continue;
                var f = d.files[key];
                var sz = f.size > 1048576 ? (f.size/1048576).toFixed(1)+'MB'
                       : f.size > 1024    ? (f.size/1024).toFixed(0)+'KB'
                       : f.size+'B';
                var opt = document.createElement('option');
                opt.value = key;
                opt.textContent = f.label + '  (' + sz + ')';
                sel.appendChild(opt);
            }
        });
}

// ── Copy helper ───────────────────────────────────────────────────────────────
function copyToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(function () {
        var orig = btn.textContent;
        btn.textContent = 'Скопійовано ✓';
        setTimeout(function () { btn.textContent = orig; }, 1800);
    });
}

// ── Markdown renderer ─────────────────────────────────────────────────────────
function renderMarkdown(text) {
    var html = text
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/```[\w]*\n?([\s\S]*?)```/g, function(_, c) {
            return '<pre><code>' + c.trim() + '</code></pre>';
        })
        .replace(/`([^`]+)`/g, '<code>$1</code>')
        .replace(/^## (.+)$/gm, '<h2>$1</h2>')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/((?:^- .+\n?)+)/gm, function(block) {
            var items = block.trim().split('\n').map(function(l) {
                return '<li>' + l.replace(/^- /, '') + '</li>';
            });
            return '<ul>' + items.join('') + '</ul>';
        })
        .replace(/\n\n+/g, '</p><p>')
        .replace(/\n/g, '<br>');
    return '<p>' + html + '</p>';
}

// ── Log loading ───────────────────────────────────────────────────────────────
var offLogOutput = document.getElementById('offLogOutput');
var offLogMeta   = document.getElementById('offLogMeta');
var offLogSel    = document.getElementById('offLogFileSelect');
var offLinesSel  = document.getElementById('offLogLinesSelect');

function loadOffLog() {
    var file  = offLogSel.value;
    var lines = offLinesSel.value;
    if (!file) return;

    offLogOutput.innerHTML = '<span style="color:#94a3b8">Завантаження…</span>';

    fetch('/system/api/get_site_log?file=' + encodeURIComponent(file) + '&lines=' + lines)
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.ok) {
                offLogOutput.innerHTML = '<span style="color:#f87171">Помилка: ' + esc(d.error) + '</span>';
                return;
            }
            var content = d.content || '';
            var logLines = content.split('\n');
            var html = '';
            logLines.forEach(function (line) {
                var cls = '';
                var ll  = line.toLowerCase();
                if (ll.indexOf('[error]') !== -1 || ll.indexOf('fatal') !== -1 || ll.indexOf('crit') !== -1) cls = 'log-line-err';
                else if (ll.indexOf('[warn') !== -1 || ll.indexOf('warning') !== -1) cls = 'log-line-warn';
                html += '<span' + (cls ? ' class="' + cls + '"' : '') + '>' + esc(line) + '\n</span>';
            });
            offLogOutput.innerHTML = html || '<span style="color:#94a3b8">(порожньо)</span>';
            offLogOutput.scrollTop = offLogOutput.scrollHeight;
            var mtime = d.mtime ? new Date(d.mtime * 1000).toLocaleTimeString('uk') : '';
            offLogMeta.textContent = d.label + ' · ' + logLines.length + ' рядків · ' + mtime;
        });
}

offLogSel.addEventListener('change', loadOffLog);
offLinesSel.addEventListener('change', loadOffLog);
document.getElementById('offLogRefreshBtn').addEventListener('click', loadOffLog);

document.getElementById('offLogCopyBtn').addEventListener('click', function () {
    copyToClipboard(offLogOutput.innerText || offLogOutput.textContent, this);
});

document.getElementById('offLogClearBtn').addEventListener('click', function () {
    offLogOutput.innerHTML = '<span style="color:#475569">Вивід очищено</span>';
    offLogMeta.textContent = '';
});

// ── Off AI analysis ───────────────────────────────────────────────────────────
var offAiBtn    = document.getElementById('offLogAiBtn');
var offAiPanel  = document.getElementById('offAiPanel');
var offAiFile   = document.getElementById('offAiPanelFile');
var offAiThink  = document.getElementById('offAiThinking');
var offAiResult = document.getElementById('offAiResult');

offAiBtn.addEventListener('click', function () {
    var fileVal = offLogSel.value;
    var content = offLogOutput.innerText || offLogOutput.textContent;
    if (!fileVal || !content.trim() || content.indexOf('Виберіть файл') !== -1) {
        alert('Спочатку завантажте лог');
        return;
    }

    offAiPanel.style.display = 'block';
    offAiFile.textContent = offLogSel.options[offLogSel.selectedIndex].textContent;
    offAiThink.style.display = 'flex';
    offAiResult.innerHTML = '';
    offAiBtn.disabled = true;
    offAiBtn.classList.add('loading');
    offAiBtn.textContent = 'Аналізую…';

    setTimeout(function () { offAiPanel.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 100);

    var fd = new FormData();
    fd.append('log_content', content);
    fd.append('log_label',   offLogSel.options[offLogSel.selectedIndex].textContent);

    fetch('/system/api/analyze_log', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            offAiThink.style.display = 'none';
            offAiBtn.disabled = false;
            offAiBtn.classList.remove('loading');
            offAiBtn.textContent = 'AI Аналіз';

            if (!d.ok) {
                offAiResult.innerHTML = '<p style="color:#dc2626">Помилка: ' + esc(d.error || '') + '</p>';
                return;
            }
            offAiResult.innerHTML = renderMarkdown(d.analysis);
            if (d.truncated) {
                offAiResult.innerHTML += '<p style="color:#94a3b8;font-size:12px;margin-top:12px">⚠ Лог обрізано до останніх ~12000 символів для аналізу</p>';
            }
        })
        .catch(function (e) {
            offAiThink.style.display = 'none';
            offAiBtn.disabled = false;
            offAiBtn.classList.remove('loading');
            offAiBtn.textContent = 'AI Аналіз';
            offAiResult.innerHTML = '<p style="color:#dc2626">Мережева помилка: ' + esc(String(e)) + '</p>';
        });
});

document.getElementById('offAiPanelClose').addEventListener('click', function () {
    offAiPanel.style.display = 'none';
});

document.getElementById('offAiCopyBtn').addEventListener('click', function () {
    copyToClipboard(offAiResult.innerText || offAiResult.textContent, this);
});

// ── Init ──────────────────────────────────────────────────────────────────────
loadStats();

}());
</script>

<?php require_once __DIR__ . '/../../../modules/shared/layout_end.php'; ?>
