/**
 * TTN Detail Modal — shared component.
 *
 * Usage:
 *   TtnDetailModal.init();          // creates overlay+box, binds Escape / overlay-click
 *   TtnDetailModal.open(ttnId);     // fetches detail and renders
 *   TtnDetailModal.close();
 *   TtnDetailModal.onDelete = fn;   // optional callback after successful delete
 *   TtnDetailModal.onSave   = fn;   // optional callback after successful save
 */
window.TtnDetailModal = (function () {
    var overlay, box;
    var _ttn       = null;
    var _senders   = [];
    var _addresses = [];
    var _openSheet = null;
    var _editing   = false;
    var _cityTimer = null;
    var _whTimer   = null;
    var _seatsMode = 'simple';
    var _inited    = false;

    // Public callbacks
    var _onDelete = null;
    var _onSave   = null;

    // ── Helpers ───────────────────────────────────────────────────────────
    function h(s) {
        return String(s == null ? '' : s)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function dash(v) { return (v !== null && v !== undefined && v !== '') ? v : '—'; }
    function getNamePart(full, idx) {
        var p = (full||'').trim().split(/\s+/); return p[idx]||'';
    }
    var PAYER   = {'Sender':'Відправник','Recipient':'Одержувач','ThirdPerson':'Третя особа'};
    var PAYMENT = {'Cash':'Готівка','NonCash':'Безготівка'};
    var STATE_CLASS = {1:'badge-gray',4:'badge-blue',5:'badge-blue',6:'badge-blue',7:'badge-orange',8:'badge-orange',9:'badge-green',10:'badge-orange',103:'badge-orange',102:'badge-red',106:'badge-red'};

    // ── Init ──────────────────────────────────────────────────────────────
    function init() {
        if (_inited) return;
        _inited = true;
        // Create DOM
        overlay = document.createElement('div');
        overlay.className = 'ttn-modal-overlay hidden';
        overlay.id = 'ttnModal';
        box = document.createElement('div');
        box.className = 'ttn-modal-box';
        box.id = 'ttnModalBox';
        box.innerHTML = '<div class="ttn-modal-loading">Завантаження…</div>';
        overlay.appendChild(box);
        document.body.appendChild(overlay);

        overlay.addEventListener('click', function(e){ if(e.target===overlay) closeModal(); });
        document.addEventListener('keydown', function(e){ if(e.key==='Escape' && !overlay.classList.contains('hidden')) closeModal(); });
        if (typeof makeDraggable === 'function') makeDraggable(overlay, { box: '.ttn-modal-box', handle: '.ttn-mh' });

        // Delegate .ttn-num-link clicks
        document.addEventListener('click', function(e){
            var link = e.target.closest('.ttn-num-link');
            if (!link) return;
            e.preventDefault();
            openModal(link.dataset.ttnId);
        });
    }

    // ── Open / Close ──────────────────────────────────────────────────────
    function openModal(ttnId) {
        init();
        overlay.classList.remove('hidden');
        box.innerHTML = '<div class="ttn-modal-loading">Завантаження…</div>';
        document.body.style.overflow = 'hidden';
        fetch('/novaposhta/api/get_ttn_detail?ttn_id=' + ttnId)
            .then(function(r){ return r.json(); })
            .then(function(res) {
                if (!res.ok) { box.innerHTML = '<div class="ttn-modal-error">Помилка: '+h(res.error||'')+'</div>'; return; }
                _ttn        = res.ttn;
                _senders    = res.senders || [];
                _addresses  = res.sender_addresses || [];
                _openSheet  = res.open_sheet;
                _editing    = false;
                var hasSeatData = false;
                try { hasSeatData = JSON.parse(res.ttn.options_seat || '[]').length > 0; } catch(e) {}
                _seatsMode  = hasSeatData ? 'detailed' : 'simple';
                render();
            })
            .catch(function(){ box.innerHTML = '<div class="ttn-modal-error">Мережева помилка</div>'; });
    }

    function closeModal() {
        if (!overlay) return;
        overlay.classList.add('hidden');
        document.body.style.overflow = '';
        _ttn = null; _editing = false; _seatsMode = 'simple';
    }

    // ── Render ────────────────────────────────────────────────────────────
    function render() {
        var t = _ttn;
        var sc = STATE_CLASS[parseInt(t.state_define)] || 'badge-gray';
        var editable = !!t.is_editable;

        // Header
        var html = '<div class="ttn-mh">';
        html += '<div class="ttn-mh-meta">Створена: ' + h(t.moment ? t.moment.replace('T',' ').substr(0,16) : '—') + '</div>';
        html += '<div class="ttn-mh-num">';
        html += '<h2>' + h(t.int_doc_number || 'Чернетка') + '</h2>';
        html += '<span class="badge ' + sc + '" style="font-size:11px">' + h(t.state_name || 'Новий') + '</span>';
        html += '<button type="button" class="ttn-mh-close" id="ttnMClose">✕</button>';
        html += '</div></div>';

        // Body: 2 columns
        html += '<div class="ttn-mb">';

        // Left: timeline
        html += '<div class="ttn-mb-left">';
        html += '<div class="ttn-timeline">';

        // Origin dot
        html += '<div class="ttn-tl-item">';
        html += '<div class="ttn-tl-dot active"></div>';
        if (t.moment) html += '<div class="ttn-tl-date">Дата відправлення: ' + h(t.moment.substr(0,10).split('-').reverse().join('.')) + '</div>';
        if (t.city_sender_desc) html += '<div class="ttn-tl-place">' + h(t.city_sender_desc) + (t.sender_desc ? ', ' + h(t.sender_desc) : '') + '</div>';
        html += '</div>';

        // Status dot
        if (t.state_name && parseInt(t.state_define) !== 1) {
            html += '<div class="ttn-tl-item">';
            html += '<div class="ttn-tl-dot" style="border-color:#f59e0b;background:#f59e0b"></div>';
            html += '<div class="ttn-tl-status"><span class="badge ' + sc + '" style="font-size:11px">' + h(t.state_name) + '</span></div>';
            html += '</div>';
        }

        // Destination dot
        html += '<div class="ttn-tl-item">';
        html += '<div class="ttn-tl-dot"></div>';
        if (t.estimated_delivery_date) {
            html += '<div class="ttn-tl-date">Плановий час доставки: ' + h(t.estimated_delivery_date.substr(0,10).split('-').reverse().join('.')) + '</div>';
        } else {
            html += '<div class="ttn-tl-date">Адреса доставки</div>';
        }
        if (t.city_recipient_desc || t.recipient_address_desc) {
            html += '<div class="ttn-tl-place">' + h(t.city_recipient_desc || '') + (t.recipient_address_desc ? ', ' + h(t.recipient_address_desc) : '') + '</div>';
        }
        html += '</div>';
        html += '</div>'; // timeline

        // Additional services
        if (!_editing && t.backward_delivery_money > 0) {
            html += '<div class="ttn-services">';
            html += '<div class="ttn-services-title">Додаткові послуги</div>';
            html += '<div class="ttn-service-item">';
            html += '<svg viewBox="0 0 16 16" fill="none" style="width:16px;height:16px;flex-shrink:0"><path d="M13 5H6a3 3 0 0 0 0 6h7" stroke="#c2410c" stroke-width="1.5" stroke-linecap="round"/><path d="M11 3l2 2-2 2" stroke="#c2410c" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            html += 'Контроль оплати: <span style="color:#c2410c">' + h(parseFloat(t.backward_delivery_money).toLocaleString('uk')) + ' грн</span>';
            html += '</div></div>';
        }
        if (_editing) {
            html += renderSeatsSection(t);
        }
        html += '</div>'; // left

        // Right column
        html += '<div class="ttn-mb-right">';

        if (_editing) {
            html += renderEditRight(t);
        } else {
            html += '<div class="ttn-rf-row" style="margin-bottom:14px">';
            html += '<div class="ttn-rf"><div class="ttn-rf-label">Опис відправлення</div><div class="ttn-rf-val">' + h(dash(t.description)) + '</div></div>';
            html += '<div class="ttn-rf"><div class="ttn-rf-label">Оголошена цінність</div><div class="ttn-rf-val">' + (t.declared_value ? h(t.declared_value) + ' грн' : '—') + '</div></div>';
            html += '</div>';
            html += '<div class="ttn-rf"><div class="ttn-rf-label">Отримувач</div><div class="ttn-rf-val large">' + h(dash(t.recipient_contact_person)) + '</div></div>';
            html += '<div class="ttn-rf"><div class="ttn-rf-label">Телефон отримувача</div><div class="ttn-rf-val">' + h(dash(t.recipients_phone)) + '</div></div>';
        }

        html += '</div>'; // right
        html += '</div>'; // ttn-mb

        // Cost bar
        html += '<div class="ttn-cost-bar">';
        html += '<div class="ttn-rf"><div class="ttn-rf-label">Вартість доставки</div><div class="ttn-rf-val">' + (t.cost_on_site ? h(parseFloat(t.cost_on_site)) + ' грн' : '—') + '</div></div>';

        if (_editing) {
            html += '<div class="ttn-rf"><div class="ttn-rf-label">Платник за доставку</div>' +
                '<select class="ttn-ef" id="ef_payer">' +
                opt('Sender','Відправник',t.payer_type) + opt('Recipient','Одержувач',t.payer_type) + opt('ThirdPerson','Третя особа',t.payer_type) +
                '</select></div>';
            html += '<div class="ttn-rf"><div class="ttn-rf-label">Форма оплати</div>' +
                '<select class="ttn-ef" id="ef_payment">' +
                opt('Cash','Готівка',t.payment_method) + opt('NonCash','Безготівка',t.payment_method) +
                '</select></div>';
            html += '<div class="ttn-rf"><div class="ttn-rf-label">↩ Контроль оплати, грн</div>' +
                '<input type="number" class="ttn-ef ttn-ef-md" id="ef_cod" min="0" step="1" value="' + h(parseFloat(t.backward_delivery_money)||0) + '">' +
                '<div id="ef_cod_hint" style="font-size:11px;color:#6b7280;margin-top:2px"></div></div>';
        } else {
            html += '<div class="ttn-rf"><div class="ttn-rf-label">Платник за доставку</div><div class="ttn-rf-val">' + h(PAYER[t.payer_type]||dash(t.payer_type)) + '</div></div>';
            html += '<div class="ttn-rf"><div class="ttn-rf-label">Форма оплати за доставку</div><div class="ttn-rf-val">' + h(PAYMENT[t.payment_method]||dash(t.payment_method)) + '</div></div>';
            html += '<div></div>';
        }
        html += '</div>'; // cost-bar

        // Specs row
        html += '<div class="ttn-specs">';
        html += '<div class="ttn-rf"><div class="ttn-rf-label">Тип</div><div class="ttn-rf-val">Посилка</div></div>';
        html += '<div class="ttn-rf"><div class="ttn-rf-label">Вага</div><div class="ttn-rf-val">' + (t.weight ? h(parseFloat(t.weight)) + ' кг' : '—') + '</div></div>';
        html += '<div class="ttn-rf"><div class="ttn-rf-label">Місць</div><div class="ttn-rf-val">' + h(dash(t.seats_amount)) + '</div></div>';
        html += '<div></div>';
        html += '<div class="ttn-rf"><div class="ttn-rf-label">Відправник</div><div class="ttn-rf-val">' + h(dash(t.sender_full_name||t.sender_desc)) + '</div>' +
            (t.phone_sender ? '<div class="ttn-rf-label" style="margin-top:2px">' + h(t.phone_sender) + '</div>' : '') + '</div>';
        html += '<div></div>';
        html += '</div>'; // specs

        // Extra
        html += '<div class="ttn-extra">';
        if (_editing) {
            html += '<div class="ttn-rf"><div class="ttn-rf-label">Опис відправлення</div><div class="ttn-rf-val">';
            html += '<input type="text" class="ttn-ef" id="ef_desc" value="' + h(t.description||'') + '" maxlength="200" placeholder="Товар">';
            html += '</div></div>';
            html += '<div class="ttn-rf"><div class="ttn-rf-label">Оголошена вартість, грн</div><div class="ttn-rf-val">';
            html += '<input type="number" class="ttn-ef ttn-ef-md" id="ef_declared" min="1" step="1" value="' + h(parseInt(t.declared_value)||1) + '">';
            html += '<div id="ef_declared_hint" style="font-size:11px;color:#6b7280;margin-top:2px"></div>';
            html += '</div></div>';
            html += '<div class="ttn-rf" style="grid-column:1/-1"><div class="ttn-rf-label">Додаткова інформація про відправлення</div><div class="ttn-rf-val">';
            html += '<input type="text" class="ttn-ef" id="ef_add_info" value="' + h(t.additional_information||'') + '" maxlength="500" placeholder="Інформація про позиції замовлення…">';
            html += '</div></div>';
        } else {
            html += '<div class="ttn-rf"><div class="ttn-rf-label">Опис відправлення</div><div class="ttn-rf-val">' + h(dash(t.description)) + '</div></div>';
            html += '<div class="ttn-rf"><div class="ttn-rf-label">Оголошена вартість</div><div class="ttn-rf-val">' + (t.declared_value ? h(t.declared_value) + ' грн' : '—') + '</div></div>';
            if (t.additional_information) {
                html += '<div class="ttn-rf" style="grid-column:1/-1"><div class="ttn-rf-label">Додаткова інформація</div><div class="ttn-rf-val">' + h(t.additional_information) + '</div></div>';
            }
            if (t.customerorder_id) {
                html += '<div class="ttn-rf"><div class="ttn-rf-label">Замовлення</div><div class="ttn-rf-val"><a href="/customerorder/edit?id=' + h(t.customerorder_id) + '" target="_blank" style="color:#2563eb">#' + h(t.customerorder_id) + '</a></div></div>';
            }
        }
        html += '</div>'; // extra

        // Footer
        html += '<div class="ttn-mf">';
        html += '<button type="button" class="ttn-mf-hide" id="ttnMClose2">';
        html += '<svg viewBox="0 0 16 16" fill="none" style="width:14px;height:14px"><path d="M4 10l4-4 4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        html += 'ПРИХОВАТИ</button>';

        html += iconBtn('ttnActDelete', 'danger', 'Видалити', '<path d="M3 5h10M8 5V3M6 5v9M10 5v9M4 5l.5 9h7l.5-9" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>');
        if (t.int_doc_number && (!t.scan_sheet_ref || t.scan_sheet_status !== 'open')) {
            html += iconBtn('ttnActSheet', '', _openSheet ? 'Додати до реєстру' : 'Новий реєстр', '<rect x="2" y="2" width="12" height="12" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M5 6h6M5 8.5h6M5 11h4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>');
        }
        if (t.can_duplicate) {
            html += iconBtn('ttnActDup', '', 'Дублювати', '<rect x="5" y="4" width="8" height="10" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M3 12V3a1 1 0 0 1 1-1h7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>');
        }
        if (t.int_doc_number) {
            html += iconBtn('ttnActP100', '', 'Друк 100×100', '<rect x="3" y="1" width="10" height="6" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="3" y="9" width="10" height="6" rx="1" stroke="currentColor" stroke-width="1.4"/><path d="M1 7h14v4H1z" fill="none" stroke="currentColor" stroke-width="1.4"/><circle cx="12" cy="9.5" r=".8" fill="currentColor"/>');
            html += iconBtn('ttnActPA4',  '', 'Друк A4/6',   '<rect x="3" y="1" width="10" height="6" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="3" y="9" width="10" height="6" rx="1" stroke="currentColor" stroke-width="1.4"/><path d="M1 7h14v4H1z" fill="none" stroke="currentColor" stroke-width="1.4"/><circle cx="12" cy="9.5" r=".8" fill="currentColor"/>');
        }

        if (t.counterparty_id) {
            html += '<span style="margin-left:auto;display:inline-flex;gap:4px">';
            html += '<button type="button" class="btn btn-ghost btn-sm js-ttn-send"'
                  + ' data-cp="'     + h(String(t.counterparty_id))        + '"'
                  + ' data-ttn="'    + h(String(t.int_doc_number || ''))   + '"'
                  + ' data-status="' + h(String(t.state_name     || ''))   + '">'
                  + '&#128228; Надіслати ▾</button>';
            html += '<button type="button" class="btn btn-ghost btn-sm"'
                  + ' onclick="if(typeof ChatModal!==\'undefined\')ChatModal.open(' + h(String(t.counterparty_id)) + ')">'
                  + '&#128172; Чат</button>';
            html += '</span>';
        }

        if (editable) {
            if (_editing) {
                html += '<button type="button" class="btn btn-primary btn-sm ttn-edit-btn" id="ttnActSave" style="margin-left:8px">💾 Зберегти</button>';
                html += '<button type="button" class="btn btn-ghost btn-sm" id="ttnActCancelEdit" style="margin-left:4px">Скасувати</button>';
            } else {
                html += '<button type="button" class="btn btn-ghost btn-sm ttn-edit-btn" id="ttnActEdit">';
                html += '<svg viewBox="0 0 16 16" fill="none" style="width:14px;height:14px"><path d="M11.5 2.5l2 2L5 13H3v-2L11.5 2.5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>';
                html += 'РЕДАГУВАТИ</button>';
            }
        }
        html += '</div>'; // footer

        box.innerHTML = html;
        bindEvents();
        if (_editing) { bindCitySearch(); bindWhSearch(); bindPhoneSearch(); }
    }

    function renderSeatRowHTML(num, s) {
        var manChecked = s.manual ? ' checked' : '';
        return '<tr class="seat-row">' +
            '<td class="seat-num" style="color:#9ca3af;font-size:11px;width:14px">' + num + '</td>' +
            '<td><input type="number" class="ttn-ef ttn-ef-sm seat-w" step="0.01" min="0.01" value="' + (s.weight||'') + '" placeholder="0.5"></td>' +
            '<td><input type="number" class="ttn-ef ttn-ef-sm seat-l" step="1" min="0" value="' + (s.length||'') + '" placeholder="0"></td>' +
            '<td><input type="number" class="ttn-ef ttn-ef-sm seat-wi" step="1" min="0" value="' + (s.width||'') + '" placeholder="0"></td>' +
            '<td><input type="number" class="ttn-ef ttn-ef-sm seat-h" step="1" min="0" value="' + (s.height||'') + '" placeholder="0"></td>' +
            '<td style="text-align:center"><input type="checkbox" class="seat-manual" title="Ручна обробка"' + manChecked + '></td>' +
            '<td><button type="button" class="ttn-seat-del" title="Видалити">✕</button></td>' +
            '</tr>';
    }

    function renderSeatsSection(t) {
        var seats = [];
        try { seats = JSON.parse(t.options_seat || '[]'); } catch(e) {}
        if (!seats.length) {
            var n = parseInt(t.seats_amount) || 1;
            var wEach = Math.round((parseFloat(t.weight) || 0.5) / n * 100) / 100;
            for (var i = 0; i < n; i++) { seats.push({weight: wEach, length: 0, width: 0, height: 0, manual: false}); }
        }

        var isDetailed = (_seatsMode === 'detailed');
        var totalW = 0;
        seats.forEach(function(s) { totalW += (parseFloat(s.weight) || 0); });
        totalW = Math.round(totalW * 100) / 100;

        var html = '<div class="ttn-seats-section" id="seatsSectionWrap">';
        html += '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">';
        html += '<span style="font-size:11px;color:#6b7280;font-weight:500">Місця / Габарити</span>';
        html += '<label style="display:flex;align-items:center;gap:4px;font-size:11px;color:#374151;cursor:pointer">';
        html += '<input type="checkbox" id="seatsDetailedToggle"' + (isDetailed ? ' checked' : '') + '> По місцях';
        html += '</label></div>';

        if (isDetailed) {
            html += '<table class="ttn-seats-table" id="seatsTable"><thead><tr>';
            html += '<th>#</th><th>Вага</th><th>Дов</th><th>Шир</th><th>Вис</th><th title="Ручна обробка">РО</th><th></th>';
            html += '</tr></thead><tbody id="seatsTbody">';
            for (var si = 0; si < seats.length; si++) {
                html += renderSeatRowHTML(si + 1, seats[si]);
            }
            html += '</tbody></table>';
            html += '<div class="ttn-seats-footer">';
            html += '<button type="button" class="btn btn-ghost btn-xs" id="addSeatBtn">+ Місце</button>';
            html += '<span class="ttn-seats-totals" id="seatsTotals"></span>';
            html += '</div>';
        } else {
            var seatsCount = seats.length;
            html += '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">';
            html += '<div><div class="ttn-rf-label">Вага, кг</div><input type="number" class="ttn-ef ttn-ef-sm" id="ef_weight" step="0.01" min="0.1" value="' + h(totalW || parseFloat(t.weight) || 0.5) + '"></div>';
            html += '<div><div class="ttn-rf-label">Місць</div><input type="number" class="ttn-ef ttn-ef-sm" id="ef_seats" min="1" value="' + h(seatsCount) + '"></div>';
            html += '</div>';
        }
        html += '</div>';
        return html;
    }

    function renderEditRight(t) {
        var html = '';
        html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:6px">';
        html += '<div><div class="ttn-rf-label">Прізвище</div><input type="text" class="ttn-ef" id="ef_last" value="' + h(getNamePart(t.recipient_contact_person,0)) + '"></div>';
        html += '<div><div class="ttn-rf-label">Ім\'я</div><input type="text" class="ttn-ef" id="ef_first" value="' + h(getNamePart(t.recipient_contact_person,1)) + '"></div>';
        html += '</div>';
        html += '<input type="hidden" id="ef_mid" value="">';
        html += '<div style="margin-bottom:6px"><div class="ttn-rf-label">Телефон</div>';
        html += '<div class="ttn-search-wrap">';
        html += '<input type="text" class="ttn-ef" id="ef_phone" value="' + h(t.recipients_phone||'') + '" placeholder="+380…" autocomplete="off">';
        html += '<div class="ttn-search-dd" id="ef_phone_dd" style="display:none"></div>';
        html += '</div></div>';
        html += '<div style="margin-bottom:6px"><div class="ttn-rf-label">Місто</div><div class="ttn-search-wrap">';
        html += '<input type="text" class="ttn-ef" id="ef_city_text" value="' + h(t.city_recipient_desc||'') + '" placeholder="Пошук міста…" autocomplete="off">';
        html += '<input type="hidden" id="ef_city_ref" value="' + h(t.city_recipient_ref||'') + '">';
        html += '<div class="ttn-search-dd" id="ef_city_dd" style="display:none"></div>';
        html += '</div></div>';
        html += '<div><div class="ttn-rf-label">Відділення / Адреса</div><div class="ttn-search-wrap">';
        html += '<input type="text" class="ttn-ef" id="ef_wh_text" value="' + h(t.recipient_address_desc||'') + '" placeholder="Пошук відділення…" autocomplete="off">';
        html += '<input type="hidden" id="ef_wh_ref" value="' + h(t.recipient_address||'') + '">';
        html += '<div class="ttn-search-dd" id="ef_wh_dd" style="display:none"></div>';
        html += '</div></div>';
        return html;
    }

    function iconBtn(id, cls, title, svgPath) {
        return '<button type="button" class="ttn-act-icon ' + cls + '" id="' + id + '" title="' + h(title) + '">' +
            '<svg viewBox="0 0 16 16" fill="none">' + svgPath + '</svg></button>';
    }
    function opt(v, label, cur) {
        return '<option value="' + h(v) + '"' + (v===cur?' selected':'') + '>' + h(label) + '</option>';
    }

    // ── Bind events ───────────────────────────────────────────────────────
    function bindEvents() {
        var closeBtn  = document.getElementById('ttnMClose');
        var closeBtn2 = document.getElementById('ttnMClose2');
        if (closeBtn)  closeBtn.addEventListener('click', closeModal);
        if (closeBtn2) closeBtn2.addEventListener('click', closeModal);

        var editBtn = document.getElementById('ttnActEdit');
        if (editBtn) editBtn.addEventListener('click', function(){ _editing=true; render(); });

        var cancelBtn = document.getElementById('ttnActCancelEdit');
        if (cancelBtn) cancelBtn.addEventListener('click', function(){ _editing=false; render(); });

        var saveBtn = document.getElementById('ttnActSave');
        if (saveBtn) saveBtn.addEventListener('click', doSave);

        // COD / declared value hints
        var codInp  = document.getElementById('ef_cod');
        var declInp = document.getElementById('ef_declared');
        var codHint  = document.getElementById('ef_cod_hint');
        var declHint = document.getElementById('ef_declared_hint');

        function updateCodDeclHints() {
            if (!codInp || !declInp) return;
            var cod  = parseFloat(codInp.value)  || 0;
            var decl = parseFloat(declInp.value) || 0;
            if (cod > 0 && decl < cod) {
                declInp.value = Math.ceil(cod);
                decl = Math.ceil(cod);
                if (declHint) { declHint.textContent = '↑ Підвищено до суми контролю оплати'; declHint.style.color = '#f59e0b'; }
            } else {
                if (declHint) { declHint.textContent = cod > 0 ? 'мін. ' + Math.ceil(cod) + ' грн (= контроль оплати)' : ''; }
            }
            if (codHint) {
                codHint.textContent = cod > 0 ? 'Оголошена вартість: ' + decl + ' грн' : '';
            }
        }

        if (codInp)  { codInp.addEventListener('input', updateCodDeclHints); }
        if (declInp) {
            declInp.addEventListener('blur', function() {
                var cod  = parseFloat(codInp ? codInp.value : 0) || 0;
                var decl = parseFloat(declInp.value) || 0;
                if (cod > 0 && decl < cod) {
                    declInp.value = Math.ceil(cod);
                    if (declHint) { declHint.textContent = 'мін. ' + Math.ceil(cod) + ' грн'; declHint.style.color = '#6b7280'; }
                }
                if (codHint) { codHint.textContent = cod > 0 ? 'Оголошена вартість: ' + (parseFloat(declInp.value)||0) + ' грн' : ''; }
            });
        }
        updateCodDeclHints();

        if (_editing) bindSeatsTable();

        var p100 = document.getElementById('ttnActP100');
        var pA4  = document.getElementById('ttnActPA4');
        if (p100) p100.addEventListener('click', function(){ window.open('/novaposhta/api/print_ttn_sticker?ttn_id='+_ttn.id+'&format=100x100','_blank'); });
        if (pA4)  pA4.addEventListener('click',  function(){ window.open('/novaposhta/api/print_ttn_sticker?ttn_id='+_ttn.id+'&format=a4_6','_blank'); });

        var dupBtn = document.getElementById('ttnActDup');
        if (dupBtn) dupBtn.addEventListener('click', function(){
            dupBtn.disabled = true;
            fetch('/novaposhta/api/duplicate_ttn', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'ttn_id='+_ttn.id})
            .then(function(r){ return r.json(); }).then(function(res){
                dupBtn.disabled = false;
                if (!res.ok) { showToast('Помилка: '+(res.error||''),true); return; }
                showToast('Створено копію: '+(res.int_doc_number||'#'+res.ttn_id));
                closeModal();
                if (_onSave) _onSave();
            }).catch(function(){ dupBtn.disabled=false; showToast('Мережева помилка',true); });
        });

        var sheetBtn = document.getElementById('ttnActSheet');
        if (sheetBtn) sheetBtn.addEventListener('click', function(){
            sheetBtn.disabled = true;
            var body = 'ttn_id='+_ttn.id;
            if (_openSheet) body += '&scan_sheet_ref='+encodeURIComponent(_openSheet.Ref);
            fetch('/novaposhta/api/add_ttn_to_scansheet', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:body})
            .then(function(r){ return r.json(); }).then(function(res){
                sheetBtn.disabled = false;
                if (!res.ok) { showToast('Помилка: '+(res.error||''),true); return; }
                _ttn.scan_sheet_ref = res.scan_sheet_ref;
                showToast(res.created_new ? 'Додано до нового реєстру' : 'Додано до реєстру');
                render();
            }).catch(function(){ sheetBtn.disabled=false; showToast('Мережева помилка',true); });
        });

        var delBtn = document.getElementById('ttnActDelete');
        if (delBtn) delBtn.addEventListener('click', function(){
            if (!confirm('Видалити ТТН? Буде видалено в НП (якщо API дозволяє).')) return;
            delBtn.disabled = true;
            fetch('/novaposhta/api/delete_ttn', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'ttn_id='+_ttn.id})
            .then(function(r){ return r.json(); }).then(function(res){
                if (!res.ok) { delBtn.disabled=false; showToast('Помилка: '+(res.error||''),true); return; }
                showToast('ТТН видалено');
                // Remove from table if present (TTN list page)
                var cb = document.querySelector('.ttn-row-check[value="'+_ttn.id+'"]');
                if (cb) { var tr=cb.closest('tr'); if(tr) tr.remove(); }
                closeModal();
                if (_onDelete) _onDelete(_ttn.id);
            }).catch(function(){ delBtn.disabled=false; showToast('Мережева помилка',true); });
        });
    }

    // ── Seats table ──────────────────────────────────────────────────────
    function collectSeats() {
        var tbody = document.getElementById('seatsTbody');
        if (!tbody) return [];
        var result = [];
        tbody.querySelectorAll('.seat-row').forEach(function(tr) {
            var manEl = tr.querySelector('.seat-manual');
            result.push({
                weight: parseFloat(tr.querySelector('.seat-w').value) || 0,
                length: parseInt(tr.querySelector('.seat-l').value)   || 0,
                width:  parseInt(tr.querySelector('.seat-wi').value)  || 0,
                height: parseInt(tr.querySelector('.seat-h').value)   || 0,
                manual: manEl ? manEl.checked : false
            });
        });
        return result;
    }

    function updateSeatTotals() {
        var seats = collectSeats();
        var totalW = 0, totalVol = 0;
        seats.forEach(function(s) {
            totalW   += s.weight;
            totalVol += (s.length * s.width * s.height) / 4000;
        });
        totalW   = Math.round(totalW   * 100) / 100;
        totalVol = Math.round(totalVol * 100) / 100;

        var totEl = document.getElementById('seatsTotals');
        if (totEl) {
            totEl.textContent = 'Вага: ' + totalW + ' кг' + (totalVol > 0 ? ' | Об.: ' + totalVol + ' кг' : '');
        }
        var wInp = document.getElementById('ef_weight');
        var sInp = document.getElementById('ef_seats');
        if (wInp && totalW > 0) wInp.value = totalW;
        if (sInp) sInp.value = seats.length;
    }

    function bindSeatsRow(tr) {
        tr.querySelector('.ttn-seat-del').addEventListener('click', function() {
            var tbody = document.getElementById('seatsTbody');
            if (!tbody || tbody.querySelectorAll('.seat-row').length <= 1) return;
            tr.remove();
            tbody.querySelectorAll('.seat-row').forEach(function(r, i) {
                var nc = r.querySelector('.seat-num'); if (nc) nc.textContent = i + 1;
            });
            updateSeatTotals();
        });
        tr.querySelectorAll('input').forEach(function(inp) {
            inp.addEventListener('input', updateSeatTotals);
        });
    }

    function bindSeatsTable() {
        var toggle = document.getElementById('seatsDetailedToggle');
        if (toggle) {
            toggle.addEventListener('change', function() {
                if (this.checked) {
                    var wInp = document.getElementById('ef_weight');
                    var sInp = document.getElementById('ef_seats');
                    var totalW = wInp ? (parseFloat(wInp.value) || 0.5) : 0.5;
                    var n = sInp ? (parseInt(sInp.value) || 1) : 1;
                    var wEach = Math.round(totalW / n * 100) / 100;
                    var newSeats = [];
                    for (var i = 0; i < n; i++) { newSeats.push({weight: wEach, length: 0, width: 0, height: 0, manual: false}); }
                    _ttn.options_seat = JSON.stringify(newSeats);
                    _seatsMode = 'detailed';
                } else {
                    var seats = collectSeats();
                    var sumW = 0; seats.forEach(function(s){ sumW += s.weight; });
                    sumW = Math.round(sumW * 100) / 100;
                    _ttn.options_seat = '[]';
                    _ttn.weight = sumW || _ttn.weight;
                    _ttn.seats_amount = seats.length;
                    _seatsMode = 'simple';
                }
                var wrap = document.getElementById('seatsSectionWrap');
                if (wrap) {
                    var tmp = document.createElement('div');
                    tmp.innerHTML = renderSeatsSection(_ttn);
                    wrap.parentNode.replaceChild(tmp.firstChild, wrap);
                    bindSeatsTable();
                }
            });
        }

        var tbody = document.getElementById('seatsTbody');
        var addBtn = document.getElementById('addSeatBtn');
        if (!tbody) return;
        tbody.querySelectorAll('.seat-row').forEach(bindSeatsRow);
        if (addBtn) addBtn.addEventListener('click', function() {
            var rows = tbody.querySelectorAll('.seat-row');
            var lastW = 0.5;
            if (rows.length) { lastW = parseFloat(rows[rows.length-1].querySelector('.seat-w').value) || 0.5; }
            var newNum = rows.length + 1;
            var tmp = document.createElement('tbody');
            tmp.innerHTML = renderSeatRowHTML(newNum, {weight: lastW, length: 0, width: 0, height: 0, manual: false});
            var newTr = tmp.querySelector('tr');
            tbody.appendChild(newTr);
            bindSeatsRow(newTr);
            updateSeatTotals();
            newTr.querySelector('.seat-w').focus();
        });
        updateSeatTotals();
    }

    // ── City / Warehouse / Phone search ───────────────────────────────────
    var _openDd = null;

    function posDd(inp, dd) {
        var r = inp.getBoundingClientRect();
        dd.style.top   = r.bottom + 'px';
        dd.style.left  = r.left + 'px';
        dd.style.width = r.width + 'px';
    }

    document.addEventListener('mousedown', function(e) {
        if (!_openDd) return;
        if (e.target !== _openDd.inp && !_openDd.dd.contains(e.target)) {
            _openDd.dd.style.display = 'none';
            _openDd = null;
        }
    });

    function showDd(inp, dd) {
        posDd(inp, dd);
        dd.style.display = 'block';
        _openDd = { inp: inp, dd: dd };
    }
    function hideDd(dd) {
        dd.style.display = 'none';
        _openDd = null;
    }

    function bindPhoneSearch() {
        var inp = document.getElementById('ef_phone');
        var dd  = document.getElementById('ef_phone_dd');
        if (!inp || !dd) return;
        var _phoneTimer = null;

        inp.addEventListener('input', function() {
            clearTimeout(_phoneTimer);
            var q = inp.value.trim();
            if (q.length < 3) { hideDd(dd); return; }
            _phoneTimer = setTimeout(function() {
                fetch('/counterparties/api/search?q=' + encodeURIComponent(q) + '&type=person')
                .then(function(r){ return r.json(); })
                .then(function(res) {
                    dd.innerHTML = '';
                    if (!res.ok || !res.items || !res.items.length) { hideDd(dd); return; }
                    res.items.slice(0, 8).forEach(function(cp) {
                        var el = document.createElement('div');
                        el.className = 'ttn-search-opt';
                        var phone = cp.phone || '';
                        el.textContent = cp.name + (phone ? ' · ' + phone : '');
                        el.addEventListener('click', function() {
                            var parts = (cp.name || '').trim().split(/\s+/);
                            var lastEl  = document.getElementById('ef_last');
                            var firstEl = document.getElementById('ef_first');
                            if (lastEl  && parts[0]) lastEl.value  = parts[0];
                            if (firstEl && parts[1]) firstEl.value = parts[1];
                            if (phone) {
                                var digits = phone.replace(/\D/g,'');
                                if (digits.length === 12 && digits.substr(0,2)==='38') digits = digits.substr(2);
                                else if (digits.length === 11 && digits.charAt(0)==='8') digits = digits.substr(1);
                                inp.value = digits.length >= 10 ? digits : phone;
                            }
                            hideDd(dd);
                            inp.focus();
                        });
                        dd.appendChild(el);
                    });
                    showDd(inp, dd);
                })
                .catch(function(){});
            }, 350);
        });
    }

    function bindCitySearch() {
        var inp   = document.getElementById('ef_city_text');
        var ref   = document.getElementById('ef_city_ref');
        var dd    = document.getElementById('ef_city_dd');
        var whInp = document.getElementById('ef_wh_text');
        var whRef = document.getElementById('ef_wh_ref');
        if (!inp) return;

        inp.addEventListener('input', function() {
            ref.value = '';
            clearTimeout(_cityTimer);
            var q = inp.value.trim();
            if (q.length < 2) { hideDd(dd); return; }
            _cityTimer = setTimeout(function() {
                fetch('/novaposhta/api/search_city?q=' + encodeURIComponent(q) + '&sender_ref=' + encodeURIComponent(_ttn.sender_ref))
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    dd.innerHTML = '';
                    if (!res.ok || !res.cities || !res.cities.length) { hideDd(dd); return; }
                    res.cities.slice(0, 10).forEach(function(c) {
                        var el = document.createElement('div');
                        el.className = 'ttn-search-opt';
                        var cityName = c.Description;
                        el.textContent = cityName + (c.AreaDescription ? ', ' + c.AreaDescription : '');
                        el.addEventListener('click', function() {
                            inp.value = cityName;
                            ref.value = c.Ref;
                            hideDd(dd);
                            if (whInp) { whInp.value = ''; }
                            if (whRef) { whRef.value = ''; }
                            inp.focus();
                        });
                        dd.appendChild(el);
                    });
                    showDd(inp, dd);
                });
            }, 250);
        });
    }

    function bindWhSearch() {
        var cityRef = document.getElementById('ef_city_ref');
        var inp = document.getElementById('ef_wh_text');
        var ref = document.getElementById('ef_wh_ref');
        var dd  = document.getElementById('ef_wh_dd');
        if (!inp) return;

        inp.addEventListener('input', function() {
            ref.value = '';
            clearTimeout(_whTimer);
            var cr = cityRef ? cityRef.value : '';
            if (!cr) { showToast('Спочатку оберіть місто', true); return; }
            var q = inp.value.trim();
            _whTimer = setTimeout(function() {
                fetch('/novaposhta/api/search_warehouse?city_ref=' + encodeURIComponent(cr) + '&q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    dd.innerHTML = '';
                    if (!res.ok || !res.warehouses || !res.warehouses.length) { hideDd(dd); return; }
                    res.warehouses.slice(0, 15).forEach(function(w) {
                        var el = document.createElement('div');
                        el.className = 'ttn-search-opt';
                        el.textContent = w.Description;
                        el.addEventListener('click', function() {
                            inp.value = w.Description;
                            ref.value = w.Ref;
                            hideDd(dd);
                            inp.focus();
                        });
                        dd.appendChild(el);
                    });
                    showDd(inp, dd);
                });
            }, 250);
        });
    }

    // ── Save ──────────────────────────────────────────────────────────────
    function doSave() {
        var btn = document.getElementById('ttnActSave');
        if (btn) { btn.disabled=true; btn.textContent='…'; }
        var p = 'ttn_id='+_ttn.id;
        function f(id, name) { var el=document.getElementById(id); if(el) p+='&'+name+'='+encodeURIComponent(el.value); }
        f('ef_desc',     'description');   f('ef_declared',  'declared_value');
        f('ef_add_info', 'additional_information');
        f('ef_payer',   'payer_type');     f('ef_payment',  'payment_method');
        f('ef_cod',     'backward_delivery_money');
        f('ef_last',    'recipient_last_name');   f('ef_first', 'recipient_first_name');
        f('ef_mid',     'recipient_middle_name'); f('ef_phone', 'recipient_phone');
        f('ef_city_ref','city_recipient_ref');    f('ef_city_text','city_recipient_desc');
        f('ef_wh_ref',  'recipient_address_ref'); f('ef_wh_text',  'recipient_address_desc');
        if (_seatsMode === 'detailed') {
            var seats = collectSeats();
            var totalW = 0; seats.forEach(function(s){ totalW += s.weight; });
            totalW = Math.round(totalW * 100) / 100;
            p += '&options_seat=' + encodeURIComponent(JSON.stringify(seats));
            p += '&weight=' + (totalW || 0.5);
            p += '&seats_amount=' + seats.length;
            p += '&manual_handling=0';
        } else {
            f('ef_weight', 'weight'); f('ef_seats', 'seats_amount');
            p += '&options_seat=' + encodeURIComponent('[]');
            p += '&manual_handling=0';
        }

        fetch('/novaposhta/api/update_ttn_detail', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:p})
        .then(function(r){ return r.json(); }).then(function(res){
            if (btn) { btn.disabled=false; btn.textContent='💾 Зберегти'; }
            if (!res.ok) { showToast('Помилка: '+(res.error||''),true); return; }
            _ttn=res.ttn; _editing=false;
            showToast('Збережено');
            render();
            if (_onSave) _onSave();
        }).catch(function(){ if(btn){btn.disabled=false;btn.textContent='💾 Зберегти';} showToast('Мережева помилка',true); });
    }

    // ── Public API ────────────────────────────────────────────────────────
    return {
        init:  init,
        open:  openModal,
        close: closeModal,
        set onDelete(fn) { _onDelete = fn; },
        set onSave(fn)   { _onSave   = fn; }
    };
}());
