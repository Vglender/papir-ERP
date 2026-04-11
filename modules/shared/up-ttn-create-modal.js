/**
 * UpTtnCreateModal — shared modal for creating / editing Ukrposhta TTN.
 *
 * Usage:
 *   UpTtnCreateModal.open(orderId, { onCreated: function(res, orderId) { ... } });
 *   UpTtnCreateModal.open(orderId, { editTtnId: 123 });   // edit existing
 *   UpTtnCreateModal.open(0);                             // standalone TTN without order
 *
 * DOM (expected in the page):
 *   #upTtnModal        — modal-overlay container
 *   #upTtnBody         — modal body
 *   #upTtnModalClose   — close button
 *   #upTtnModalTitle   — header
 */
window.UpTtnCreateModal = (function() {
    var _orderId = 0;
    var _opts = {};
    var _editTtn = null;

    function esc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function open(orderId, opts) {
        _orderId = parseInt(orderId) || 0;
        _opts = opts || {};
        _editTtn = null;

        var modal = document.getElementById('upTtnModal');
        var body  = document.getElementById('upTtnBody');
        var title = document.getElementById('upTtnModalTitle');
        if (!modal || !body) return;
        body.innerHTML = '<div style="text-align:center;color:#9ca3af;padding:30px;">Завантаження…</div>';
        if (title) title.textContent = _opts.editTtnId ? 'Редагування ТТН Укрпошта' : 'Нова ТТН Укрпошта';
        modal.classList.add('open');

        var url = '/ukrposhta/api/get_ttn_form?order_id=' + _orderId;
        if (_opts.editTtnId) url += '&ttn_id=' + _opts.editTtnId;

        fetch(url)
            .then(function(r){ return r.json(); })
            .then(function(res) {
                if (!res.ok) { body.innerHTML = '<div style="color:#dc2626;padding:16px">' + esc(res.error || 'Помилка') + '</div>'; return; }
                if (res.ttn) _editTtn = res.ttn;
                renderForm(res.data || {}, body);
            })
            .catch(function() { body.innerHTML = '<div style="color:#dc2626;padding:16px">Помилка завантаження</div>'; });
    }

    function close() {
        var modal = document.getElementById('upTtnModal');
        if (modal) modal.classList.remove('open');
    }

    function renderForm(data, body) {
        var r = (data && data.recipient) || {};
        var d = data && data.defaults || {};
        var isEdit = !!_editTtn;

        var html = '';

        // ── Recipient ──
        html += '<div class="np-form-section">Одержувач</div>';
        html += '<div class="np-2col">';
        html += '<div><label class="np-field-label" style="margin-top:0">Прізвище</label><input type="text" id="upRcpLast" class="np-inp" value="' + esc(isEdit ? parseName(_editTtn.recipient_name, 1) : (r.last_name||'')) + '"></div>';
        html += '<div><label class="np-field-label" style="margin-top:0">Ім\'я</label><input type="text" id="upRcpFirst" class="np-inp" value="' + esc(isEdit ? parseName(_editTtn.recipient_name, 0) : (r.first_name||'')) + '"></div>';
        html += '</div>';
        html += '<label class="np-field-label">По батькові</label>';
        html += '<input type="text" id="upRcpMiddle" class="np-inp" value="' + esc(isEdit ? parseName(_editTtn.recipient_name, 2) : (r.middle_name||'')) + '">';

        html += '<div class="np-2col" style="margin-top:6px">';
        html += '<div><label class="np-field-label" style="margin-top:0">Телефон</label>';
        html += '<div class="np-ac-wrap"><input type="text" id="upRcpPhone" class="np-inp" value="' + esc(isEdit ? (_editTtn.recipient_phoneNumber||'') : (r.phone||'')) + '" placeholder="0671234567">';
        html += '<div class="np-ac-dd" id="upPhoneDd"></div></div></div>';
        html += '<div><label class="np-field-label" style="margin-top:0">Email</label><input type="email" id="upRcpEmail" class="np-inp" value="' + esc(r.email||'') + '"></div>';
        html += '</div>';

        // Org (optional)
        html += '<div style="display:flex;gap:12px;margin-top:8px">';
        html += '<label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:13px"><input type="checkbox" id="upIsOrg"> Юридична особа</label>';
        html += '</div>';
        html += '<div id="upOrgFields" style="display:none;margin-top:6px">';
        html += '<div class="np-2col">';
        html += '<div><label class="np-field-label" style="margin-top:0">Назва</label><input type="text" id="upRcpOrgName" class="np-inp"></div>';
        html += '<div><label class="np-field-label" style="margin-top:0">ІПН/ЄДРПОУ</label><input type="text" id="upRcpCode" class="np-inp" maxlength="10" placeholder="10 — ФОП, 8 — ТОВ"></div>';
        html += '</div></div>';

        // ── Address ──
        html += '<div class="np-form-section">Адреса одержувача</div>';
        html += '<label class="np-field-label" style="margin-top:0">Тип доставки</label>';
        html += '<select id="upDeliveryType" class="np-inp" style="height:auto;padding:4px 7px;">';
        html += '<option value="W2W"' + (isEdit && _editTtn.deliveryType==='W2W'?' selected':'') + '>На відділення (W2W)</option>';
        html += '<option value="W2D"' + (isEdit && _editTtn.deliveryType==='W2D'?' selected':'') + '>На адресу (W2D)</option>';
        html += '</select>';

        html += '<label class="np-field-label">Поштовий індекс</label>';
        html += '<input type="text" id="upPostcode" class="np-inp" value="' + esc(isEdit ? (_editTtn.postcode||'') : (r.postcode||'')) + '" placeholder="напр. 65062" maxlength="5">';

        html += '<label class="np-field-label">Місто (для довідки)</label>';
        html += '<input type="text" id="upCity" class="np-inp" value="' + esc(isEdit ? (_editTtn.recipient_city||'') : (r.city_hint||'')) + '" placeholder="Київ">';

        html += '<div id="upAddrSection" style="display:none;margin-top:6px">';
        html += '<label class="np-field-label" style="margin-top:0">Вулиця</label>';
        html += '<input type="text" id="upStreet" class="np-inp" placeholder="Вулиця">';
        html += '<div class="np-2col" style="margin-top:6px"><div><label class="np-field-label" style="margin-top:0">Будинок</label><input type="text" id="upBuilding" class="np-inp"></div>';
        html += '<div><label class="np-field-label" style="margin-top:0">Квартира</label><input type="text" id="upFlat" class="np-inp"></div></div>';
        html += '</div>';

        // ── Cargo ──
        html += '<div class="np-form-section">Вантаж</div>';
        html += '<label class="np-field-label" style="margin-top:0">Тип (SLA)</label>';
        html += '<select id="upType" class="np-inp" style="height:auto;padding:4px 7px;">';
        html += '<option value="STANDARD"' + ((isEdit ? _editTtn.type : (d.shipment_type||'STANDARD')) === 'STANDARD'?' selected':'') + '>Стандарт</option>';
        html += '<option value="EXPRESS"' + ((isEdit ? _editTtn.type : (d.shipment_type||'STANDARD')) === 'EXPRESS'?' selected':'') + '>Експрес</option>';
        html += '</select>';

        html += '<div class="np-2col" style="margin-top:6px">';
        html += '<div><label class="np-field-label" style="margin-top:0">Вага (кг)</label><input type="number" id="upWeight" class="np-inp" value="' + (isEdit ? ((parseFloat(_editTtn.weight)||0)/1000).toFixed(2) : (d.weight||'1')) + '" step="0.1" min="0.1"></div>';
        html += '<div><label class="np-field-label" style="margin-top:0">Місць</label><input type="number" id="upSeats" class="np-inp" value="1" min="1" step="1"></div>';
        html += '</div>';

        html += '<div class="np-2col" style="margin-top:6px;grid-template-columns:1fr 1fr 1fr">';
        html += '<div><label class="np-field-label" style="margin-top:0">Довжина</label><input type="number" id="upLength" class="np-inp" value="' + (isEdit ? _editTtn.length : (d.length||'30')) + '" min="1"></div>';
        html += '<div><label class="np-field-label" style="margin-top:0">Ширина</label><input type="number" id="upWidth" class="np-inp" value="' + (isEdit ? _editTtn.width : (d.width||'20')) + '" min="1"></div>';
        html += '<div><label class="np-field-label" style="margin-top:0">Висота</label><input type="number" id="upHeight" class="np-inp" value="' + (isEdit ? _editTtn.height : (d.height||'2')) + '" min="1"></div>';
        html += '</div>';

        html += '<label class="np-field-label">Опис вантажу</label>';
        html += '<input type="text" id="upDesc" class="np-inp" value="' + esc(isEdit ? (_editTtn.description||'') : (d.description || 'Канцелярські приладдя')) + '" maxlength="40">';

        var declared = isEdit ? parseFloat(_editTtn.declaredPrice) : (parseFloat(data.sum_total) || 200);
        if (!declared) declared = 200;
        var postPay  = isEdit ? parseFloat(_editTtn.postPayUah) : (parseFloat(data.cod_hint) || 0);

        html += '<div class="np-2col">';
        html += '<div><label class="np-field-label">Оголошена вартість (₴)</label><input type="number" id="upDeclared" class="np-inp" value="' + Math.ceil(declared) + '" min="1"></div>';
        html += '<div><label class="np-field-label">Накл. платіж (₴)</label><input type="number" id="upPostPay" class="np-inp" value="' + Math.ceil(postPay) + '" min="0" step="1"></div>';
        html += '</div>';

        html += '<label class="np-field-label">Платник доставки</label>';
        html += '<select id="upPayer" class="np-inp" style="height:auto;padding:4px 7px;">';
        html += '<option value="recipient">Одержувач</option>';
        html += '<option value="sender">Відправник</option>';
        html += '</select>';

        html += '<div id="upTtnError" class="np-form-error"></div>';

        html += '<div style="display:flex;gap:8px;margin-top:14px;padding-top:12px;border-top:1px solid var(--border)">';
        html += '<button type="button" class="btn btn-primary btn-sm" id="upTtnSubmitBtn">' + (isEdit ? 'Зберегти' : 'Створити ТТН') + '</button>';
        html += '<button type="button" class="btn btn-sm" id="upTtnCancelBtn">Скасувати</button>';
        html += '</div>';

        body.innerHTML = html;
        bindEvents(data);
    }

    function parseName(full, idx) {
        if (!full) return '';
        var parts = String(full).split(/\s+/);
        return parts[idx] || '';
    }

    function bindEvents(data) {
        var isEdit = !!_editTtn;
        var cancelBtn = document.getElementById('upTtnCancelBtn'); if (cancelBtn) cancelBtn.addEventListener('click', close);

        var deliveryTypeSel = document.getElementById('upDeliveryType');
        function toggleAddr() {
            document.getElementById('upAddrSection').style.display = (deliveryTypeSel.value === 'W2D') ? '' : 'none';
        }
        deliveryTypeSel.addEventListener('change', toggleAddr);
        toggleAddr();

        var orgCb = document.getElementById('upIsOrg');
        orgCb.addEventListener('change', function() {
            document.getElementById('upOrgFields').style.display = this.checked ? '' : 'none';
        });

        // Recipient phone autocomplete from counterparties
        var phoneInp = document.getElementById('upRcpPhone');
        var phoneDd  = document.getElementById('upPhoneDd');
        var tm;
        phoneInp.addEventListener('input', function() {
            clearTimeout(tm);
            var q = phoneInp.value.trim();
            if (q.length < 3) { phoneDd.style.display = 'none'; return; }
            tm = setTimeout(function() {
                fetch('/novaposhta/api/search_counterparty?mode=person&q=' + encodeURIComponent(q))
                    .then(function(r){ return r.json(); }).then(function(res) {
                        var items = (res && res.items) || [];
                        if (!items.length) { phoneDd.style.display='none'; return; }
                        phoneDd.innerHTML = items.slice(0, 10).map(function(it, i) {
                            return '<div class="np-ac-item" data-idx="'+i+'">'+esc(it.full_name||'—')
                                 + '<div class="np-ac-sub">'+esc(it.phone||'')+'</div></div>';
                        }).join('');
                        phoneDd._items = items;
                        phoneDd.style.display = 'block';
                    });
            }, 250);
        });
        phoneDd.addEventListener('mousedown', function(e) {
            var row = e.target.closest('.np-ac-item'); if (!row) return;
            var item = phoneDd._items[parseInt(row.dataset.idx, 10)];
            if (!item) return;
            var nameParts = (item.full_name||'').split(/\s+/);
            document.getElementById('upRcpLast').value   = nameParts[0] || '';
            document.getElementById('upRcpFirst').value  = nameParts[1] || '';
            document.getElementById('upRcpMiddle').value = nameParts.slice(2).join(' ');
            document.getElementById('upRcpPhone').value  = item.phone || phoneInp.value;
            phoneDd.style.display = 'none';
        });
        document.addEventListener('click', function(e) {
            if (!phoneInp.contains(e.target) && !phoneDd.contains(e.target)) phoneDd.style.display = 'none';
        });

        // Submit
        document.getElementById('upTtnSubmitBtn').addEventListener('click', function() {
            var btn = this;
            var errDiv = document.getElementById('upTtnError');
            errDiv.style.display = 'none';

            var postcode = (document.getElementById('upPostcode').value || '').replace(/[^0-9]/g, '');
            var phone = (document.getElementById('upRcpPhone').value || '').trim();
            var last  = (document.getElementById('upRcpLast').value  || '').trim();
            var first = (document.getElementById('upRcpFirst').value || '').trim();
            var deliveryType = deliveryTypeSel.value;

            if (!postcode || postcode.length !== 5) { errDiv.textContent = 'Невірний поштовий індекс (5 цифр)'; errDiv.style.display='block'; return; }
            if (!phone) { errDiv.textContent = 'Введіть телефон одержувача'; errDiv.style.display='block'; return; }

            if (deliveryType === 'W2D') {
                if (!document.getElementById('upStreet').value.trim() || !document.getElementById('upBuilding').value.trim()) {
                    errDiv.textContent = 'Для W2D потрібно заповнити вулицю і будинок';
                    errDiv.style.display = 'block'; return;
                }
            }

            btn.disabled = true;
            btn.textContent = isEdit ? 'Збереження…' : 'Створення…';

            var payload = {
                customerorder_id: _orderId,
                postcode: postcode,
                recipient_last_name:   last,
                recipient_first_name:  first,
                recipient_middle_name: document.getElementById('upRcpMiddle').value || '',
                recipient_phone: phone,
                recipient_email: document.getElementById('upRcpEmail').value || '',
                type: document.getElementById('upType').value,
                weight: parseFloat(document.getElementById('upWeight').value) || 1,
                length: parseInt(document.getElementById('upLength').value, 10) || 30,
                width:  parseInt(document.getElementById('upWidth').value,  10) || 20,
                height: parseInt(document.getElementById('upHeight').value, 10) || 2,
                seats:  parseInt(document.getElementById('upSeats').value, 10) || 1,
                description: document.getElementById('upDesc').value || '',
                declared_price: parseFloat(document.getElementById('upDeclared').value) || 0,
                post_pay: parseFloat(document.getElementById('upPostPay').value) || 0,
                paid_by: document.getElementById('upPayer').value,
            };
            if (deliveryType === 'W2D') {
                payload.street   = document.getElementById('upStreet').value || '';
                payload.building = document.getElementById('upBuilding').value || '';
                payload.flat     = document.getElementById('upFlat').value || '';
            }
            if (document.getElementById('upIsOrg').checked) {
                payload.recipient_name = document.getElementById('upRcpOrgName').value || '';
                payload.recipient_code = document.getElementById('upRcpCode').value || '';
            }

            var endpoint = isEdit ? '/ukrposhta/api/update_ttn' : '/ukrposhta/api/create_ttn';
            if (isEdit) payload.ttn_id = _editTtn.id;

            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(function(r){ return r.json(); }).then(function(res) {
                btn.disabled = false;
                btn.textContent = isEdit ? 'Зберегти' : 'Створити ТТН';
                if (!res.ok) {
                    errDiv.textContent = res.error || 'Невідома помилка';
                    errDiv.style.display = 'block';
                    return;
                }
                close();
                if (typeof _opts.onCreated === 'function') {
                    _opts.onCreated(res, _orderId);
                } else {
                    if (typeof showToast === 'function') {
                        showToast((isEdit ? 'Оновлено ' : 'Створено ') + (res.barcode || '—'));
                    }
                    setTimeout(function(){ window.location.reload(); }, 600);
                }
            }).catch(function() {
                btn.disabled = false;
                btn.textContent = isEdit ? 'Зберегти' : 'Створити ТТН';
                errDiv.textContent = 'Помилка зʼєднання';
                errDiv.style.display = 'block';
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        var closeBtn = document.getElementById('upTtnModalClose');
        if (closeBtn) closeBtn.addEventListener('click', close);
    });

    return { open: open, close: close };
})();
