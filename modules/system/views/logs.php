<?php
$extraCss = '<link rel="stylesheet" href="/modules/shared/ui.css?v=' . filemtime(__DIR__ . '/../../shared/ui.css') . '">';
require_once __DIR__ . '/../../shared/layout.php';
?>
<style>
.logs-wrap {
    display: grid;
    grid-template-columns: 240px 1fr;
    gap: 16px;
    height: calc(100vh - 112px);
    overflow: hidden;
}

/* ── Sidebar ── */
.logs-sidebar {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-height: 0;
}
.logs-sidebar-head {
    padding: 12px 14px 8px;
    border-bottom: 1px solid #f1f5f9;
    flex-shrink: 0;
}
.logs-sidebar-head h2 {
    margin: 0 0 8px;
    font-size: 13px;
    font-weight: 700;
    color: #1e293b;
}
.logs-search {
    width: 100%;
    height: 30px;
    padding: 0 10px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 12px;
    box-sizing: border-box;
}
.logs-search:focus { outline: none; border-color: #0d9488; }

.logs-list {
    overflow-y: auto;
    flex: 1;
    padding: 6px 0;
}
.logs-group-label {
    padding: 8px 14px 3px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: #94a3b8;
}
.logs-file-item {
    padding: 7px 14px;
    cursor: pointer;
    border-left: 3px solid transparent;
    transition: background .12s;
}
.logs-file-item:hover  { background: #f8fafc; }
.logs-file-item.active { background: #f0fdfa; border-left-color: #0d9488; }
.logs-file-name {
    font-size: 12px;
    font-weight: 500;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.logs-file-meta {
    font-size: 10px;
    color: #94a3b8;
    margin-top: 1px;
}
.logs-file-item.hidden { display: none; }

/* ── Main viewer ── */
.logs-main {
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: 0;
    overflow: hidden;
}
.logs-toolbar {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}
.logs-toolbar h2 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
    flex-shrink: 0;
    max-width: 300px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.logs-toolbar select {
    height: 32px;
    padding: 0 8px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 12px;
    background: #fff;
}
.logs-filter {
    height: 32px;
    padding: 0 10px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 12px;
    flex: 1;
    min-width: 120px;
}
.logs-filter:focus { outline: none; border-color: #0d9488; }
.logs-meta {
    font-size: 11px;
    color: #94a3b8;
    white-space: nowrap;
    margin-left: auto;
}
.logs-output {
    flex: 1;
    background: #0f172a;
    color: #e2e8f0;
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 12px;
    line-height: 1.6;
    padding: 14px 16px;
    border-radius: 8px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-break: break-all;
    min-height: 200px;
}
.logs-output .log-line-err  { color: #fca5a5; }
.logs-output .log-line-warn { color: #fcd34d; }
.logs-output .log-line-ok   { color: #86efac; }
.logs-output .log-line-hi   { background: #1e3a5f; }

.logs-empty {
    color: #475569;
    font-style: italic;
}

/* ── AI панель ── */
.ai-panel {
    background: #faf5ff;
    border: 1px solid #e9d5ff;
    border-radius: 8px;
    margin-top: 12px;
    overflow: hidden;
    flex-shrink: 0;
    max-height: 340px;
    display: flex;
    flex-direction: column;
}
.ai-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    border-bottom: 1px solid #e9d5ff;
    background: #f3e8ff;
    flex-shrink: 0;
}
.ai-panel-title { font-size: 13px; font-weight: 600; color: #6b21a8; }
.ai-panel-file  { font-size: 11px; color: #9333ea; font-family: monospace; }
.ai-panel-body  { padding: 14px; overflow-y: auto; flex: 1; font-size: 13px; color: #1e293b; line-height: 1.6; }
.ai-close-btn   { background: none; border: none; cursor: pointer; color: #9333ea; font-size: 16px; padding: 0 4px; }
.ai-thinking    { display: flex; align-items: center; }
.ai-dot { width: 7px; height: 7px; background: #9333ea; border-radius: 50%; margin: 0 2px;
          animation: aiPulse 1.2s infinite ease-in-out; }
.ai-dot:nth-child(2) { animation-delay: .2s; }
.ai-dot:nth-child(3) { animation-delay: .4s; }
@keyframes aiPulse { 0%,80%,100% { opacity: .3; transform: scale(.8); } 40% { opacity: 1; transform: scale(1); } }
</style>

<div id="toast" class="toast"></div>
<div class="page-wrap-lg" style="padding-top:12px">
<div class="logs-wrap">

    <!-- ══ Sidebar ══ -->
    <div class="logs-sidebar">
        <div class="logs-sidebar-head">
            <h2>Файли логів</h2>
            <input type="text" class="logs-search" id="logsSearch" placeholder="Пошук…" autocomplete="off">
        </div>
        <div class="logs-list" id="logsList">
            <div style="padding:20px;color:#94a3b8;font-size:12px">Завантаження…</div>
        </div>
    </div>

    <!-- ══ Main viewer ══ -->
    <div class="logs-main">
        <div class="logs-toolbar">
            <h2 id="logTitle">— Виберіть файл —</h2>
            <select id="linesSelect">
                <option value="100">100 рядків</option>
                <option value="200" selected>200 рядків</option>
                <option value="500">500 рядків</option>
                <option value="1000">1000 рядків</option>
                <option value="2000">2000 рядків</option>
                <option value="5000">5000 рядків</option>
            </select>
            <button class="btn btn-ghost btn-sm" id="refreshBtn" title="Оновити">
                <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M2 8a6 6 0 1 0 1.2-3.6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M2 3.5V8h4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <label style="display:flex;align-items:center;gap:5px;font-size:12px;color:#475569;cursor:pointer;flex-shrink:0">
                <input type="checkbox" id="autoRefresh"> авто 10с
            </label>
            <input type="text" class="logs-filter" id="logsFilter" placeholder="Фільтр рядків…">
            <button class="btn btn-ghost btn-sm" id="copyBtn" title="Копіювати">Копіювати</button>
            <button class="btn btn-danger btn-sm" id="clearBtn" title="Очистити файл лога" style="display:none">Очистити</button>
            <button class="btn btn-sm" id="aiBtn" style="background:#7c3aed;color:#fff;display:none" title="AI аналіз">AI</button>
            <span class="logs-meta" id="logsMeta"></span>
        </div>

        <div class="logs-output" id="logsOutput">
            <span class="logs-empty">Виберіть файл зліва</span>
        </div>

        <div class="ai-panel" id="aiPanel" style="display:none">
            <div class="ai-panel-head">
                <div style="display:flex;align-items:center;gap:8px">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="#7c3aed" stroke-width="1.7"/><path d="M8 12s1.5-3 4-3 4 3 4 3-1.5 3-4 3-4-3-4-3z" stroke="#7c3aed" stroke-width="1.5"/><circle cx="12" cy="12" r="1.5" fill="#7c3aed"/></svg>
                    <span class="ai-panel-title">AI Аналіз</span>
                    <span class="ai-panel-file" id="aiPanelFile"></span>
                </div>
                <div style="display:flex;align-items:center;gap:8px">
                    <button class="btn btn-ghost btn-sm" id="aiCopyBtn">Копіювати</button>
                    <button class="ai-close-btn" id="aiClose">&#x2715;</button>
                </div>
            </div>
            <div class="ai-panel-body" id="aiPanelBody">
                <div class="ai-thinking" id="aiThinking" style="display:none">
                    <span class="ai-dot"></span><span class="ai-dot"></span><span class="ai-dot"></span>
                    <span style="margin-left:8px;color:#7c3aed;font-size:13px">Аналізую лог…</span>
                </div>
                <div id="aiResult"></div>
            </div>
        </div>
    </div>

</div>
</div>

<script>
(function () {

var currentFile  = null;
var currentLabel = '';
var rawContent   = '';
var autoTimer    = null;

var groups = {
    papir:  'Крони Papir',
    system: 'Система',
    tmp:    'Фонові скрипти',
};

// ── Sidebar ───────────────────────────────────────────────────────────────────

function loadFileList() {
    fetch('/system/api/get_log?file=list')
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.ok) return;
            renderSidebar(d.files);

            // Відкрити файл з URL-хешу
            var hash = window.location.hash.replace('#', '');
            if (hash && d.files[hash]) {
                selectFile(hash, d.files[hash].label);
            }
        });
}

function renderSidebar(files) {
    var grouped = { papir: [], system: [], tmp: [] };
    Object.keys(files).forEach(function (key) {
        var f = files[key];
        var g = f.group || 'tmp';
        if (!grouped[g]) grouped[g] = [];
        grouped[g].push({ key: key, label: f.label, size: f.size, mtime: f.mtime });
    });

    var html = '';
    Object.keys(groups).forEach(function (gKey) {
        var items = grouped[gKey] || [];
        if (!items.length) return;
        // Сортувати за mtime DESC
        items.sort(function (a, b) { return b.mtime - a.mtime; });
        html += '<div class="logs-group-label">' + esc(groups[gKey]) + '</div>';
        items.forEach(function (item) {
            var sizeStr = item.size > 1048576
                ? (item.size / 1048576).toFixed(1) + ' MB'
                : item.size > 1024
                    ? Math.round(item.size / 1024) + ' KB'
                    : item.size + ' B';
            var date = item.mtime ? new Date(item.mtime * 1000) : null;
            var dateStr = date ? formatDate(date) : '—';
            html += '<div class="logs-file-item" data-key="' + esc(item.key) + '" data-label="' + esc(item.label) + '">'
                  + '<div class="logs-file-name">' + esc(item.label) + '</div>'
                  + '<div class="logs-file-meta">' + sizeStr + ' &nbsp;·&nbsp; ' + dateStr + '</div>'
                  + '</div>';
        });
    });

    document.getElementById('logsList').innerHTML = html || '<div style="padding:16px;color:#94a3b8;font-size:12px">Немає файлів</div>';

    document.querySelectorAll('.logs-file-item').forEach(function (el) {
        el.addEventListener('click', function () {
            selectFile(el.dataset.key, el.dataset.label);
        });
    });
}

function selectFile(key, label) {
    currentFile  = key;
    currentLabel = label;
    window.location.hash = key;

    document.querySelectorAll('.logs-file-item').forEach(function (el) {
        el.classList.toggle('active', el.dataset.key === key);
    });

    document.getElementById('logTitle').textContent = label;
    document.getElementById('clearBtn').style.display = '';
    document.getElementById('aiBtn').style.display = '';
    loadLog();
}

// ── Load log ──────────────────────────────────────────────────────────────────

function loadLog() {
    if (!currentFile) return;
    var lines = document.getElementById('linesSelect').value;
    document.getElementById('logsMeta').textContent = 'Завантаження…';

    fetch('/system/api/get_log?file=' + encodeURIComponent(currentFile) + '&lines=' + lines)
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.ok) {
                document.getElementById('logsOutput').innerHTML = '<span style="color:#fca5a5">' + esc(d.error || 'Помилка') + '</span>';
                return;
            }
            rawContent = d.content || '';
            var sizeStr = d.size > 1048576
                ? (d.size / 1048576).toFixed(1) + ' MB'
                : Math.round(d.size / 1024) + ' KB';
            var mtime = d.mtime ? new Date(d.mtime * 1000) : null;
            document.getElementById('logsMeta').textContent = sizeStr + (mtime ? '  ·  ' + formatDate(mtime) : '');
            applyFilter();
        });
}

// ── Filter ────────────────────────────────────────────────────────────────────

function applyFilter() {
    var q = document.getElementById('logsFilter').value.toLowerCase().trim();
    var lines = rawContent.split('\n');
    if (q) {
        lines = lines.filter(function (l) { return l.toLowerCase().indexOf(q) !== -1; });
    }
    if (!lines.length || (lines.length === 1 && lines[0] === '')) {
        document.getElementById('logsOutput').innerHTML = '<span class="logs-empty">Порожній файл</span>';
        return;
    }

    var html = lines.map(function (line) {
        var cls = '';
        var ll = line.toLowerCase();
        if (/error|fatal|crit|alert|emerg/i.test(line))   cls = 'log-line-err';
        else if (/warn|notice/i.test(line))                cls = 'log-line-warn';
        else if (/\[ok\]|success|done|✓/i.test(line))     cls = 'log-line-ok';

        var escaped = esc(line);
        if (q) {
            var idx = ll.indexOf(q);
            if (idx !== -1) {
                escaped = esc(line.substring(0, idx))
                    + '<mark style="background:#1e3a5f;color:#93c5fd">' + esc(line.substring(idx, idx + q.length)) + '</mark>'
                    + esc(line.substring(idx + q.length));
                cls += ' log-line-hi';
            }
        }
        return cls ? '<span class="' + cls + '">' + escaped + '</span>' : escaped;
    }).join('\n');

    document.getElementById('logsOutput').innerHTML = html;
    // Scroll to bottom
    var out = document.getElementById('logsOutput');
    out.scrollTop = out.scrollHeight;
}

// ── Sidebar search ────────────────────────────────────────────────────────────

document.getElementById('logsSearch').addEventListener('input', function () {
    var q = this.value.toLowerCase().trim();
    document.querySelectorAll('.logs-file-item').forEach(function (el) {
        var name = el.dataset.label.toLowerCase();
        el.classList.toggle('hidden', q !== '' && name.indexOf(q) === -1);
    });
});

// ── Controls ──────────────────────────────────────────────────────────────────

document.getElementById('refreshBtn').addEventListener('click', loadLog);
document.getElementById('linesSelect').addEventListener('change', loadLog);
document.getElementById('logsFilter').addEventListener('input', applyFilter);

document.getElementById('copyBtn').addEventListener('click', function () {
    if (!rawContent) return;
    navigator.clipboard.writeText(rawContent).then(function () {
        showToast('Скопійовано');
    });
});

document.getElementById('clearBtn').addEventListener('click', function () {
    if (!currentFile) return;
    if (!confirm('Очистити файл лога «' + currentLabel + '»?')) return;
    var fd = new FormData();
    fd.append('file', currentFile);
    fetch('/system/api/clear_log', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.ok) { rawContent = ''; applyFilter(); showToast('Лог очищено'); }
            else showToast(d.error || 'Помилка', true);
        });
});

// ── Auto-refresh ──────────────────────────────────────────────────────────────

document.getElementById('autoRefresh').addEventListener('change', function () {
    if (autoTimer) { clearInterval(autoTimer); autoTimer = null; }
    if (this.checked) {
        autoTimer = setInterval(loadLog, 10000);
    }
});

// ── AI Analysis ───────────────────────────────────────────────────────────────

document.getElementById('aiBtn').addEventListener('click', function () {
    if (!rawContent) return;
    var panel = document.getElementById('aiPanel');
    panel.style.display = 'flex';
    document.getElementById('aiPanelFile').textContent = currentLabel;
    document.getElementById('aiResult').innerHTML = '';
    document.getElementById('aiThinking').style.display = 'flex';

    var fd = new FormData();
    fd.append('log_content', rawContent);
    fd.append('log_label', currentLabel);

    fetch('/system/api/analyze_log', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            document.getElementById('aiThinking').style.display = 'none';
            document.getElementById('aiResult').innerHTML = d.ok
                ? renderMarkdown(d.analysis)
                : '<span style="color:#dc2626">' + esc(d.error || 'Помилка AI') + '</span>';
        })
        .catch(function () {
            document.getElementById('aiThinking').style.display = 'none';
            document.getElementById('aiResult').innerHTML = '<span style="color:#dc2626">Помилка запиту</span>';
        });
});

document.getElementById('aiClose').addEventListener('click', function () {
    document.getElementById('aiPanel').style.display = 'none';
});

document.getElementById('aiCopyBtn').addEventListener('click', function () {
    var text = document.getElementById('aiResult').innerText;
    if (!text) return;
    navigator.clipboard.writeText(text).then(function () { showToast('Скопійовано'); });
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function showToast(msg, isError) {
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.style.background = isError ? '#dc2626' : '';
    t.classList.add('show');
    setTimeout(function () { t.classList.remove('show'); t.style.background = ''; }, 2200);
}

function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function formatDate(d) {
    var now = new Date();
    var pad = function (n) { return n < 10 ? '0' + n : n; };
    var time = pad(d.getHours()) + ':' + pad(d.getMinutes());
    if (d.toDateString() === now.toDateString()) return 'сьогодні ' + time;
    var yesterday = new Date(now); yesterday.setDate(now.getDate() - 1);
    if (d.toDateString() === yesterday.toDateString()) return 'вчора ' + time;
    return pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + ' ' + time;
}

function renderMarkdown(text) {
    return text
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/^## (.+)$/gm, '<h4 style="margin:10px 0 4px;color:#6b21a8">$1</h4>')
        .replace(/^### (.+)$/gm, '<h5 style="margin:8px 0 3px;color:#7c3aed">$1</h5>')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/`([^`]+)`/g, '<code style="background:#f3e8ff;padding:1px 4px;border-radius:3px;font-family:monospace">$1</code>')
        .replace(/\n/g, '<br>');
}

// ── Init ──────────────────────────────────────────────────────────────────────

loadFileList();

}());
</script>

<?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>
