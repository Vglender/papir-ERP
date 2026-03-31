<?php
/** @var array $tplData */
$t = $tplData;

$statusLabels = array(
    'draft'    => array('label' => 'Чернетка', 'class' => 'badge-gray'),
    'active'   => array('label' => 'Активний', 'class' => 'badge-green'),
    'archived' => array('label' => 'Архів',    'class' => 'badge-orange'),
);

$types = (new PrintTemplateRepository())->getTypes();

// Parse variables_schema for the test-values panel
$schemaVars = array();
if (!empty($t['variables_schema'])) {
    $decoded = json_decode($t['variables_schema'], true);
    if ($decoded && isset($decoded['variables'])) {
        $schemaVars = $decoded['variables'];
    }
}
?>
<!-- CodeMirror 5 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/eclipse.min.css">
<style>
/* ── Editor layout ──────────────────────────────────────────────────────── */
.ed-wrap        { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 0; }
#tplForm        { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 0; }

.ed-meta        { display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
                  padding: 10px 16px; background: #fff; border-bottom: 1px solid #e5e7eb;
                  flex-shrink: 0; }
.ed-meta-field  { display: flex; align-items: center; gap: 6px; }
.ed-meta-field label { font-size: 11px; font-weight: 700; color: #94a3b8;
                        text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }
