<?php
/**
 * Custom settings view for Nova Poshta.
 * Sections: Active toggle, API connections, Default settings.
 * Variables from parent: $appKey, $app, $savedSettings
 */
require_once __DIR__ . '/../IntegrationSettingsService.php';
require_once __DIR__ . '/../../novaposhta/repositories/SenderRepository.php';

$connections = IntegrationSettingsService::getConnections('novaposhta');

// Load addresses, contacts, sender extra fields per sender_ref
$_addrMap = array();
$_contactMap = array();
$_senderExtra = array(); // use_payment_control, organization_id etc.
foreach ($connections as $c) {
    $sRef = isset($c['metadata']['sender_ref']) ? $c['metadata']['sender_ref'] : '';
    if ($sRef) {
        $_addrMap[$sRef]    = \Papir\Crm\SenderRepository::getAddresses($sRef);
        $_contactMap[$sRef] = \Papir\Crm\SenderRepository::getContacts($sRef);
        $raw = \Database::fetchRow('Papir', "SELECT organization_id, use_payment_control, courier_call_interval, courier_call_planned_weight, default_description FROM np_sender WHERE Ref = '" . \Database::escape('Papir', $sRef) . "'");
        if ($raw['ok'] && $raw['row']) $_senderExtra[$sRef] = $raw['row'];
    }
}

// Organizations for dropdown
$_orgsRes = \Database::fetchAll('Papir', "SELECT id, name FROM organization WHERE name NOT LIKE '[Архів]%' ORDER BY name");
$_orgs = ($_orgsRes['ok']) ? $_orgsRes['rows'] : array();
$isActive    = IntegrationSettingsService::get('novaposhta', 'is_active', '1') === '1';

// Defaults
$defaults = array(
    'default_service_type'   => array('label' => 'Тип доставки',    'value' => IntegrationSettingsService::get('novaposhta', 'default_service_type', 'WarehouseWarehouse'),
        'options' => array('WarehouseWarehouse' => 'Склад — Склад', 'WarehouseDoors' => 'Склад — Двері', 'DoorsWarehouse' => 'Двері — Склад', 'DoorsDoors' => 'Двері — Двері')),
    'default_payer_type'     => array('label' => 'Платник доставки', 'value' => IntegrationSettingsService::get('novaposhta', 'default_payer_type', 'Recipient'),
        'options' => array('Sender' => 'Відправник', 'Recipient' => 'Отримувач', 'ThirdPerson' => 'Третя особа')),
    'default_payment_method' => array('label' => 'Форма оплати',    'value' => IntegrationSettingsService::get('novaposhta', 'default_payment_method', 'Cash'),
        'options' => array('Cash' => 'Готівка', 'NonCash' => 'Безготівка')),
    'default_cargo_type'     => array('label' => 'Тип вантажу',     'value' => IntegrationSettingsService::get('novaposhta', 'default_cargo_type', 'Cargo'),
        'options' => array('Cargo' => 'Вантаж', 'Documents' => 'Документи', 'TiresWheels' => 'Шини/Диски', 'Pallet' => 'Палети')),
    'default_weight'         => array('label' => 'Вага за замовчуванням (кг)', 'value' => IntegrationSettingsService::get('novaposhta', 'default_weight', '0.5'), 'type' => 'number'),
    'default_seats_amount'   => array('label' => 'Кількість місць',            'value' => IntegrationSettingsService::get('novaposhta', 'default_seats_amount', '1'), 'type' => 'number'),
    'default_description'    => array('label' => 'Опис вантажу',               'value' => IntegrationSettingsService::get('novaposhta', 'default_description', 'Товар'), 'type' => 'text'),
);
?>

