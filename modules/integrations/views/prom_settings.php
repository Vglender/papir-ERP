<?php
/**
 * Custom settings view for Prom.ua.
 * Sections: Active toggle, API token, Connection test, Mappings, Default settings.
 * Variables from parent: $appKey, $app, $savedSettings
 */
require_once __DIR__ . '/../IntegrationSettingsService.php';

$isActive  = IntegrationSettingsService::get('prom', 'is_active', '1') === '1';
$authToken = IntegrationSettingsService::get('prom', 'auth_token', '');
$hasToken  = ($authToken !== '');

// ── Load Papir dictionaries ─────────────────────────────────────────────
$_papirStatuses = array();
$r = \Database::fetchAll('Papir',
    "SELECT s.code, i.name FROM order_status s
     LEFT JOIN order_status_i18n i ON i.code = s.code AND i.language_id = 2
     ORDER BY s.sort_order");
if ($r['ok']) foreach ($r['rows'] as $row) $_papirStatuses[$row['code']] = $row['name'];

$_papirDelivery = array();
$r = \Database::fetchAll('Papir', "SELECT id, code, name_uk FROM delivery_method WHERE status = 1 ORDER BY sort_order");
if ($r['ok']) foreach ($r['rows'] as $row) $_papirDelivery[$row['id']] = $row['name_uk'];

$_papirPayment = array();
$r = \Database::fetchAll('Papir', "SELECT id, code, name_uk FROM payment_method WHERE status = 1 ORDER BY sort_order");
if ($r['ok']) foreach ($r['rows'] as $row) $_papirPayment[$row['id']] = $row['name_uk'];

// ── Load current mappings from DB ────────────────────────────────────────
$_statusMap = array(); // papir_code => prom_status_id
$r = \Database::fetchAll('Papir', "SELECT papir_code, site_status_id FROM order_status_site_mapping WHERE site_id = 3");
if ($r['ok']) foreach ($r['rows'] as $row) $_statusMap[$row['papir_code']] = $row['site_status_id'];

$_deliveryMap = array(); // shipping_code => delivery_method_id
$r = \Database::fetchAll('Papir', "SELECT shipping_code, delivery_method_id, delivery_code FROM site_delivery_method_map WHERE shipping_code LIKE 'prom.%'");
if ($r['ok']) foreach ($r['rows'] as $row) $_deliveryMap[$row['shipping_code']] = $row['delivery_method_id'];

$_paymentMap = array(); // payment_code => payment_method_id
$r = \Database::fetchAll('Papir', "SELECT payment_code, payment_method_id FROM site_payment_method_map WHERE payment_code LIKE 'prom.%'");
if ($r['ok']) foreach ($r['rows'] as $row) $_paymentMap[$row['payment_code']] = $row['payment_method_id'];

// ── Prom dictionaries (static — from API docs) ──────────────────────────
$promStatuses = array(
    0 => 'Новий (pending)',
    1 => 'Прийнятий (received)',
    3 => 'Виконаний (delivered)',
    4 => 'Скасований (canceled)',
    6 => 'Оплачений (paid)',
);

$promDeliveryTypes = array(
    'prom.nova_poshta'      => 'Нова Пошта',
    'prom.ukrposhta'        => 'Укрпошта',
    'prom.meest'            => 'Meest',
    'prom.meest_express'    => 'Meest Express',
    'prom.delivery_auto'    => 'Delivery',
    'prom.rozetka_delivery' => 'Магазини Rozetka',
    'prom.pickup'           => 'Самовивіз',
);

$promPaymentTypes = array(
    'prom.bank'             => 'Оплата на рахунок',
    'prom.cash_on_delivery' => 'Накладений платіж',
    'prom.online'           => 'Пром-оплата',
    'prom.installment'      => 'Оплатити частинами',
);

// Default settings
$defaults = array(
    'default_order_status_on_accept' => array(
        'label'   => 'Статус замовлення при прийнятті',
        'value'   => IntegrationSettingsService::get('prom', 'default_order_status_on_accept', 'received'),
        'options' => array(
            'received'  => 'Прийнято (received)',
            'paid'      => 'Оплачено (paid)',
        ),
    ),
    'default_delivery_type' => array(
        'label'   => 'Тип доставки для ТТН',
        'value'   => IntegrationSettingsService::get('prom', 'default_delivery_type', 'nova_poshta'),
        'options' => array(
            'nova_poshta' => 'Нова Пошта',
            'ukrposhta'   => 'Укрпошта',
            'meest'       => 'Meest',
        ),
    ),
    'sync_interval_minutes' => array(
        'label' => 'Інтервал синхронізації замовлень (хв)',
        'value' => IntegrationSettingsService::get('prom', 'sync_interval_minutes', '15'),
        'type'  => 'number',
    ),
    'auto_set_ttn' => array(
        'label'   => 'Автоматично відправляти ТТН в Prom',
        'value'   => IntegrationSettingsService::get('prom', 'auto_set_ttn', '0'),
        'type'    => 'toggle',
    ),
);
?>

<style>
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

.prom-section { margin-bottom: 28px; }
.prom-section-title {
    font-size: 13px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .5px; color: var(--text-muted);
    margin-bottom: 12px;
}
.prom-token-row {
    display: flex; align-items: center; gap: 8px; margin-bottom: 12px;
}
.prom-token-row input {
    flex: 1; padding: 9px 12px;
    border: 1px solid var(--border); border-radius: 6px;
    font-size: 14px; font-family: monospace;
    background: var(--bg-card); color: var(--text);
}
.prom-token-row input:focus {
    outline: none; border-color: #475569;
    box-shadow: 0 0 0 3px rgba(71,85,105,.12);
}
.prom-test-result {
    font-size: 13px; padding: 8px 14px;
    border-radius: 6px; display: none; margin-top: 10px; margin-bottom: 16px;
}
.prom-test-result.ok { display: block; background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.prom-test-result.err { display: block; background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
.prom-conn-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; border-radius: 8px;
    font-size: 12px; font-weight: 600; margin-bottom: 20px;
}
.prom-conn-badge.connected { background: #dcfce7; color: #166534; }
.prom-conn-badge.disconnected { background: #fef2f2; color: #991b1b; }
.prom-defaults-grid {
    display: grid; gap: 14px;
}
.prom-defaults-grid .form-group label,
.prom-map-table label {
    display: block; font-size: 13px; font-weight: 600;
    color: var(--text-secondary); margin-bottom: 6px;
}
.prom-defaults-grid .form-group select,
.prom-defaults-grid .form-group input {
    width: 100%; padding: 9px 12px;
    border: 1px solid var(--border); border-radius: 6px;
    font-size: 14px; font-family: inherit;
    background: var(--bg-card); color: var(--text);
}
.prom-defaults-grid .form-group select:focus,
.prom-defaults-grid .form-group input:focus {
    outline: none; border-color: #475569;
    box-shadow: 0 0 0 3px rgba(71,85,105,.12);
}
.prom-toggle-row {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 0;
}
.prom-toggle-row label { font-size: 13px; font-weight: 600; color: var(--text-secondary); flex: 1; }

/* ── Mapping tables ──────────────────────────────────────────────────── */
.prom-map-table {
    width: 100%; border-collapse: collapse; font-size: 13px;
}
.prom-map-table th {
    text-align: left; padding: 8px 10px;
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .4px; color: var(--text-muted);
    border-bottom: 2px solid var(--border);
}
.prom-map-table td {
    padding: 6px 10px; border-bottom: 1px solid var(--border);
    vertical-align: middle;
}
.prom-map-table tr:last-child td { border-bottom: none; }
.prom-map-table .prom-label {
    font-weight: 500; color: var(--text); white-space: nowrap;
}
.prom-map-table select {
    width: 100%; padding: 6px 10px;
    border: 1px solid var(--border); border-radius: 5px;
    font-size: 13px; font-family: inherit;
    background: var(--bg-card); color: var(--text);
}
.prom-map-table select:focus {
    outline: none; border-color: #475569;
    box-shadow: 0 0 0 3px rgba(71,85,105,.12);
}
.prom-map-arrow {
    color: var(--text-muted); font-size: 16px; text-align: center;
}
</style>

<!-- Active toggle -->
<div class="app-active-row">
    <label>Додаток активний</label>
    <label class="toggle-switch">
        <input type="checkbox" id="promActiveToggle" <?php echo $isActive ? 'checked' : ''; ?>>
        <span class="toggle-track"></span>
        <span class="toggle-knob"></span>
    </label>
</div>

<!-- Connection status badge -->
<?php if ($hasToken): ?>
<div class="prom-conn-badge connected">
    <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M3 8.5l3 3 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    Авторизовано
</div>
<?php else: ?>
<div class="prom-conn-badge disconnected">
    <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M4 4l8 8M12 4l-8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    Не підключено
</div>
<?php endif; ?>

<!-- API Token section -->
<div class="prom-section">
    <div class="prom-section-title">API Токен</div>
    <div class="prom-token-row">
        <input type="password" id="promToken" value="<?php echo htmlspecialchars($authToken); ?>" placeholder="Bearer токен з кабінету Prom.ua">
        <button type="button" class="btn btn-secondary" id="promToggleShow" title="Показати/сховати" style="padding:8px 10px;">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M1.5 9s3-5.5 7.5-5.5S16.5 9 16.5 9s-3 5.5-7.5 5.5S1.5 9 1.5 9z" stroke="currentColor" stroke-width="1.4"/><circle cx="9" cy="9" r="2.5" stroke="currentColor" stroke-width="1.4"/></svg>
        </button>
    </div>
    <div style="display:flex;gap:8px;">
        <button type="button" class="btn btn-primary" id="promSaveToken">Зберегти</button>
        <button type="button" class="btn btn-secondary" id="promTestBtn">Перевірити з'єднання</button>
    </div>
    <div class="prom-test-result" id="promTestResult"></div>
</div>

<!-- ── Status Mapping ─────────────────────────────────────────────────── -->
<div class="prom-section">
    <div class="prom-section-title">Мепінг статусів замовлень</div>
    <table class="prom-map-table" id="promStatusMap">
        <thead>
            <tr><th>Papir</th><th></th><th>Prom.ua</th></tr>
        </thead>
        <tbody>
        <?php foreach ($_papirStatuses as $code => $name):
            $currentPromId = isset($_statusMap[$code]) ? (int) $_statusMap[$code] : -1;
        ?>
            <tr>
                <td class="prom-label"><?php echo htmlspecialchars($name); ?></td>
                <td class="prom-map-arrow">&rarr;</td>
                <td>
                    <select name="status_<?php echo $code; ?>" data-papir="<?php echo $code; ?>">
                        <option value="-1" <?php echo $currentPromId === -1 ? 'selected' : ''; ?>>— не мепити —</option>
                        <?php foreach ($promStatuses as $promId => $promLabel): ?>
                        <option value="<?php echo $promId; ?>" <?php echo $currentPromId === $promId ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($promLabel); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div style="padding-top:10px;">
        <button type="button" class="btn btn-primary" id="promSaveStatusMap">Зберегти мепінг</button>
        <span class="app-settings-saved" id="promStatusSaved" style="display:none;">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8.5l3 3 7-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Збережено
        </span>
    </div>
</div>

<!-- ── Delivery Mapping ───────────────────────────────────────────────── -->
<div class="prom-section">
    <div class="prom-section-title">Мепінг способів доставки</div>
    <table class="prom-map-table" id="promDeliveryMap">
        <thead>
            <tr><th>Prom.ua</th><th></th><th>Papir</th></tr>
        </thead>
        <tbody>
        <?php foreach ($promDeliveryTypes as $promCode => $promLabel):
            $currentDm = isset($_deliveryMap[$promCode]) ? (int) $_deliveryMap[$promCode] : 0;
        ?>
            <tr>
                <td class="prom-label"><?php echo htmlspecialchars($promLabel); ?></td>
                <td class="prom-map-arrow">&rarr;</td>
                <td>
                    <select name="del_<?php echo $promCode; ?>" data-prom="<?php echo $promCode; ?>">
                        <option value="0" <?php echo $currentDm === 0 ? 'selected' : ''; ?>>— не мепити —</option>
                        <?php foreach ($_papirDelivery as $dmId => $dmName): ?>
                        <option value="<?php echo $dmId; ?>" <?php echo $currentDm === (int)$dmId ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dmName); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div style="padding-top:10px;">
        <button type="button" class="btn btn-primary" id="promSaveDeliveryMap">Зберегти мепінг</button>
        <span class="app-settings-saved" id="promDeliverySaved" style="display:none;">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8.5l3 3 7-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Збережено
        </span>
    </div>
</div>

<!-- ── Payment Mapping ────────────────────────────────────────────────── -->
<div class="prom-section">
    <div class="prom-section-title">Мепінг способів оплати</div>
    <table class="prom-map-table" id="promPaymentMap">
        <thead>
            <tr><th>Prom.ua</th><th></th><th>Papir</th></tr>
        </thead>
        <tbody>
        <?php foreach ($promPaymentTypes as $promCode => $promLabel):
            $currentPm = isset($_paymentMap[$promCode]) ? (int) $_paymentMap[$promCode] : 0;
        ?>
            <tr>
                <td class="prom-label"><?php echo htmlspecialchars($promLabel); ?></td>
                <td class="prom-map-arrow">&rarr;</td>
                <td>
                    <select name="pay_<?php echo $promCode; ?>" data-prom="<?php echo $promCode; ?>">
                        <option value="0" <?php echo $currentPm === 0 ? 'selected' : ''; ?>>— не мепити —</option>
                        <?php foreach ($_papirPayment as $pmId => $pmName): ?>
                        <option value="<?php echo $pmId; ?>" <?php echo $currentPm === (int)$pmId ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pmName); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div style="padding-top:10px;">
        <button type="button" class="btn btn-primary" id="promSavePaymentMap">Зберегти мепінг</button>
        <span class="app-settings-saved" id="promPaymentSaved" style="display:none;">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8.5l3 3 7-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Збережено
        </span>
    </div>
</div>

<!-- Default settings -->
<div class="prom-section">
    <div class="prom-section-title">Налаштування за замовчуванням</div>
    <div class="prom-defaults-grid" id="promDefaultsForm">
        <?php foreach ($defaults as $key => $def):
            $type = isset($def['type']) ? $def['type'] : 'select';
            if ($type === 'toggle'): ?>
        <div class="prom-toggle-row">
            <label><?php echo htmlspecialchars($def['label']); ?></label>
            <label class="toggle-switch">
                <input type="checkbox" name="<?php echo $key; ?>" <?php echo $def['value'] === '1' ? 'checked' : ''; ?> data-type="toggle">
                <span class="toggle-track"></span>
                <span class="toggle-knob"></span>
            </label>
        </div>
            <?php elseif (isset($def['options'])): ?>
        <div class="form-group">
            <label><?php echo htmlspecialchars($def['label']); ?></label>
            <select name="<?php echo $key; ?>">
                <?php foreach ($def['options'] as $optVal => $optLabel): ?>
                <option value="<?php echo htmlspecialchars($optVal); ?>" <?php echo $def['value'] === $optVal ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($optLabel); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
            <?php else: ?>
        <div class="form-group">
            <label><?php echo htmlspecialchars($def['label']); ?></label>
            <input type="<?php echo $type; ?>" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($def['value']); ?>">
        </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <div style="padding-top:8px;">
            <button type="button" class="btn btn-primary" id="promSaveDefaults">Зберегти налаштування</button>
            <span class="app-settings-saved" id="promDefaultsSaved" style="display:none;">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8.5l3 3 7-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Збережено
            </span>
        </div>
    </div>
</div>

<script>
(function() {
    var appKey = 'prom';

    // Active toggle
    var activeToggle = document.getElementById('promActiveToggle');
    if (activeToggle) {
        activeToggle.addEventListener('change', function() {
            fetch('/integrations/api/toggle_app', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ app_key: appKey, is_active: this.checked ? 1 : 0 })
            });
        });
    }

    // Show/hide token
    var toggleBtn = document.getElementById('promToggleShow');
    var tokenInput = document.getElementById('promToken');
    if (toggleBtn && tokenInput) {
        toggleBtn.addEventListener('click', function() {
            tokenInput.type = tokenInput.type === 'password' ? 'text' : 'password';
        });
    }

    // Save token
    var saveTokenBtn = document.getElementById('promSaveToken');
    if (saveTokenBtn) {
        saveTokenBtn.addEventListener('click', function() {
            var token = tokenInput.value.trim();
            saveTokenBtn.disabled = true;
            saveTokenBtn.textContent = 'Збереження...';
            fetch('/integrations/api/save_settings', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    app_key: appKey,
                    settings: [{ key: 'auth_token', value: token, secret: 1 }]
                })
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                saveTokenBtn.disabled = false;
                saveTokenBtn.textContent = 'Зберегти';
                if (!d.ok) alert(d.error || 'Помилка');
                else location.reload();
            })
            .catch(function() {
                saveTokenBtn.disabled = false;
                saveTokenBtn.textContent = 'Зберегти';
                alert("Помилка з'єднання");
            });
        });
    }

    // Test connection
    var testBtn = document.getElementById('promTestBtn');
    var testResult = document.getElementById('promTestResult');
    if (testBtn) {
        testBtn.addEventListener('click', function() {
            var token = tokenInput.value.trim();
            if (!token) { alert('Вкажіть токен'); return; }
            testBtn.disabled = true;
            testBtn.textContent = 'Перевірка...';
            testResult.className = 'prom-test-result';
            testResult.style.display = 'none';
            fetch('/prom/api/test_connection', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ token: token })
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                testBtn.disabled = false;
                testBtn.textContent = "Перевірити з'єднання";
                testResult.style.display = 'block';
                if (d.ok) {
                    testResult.className = 'prom-test-result ok';
                    testResult.textContent = d.message || "З'єднання успішне";
                } else {
                    testResult.className = 'prom-test-result err';
                    testResult.textContent = d.error || "Помилка з'єднання";
                }
            })
            .catch(function() {
                testBtn.disabled = false;
                testBtn.textContent = "Перевірити з'єднання";
                testResult.style.display = 'block';
                testResult.className = 'prom-test-result err';
                testResult.textContent = 'Помилка мережі';
            });
        });
    }

    // ── Save mapping helper ──────────────────────────────────────────────
    function saveMapping(endpoint, data, btnId, savedId) {
        var btn = document.getElementById(btnId);
        btn.disabled = true;
        btn.textContent = 'Збереження...';
        fetch('/prom/api/save_mapping', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            btn.disabled = false;
            btn.textContent = 'Зберегти мепінг';
            if (d.ok) {
                var saved = document.getElementById(savedId);
                saved.style.display = 'inline-flex';
                setTimeout(function() { saved.style.display = 'none'; }, 2500);
            } else {
                alert(d.error || 'Помилка');
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Зберегти мепінг';
            alert("Помилка з'єднання");
        });
    }

    // Save status mapping
    document.getElementById('promSaveStatusMap').addEventListener('click', function() {
        var rows = document.querySelectorAll('#promStatusMap select');
        var map = {};
        for (var i = 0; i < rows.length; i++) {
            var papir = rows[i].getAttribute('data-papir');
            var val = parseInt(rows[i].value, 10);
            if (val >= 0) map[papir] = val;
        }
        saveMapping('/prom/api/save_mapping', { type: 'status', map: map }, 'promSaveStatusMap', 'promStatusSaved');
    });

    // Save delivery mapping
    document.getElementById('promSaveDeliveryMap').addEventListener('click', function() {
        var rows = document.querySelectorAll('#promDeliveryMap select');
        var map = {};
        for (var i = 0; i < rows.length; i++) {
            var prom = rows[i].getAttribute('data-prom');
            var val = parseInt(rows[i].value, 10);
            if (val > 0) map[prom] = val;
        }
        saveMapping('/prom/api/save_mapping', { type: 'delivery', map: map }, 'promSaveDeliveryMap', 'promDeliverySaved');
    });

    // Save payment mapping
    document.getElementById('promSavePaymentMap').addEventListener('click', function() {
        var rows = document.querySelectorAll('#promPaymentMap select');
        var map = {};
        for (var i = 0; i < rows.length; i++) {
            var prom = rows[i].getAttribute('data-prom');
            var val = parseInt(rows[i].value, 10);
            if (val > 0) map[prom] = val;
        }
        saveMapping('/prom/api/save_mapping', { type: 'payment', map: map }, 'promSavePaymentMap', 'promPaymentSaved');
    });

    // Save defaults
    var saveDefaultsBtn = document.getElementById('promSaveDefaults');
    if (saveDefaultsBtn) {
        saveDefaultsBtn.addEventListener('click', function() {
            var form = document.getElementById('promDefaultsForm');
            var settings = [];
            var inputs = form.querySelectorAll('select[name], input[name][type="text"], input[name][type="number"]');
            for (var i = 0; i < inputs.length; i++) {
                settings.push({ key: inputs[i].name, value: inputs[i].value, secret: 0 });
            }
            var toggles = form.querySelectorAll('input[data-type="toggle"]');
            for (var j = 0; j < toggles.length; j++) {
                settings.push({ key: toggles[j].name, value: toggles[j].checked ? '1' : '0', secret: 0 });
            }
            saveDefaultsBtn.disabled = true;
            saveDefaultsBtn.textContent = 'Збереження...';
            fetch('/integrations/api/save_settings', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ app_key: appKey, settings: settings })
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                saveDefaultsBtn.disabled = false;
                saveDefaultsBtn.textContent = 'Зберегти налаштування';
                if (d.ok) {
                    var saved = document.getElementById('promDefaultsSaved');
                    saved.style.display = 'inline-flex';
                    setTimeout(function() { saved.style.display = 'none'; }, 2500);
                } else {
                    alert(d.error || 'Помилка');
                }
            })
            .catch(function() {
                saveDefaultsBtn.disabled = false;
                saveDefaultsBtn.textContent = 'Зберегти налаштування';
                alert("Помилка з'єднання");
            });
        });
    }
}());
</script>