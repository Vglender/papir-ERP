<?php
$title = 'Аудит зображень';
require_once __DIR__ . '/../../shared/layout.php';
?>
<style>
.audit-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}
.audit-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    transition: opacity .3s;
}
.audit-card.running { opacity: .45; }
.audit-card-val {
    font-size: 28px;
    font-weight: 700;
    line-height: 1.1;
    color: var(--text);
}
.audit-card-val.red   { color: var(--red); }
.audit-card-val.orange{ color: #e67e22; }
.audit-card-val.green { color: var(--green); }
.audit-card-lbl {
    font-size: 12px;
    color: var(--text-muted);
}
.audit-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    align-items: center;
}
.audit-ts {
    font-size: 12px;
    color: var(--text-muted);
    margin-left: auto;
}
/* Running banner */
.audit-banner {
    display: none;
    align-items: center;
    gap: 12px;
    background: #eef4ff;
    border: 1px solid #c3d8ff;
    border-radius: var(--radius);
    padding: 12px 16px;
    margin-bottom: 16px;
    font-size: 13px;
    font-weight: 500;
    color: var(--blue);
}
.audit-banner.visible { display: flex; }
.audit-banner-label { flex: 1; }
.audit-step-bar {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.audit-step {
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
    border: 1px solid #c3d8ff;
    color: #6b8fcf;
    background: #f0f5ff;
    white-space: nowrap;
}
.audit-step.active {
    background: var(--blue);
    color: #fff;
    border-color: var(--blue);
}
.audit-step.done {
    background: var(--green);
    color: #fff;
    border-color: var(--green);
}
/* Log */
.audit-log-wrap { display: none; margin-bottom: 24px; }
.audit-log-wrap.visible { display: block; }
.audit-log {
    background: #1a1a2e;
    color: #c8f0c8;
    font-family: monospace;
    font-size: 12px;
    line-height: 1.5;
    padding: 14px 16px;
    border-radius: var(--radius);
    height: 380px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-break: break-all;
}
.audit-log-placeholder {
    color: #7a9f8a;
    font-style: italic;
}
/* Spinner */
.audit-spinner {
    display: inline-block;
    width: 14px; height: 14px;
    border: 2px solid rgba(0,80,200,.25);
    border-top-color: var(--blue);
    border-radius: 50%;
    animation: spin .7s linear infinite;
    flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<div class="page-wrap">
    <div class="page-head">
        <div class="breadcrumb">Сервіс</div>
        <h1>Аудит зображень</h1>
    </div>

    <!-- Summary cards -->
    <div class="audit-grid" id="auditGrid">
        <?php
        $cards = array(
            array('key' => 'total_files_on_disk', 'label' => 'Файлів на диску',    'color' => ''),
            array('key' => 'total_db_refs',       'label' => 'Посилань в БД',      'color' => ''),
            array('key' => 'orphans',             'label' => 'Orphans',            'color' => 'orange'),
            array('key' => 'broken',              'label' => 'Broken refs',        'color' => 'red'),
            array('key' => 'duplicate_groups',    'label' => 'Дублі (груп)',       'color' => ''),
            array('key' => 'oversized',           'label' => 'Великі файли',       'color' => 'orange'),
            array('key' => 'undersized',          'label' => 'Дрібні зображення',  'color' => ''),
        );
        foreach ($cards as $c):
            $val = isset($summary[$c['key']]) ? number_format((int)$summary[$c['key']], 0, '.', ',') : '—';
        ?>
        <div class="audit-card" data-key="<?php echo $c['key']; ?>">
            <div class="audit-card-val <?php echo $c['color']; ?>"><?php echo $val; ?></div>
            <div class="audit-card-lbl"><?php echo $c['label']; ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Running banner (shown while process is active) -->
    <div class="audit-banner" id="auditBanner">
        <div class="audit-spinner"></div>
        <div class="audit-banner-label" id="bannerLabel">Виконується...</div>
        <div class="audit-step-bar" id="stepBar"></div>
    </div>

    <!-- Actions -->
    <div class="audit-actions" id="auditActions">
        <button class="btn btn-primary btn-sm" id="btnAudit"         onclick="runAction('audit')">&#9654; Запустити аудит</button>
        <button class="btn btn-ghost  btn-sm" id="btnDeleteOrphans"  onclick="runAction('delete_orphans')">&#128465; Видалити orphans</button>
        <button class="btn btn-ghost  btn-sm" id="btnFixBroken"      onclick="runAction('fix_broken')">&#128683; Видалити broken з БД</button>
        <button class="btn btn-ghost  btn-sm" id="btnRecompress"     onclick="runAction('recompress')">&#128190; Стиснути oversized</button>
        <button class="btn btn-ghost  btn-sm" id="btnWarmCache"      onclick="runAction('warm_cache')">&#9889; Прогріти cache</button>
        <span class="audit-ts" id="auditTs">
            <?php echo $generatedAt ? 'Останній аудит: ' . $generatedAt : 'Аудит ще не запускався'; ?>
        </span>
    </div>

    <!-- Log -->
    <div class="audit-log-wrap" id="logWrap">
        <div class="card" style="padding:0">
            <div style="padding:10px 14px; border-bottom:1px solid var(--border); font-size:13px; font-weight:500; display:flex; justify-content:space-between; align-items:center;">
                <span>Лог виконання</span>
                <button class="btn btn-ghost btn-xs" onclick="hideLog()">&#10005;</button>
            </div>
            <div class="audit-log" id="auditLog">
                <span class="audit-log-placeholder" id="logPlaceholder">Очікування запуску...</span>
            </div>
        </div>
    </div>
</div>

<script>
var _polling = null;
var _running = false;

/* Step definitions per action */
var ACTION_STEPS = {
    audit:          ['Підключення до БД', 'Сканування диску', 'Збір посилань з БД', 'Аналіз', 'Збереження звіту'],
    delete_orphans: ['Підключення до БД', 'Сканування диску', 'Збір посилань з БД', 'Аналіз', 'Видалення orphans', 'Збереження звіту'],
    fix_broken:     ['Підключення до БД', 'Сканування диску', 'Збір посилань з БД', 'Аналіз', 'Видалення broken з БД', 'Збереження звіту'],
    recompress:     ['Читання звіту', 'Стиснення файлів'],
    warm_cache:     ['Завантаження товарів', 'Генерація ескізів']
};

/* Keywords that signal a step is active (matched against last log lines) */
var STEP_SIGNALS = {
    audit: [
        ['connect', 'bootstrap', 'баз'],
        ['скануємо диск', 'scan disk', 'файлів на диску', 'scanned'],
        ['збираємо посилання', 'db refs', 'посилань зібрано'],
        ['аналіз', 'orphan', 'broken', 'duplicate', 'oversized'],
        ['звіт', 'report', 'json']
    ],
    delete_orphans: [
        ['connect', 'bootstrap', 'баз'],
        ['скануємо диск', 'scanned'],
        ['збираємо посилання', 'db refs'],
        ['аналіз', 'orphan', 'broken'],
        ['видален', 'deleted', 'orphan'],
        ['звіт', 'report']
    ],
    fix_broken: [
        ['connect', 'bootstrap', 'баз'],
        ['скануємо диск', 'scanned'],
        ['збираємо посилання', 'db refs'],
        ['аналіз', 'orphan', 'broken'],
        ['fix_broken', 'fixed', 'видален', 'broken refs', 'rows removed'],
        ['звіт', 'report']
    ],
    recompress: [
        ['читаємо', 'звіт', 'report', 'oversized'],
        ['обробка', 'processed', 'jpg', 'png', 'jpeg', 'saved']
    ],
    warm_cache: [
        ['товарів', 'products', 'category', 'categor'],
        ['thumbnail', 'cache', 'згенеровано', 'generated']
    ]
};

var _currentAction = null;
var _steps = [];

function runAction(action) {
    var labels = {
        audit:          'Запустити аудит?',
        delete_orphans: 'Видалити всі orphan файли?',
        recompress:     'Стиснути всі oversized файли?',
        warm_cache:     'Прогріти image cache?'
    };
    if (!confirm(labels[action] || 'Продовжити?')) return;

    /* Show UI immediately, before server responds */
    _currentAction = action;
    _steps = ACTION_STEPS[action] || [];
    setRunning(true);
    appendLog('Запуск ' + action + '...');

    fetch('/image-audit/api/action', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=' + action
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (!d.ok) {
            setRunning(false);
            showToast(d.error || 'Помилка');
            return;
        }
        startPolling();
    })
    .catch(function() {
        setRunning(false);
        showToast('Помилка мережі');
    });
}

function setRunning(on) {
    _running = on;
    var btns = document.querySelectorAll('#auditActions .btn');
    for (var i = 0; i < btns.length; i++) {
        btns[i].disabled = on;
    }
    var cards = document.querySelectorAll('.audit-card');
    for (var i = 0; i < cards.length; i++) {
        if (on) cards[i].classList.add('running');
        else     cards[i].classList.remove('running');
    }
    var banner = document.getElementById('auditBanner');
    if (on) banner.classList.add('visible');
    else    banner.classList.remove('visible');

    if (on) {
        renderSteps(-1);
        showLogWrap();
    }
}

function renderSteps(activeIdx) {
    var bar = document.getElementById('stepBar');
    if (!_steps.length) { bar.innerHTML = ''; return; }
    var html = '';
    for (var i = 0; i < _steps.length; i++) {
        var cls = 'audit-step';
        if (i < activeIdx)  cls += ' done';
        if (i === activeIdx) cls += ' active';
        html += '<span class="' + cls + '">' + _steps[i] + '</span>';
    }
    bar.innerHTML = html;
}

function guessStep(logLines) {
    if (!_currentAction || !STEP_SIGNALS[_currentAction]) return -1;
    var signals = STEP_SIGNALS[_currentAction];
    var tail = logLines.slice(-10).join('\n').toLowerCase();
    var best = -1;
    for (var s = 0; s < signals.length; s++) {
        var kws = signals[s];
        for (var k = 0; k < kws.length; k++) {
            if (tail.indexOf(kws[k]) !== -1) { best = s; break; }
        }
    }
    return best;
}

function showLogWrap() {
    document.getElementById('logWrap').classList.add('visible');
}

function hideLog() {
    document.getElementById('logWrap').classList.remove('visible');
}

function appendLog(text) {
    var el = document.getElementById('auditLog');
    var ph = document.getElementById('logPlaceholder');
    if (ph) { ph.parentNode.removeChild(ph); }
    el.textContent += text + '\n';
    el.scrollTop = el.scrollHeight;
}

var _lastLogLen   = 0;
var _pollErrors   = 0;
var POLL_MAX_ERRORS = 5;

function startPolling() {
    if (_polling) clearInterval(_polling);
    _lastLogLen = 0;
    _pollErrors = 0;
    _polling = setInterval(poll, 1000);
    poll();
}

function poll() {
    fetch('/image-audit/api/status')
    .then(function(r) { return r.json(); })
    .then(function(d) {
        _pollErrors = 0;
        if (!d.ok) return;

        /* Update log — only new lines */
        if (d.log && d.log.length) {
            var el = document.getElementById('auditLog');
            var ph = document.getElementById('logPlaceholder');
            if (ph) { ph.parentNode.removeChild(ph); }
            if (d.log.length !== _lastLogLen) {
                el.textContent = d.log.join('\n');
                el.scrollTop = el.scrollHeight;
                _lastLogLen = d.log.length;
            }
            /* Guess current step */
            var step = guessStep(d.log);
            if (step >= 0) renderSteps(step);
        }

        if (!d.running) {
            clearInterval(_polling);
            _polling = null;
            setRunning(false);

            /* Update summary cards */
            if (d.summary) updateCards(d.summary);
            if (d.generated_at) {
                document.getElementById('auditTs').textContent = 'Останній аудит: ' + d.generated_at;
            }
            showToast('Завершено');
        }
    })
    .catch(function() {
        _pollErrors++;
        if (_pollErrors >= POLL_MAX_ERRORS) {
            clearInterval(_polling);
            _polling = null;
            setRunning(false);
            showToast('Зв\'язок втрачено, перевірте статус вручну');
        }
    });
}

function updateCards(s) {
    var map = {
        total_files_on_disk: s.total_files_on_disk,
        total_db_refs:       s.total_db_refs,
        orphans:             s.orphans,
        broken:              s.broken,
        duplicate_groups:    s.duplicate_groups,
        oversized:           s.oversized,
        undersized:          s.undersized
    };
    var cards = document.querySelectorAll('.audit-card[data-key]');
    for (var i = 0; i < cards.length; i++) {
        var key = cards[i].getAttribute('data-key');
        if (key in map && map[key] !== undefined) {
            cards[i].querySelector('.audit-card-val').textContent =
                map[key].toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
    }
}

/* On load: check if already running */
(function() {
    fetch('/image-audit/api/status')
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.running) {
            setRunning(true);
            startPolling();
        }
    });
})();
</script>

<?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>
