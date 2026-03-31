<!--
  Shared print modal.
  Include ONCE per page that needs print functionality.
  JS interface: PrintModal.open(entityType, entityId, orgId)
-->
<div class="modal-overlay" id="printModal" style="display:none">
    <div class="print-modal-box">
        <div class="modal-head">
            <span id="printModalTitle">Друк документа</span>
            <button class="modal-close" type="button" onclick="PrintModal.close()">&#x2715;</button>
        </div>
        <div class="print-modal-body">
            <!-- Template list sidebar -->
            <div class="print-tpl-sidebar" id="printTplList">
                <div class="print-tpl-loading">Завантаження…</div>
            </div>
            <!-- Preview area -->
            <div class="print-preview-area">
                <div class="print-preview-empty" id="printPreviewEmpty">
                    Оберіть шаблон зі списку
                </div>
                <iframe id="printPreviewFrame"
                        style="width:100%;height:100%;border:0;display:none"
                        sandbox="allow-same-origin allow-scripts allow-modals"></iframe>
                <div class="print-preview-spinner" id="printPreviewSpinner" style="display:none">
                    <div class="print-spinner-dot"></div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" id="printDoBtn" type="button"
                    onclick="PrintModal.doPrint()" disabled>
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" style="margin-right:4px;vertical-align:-1px">
                    <rect x="2" y="5" width="12" height="8" rx="1" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M4 5V2h8v3" stroke="currentColor" stroke-width="1.5"/>
                    <rect x="4" y="9" width="8" height="4" rx=".5" fill="currentColor" opacity=".3"/>
                    <circle cx="11.5" cy="7.5" r=".75" fill="currentColor"/>
                </svg>Друкувати
            </button>
            <button class="btn btn-ghost" type="button" onclick="PrintModal.close()">Закрити</button>
        </div>
    </div>
</div>

