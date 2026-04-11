<?php
/**
 * Custom settings view for Ukrposhta.
 *
 * Sections:
 *   1. Active toggle
 *   2. API tokens (eCom Bearer, User Token, Tracking Bearer)
 *   3. Default sender (Hlonder FOP) — pick sender_ukr uuid, addresses, client uuid
 *   4. Shipment defaults
 *
 * Variables injected by the parent universal settings page: $appKey, $app, $savedSettings
 */
require_once __DIR__ . '/../IntegrationSettingsService.php';
require_once __DIR__ . '/../../ukrposhta/repositories/UpSenderRepository.php';
require_once __DIR__ . '/../../ukrposhta/UpDefaults.php';

$isActive = IntegrationSettingsService::get('ukrposhta', 'is_active', '1') === '1';

$settings = array(
    'ecom_token'                => IntegrationSettingsService::get('ukrposhta', 'ecom_token',                ''),
    'user_token'                => IntegrationSettingsService::get('ukrposhta', 'user_token',                ''),
    'tracking_token'            => IntegrationSettingsService::get('ukrposhta', 'tracking_token',            ''),
    'default_sender_uuid'       => IntegrationSettingsService::get('ukrposhta', 'default_sender_uuid',       ''),
    'default_client_uuid'       => IntegrationSettingsService::get('ukrposhta', 'default_client_uuid',       ''),
    'default_sender_address_id' => IntegrationSettingsService::get('ukrposhta', 'default_sender_address_id', '645116149'),
    'default_return_address_id' => IntegrationSettingsService::get('ukrposhta', 'default_return_address_id', '645116149'),
    'default_shipment_type'     => IntegrationSettingsService::get('ukrposhta', 'default_shipment_type',     'STANDARD'),
    'default_delivery_type'     => IntegrationSettingsService::get('ukrposhta', 'default_delivery_type',     'W2W'),
    'default_payer'             => IntegrationSettingsService::get('ukrposhta', 'default_payer',             'recipient'),
    'default_weight'            => IntegrationSettingsService::get('ukrposhta', 'default_weight',            '1'),
    'default_length'            => IntegrationSettingsService::get('ukrposhta', 'default_length',            '30'),
    'default_width'             => IntegrationSettingsService::get('ukrposhta', 'default_width',             '20'),
    'default_height'            => IntegrationSettingsService::get('ukrposhta', 'default_height',            '2'),
    'default_description'       => IntegrationSettingsService::get('ukrposhta', 'default_description',       'Канцелярські приладдя'),
    'on_fail_receive_type'      => IntegrationSettingsService::get('ukrposhta', 'on_fail_receive_type',      'RETURN'),
    'return_after_storage_days' => IntegrationSettingsService::get('ukrposhta', 'return_after_storage_days', '10'),
);

