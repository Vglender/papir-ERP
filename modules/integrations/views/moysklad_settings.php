<?php
/**
 * MoySklad integration settings — CRUD-level exchange control.
 */
require_once __DIR__ . '/../IntegrationSettingsService.php';

$isActive = IntegrationSettingsService::get('moysklad', 'is_active', '1') === '1';
$auth     = IntegrationSettingsService::get('moysklad', 'auth', '');
$_gs = function($k, $d='0') { return IntegrationSettingsService::get('moysklad', $k, $d); };

// CRUD exchange matrix — reflects real code behavior
$docs = array(
    'order' => array(
        'label' => 'Замовлення покупців',
        'wh'    => 'wh_customerorder',
        'wh_on' => $_gs('wh_customerorder','1') === '1',
        'C_from' => $_gs('ms_order_C_from','1'), 'C_to' => $_gs('ms_order_C_to','1'),
        'U_from' => $_gs('ms_order_U_from','1'), 'U_to' => $_gs('ms_order_U_to','1'),
        'D_from' => $_gs('ms_order_D_from','1'), 'D_to' => $_gs('ms_order_D_to','1'),
        'note'   => 'Статус: Papir = джерело правди',
    ),
    'demand' => array(
        'label' => 'Відвантаження',
        'wh'    => 'wh_demand',
        'wh_on' => $_gs('wh_demand','1') === '1',
        'C_from' => $_gs('ms_demand_C_from','0'), 'C_to' => $_gs('ms_demand_C_to','1'),
        'U_from' => $_gs('ms_demand_U_from','1'), 'U_to' => $_gs('ms_demand_U_to','1'),
        'D_from' => $_gs('ms_demand_D_from','0'), 'D_to' => $_gs('ms_demand_D_to','0'),
        'note'   => 'Створення: тільки з Papir',
    ),
    'finance' => array(
        'label' => 'Оплати / Каса',
        'wh'    => 'wh_finance',
        'wh_on' => $_gs('wh_finance','1') === '1',
        'C_from' => $_gs('ms_finance_C_from','1'), 'C_to' => $_gs('ms_finance_C_to','0'),
        'U_from' => $_gs('ms_finance_U_from','1'), 'U_to' => $_gs('ms_finance_U_to','0'),
        'D_from' => $_gs('ms_finance_D_from','1'), 'D_to' => $_gs('ms_finance_D_to','1'),
        'note'   => 'Payment/cash — загальний контроль',
    ),
    'finance_cashin' => array(
        'label' => 'Каса — ПКО (накладенка)',
        'wh'    => 'wh_finance',
        'wh_on' => $_gs('wh_finance','1') === '1',
        'C_from' => $_gs('ms_finance_cashin_C_from','0'), 'C_to' => $_gs('ms_finance_cashin_C_to','1'),
        'U_from' => $_gs('ms_finance_cashin_U_from','0'), 'U_to' => $_gs('ms_finance_cashin_U_to','1'),
        'D_from' => $_gs('ms_finance_cashin_D_from','0'), 'D_to' => $_gs('ms_finance_cashin_D_to','1'),
        'note'   => 'Papir = джерело правди (сценарій по ТТН)',
    ),
);

// Reports (no CRUD, just direction)
$reports = array(
    array('key' => 'sync_stock',  'label' => 'Залишки', 'dir' => 'МС → Papir', 'desc' => 'sync_stock.php кожні 4 год'),
    array('key' => 'sync_prices', 'label' => 'Ціни',    'dir' => 'Papir → МС', 'desc' => 'sync_prices.php щодня 01:00'),
);

// Mappings
$mappings = array(
    array('label' => 'Статуси замовлень', 'desc' => 'МС state UUID ↔ Papir status enum',     'status' => 'active'),
    array('label' => 'Співробітники',     'desc' => 'МС employee ↔ Papir auth_users',         'status' => 'planned'),
    array('label' => 'Склади',            'desc' => 'МС store ↔ Papir warehouse',              'status' => 'planned'),
    array('label' => 'Статті витрат',     'desc' => 'МС expenseitem ↔ Papir expense_type',     'status' => 'planned'),
    array('label' => 'Організації',       'desc' => 'МС organization ↔ Papir organization',    'status' => 'planned'),
);

