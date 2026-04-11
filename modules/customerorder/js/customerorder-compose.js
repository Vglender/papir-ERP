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
            { icon: '📋', label: 'Склад замовлення',       key: 'items_list' },
            { icon: '💳', label: 'Посилання на оплату',    key: 'pay' },
            { icon: '📄', label: 'Рахунок (PDF-файл)',      key: 'invoice' },
            { icon: '🔗', label: 'Посилання на портал',    key: 'portal_link' },
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

    function _fetchPortalLink(callback) {
        var btn = document.getElementById('btnSendTpl');
        if (btn) { btn.disabled = true; btn.innerHTML = '⏳ Отримую посилання…'; }
        var fd = new FormData();
        fd.append('order_id', _orderId);
        fetch('/client_portal/api/get_link', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (btn) { btn.disabled = false; btn.innerHTML = '📤 Надіслати ▾'; }
                if (!d.ok) { alert('Помилка: ' + (d.error || 'не вдалось отримати посилання')); return; }
                callback(d);
            })
            .catch(function() {
                if (btn) { btn.disabled = false; btn.innerHTML = '📤 Надіслати ▾'; }
                alert('Помилка мережі при отриманні посилання');
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
        } else if (key === 'portal_link') {
            _fetchPortalLink(function(d) {
                var text = 'Надаємо вам посилання на ваше замовлення, де ви можете перевірити '
                         + 'його статус, товари в замовленні, отримати реквізити на оплату '
                         + 'та документи по замовленню.\n\n'
                         + d.url;
                _openCompose('Посилання на портал клієнта', text);
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
/* Thin wrapper around window.NpTtnCreateModal (shared module). */
var NpTtnModal = (function() {
    function open(orderId) {
        if (!window.NpTtnCreateModal) return;
        window.NpTtnCreateModal.open(orderId, {
            onCreated: function(res, oid) {
                ShipmentsPanel.reload();
                _relDocsLoaded = false;
                RelDocsGraph.load(oid);
                if (typeof showToast === 'function') {
                    showToast('ТТН ' + (res.int_doc_number || '') + ' створено ✓');
                }
                if (res.ttn_id && typeof TtnDetailModal !== 'undefined') {
                    TtnDetailModal.open(res.ttn_id);
                }
            }
        });
    }
    function close() {
        if (window.NpTtnCreateModal) window.NpTtnCreateModal.close();
    }
    return { open: open, close: close };
}());

