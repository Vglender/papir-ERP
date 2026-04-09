(function() {
    var _cpId     = _PAGE.demand.counterparty_id;
    var _demandId = _PAGE.demandId;
    var _num      = _PAGE.demand.number || ('#' + _demandId);
    var _date     = _PAGE.demand.moment
        ? _PAGE.demand.moment.substring(0, 10).split('-').reverse().join('.')
        : '';

    var _menu = null;

    /* ── compose modal state ── */
    var _composeAttachUrl  = null;
    var _composeAttachName = null;

    /* ── Compose modal open / close ── */
    function _openCompose(title, text, attachUrl, attachName) {
        _composeAttachUrl  = attachUrl  || null;
        _composeAttachName = attachName || null;

        document.getElementById('sendComposeTitle').textContent = title || 'Надіслати клієнту';
        document.getElementById('sendComposeText').value = text || '';

        var attachInfo = document.getElementById('sendComposeAttachInfo');
        var attachNameEl = document.getElementById('sendComposeAttachName');
        if (attachUrl) {
            attachNameEl.textContent = attachName || attachUrl;
            attachInfo.style.display = '';
        } else {
            attachInfo.style.display = 'none';
        }

        document.getElementById('sendComposeModal').classList.add('open');
        setTimeout(function() {
            var ta = document.getElementById('sendComposeText');
            ta.focus();
            ta.setSelectionRange(0, 0);
        }, 50);
    }

    function _closeCompose() {
        document.getElementById('sendComposeModal').classList.remove('open');
    }

    document.getElementById('sendComposeClose').addEventListener('click', _closeCompose);
    document.getElementById('sendComposeCancel').addEventListener('click', _closeCompose);
    document.getElementById('sendComposeModal').addEventListener('click', function(e) {
        if (e.target === this) _closeCompose();
    });

    document.getElementById('sendComposeSend').addEventListener('click', function() {
        var text = document.getElementById('sendComposeText').value.trim();
        if (!text) { document.getElementById('sendComposeText').focus(); return; }
        var ch = (document.querySelector('input[name="sendCompCh"]:checked') || {}).value || 'viber';
        _closeCompose();
        ChatModal.open(_cpId, ch, text, _composeAttachUrl, _composeAttachName);
    });

    /* ── Build items text ── */
    function _buildItemsText() {
        var rows = document.querySelectorAll('#positionsTable tbody tr[data-item-row]');
        if (!rows.length) return '';

        var lines = ['Накладна №' + _num + ':'];
        var n = 0;
        var total = 0;

        rows.forEach(function(tr) {
            var nameEl = tr.querySelector('.prod-name-link');
            var name = nameEl ? (nameEl.textContent || nameEl.innerText || '').trim() : '';
            var artEl = tr.querySelector('a[style*="9ca3af"]');
            var art = artEl ? (artEl.textContent || '').trim() : '';
            var qty = parseFloat((tr.querySelector('[data-field="quantity"]') || {}).value) || 0;
            var price = parseFloat((tr.querySelector('[data-field="price"]') || {}).value) || 0;
            var disc = parseFloat((tr.querySelector('[data-field="discount_percent"]') || {}).value) || 0;
            var unit = (tr.querySelector('[data-field="unit"]') || {}).value || 'шт';
            var gross = qty * price;
            var sum = disc > 0 ? gross * (1 - disc / 100) : gross;
            total += sum;
            n++;

            var label = name || art || ('Позиція ' + n);
            var discStr = disc > 0 ? ' (-' + disc + '%)' : '';
            lines.push(n + '. ' + label
                + '\n   ' + qty + ' ' + unit + ' x ' + _fmt2(price) + discStr
                + ' = ' + _fmt2(sum) + ' грн');
        });

        if (!n) return '';
        lines.push('');
        lines.push('РАЗОМ: ' + _fmt2(total) + ' грн');
        return lines.join('\n');
    }

    function _fmt2(v) {
        return parseFloat(v).toFixed(2).replace('.', ',');
    }

    /* ── Generate PDF ── */
    function _generatePdf(templateId, callback) {
        var btn = document.getElementById('btnSendTpl');
        if (btn) { btn.disabled = true; btn.innerHTML = '⏳ Формую…'; }
        var fd = new FormData();
        fd.append('demand_id', _demandId);
        if (templateId) fd.append('template_id', templateId);
        fetch('/print/api/generate_demand_pdf', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (btn) { btn.disabled = false; btn.innerHTML = '📤 Надіслати ▾'; }
                if (!d.ok) { alert('Помилка генерації PDF: ' + (d.error || '')); return; }
                callback(d);
            })
            .catch(function() {
                if (btn) { btn.disabled = false; btn.innerHTML = '📤 Надіслати ▾'; }
                alert('Помилка мережі при генерації PDF');
            });
    }

    /* ── Menu ── */
    function _closeMenu() {
        if (_menu) { _menu.remove(); _menu = null; }
    }

    function _openMenu(anchor) {
        _closeMenu();
        var div = document.createElement('div');
        div.style.cssText = 'position:fixed;z-index:9999;background:#fff;border:1px solid #e5e7eb;border-radius:10px;'
            + 'box-shadow:0 6px 24px rgba(0,0,0,.13);min-width:250px;overflow:hidden;font-family:inherit';
        div.innerHTML =
            '<div style="padding:8px 13px 6px;border-bottom:1px solid #f3f4f6;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px">Надіслати клієнту</div>'
            + '<div id="_sendMenuItems"></div>';

        var items = [
            { icon: '📋', label: 'Склад накладної',           key: 'items_list' },
            { icon: '📄', label: 'Накладна з печаткою (PDF)',  key: 'waybill_stamp' },
            { icon: '📄', label: 'Накладна без печатки (PDF)', key: 'waybill_plain' },
        ];
        var itemsEl = div.querySelector('#_sendMenuItems');
        items.forEach(function(it) {
            var btn = document.createElement('div');
            btn.style.cssText = 'padding:9px 13px;cursor:pointer;display:flex;align-items:center;gap:8px;font-size:13px;color:#1f2937;transition:background .1s';
            btn.innerHTML = '<span style="font-size:16px">' + it.icon + '</span><span>' + it.label + '</span>';
            btn.addEventListener('mouseover', function() { btn.style.background = '#f5f3ff'; });
            btn.addEventListener('mouseout',  function() { btn.style.background = ''; });
            btn.addEventListener('click', function() { _closeMenu(); _runAction(it.key); });
            itemsEl.appendChild(btn);
        });

        document.body.appendChild(div);
        _menu = div;

        var rect = anchor.getBoundingClientRect();
        var menuW = 260;
        var left  = rect.left;
        if (left + menuW > window.innerWidth - 8) left = window.innerWidth - menuW - 8;
        div.style.top  = (rect.bottom + 4) + 'px';
        div.style.left = left + 'px';

        setTimeout(function() {
            document.addEventListener('click', function _cl(e) {
                if (!div.contains(e.target)) { _closeMenu(); document.removeEventListener('click', _cl); }
            });
        }, 10);
    }

    /* ── Template ID lookup ── */
    var _tplIds = {};
    // Load template IDs on init
    fetch('/print/api/get_doc_templates?entity_type=demand')
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.ok) return;
            var groups = d.groups || {};
            Object.keys(groups).forEach(function(typeName) {
                (groups[typeName] || []).forEach(function(t) {
                    _tplIds[t.code] = t.id;
                });
            });
        });

    function _runAction(key) {
        if (key === 'items_list') {
            var text = _buildItemsText();
            _openCompose('Склад накладної', text);
        } else if (key === 'waybill_stamp') {
            var tplId = _tplIds['waybill_stamp'] || 0;
            _generatePdf(tplId, function(d) {
                var text = 'Накладна №' + _num + ' від ' + _date;
                _openCompose('Накладна з печаткою', text, d.url, d.filename);
            });
        } else if (key === 'waybill_plain') {
            var tplId = _tplIds['waybill_plain'] || 0;
            _generatePdf(tplId, function(d) {
                var text = 'Накладна №' + _num + ' від ' + _date;
                _openCompose('Накладна без печатки', text, d.url, d.filename);
            });
        }
    }

    var btn = document.getElementById('btnSendTpl');
    if (btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (_menu) { _closeMenu(); return; }
            _openMenu(btn);
        });
    }
}());