// Future docs (stubs)
$stubs = array('Повернення','Замовлення постачальнику','Приймання','Списання','Оприходування','Переміщення','Товари');
?>

<style>
/* ── Layout ───────────────────────────────────────────────────────────── */
.ms-top { display: grid; grid-template-columns: 280px 1fr; gap: 20px; margin-bottom: 24px; }
.ms-panel { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px 18px; }

/* ── Active + Auth ────────────────────────────────────────────────────── */
.ms-pill { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 14px; padding: 5px 12px; border: 1px solid var(--border); border-radius: 20px; font-size: 13px; }
.ms-pill label:first-child { font-weight: 500; color: var(--text-secondary); }
.tsw { position: relative; width: 36px; height: 20px; cursor: pointer; display: inline-block; }
.tsw input { opacity: 0; width: 0; height: 0; }
.tsw .tr { position: absolute; inset: 0; border-radius: 10px; background: #cbd5e1; transition: background .2s; }
.tsw input:checked + .tr { background: #22c55e; }
.tsw .kn { position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.2); transition: transform .2s; }
.tsw input:checked ~ .kn { transform: translateX(16px); }
.ms-lbl { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 5px; }
.ms-auth-w { position: relative; }
.ms-auth-w input { width: 100%; padding: 7px 30px 7px 10px; font-size: 13px; font-family: monospace; border: 1px solid var(--border); border-radius: 6px; background: var(--bg); color: var(--text); }
.ms-auth-w input:focus { outline: none; border-color: #475569; box-shadow: 0 0 0 3px rgba(71,85,105,.1); }
.ms-eye { position: absolute; right: 5px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--text-muted); padding: 3px; }

/* ── CRUD Matrix ──────────────────────────────────────────────────────── */
.ms-sec { margin-bottom: 24px; }
.ms-sec-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--text-muted); margin-bottom: 8px; }
.ms-crud { width: 100%; border-collapse: collapse; border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; font-size: 13px; }
.ms-crud th { background: var(--bg); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; color: var(--text-muted); padding: 7px 8px; text-align: center; border-bottom: 1px solid var(--border); }
.ms-crud th:first-child { text-align: left; padding-left: 12px; width: 180px; }
.ms-crud th.wh-col { width: 60px; }
.ms-crud th.op-col { width: 70px; }
.ms-crud td { padding: 0; border-bottom: 1px solid var(--border); background: var(--bg-card); text-align: center; vertical-align: middle; }
.ms-crud tr:last-child td { border-bottom: none; }
.ms-crud td:first-child { text-align: left; padding: 8px 12px; }
.ms-crud .doc-name { font-weight: 600; font-size: 13px; }
.ms-crud .doc-note { font-size: 10px; color: var(--text-muted); margin-top: 1px; }
.ms-crud .op-pair { display: flex; justify-content: center; align-items: center; gap: 2px; padding: 6px 0; }
.ms-crud .op-btn { width: 28px; height: 24px; border: 1px solid var(--border); border-radius: 3px; background: var(--bg); cursor: pointer; font-size: 10px; font-weight: 700; color: var(--text-muted); display: flex; align-items: center; justify-content: center; transition: all .15s; }
.ms-crud .op-btn:hover { border-color: #94a3b8; }
.ms-crud .op-btn.on { background: #dbeafe; border-color: #93c5fd; color: #1e40af; }
.ms-crud .op-btn.on-out { background: #fef3c7; border-color: #fcd34d; color: #92400e; }
.ms-crud .stub-row td { opacity: .35; }
.ms-crud .stub-badge { font-size: 9px; background: var(--bg); padding: 1px 5px; border-radius: 6px; color: var(--text-muted); margin-left: 4px; }

/* ── Reports ──────────────────────────────────────────────────────────── */
.ms-rep { display: flex; gap: 12px; flex-wrap: wrap; }
.ms-rep-card { flex: 1; min-width: 200px; padding: 10px 14px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--bg-card); }
.ms-rep-label { font-size: 13px; font-weight: 600; }
.ms-rep-dir { font-size: 12px; color: #3b82f6; font-weight: 500; }
.ms-rep-desc { font-size: 11px; color: var(--text-muted); margin-top: 2px; }

/* ── Mappings ─────────────────────────────────────────────────────────── */
.ms-map-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 8px; }
.ms-map-card { padding: 10px 14px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--bg-card); }
.ms-map-card.planned { opacity: .45; }
.ms-map-name { font-size: 13px; font-weight: 600; }
.ms-map-desc { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
.ms-map-st { display: inline-block; font-size: 10px; font-weight: 600; padding: 1px 6px; border-radius: 8px; margin-top: 4px; }
.ms-map-st.active { background: #dcfce7; color: #15803d; }
.ms-map-st.planned { background: #f1f5f9; color: #94a3b8; }

/* ── Save ─────────────────────────────────────────────────────────────── */
.ms-save { display: flex; align-items: center; gap: 10px; margin-top: 20px; padding-top: 14px; border-top: 1px solid var(--border); }
.ms-ok { display: none; align-items: center; gap: 4px; color: #15803d; font-size: 13px; font-weight: 500; }
.ms-ok.show { display: inline-flex; }

@media (max-width: 800px) { .ms-top { grid-template-columns: 1fr; } }
</style>

<!-- ═══ Connection ═══ -->
<div class="ms-top">
    <div class="ms-panel">
        <div class="ms-pill">
            <label>Активний</label>
            <label class="tsw"><input type="checkbox" id="msActive" <?php echo $isActive ? 'checked' : ''; ?>><span class="tr"></span><span class="kn"></span></label>
        </div>
        <div class="ms-lbl">Авторизація</div>
        <div class="ms-auth-w">
            <input type="password" id="msAuth" value="<?php echo htmlspecialchars($auth); ?>" placeholder="login:password">
            <button type="button" class="ms-eye" onclick="var i=document.getElementById('msAuth');i.type=i.type==='password'?'text':'password'">
                <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M1.5 9s3-5.5 7.5-5.5S16.5 9 16.5 9s-3 5.5-7.5 5.5S1.5 9 1.5 9z" stroke="currentColor" stroke-width="1.4"/><circle cx="9" cy="9" r="2.5" stroke="currentColor" stroke-width="1.4"/></svg>
            </button>
        </div>
    </div>
    <div class="ms-panel" style="font-size:12px;color:var(--text-secondary);line-height:1.7;">
        <b style="color:var(--text)">Обмін на рівні CRUD</b><br>
        Кожна операція (створення / оновлення / видалення) налаштовується окремо.<br>
        <span style="display:inline-block;width:18px;height:14px;background:#dbeafe;border:1px solid #93c5fd;border-radius:2px;vertical-align:middle;margin:0 2px;"></span> <b>←</b> МС → Papir &nbsp;
        <span style="display:inline-block;width:18px;height:14px;background:#fef3c7;border:1px solid #fcd34d;border-radius:2px;vertical-align:middle;margin:0 2px;"></span> <b>→</b> Papir → МС<br>
        Вебхук потрібен для прийому даних з МС (← напрямок).
    </div>
</div>

<!-- ═══ CRUD Matrix ═══ -->
<div class="ms-sec">
    <div class="ms-sec-title">Документи — обмін</div>
    <table class="ms-crud">
        <thead><tr>
            <th>Документ</th>
            <th class="wh-col">WH</th>
            <th class="op-col">Create</th>
            <th class="op-col">Update</th>
            <th class="op-col">Delete</th>
        </tr></thead>
        <tbody>
        <?php foreach ($docs as $dKey => $d): ?>
        <tr data-doc="<?php echo $dKey; ?>">
            <td>
                <div class="doc-name"><?php echo htmlspecialchars($d['label']); ?></div>
                <?php if ($d['note']): ?><div class="doc-note"><?php echo htmlspecialchars($d['note']); ?></div><?php endif; ?>
            </td>
            <td class="wh-cell">
                <label class="tsw" title="Webhook">
                    <input type="checkbox" class="ms-wh-toggle" data-key="<?php echo $d['wh']; ?>" <?php echo $d['wh_on'] ? 'checked' : ''; ?>>
                    <span class="tr"></span><span class="kn"></span>
                </label>
            </td>
            <?php foreach (array('C','U','D') as $op): ?>
            <td>
                <div class="op-pair">
                    <button type="button" class="op-btn<?php echo $d[$op.'_from']==='1' ? ' on' : ''; ?>"
                            data-setting="ms_<?php echo $dKey; ?>_<?php echo $op; ?>_from" title="МС → Papir">←</button>
                    <button type="button" class="op-btn<?php echo $d[$op.'_to']==='1' ? ' on-out' : ''; ?>"
                            data-setting="ms_<?php echo $dKey; ?>_<?php echo $op; ?>_to" title="Papir → МС">→</button>
                </div>
            </td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
        <?php foreach ($stubs as $stub): ?>
        <tr class="stub-row"><td><span class="doc-name"><?php echo $stub; ?></span><span class="stub-badge">скоро</span></td><td>—</td><td>—</td><td>—</td><td>—</td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ═══ Reports ═══ -->
<div class="ms-sec">
    <div class="ms-sec-title">Звіти / Залишки</div>
    <div class="ms-rep">
        <?php foreach ($reports as $r): ?>
        <div class="ms-rep-card">
            <div class="ms-rep-label"><?php echo htmlspecialchars($r['label']); ?></div>
            <div class="ms-rep-dir"><?php echo htmlspecialchars($r['dir']); ?></div>
            <div class="ms-rep-desc"><?php echo htmlspecialchars($r['desc']); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ═══ Mappings ═══ -->
<div class="ms-sec">
    <div class="ms-sec-title">Таблиці маппінгів</div>
    <div class="ms-map-grid">
        <?php foreach ($mappings as $m): ?>
        <div class="ms-map-card<?php echo $m['status']==='planned' ? ' planned' : ''; ?>">
            <div class="ms-map-name"><?php echo htmlspecialchars($m['label']); ?></div>
            <div class="ms-map-desc"><?php echo htmlspecialchars($m['desc']); ?></div>
            <span class="ms-map-st <?php echo $m['status']; ?>"><?php echo $m['status']==='active' ? 'Налаштовано' : 'Заплановано'; ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ═══ Save ═══ -->
<div class="ms-save">
    <button type="button" class="btn btn-primary" id="msSaveBtn">Зберегти</button>
    <span class="ms-ok" id="msOk"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8.5l3 3 7-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg> Збережено</span>
</div>

<script>
(function() {
    // Active toggle
    document.getElementById('msActive').addEventListener('change', function() {
        fetch('/integrations/api/toggle_app', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ app_key: 'moysklad', is_active: this.checked ? 1 : 0 })
        });
    });

    // Webhook toggles — real MS API
    document.querySelectorAll('.ms-wh-toggle').forEach(function(chk) {
        chk.addEventListener('change', function() {
            var key = this.dataset.key, enable = this.checked, el = this;
            el.disabled = true;
            fetch('/integrations/api/toggle_ms_webhook', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ wh_key: key, enable: enable ? 1 : 0 })
            }).then(function(r) { return r.json(); }).then(function(d) {
                el.disabled = false;
                if (!d.ok) { el.checked = !enable; alert(d.error || 'Помилка'); }
            }).catch(function() { el.disabled = false; el.checked = !enable; });
        });
    });

    // CRUD operation buttons — toggle on/off
    document.querySelectorAll('.op-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var isFrom = this.dataset.setting.endsWith('_from');
            var isOn = isFrom ? this.classList.contains('on') : this.classList.contains('on-out');
            if (isOn) {
                this.classList.remove('on', 'on-out');
            } else {
                this.classList.add(isFrom ? 'on' : 'on-out');
            }
        });
    });

    // Save all (auth + CRUD settings)
    document.getElementById('msSaveBtn').addEventListener('click', function() {
        var settings = [];
        settings.push({ key: 'auth', value: document.getElementById('msAuth').value, secret: 1 });
        document.querySelectorAll('.op-btn').forEach(function(btn) {
            var isFrom = btn.dataset.setting.endsWith('_from');
            var isOn = isFrom ? btn.classList.contains('on') : btn.classList.contains('on-out');
            settings.push({ key: btn.dataset.setting, value: isOn ? '1' : '0', secret: 0 });
        });

        var b = this; b.disabled = true; b.textContent = 'Збереження...';
        fetch('/integrations/api/save_settings', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ app_key: 'moysklad', settings: settings })
        }).then(function(r) { return r.json(); }).then(function(d) {
            b.disabled = false; b.textContent = 'Зберегти';
            if (d.ok) { var m = document.getElementById('msOk'); m.classList.add('show'); setTimeout(function() { m.classList.remove('show'); }, 2500); }
        });
    });
}());
</script>