<style>
.print-modal-box {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 20px 60px rgba(0,0,0,.18);
    display: flex;
    flex-direction: column;
    width: 92vw;
    max-width: 1100px;
    height: 86vh;
    overflow: hidden;
}
.print-modal-body {
    flex: 1;
    display: flex;
    min-height: 0;
}
.print-tpl-sidebar {
    width: 220px;
    flex-shrink: 0;
    border-right: 1px solid #e5e7eb;
    overflow-y: auto;
    background: #fafafa;
}
.print-tpl-loading {
    padding: 16px 12px;
    font-size: 13px;
    color: #94a3b8;
}
.print-tpl-group {}
.print-tpl-type {
    padding: 8px 12px 4px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .07em;
    color: #94a3b8;
    text-transform: uppercase;
}
.print-tpl-row {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 7px 12px;
    cursor: pointer;
    font-size: 12.5px;
    border-left: 3px solid transparent;
}
.print-tpl-row:hover { background: #f0f9ff; }
.print-tpl-row.selected { background: #eff6ff; border-left-color: #3b82f6; }
.print-tpl-name { flex: 1; font-weight: 500; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.print-tpl-ver  { font-size: 10px; color: #94a3b8; flex-shrink: 0; }
.print-tpl-empty { padding: 16px 12px; font-size: 13px; color: #94a3b8; }

.print-preview-area {
    flex: 1;
    min-width: 0;
    position: relative;
    background: #f8fafc;
}
.print-preview-empty {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    color: #94a3b8;
}
.print-preview-spinner {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(248,250,252,.8);
}
.print-spinner-dot {
    width: 32px;
    height: 32px;
    border: 3px solid #e5e7eb;
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: printSpin .7s linear infinite;
}
@keyframes printSpin { to { transform: rotate(360deg); } }
</style>

<script>
var PrintModal = (function () {
    var _entityType, _entityId, _orgId;

    function open(entityType, entityId, orgId) {
        _entityType = entityType;
        _entityId   = entityId;
        _orgId      = orgId || 0;

        document.getElementById('printModal').style.display = 'flex';
        document.getElementById('printDoBtn').disabled = true;
        _showEmpty();
        _loadTemplates();
    }

    function close() {
        document.getElementById('printModal').style.display = 'none';
        var frame = document.getElementById('printPreviewFrame');
        frame.style.display = 'none';
        frame.src = 'about:blank';
    }

    function _showEmpty() {
        document.getElementById('printPreviewEmpty').style.display  = 'flex';
        document.getElementById('printPreviewFrame').style.display  = 'none';
        document.getElementById('printPreviewSpinner').style.display = 'none';
    }

    function _showSpinner() {
        document.getElementById('printPreviewEmpty').style.display  = 'none';
        document.getElementById('printPreviewFrame').style.display  = 'none';
        document.getElementById('printPreviewSpinner').style.display = 'flex';
    }

    function _showFrame() {
        document.getElementById('printPreviewEmpty').style.display  = 'none';
        document.getElementById('printPreviewFrame').style.display  = 'block';
        document.getElementById('printPreviewSpinner').style.display = 'none';
    }

    function _loadTemplates() {
        var list = document.getElementById('printTplList');
        list.innerHTML = '<div class="print-tpl-loading">Завантаження…</div>';

        fetch('/print/api/get_doc_templates?entity_type=' + encodeURIComponent(_entityType))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) {
                    list.innerHTML = '<div class="print-tpl-empty">Помилка завантаження</div>';
                    return;
                }
                _renderList(d.groups);
            })
            .catch(function () {
                list.innerHTML = '<div class="print-tpl-empty">Помилка мережі</div>';
            });
    }

    function _renderList(groups) {
        var list = document.getElementById('printTplList');
        var keys = Object.keys(groups || {});
        if (keys.length === 0) {
            list.innerHTML = '<div class="print-tpl-empty">Активних шаблонів немає.<br>Створіть шаблон у розділі Документи → Шаблони.</div>';
            return;
        }

        var html = '';
        for (var i = 0; i < keys.length; i++) {
            var typeName = keys[i];
            var items    = groups[typeName];
            html += '<div class="print-tpl-group">';
            html += '<div class="print-tpl-type">' + _esc(typeName) + '</div>';
            for (var j = 0; j < items.length; j++) {
                var t = items[j];
                html += '<div class="print-tpl-row" data-id="' + (int(t.id)) + '">'
                      + '<span class="print-tpl-name" title="' + _esc(t.name) + '">' + _esc(t.name) + '</span>'
                      + '<span class="print-tpl-ver">v' + (int(t.version)) + '</span>'
                      + '</div>';
            }
            html += '</div>';
        }
        list.innerHTML = html;

        // Bind clicks
        var rows = list.querySelectorAll('.print-tpl-row');
        for (var k = 0; k < rows.length; k++) {
            (function (row) {
                row.addEventListener('click', function () {
                    // Highlight
                    for (var m = 0; m < rows.length; m++) rows[m].classList.remove('selected');
                    row.classList.add('selected');
                    _loadPreview(int(row.getAttribute('data-id')));
                });
            }(rows[k]));
        }

        // Auto-select first
        if (rows.length > 0) {
            rows[0].click();
        }
    }

    function _loadPreview(tplId) {
        _showSpinner();
        document.getElementById('printDoBtn').disabled = true;

        var params = new URLSearchParams({
            template_id: tplId,
            entity_type: _entityType,
            entity_id:   _entityId,
            org_id:      _orgId
        });

        fetch('/print/api/render_doc', { method: 'POST', body: params })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) {
                    _showEmpty();
                    return;
                }
                var frame = document.getElementById('printPreviewFrame');
                var doc   = frame.contentDocument || frame.contentWindow.document;
                doc.open();
                doc.write(d.html);
                doc.close();
                _showFrame();
                document.getElementById('printDoBtn').disabled = false;
            })
            .catch(function () { _showEmpty(); });
    }

    function doPrint() {
        var frame = document.getElementById('printPreviewFrame');
        if (frame && frame.contentWindow) {
            frame.contentWindow.focus();
            frame.contentWindow.print();
        }
    }

    function int(v) { return parseInt(v, 10) || 0; }
    function _esc(s) {
        return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Close on backdrop click
    document.addEventListener('click', function (e) {
        var overlay = document.getElementById('printModal');
        if (overlay && e.target === overlay) { close(); }
    });

    return { open: open, close: close, doPrint: doPrint };
}());
</script>