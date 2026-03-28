<?php $title = 'Фонові процеси'; $activeNav = 'system'; $subNav = 'jobs'; require_once __DIR__ . '/../../../modules/shared/layout.php'; ?>

<style>
.jobs-wrap {
    max-width: 1100px;
    margin: 0 auto;
    padding: 24px;
}
.jobs-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.jobs-title { font-size: 22px; font-weight: 700; margin: 0; }
.job-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    margin-bottom: 12px;
    overflow: hidden;
}
.job-card-head {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    cursor: pointer;
    user-select: none;
}
.job-card-head:hover { background: #f9fafb; }
.job-status {
    width: 10px; height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}
.job-status.running { background: #f59e0b; animation: pulse 1.2s infinite; }
.job-status.done    { background: #16a34a; }
.job-status.failed  { background: #dc2626; }
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.4; }
}
.job-title  { font-weight: 600; font-size: 14px; flex: 1; }
.job-meta   { font-size: 12px; color: var(--text-muted); white-space: nowrap; }
.job-badge  { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 20px; }
.job-badge.running { background: #fef3c7; color: #b45309; }
.job-badge.done    { background: #dcfce7; color: #15803d; }
.job-badge.failed  { background: #fee2e2; color: #b91c1c; }
.job-counts { font-size: 12px; color: var(--text-muted); }
.job-counts .ok-c  { color: #16a34a; font-weight: 600; }
.job-counts .err-c { color: #dc2626; font-weight: 600; }

.job-body {
    border-top: 1px solid var(--border);
    padding: 12px 16px;
    display: none;
}
.job-body.open { display: block; }
.job-log {
    font-family: monospace;
    font-size: 12px;
    line-height: 1.5;
    background: #0f172a;
    color: #e2e8f0;
    border-radius: 6px;
    padding: 12px 14px;
    max-height: 400px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-break: break-all;
}
.job-log .log-ok  { color: #4ade80; }
.job-log .log-err { color: #f87171; }
.job-log .log-hd  { color: #94a3b8; }

.job-script {
    font-size: 11px;
    color: var(--text-muted);
    font-family: monospace;
    margin-bottom: 8px;
}
.job-footer {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
}
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted);
}
.empty-state svg { opacity: 0.3; margin-bottom: 12px; }

.refresh-btn { display: flex; align-items: center; gap: 6px; }
.refresh-btn svg { transition: transform 0.4s; }
.refresh-btn.spinning svg { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.auto-badge {
    font-size: 11px; background: #dbeafe; color: #1d4ed8;
    padding: 2px 8px; border-radius: 20px; font-weight: 600;
}
</style>

<div class="jobs-wrap">
    <div class="jobs-head">
        <h1 class="jobs-title">Фонові процеси</h1>
        <div style="display:flex;align-items:center;gap:10px">
            <span class="auto-badge" id="autoLabel">Авто-оновлення: вкл</span>
            <button class="btn btn-ghost btn-sm refresh-btn" id="btnRefresh">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                Оновити
            </button>
        </div>
    </div>

    <div id="jobsList"></div>
</div>

<script>
var openedJobs = {};
var autoRefresh = true;
var autoTimer   = null;

function formatDuration(startedAt, finishedAt) {
    var start = new Date(startedAt.replace(' ', 'T'));
    var end   = finishedAt ? new Date(finishedAt.replace(' ', 'T')) : new Date();
    var sec   = Math.round((end - start) / 1000);
    if (sec < 60)   return sec + 'с';
    if (sec < 3600) return Math.floor(sec/60) + 'хв ' + (sec%60) + 'с';
    return Math.floor(sec/3600) + 'год ' + Math.floor((sec%3600)/60) + 'хв';
}

function formatDate(dt) {
    if (!dt) return '—';
    return dt.replace('T', ' ').substring(0, 16);
}

function colorLog(text) {
    return text
        .split('\n')
        .map(function(line) {
            if (/^\s+OK\s/.test(line))  return '<span class="log-ok">'  + escHtml(line) + '</span>';
            if (/^\s+ERR/.test(line))   return '<span class="log-err">' + escHtml(line) + '</span>';
            if (/^=+$/.test(line.trim()) || /^Category AI|^DONE:/.test(line.trim())) {
                return '<span class="log-hd">' + escHtml(line) + '</span>';
            }
            return escHtml(line);
        })
        .join('\n');
}

function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function loadList() {
    var btn = document.getElementById('btnRefresh');
    btn.classList.add('spinning');

    fetch('/jobs/api/list')
        .then(function(r) { return r.json(); })
        .then(function(d) {
            btn.classList.remove('spinning');
            if (!d.ok) return;
            renderList(d.jobs);
            // reload open job logs
            for (var jobId in openedJobs) {
                if (openedJobs[jobId]) loadLog(parseInt(jobId, 10));
            }
        })
        .catch(function() { btn.classList.remove('spinning'); });
}

function renderList(jobs) {
    var el = document.getElementById('jobsList');
    if (!jobs || !jobs.length) {
        el.innerHTML = '<div class="empty-state">'
            + '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
            + '<div>Немає зареєстрованих процесів</div></div>';
        return;
    }

    var html = '';
    for (var i = 0; i < jobs.length; i++) {
        var j = jobs[i];
        var id = j.job_id;
        var dur = formatDuration(j.started_at, j.finished_at);
        var started = formatDate(j.started_at);
        var isOpen  = !!openedJobs[id];

        html += '<div class="job-card" id="jcard-' + id + '">'
            + '<div class="job-card-head" onclick="toggleJob(' + id + ')">'
            +   '<div class="job-status ' + j.status + '"></div>'
            +   '<div class="job-title">' + escHtml(j.title) + '</div>'
            +   '<div class="job-counts" id="jcounts-' + id + '"></div>'
            +   '<div class="job-meta">' + started + ' &nbsp;·&nbsp; ' + dur + '</div>'
            +   '<span class="job-badge ' + j.status + '">' + statusLabel(j.status) + '</span>'
            + '</div>'
            + '<div class="job-body' + (isOpen ? ' open' : '') + '" id="jbody-' + id + '">'
            +   (j.script ? '<div class="job-script">' + escHtml(j.script) + '</div>' : '')
            +   '<div class="job-log" id="jlog-' + id + '">Завантаження&hellip;</div>'
            +   '<div class="job-footer">'
            +     '<span class="text-muted fs-12">PID: ' + (j.pid || '—') + '</span>'
            +   '</div>'
            + '</div>'
            + '</div>';
    }
    el.innerHTML = html;

    // Load logs for open cards
    for (var jobId in openedJobs) {
        if (openedJobs[jobId]) loadLog(parseInt(jobId, 10));
    }
}

function statusLabel(s) {
    if (s === 'running') return 'Виконується';
    if (s === 'done')    return 'Завершено';
    if (s === 'failed')  return 'Помилка';
    return s;
}

function toggleJob(jobId) {
    var body = document.getElementById('jbody-' + jobId);
    if (!body) return;
    var isOpen = body.classList.contains('open');
    body.classList.toggle('open', !isOpen);
    openedJobs[jobId] = !isOpen;
    if (!isOpen) loadLog(jobId);
}

function loadLog(jobId) {
    fetch('/jobs/api/status?job_id=' + jobId + '&lines=80')
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.ok) return;
            var logEl    = document.getElementById('jlog-'    + jobId);
            var countEl  = document.getElementById('jcounts-' + jobId);
            var badgeEl  = document.querySelector('#jcard-' + jobId + ' .job-badge');
            var statusEl = document.querySelector('#jcard-' + jobId + ' .job-status');

            if (logEl) {
                logEl.innerHTML = colorLog(d.tail || '(лог порожній)');
                logEl.scrollTop = logEl.scrollHeight;
            }
            if (countEl && (d.ok_count > 0 || d.err_count > 0)) {
                countEl.innerHTML = '<span class="ok-c">✓ ' + d.ok_count + '</span>'
                    + (d.err_count > 0 ? '&nbsp;&nbsp;<span class="err-c">✗ ' + d.err_count + '</span>' : '');
            }
            // Update badge if status changed
            if (badgeEl && d.job) {
                badgeEl.className = 'job-badge ' + d.job.status;
                badgeEl.textContent = statusLabel(d.job.status);
            }
            if (statusEl && d.job) {
                statusEl.className = 'job-status ' + d.job.status;
            }
        });
}

// Auto-refresh every 5s for running jobs
function scheduleAuto() {
    clearTimeout(autoTimer);
    if (!autoRefresh) return;
    autoTimer = setTimeout(function() {
        loadList();
        scheduleAuto();
    }, 5000);
}

document.getElementById('btnRefresh').addEventListener('click', function() {
    loadList();
});

document.getElementById('autoLabel').addEventListener('click', function() {
    autoRefresh = !autoRefresh;
    this.textContent = 'Авто-оновлення: ' + (autoRefresh ? 'вкл' : 'викл');
    this.style.background = autoRefresh ? '' : '#f1f5f9';
    this.style.color = autoRefresh ? '' : '#64748b';
    if (autoRefresh) scheduleAuto();
    else clearTimeout(autoTimer);
});

// Init
loadList();
scheduleAuto();
</script>

<?php require_once __DIR__ . '/../../../modules/shared/layout_end.php'; ?>
