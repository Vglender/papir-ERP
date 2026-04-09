<style>
.pq-toolbar { display:flex; align-items:center; gap:8px; padding:12px 16px; background:#fff; border:1px solid var(--border); border-radius:10px 10px 0 0; }
.pq-toolbar .btn { height:32px; padding:0 14px; font-size:13px; }
.pq-count { font-size:13px; color:var(--text-muted); margin-left:auto; }
.pq-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid var(--border); border-top:0; border-radius:0 0 10px 10px; overflow:hidden; }
.pq-table th { font-size:11px; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:.4px; padding:8px 10px; text-align:left; border-bottom:1px solid var(--border); background:#fafafa; }
.pq-table td { padding:10px; font-size:13px; border-bottom:1px solid #f3f4f6; vertical-align:top; }
.pq-table tr:hover { background:#f8fafc; }
.pq-demand-num { font-weight:600; color:#1e293b; }
.pq-demand-cp { font-size:12px; color:#6b7280; }
.pq-docs { display:flex; flex-wrap:wrap; gap:4px; }
.pq-doc-tag { font-size:11px; padding:2px 7px; border-radius:4px; background:#f1f5f9; color:#475569; white-space:nowrap; }
.pq-doc-tag.ok { background:#dcfce7; color:#15803d; }
.pq-doc-tag.skip { background:#f3f4f6; color:#9ca3af; }
.pq-doc-tag.error { background:#fee2e2; color:#b91c1c; }
.pq-actions { display:flex; gap:4px; }
.pq-actions .btn { height:26px; padding:0 8px; font-size:12px; }
.pq-empty { text-align:center; padding:48px 16px; color:#94a3b8; font-size:14px; }
.pq-time { font-size:12px; color:#94a3b8; white-space:nowrap; }
.pq-bulk { display:flex; align-items:center; gap:8px; padding:10px 16px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; margin-bottom:10px; display:none; }
.pq-bulk.visible { display:flex; }
.pq-bulk-count { font-weight:600; font-size:13px; }
</style>

<div class="page-shell">

<div class="pq-bulk" id="pqBulk">
    <span>Обрано: <span class="pq-bulk-count" id="pqBulkCount">0</span></span>
    <button type="button" class="btn btn-primary" id="pqBulkPrint">🖨 Друкувати вибрані</button>
    <button type="button" class="btn" id="pqBulkRemove">✓ Прибрати з черги</button>
</div>

<div class="pq-toolbar">
    <button type="button" class="btn btn-primary" id="pqPrintAll">🖨 Друкувати все</button>
    <button type="button" class="btn" id="pqClearAll">🗑 Очистити чергу</button>
    <button type="button" class="btn" id="pqRefresh">↺ Оновити</button>
    <div class="pq-count" id="pqTotalCount"></div>
</div>

<table class="pq-table">
    <thead>
    <tr>
        <th style="width:32px"><input type="checkbox" id="pqCheckAll"></th>
        <th>Відвантаження</th>
        <th>Профіль</th>
        <th>Документи</th>
        <th style="width:80px">Додано</th>
        <th style="width:120px">Дії</th>
    </tr>
    </thead>
    <tbody id="pqBody">
        <tr><td colspan="6" class="pq-empty" id="pqLoading">Завантаження…</td></tr>
    </tbody>
</table>

</div>

<script>
(function() {
    var body     = document.getElementById('pqBody');
    var checkAll = document.getElementById('pqCheckAll');
    var bulk     = document.getElementById('pqBulk');
    var bulkCnt  = document.getElementById('pqBulkCount');
    var totalCnt = document.getElementById('pqTotalCount');
    var _queue   = [];

    function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    function load() {
        body.innerHTML = '<tr><td colspan="6" class="pq-empty">Завантаження…</td></tr>';
        fetch('/print/api/queue')
            .then(function(r) { return r.json(); })
            .then(function(d) {
                _queue = (d.ok && d.queue) ? d.queue : [];
                render();
            })
            .catch(function() {
                body.innerHTML = '<tr><td colspan="6" class="pq-empty" style="color:#ef4444">Помилка завантаження</td></tr>';
            });
    }

    function render() {
        if (!_queue.length) {
            body.innerHTML = '<tr><td colspan="6" class="pq-empty">Черга друку порожня</td></tr>';
            totalCnt.textContent = '';
            bulk.classList.remove('visible');
            return;
        }
        totalCnt.textContent = 'Пакетів: ' + _queue.length;
        var html = '';
        _queue.forEach(function(q) {
            var items = q.items || [];
            var docsHtml = '';
            items.forEach(function(it) {
                var cls = it.status || 'skip';
                docsHtml += '<span class="pq-doc-tag ' + cls + '">' + esc(it.label || it.type) + '</span>';
            });

            var time = '';
            if (q.created_at) {
                var parts = q.created_at.split(' ');
                time = parts.length > 1 ? parts[1].substring(0, 5) : parts[0];
            }

            html += '<tr data-pack-id="' + q.id + '">'
                + '<td><input type="checkbox" class="pq-check" value="' + q.id + '"></td>'
                + '<td>'
                + '<div class="pq-demand-num">№ ' + esc(q.demand_number || q.demand_id) + '</div>'
                + '<div class="pq-demand-cp">' + esc(q.counterparty_name || '') + '</div>'
                + '</td>'
                + '<td>' + esc(q.profile_name || '—') + '</td>'
                + '<td><div class="pq-docs">' + docsHtml + '</div></td>'
                + '<td class="pq-time">' + esc(time) + '</td>'
                + '<td><div class="pq-actions">'
                + '<button type="button" class="btn" onclick="pqPrintOne(' + q.id + ')" title="Друкувати">🖨</button>'
                + '<a href="/demand/edit?id=' + q.demand_id + '" class="btn" title="Відкрити відвантаження">↗</a>'
                + '<button type="button" class="btn" onclick="pqRemove([' + q.id + '])" title="Прибрати з черги">✕</button>'
                + '</div></td>'
                + '</tr>';
        });
        body.innerHTML = html;
        updateBulk();
    }

    // Selection
    checkAll.addEventListener('change', function() {
        body.querySelectorAll('.pq-check').forEach(function(cb) { cb.checked = checkAll.checked; });
        updateBulk();
    });
    body.addEventListener('change', function(e) {
        if (e.target.classList.contains('pq-check')) updateBulk();
    });

    function getSelectedIds() {
        var ids = [];
        body.querySelectorAll('.pq-check:checked').forEach(function(cb) { ids.push(parseInt(cb.value)); });
        return ids;
    }

    function updateBulk() {
        var ids = getSelectedIds();
        bulkCnt.textContent = ids.length;
        bulk.classList.toggle('visible', ids.length > 0);
    }

    // Print one pack
    window.pqPrintOne = function(packId) {
        var pack = _queue.filter(function(q) { return q.id == packId; })[0];
        if (!pack) return;
        (pack.items || []).forEach(function(it) {
            if (it.status === 'ok' && it.url) window.open(it.url, '_blank');
        });
    };

    // Remove from queue
    window.pqRemove = function(ids) {
        var fd = new FormData();
        fd.append('pack_ids', ids.join(','));
        fetch('/print/api/queue_remove', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.ok) load();
            });
    };

    // Print all
    document.getElementById('pqPrintAll').addEventListener('click', function() {
        _queue.forEach(function(q) {
            (q.items || []).forEach(function(it) {
                if (it.status === 'ok' && it.url) window.open(it.url, '_blank');
            });
        });
    });

    // Clear all
    document.getElementById('pqClearAll').addEventListener('click', function() {
        if (!_queue.length) return;
        if (!confirm('Очистити всю чергу друку? (' + _queue.length + ' пакетів)')) return;
        fetch('/print/api/queue_clear', { method: 'POST' })
            .then(function(r) { return r.json(); })
            .then(function(d) { if (d.ok) load(); });
    });

    // Bulk print selected
    document.getElementById('pqBulkPrint').addEventListener('click', function() {
        var ids = getSelectedIds();
        ids.forEach(function(id) { pqPrintOne(id); });
    });

    // Bulk remove selected
    document.getElementById('pqBulkRemove').addEventListener('click', function() {
        var ids = getSelectedIds();
        if (!ids.length) return;
        pqRemove(ids);
    });

    // Refresh
    document.getElementById('pqRefresh').addEventListener('click', load);

    load();
}());
</script>