<style>
/* ── Active toggle ────────────────────────────────────────────────────── */
.app-active-row {
    display: inline-flex; align-items: center; gap: 10px;
    margin-bottom: 24px; padding: 8px 16px;
    background: var(--bg-card); border: 1px solid var(--border); border-radius: 20px;
    font-size: 13px;
}
.app-active-row > label:first-child { font-weight: 500; color: var(--text-secondary); }
.toggle-switch { position: relative; width: 44px; height: 24px; cursor: pointer; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-track {
    position: absolute; inset: 0; border-radius: 12px;
    background: #cbd5e1; transition: background .2s;
}
.toggle-switch input:checked + .toggle-track { background: #22c55e; }
.toggle-knob {
    position: absolute; top: 2px; left: 2px;
    width: 20px; height: 20px; border-radius: 50%;
    background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.2);
    transition: transform .2s;
}
.toggle-switch input:checked ~ .toggle-knob { transform: translateX(20px); }

/* ── Section headers ──────────────────────────────────────────────────── */
.np-section { margin-bottom: 28px; }
.np-section-title {
    font-size: 13px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .5px; color: var(--text-muted);
    margin-bottom: 12px;
}

/* ── Connection cards ─────────────────────────────────────────────────── */
.np-conn-list { display: flex; flex-direction: column; gap: 10px; }
.np-conn-card {
    border: 1px solid var(--border); border-radius: var(--radius);
    padding: 14px 18px; background: var(--bg-card);
    transition: border-color .15s;
}
.np-conn-card.is-default { border-color: #93c5fd; background: #f0f9ff; }
.np-conn-card.inactive { opacity: .55; }
.np-conn-head {
    display: flex; align-items: center; gap: 8px; margin-bottom: 10px;
}
.np-conn-name { font-size: 14px; font-weight: 600; flex: 1; }
.np-conn-badges { display: flex; gap: 6px; }
.np-badge {
    font-size: 10px; font-weight: 700; text-transform: uppercase;
    padding: 2px 7px; border-radius: 8px; letter-spacing: .3px;
}
.np-badge.default { background: #dbeafe; color: #1e40af; }
.np-badge.off { background: #f1f5f9; color: #94a3b8; }
.np-conn-meta { font-size: 12px; color: var(--text-muted); margin-bottom: 8px; }
.np-conn-api { display: flex; align-items: center; gap: 6px; }
.np-conn-api input {
    flex: 1; padding: 6px 10px;
    border: 1px solid var(--border); border-radius: 5px;
    font-size: 13px; font-family: monospace;
    background: var(--bg); color: var(--text);
}
.np-conn-api input:focus { outline: none; border-color: #475569; box-shadow: 0 0 0 3px rgba(71,85,105,.1); }
.np-conn-actions {
    display: flex; align-items: center; gap: 10px; margin-top: 10px;
    padding-top: 8px; border-top: 1px solid var(--border);
}
.np-conn-actions .set-default {
    font-size: 12px; padding: 0; border: none; background: none;
    cursor: pointer; color: var(--text-muted);
    display: flex; align-items: center; gap: 4px;
}
.np-conn-actions .set-default:hover { color: #1e40af; }
.np-conn-actions .set-default.active {
    color: #1e40af; font-weight: 600; cursor: default;
}
.np-conn-actions .set-default .star {
    font-size: 14px;
}
.np-conn-actions .set-default.active .star { color: #f59e0b; }
.np-conn-actions .conn-active-wrap {
    display: flex; align-items: center; gap: 5px;
    font-size: 12px; color: var(--text-muted); cursor: pointer;
}
.np-conn-actions .conn-active-wrap input { margin: 0; cursor: pointer; }

/* ── Connection details (addresses, contacts) ─────────────────────────── */
.np-conn-details {
    display: none; margin-top: 10px; padding-top: 10px;
    border-top: 1px solid var(--border);
}
.np-conn-details.open { display: block; }
.np-conn-toggle-details {
    font-size: 12px; color: var(--text-muted);
    background: none; border: none; cursor: pointer;
    display: flex; align-items: center; gap: 4px;
    padding: 0; margin-left: auto;
}
.np-conn-toggle-details:hover { color: var(--text); }
.np-detail-section { margin-bottom: 10px; }
.np-detail-title {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    color: var(--text-muted); letter-spacing: .4px; margin-bottom: 6px;
}
.np-detail-item {
    display: flex; align-items: center; gap: 8px;
    padding: 6px 8px; font-size: 12px; color: var(--text-secondary);
    border-radius: 4px; cursor: pointer; transition: background .1s;
}
.np-detail-item:hover { background: var(--bg); }
.np-detail-item.active { background: #eff6ff; font-weight: 500; }
.np-detail-item .def-star { color: #f59e0b; font-size: 13px; }
.np-detail-item .empty-star { color: #cbd5e1; font-size: 13px; }
.np-detail-item .dim { color: var(--text-muted); }
.np-detail-item .set-label {
    margin-left: auto; font-size: 11px; color: var(--text-muted);
    opacity: 0; transition: opacity .15s;
}
.np-detail-item:hover .set-label { opacity: 1; }
.np-detail-row-select {
    display: flex; align-items: center; gap: 8px;
    margin-top: 6px;
}
.np-detail-row-select label {
    font-size: 11px; font-weight: 600; color: var(--text-muted);
    white-space: nowrap;
}
.np-detail-row-select select {
    flex: 1; padding: 5px 8px; font-size: 12px;
    border: 1px solid var(--border); border-radius: 5px;
    background: var(--bg-card); color: var(--text);
}
.np-detail-chk {
    display: flex; align-items: center; gap: 6px;
    margin-top: 8px; font-size: 12px; color: var(--text-secondary);
}
.np-detail-chk input { margin: 0; }

/* ── Defaults form ────────────────────────────────────────────────────── */
.np-defaults-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 14px;
}
.np-defaults-grid .form-group { margin: 0; }
.np-defaults-grid label {
    display: block; font-size: 12px; font-weight: 600;
    color: var(--text-secondary); margin-bottom: 5px;
}
.np-defaults-grid select, .np-defaults-grid input {
    width: 100%; padding: 7px 10px;
    border: 1px solid var(--border); border-radius: 6px;
    font-size: 13px; font-family: inherit;
    background: var(--bg-card); color: var(--text);
}
.np-defaults-grid select:focus, .np-defaults-grid input:focus {
    outline: none; border-color: #475569; box-shadow: 0 0 0 3px rgba(71,85,105,.1);
}

/* ── Save bar ─────────────────────────────────────────────────────────── */
.np-save-bar {
    display: flex; align-items: center; gap: 10px;
    margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border);
}
.np-save-ok {
    display: none; align-items: center; gap: 4px;
    color: #15803d; font-size: 13px; font-weight: 500;
}
.np-save-ok.show { display: inline-flex; }

@media (max-width: 600px) {
    .np-defaults-grid { grid-template-columns: 1fr; }
}
</style>

<!-- ═══ Active toggle ═══ -->
<div class="app-active-row">
    <label>Додаток активний у проекті</label>
    <label class="toggle-switch">
        <input type="checkbox" id="npActiveToggle" <?php echo $isActive ? 'checked' : ''; ?>>
        <span class="toggle-track"></span>
        <span class="toggle-knob"></span>
    </label>
</div>

<!-- ═══ Connections ═══ -->
<div class="np-section">
    <div class="np-section-title">Підключення (API ключі)</div>
    <div class="np-conn-list" id="npConnList">
        <?php foreach ($connections as $c):
            $isDef = !empty($c['is_default']);
            $isOn  = !empty($c['is_active']);
            $cls   = 'np-conn-card' . ($isDef ? ' is-default' : '') . (!$isOn ? ' inactive' : '');
            $edrpou = isset($c['metadata']['edrpou']) ? $c['metadata']['edrpou'] : '';
        ?>
        <div class="<?php echo $cls; ?>" data-id="<?php echo (int)$c['id']; ?>">
            <div class="np-conn-head">
                <span class="np-conn-name"><?php echo htmlspecialchars($c['name']); ?></span>
                <div class="np-conn-badges">
                    <?php if ($isDef): ?><span class="np-badge default">основний</span><?php endif; ?>
                    <?php if (!$isOn): ?><span class="np-badge off">вимкнено</span><?php endif; ?>
                </div>
            </div>
            <?php if ($edrpou): ?>
            <div class="np-conn-meta">ЄДРПОУ: <?php echo htmlspecialchars($edrpou); ?></div>
            <?php endif; ?>
            <div class="np-conn-api">
                <input type="password" value="<?php echo htmlspecialchars($c['api_key']); ?>"
                       data-conn-id="<?php echo (int)$c['id']; ?>" placeholder="API ключ">
                <button type="button" class="toggle-secret" title="Показати/сховати" style="background:none;border:none;cursor:pointer;color:var(--text-muted);padding:4px;">
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M1.5 9s3-5.5 7.5-5.5S16.5 9 16.5 9s-3 5.5-7.5 5.5S1.5 9 1.5 9z" stroke="currentColor" stroke-width="1.4"/><circle cx="9" cy="9" r="2.5" stroke="currentColor" stroke-width="1.4"/></svg>
                </button>
            </div>
            <div class="np-conn-actions">
                <button type="button" class="set-default<?php echo $isDef ? ' active' : ''; ?>"
                        data-conn-id="<?php echo (int)$c['id']; ?>" title="<?php echo $isDef ? 'Основний відправник' : 'Зробити основним'; ?>">
                    <span class="star"><?php echo $isDef ? '&#9733;' : '&#9734;'; ?></span>
                    <?php echo $isDef ? 'Основний' : 'Зробити основним'; ?>
                </button>
                <label class="conn-active-wrap">
                    <input type="checkbox" class="conn-active-chk" data-conn-id="<?php echo (int)$c['id']; ?>"
                           <?php echo $isOn ? 'checked' : ''; ?>>
                    Активний
                </label>
                <button type="button" class="np-conn-toggle-details">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M3 4.5l3 3 3-3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                    Деталі
                </button>
            </div>

            <?php
            $sRef   = isset($c['metadata']['sender_ref']) ? $c['metadata']['sender_ref'] : '';
            $addrs  = isset($_addrMap[$sRef])    ? $_addrMap[$sRef]    : array();
            $conts  = isset($_contactMap[$sRef]) ? $_contactMap[$sRef] : array();
            $extra  = isset($_senderExtra[$sRef]) ? $_senderExtra[$sRef] : array();
            $orgId  = isset($extra['organization_id']) ? (int)$extra['organization_id'] : 0;
            $usePc  = !empty($extra['use_payment_control']);
            ?>
            <div class="np-conn-details">

                <!-- Addresses -->
                <?php if ($addrs): ?>
                <div class="np-detail-section">
                    <div class="np-detail-title">Адреса відправки <span class="dim">(натисніть щоб обрати основну)</span></div>
                    <?php foreach ($addrs as $a):
                        $isDfAddr = !empty($a['is_default']);
                        $addrDesc = $a['Description'] ?: ($a['CityDescription'] . ', ' . ($a['address_type'] === 'warehouse' ? 'Склад' : 'Адреса'));
                    ?>
                    <div class="np-detail-item<?php echo $isDfAddr ? ' active' : ''; ?>"
                         data-action="set-default-addr" data-sender="<?php echo htmlspecialchars($sRef); ?>"
                         data-ref="<?php echo htmlspecialchars($a['Ref']); ?>">
                        <span class="<?php echo $isDfAddr ? 'def-star' : 'empty-star'; ?>">
                            <?php echo $isDfAddr ? '&#9733;' : '&#9734;'; ?>
                        </span>
                        <span><?php echo htmlspecialchars($addrDesc); ?></span>
                        <?php if (!$isDfAddr): ?><span class="set-label">зробити основною</span><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Contacts -->
                <?php if ($conts): ?>
                <div class="np-detail-section">
                    <div class="np-detail-title">Контактна особа <span class="dim">(натисніть щоб обрати основну)</span></div>
                    <?php foreach ($conts as $cp):
                        $isDfCp = !empty($cp['is_default']);
                    ?>
                    <div class="np-detail-item<?php echo $isDfCp ? ' active' : ''; ?>"
                         data-action="set-default-contact" data-sender="<?php echo htmlspecialchars($sRef); ?>"
                         data-ref="<?php echo htmlspecialchars($cp['Ref']); ?>">
                        <span class="<?php echo $isDfCp ? 'def-star' : 'empty-star'; ?>">
                            <?php echo $isDfCp ? '&#9733;' : '&#9734;'; ?>
                        </span>
                        <span><?php echo htmlspecialchars($cp['full_name']); ?></span>
                        <span class="dim"><?php echo htmlspecialchars($cp['phone']); ?></span>
                        <?php if (!$isDfCp): ?><span class="set-label">зробити основною</span><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Organization + payment control + courier + description -->
                <?php
                $curInterval = isset($extra['courier_call_interval']) && $extra['courier_call_interval'] ? $extra['courier_call_interval'] : 'CityPickingTimeInterval7';
                $curWeight   = isset($extra['courier_call_planned_weight']) && $extra['courier_call_planned_weight'] ? (int)$extra['courier_call_planned_weight'] : 300;
                $curDesc     = isset($extra['default_description']) && $extra['default_description'] ? $extra['default_description'] : '';
                $_intervals  = array(
                    'CityPickingTimeInterval1' => '08:00–09:00', 'CityPickingTimeInterval2' => '09:00–10:00',
                    'CityPickingTimeInterval3' => '10:00–12:00', 'CityPickingTimeInterval4' => '12:00–14:00',
                    'CityPickingTimeInterval5' => '13:00–14:00', 'CityPickingTimeInterval6' => '14:00–16:00',
                    'CityPickingTimeInterval7' => '16:00–18:00', 'CityPickingTimeInterval8' => '18:00–19:00',
                    'CityPickingTimeInterval9' => '19:00–20:00', 'CityPickingTimeInterval10' => '20:00–21:00',
                );
                ?>
                <div class="np-detail-section">
                    <div class="np-detail-title">Налаштування відправника</div>

                    <div class="np-detail-row-select">
                        <label>Організація</label>
                        <select data-action="save-org" data-sender="<?php echo htmlspecialchars($sRef); ?>">
                            <option value="0">— не привʼязано —</option>
                            <?php foreach ($_orgs as $_o): ?>
                            <option value="<?php echo (int)$_o['id']; ?>"<?php echo $orgId === (int)$_o['id'] ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars($_o['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="np-detail-row-select" style="margin-top:8px">
                        <label>Опис відправлення</label>
                        <input type="text" data-action="save-desc" data-sender="<?php echo htmlspecialchars($sRef); ?>"
                               value="<?php echo htmlspecialchars($curDesc); ?>" placeholder="Товар"
                               maxlength="200" style="flex:1">
                    </div>

                    <label class="np-detail-chk">
                        <input type="checkbox" data-action="save-payment-control" data-sender="<?php echo htmlspecialchars($sRef); ?>"
                               <?php echo $usePc ? 'checked' : ''; ?>>
                        NovaPay (контроль оплати)
                    </label>
                </div>

                <div class="np-detail-section">
                    <div class="np-detail-title">Виклик кур'єра</div>
                    <div style="display:grid;grid-template-columns:1fr 120px;gap:8px;align-items:end;">
                        <div class="np-detail-row-select" style="margin:0">
                            <label>Інтервал</label>
                            <select data-action="save-courier" data-field="interval" data-sender="<?php echo htmlspecialchars($sRef); ?>">
                                <?php foreach ($_intervals as $iVal => $iLabel): ?>
                                <option value="<?php echo $iVal; ?>"<?php echo $curInterval === $iVal ? ' selected' : ''; ?>><?php echo $iLabel; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="np-detail-row-select" style="margin:0">
                            <label>Вага, кг</label>
                            <input type="number" min="1" step="1" data-action="save-courier" data-field="weight"
                                   data-sender="<?php echo htmlspecialchars($sRef); ?>"
                                   value="<?php echo $curWeight; ?>" style="width:100%">
                        </div>
                    </div>
                    <div style="margin-top:4px;font-size:11px;color:var(--text-muted)">
                        При перевищенні планової ваги на 10% автоматично створюється новий виклик.
                    </div>
                </div>

                <?php if (!$addrs && !$conts): ?>
                <div style="font-size:12px;color:var(--text-muted);padding:4px 0;">Немає синхронізованих даних. Натисніть «Оновити» для завантаження з API Нової Пошти.</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ═══ Defaults ═══ -->
<div class="np-section">
    <div class="np-section-title">Налаштування за замовчуванням</div>
    <form id="npDefaultsForm" autocomplete="off">
        <div class="np-defaults-grid">
            <?php foreach ($defaults as $dKey => $d): ?>
            <div class="form-group">
                <label><?php echo htmlspecialchars($d['label']); ?></label>
                <?php if (isset($d['options'])): ?>
                <select name="<?php echo $dKey; ?>">
                    <?php foreach ($d['options'] as $optVal => $optLabel): ?>
                    <option value="<?php echo $optVal; ?>"<?php echo $d['value'] === $optVal ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars($optLabel); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php else:
                    $type = isset($d['type']) ? $d['type'] : 'text';
                ?>
                <input type="<?php echo $type; ?>" name="<?php echo $dKey; ?>"
                       value="<?php echo htmlspecialchars($d['value']); ?>"
                       <?php echo $type === 'number' ? 'step="0.1" min="0"' : ''; ?>>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="np-save-bar">
            <button type="submit" class="btn btn-primary">Зберегти</button>
            <span class="np-save-ok" id="npSaveOk">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8.5l3 3 7-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Збережено
            </span>
        </div>
    </form>
</div>

<script>
(function() {
    // ── Toggle secret ──
    document.querySelectorAll('.toggle-secret').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var inp = this.closest('.np-conn-api').querySelector('input');
            inp.type = inp.type === 'password' ? 'text' : 'password';
        });
    });

    // ── Toggle details panels ──
    document.querySelectorAll('.np-conn-toggle-details').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var details = this.closest('.np-conn-card').querySelector('.np-conn-details');
            details.classList.toggle('open');
            var svg = this.querySelector('svg');
            svg.style.transform = details.classList.contains('open') ? 'rotate(180deg)' : '';
        });
    });

    // ── Set default address ──
    document.querySelectorAll('[data-action="set-default-addr"]').forEach(function(el) {
        el.addEventListener('click', function() {
            var sender = this.dataset.sender;
            var ref = this.dataset.ref;
            var section = this.closest('.np-detail-section');
            var fd = new FormData();
            fd.append('sender_ref', sender);
            fd.append('address_ref', ref);
            fetch('/novaposhta/api/set_default_address', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.ok) return;
                section.querySelectorAll('.np-detail-item').forEach(function(it) {
                    it.classList.remove('active');
                    var star = it.querySelector('span:first-child');
                    star.className = 'empty-star'; star.innerHTML = '&#9734;';
                    var lbl = it.querySelector('.set-label');
                    if (!lbl) { lbl = document.createElement('span'); lbl.className = 'set-label'; lbl.textContent = 'зробити основною'; it.appendChild(lbl); }
                    else { lbl.style.display = ''; }
                });
                el.classList.add('active');
                var star = el.querySelector('span:first-child');
                star.className = 'def-star'; star.innerHTML = '&#9733;';
                var lbl = el.querySelector('.set-label');
                if (lbl) lbl.style.display = 'none';
            });
        });
    });

    // ── Set default contact ──
    document.querySelectorAll('[data-action="set-default-contact"]').forEach(function(el) {
        el.addEventListener('click', function() {
            var sender = this.dataset.sender;
            var ref = this.dataset.ref;
            var section = this.closest('.np-detail-section');
            var fd = new FormData();
            fd.append('sender_ref', sender);
            fd.append('contact_ref', ref);
            fetch('/novaposhta/api/set_default_contact', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.ok) return;
                section.querySelectorAll('.np-detail-item').forEach(function(it) {
                    it.classList.remove('active');
                    var star = it.querySelector('span:first-child');
                    star.className = 'empty-star'; star.innerHTML = '&#9734;';
                    var lbl = it.querySelector('.set-label');
                    if (!lbl) { lbl = document.createElement('span'); lbl.className = 'set-label'; lbl.textContent = 'зробити основною'; it.appendChild(lbl); }
                    else { lbl.style.display = ''; }
                });
                el.classList.add('active');
                var star = el.querySelector('span:first-child');
                star.className = 'def-star'; star.innerHTML = '&#9733;';
                var lbl = el.querySelector('.set-label');
                if (lbl) lbl.style.display = 'none';
            });
        });
    });

    // ── Save organization binding ──
    document.querySelectorAll('[data-action="save-org"]').forEach(function(sel) {
        sel.addEventListener('change', function() {
            var fd = new FormData();
            fd.append('sender_ref', this.dataset.sender);
            fd.append('organization_id', this.value);
            fetch('/novaposhta/api/save_sender_org', { method: 'POST', body: fd });
        });
    });

    // ── Save payment control ──
    document.querySelectorAll('[data-action="save-payment-control"]').forEach(function(chk) {
        chk.addEventListener('change', function() {
            var fd = new FormData();
            fd.append('sender_ref', this.dataset.sender);
            fd.append('use_payment_control', this.checked ? 1 : 0);
            fetch('/novaposhta/api/save_sender_settings', { method: 'POST', body: fd });
        });
    });

    // ── Save default description ──
    document.querySelectorAll('[data-action="save-desc"]').forEach(function(inp) {
        inp.addEventListener('change', function() {
            var fd = new FormData();
            fd.append('sender_ref', this.dataset.sender);
            fd.append('default_description', this.value);
            fetch('/novaposhta/api/save_sender_settings', { method: 'POST', body: fd });
        });
    });

    // ── Save courier call settings ──
    document.querySelectorAll('[data-action="save-courier"]').forEach(function(el) {
        el.addEventListener('change', function() {
            var sender = this.dataset.sender;
            var card = this.closest('.np-conn-details');
            var interval = card.querySelector('[data-action="save-courier"][data-field="interval"]').value;
            var weight = card.querySelector('[data-action="save-courier"][data-field="weight"]').value;
            var fd = new FormData();
            fd.append('sender_ref', sender);
            fd.append('courier_call_interval', interval);
            fd.append('courier_call_planned_weight', weight);
            fetch('/novaposhta/api/save_sender_settings', { method: 'POST', body: fd });
        });
    });

    // ── Active toggle (app level) ──
    var activeToggle = document.getElementById('npActiveToggle');
    activeToggle.addEventListener('change', function() {
        fetch('/integrations/api/toggle_app', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ app_key: 'novaposhta', is_active: this.checked ? 1 : 0 })
        });
    });

    // ── Save connection changes (API key, active, default) ──
    function saveConn(connId, data) {
        // Get current card data
        var card = document.querySelector('.np-conn-card[data-id="' + connId + '"]');
        var input = card.querySelector('input[data-conn-id="' + connId + '"]');
        var activeChk = card.querySelector('.conn-active-chk');

        var payload = Object.assign({
            id: connId,
            app_key: 'novaposhta',
            name: card.querySelector('.np-conn-name').textContent.trim(),
            api_key: input.value,
            is_active: activeChk.checked ? 1 : 0
        }, data);

        return fetch('/integrations/api/save_connection', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        }).then(function(r) { return r.json(); });
    }

    // Set default
    document.querySelectorAll('.set-default').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var connId = parseInt(this.dataset.connId);
            saveConn(connId, { is_default: 1 }).then(function() {
                document.querySelectorAll('.set-default').forEach(function(b) { b.classList.remove('active'); });
                btn.classList.add('active');
                document.querySelectorAll('.np-conn-card').forEach(function(c) {
                    c.classList.remove('is-default');
                    c.querySelector('.np-badge.default')?.remove();
                });
                btn.classList.add('active');
                btn.closest('.np-conn-card').classList.add('is-default');
            });
        });
    });

    // Toggle connection active
    document.querySelectorAll('.conn-active-chk').forEach(function(chk) {
        chk.addEventListener('change', function() {
            var connId = parseInt(this.dataset.connId);
            var card = this.closest('.np-conn-card');
            if (this.checked) { card.classList.remove('inactive'); }
            else { card.classList.add('inactive'); }
            saveConn(connId, {});
        });
    });

    // Save API key on blur
    document.querySelectorAll('input[data-conn-id]').forEach(function(inp) {
        inp.addEventListener('change', function() {
            var connId = parseInt(this.dataset.connId);
            saveConn(connId, {});
        });
    });

    // ── Save defaults form ──
    var defForm = document.getElementById('npDefaultsForm');
    defForm.addEventListener('submit', function(e) {
        e.preventDefault();
        var fields = defForm.querySelectorAll('select, input[name]');
        var settings = [];
        fields.forEach(function(f) {
            settings.push({ key: f.name, value: f.value, secret: 0 });
        });

        var btn = defForm.querySelector('[type="submit"]');
        btn.disabled = true; btn.textContent = 'Збереження...';

        fetch('/integrations/api/save_settings', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ app_key: 'novaposhta', settings: settings })
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            btn.disabled = false; btn.textContent = 'Зберегти';
            if (d.ok) {
                var msg = document.getElementById('npSaveOk');
                msg.classList.add('show');
                setTimeout(function() { msg.classList.remove('show'); }, 2500);
            }
        });
    });
}());
</script>