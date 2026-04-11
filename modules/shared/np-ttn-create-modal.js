/**
 * NpTtnCreateModal — shared modal for creating Nova Poshta TTN.
 *
 * Usage:
 *   NpTtnCreateModal.open(orderId, { onCreated: function(res, orderId) { ... } });
 *   NpTtnCreateModal.open(0);  // standalone TTN without order context
 *
 * Requires in DOM:
 *   #newTtnModal      — modal-overlay container
 *   #npTtnBody        — modal body (form target)
 *   #newTtnModalClose — close button
 */
window.NpTtnCreateModal = (function() {
    var _orderId = 0;
    var _opts = {};
    var _prefillData = null;

    function esc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function open(orderId, opts) {
        _orderId = parseInt(orderId) || 0;
        _opts = opts || {};
        var modal = document.getElementById('newTtnModal');
        var body  = document.getElementById('npTtnBody');
        if (!modal || !body) return;
        body.innerHTML = '<div style="text-align:center;color:#9ca3af;padding:30px;">Завантаження…</div>';
        modal.classList.add('open');

        fetch('/novaposhta/api/get_ttn_form?order_id=' + _orderId)
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
        html += '<div><label class="np-field-label" style="margin-top:0">ЄДРПОУ</label>';
        html += '<div class="np-ac-wrap"><input type="text" id="npRcpEdrpou" class="np-inp" value="' + esc(recipient.edrpou||'') + '" placeholder="12345678" maxlength="10" autocomplete="off">';
        html += '<div class="np-ac-dd" id="npEdrpouDd"></div></div></div>';
        html += '<div><label class="np-field-label" style="margin-top:0">Контактна особа</label><input type="text" id="npRcpContactPerson" class="np-inp" value="' + esc(recipient.contact_person||'') + '" placeholder="Прізвище Ім\'я"></div>';
        html += '</div></div>';
        html += '<label class="np-field-label">Телефон</label>';
        html += '<div class="np-ac-wrap"><input type="text" id="npRcpPhone" class="np-inp" value="' + esc(recipient.phone||'') + '" placeholder="0671234567" autocomplete="off">';
        html += '<div class="np-ac-dd" id="npPhoneDd"></div></div>';
        html += '<input type="hidden" id="npCounterpartyId" value="' + esc(recipient.counterparty_id||0) + '">';

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

        // ── Auto NonCash when Organization or Sender pays
        var payMethodSel = document.getElementById('npPayMethod');
        var payerTypeSel = document.getElementById('npPayerType');
        function autoPaymentMethod() {
            var rcpSel = document.querySelector('input[name="npRcpType"]:checked');
            var isOrg = rcpSel && rcpSel.value === 'Organization';
            var isSenderPays = payerTypeSel && payerTypeSel.value === 'Sender';
            if (isOrg || isSenderPays) {
                payMethodSel.value = 'NonCash';
            }
        }
        rcpTypeRadios.forEach(function(r) { r.addEventListener('change', autoPaymentMethod); });
        if (payerTypeSel) payerTypeSel.addEventListener('change', autoPaymentMethod);
        autoPaymentMethod();

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

        // Autocomplete helper.
        function makeAc(inpId, ddId, hiddenId, fetchFn, renderFn, opts) {
            var inp    = document.getElementById(inpId);
            var dd     = document.getElementById(ddId);
            var hidden = document.getElementById(hiddenId);
            if (!inp || !dd || !hidden) return;
            var minChars = (opts && typeof opts.minChars === 'number') ? opts.minChars : 2;
            var timer;
            function doFetch() {
                clearTimeout(timer);
                var q = inp.value.trim();
                if (q.length < minChars) { dd.style.display = 'none'; return; }
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
            }
            inp.addEventListener('input', doFetch);
            if (minChars === 0) {
                inp.addEventListener('focus', doFetch);
            }
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
            if (wh) { wh.focus(); }
        });

        makeAc('npWhInput', 'npWhDd', 'npWhRef',
            function(q, cb) {
                var cityRef = (document.getElementById('npCityRef') || {}).value || '';
                if (!cityRef) { cb([]); return; }
                fetch('/novaposhta/api/search_warehouse?city_ref=' + encodeURIComponent(cityRef)
                    + '&q=' + encodeURIComponent(q) + '&sender_ref=' + encodeURIComponent(curSenderRef()))
                    .then(function(r){ return r.json(); }).then(function(res){ cb(res.warehouses||[]); });
            },
            function(w) { return { label: 'Відд. №' + w.Number + (w.ShortAddress ? ': ' + w.ShortAddress : ''), sub: w.Description }; },
            { minChars: 0 }
        );

        // ── Counterparty live-search: phone (person) / EDRPOU (org) ──
        function bindCpSearch(inputId, ddId, mode, onPick) {
            var inp = document.getElementById(inputId);
            var dd  = document.getElementById(ddId);
            if (!inp || !dd) return;
            var timer;
            function hide() { dd.style.display = 'none'; }
            function fetchItems() {
                clearTimeout(timer);
                var q = inp.value.trim();
                if (q.length < 3) { hide(); return; }
                timer = setTimeout(function() {
                    fetch('/novaposhta/api/search_counterparty?mode=' + mode + '&q=' + encodeURIComponent(q))
                        .then(function(r){ return r.json(); })
                        .then(function(res) {
                            var items = (res && res.ok && res.items) ? res.items : [];
                            if (!items.length) { hide(); return; }
                            dd.innerHTML = items.map(function(it, i) {
                                var label, sub;
                                if (mode === 'person') {
                                    label = (it.full_name || '—');
                                    sub   = it.phone || '';
                                } else {
                                    label = it.full_name || '—';
                                    sub   = 'ЄДРПОУ: ' + (it.edrpou || '') + (it.phone ? ' · ' + it.phone : '');
                                }
                                return '<div class="np-ac-item" data-idx="' + i + '">' + esc(label)
                                    + (sub ? '<div class="np-ac-sub">' + esc(sub) + '</div>' : '') + '</div>';
                            }).join('');
                            dd._items = items;
                            dd.style.display = 'block';
                        })
                        .catch(hide);
                }, 280);
            }
            inp.addEventListener('input', fetchItems);
            inp.addEventListener('focus', function() {
                if (inp.value.trim().length >= 3) fetchItems();
            });
            dd.addEventListener('mousedown', function(e) {
                var row = e.target.closest('.np-ac-item');
                if (!row) return;
                var idx = parseInt(row.dataset.idx, 10);
                var item = dd._items && dd._items[idx];
                if (item) onPick(item);
                hide();
            });
            document.addEventListener('click', function(e) {
                if (!inp.contains(e.target) && !dd.contains(e.target)) hide();
            });
        }

        bindCpSearch('npRcpPhone', 'npPhoneDd', 'person', function(item) {
            // Switch to PrivatePerson mode
            var rcpPerson = document.querySelector('input[name="npRcpType"][value="PrivatePerson"]');
            if (rcpPerson && !rcpPerson.checked) { rcpPerson.checked = true; toggleRcpType(); autoPaymentMethod(); }
            document.getElementById('npRcpLast').value  = item.last_name  || '';
            document.getElementById('npRcpFirst').value = item.first_name || '';
            document.getElementById('npRcpPhone').value = item.phone      || document.getElementById('npRcpPhone').value;
            document.getElementById('npCounterpartyId').value = item.counterparty_id || 0;
        });

        bindCpSearch('npRcpEdrpou', 'npEdrpouDd', 'org', function(item) {
            // Switch to Organization mode
            var rcpOrg = document.querySelector('input[name="npRcpType"][value="Organization"]');
            if (rcpOrg && !rcpOrg.checked) { rcpOrg.checked = true; toggleRcpType(); autoPaymentMethod(); }
            document.getElementById('npRcpOrgName').value = item.full_name || '';
            document.getElementById('npRcpEdrpou').value  = item.edrpou    || '';
            if (item.phone) {
                document.getElementById('npRcpPhone').value = item.phone;
            }
            document.getElementById('npCounterpartyId').value = item.counterparty_id || 0;
        });

        // If user edits recipient identity fields manually — drop the cached
        // counterparty_id so the created NP recipient isn't attached to a stale card.
        // (Programmatic .value assignments in pick handlers don't fire 'input', so
        // this only runs on real user typing.)
        ['npRcpLast','npRcpFirst','npRcpOrgName'].forEach(function(id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('input', function() {
                var cpEl = document.getElementById('npCounterpartyId');
                if (cpEl) cpEl.value = 0;
            });
        });

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
                'counterparty_id='         + (parseInt(((document.getElementById('npCounterpartyId')||{}).value||'0'))||0),
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
                if (typeof _opts.onCreated === 'function') {
                    _opts.onCreated(res, _orderId);
                } else {
                    if (typeof showToast === 'function') {
                        showToast('ТТН ' + (res.int_doc_number || '') + ' створено ✓');
                    }
                    if (res.ttn_id && typeof TtnDetailModal !== 'undefined') {
                        TtnDetailModal.open(res.ttn_id);
                    }
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
        if (typeof makeDraggable === 'function') {
            var m = document.getElementById('newTtnModal');
            if (m) makeDraggable(m);
        }
    });

    return { open: open, close: close };
}());