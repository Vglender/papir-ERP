/**
 * send-templates.js — "Надіслати ▾" dropdown з контекстними шаблонами.
 *
 * Usage:
 *   SendTemplates.bind(document.getElementById('btnSend'), {
 *       cpId:    123,
 *       context: 'order',        // 'order' | 'ttn' | 'any'
 *       vars:    { '{order_number}': '98267OFF', '{status}': 'Підтверджено' },
 *       channel: 'viber'         // optional default channel for ChatModal
 *   });
 *
 * Placeholders in template body: {order_number}, {status}, {ttn_number}, {ttn_status}, etc.
 * Depends on: ChatModal (chat-modal.js) being loaded on the same page.
 */
var SendTemplates = (function() {

    var _dropId          = '_stDrop';
    var _modalId         = '_stModal';
    var _injected        = false;
    var _modalOpts       = null;
    var _currentDropOpts  = null;
    var _currentTemplates = [];

    function _esc(str) {
        return String(str || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function _renderVars(text, vars) {
        var result = text;
        for (var key in vars) {
            if (vars.hasOwnProperty(key)) {
                result = result.split(key).join(vars[key]);
            }
        }
        return result;
    }

    function _ctxLabel(ctx) {
        if (ctx === 'order') return 'Замовлення';
        if (ctx === 'ttn')   return 'ТТН';
        return 'Будь-який';
    }

    function _injectStyles() {
        if (_injected) return;
        _injected = true;
        var s = document.createElement('style');
        s.textContent = [
            '#_stDrop{position:fixed;z-index:9100;background:#fff;border:1px solid #e5e7eb;border-radius:10px;box-shadow:0 6px 24px rgba(0,0,0,.13);min-width:260px;overflow:hidden;display:none}',
            '#_stDrop.st-open{display:block}',
            '.st-drop-head{padding:9px 13px 7px;border-bottom:1px solid #f3f4f6;font-size:12px;font-weight:700;color:#374151;display:flex;align-items:center;justify-content:space-between}',
            '.st-drop-manage{font-size:11px;color:#7c3aed;background:none;border:none;cursor:pointer;font-family:inherit;padding:0}',
            '.st-drop-manage:hover{text-decoration:underline}',
            '.st-drop-list{max-height:240px;overflow-y:auto}',
            '.st-drop-item{padding:8px 13px;cursor:pointer;border-bottom:1px solid #f9fafb;transition:background .1s}',
            '.st-drop-item:last-child{border-bottom:none}',
            '.st-drop-item:hover{background:#f5f3ff}',
            '.st-drop-item-title{font-size:12px;font-weight:600;color:#1f2937;margin-bottom:2px}',
            '.st-drop-item-preview{font-size:11px;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}',
            '.st-drop-empty,.st-drop-loading{padding:16px 13px;font-size:12px;color:#9ca3af;text-align:center}',
            '.st-ctx-badge{display:inline-block;font-size:10px;color:#7c3aed;background:#ede9fe;border-radius:4px;padding:1px 5px;margin-left:5px;vertical-align:middle}',
            '#_stModal{position:fixed;inset:0;z-index:9200;background:rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center}',
            '#_stModal.st-hidden{display:none}',
            '.st-modal-box{background:#fff;border-radius:12px;box-shadow:0 8px 40px rgba(0,0,0,.18);width:100%;max-width:520px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden}',
            '.st-modal-head{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #f0f0f0;font-size:14px;font-weight:700;color:#111827;flex-shrink:0}',
            '.st-modal-close{width:28px;height:28px;border:none;background:transparent;cursor:pointer;font-size:18px;color:#9ca3af;border-radius:5px;display:flex;align-items:center;justify-content:center}',
            '.st-modal-close:hover{background:#fee2e2;color:#dc2626}',
            '.st-modal-body{padding:16px 18px;overflow-y:auto;flex:1}',
            '.st-tm-list{display:flex;flex-direction:column;gap:6px;margin-bottom:12px}',
            '.st-tm-row{display:flex;align-items:flex-start;gap:8px;background:#f9fafb;border-radius:8px;padding:9px 11px}',
            '.st-tm-info{flex:1;min-width:0}',
            '.st-tm-title{font-size:12px;font-weight:600;color:#1f2937}',
            '.st-tm-meta{font-size:10px;color:#9ca3af;margin-top:2px}',
            '.st-tm-preview{font-size:11px;color:#6b7280;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}',
            '.st-tm-acts{display:flex;gap:4px;flex-shrink:0;align-items:center}',
            '.st-tm-btn{background:none;border:1px solid #e5e7eb;border-radius:5px;cursor:pointer;padding:2px 7px;font-size:11px;font-family:inherit;color:#6b7280}',
            '.st-tm-btn:hover{background:#f3f4f6}',
            '.st-tm-btn.del:hover{background:#fee2e2;border-color:#fca5a5;color:#dc2626}',
            '.st-form{border-top:1px solid #f0f0f0;padding-top:14px;margin-top:8px}',
            '.st-form-title{font-size:12px;font-weight:700;color:#374151;margin-bottom:10px}',
            '.st-form-row{margin-bottom:10px}',
            '.st-form-row label{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px}',
            '.st-form-row input[type=text],.st-form-row textarea,.st-form-row select{width:100%;padding:7px 10px;border:1px solid #e5e7eb;border-radius:7px;font-size:13px;font-family:inherit;outline:none;transition:border-color .12s;box-sizing:border-box}',
            '.st-form-row input:focus,.st-form-row textarea:focus,.st-form-row select:focus{border-color:#a78bfa}',
            '.st-chs{display:flex;gap:10px;flex-wrap:wrap;margin:4px 0 2px}',
            '.st-ch{display:flex;align-items:center;gap:4px;font-size:12px;color:#4b5563;cursor:pointer}',
            '.st-form-hint{font-size:11px;color:#9ca3af;margin-top:4px}',
            '.st-form-err{color:#dc2626;font-size:12px;margin-top:6px;display:none}',
            '.st-form-btns{display:flex;gap:8px;justify-content:flex-end;margin-top:10px}',
            '.st-btn{display:inline-flex;align-items:center;gap:5px;padding:0 13px;height:32px;border-radius:7px;border:1px solid #e5e7eb;background:#fff;color:#374151;font-size:12px;font-weight:500;cursor:pointer;font-family:inherit}',
            '.st-btn:hover{background:#f9fafb}',
            '.st-btn-primary{background:#7c3aed;border-color:#7c3aed;color:#fff}',
            '.st-btn-primary:hover{background:#6d28d9}',
        ].join('');
        document.head.appendChild(s);
    }

    // ── Dropdown ───────────────────────────────────────────────────────────────

    function _ensureDropdown() {
        if (document.getElementById(_dropId)) return;
        var el = document.createElement('div');
        el.id = _dropId;
        document.body.appendChild(el);
        // Delegated: manage button click — runs before bubbling reaches document
        el.addEventListener('click', function(e) {
            var btn = e.target.id === '_stDropManage'
                ? e.target : e.target.closest && e.target.closest('#_stDropManage');
            if (btn) {
                e.stopPropagation();
                el.classList.remove('st-open');
                _openModal(_currentDropOpts || {});
                return;
            }
        });
        // Close on outside click
        document.addEventListener('click', function(e) {
            if (!el.classList.contains('st-open')) return;
            if (!el.contains(e.target)) { el.classList.remove('st-open'); }
        });
    }

    function _showDropdown(triggerEl, opts) {
        _currentDropOpts = opts;
        _ensureDropdown();
        var drop = document.getElementById(_dropId);
        drop.innerHTML = '<div class="st-drop-loading">Завантаження\u2026</div>';
        drop.classList.add('st-open');

        var rect = triggerEl.getBoundingClientRect();
        drop.style.top   = (rect.bottom + 4) + 'px';
        drop.style.left  = rect.left + 'px';
        drop.style.right = 'auto';

        var url = '/counterparties/api/get_templates';
        if (opts.context && opts.context !== 'any') {
            url += '?context=' + encodeURIComponent(opts.context);
        }
        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.ok) { drop.innerHTML = '<div class="st-drop-empty">Помилка завантаження</div>'; return; }
                _renderDropdown(drop, data.templates || [], opts);
            })
            .catch(function() { drop.innerHTML = '<div class="st-drop-empty">Помилка мережі</div>'; });
    }

    function _renderDropdown(drop, templates, opts) {
        var html = '<div class="st-drop-head"><span>Шаблон відправки</span>'
            + '<button class="st-drop-manage" id="_stDropManage">\u270f\ufe0f Редагувати</button></div>'
            + '<div class="st-drop-list">';
        if (templates.length === 0) {
            html += '<div class="st-drop-empty">Немає шаблонів. Натисніть «Редагувати» щоб додати.</div>';
        } else {
            for (var i = 0; i < templates.length; i++) {
                var t       = templates[i];
                var preview = _renderVars(t.body, opts.vars || {});
                var badge   = (t.context && t.context !== 'any')
                    ? '<span class="st-ctx-badge">' + _esc(_ctxLabel(t.context)) + '</span>' : '';
                html += '<div class="st-drop-item" data-idx="' + i + '">'
                    + '<div class="st-drop-item-title">' + _esc(t.title) + badge + '</div>'
                    + '<div class="st-drop-item-preview">' + _esc(preview) + '</div>'
                    + '</div>';
            }
        }
        html += '</div>';
        drop.innerHTML = html;

        var items = drop.querySelectorAll('.st-drop-item');
        for (var j = 0; j < items.length; j++) {
            (function(item, tpl) {
                item.addEventListener('click', function() {
                    drop.classList.remove('st-open');
                    var text = _renderVars(tpl.body, opts.vars || {});
                    if (typeof ChatModal !== 'undefined') {
                        ChatModal.open(opts.cpId, opts.channel || 'viber', text);
                    }
                });
            }(items[j], templates[j]));
        }

        // manage button handled by delegated listener in _ensureDropdown
    }

    // ── Modal ──────────────────────────────────────────────────────────────────

    function _ensureModal() {
        if (document.getElementById(_modalId)) return;
        var el = document.createElement('div');
        el.id = _modalId;
        el.className = 'st-hidden';
        el.innerHTML =
            '<div class="st-modal-box">'
            + '<div class="st-modal-head"><span>\u0428\u0430\u0431\u043b\u043e\u043d\u0438 \u043f\u043e\u0432\u0456\u0434\u043e\u043c\u043b\u0435\u043d\u044c</span>'
            + '<button class="st-modal-close" id="_stModalClose">\xd7</button></div>'
            + '<div class="st-modal-body" id="_stModalBody"></div>'
            + '</div>';
        document.body.appendChild(el);

        // Single delegated listener covers all modal interactions
        el.addEventListener('click', function(e) {
            if (e.target === el) { _closeModal(); return; }
            if (e.target.id === '_stModalClose') { _closeModal(); return; }
            // edit button — handled via inline onclick → SendTemplates._editTm(idx)
            // delete button
            var delBtn = e.target.closest ? e.target.closest('[data-del]') : (e.target.getAttribute && e.target.getAttribute('data-del') ? e.target : null);
            if (delBtn) {
                if (confirm('\u0412\u0438\u0434\u0430\u043b\u0438\u0442\u0438 \u0446\u0435\u0439 \u0448\u0430\u0431\u043b\u043e\u043d?')) {
                    _deleteTm(parseInt(delBtn.getAttribute('data-del'), 10));
                }
                return;
            }
            if (e.target.id === '_stTmAddBtn')    { _startEdit(null); return; }
            if (e.target.id === '_stTmCancelBtn') { _cancelEdit(); return; }
            if (e.target.id === '_stTmSaveBtn')   { _saveTm(); return; }
        });
    }

    function _openModal(opts) {
        _ensureModal();
        _modalOpts = opts || {};
        document.getElementById(_modalId).classList.remove('st-hidden');
        _loadModalTemplates();
    }

    function _closeModal() {
        var el = document.getElementById(_modalId);
        if (el) el.classList.add('st-hidden');
    }

    function _loadModalTemplates() {
        var bodyEl = document.getElementById('_stModalBody');
        if (!bodyEl) return;
        bodyEl.innerHTML = '<div style="padding:20px;text-align:center;font-size:13px;color:#9ca3af">Завантаження\u2026</div>';
        fetch('/counterparties/api/get_templates')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.ok) { bodyEl.innerHTML = '<div style="padding:20px;color:#dc2626;font-size:13px">Помилка</div>'; return; }
                _renderModal(bodyEl, data.templates || []);
            })
            .catch(function() { bodyEl.innerHTML = '<div style="padding:20px;color:#dc2626;font-size:13px">Помилка мережі</div>'; });
    }

    function _renderModal(bodyEl, templates) {
        _currentTemplates = templates;
        var html = '<div class="st-tm-list">';
        if (templates.length === 0) {
            html += '<div style="font-size:13px;color:#9ca3af;padding:8px 0">Шаблонів ще немає</div>';
        }
        for (var i = 0; i < templates.length; i++) {
            var t = templates[i];
            html += '<div class="st-tm-row">'
                + '<div class="st-tm-info">'
                + '<div class="st-tm-title">' + _esc(t.title) + '</div>'
                + '<div class="st-tm-meta">' + _esc(t.channels) + ' \xb7 ' + _esc(_ctxLabel(t.context)) + '</div>'
                + '<div class="st-tm-preview">' + _esc(t.body) + '</div>'
                + '</div>'
                + '<div class="st-tm-acts">'
                + '<button class="st-tm-btn" type="button" onclick="SendTemplates._editTm(' + i + ')">\u270f\ufe0f</button>'
                + '<button class="st-tm-btn del" data-del="' + t.id + '">\u2715</button>'
                + '</div></div>';
        }
        html += '</div>'
            + '<button class="st-btn" id="_stTmAddBtn" style="margin-bottom:4px">+ Новий шаблон</button>'
            + '<div class="st-form" id="_stTmForm" style="display:none">'
            + '<div class="st-form-title" id="_stTmFormTitle">Новий шаблон</div>'
            + '<input type="hidden" id="_stTmId" value="0">'
            + '<div class="st-form-row"><label>Назва <span style="color:#ef4444">*</span></label>'
            + '<input type="text" id="_stTmTitle" placeholder="Короткий опис шаблону"></div>'
            + '<div class="st-form-row"><label>Текст <span style="color:#ef4444">*</span></label>'
            + '<textarea id="_stTmBody" rows="4" style="resize:vertical" placeholder="Текст повідомлення\u2026"></textarea>'
            + '<div class="st-form-hint">Плейсхолдери: {order_number}, {status}, {ttn_number}, {ttn_status}</div></div>'
            + '<div class="st-form-row"><label>Канали</label>'
            + '<div class="st-chs">'
            + '<label class="st-ch"><input type="checkbox" name="stch" value="viber" checked> Viber</label>'
            + '<label class="st-ch"><input type="checkbox" name="stch" value="sms"> SMS</label>'
            + '<label class="st-ch"><input type="checkbox" name="stch" value="email"> Email</label>'
            + '<label class="st-ch"><input type="checkbox" name="stch" value="telegram"> Telegram</label>'
            + '<label class="st-ch"><input type="checkbox" name="stch" value="note"> Нотатка</label>'
            + '</div></div>'
            + '<div class="st-form-row"><label>Контекст</label>'
            + '<select id="_stTmCtx"><option value="any">Будь-який</option>'
            + '<option value="order">Замовлення</option><option value="ttn">ТТН</option></select></div>'
            + '<div class="st-form-err" id="_stTmErr"></div>'
            + '<div class="st-form-btns">'
            + '<button class="st-btn" id="_stTmCancelBtn">Скасувати</button>'
            + '<button class="st-btn st-btn-primary" id="_stTmSaveBtn">Зберегти</button>'
            + '</div></div>';

        bodyEl.innerHTML = html;
        // event binding is handled by delegated listener on the modal (see _ensureModal)
    }

    function _startEdit(tpl) {
        var form   = document.getElementById('_stTmForm');
        var addBtn = document.getElementById('_stTmAddBtn');
        if (!form) return;
        if (tpl) {
            document.getElementById('_stTmFormTitle').textContent = 'Редагувати шаблон';
            document.getElementById('_stTmId').value    = tpl.id;
            document.getElementById('_stTmTitle').value = tpl.title;
            document.getElementById('_stTmBody').value  = tpl.body;
            document.getElementById('_stTmCtx').value   = tpl.context || 'any';
            var channels = (tpl.channels || '').split(',');
            var cbs = form.querySelectorAll('input[name="stch"]');
            for (var i = 0; i < cbs.length; i++) { cbs[i].checked = channels.indexOf(cbs[i].value) !== -1; }
        } else {
            document.getElementById('_stTmFormTitle').textContent = 'Новий шаблон';
            document.getElementById('_stTmId').value    = '0';
            document.getElementById('_stTmTitle').value = '';
            document.getElementById('_stTmBody').value  = '';
            document.getElementById('_stTmCtx').value   = (_modalOpts && _modalOpts.context) ? _modalOpts.context : 'any';
            var cbs2 = form.querySelectorAll('input[name="stch"]');
            for (var j = 0; j < cbs2.length; j++) { cbs2[j].checked = (cbs2[j].value === 'viber'); }
        }
        var errEl = document.getElementById('_stTmErr');
        if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
        form.style.display = 'block';
        if (addBtn) addBtn.style.display = 'none';
        // Scroll modal body to show the form (it's at the bottom)
        var modalBody = document.getElementById('_stModalBody');
        if (modalBody) { modalBody.scrollTop = modalBody.scrollHeight; }
        var titleInp = document.getElementById('_stTmTitle');
        if (titleInp) { titleInp.focus(); }
    }

    function _cancelEdit() {
        var form   = document.getElementById('_stTmForm');
        var addBtn = document.getElementById('_stTmAddBtn');
        if (form)   form.style.display = 'none';
        if (addBtn) addBtn.style.display = '';
    }

    function _saveTm() {
        var title  = (document.getElementById('_stTmTitle').value || '').trim();
        var body   = (document.getElementById('_stTmBody').value  || '').trim();
        var ctx    = document.getElementById('_stTmCtx').value;
        var id     = parseInt(document.getElementById('_stTmId').value, 10) || 0;
        var errEl  = document.getElementById('_stTmErr');
        if (!title || !body) {
            errEl.textContent = 'Назва і текст обовʼязкові';
            errEl.style.display = 'block';
            return;
        }
        errEl.style.display = 'none';
        var channels = [];
        var cbs = document.querySelectorAll('#_stTmForm input[name="stch"]');
        for (var i = 0; i < cbs.length; i++) { if (cbs[i].checked) channels.push(cbs[i].value); }
        if (channels.length === 0) channels = ['viber', 'sms'];
        var saveBtn = document.getElementById('_stTmSaveBtn');
        saveBtn.disabled = true;
        var params = 'id=' + id + '&title=' + encodeURIComponent(title)
            + '&body=' + encodeURIComponent(body) + '&context=' + encodeURIComponent(ctx)
            + '&sort_order=0&status=1';
        for (var j = 0; j < channels.length; j++) {
            params += '&channels[]=' + encodeURIComponent(channels[j]);
        }
        fetch('/counterparties/api/save_template', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
        }).then(function(r) { return r.json(); }).then(function(data) {
            saveBtn.disabled = false;
            if (!data.ok) { errEl.textContent = data.error || 'Помилка'; errEl.style.display = 'block'; return; }
            _cancelEdit();
            _loadModalTemplates();
        }).catch(function() {
            saveBtn.disabled = false;
            errEl.textContent = 'Помилка мережі'; errEl.style.display = 'block';
        });
    }

    function _deleteTm(id) {
        fetch('/counterparties/api/delete_template', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + id
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data.ok) { _loadModalTemplates(); }
        });
    }

    // ── Public API ─────────────────────────────────────────────────────────────

    return {
        /**
         * Attach "Надіслати ▾" behaviour to a trigger button.
         * @param {HTMLElement} triggerEl
         * @param {Object}      opts  { cpId, context, vars, channel }
         */
        bind: function(triggerEl, opts) {
            if (!triggerEl) return;
            _injectStyles();
            triggerEl.addEventListener('click', function(e) {
                e.stopPropagation();
                var drop = document.getElementById(_dropId);
                if (drop && drop.classList.contains('st-open')) {
                    drop.classList.remove('st-open');
                    return;
                }
                _showDropdown(triggerEl, opts || {});
            });
        },

        /**
         * Directly show the dropdown anchored to triggerEl (no binding needed).
         * Use this for dynamically-rendered buttons.
         */
        show: function(triggerEl, opts) {
            _injectStyles();
            var drop = document.getElementById(_dropId);
            if (drop && drop.classList.contains('st-open')) {
                drop.classList.remove('st-open');
                return;
            }
            _showDropdown(triggerEl, opts || {});
        },

        /** Called by inline onclick on edit button in template list. */
        _editTm: function(idx) {
            if (_currentTemplates[idx]) { _startEdit(_currentTemplates[idx]); }
        },

        /** Open template manager modal directly. */
        openManager: function(opts) {
            _injectStyles();
            _openModal(opts || {});
        }
    };

}());