.ed-meta-field input,
.ed-meta-field select { height: 30px; font-size: 13px; padding: 0 8px; }
.ed-meta-field input  { width: 160px; }
.ed-meta-sep    { color: #e5e7eb; }
.ed-meta-badge  { display: flex; align-items: center; gap: 6px; }

/* ── Split pane ─────────────────────────────────────────────────────────── */
.ed-split       { flex: 1; display: flex; overflow: hidden; min-height: 0; }

/* Code pane */
.ed-code-pane   { width: 50%; min-width: 0; display: flex; flex-direction: column;
                  border-right: 1px solid #e5e7eb; overflow: hidden; }
.ed-pane-head   { padding: 6px 12px; background: #f8fafc; border-bottom: 1px solid #e5e7eb;
                  font-size: 11px; font-weight: 700; color: #64748b; letter-spacing: .04em;
                  display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.ed-pane-head .btn { height: 22px; padding: 0 8px; font-size: 11px; margin-left: auto; }

.CodeMirror     { flex: 1; height: 100%; font-family: 'Fira Code', 'Courier New', monospace;
                  font-size: 12.5px; line-height: 1.6; }
.CodeMirror-scroll { height: 100%; }

/* Mustache variable highlight */
.cm-mustache    { background: #fef3c7; color: #92400e; border-radius: 2px;
                  font-weight: 600; padding: 0 1px; }
.cm-mustache-section { background: #dbeafe; color: #1e40af; border-radius: 2px;
                       font-weight: 600; padding: 0 1px; }

/* Preview pane */
.ed-preview-pane { flex: 1; min-width: 0; display: flex; flex-direction: column; overflow: hidden; }
.ed-preview-iframe { flex: 1; border: none; background: #fff; }
.ed-preview-loading { display: flex; align-items: center; justify-content: center;
                      height: 100%; color: #94a3b8; font-size: 13px; }

/* ── Test values panel ──────────────────────────────────────────────────── */
.ed-testvals    { flex-shrink: 0; background: #fff; border-top: 1px solid #e5e7eb;
                  overflow: hidden; transition: max-height .25s; max-height: 0;
                  height: 0; }
.ed-testvals.open { max-height: 200px; height: 200px; }
.ed-testvals-inner { padding: 10px 16px 12px; display: flex; flex-direction: column; gap: 8px;
                     height: 100%; }
.ed-testvals-head { display: flex; align-items: center; gap: 8px; font-size: 12px;
                    font-weight: 700; color: #64748b; flex-shrink: 0; }
.ed-testvals-head .btn { height: 24px; padding: 0 8px; font-size: 11px; margin-left: auto; }
.ed-testvals-grid { display: flex; flex-wrap: wrap; gap: 8px; overflow-y: auto;
                    padding-bottom: 4px; }
.ed-tv-field    { display: flex; flex-direction: column; gap: 3px; min-width: 140px; }
.ed-tv-field label { font-size: 10px; font-weight: 700; color: #94a3b8;
                     letter-spacing: .04em; font-family: monospace; cursor: default; }
.ed-tv-field label span { color: #0d9488; }
.ed-tv-field input { height: 28px; font-size: 12px; padding: 0 7px; width: 100%; }
.ed-tv-no-vars  { font-size: 12px; color: #94a3b8; padding: 4px 0; }

/* ── Footer ─────────────────────────────────────────────────────────────── */
.ed-footer      { display: flex; align-items: center; gap: 8px; padding: 10px 16px;
                  background: #fff; border-top: 1px solid #f1f3f6; flex-shrink: 0; }
.ed-footer .btn { height: 32px; padding: 0 16px; font-size: 13px; }
.ed-testvals-toggle { display: flex; align-items: center; gap: 5px; font-size: 12px;
                      color: #64748b; border: 1px solid #e5e7eb; background: #fff;
                      border-radius: 6px; padding: 0 10px; height: 32px; cursor: pointer; }
.ed-testvals-toggle:hover { border-color: #0d9488; color: #0d9488; background: #f0fdfa; }
.ed-testvals-toggle.active { border-color: #0d9488; color: #0d9488; background: #f0fdfa; }
.ed-testvals-toggle svg { transition: transform .2s; }
.ed-testvals-toggle.active svg { transform: rotate(180deg); }
</style>

<div class="ed-wrap" id="edWrap">

    <!-- Meta bar -->
    <form id="tplForm">
    <input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
    <div class="ed-meta">
        <div class="ed-meta-field">
            <label>Назва</label>
            <input type="text" name="name" value="<?php echo ViewHelper::h($t['name']); ?>" required style="width:220px">
        </div>
        <div class="ed-meta-sep">|</div>
        <div class="ed-meta-field">
            <label>Код</label>
            <input type="text" name="code" value="<?php echo ViewHelper::h($t['code']); ?>" required style="width:140px">
        </div>
        <div class="ed-meta-sep">|</div>
        <div class="ed-meta-field">
            <label>Тип</label>
            <select name="type_id">
                <?php foreach ($types as $ty): ?>
                <option value="<?php echo (int)$ty['id']; ?>" <?php echo $ty['id'] == $t['type_id'] ? 'selected' : ''; ?>>
                    <?php echo ViewHelper::h($ty['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ed-meta-sep">|</div>
        <div class="ed-meta-field">
            <label>Статус</label>
            <select name="status">
                <option value="draft"    <?php echo $t['status']=='draft'    ? 'selected':''?>>Чернетка</option>
                <option value="active"   <?php echo $t['status']=='active'   ? 'selected':''?>>Активний</option>
                <option value="archived" <?php echo $t['status']=='archived' ? 'selected':''?>>Архів</option>
            </select>
        </div>
        <div class="ed-meta-badge">
            <span class="badge <?php echo $statusLabels[$t['status']]['class']; ?>"><?php echo $statusLabels[$t['status']]['label']; ?></span>
            <span class="text-muted fs-12">v<?php echo (int)$t['version']; ?></span>
        </div>
    </div>

    <!-- Split pane: code | preview -->
    <div class="ed-split">

        <!-- Code pane -->
        <div class="ed-code-pane">
            <div class="ed-pane-head">
                <svg width="12" height="12" viewBox="0 0 16 16" fill="none">
                    <path d="M5 3L2 8l3 5M11 3l3 5-3 5M9 2l-2 12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
                HTML шаблон
                <span style="font-weight:400;color:#b0b8c8">· Ctrl+S для збереження</span>
            </div>
            <textarea id="tplHtmlBody" name="html_body"
                      style="display:none"><?php echo ViewHelper::h($t['html_body']); ?></textarea>
        </div>

        <!-- Preview pane -->
        <div class="ed-preview-pane">
            <div class="ed-pane-head">
                <svg width="12" height="12" viewBox="0 0 16 16" fill="none">
                    <path d="M1 8s3-5 7-5 7 5 7 5-3 5-7 5-7-5-7-5z" stroke="currentColor" stroke-width="1.5"/>
                    <circle cx="8" cy="8" r="2.2" stroke="currentColor" stroke-width="1.5"/>
                </svg>
                Перегляд
                <span id="previewStatus" style="font-weight:400;color:#b0b8c8"></span>
                <button class="btn btn-ghost" type="button" id="tplRefreshPreview">↺ Оновити</button>
                <button class="btn btn-ghost" type="button" id="tplOpenPreviewBtn" title="Відкрити у новій вкладці">↗</button>
            </div>
            <div class="ed-preview-loading" id="previewLoading">Натисніть «Оновити» для перегляду</div>
            <iframe class="ed-preview-iframe" id="tplPreviewFrame" sandbox="allow-same-origin" style="display:none"></iframe>
        </div>
    </div>

    <!-- Test values panel (collapsible) -->
    <div class="ed-testvals" id="edTestVals">
        <div class="ed-testvals-inner">
            <div class="ed-testvals-head">
                <svg width="12" height="12" viewBox="0 0 16 16" fill="none">
                    <path d="M3 3h10M3 6.5h10M3 10h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                Тестові значення для перегляду
                <button class="btn btn-ghost" type="button" id="extractVarsBtn" title="Витягти змінні з шаблону">⟳ Оновити з шаблону</button>
            </div>
            <div class="ed-testvals-grid" id="testValsGrid">
                <div class="ed-tv-no-vars" id="noVarsMsg">Натисніть «Оновити з шаблону» щоб витягти змінні</div>
            </div>
        </div>
    </div>

    </form>

    <!-- Footer -->
    <div class="ed-footer">
        <button class="btn btn-primary" type="button" id="tplSaveBtn">Зберегти</button>
        <button class="btn btn-ghost" type="button" id="tplNewVersionBtn">+ Нова версія</button>
        <button class="ed-testvals-toggle" type="button" id="testValsToggle">
            <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                <path d="M2 3.5l3 3 3-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            Тестові дані
        </button>
        <span id="tplSaveStatus" class="text-muted fs-12" style="margin-left:auto"></span>
    </div>

</div><!-- /ed-wrap -->

<!-- CodeMirror scripts (load after DOM) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/mode/overlay.min.js"></script>

<script>
(function () {

    // ── CodeMirror setup ─────────────────────────────────────────────────────
    var textarea = document.getElementById('tplHtmlBody');

    // Mustache overlay: highlights {{var}}, {{#section}}, {{/section}}
    CodeMirror.defineMode('mustache-overlay', function () {
        return {
            token: function (stream) {
                if (stream.match('{{')) {
                    var section = stream.peek() === '#' || stream.peek() === '/';
                    while (!stream.eol()) {
                        if (stream.match('}}')) {
                            return section ? 'mustache-section' : 'mustache';
                        }
                        stream.next();
                    }
                    return section ? 'mustache-section' : 'mustache';
                }
                while (stream.next() !== null && !stream.match('{{', false)) {}
                return null;
            }
        };
    });

    var cm = CodeMirror.fromTextArea(textarea, {
        mode: CodeMirror.overlayMode(
            CodeMirror.getMode({}, 'htmlmixed'),
            CodeMirror.getMode({}, 'mustache-overlay')
        ),
        theme:        'eclipse',
        lineNumbers:  true,
        lineWrapping: false,
        tabSize:      2,
        indentWithTabs: false,
        extraKeys: {
            'Ctrl-S': function () { doSave(null); },
            'Cmd-S':  function () { doSave(null); },
        },
    });

    // Keep CodeMirror filling its container
    function resizeCM() {
        var pane = document.querySelector('.ed-code-pane');
        if (!pane) return;
        var head  = pane.querySelector('.ed-pane-head');
        var h     = pane.offsetHeight - (head ? head.offsetHeight : 0);
        cm.setSize('100%', Math.max(200, h));
        cm.refresh();
    }
    window.addEventListener('resize', resizeCM);
    setTimeout(resizeCM, 100);

    // ── Test values panel ────────────────────────────────────────────────────
    var testVals    = document.getElementById('edTestVals');
    var testToggle  = document.getElementById('testValsToggle');
    var testGrid    = document.getElementById('testValsGrid');
    var noVarsMsg   = document.getElementById('noVarsMsg');
    var _testValues = {}; // key → current value

    testToggle.addEventListener('click', function () {
        var open = testVals.classList.toggle('open');
        testToggle.classList.toggle('active', open);
        setTimeout(resizeCM, 260);
        if (open && Object.keys(_testValues).length === 0) {
            extractAndBuildVars();
        }
    });

    document.getElementById('extractVarsBtn').addEventListener('click', function () {
        extractAndBuildVars();
    });

    function extractVarsFromHtml(html) {
        var found = {};
        // Match {{var}} and {{object.prop}} — skip {{#section}}, {{/section}}, {{!comment}}
        var re = /\{\{([^#\/!>^{][^}]*)\}\}/g;
        var m;
        while ((m = re.exec(html)) !== null) {
            var key = m[1].trim();
            // Skip section close, lambda, etc. Already filtered by regex above
            if (key.indexOf(' ') !== -1) continue; // mustache lambdas
            found[key] = found[key] !== undefined ? found[key] : '';
        }
        return found;
    }

    function extractAndBuildVars() {
        var html    = cm.getValue();
        var found   = extractVarsFromHtml(html);
        var keys    = Object.keys(found).sort();

        // Merge: keep existing values, add new keys
        keys.forEach(function (k) {
            if (_testValues[k] === undefined) { _testValues[k] = ''; }
        });
        // Remove keys no longer in template
        Object.keys(_testValues).forEach(function (k) {
            if (found[k] === undefined) { delete _testValues[k]; }
        });

        buildGrid(Object.keys(_testValues).sort());
    }

    function buildGrid(keys) {
        testGrid.innerHTML = '';
        if (keys.length === 0) {
            var msg = document.createElement('div');
            msg.className = 'ed-tv-no-vars';
            msg.textContent = 'Змінних не знайдено — можливо шаблон порожній';
            testGrid.appendChild(msg);
            return;
        }
        keys.forEach(function (key) {
            var field = document.createElement('div');
            field.className = 'ed-tv-field';

            var lbl = document.createElement('label');
            lbl.title = 'Подвійний клік — вставити в шаблон';
            lbl.innerHTML = '<span>{{' + escHtml(key) + '}}</span>';
            lbl.style.cursor = 'pointer';
            lbl.addEventListener('dblclick', function () {
                var cur = cm.getCursor();
                cm.replaceRange('{{' + key + '}}', cur);
                cm.focus();
            });

            var inp = document.createElement('input');
            inp.type  = 'text';
            inp.value = _testValues[key] || getSampleValue(key);
            inp.addEventListener('input', function () {
                _testValues[key] = inp.value;
            });

            field.appendChild(lbl);
            field.appendChild(inp);
            testGrid.appendChild(field);
        });
    }

    function getSampleValue(key) {
        // Auto-fill sensible defaults based on key name
        var k = key.toLowerCase();
        if (k.indexOf('number')  !== -1 || k.indexOf('num') !== -1)  return 'ТОВ-РАХ-2026-0001';
        if (k.indexOf('date')    !== -1)                              return new Date().toLocaleDateString('uk-UA');
        if (k.indexOf('name')    !== -1)                              return 'ТОВ "Назва"';
        if (k.indexOf('address') !== -1)                              return 'м. Київ, вул. Прикладна, 1';
        if (k.indexOf('phone')   !== -1)                              return '+380 44 000-00-00';
        if (k.indexOf('email')   !== -1)                              return 'info@example.com';
        if (k.indexOf('iban')    !== -1)                              return 'UA123456789012345678901234567';
        if (k.indexOf('okpo')    !== -1 || k.indexOf('edrpou') !== -1) return '12345678';
        if (k.indexOf('price')   !== -1 || k.indexOf('total') !== -1
            || k.indexOf('sum')  !== -1 || k.indexOf('amount') !== -1) return '1 250,00';
        if (k.indexOf('qty')     !== -1 || k.indexOf('quantity') !== -1) return '5';
        if (k.indexOf('rate')    !== -1 || k.indexOf('vat') !== -1)   return '20';
        if (k.indexOf('mfo')     !== -1)                              return '305299';
        return '—';
    }

    function escHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // ── Preview ──────────────────────────────────────────────────────────────
    var previewFrame   = document.getElementById('tplPreviewFrame');
    var previewLoading = document.getElementById('previewLoading');
    var previewStatus  = document.getElementById('previewStatus');
    var _previewTimer  = null;

    function buildContext() {
        // Merge custom test values over the sample context
        var ctx = {};
        Object.keys(_testValues).forEach(function (k) {
            // Handle dot-notation: seller.name → ctx.seller = { name: ... }
            var parts = k.split('.');
            if (parts.length === 2) {
                if (!ctx[parts[0]]) { ctx[parts[0]] = {}; }
                ctx[parts[0]][parts[1]] = _testValues[k] || getSampleValue(k);
            } else {
                ctx[k] = _testValues[k] || getSampleValue(k);
            }
        });
        return ctx;
    }

    function renderPreview() {
        previewStatus.textContent = 'оновлення…';
        var params = new URLSearchParams({
            html_body:   cm.getValue(),
            test_values: JSON.stringify(buildContext()),
        });
        fetch('/print/api/render_preview', { method: 'POST', body: params })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            previewStatus.textContent = '';
            if (!d.ok) {
                previewFrame.style.display  = 'none';
                previewLoading.style.display = 'flex';
                previewLoading.textContent   = 'Помилка шаблону: ' + d.error;
                previewLoading.style.color   = '#ef4444';
                return;
            }
            previewLoading.style.display = 'none';
            previewFrame.style.display   = 'block';
            previewFrame.srcdoc          = d.html;
        })
        .catch(function () { previewStatus.textContent = 'помилка мережі'; });
    }

    // Debounced auto-preview on edit (600ms after last keystroke)
    cm.on('change', function () {
        clearTimeout(_previewTimer);
        previewStatus.textContent = '…';
        _previewTimer = setTimeout(renderPreview, 600);
    });

    document.getElementById('tplRefreshPreview').addEventListener('click', renderPreview);

    // Open in new tab
    document.getElementById('tplOpenPreviewBtn').addEventListener('click', function () {
        var params = new URLSearchParams({
            html_body:   cm.getValue(),
            test_values: JSON.stringify(buildContext()),
        });
        fetch('/print/api/render_preview', { method: 'POST', body: params })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.ok) { showToast('Помилка рендеру', true); return; }
            var w = window.open('', '_blank');
            w.document.write(d.html);
            w.document.close();
        });
    });

    // Render preview on initial load
    if (cm.getValue().trim()) { renderPreview(); }

    // ── Save ─────────────────────────────────────────────────────────────────
    function doSave(afterSave) {
        var saveBtn = document.getElementById('tplSaveBtn');
        var status  = document.getElementById('tplSaveStatus');
        saveBtn.disabled = true;
        status.textContent = 'Зберігаю…';

        // Sync CodeMirror → textarea before FormData
        cm.save();
        var fd = new FormData(document.getElementById('tplForm'));
        fetch('/print/api/save_template', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            saveBtn.disabled = false;
            if (!d.ok) { showToast('Помилка: ' + (d.error || ''), true); status.textContent = ''; return; }
            status.textContent = 'Збережено ✓';
            setTimeout(function () { status.textContent = ''; }, 2500);
            showToast('Збережено');
            if (afterSave) { afterSave(d.id); }
        })
        .catch(function () { saveBtn.disabled = false; status.textContent = ''; showToast('Помилка мережі', true); });
    }

    document.getElementById('tplSaveBtn').addEventListener('click', function () { doSave(null); });

    // New version
    document.getElementById('tplNewVersionBtn').addEventListener('click', function () {
        if (!confirm('Зберегти поточні зміни і створити нову версію?')) return;
        doSave(function (savedId) {
            var params = new URLSearchParams({ action: 'new_version', parent_id: savedId });
            fetch('/print/api/save_template', { method: 'POST', body: params })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) { showToast('Помилка: ' + (d.error || ''), true); return; }
                window.location.href = '/print/templates?selected=' + d.id;
            });
        });
    });

}());
</script>