// Load sender dropdown options from sender_ukr (147 rows, most are Hlonder FOP with different UP UUIDs).
// Show top 20 by usage count in ttn_ukrposhta, plus the currently-selected one.
$topSenders = \Database::fetchAll('Papir',
    "SELECT s.id, s.uuid, s.name, s.sender_city, s.sender_postcode, s.phoneNumber,
            (SELECT COUNT(*) FROM ttn_ukrposhta t WHERE t.sender_uuid = s.uuid) AS usage_count
     FROM sender_ukr s
     WHERE s.uuid IS NOT NULL AND s.uuid <> ''
     ORDER BY usage_count DESC, s.id DESC
     LIMIT 30");
$senders = $topSenders['ok'] ? $topSenders['rows'] : array();

$shipmentTypes  = \Papir\Crm\UpDefaults::shipmentTypes();
$deliveryTypes  = \Papir\Crm\UpDefaults::deliveryTypes();
$payers         = \Papir\Crm\UpDefaults::payers();
$onFailOptions  = array('RETURN' => 'Повернути відправнику', 'REDIRECT' => 'Переадресувати', 'WAIT' => 'Чекати у відділенні');
?>
<style>
.up-active-row {
    display: inline-flex; align-items: center; gap: 10px;
    margin-bottom: 24px; padding: 8px 16px;
    background: var(--bg-card); border: 1px solid var(--border); border-radius: 20px;
    font-size: 13px;
}
.up-active-row > label:first-child { font-weight: 500; color: var(--text-secondary); }
.toggle-switch { position: relative; width: 44px; height: 24px; cursor: pointer; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-track { position: absolute; inset: 0; border-radius: 12px; background: #cbd5e1; transition: background .2s; }
.toggle-switch input:checked + .toggle-track { background: #22c55e; }
.toggle-knob { position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.2); transition: transform .2s; }
.toggle-switch input:checked ~ .toggle-knob { transform: translateX(20px); }

.up-section { margin-bottom: 26px; max-width: 820px; }
.up-section-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--text-muted); margin-bottom: 10px; padding-bottom: 4px; border-bottom: 1px solid var(--border); }
.up-section-hint { font-size: 11px; color: var(--text-muted); margin-bottom: 10px; }
.up-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.up-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
.up-field label { display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 4px; }
.up-field input, .up-field select, .up-field textarea {
    width: 100%; padding: 7px 10px; border: 1px solid var(--border); border-radius: 6px;
    font-size: 13px; background: var(--bg-card); color: var(--text);
}
.up-field input[type="password"] { font-family: monospace; }
.up-field .row-with-eye { display: flex; gap: 4px; align-items: center; }
.up-field .row-with-eye input { flex: 1; }
.up-field .row-with-eye button { padding: 4px 8px; background: var(--bg); border: 1px solid var(--border); border-radius: 4px; cursor: pointer; color: var(--text-muted); }
.up-field .row-with-eye button:hover { color: var(--text); }

.up-save-bar { display: flex; align-items: center; gap: 10px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border); }
.up-save-ok { display: none; align-items: center; gap: 4px; color: #15803d; font-size: 13px; font-weight: 500; }
.up-save-ok.show { display: inline-flex; }

.up-test-row { display: flex; align-items: center; gap: 10px; margin-top: 10px; flex-wrap: wrap; font-size: 12px; }
.up-test-row .tag { min-width: 80px; font-weight: 600; text-transform: uppercase; color: var(--text-muted); font-size: 11px; letter-spacing: .3px; }
.up-test-row .ok  { color: #15803d; }
.up-test-row .err { color: #dc2626; }
</style>

<!-- ═══ Active toggle ═══ -->
<div class="up-active-row">
    <label>Додаток активний у проекті</label>
    <label class="toggle-switch">
        <input type="checkbox" id="upActiveToggle" <?php echo $isActive ? 'checked' : ''; ?>>
        <span class="toggle-track"></span>
        <span class="toggle-knob"></span>
    </label>
</div>

<form id="upSettingsForm" autocomplete="off">

    <!-- ═══ API Tokens ═══ -->
    <div class="up-section">
        <div class="up-section-title">API токени Укрпошти</div>
        <div class="up-section-hint">Токени беруться з кабінету ecom.ukrposhta.ua. Один акаунт на весь проект.</div>
        <div class="up-grid">
            <div class="up-field">
                <label>eCom Bearer</label>
                <div class="row-with-eye">
                    <input type="password" name="ecom_token" value="<?php echo htmlspecialchars($settings['ecom_token']); ?>" placeholder="UUID токена">
                    <button type="button" class="toggle-secret">👁</button>
                </div>
            </div>
            <div class="up-field">
                <label>User Token</label>
                <div class="row-with-eye">
                    <input type="password" name="user_token" value="<?php echo htmlspecialchars($settings['user_token']); ?>" placeholder="Передається як ?token=">
                    <button type="button" class="toggle-secret">👁</button>
                </div>
            </div>
        </div>
        <div class="up-field" style="margin-top:12px">
            <label>Tracking Bearer</label>
            <div class="row-with-eye">
                <input type="password" name="tracking_token" value="<?php echo htmlspecialchars($settings['tracking_token']); ?>" placeholder="Для /status-tracking/">
                <button type="button" class="toggle-secret">👁</button>
            </div>
        </div>
        <div class="up-test-row">
            <button type="button" class="btn btn-sm" id="upTestTokensBtn">Перевірити токени</button>
            <span id="upTestResult" style="display:none"></span>
        </div>
    </div>

    <!-- ═══ Default sender ═══ -->
    <div class="up-section">
        <div class="up-section-title">Відправник за замовчуванням</div>
        <div class="up-section-hint">Договір з Укрпошти — один акаунт «ГЛьондер ФОП». Обираємо UUID клієнта, з якого створюються всі ТТН, і типові адреси відправки / повернення.</div>
        <div class="up-field">
            <label>Sender UUID</label>
            <select name="default_sender_uuid">
                <?php foreach ($senders as $s): ?>
                    <option value="<?php echo htmlspecialchars($s['uuid']); ?>"
                            <?php echo ($settings['default_sender_uuid'] === $s['uuid']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['name']); ?>
                        <?php if (!empty($s['sender_city'])) echo ' · ' . htmlspecialchars($s['sender_city']); ?>
                        <?php if (!empty($s['phoneNumber'])) echo ' · ' . htmlspecialchars($s['phoneNumber']); ?>
                        (використано: <?php echo (int)$s['usage_count']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="up-grid" style="margin-top:12px">
            <div class="up-field">
                <label>Адреса відправки (senderAddressId)</label>
                <input type="text" name="default_sender_address_id" value="<?php echo htmlspecialchars($settings['default_sender_address_id']); ?>" placeholder="напр. 645116149">
            </div>
            <div class="up-field">
                <label>Адреса повернень (returnAddressId)</label>
                <input type="text" name="default_return_address_id" value="<?php echo htmlspecialchars($settings['default_return_address_id']); ?>" placeholder="напр. 645116149">
            </div>
        </div>
        <div class="up-field" style="margin-top:12px">
            <label>Client UUID (для реєстрів)</label>
            <input type="text" name="default_client_uuid" value="<?php echo htmlspecialchars($settings['default_client_uuid']); ?>" placeholder="зазвичай = Sender UUID">
        </div>
    </div>

    <!-- ═══ Shipment defaults ═══ -->
    <div class="up-section">
        <div class="up-section-title">Дефолти для ТТН</div>
        <div class="up-grid">
            <div class="up-field">
                <label>Тип відправлення</label>
                <select name="default_shipment_type">
                    <?php foreach ($shipmentTypes as $k => $v): ?>
                        <option value="<?php echo $k; ?>"<?php echo $settings['default_shipment_type']===$k?' selected':''; ?>><?php echo htmlspecialchars($v); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="up-field">
                <label>Тип доставки</label>
                <select name="default_delivery_type">
                    <?php foreach ($deliveryTypes as $k => $v): ?>
                        <option value="<?php echo $k; ?>"<?php echo $settings['default_delivery_type']===$k?' selected':''; ?>><?php echo htmlspecialchars($v); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="up-field">
                <label>Платник за доставку</label>
                <select name="default_payer">
                    <?php foreach ($payers as $k => $v): ?>
                        <option value="<?php echo $k; ?>"<?php echo $settings['default_payer']===$k?' selected':''; ?>><?php echo htmlspecialchars($v); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="up-field">
                <label>При неотриманні</label>
                <select name="on_fail_receive_type">
                    <?php foreach ($onFailOptions as $k => $v): ?>
                        <option value="<?php echo $k; ?>"<?php echo $settings['on_fail_receive_type']===$k?' selected':''; ?>><?php echo htmlspecialchars($v); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="up-grid" style="margin-top:12px; grid-template-columns: repeat(5, 1fr);">
            <div class="up-field"><label>Вага (кг)</label><input type="number" name="default_weight" step="0.1" min="0" value="<?php echo htmlspecialchars($settings['default_weight']); ?>"></div>
            <div class="up-field"><label>Довжина (см)</label><input type="number" name="default_length" min="0" value="<?php echo htmlspecialchars($settings['default_length']); ?>"></div>
            <div class="up-field"><label>Ширина (см)</label><input type="number" name="default_width" min="0" value="<?php echo htmlspecialchars($settings['default_width']); ?>"></div>
            <div class="up-field"><label>Висота (см)</label><input type="number" name="default_height" min="0" value="<?php echo htmlspecialchars($settings['default_height']); ?>"></div>
            <div class="up-field"><label>Днів зберігання</label><input type="number" name="return_after_storage_days" min="1" max="30" value="<?php echo htmlspecialchars($settings['return_after_storage_days']); ?>"></div>
        </div>
        <div class="up-field" style="margin-top:12px">
            <label>Опис вантажу</label>
            <input type="text" name="default_description" maxlength="40" value="<?php echo htmlspecialchars($settings['default_description']); ?>">
        </div>
    </div>

    <div class="up-save-bar">
        <button type="submit" class="btn btn-primary">Зберегти</button>
        <span class="up-save-ok" id="upSaveOk">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8.5l3 3 7-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Збережено
        </span>
    </div>
</form>

<script>
(function() {
    var APP_KEY = 'ukrposhta';

    // Active toggle
    var activeToggle = document.getElementById('upActiveToggle');
    if (activeToggle) {
        activeToggle.addEventListener('change', function() {
            fetch('/integrations/api/toggle_app', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ app_key: APP_KEY, is_active: this.checked ? 1 : 0 }),
            });
        });
    }

    // Show/hide password fields
    document.querySelectorAll('.toggle-secret').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var inp = this.parentElement.querySelector('input');
            inp.type = inp.type === 'password' ? 'text' : 'password';
        });
    });

    // Save form
    document.getElementById('upSettingsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var settings = [];
        this.querySelectorAll('input[name], select[name]').forEach(function(f) {
            settings.push({ key: f.name, value: f.value, secret: 0 });
        });
        fetch('/integrations/api/save_settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ app_key: APP_KEY, settings: settings }),
        })
            .then(function(r) { return r.json(); })
            .then(function(j) {
                if (j && j.ok) {
                    var ok = document.getElementById('upSaveOk');
                    ok.classList.add('show');
                    setTimeout(function() { ok.classList.remove('show'); }, 1500);
                } else {
                    alert('Помилка збереження: ' + (j && j.error ? j.error : 'невідома'));
                }
            });
    });

    // Test tokens button
    document.getElementById('upTestTokensBtn').addEventListener('click', function() {
        var btn = this;
        var res = document.getElementById('upTestResult');
        btn.disabled = true;
        res.style.display = 'inline-flex';
        res.innerHTML = '<span class="tag">статус</span> Перевіряю…';
        fetch('/ukrposhta/api/test_connection', { method: 'POST', body: '{}' })
            .then(function(r){ return r.json(); })
            .then(function(j) {
                btn.disabled = false;
                var html = '';
                if (j && j.ecom) {
                    html += '<span class="tag">eCom</span><span class="' + (j.ecom.ok ? 'ok' : 'err') + '">'
                        + (j.ecom.ok ? '✓' : '✗') + ' ' + (j.ecom.message || '') + '</span>';
                }
                if (j && j.tracking) {
                    html += ' <span class="tag">track</span><span class="' + (j.tracking.ok ? 'ok' : 'err') + '">'
                        + (j.tracking.ok ? '✓' : '✗') + ' ' + (j.tracking.message || '') + '</span>';
                }
                if (!html) html = '<span class="err">' + (j && j.error ? j.error : 'Помилка') + '</span>';
                res.innerHTML = html;
            })
            .catch(function() { btn.disabled = false; res.innerHTML = '<span class="err">Помилка зв\'язку</span>'; });
    });
})();
</script>