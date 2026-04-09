(function() {
    var _cpId    = _PAGE.cpId;
    var _orderId = _PAGE.orderId;
    var _num     = _PAGE.orderNumber;
    var _sum     = _PAGE.orderSumTotal;
    var _date    = _PAGE.orderDate;

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

    /* ── Build order items text ── */
    function _buildItemsText() {
        var rows = document.querySelectorAll('#positionsTable tbody tr[data-local-id]');
        if (!rows.length) return '';

        var lines = ['Замовлення №' + _num + ':'];
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
            var discStr = disc > 0 ? ' (−' + disc + '%)' : '';
            lines.push(n + '. ' + label
                + '\n   ' + qty + ' ' + unit + ' × ' + _fmt2(price) + discStr
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

    /* ── Menu ── */
    function _closeMenu() {
        if (_menu) { _menu.remove(); _menu = null; }
    }

    function _openMenu(anchor) {
        _closeMenu();
        var div = document.createElement('div');
        div.style.cssText = 'position:fixed;z-index:9999;background:#fff;border:1px solid #e5e7eb;border-radius:10px;'
            + 'box-shadow:0 6px 24px rgba(0,0,0,.13);min-width:230px;overflow:hidden;font-family:inherit';
        div.innerHTML =
            '<div style="padding:8px 13px 6px;border-bottom:1px solid #f3f4f6;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px">Надіслати клієнту</div>'
            + '<div id="_sendMenuItems"></div>';

        var items = [
            { icon: '📋', label: 'Склад замовлення',    key: 'items_list' },
            { icon: '💳', label: 'Посилання на оплату', key: 'pay' },
            { icon: '📄', label: 'Рахунок (PDF-файл)',   key: 'invoice' },
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
        var menuW = 234;
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

    function _generatePdf(callback) {
        var btn = document.getElementById('btnSendTpl');
        if (btn) { btn.disabled = true; btn.innerHTML = '⏳ Формую…'; }
        var fd = new FormData();
        fd.append('order_id', _orderId);
        fetch('/print/api/generate_order_pdf', { method: 'POST', body: fd })
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

    function _runAction(key) {
        if (key === 'items_list') {
            var text = _buildItemsText();
            _openCompose('Склад замовлення', text);
        } else if (key === 'pay') {
            _generatePdf(function(d) {
                var text = 'Рахунок №' + _num + ' від ' + _date + ':\n' + d.url;
                _openCompose('Посилання на оплату', text);
            });
        } else if (key === 'invoice') {
            _generatePdf(function(d) {
                var text = 'Рахунок №' + _num + ' від ' + _date;
                _openCompose('Рахунок (PDF)', text, d.url, d.filename);
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

/* ══ SHIPMENTS PANEL ══ */
var ShipmentsPanel = (function() {
    var _orderId = 0;

    var TN_STATUS = {
        draft:      { cls: 'ttn-status-created',    label: 'Чернетка' },
        created:    { cls: 'ttn-status-created',    label: 'Створено' },
        in_transit: { cls: 'ttn-status-in_transit', label: 'В дорозі' },
        at_branch:  { cls: 'ttn-status-at_branch',  label: 'У відділенні' },
        delivered:  { cls: 'ttn-status-delivered',  label: 'Доставлено' },
        returned:   { cls: 'ttn-status-returned',   label: 'Повернення' },
        refused:    { cls: 'ttn-status-returned',   label: 'Відмова' },
        deleted:    { cls: 'ttn-status-deleted',     label: 'Видалено' },
    };
    var ND_STATUS = {
        pending:   { cls: 'nd-status-pending',   label: 'Очікує' },
        sent:      { cls: 'nd-status-sent',       label: 'Відправлено' },
        delivered: { cls: 'nd-status-delivered',  label: 'Доставлено' },
        cancelled: { cls: 'nd-status-cancelled',  label: 'Скасовано' },
    };
    var DEMAND_STATUS = {
        new:        { cls: 'badge-blue',   label: 'Нове' },
        assembling: { cls: 'badge-orange', label: 'Збирається' },
        assembled:  { cls: 'badge-indigo', label: 'Зібрано' },
        shipped:    { cls: 'badge-green',  label: 'Відвантажено' },
        arrived:    { cls: 'badge-teal',   label: 'Отримано' },
        transfer:   { cls: 'badge-purple', label: 'Передача' },
        robot:      { cls: 'badge-gray',   label: 'Авто' },
        cancelled:  { cls: 'badge-red',    label: 'Скасовано' },
    };

    function ttnStatus(t) {
        if (t.deletion_mark) return 'deleted';
        var def = parseInt(t.state_define, 10);
        if (def === 9)                                               return 'delivered';
        if (def === 7 || def === 8 || def === 105)                   return 'at_branch';
        if (def === 4 || def === 5 || def === 6 || def === 41
            || def === 101 || def === 104)                           return 'in_transit';
        if (def === 10 || def === 11 || def === 103)                 return 'returned';
        if (def === 102 || def === 106)                              return 'refused';
        if (def === 2 || def === 3)                                  return 'deleted';
        if (def === 1)                                               return 'draft';
        return 'created';
    }

    function badge(cls, label) {
        return '<span class="badge ' + cls + '" style="font-size:11px;">' + esc(label) + '</span>';
    }

    function esc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function fmt2(n) {
        return parseFloat(n || 0).toFixed(2).replace(/\.?0+$/, function(m, o, s){ return s.replace(/\./,'') === s.replace(/[^.]/g,'').repeat(s.length) ? '' : m; });
    }

    function renderTtn(t) {
        var st = ttnStatus(t);
        var stInfo = TN_STATUS[st] || TN_STATUS.created;
        var num = t.int_doc_number || ('#' + t.id);

        var meta = [];
        if (t.recipient_contact_person) meta.push(esc(t.recipient_contact_person));
        if (t.city_recipient_desc)       meta.push('📍 ' + esc(t.city_recipient_desc));
        if (t.cost_on_site && parseFloat(t.cost_on_site) > 0) meta.push('Доставка: ' + parseFloat(t.cost_on_site).toFixed(0) + ' ₴');
        if (t.backward_delivery_money && parseFloat(t.backward_delivery_money) > 0) meta.push('НП: ' + parseFloat(t.backward_delivery_money).toFixed(0) + ' ₴');
        if (t.state_name) meta.push('<i style="color:#6b7280">' + esc(t.state_name) + '</i>');

        var trackUrl = 'https://track.novaposhta.ua/#/' + encodeURIComponent(num);

        return '<div class="shipment-card">'
            + '<div class="shipment-card-icon">🚚</div>'
            + '<div class="shipment-card-body">'
            + '<div class="shipment-card-title">'
            + badge(stInfo.cls, stInfo.label)
            + '<span class="shipment-card-num">' + esc(num) + '</span>'
            + '</div>'
            + (meta.length ? '<div class="shipment-card-meta">' + meta.join(' · ') + '</div>' : '')
            + '<div class="shipment-card-acts">'
            + '<a href="' + trackUrl + '" target="_blank" class="btn btn-xs">Трекінг ↗</a>'
            + '</div>'
            + '</div>'
            + '</div>';
    }

    function renderDelivery(d) {
        var st = d.status || 'pending';
        var stInfo = ND_STATUS[st] || ND_STATUS.pending;
        var icon = d.code === 'pickup' ? '🏠' : '🚐';
        var meta = [];
        if (d.comment) meta.push(esc(d.comment));

        return '<div class="shipment-card" data-nd-id="' + (int(d.id)) + '">'
            + '<div class="shipment-card-icon">' + icon + '</div>'
            + '<div class="shipment-card-body">'
            + '<div class="shipment-card-title">'
            + badge(stInfo.cls, stInfo.label)
            + '<span>' + esc(d.name_uk) + '</span>'
            + '</div>'
            + (meta.length ? '<div class="shipment-card-meta">' + meta.join(' · ') + '</div>' : '')
            + '<div class="shipment-card-acts">'
            + '<button type="button" class="btn btn-xs nd-edit-btn">Редагувати</button>'
            + '</div>'
            + '</div>'
            + '</div>';
    }

    function renderDemand(d) {
        var st = d.status || 'new';
        var stInfo = DEMAND_STATUS[st] || { cls: 'badge-gray', label: st };
        var num = d.number || ('#' + d.id);
        var meta = [];
        if (d.moment) {
            var p = String(d.moment).split(' ')[0].split('-');
            if (p.length >= 3) meta.push(p[2] + '.' + p[1] + '.' + p[0]);
        }
        if (d.sum_total && parseFloat(d.sum_total) > 0) meta.push(parseFloat(d.sum_total).toFixed(2).replace(/\.00$/, '') + ' ₴');
        var url = '/demand/edit?id=' + int(d.id);
        return '<div class="shipment-card">'
            + '<div class="shipment-card-icon">📋</div>'
            + '<div class="shipment-card-body">'
            + '<div class="shipment-card-title">'
            + badge(stInfo.cls, stInfo.label)
            + '<span class="shipment-card-num">' + esc(num) + '</span>'
            + '</div>'
            + (meta.length ? '<div class="shipment-card-meta">' + meta.join(' · ') + '</div>' : '')
            + '<div class="shipment-card-acts">'
            + '<a href="' + url + '" class="btn btn-xs">Відкрити ↗</a>'
            + '<button type="button" class="btn btn-xs" onclick="PackPrint.open(' + int(d.id) + ')">📦 Пакет</button>'
            + '</div>'
            + '</div>'
            + '</div>';
    }

    function int(v) { return parseInt(v, 10) || 0; }

    function render(data) {
        var list    = document.getElementById('shipments-list');
        var loading = document.getElementById('shipments-loading');
        var empty   = document.getElementById('shipments-empty');
        if (!list) return;
        loading.style.display = 'none';

        var html = '';
        (data.ttns      || []).forEach(function(t) { html += renderTtn(t); });
        (data.deliveries|| []).forEach(function(d) { html += renderDelivery(d); });

        if (!html) {
            empty.style.display = '';
            list.innerHTML = '';
        } else {
            empty.style.display = 'none';
            list.innerHTML = html;

            // bind "Редагувати" buttons on delivery cards
            list.querySelectorAll('.nd-edit-btn').forEach(function(btn) {
                var card = btn.closest('.shipment-card');
                var ndId = int(card.dataset.ndId);
                var match = (data.deliveries || []).filter(function(d){ return int(d.id) === ndId; })[0];
                if (match) btn.addEventListener('click', function(){ DeliveryModal.open(match); });
            });
        }
    }

    function load(orderId) {
        _orderId = orderId;
        if (!orderId) return;
        var loading = document.getElementById('shipments-loading');
        var empty   = document.getElementById('shipments-empty');
        var list    = document.getElementById('shipments-list');
        if (!loading) return;
        loading.style.display = '';
        empty.style.display = 'none';
        if (list) list.innerHTML = '';

        fetch('/customerorder/api/get_order_shipments?order_id=' + orderId)
            .then(function(r){ return r.json(); })
            .then(function(data) {
                if (data.ok) render(data);
                else {
                    loading.style.display = 'none';
                    empty.style.display = '';
                }
            })
            .catch(function() {
                loading.style.display = 'none';
                empty.style.display = '';
            });
    }

    function reload() { load(_orderId); }

    // bind toolbar buttons
    document.addEventListener('DOMContentLoaded', function() {
        var ttnBtn = document.getElementById('newTtnNpBtn');
        if (ttnBtn) ttnBtn.addEventListener('click', function() { NpTtnModal.open(window._orderId); });
        var ndBtn  = document.getElementById('newDeliveryBtn');
        if (ndBtn)  ndBtn.addEventListener('click', function() { DeliveryModal.open(null); });
        var shareBtn = document.getElementById('btnShareOrder');
        if (shareBtn) shareBtn.addEventListener('click', function() {
            ShareOrder.open({
                orderId: _PAGE.orderId,
                orderNumber: _PAGE.orderNumber,
                orderDate: _PAGE.orderMoment,
                orderSum: _PAGE.orderSum,
                counterpartyId: _PAGE.cpId
            });
        });
    });

    return { load: load, reload: reload };
}());

/* ══ DELIVERY (PICKUP/COURIER) MODAL ══ */
var DeliveryModal = (function() {
    var _orderId = _PAGE.orderId;

    function open(existing, preselectedMethodId) {
        var modal    = document.getElementById('newDeliveryModal');
        var title    = document.getElementById('newDeliveryModalTitle');
        var idEl     = document.getElementById('ndDeliveryId');
        var methodEl = document.getElementById('ndMethodId');
        var statusEl = document.getElementById('ndStatus');
        var commentEl= document.getElementById('ndComment');
        var errEl    = document.getElementById('ndError');
        if (!modal) return;

        errEl.style.display = 'none';
        if (existing) {
            title.textContent      = 'Редагувати відправлення';
            idEl.value             = existing.id;
            if (methodEl) methodEl.value = existing.delivery_method_id;
            if (statusEl) statusEl.value = existing.status;
            if (commentEl) commentEl.value = existing.comment || '';
        } else {
            title.textContent = 'Нове відправлення';
            idEl.value = '0';
            if (preselectedMethodId && methodEl) methodEl.value = preselectedMethodId;
            if (statusEl) statusEl.value = 'pending';
            if (commentEl) commentEl.value = '';
        }
        modal.classList.add('open');
    }

    function close() {
        var modal = document.getElementById('newDeliveryModal');
        if (modal) modal.classList.remove('open');
    }

    document.addEventListener('DOMContentLoaded', function() {
        var closeBtn  = document.getElementById('newDeliveryModalClose');
        var cancelBtn = document.getElementById('ndCancelBtn');
        var saveBtn   = document.getElementById('ndSaveBtn');
        var errEl     = document.getElementById('ndError');
        if (closeBtn)  closeBtn.addEventListener('click', close);
        if (cancelBtn) cancelBtn.addEventListener('click', close);

        if (saveBtn) saveBtn.addEventListener('click', function() {
            var idEl     = document.getElementById('ndDeliveryId');
            var methodEl = document.getElementById('ndMethodId');
            var statusEl = document.getElementById('ndStatus');
            var commentEl= document.getElementById('ndComment');
            if (!methodEl || !methodEl.value) {
                errEl.textContent = 'Оберіть спосіб доставки'; errEl.style.display = ''; return;
            }
            saveBtn.disabled = true;
            var body = 'customerorder_id=' + _orderId
                + '&id='                  + (idEl ? idEl.value : '0')
                + '&delivery_method_id='  + encodeURIComponent(methodEl.value)
                + '&status='              + encodeURIComponent(statusEl ? statusEl.value : 'pending')
                + '&comment='             + encodeURIComponent(commentEl ? commentEl.value : '');
            fetch('/customerorder/api/save_order_delivery', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body
            }).then(function(r){ return r.json(); }).then(function(res) {
                saveBtn.disabled = false;
                if (!res.ok) { errEl.textContent = res.error || 'Помилка'; errEl.style.display = ''; return; }
                close();
                ShipmentsPanel.reload();
                showToast('Збережено ✓');
            }).catch(function() { saveBtn.disabled = false; errEl.textContent = 'Помилка з\'єднання'; errEl.style.display = ''; });
        });
    });

    return { open: open, close: close };
}());

/* ══ NP TTN CREATE MODAL ══ */
var NpTtnModal = (function() {
    var _orderId = 0;
    var _prefillData = null;

    function esc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function open(orderId) {
        _orderId = orderId;
        var modal = document.getElementById('newTtnModal');
        var body  = document.getElementById('npTtnBody');
        if (!modal || !body) return;
        body.innerHTML = '<div style="text-align:center;color:#9ca3af;padding:30px;">Завантаження…</div>';
        modal.classList.add('open');

        fetch('/novaposhta/api/get_ttn_form?order_id=' + orderId)
            .then(function(r){ return r.json(); })
            .then(function(res) {
                if (!res.ok) { body.innerHTML = '<div style="color:#dc2626;padding:16px">' + esc(res.error || 'Помилка') + '</div>'; return; }
                _prefillData = res.data;
                renderForm(res.data, body);
            })
            .catch(function() { body.innerHTML = '<div style="color:#dc2626;padding:16px">Помилка завантаження</div>'; });
    }

    function close() {
        var modal = document.getElementById('newTtnModal');
        if (modal) modal.classList.remove('open');
    }

    function renderForm(data, body) {
        var senders   = data.senders   || [];
        var recipient = data.recipient || {};

        var sOpts = senders.map(function(s) {
            var sel = (s.Ref === data.sender_ref) ? ' selected' : '';
            return '<option value="' + esc(s.Ref) + '"' + sel + '>' + esc(s.Description) + '</option>';
        }).join('');

        // ServiceType auto-detected from sender address + recipient delivery

        var html = '';

        // Sender
        html += '<div class="np-form-section">Відправник</div>';
        html += '<label class="np-field-label" style="margin-top:0">Відправник</label>';
        html += '<select id="npSenderRef" class="np-inp" style="height:auto;padding:4px 7px;">' + sOpts + '</select>';
        html += '<label class="np-field-label">Адреса відправки</label>';
        html += '<select id="npSenderAddr" class="np-inp" style="height:auto;padding:4px 7px;"><option value="">Завантаження…</option></select>';

        // Recipient
        var isOrg = (recipient.counterparty_type === 'Organization');
        html += '<div class="np-form-section">Одержувач</div>';
        html += '<div style="display:flex;gap:12px;margin-bottom:8px">';
        html += '<label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:13px"><input type="radio" name="npRcpType" value="PrivatePerson"' + (!isOrg?' checked':'') + '> Фізична особа</label>';
        html += '<label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:13px"><input type="radio" name="npRcpType" value="Organization"' + (isOrg?' checked':'') + '> Юридична особа</label>';
        html += '</div>';
        // PrivatePerson fields
        html += '<div id="npRcpPersonFields"' + (isOrg?' style="display:none"':'') + '>';
        html += '<div class="np-2col">';
        html += '<div><label class="np-field-label" style="margin-top:0">Прізвище</label><input type="text" id="npRcpLast" class="np-inp" value="' + esc(recipient.last_name||'') + '"></div>';
        html += '<div><label class="np-field-label" style="margin-top:0">Ім\'я</label><input type="text" id="npRcpFirst" class="np-inp" value="' + esc(recipient.first_name||'') + '"></div>';
        html += '</div></div>';
        // Organization fields
        html += '<div id="npRcpOrgFields"' + (!isOrg?' style="display:none"':'') + '>';
        html += '<label class="np-field-label" style="margin-top:0">Назва організації</label>';
        html += '<input type="text" id="npRcpOrgName" class="np-inp" value="' + esc(recipient.full_name||'') + '" placeholder="ТОВ «Назва»">';
        html += '<div class="np-2col" style="margin-top:6px">';
        html += '<div><label class="np-field-label" style="margin-top:0">ЄДРПОУ</label><input type="text" id="npRcpEdrpou" class="np-inp" value="' + esc(recipient.edrpou||'') + '" placeholder="12345678" maxlength="10"></div>';
        html += '<div><label class="np-field-label" style="margin-top:0">Контактна особа</label><input type="text" id="npRcpContactPerson" class="np-inp" value="' + esc(recipient.contact_person||'') + '" placeholder="Прізвище Ім\'я"></div>';
        html += '</div></div>';
        html += '<label class="np-field-label">Телефон</label>';
        html += '<input type="text" id="npRcpPhone" class="np-inp" value="' + esc(recipient.phone||'') + '" placeholder="0671234567">';

        html += '<label class="np-field-label">Місто одержувача</label>';
        html += '<div class="np-ac-wrap"><input type="text" id="npCityInput" class="np-inp" value="' + esc(recipient.city_hint||'') + '" placeholder="Введіть місто…" autocomplete="off">';
        html += '<input type="hidden" id="npCityRef" value="' + esc(recipient.city_ref||'') + '"><div class="np-ac-dd" id="npCityDd"></div></div>';

        html += '<div id="npWhSection"><label class="np-field-label">Відділення / Поштомат</label>';
        html += '<div class="np-ac-wrap"><input type="text" id="npWhInput" class="np-inp" value="' + esc(recipient.np_warehouse_desc||recipient.address_hint||'') + '" placeholder="Відділення або поштомат…" autocomplete="off">';
        html += '<input type="hidden" id="npWhRef" value="' + esc(recipient.np_warehouse_ref||'') + '"><div class="np-ac-dd" id="npWhDd"></div></div></div>';

        html += '<div id="npAddrSection" style="display:none"><label class="np-field-label">Вулиця</label>';
        html += '<div class="np-ac-wrap"><input type="text" id="npStreetInput" class="np-inp" placeholder="Вулиця…" autocomplete="off">';
        html += '<input type="hidden" id="npStreetRef" value=""><div class="np-ac-dd" id="npStreetDd"></div></div>';
        html += '<div class="np-2col" style="margin-top:6px"><div><label class="np-field-label" style="margin-top:0">Будинок</label><input type="text" id="npBuilding" class="np-inp"></div>';
        html += '<div><label class="np-field-label" style="margin-top:0">Квартира</label><input type="text" id="npFlat" class="np-inp"></div></div></div>';

        // Cargo
        html += '<div class="np-form-section">Вантаж</div>';
        html += '<label class="np-field-label" style="margin-top:0">Доставка одержувачу</label>';
        html += '<select id="npRecipientDelivery" class="np-inp" style="height:auto;padding:4px 7px;"><option value="Warehouse">На відділення / поштомат</option><option value="Doors">На адресу</option></select>';
        html += '<input type="hidden" id="npServiceType" value="WarehouseWarehouse">';
        html += '<div id="npServiceTypeInfo" style="font-size:11px;color:#9ca3af;margin-top:4px"></div>';
        html += '<div class="np-2col" style="margin-top:8px">';
        html += '<div><label class="np-field-label" style="margin-top:0">Вага (кг)</label><input type="number" id="npWeight" class="np-inp" value="0.5" step="0.1" min="0.1"></div>';
        html += '<div><label class="np-field-label" style="margin-top:0">Місць</label><input type="number" id="npSeats" class="np-inp" value="1" step="1" min="1"></div>';
        html += '</div>';
        html += '<div id="npSeatsDetail" style="margin-top:8px"><label class="np-field-label" style="margin-top:0">Розміри місць</label><div id="npSeatsRows"></div></div>';
        html += '<label class="np-field-label">Опис вантажу</label><input type="text" id="npDesc" class="np-inp" value="' + esc(data.description_hint || 'Канцтовари') + '" maxlength="36">';
        html += '<label class="np-field-label">Додаткова інформація</label><input type="text" id="npAddInfo" class="np-inp" value="' + esc(data.additional_info_hint || '') + '">';
        var declaredVal = Math.max(Math.ceil(parseFloat(data.sum_total) || 1), Math.ceil(parseFloat(data.backward_money_hint) || 0), 1);
        html += '<div class="np-2col">';
        html += '<div><label class="np-field-label">Оголошена вартість (грн)</label><input type="number" id="npCost" class="np-inp" value="' + declaredVal + '" min="1"></div>';
        html += '<div><label class="np-field-label">Наклад. платіж (грн)</label><input type="number" id="npBackMoney" class="np-inp" value="' + esc(data.backward_money_hint || 0) + '" min="0" step="0.01"></div>';
        html += '</div>';

        // Payment
        html += '<div class="np-form-section">Оплата</div>';
        html += '<div class="np-2col">';
        html += '<div><label class="np-field-label" style="margin-top:0">Платник</label>'
              + '<select id="npPayerType" class="np-inp" style="height:auto;padding:4px 7px;">'
              + '<option value="Recipient">Одержувач</option><option value="Sender">Відправник</option></select></div>';
        html += '<div><label class="np-field-label" style="margin-top:0">Спосіб оплати</label>'
              + '<select id="npPayMethod" class="np-inp" style="height:auto;padding:4px 7px;">'
              + '<option value="Cash">Готівка</option><option value="NonCash">Безготівка</option></select></div>';
        html += '</div>';
        html += '<label class="np-field-label">Дата відправки</label>';
        html += '<input type="date" id="npDate" class="np-inp" value="' + (function(){ var d=new Date(); return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); })() + '">';

        html += '<div id="npTtnError" class="np-form-error"></div>';

        html += '<div style="display:flex;gap:8px;margin-top:14px;padding-top:12px;border-top:1px solid var(--border)">';
        html += '<button type="button" class="btn btn-primary btn-sm" id="npTtnSubmitBtn">Створити ТТН</button>';
        html += '<button type="button" class="btn btn-sm" id="npTtnCancelBtn">Скасувати</button>';
        html += '</div>';

        body.innerHTML = html;
        bindFormEvents(data);
    }

    function bindFormEvents(data) {
        document.getElementById('npTtnCancelBtn').addEventListener('click', close);

        // ── Recipient type toggle (PrivatePerson / Organization)
        var rcpTypeRadios = document.querySelectorAll('input[name="npRcpType"]');
        function toggleRcpType() {
            var sel = document.querySelector('input[name="npRcpType"]:checked');
            var isOrg = sel && sel.value === 'Organization';
            document.getElementById('npRcpPersonFields').style.display = isOrg ? 'none' : '';
            document.getElementById('npRcpOrgFields').style.display = isOrg ? '' : 'none';
        }
        rcpTypeRadios.forEach(function(r) { r.addEventListener('change', toggleRcpType); });

        // ── Service type auto-detection
        var _senderAddrType = 'street';
        var rcpDeliverySel = document.getElementById('npRecipientDelivery');
        var stHidden = document.getElementById('npServiceType');
        var stInfo = document.getElementById('npServiceTypeInfo');
        function updateServiceType() {
            var sP = (_senderAddrType === 'warehouse') ? 'Warehouse' : 'Doors';
            var rP = rcpDeliverySel.value;
            stHidden.value = sP + rP;
            var lb = {WarehouseWarehouse:'Відділення → Відділення',WarehouseDoors:'Відділення → Адреса',DoorsWarehouse:'Адреса → Відділення',DoorsDoors:'Адреса → Адреса'};
            stInfo.textContent = lb[stHidden.value] || stHidden.value;
            var isRA = (rP === 'Doors');
            document.getElementById('npWhSection').style.display = isRA ? 'none' : '';
            document.getElementById('npAddrSection').style.display = isRA ? '' : 'none';
        }
        rcpDeliverySel.addEventListener('change', updateServiceType);
        updateServiceType();

        // ── Sender addresses
        function loadSenderAddresses(senderRef) {
            var addrSel = document.getElementById('npSenderAddr');
            if (!addrSel) return;
            addrSel.innerHTML = '<option value="">Завантаження…</option>';
            fetch('/novaposhta/api/get_senders?sender_ref=' + encodeURIComponent(senderRef))
                .then(function(r){ return r.json(); })
                .then(function(res) {
                    if (!res.ok || !res.addresses || !res.addresses.length) {
                        addrSel.innerHTML = '<option value="">Адреси не знайдено</option>'; return;
                    }
                    addrSel.innerHTML = res.addresses.map(function(a) {
                        var s = (a.is_default === 1 || a.is_default === '1') ? ' selected' : '';
                        return '<option value="' + esc(a.Ref) + '"' + s + ' data-city="' + esc(a.CityRef||'') + '" data-city-desc="' + esc(a.CityDescription||'') + '" data-type="' + esc(a.address_type||'street') + '">' + esc(a.Description||a.Ref) + '</option>';
                    }).join('');
                    onSenderAddrChange();
                });
        }
        function onSenderAddrChange() {
            var addrSel = document.getElementById('npSenderAddr');
            if (!addrSel || addrSel.selectedIndex < 0) return;
            var opt = addrSel.options[addrSel.selectedIndex];
            _senderAddrType = (opt && opt.dataset.type) ? opt.dataset.type : 'street';
            updateServiceType();
        }
        document.getElementById('npSenderAddr').addEventListener('change', onSenderAddrChange);
        var senderSel = document.getElementById('npSenderRef');
        if (senderSel.value) loadSenderAddresses(senderSel.value);
        senderSel.addEventListener('change', function(){ loadSenderAddresses(this.value); });

        // ── Per-seat dimensions
        var seatsInput = document.getElementById('npSeats'), seatsDetail = document.getElementById('npSeatsDetail'), seatsRows = document.getElementById('npSeatsRows'), weightInput = document.getElementById('npWeight');
        function renderSeatsRows() {
            var n = parseInt(seatsInput.value) || 1;
            seatsDetail.style.display = '';
            var perW = Math.round((parseFloat(weightInput.value) || 0.5) / n * 100) / 100;
            var html = '<div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:4px;font-size:11px;color:#9ca3af;margin-bottom:4px"><span>Вага</span><span>Довж</span><span>Шир</span><span>Вис</span><span>Ручна</span></div>';
            for (var i = 0; i < n; i++) {
                var ex = seatsRows.querySelector('.seat-row[data-idx="' + i + '"]');
                var w=ex?ex.querySelector('.seat-w').value:perW, l=ex?ex.querySelector('.seat-l').value:'', wi=ex?ex.querySelector('.seat-wi').value:'', h=ex?ex.querySelector('.seat-h').value:'', m=ex?ex.querySelector('.seat-m').checked:false;
                html += '<div class="seat-row" data-idx="'+i+'" style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:4px;margin-bottom:3px;align-items:center"><input type="number" class="np-inp seat-w" value="'+esc(w)+'" step="0.1" min="0.1" placeholder="кг"><input type="number" class="np-inp seat-l" value="'+esc(l)+'" step="1" min="0" placeholder="см"><input type="number" class="np-inp seat-wi" value="'+esc(wi)+'" step="1" min="0" placeholder="см"><input type="number" class="np-inp seat-h" value="'+esc(h)+'" step="1" min="0" placeholder="см"><input type="checkbox" class="seat-m" title="Ручна обробка"'+(m?' checked':'') +' style="width:16px;height:16px;cursor:pointer"></div>';
            }
            seatsRows.innerHTML = html;
        }
        seatsInput.addEventListener('change', renderSeatsRows);
        seatsInput.addEventListener('input', renderSeatsRows);
        renderSeatsRows();

        // ── Declared value >= COD enforcement
        var costInput = document.getElementById('npCost');
        var backMoneyInput = document.getElementById('npBackMoney');
        function enforceDeclaredMin() {
            var cod = Math.ceil(parseFloat(backMoneyInput.value) || 0);
            var cur = parseInt(costInput.value) || 1;
            if (cod > cur) costInput.value = cod;
        }
        backMoneyInput.addEventListener('change', enforceDeclaredMin);
        backMoneyInput.addEventListener('input', enforceDeclaredMin);

        // Autocomplete helper
        function makeAc(inpId, ddId, hiddenId, fetchFn, renderFn) {
            var inp    = document.getElementById(inpId);
            var dd     = document.getElementById(ddId);
            var hidden = document.getElementById(hiddenId);
            if (!inp || !dd || !hidden) return;
            var timer;
            inp.addEventListener('input', function() {
                clearTimeout(timer);
                var q = inp.value.trim();
                if (q.length < 2) { dd.style.display = 'none'; return; }
                timer = setTimeout(function() {
                    fetchFn(q, function(items) {
                        if (!items.length) { dd.style.display = 'none'; return; }
                        dd.innerHTML = items.slice(0, 15).map(function(item) {
                            var r = renderFn(item);
                            return '<div class="np-ac-item" data-ref="' + esc(item.Ref) + '" data-label="' + esc(r.label) + '">'
                                + esc(r.label) + (r.sub ? '<div class="np-ac-sub">' + esc(r.sub) + '</div>' : '') + '</div>';
                        }).join('');
                        dd.style.display = 'block';
                    });
                }, 280);
            });
            dd.addEventListener('mousedown', function(e) {
                var item = e.target.closest('.np-ac-item');
                if (!item) return;
                inp.value    = item.dataset.label;
                hidden.value = item.dataset.ref;
                dd.style.display = 'none';
                inp.dispatchEvent(new Event('np-selected', { bubbles: true }));
            });
            document.addEventListener('click', function(e) {
                if (!inp.contains(e.target) && !dd.contains(e.target)) dd.style.display = 'none';
            });
        }

        var curSenderRef = function() { return (document.getElementById('npSenderRef') || {}).value || ''; };

        makeAc('npCityInput', 'npCityDd', 'npCityRef',
            function(q, cb) {
                fetch('/novaposhta/api/search_city?q=' + encodeURIComponent(q) + '&sender_ref=' + encodeURIComponent(curSenderRef()))
                    .then(function(r){ return r.json(); }).then(function(res){ cb(res.cities||[]); });
            },
            function(c) { return { label: c.Description, sub: c.SettlementTypeDescription || '' }; }
        );
        document.getElementById('npCityInput').addEventListener('np-selected', function() {
            var wh = document.getElementById('npWhInput');
            var ws = document.getElementById('npWhRef');
            var si = document.getElementById('npStreetInput');
            var sr = document.getElementById('npStreetRef');
            if (wh) wh.value = ''; if (ws) ws.value = '';
            if (si) si.value = ''; if (sr) sr.value = '';
        });

        makeAc('npWhInput', 'npWhDd', 'npWhRef',
            function(q, cb) {
                var cityRef = (document.getElementById('npCityRef') || {}).value || '';
                if (!cityRef) { cb([]); return; }
                fetch('/novaposhta/api/search_warehouse?city_ref=' + encodeURIComponent(cityRef)
                    + '&q=' + encodeURIComponent(q) + '&sender_ref=' + encodeURIComponent(curSenderRef()))
                    .then(function(r){ return r.json(); }).then(function(res){ cb(res.warehouses||[]); });
            },
            function(w) { return { label: 'Відд. №' + w.Number + (w.ShortAddress ? ': ' + w.ShortAddress : ''), sub: w.Description }; }
        );

        makeAc('npStreetInput', 'npStreetDd', 'npStreetRef',
            function(q, cb) {
                var cityRef = (document.getElementById('npCityRef') || {}).value || '';
                if (!cityRef) { cb([]); return; }
                fetch('/novaposhta/api/search_street?city_ref=' + encodeURIComponent(cityRef)
                    + '&q=' + encodeURIComponent(q) + '&sender_ref=' + encodeURIComponent(curSenderRef()))
                    .then(function(r){ return r.json(); }).then(function(res){ cb(res.streets||[]); });
            },
            function(s) { return { label: s.Description, sub: s.StreetsType || '' }; }
        );

        // Submit
        document.getElementById('npTtnSubmitBtn').addEventListener('click', function() {
            var btn    = this;
            var errDiv = document.getElementById('npTtnError');
            errDiv.style.display = 'none';

            var senderRef   = (document.getElementById('npSenderRef')   || {}).value || '';
            var addrSel     = document.getElementById('npSenderAddr');
            var senderAddr  = addrSel ? addrSel.value : '';
            var cityRcpRef  = (document.getElementById('npCityRef')     || {}).value || '';
            var cityRcpDesc = (document.getElementById('npCityInput')   || {}).value || '';
            var phone       = ((document.getElementById('npRcpPhone')   || {}).value || '').trim();
            var weight      = parseFloat((document.getElementById('npWeight') || {}).value) || 0;
            var serviceType = (document.getElementById('npServiceType') || {}).value || 'WarehouseWarehouse';
            var whRef       = (document.getElementById('npWhRef')       || {}).value || '';

            if (!senderRef)   { errDiv.textContent = 'Оберіть відправника';         errDiv.style.display = 'block'; return; }
            if (!cityRcpRef)  { errDiv.textContent = 'Оберіть місто одержувача';    errDiv.style.display = 'block'; return; }
            if (!phone)       { errDiv.textContent = 'Введіть телефон одержувача';  errDiv.style.display = 'block'; return; }
            if (weight <= 0)  { errDiv.textContent = 'Вага повинна бути > 0';       errDiv.style.display = 'block'; return; }
            var isRcpWh = (serviceType === 'WarehouseWarehouse' || serviceType === 'DoorsWarehouse');
            if (isRcpWh && !whRef) { errDiv.textContent = 'Оберіть відділення одержувача'; errDiv.style.display = 'block'; return; }
            if (!isRcpWh) {
                var stRef = ((document.getElementById('npStreetRef')||{}).value||'');
                var bld = ((document.getElementById('npBuilding')||{}).value||'').trim();
                if (!stRef) { errDiv.textContent = 'Оберіть вулицю одержувача'; errDiv.style.display = 'block'; return; }
                if (!bld) { errDiv.textContent = 'Введіть номер будинку'; errDiv.style.display = 'block'; return; }
            }

            var addrOpt = addrSel ? addrSel.options[addrSel.selectedIndex] : null;
            var citySenderRef  = addrOpt ? (addrOpt.dataset.city     || '') : '';
            var citySenderDesc = addrOpt ? (addrOpt.dataset.cityDesc || '') : '';

            var dateVal   = (document.getElementById('npDate') || {}).value || '';
            var dateParts = dateVal.split('-');
            var dateNp    = dateParts.length === 3 ? dateParts[2]+'.'+dateParts[1]+'.'+dateParts[0] : '';

            var seatsN = parseInt(((document.getElementById('npSeats')||{}).value||'1'))||1;
            var optionsSeat = [];
            var hasDimensions = false;
            document.querySelectorAll('#npSeatsRows .seat-row').forEach(function(row) {
                var l = parseInt(row.querySelector('.seat-l').value)||0;
                var wi = parseInt(row.querySelector('.seat-wi').value)||0;
                var h = parseInt(row.querySelector('.seat-h').value)||0;
                var m = row.querySelector('.seat-m').checked ? 1 : 0;
                if (l > 0 || wi > 0 || h > 0 || m) hasDimensions = true;
                optionsSeat.push({weight:parseFloat(row.querySelector('.seat-w').value)||0, length:l, width:wi, height:h, manual:m});
            });

            // Recipient type
            var rcpTypeSel = document.querySelector('input[name="npRcpType"]:checked');
            var rcpType = (rcpTypeSel && rcpTypeSel.value === 'Organization') ? 'Organization' : 'PrivatePerson';
            var isOrgSubmit = (rcpType === 'Organization');

            // Org validation
            if (isOrgSubmit) {
                var orgName = ((document.getElementById('npRcpOrgName')||{}).value||'').trim();
                var edrpou  = ((document.getElementById('npRcpEdrpou')||{}).value||'').trim();
                if (!orgName) { errDiv.textContent = 'Введіть назву організації'; errDiv.style.display = 'block'; return; }
                if (!edrpou)  { errDiv.textContent = 'Введіть код ЄДРПОУ';       errDiv.style.display = 'block'; return; }
            }

            btn.disabled = true; btn.textContent = 'Створення…';

            var body = [
                'customerorder_id='        + _orderId,
                'sender_ref='              + encodeURIComponent(senderRef),
                'sender_address_ref='      + encodeURIComponent(senderAddr),
                'city_sender_ref='         + encodeURIComponent(citySenderRef),
                'city_sender_desc='        + encodeURIComponent(citySenderDesc),
                'city_recipient_ref='      + encodeURIComponent(cityRcpRef),
                'city_recipient_desc='     + encodeURIComponent(cityRcpDesc),
                'service_type='            + encodeURIComponent(serviceType),
                'recipient_type='          + encodeURIComponent(rcpType),
            ];
            if (isOrgSubmit) {
                body.push('recipient_full_name='  + encodeURIComponent(((document.getElementById('npRcpOrgName')||{}).value||'').trim()));
                body.push('recipient_edrpou='     + encodeURIComponent(((document.getElementById('npRcpEdrpou')||{}).value||'').trim()));
                // Contact person for org — split into first/last name for NP API
                var contactPerson = ((document.getElementById('npRcpContactPerson')||{}).value||'').trim();
                var cpParts = contactPerson.split(/\s+/);
                body.push('recipient_last_name='  + encodeURIComponent(cpParts[0] || ''));
                body.push('recipient_first_name=' + encodeURIComponent(cpParts.slice(1).join(' ') || ''));
                body.push('recipient_middle_name=');
            } else {
                body.push('recipient_last_name='  + encodeURIComponent(((document.getElementById('npRcpLast')||{}).value||'').trim()));
                body.push('recipient_first_name=' + encodeURIComponent(((document.getElementById('npRcpFirst')||{}).value||'').trim()));
                body.push('recipient_middle_name=');
            }
            body = body.concat([
                'recipient_phone='         + encodeURIComponent(phone),
                'counterparty_id='         + (_prefillData && _prefillData.recipient ? (_prefillData.recipient.counterparty_id || 0) : 0),
                'recipient_warehouse_ref=' + encodeURIComponent(whRef),
                'recipient_address_desc='  + encodeURIComponent(((document.getElementById('npWhInput')||{}).value||'')),
                'recipient_street_ref='    + encodeURIComponent(((document.getElementById('npStreetRef')||{}).value||'')),
                'recipient_building='      + encodeURIComponent(((document.getElementById('npBuilding')||{}).value||'').trim()),
                'recipient_flat='          + encodeURIComponent(((document.getElementById('npFlat')||{}).value||'').trim()),
                'weight='                  + weight,
                'seats_amount='            + seatsN,
                'cargo_type=Cargo',
                'description='             + encodeURIComponent(((document.getElementById('npDesc')||{}).value||'Канцтовари').trim()),
                'additional_info='         + encodeURIComponent(((document.getElementById('npAddInfo')||{}).value||'').trim()),
                'cost='                    + (parseInt(((document.getElementById('npCost')||{}).value||'1'))||1),
                'payment_method='          + encodeURIComponent(((document.getElementById('npPayMethod')||{}).value||'Cash')),
                'payer_type='              + encodeURIComponent(((document.getElementById('npPayerType')||{}).value||'Recipient')),
                'backward_delivery_money=' + (parseFloat(((document.getElementById('npBackMoney')||{}).value||'0'))||0),
                'date='                    + encodeURIComponent(dateNp),
            ]);
            if (hasDimensions && optionsSeat.length > 0) body.push('options_seat=' + encodeURIComponent(JSON.stringify(optionsSeat)));
            body = body.join('&');

            fetch('/novaposhta/api/create_ttn', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body
            }).then(function(r){ return r.json(); }).then(function(res) {
                btn.disabled = false; btn.textContent = 'Створити ТТН';
                if (!res.ok) {
                    errDiv.textContent = res.error || 'Невідома помилка'; errDiv.style.display = 'block'; return;
                }
                close();
                ShipmentsPanel.reload();
                _relDocsLoaded = false;
                RelDocsGraph.load(_orderId);
                showToast('ТТН ' + (res.int_doc_number || '') + ' створено ✓');
                if (res.ttn_id && typeof TtnDetailModal !== 'undefined') {
                    TtnDetailModal.open(res.ttn_id);
                }
            }).catch(function() {
                btn.disabled = false; btn.textContent = 'Створити ТТН';
                errDiv.textContent = 'Помилка з\'єднання'; errDiv.style.display = 'block';
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        var closeBtn = document.getElementById('newTtnModalClose');
        if (closeBtn) closeBtn.addEventListener('click', close);
        if (typeof makeDraggable === 'function') makeDraggable(document.getElementById('newTtnModal'));
    });

    return { open: open, close: close };
}());
