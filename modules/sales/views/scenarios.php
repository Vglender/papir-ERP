<?php
$title     = 'Сценарії';
$activeNav = 'sales';
$subNav    = 'scenarios';
require_once __DIR__ . '/../../shared/layout.php';
?>
<style>
.sc-wrap { max-width: 1400px; margin: 0 auto; padding: 20px 16px 40px; }
.sc-toolbar { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
.sc-toolbar h1 { margin: 0; font-size: 18px; font-weight: 700; flex: 1; }

/* ── Layout ────────────────────────────────────────────────────── */
.sc-layout { display: grid; grid-template-columns: 360px 1fr; gap: 20px; align-items: start; }
@media(max-width:900px){ .sc-layout { grid-template-columns: 1fr; } }

/* ── Left panel ────────────────────────────────────────────────── */
.sc-section-head {
    display: flex; align-items: center; justify-content: space-between;
    font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;
    letter-spacing: .4px; padding: 0 4px 8px; border-bottom: 1px solid var(--border);
    margin-bottom: 8px;
}
.sc-section-head .btn-xs { text-transform: none; letter-spacing: 0; font-size: 11px; }
.sc-left-section { margin-bottom: 20px; }

.sc-event-group { margin-bottom: 10px; }
.sc-event-label { font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;
    letter-spacing: .3px; padding: 4px 6px; }

.sc-trigger-row, .sc-scenario-row {
    display: flex; align-items: center; gap: 8px; padding: 7px 10px;
    border: 1px solid var(--border); border-radius: 7px; margin-bottom: 5px; cursor: pointer;
    background: var(--bg-card); transition: border-color .12s;
}
.sc-trigger-row:hover, .sc-scenario-row:hover { border-color: var(--blue-light); }
.sc-trigger-row.selected, .sc-scenario-row.selected { border-color: var(--blue); background: var(--blue-bg); }
.sc-row-name { font-weight: 600; font-size: 13px; flex: 1; min-width: 0;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sc-row-sub { font-size: 11px; color: var(--text-muted); margin-top: 1px; }
.sc-row-meta { display: flex; gap: 5px; align-items: center; flex-shrink: 0; }
.sc-status-pill { display: inline-block; width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.sc-status-on  { background: #22c55e; }
.sc-status-off { background: #d1d5db; }
.sc-fired-cnt { font-size: 10px; color: var(--text-muted); }
.sc-step-cnt { font-size: 10px; background: var(--bg-header); border-radius: 10px;
    padding: 1px 6px; color: var(--text-muted); font-weight: 600; }
.sc-trig-cnt { font-size: 10px; background: #fef3c7; border-radius: 10px;
    padding: 1px 6px; color: #92400e; font-weight: 600; }

/* ── Right panel ───────────────────────────────────────────────── */
.sc-panel { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 20px; }
.sc-panel-title { font-size: 15px; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.sc-panel-subtitle { font-size: 10px; font-weight: 600; color: var(--text-muted); text-transform: uppercase;
    letter-spacing: .4px; background: var(--blue-bg); border-radius: 4px; padding: 2px 7px; }
.sc-divider { margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid var(--border); }
.sc-field { margin-bottom: 10px; }
.sc-field label { display: block; font-size: 11px; font-weight: 600; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: .3px; margin-bottom: 3px; }
.sc-field input, .sc-field select, .sc-field textarea {
    width: 100%; padding: 6px 9px; border: 1px solid var(--border-input); border-radius: var(--radius-sm);
    font-size: 13px; font-family: var(--font); background: #fff; outline: none; box-sizing: border-box;
}
.sc-field input:focus, .sc-field select:focus { border-color: var(--blue-light); }
.sc-actions { display: flex; gap: 8px; margin-top: 16px; align-items: center; }

/* ── Scenario picker ────────────────────────────────────────────── */
.sc-picker-wrap { display: flex; gap: 6px; align-items: center; }
.sc-picker-wrap select { flex: 1; }
.sc-picker-link { font-size: 11px; color: var(--blue); white-space: nowrap; cursor: pointer; flex-shrink: 0; }
.sc-picker-link:hover { text-decoration: underline; }

/* ── Conditions builder ────────────────────────────────────────── */
.sc-cond-builder { display: flex; flex-direction: column; gap: 6px; }
.sc-cond-rule {
    display: flex; align-items: flex-start; gap: 6px;
    padding: 8px 10px; background: var(--bg-header);
    border: 1px solid var(--border); border-radius: 6px;
}
.sc-cond-rule-inputs { flex: 1; min-width: 0; display: flex; gap: 6px; align-items: flex-start; }
.sc-cond-rule-del { flex-shrink: 0; border: none; background: none; cursor: pointer;
    color: #9ca3af; font-size: 16px; line-height: 1; padding: 0 2px; align-self: center; }
.sc-cond-rule-del:hover { color: #ef4444; }
.sc-cond-pills { display: flex; flex-wrap: wrap; gap: 5px; }
.sc-cond-pill { display: flex; align-items: center; gap: 4px; padding: 3px 8px;
    border: 1px solid var(--border-input); border-radius: 12px; font-size: 11px;
    cursor: pointer; background: #fff; transition: background .1s, border-color .1s; }
.sc-cond-pill:hover { border-color: var(--blue-light); }
.sc-cond-pill input[type=checkbox] { width: 12px; height: 12px; margin: 0; cursor: pointer; accent-color: var(--blue); }
.sc-cond-pill.checked { background: var(--blue-bg); border-color: var(--blue); font-weight: 600; }
.sc-cond-logic-row { display: flex; align-items: center; gap: 10px; margin-top: 4px; }

/* ── Steps builder ─────────────────────────────────────────────── */
.sc-steps { display: flex; flex-direction: column; gap: 8px; }
.sc-step { border: 1px solid var(--border); border-radius: 8px; overflow: hidden; background: #fff; }
.sc-step-head {
    display: flex; align-items: center; gap: 8px; padding: 8px 12px;
    background: var(--bg-header); border-bottom: 1px solid var(--border); cursor: pointer;
}
.sc-step-num { width: 22px; height: 22px; border-radius: 50%; background: var(--blue); color: #fff;
    display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0; }
.sc-step-summary { flex: 1; font-size: 12px; font-weight: 600; }
.sc-step-executor { font-size: 11px; color: var(--text-muted); }
.sc-step-del { border: none; background: none; cursor: pointer; color: #9ca3af; font-size: 16px; line-height: 1; padding: 0 4px; }
.sc-step-del:hover { color: #ef4444; }
.sc-step-body { padding: 12px; display: none; }
.sc-step-body.open { display: block; }
.sc-step-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.sc-step-params { margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--border); }
.sc-step-params label { font-size: 11px; color: var(--text-muted); display: block; margin-bottom: 3px; }
.sc-step-params input, .sc-step-params select, .sc-step-params textarea {
    width: 100%; padding: 5px 8px; border: 1px solid var(--border-input); border-radius: 5px;
    font-size: 12px; font-family: var(--font); box-sizing: border-box;
}
.sc-add-step { width: 100%; padding: 8px; border: 1px dashed var(--border); border-radius: 7px;
    background: none; cursor: pointer; font-size: 12px; color: var(--text-muted); transition: border-color .12s; }
.sc-add-step:hover { border-color: var(--blue-light); color: var(--blue); }

/* ── Queue monitor ─────────────────────────────────────────────── */
.sc-queue-row { display: flex; gap: 8px; padding: 6px 0; border-bottom: 1px solid var(--border); font-size: 12px; }
.sc-queue-row:last-child { border-bottom: none; }
.sc-queue-status { padding: 1px 6px; border-radius: 4px; font-weight: 600; font-size: 10px; text-transform: uppercase; }
.sc-queue-pending { background: #fef9c3; color: #854d0e; }
.sc-queue-done    { background: #dcfce7; color: #166534; }
.sc-queue-failed  { background: #fee2e2; color: #991b1b; }
.sc-queue-running { background: #ede9fe; color: #5b21b6; }
</style>

<div class="sc-wrap">
<div class="sc-toolbar">
    <h1>Сценарії та тригери</h1>
    <button class="btn btn-ghost" onclick="SC.newScenario()">+ Сценарій</button>
    <button class="btn btn-primary" onclick="SC.newTrigger()">+ Тригер</button>
</div>

<div class="sc-layout">

<!-- ── Left panel ──────────────────────────────────────────────── -->
<div>
  <div class="card" style="padding:16px">

    <!-- Triggers -->
    <div class="sc-left-section">
      <div class="sc-section-head">
        <span>⚡ Тригери (<?php echo count($triggers); ?>)</span>
      </div>
      <?php if (empty($triggers)): ?>
        <div style="font-size:12px;color:var(--text-muted);padding:8px 4px">Тригерів ще немає</div>
      <?php else:
        $grouped = array();
        foreach ($triggers as $t) { $grouped[$t['event_type']][] = $t; }
        foreach ($grouped as $eventType => $rows): ?>
        <div class="sc-event-group">
          <div class="sc-event-label"><?php echo htmlspecialchars(ScenarioRepository::eventLabel($eventType)); ?></div>
          <?php foreach ($rows as $t): ?>
          <div class="sc-trigger-row" data-id="<?php echo $t['id']; ?>" onclick="SC.selectTrigger(<?php echo $t['id']; ?>)">
            <span class="sc-status-pill <?php echo $t['status'] ? 'sc-status-on' : 'sc-status-off'; ?>"></span>
            <div style="flex:1;min-width:0">
              <div class="sc-row-name"><?php echo htmlspecialchars($t['name']); ?></div>
              <div class="sc-row-sub">→ <?php echo htmlspecialchars($t['scenario_name']); ?></div>
            </div>
            <div class="sc-row-meta">
              <?php if ($t['fired_count']): ?>
              <span class="sc-fired-cnt"><?php echo $t['fired_count']; ?>×</span>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- Scenarios -->
    <div class="sc-left-section">
      <div class="sc-section-head">
        <span>📋 Сценарії (<?php echo count($scenarios); ?>)</span>
      </div>
      <?php if (empty($scenarios)): ?>
        <div style="font-size:12px;color:var(--text-muted);padding:8px 4px">Сценаріїв ще немає</div>
      <?php else: foreach ($scenarios as $s): ?>
        <div class="sc-scenario-row" data-id="<?php echo $s['id']; ?>" onclick="SC.selectScenario(<?php echo $s['id']; ?>)">
          <div style="flex:1;min-width:0">
            <div class="sc-row-name"><?php echo htmlspecialchars($s['name']); ?></div>
          </div>
          <div class="sc-row-meta">
            <?php if ($s['step_count']): ?>
            <span class="sc-step-cnt"><?php echo $s['step_count']; ?> кр</span>
            <?php endif; ?>
            <?php if ($s['trigger_count']): ?>
            <span class="sc-trig-cnt">⚡<?php echo $s['trigger_count']; ?></span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- Queue -->
    <div style="border-top:1px solid var(--border);padding-top:12px">
      <div class="sc-section-head" style="margin-bottom:6px"><span>Черга</span></div>
      <div id="scQueueList"><div style="font-size:12px;color:var(--text-muted)">Завантаження…</div></div>
    </div>

  </div>
</div>

<!-- ── Right panel ────────────────────────────────────────────── -->
<div id="scPanel">

  <!-- Empty state -->
  <div class="sc-panel" id="scEmptyPanel">
    <div style="text-align:center;padding:48px 0;color:var(--text-muted)">
      <div style="font-size:32px;margin-bottom:12px">⚡</div>
      <div style="font-weight:700;font-size:14px;margin-bottom:8px">Тригери та сценарії</div>
      <div style="font-size:12px;line-height:1.8;max-width:280px;margin:0 auto">
        <b>Сценарій</b> — ланцюжок кроків (надіслати, задача, змінити статус)<br>
        <b>Тригер</b> — подія + умова → запускає сценарій
      </div>
      <div style="margin-top:16px;display:flex;gap:8px;justify-content:center">
        <button class="btn btn-ghost" onclick="SC.newScenario()">+ Сценарій</button>
        <button class="btn btn-primary" onclick="SC.newTrigger()">+ Тригер</button>
      </div>
    </div>
  </div>

  <!-- Trigger editor -->
  <div class="sc-panel" id="scTriggerPanel" style="display:none">
    <div class="sc-panel-title">
      <span id="scTriggerTitle">Новий тригер</span>
      <span class="sc-panel-subtitle">⚡ Тригер</span>
    </div>
    <input type="hidden" id="scTriggerId" value="0">

    <div class="sc-divider">
      <div class="sc-field">
        <label>Назва тригера</label>
        <input type="text" id="scTriggerName" placeholder="напр. Новий заказ — Нова Пошта">
      </div>
      <div class="sc-field">
        <label>Подія</label>
        <select id="scEventType">
          <option value="order_created">📦 Новий заказ</option>
          <option value="order_status_changed">🔄 Зміна статусу заказу</option>
          <option value="order_cancelled">❌ Скасування заказу</option>
          <option value="task_done">✅ Виконання задачі</option>
          <option value="task_created">📌 Створення задачі</option>
          <option value="document_created">📄 Новий документ</option>
        </select>
      </div>
      <div class="sc-field">
        <label>Затримка перед стартом (хв)</label>
        <input type="number" id="scTriggerDelay" value="0" min="0" style="width:100px">
      </div>
    </div>

    <div class="sc-divider">
      <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:10px">
        Умови запуску <span style="font-weight:400;text-transform:none;letter-spacing:0">(необов'язково)</span>
      </div>
      <div class="sc-cond-builder" id="scCondRules"></div>
      <div class="sc-cond-logic-row">
        <button type="button" class="btn btn-ghost btn-xs" onclick="SC.addCondRule()">+ Умова</button>
        <span style="font-size:11px;color:var(--text-muted)">Логіка:</span>
        <label style="display:flex;align-items:center;gap:3px;font-size:11px;cursor:pointer">
          <input type="radio" name="scCondLogic" id="scCondLogicAnd" value="AND" checked> AND
        </label>
        <label style="display:flex;align-items:center;gap:3px;font-size:11px;cursor:pointer">
          <input type="radio" name="scCondLogic" id="scCondLogicOr" value="OR"> OR
        </label>
      </div>
    </div>

    <div>
      <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px">
        Сценарій
      </div>
      <div class="sc-picker-wrap">
        <select id="scScenarioPicker">
          <option value="">— Оберіть сценарій —</option>
          <?php foreach ($scenarios as $s): ?>
          <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?> (<?php echo $s['step_count']; ?> кр)</option>
          <?php endforeach; ?>
        </select>
        <span class="sc-picker-link" onclick="SC.newScenario()">+ Новий сценарій</span>
      </div>
    </div>

    <div class="sc-actions">
      <button class="btn btn-primary" onclick="SC.saveTrigger()">Зберегти</button>
      <button class="btn btn-ghost" onclick="SC.cancel()">Скасувати</button>
      <button class="btn btn-danger btn-sm" id="scTriggerDeleteBtn" onclick="SC.deleteTrigger()" style="margin-left:auto;display:none">Видалити</button>
      <label style="margin-left:8px;display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer">
        <input type="checkbox" id="scTriggerStatus" checked> Активний
      </label>
    </div>
    <div class="modal-error" id="scTriggerError" style="display:none;margin-top:8px"></div>
  </div>

  <!-- Scenario editor -->
  <div class="sc-panel" id="scScenarioPanel" style="display:none">
    <div class="sc-panel-title">
      <span id="scScenarioTitle">Новий сценарій</span>
      <span class="sc-panel-subtitle">📋 Сценарій</span>
    </div>
    <input type="hidden" id="scScenarioId" value="0">

    <div class="sc-divider">
      <div class="sc-field">
        <label>Назва сценарію</label>
        <input type="text" id="scScenarioName" placeholder="напр. Підтвердження НП">
      </div>
    </div>

    <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:10px">
      Кроки
    </div>
    <div class="sc-steps" id="scStepList"></div>
    <button class="sc-add-step" onclick="SC.addStep(null)" style="margin-top:8px">+ Додати крок</button>

    <div class="sc-actions">
      <button class="btn btn-primary" onclick="SC.saveScenario()">Зберегти</button>
      <button class="btn btn-ghost" onclick="SC.cancel()">Скасувати</button>
      <button class="btn btn-danger btn-sm" id="scScenarioDeleteBtn" onclick="SC.deleteScenario()" style="margin-left:auto;display:none">Видалити</button>
    </div>
    <div class="modal-error" id="scScenarioError" style="display:none;margin-top:8px"></div>
  </div>

</div><!-- /scPanel -->
</div><!-- /sc-layout -->
</div>

<!-- Step template (hidden) -->
<script type="text/x-template" id="scStepTpl">
  <div class="sc-step" data-step-idx="__IDX__">
    <div class="sc-step-head" onclick="SC.toggleStep(this)">
      <span class="sc-step-num">__NUM__</span>
      <div style="flex:1;min-width:0">
        <div class="sc-step-summary">Крок __NUM__</div>
        <div class="sc-step-executor">🤖 Робот · send_message · 0 хв</div>
      </div>
      <button class="sc-step-del" onclick="SC.removeStep(event,__IDX__)">×</button>
    </div>
    <div class="sc-step-body">
      <div class="sc-step-grid">
        <div class="sc-field">
          <label>Виконавець</label>
          <select class="sc-executor" onchange="SC.updateStepSummary(this)">
            <option value="robot">🤖 Робот</option>
            <option value="operator">👤 Оператор</option>
            <option value="ai">✨ AI</option>
          </select>
        </div>
        <div class="sc-field">
          <label>Дія</label>
          <select class="sc-action-type" onchange="SC.renderStepParams(this);SC.updateStepSummary(this)">
            <option value="send_message">Надіслати повідомлення</option>
            <option value="send_invoice">Надіслати рахунок</option>
            <option value="create_task">Створити задачу оператору</option>
            <option value="change_status">Змінити статус заказу</option>
            <option value="wait">Очікувати</option>
          </select>
        </div>
        <div class="sc-field">
          <label>Затримка (хв)</label>
          <input type="number" class="sc-delay" value="0" min="0" onchange="SC.updateStepSummary(this)">
        </div>
      </div>
      <div class="sc-step-params"></div>
    </div>
  </div>
</script>

<script>
var SC_PAYMENT_METHODS  = <?php echo json_encode(array_map(function($m){ return array('id'=>(int)$m['id'],'name'=>$m['name_uk']); }, $paymentMethods), JSON_UNESCAPED_UNICODE); ?>;
var SC_DELIVERY_METHODS = <?php echo json_encode(array_map(function($m){ return array('id'=>(int)$m['id'],'name'=>$m['name_uk']); }, $deliveryMethods), JSON_UNESCAPED_UNICODE); ?>;
var SC_ORDER_STATUSES   = [
    {id:'new',name:'Новий'},{id:'confirmed',name:'Підтверджено'},{id:'in_progress',name:'В роботі'},
    {id:'waiting_payment',name:'Очікує оплату'},{id:'paid',name:'Оплачено'},
    {id:'partially_shipped',name:'Частково відвантажено'},{id:'shipped',name:'Відвантажено'},
    {id:'completed',name:'Виконано'},{id:'cancelled',name:'Скасовано'}
];
var SC_COND_FIELDS = [
    {key:'order.payment_method_id', label:'Тип оплати',    type:'multiselect', opts: SC_PAYMENT_METHODS},
    {key:'order.delivery_method_id',label:'Тип доставки',  type:'multiselect', opts: SC_DELIVERY_METHODS},
    {key:'order.status',            label:'Статус заказу', type:'multiselect', opts: SC_ORDER_STATUSES},
    {key:'order.sum_total',         label:'Сума замовлення',type:'number'},
    {key:'order.comment_manager',   label:'Коментар',      type:'text'}
];

var SC = {
    _stepIdx: 0,

    init: function() { this.loadQueue(); },

    // ── Panel helpers ─────────────────────────────────────────────────────────

    showPanel: function(name) {
        document.getElementById('scEmptyPanel').style.display    = 'none';
        document.getElementById('scTriggerPanel').style.display  = 'none';
        document.getElementById('scScenarioPanel').style.display = 'none';
        document.getElementById(name).style.display = '';
    },

    cancel: function() {
        document.querySelectorAll('.sc-trigger-row,.sc-scenario-row').forEach(function(r){ r.classList.remove('selected'); });
        document.getElementById('scEmptyPanel').style.display    = '';
        document.getElementById('scTriggerPanel').style.display  = 'none';
        document.getElementById('scScenarioPanel').style.display = 'none';
    },

    // ── Trigger ───────────────────────────────────────────────────────────────

    selectTrigger: function(id) {
        document.querySelectorAll('.sc-trigger-row,.sc-scenario-row').forEach(function(r){ r.classList.remove('selected'); });
        var row = document.querySelector('.sc-trigger-row[data-id="'+id+'"]');
        if (row) row.classList.add('selected');

        fetch('/sales/api/get_trigger?id=' + id)
            .then(function(r){ return r.json(); })
            .then(function(d) { if (d.ok) SC.openTriggerEdit(d.trigger); });
    },

    newTrigger: function() {
        document.querySelectorAll('.sc-trigger-row,.sc-scenario-row').forEach(function(r){ r.classList.remove('selected'); });
        SC.openTriggerEdit(null);
    },

    openTriggerEdit: function(trigger) {
        this.showPanel('scTriggerPanel');
        var isNew = !trigger || !trigger.id;
        document.getElementById('scTriggerTitle').textContent   = isNew ? 'Новий тригер' : trigger.name;
        document.getElementById('scTriggerId').value            = trigger ? trigger.id : 0;
        document.getElementById('scTriggerName').value          = trigger ? trigger.name : '';
        document.getElementById('scEventType').value            = trigger ? trigger.event_type : 'order_created';
        document.getElementById('scTriggerDelay').value         = trigger ? trigger.delay_minutes : 0;
        document.getElementById('scTriggerStatus').checked      = !trigger || trigger.status == 1;
        document.getElementById('scTriggerDeleteBtn').style.display = isNew ? 'none' : '';
        document.getElementById('scTriggerError').style.display = 'none';

        // Scenario picker
        var picker = document.getElementById('scScenarioPicker');
        picker.value = trigger ? (trigger.scenario_id || '') : '';

        this.loadConditions(trigger ? (trigger.conditions || null) : null);
    },

    saveTrigger: function() {
        var name = document.getElementById('scTriggerName').value.trim();
        if (!name) { SC.showError('Вкажіть назву тригера', 'scTriggerError'); return; }
        var scenarioId = document.getElementById('scScenarioPicker').value;
        if (!scenarioId) { SC.showError('Оберіть сценарій', 'scTriggerError'); return; }

        var condObj = SC.collectConditions();
        var fd = new FormData();
        fd.append('id',            document.getElementById('scTriggerId').value);
        fd.append('name',          name);
        fd.append('event_type',    document.getElementById('scEventType').value);
        fd.append('delay_minutes', document.getElementById('scTriggerDelay').value);
        fd.append('scenario_id',   scenarioId);
        fd.append('conditions',    condObj ? JSON.stringify(condObj) : '');
        fd.append('status',        document.getElementById('scTriggerStatus').checked ? 1 : 0);

        fetch('/sales/api/save_trigger', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (!d.ok) { SC.showError(d.error || 'Помилка', 'scTriggerError'); return; }
                window.location.reload();
            });
    },

    deleteTrigger: function() {
        var id = parseInt(document.getElementById('scTriggerId').value);
        if (!id || !confirm('Видалити тригер?')) return;
        var fd = new FormData();
        fd.append('id', id);
        fetch('/sales/api/delete_trigger', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function() { window.location.reload(); });
    },

    // ── Scenario ──────────────────────────────────────────────────────────────

    selectScenario: function(id) {
        document.querySelectorAll('.sc-trigger-row,.sc-scenario-row').forEach(function(r){ r.classList.remove('selected'); });
        var row = document.querySelector('.sc-scenario-row[data-id="'+id+'"]');
        if (row) row.classList.add('selected');

        fetch('/sales/api/get_scenario?id=' + id)
            .then(function(r){ return r.json(); })
            .then(function(d) { if (d.ok) SC.openScenarioEdit(d.scenario, d.steps); });
    },

    newScenario: function() {
        document.querySelectorAll('.sc-trigger-row,.sc-scenario-row').forEach(function(r){ r.classList.remove('selected'); });
        SC.openScenarioEdit(null, []);
    },

    openScenarioEdit: function(scenario, steps) {
        this.showPanel('scScenarioPanel');
        var isNew = !scenario || !scenario.id;
        document.getElementById('scScenarioTitle').textContent      = isNew ? 'Новий сценарій' : scenario.name;
        document.getElementById('scScenarioId').value               = scenario ? scenario.id : 0;
        document.getElementById('scScenarioName').value             = scenario ? scenario.name : '';
        document.getElementById('scScenarioDeleteBtn').style.display = isNew ? 'none' : '';
        document.getElementById('scScenarioError').style.display    = 'none';

        this._stepIdx = 0;
        var list = document.getElementById('scStepList');
        list.innerHTML = '';
        var self = this;
        if (steps && steps.length) {
            steps.forEach(function(s) { self.addStep(s); });
        } else {
            this.addStep(null);
        }
    },

    saveScenario: function() {
        var name = document.getElementById('scScenarioName').value.trim();
        if (!name) { SC.showError('Вкажіть назву сценарію', 'scScenarioError'); return; }

        var steps = SC.collectSteps();
        var fd = new FormData();
        fd.append('id',    document.getElementById('scScenarioId').value);
        fd.append('name',  name);
        fd.append('steps', JSON.stringify(steps));

        fetch('/sales/api/save_scenario', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (!d.ok) { SC.showError(d.error || 'Помилка', 'scScenarioError'); return; }
                window.location.reload();
            });
    },

    deleteScenario: function() {
        var id = parseInt(document.getElementById('scScenarioId').value);
        if (!id || !confirm('Видалити сценарій?')) return;
        var fd = new FormData();
        fd.append('id', id);
        fetch('/sales/api/delete_scenario', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (!d.ok) { alert(d.error || 'Помилка'); return; }
                window.location.reload();
            });
    },

    // ── Steps ─────────────────────────────────────────────────────────────────

    addStep: function(data) {
        var idx = this._stepIdx++;
        var tpl = document.getElementById('scStepTpl').innerHTML;
        tpl = tpl.replace(/__IDX__/g, idx).replace(/__NUM__/g, idx + 1);

        var wrap = document.createElement('div');
        wrap.innerHTML = tpl;
        var el = wrap.firstElementChild;
        document.getElementById('scStepList').appendChild(el);

        if (data) {
            el.querySelector('.sc-executor').value    = data.executor    || 'robot';
            el.querySelector('.sc-action-type').value = data.action_type || 'send_message';
            el.querySelector('.sc-delay').value       = data.delay_minutes || 0;
        }
        this.renderStepParams(el.querySelector('.sc-action-type'), data ? data.action_params : null);
        this.updateStepSummary(el.querySelector('.sc-executor'));
        el.querySelector('.sc-step-body').classList.add('open');
    },

    toggleStep: function(headEl) {
        headEl.parentElement.querySelector('.sc-step-body').classList.toggle('open');
    },

    removeStep: function(e, idx) {
        e.stopPropagation();
        var el = document.querySelector('.sc-step[data-step-idx="'+idx+'"]');
        if (el) el.remove();
        this.renumberSteps();
    },

    renumberSteps: function() {
        document.querySelectorAll('.sc-step').forEach(function(el, i) {
            el.querySelector('.sc-step-num').textContent     = i + 1;
            el.querySelector('.sc-step-summary').textContent = 'Крок ' + (i + 1);
        });
    },

    updateStepSummary: function(el) {
        var step   = el.closest('.sc-step');
        var exec   = step.querySelector('.sc-executor').value;
        var action = step.querySelector('.sc-action-type').value;
        var delay  = step.querySelector('.sc-delay').value;
        var execLbl = {robot:'🤖 Робот', operator:'👤 Оператор', ai:'✨ AI'}[exec] || exec;
        var actLbl  = {send_message:'Повідомлення', send_invoice:'Рахунок',
                       create_task:'Задача', change_status:'Змінити статус', wait:'Очікувати'}[action] || action;
        step.querySelector('.sc-step-executor').textContent = execLbl + ' · ' + actLbl + ' · ' + delay + 'хв';
    },

    renderStepParams: function(selectEl, savedParams) {
        var step   = selectEl.closest('.sc-step');
        var action = selectEl.value;
        var params = {};
        if (savedParams) {
            try { params = typeof savedParams === 'string' ? JSON.parse(savedParams) : savedParams; } catch(e) {}
        }
        var wrap = step.querySelector('.sc-step-params');
        var html = '';

        if (action === 'send_message' || action === 'send_invoice') {
            var chViber = (params.channels && params.channels.indexOf('viber') !== -1) || !savedParams ? ' checked' : '';
            var chSms   = params.channels && params.channels.indexOf('sms')   !== -1 ? ' checked' : '';
            var chEmail = params.channels && params.channels.indexOf('email')  !== -1 ? ' checked' : '';
            html += '<label>Канали відправки</label>'
                + '<div style="display:flex;gap:10px;margin-bottom:8px">'
                + '<label style="display:flex;align-items:center;gap:4px;font-size:12px;text-transform:none;letter-spacing:0"><input type="checkbox" class="sc-ch-viber"' + chViber + '> Viber</label>'
                + '<label style="display:flex;align-items:center;gap:4px;font-size:12px;text-transform:none;letter-spacing:0"><input type="checkbox" class="sc-ch-sms"' + chSms + '> SMS</label>'
                + '<label style="display:flex;align-items:center;gap:4px;font-size:12px;text-transform:none;letter-spacing:0"><input type="checkbox" class="sc-ch-email"' + chEmail + '> Email</label>'
                + '</div>';
            if (action === 'send_message') {
                html += '<label>Текст повідомлення</label>'
                    + '<textarea class="sc-msg-text" rows="3" placeholder="Доброго дня! Ваш рахунок №{{order.number}} на суму {{order.sum_total}} грн…">'
                    + (params.text || '') + '</textarea>'
                    + '<div style="font-size:10px;color:var(--text-muted);margin-top:2px">Змінні: {{order.number}}, {{order.sum_total}}, {{counterparty.name}}</div>';
            }
        }

        if (action === 'create_task') {
            var tt = params.task_type || 'call_back';
            var pri = params.priority || 3;
            var taskTypes = {call_back:'Передзвонити', follow_up:'Нагадати', send_docs:'Надіслати документи', payment:'Платіж', meeting:'Зустріч', other:'Інше'};
            html += '<label>Назва задачі</label>'
                + '<input type="text" class="sc-task-title" placeholder="напр. Зателефонувати про оплату" value="' + (params.task_title || '') + '">'
                + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:6px">'
                + '<div><label>Тип</label><select class="sc-task-type">';
            for (var k in taskTypes) { html += '<option value="'+k+'"' + (tt===k?' selected':'') + '>'+taskTypes[k]+'</option>'; }
            html += '</select></div>'
                + '<div><label>Пріоритет</label><select class="sc-task-priority">'
                + ['↓ Низький','→ Нормальний','↑ Важливий','⚡ Терміновий','🔥 Критичний'].map(function(l,i){
                    return '<option value="'+(i+1)+'"'+(pri===(i+1)?' selected':'')+'>'+l+'</option>';
                  }).join('')
                + '</select></div></div>'
                + '<label style="margin-top:6px">Дедлайн (через годин)</label>'
                + '<input type="number" class="sc-task-due-hours" min="0" value="' + (params.due_hours || '') + '" placeholder="24">';
        }

        if (action === 'change_status') {
            var statuses = {new:'Новий',confirmed:'Підтверджено',in_progress:'В роботі',waiting_payment:'Очікує оплату',
                            paid:'Оплачено',shipped:'Відвантажено',completed:'Виконано',cancelled:'Скасовано'};
            html += '<label>Новий статус</label><select class="sc-new-status">';
            for (var sk in statuses) { html += '<option value="'+sk+'"'+(params.status===sk?' selected':'')+'>'+statuses[sk]+'</option>'; }
            html += '</select>';
        }

        if (action === 'wait') {
            html = '<div style="font-size:12px;color:var(--text-muted)">Крок очікування — затримка перед наступним кроком. Вкажіть час затримки вище.</div>';
        }

        wrap.innerHTML = html;
    },

    collectStepParams: function(step) {
        var action = step.querySelector('.sc-action-type').value;
        var params = {};
        if (action === 'send_message' || action === 'send_invoice') {
            var chs = [];
            if (step.querySelector('.sc-ch-viber') && step.querySelector('.sc-ch-viber').checked) chs.push('viber');
            if (step.querySelector('.sc-ch-sms')   && step.querySelector('.sc-ch-sms').checked)   chs.push('sms');
            if (step.querySelector('.sc-ch-email') && step.querySelector('.sc-ch-email').checked)  chs.push('email');
            params.channels = chs;
            if (action === 'send_message') {
                params.text = step.querySelector('.sc-msg-text') ? step.querySelector('.sc-msg-text').value : '';
            }
        }
        if (action === 'create_task') {
            params.task_title = step.querySelector('.sc-task-title')    ? step.querySelector('.sc-task-title').value    : '';
            params.task_type  = step.querySelector('.sc-task-type')     ? step.querySelector('.sc-task-type').value     : 'other';
            params.priority   = step.querySelector('.sc-task-priority') ? parseInt(step.querySelector('.sc-task-priority').value) : 3;
            var dh = step.querySelector('.sc-task-due-hours');
            if (dh && dh.value) params.due_hours = parseInt(dh.value);
        }
        if (action === 'change_status') {
            params.status = step.querySelector('.sc-new-status') ? step.querySelector('.sc-new-status').value : '';
        }
        return params;
    },

    collectSteps: function() {
        var steps = [];
        document.querySelectorAll('.sc-step').forEach(function(step, i) {
            var params = SC.collectStepParams(step);
            steps.push({
                step_order:    i,
                executor:      step.querySelector('.sc-executor').value,
                action_type:   step.querySelector('.sc-action-type').value,
                action_params: JSON.stringify(params),
                delay_minutes: parseInt(step.querySelector('.sc-delay').value) || 0,
            });
        });
        return steps;
    },

    // ── Conditions builder ────────────────────────────────────────────────────

    addCondRule: function(ruleDef) {
        var container = document.getElementById('scCondRules');
        var fieldDef  = null;
        if (ruleDef && ruleDef.key) {
            for (var i = 0; i < SC_COND_FIELDS.length; i++) {
                if (SC_COND_FIELDS[i].key === ruleDef.key) { fieldDef = SC_COND_FIELDS[i]; break; }
            }
        }
        var ruleEl = document.createElement('div');
        ruleEl.className = 'sc-cond-rule';

        var fieldHtml = '<select style="flex-shrink:0;min-width:150px;font-size:12px;padding:4px 6px;border:1px solid var(--border-input);border-radius:5px;font-family:var(--font)" onchange="SC.onCondFieldChange(this)">'
            + '<option value="">— Поле —</option>';
        SC_COND_FIELDS.forEach(function(f) {
            fieldHtml += '<option value="' + f.key + '"' + (fieldDef && fieldDef.key === f.key ? ' selected' : '') + '>' + f.label + '</option>';
        });
        fieldHtml += '</select>';

        ruleEl.innerHTML = fieldHtml
            + '<div class="sc-cond-rule-inputs"></div>'
            + '<button type="button" class="sc-cond-rule-del" onclick="SC.removeCondRule(this)" title="Видалити">×</button>';

        container.appendChild(ruleEl);
        this.renderCondRuleInputs(ruleEl, fieldDef, ruleDef ? ruleDef.value : null, ruleDef ? ruleDef.op : null);
    },

    onCondFieldChange: function(selectEl) {
        var ruleEl  = selectEl.closest('.sc-cond-rule');
        var key     = selectEl.value;
        var fieldDef = null;
        for (var i = 0; i < SC_COND_FIELDS.length; i++) {
            if (SC_COND_FIELDS[i].key === key) { fieldDef = SC_COND_FIELDS[i]; break; }
        }
        this.renderCondRuleInputs(ruleEl, fieldDef, null, null);
    },

    renderCondRuleInputs: function(ruleEl, fieldDef, savedValue, savedOp) {
        var wrap = ruleEl.querySelector('.sc-cond-rule-inputs');
        if (!fieldDef) { wrap.innerHTML = ''; return; }
        var html = '';

        if (fieldDef.type === 'multiselect') {
            var selected = Array.isArray(savedValue) ? savedValue.map(String) : [];
            html += '<div class="sc-cond-pills">';
            fieldDef.opts.forEach(function(opt) {
                var chk = selected.indexOf(String(opt.id)) !== -1 ? ' checked' : '';
                html += '<label class="sc-cond-pill' + (chk ? ' checked' : '') + '">'
                    + '<input type="checkbox" value="' + opt.id + '"' + chk + ' onchange="SC.onPillChange(this)">'
                    + opt.name + '</label>';
            });
            html += '</div>';
        } else if (fieldDef.type === 'number') {
            var op  = savedOp  || '>=';
            var val = (savedValue !== null && savedValue !== undefined) ? savedValue : '';
            html += '<select style="width:68px;font-size:12px;padding:4px 5px;border:1px solid var(--border-input);border-radius:5px;font-family:var(--font)">'
                + ['>=','<=','>','<'].map(function(o){ return '<option value="'+o+'"'+(op===o?' selected':'')+'>'+o+'</option>'; }).join('')
                + '</select>'
                + '<input type="number" style="width:90px;font-size:12px;padding:4px 6px;border:1px solid var(--border-input);border-radius:5px;font-family:var(--font)" placeholder="5000" value="' + val + '">';
        } else if (fieldDef.type === 'text') {
            var val2 = (savedValue !== null && savedValue !== undefined) ? savedValue : '';
            html += '<span style="font-size:11px;color:var(--text-muted);align-self:center;white-space:nowrap;padding:0 2px">містить</span>'
                + '<input type="text" style="font-size:12px;padding:4px 6px;border:1px solid var(--border-input);border-radius:5px;font-family:var(--font);min-width:120px" placeholder="термінов…" value="' + val2 + '">';
        }

        wrap.innerHTML = html;
    },

    removeCondRule: function(btn) { btn.closest('.sc-cond-rule').remove(); },

    onPillChange: function(cb) {
        var label = cb.closest('.sc-cond-pill');
        if (cb.checked) label.classList.add('checked');
        else            label.classList.remove('checked');
    },

    collectConditions: function() {
        var rules = [];
        document.querySelectorAll('#scCondRules .sc-cond-rule').forEach(function(ruleEl) {
            var sel = ruleEl.querySelector('select');
            var key = sel ? sel.value : '';
            if (!key) return;

            var fieldDef = null;
            for (var i = 0; i < SC_COND_FIELDS.length; i++) {
                if (SC_COND_FIELDS[i].key === key) { fieldDef = SC_COND_FIELDS[i]; break; }
            }
            if (!fieldDef) return;

            if (fieldDef.type === 'multiselect') {
                var vals = [];
                ruleEl.querySelectorAll('input[type=checkbox]:checked').forEach(function(cb) {
                    vals.push(parseInt(cb.value) || cb.value);
                });
                if (vals.length) rules.push({key: key, op: 'in', value: vals});
            } else if (fieldDef.type === 'number') {
                var inputs = ruleEl.querySelectorAll('.sc-cond-rule-inputs select, .sc-cond-rule-inputs input');
                var op  = inputs[0] ? inputs[0].value : '>=';
                var val = inputs[1] ? parseFloat(inputs[1].value) : NaN;
                if (!isNaN(val)) rules.push({key: key, op: op, value: val});
            } else if (fieldDef.type === 'text') {
                var inp = ruleEl.querySelector('.sc-cond-rule-inputs input');
                var txt = inp ? inp.value.trim() : '';
                if (txt) rules.push({key: key, op: 'contains', value: txt});
            }
        });

        if (!rules.length) return null;
        var logicEl = document.querySelector('input[name="scCondLogic"]:checked');
        return {logic: logicEl ? logicEl.value : 'AND', rules: rules};
    },

    loadConditions: function(condJson) {
        var container = document.getElementById('scCondRules');
        container.innerHTML = '';
        document.getElementById('scCondLogicAnd').checked = true;

        if (!condJson) return;
        var cond = null;
        try { cond = typeof condJson === 'string' ? JSON.parse(condJson) : condJson; } catch(e) { return; }
        if (!cond || !cond.rules || !cond.rules.length) return;

        if (cond.logic === 'OR') document.getElementById('scCondLogicOr').checked = true;
        var self = this;
        cond.rules.forEach(function(rule) { self.addCondRule(rule); });
    },

    // ── Queue monitor ──────────────────────────────────────────────────────────

    loadQueue: function() {
        fetch('/sales/api/get_queue')
            .then(function(r){ return r.json(); })
            .then(function(d) {
                var el = document.getElementById('scQueueList');
                if (!d.ok || !d.items.length) {
                    el.innerHTML = '<div style="font-size:12px;color:var(--text-muted);padding:4px 0">Черга порожня</div>';
                    return;
                }
                var html = '';
                d.items.forEach(function(q) {
                    var cls = {pending:'sc-queue-pending',done:'sc-queue-done',failed:'sc-queue-failed',running:'sc-queue-running'}[q.status] || '';
                    html += '<div class="sc-queue-row">'
                        + '<span class="sc-queue-status ' + cls + '">' + q.status + '</span>'
                        + '<span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'
                        + q.action_type + (q.counterparty_name ? ' · ' + q.counterparty_name : '') + '</span>'
                        + '<span style="color:var(--text-muted);white-space:nowrap">' + SC.fmtTime(q.fire_at) + '</span>'
                        + '</div>';
                });
                el.innerHTML = html;
            });
    },

    fmtTime: function(dt) {
        if (!dt) return '';
        var d = new Date(dt);
        return ('0'+d.getHours()).slice(-2)+':'+('0'+d.getMinutes()).slice(-2);
    },

    showError: function(msg, elId) {
        var el = document.getElementById(elId);
        if (!el) return;
        el.textContent = msg;
        el.style.display = '';
    },
};

SC.init();
</script>

<?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>
