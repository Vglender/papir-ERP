<?php
$title     = 'Сценарії';
$activeNav = 'sales';
$subNav    = 'scenarios';
require_once __DIR__ . '/../../shared/layout.php';
?>
<style>
.sc-wrap { max-width: 1300px; margin: 0 auto; padding: 20px 16px 40px; }
.sc-toolbar { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
.sc-toolbar h1 { margin: 0; font-size: 18px; font-weight: 700; flex: 1; }

/* ── Layout ────────────────────────────────────────────────────── */
.sc-layout { display: grid; grid-template-columns: 380px 1fr; gap: 20px; align-items: start; }
@media(max-width:900px){ .sc-layout { grid-template-columns: 1fr; } }

/* ── Trigger list ──────────────────────────────────────────────── */
.sc-list-head { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;
    letter-spacing: .4px; padding: 0 4px 8px; border-bottom: 1px solid var(--border); margin-bottom: 8px; }
.sc-event-group { margin-bottom: 16px; }
.sc-event-label { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;
    letter-spacing: .3px; padding: 4px 6px; }
.sc-trigger-row {
    display: flex; align-items: center; gap: 8px; padding: 8px 10px;
    border: 1px solid var(--border); border-radius: 7px; margin-bottom: 6px; cursor: pointer;
    background: var(--bg-card); transition: border-color .12s;
}
.sc-trigger-row:hover { border-color: var(--blue-light); }
.sc-trigger-row.selected { border-color: var(--blue); background: var(--blue-bg); }
.sc-trigger-name { font-weight: 600; font-size: 13px; flex: 1; min-width: 0;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sc-trigger-scenario { font-size: 11px; color: var(--text-muted); margin-top: 1px; }
.sc-trigger-meta { display: flex; gap: 5px; align-items: center; flex-shrink: 0; }
.sc-status-pill { display: inline-block; width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.sc-status-on  { background: #22c55e; }
.sc-status-off { background: #d1d5db; }
.sc-fired-cnt { font-size: 10px; color: var(--text-muted); }

/* ── Right panel ───────────────────────────────────────────────── */
.sc-panel { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 20px; }
.sc-panel-title { font-size: 15px; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.sc-section { margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--border); }
.sc-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
.sc-section-title { font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;
    letter-spacing: .4px; margin-bottom: 10px; }
.sc-field { margin-bottom: 10px; }
.sc-field label { display: block; font-size: 11px; font-weight: 600; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: .3px; margin-bottom: 3px; }
.sc-field input, .sc-field select, .sc-field textarea {
    width: 100%; padding: 6px 9px; border: 1px solid var(--border-input); border-radius: var(--radius-sm);
    font-size: 13px; font-family: var(--font); background: #fff; outline: none; box-sizing: border-box;
}
.sc-field input:focus, .sc-field select:focus { border-color: var(--blue-light); }
.sc-cond-row { display: grid; grid-template-columns: 1fr 80px 1fr; gap: 6px; }
.sc-actions { display: flex; gap: 8px; margin-top: 16px; align-items: center; }

/* ── Steps builder ─────────────────────────────────────────────── */
.sc-steps { display: flex; flex-direction: column; gap: 8px; }
.sc-step {
    border: 1px solid var(--border); border-radius: 8px; overflow: hidden; background: #fff;
}
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
    <button class="btn btn-primary" onclick="SC.newTrigger()">+ Новий тригер</button>
    <button class="btn btn-ghost" onclick="SC.newScenario()">+ Сценарій</button>
</div>

<div class="sc-layout">

<!-- ── Left: trigger list ──────────────────────────────────────────── -->
<div>
    <div class="card" style="padding:16px">
        <div class="sc-list-head">Тригери (<?php echo count($triggers); ?>)</div>
        <div id="scTriggerList">
        <?php if (empty($triggers)): ?>
        <div style="text-align:center;padding:32px 0;color:var(--text-muted);font-size:13px">
            Тригерів ще немає.<br>Натисніть «+ Новий тригер» щоб створити.
        </div>
        <?php else:
            $grouped = array();
            foreach ($triggers as $t) {
                $grouped[$t['event_type']][] = $t;
            }
            foreach ($grouped as $eventType => $rows):
        ?>
        <div class="sc-event-group">
            <div class="sc-event-label"><?php echo htmlspecialchars(ScenarioRepository::eventLabel($eventType)); ?></div>
            <?php foreach ($rows as $t): ?>
            <div class="sc-trigger-row" data-id="<?php echo $t['id']; ?>" onclick="SC.selectTrigger(<?php echo $t['id']; ?>)">
                <span class="sc-status-pill <?php echo $t['status'] ? 'sc-status-on' : 'sc-status-off'; ?>"></span>
                <div style="flex:1;min-width:0">
                    <div class="sc-trigger-name"><?php echo htmlspecialchars($t['name']); ?></div>
                    <div class="sc-trigger-scenario">→ <?php echo htmlspecialchars($t['scenario_name']); ?></div>
                </div>
                <div class="sc-trigger-meta">
                    <?php if ($t['fired_count']): ?>
                    <span class="sc-fired-cnt"><?php echo $t['fired_count']; ?>×</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; endif; ?>
        </div>

        <!-- Queue monitor -->
        <div style="margin-top:16px;border-top:1px solid var(--border);padding-top:12px">
            <div class="sc-list-head" style="margin-bottom:6px">Черга (останні 10)</div>
            <div id="scQueueList"><div style="font-size:12px;color:var(--text-muted)">Завантаження…</div></div>
        </div>
    </div>
</div>

<!-- ── Right: edit panel ─────────────────────────────────────────── -->
<div id="scPanel">
    <div class="sc-panel" id="scEmptyPanel">
        <div style="text-align:center;padding:48px 0;color:var(--text-muted)">
            <div style="font-size:32px;margin-bottom:12px">⚡</div>
            <div style="font-weight:700;font-size:14px;margin-bottom:6px">Тригери та сценарії</div>
            <div style="font-size:12px;line-height:1.6">
                Тригер — подія (новий заказ, зміна статусу)<br>
                Сценарій — ланцюжок кроків: 🤖 Робот → 👤 Оператор<br>
                Decay-таймер піднімає задачі вгору при бездії
            </div>
        </div>
    </div>

    <!-- Trigger edit form (hidden until selected/new) -->
    <div class="sc-panel" id="scEditPanel" style="display:none">
        <div class="sc-panel-title">
            <span id="scEditTitle">Новий тригер</span>
        </div>

        <!-- Trigger settings -->
        <div class="sc-section">
            <div class="sc-section-title">Тригер</div>
            <input type="hidden" id="scTriggerId" value="0">
            <input type="hidden" id="scScenarioId" value="0">
            <div class="sc-field">
                <label>Назва</label>
                <input type="text" id="scTriggerName" placeholder="напр. Новий заказ / Передоплата">
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
                <label>Умова (необов'язково)</label>
                <div class="sc-cond-row">
                    <input type="text" id="scCondKey" placeholder="order.payment_type">
                    <select id="scCondOp">
                        <option value="">=</option>
                        <option value="=">=</option>
                        <option value="!=">≠</option>
                        <option value=">">&gt;</option>
                        <option value="<">&lt;</option>
                        <option value="in">in</option>
                        <option value="not_in">not in</option>
                    </select>
                    <input type="text" id="scCondVal" placeholder="prepaid">
                </div>
                <div style="font-size:10px;color:var(--text-muted);margin-top:3px">
                    Ключі: order.payment_type, order.status, order.sum_total, order.organization_id
                </div>
            </div>
            <div class="sc-field">
                <label>Затримка перед стартом сценарію (хв)</label>
                <input type="number" id="scTriggerDelay" value="0" min="0" style="width:100px">
            </div>
        </div>

        <!-- Scenario steps -->
        <div class="sc-section">
            <div class="sc-section-title" style="display:flex;align-items:center;justify-content:space-between">
                <span>Кроки сценарію</span>
                <input type="text" id="scScenarioName" placeholder="Назва сценарію" style="width:200px;font-size:12px;padding:4px 7px;border:1px solid var(--border-input);border-radius:5px;font-family:var(--font)">
            </div>
            <div class="sc-steps" id="scStepList"></div>
            <button class="sc-add-step" onclick="SC.addStep()" style="margin-top:8px">+ Додати крок</button>
        </div>

        <div class="sc-actions">
            <button class="btn btn-primary" onclick="SC.save()">Зберегти</button>
            <button class="btn btn-ghost" onclick="SC.cancel()">Скасувати</button>
            <button class="btn btn-danger btn-sm" id="scDeleteBtn" onclick="SC.deleteTrigger()" style="margin-left:auto;display:none">Видалити</button>
            <label style="margin-left:8px;display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer">
                <input type="checkbox" id="scStatus" checked> Активний
            </label>
        </div>
        <div class="modal-error" id="scError" style="display:none;margin-top:8px"></div>
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
      <!-- Dynamic params rendered by SC.renderStepParams -->
      <div class="sc-step-params"></div>
      <!-- Condition for this step (optional) -->
      <div style="margin-top:8px;padding-top:8px;border-top:1px solid var(--border)">
        <label style="font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.3px;font-weight:700">Умова виконання (якщо пусто — завжди)</label>
        <div class="sc-cond-row" style="margin-top:4px">
          <input type="text" class="sc-step-cond-key" placeholder="order.status">
          <select class="sc-step-cond-op">
            <option value="">=</option><option value="=">=</option><option value="!=">≠</option>
            <option value="in">in</option><option value="not_in">not in</option>
          </select>
          <input type="text" class="sc-step-cond-val" placeholder="waiting_payment">
        </div>
      </div>
    </div>
  </div>
</script>

<script>
var SC = {
    _stepIdx: 0,

    init: function() {
        this.loadQueue();
    },

    // ── Trigger list ──────────────────────────────────────────────────────────

    selectTrigger: function(id) {
        document.querySelectorAll('.sc-trigger-row').forEach(function(r){ r.classList.remove('selected'); });
        var row = document.querySelector('.sc-trigger-row[data-id="'+id+'"]');
        if (row) row.classList.add('selected');

        fetch('/sales/api/get_trigger?id=' + id)
            .then(function(r){ return r.json(); })
            .then(function(d) { if (d.ok) SC.openEdit(d.trigger, d.steps, d.scenario_name); });
    },

    newTrigger: function() {
        document.querySelectorAll('.sc-trigger-row').forEach(function(r){ r.classList.remove('selected'); });
        SC.openEdit(null, [], '');
    },

    newScenario: function() {
        SC.openEdit(null, [], '');
        document.getElementById('scScenarioName').focus();
    },

    // ── Edit panel ────────────────────────────────────────────────────────────

    openEdit: function(trigger, steps, scenarioName) {
        document.getElementById('scEmptyPanel').style.display = 'none';
        document.getElementById('scEditPanel').style.display  = '';

        var isNew = !trigger || !trigger.id;
        document.getElementById('scEditTitle').textContent = isNew ? 'Новий тригер' : trigger.name;
        document.getElementById('scTriggerId').value  = trigger ? trigger.id    : 0;
        document.getElementById('scScenarioId').value = trigger ? trigger.scenario_id : 0;
        document.getElementById('scTriggerName').value  = trigger ? trigger.name : '';
        document.getElementById('scEventType').value    = trigger ? trigger.event_type : 'order_created';
        document.getElementById('scCondKey').value      = trigger ? (trigger.condition_key   || '') : '';
        document.getElementById('scCondOp').value       = trigger ? (trigger.condition_op    || '') : '';
        document.getElementById('scCondVal').value      = trigger ? (trigger.condition_value || '') : '';
        document.getElementById('scTriggerDelay').value = trigger ? trigger.delay_minutes : 0;
        document.getElementById('scStatus').checked     = !trigger || trigger.status == 1;
        document.getElementById('scScenarioName').value = scenarioName || '';
        document.getElementById('scDeleteBtn').style.display = isNew ? 'none' : '';
        document.getElementById('scError').style.display = 'none';

        // Render steps
        this._stepIdx = 0;
        var list = document.getElementById('scStepList');
        list.innerHTML = '';
        var self = this;
        if (steps && steps.length) {
            steps.forEach(function(s) { self.addStep(s); });
        } else {
            this.addStep(null);  // default first step
        }
    },

    cancel: function() {
        document.getElementById('scEmptyPanel').style.display = '';
        document.getElementById('scEditPanel').style.display  = 'none';
        document.querySelectorAll('.sc-trigger-row').forEach(function(r){ r.classList.remove('selected'); });
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
            el.querySelector('.sc-executor').value     = data.executor    || 'robot';
            el.querySelector('.sc-action-type').value  = data.action_type || 'send_message';
            el.querySelector('.sc-delay').value        = data.delay_minutes || 0;
            el.querySelector('.sc-step-cond-key').value = data.condition_key   || '';
            el.querySelector('.sc-step-cond-op').value  = data.condition_op    || '';
            el.querySelector('.sc-step-cond-val').value = data.condition_value || '';
        }
        this.renderStepParams(el.querySelector('.sc-action-type'), data ? data.action_params : null);
        this.updateStepSummary(el.querySelector('.sc-executor'));
        // Open body by default
        el.querySelector('.sc-step-body').classList.add('open');
    },

    toggleStep: function(headEl) {
        var body = headEl.parentElement.querySelector('.sc-step-body');
        body.classList.toggle('open');
    },

    removeStep: function(e, idx) {
        e.stopPropagation();
        var el = document.querySelector('.sc-step[data-step-idx="'+idx+'"]');
        if (el) el.remove();
        this.renumberSteps();
    },

    renumberSteps: function() {
        document.querySelectorAll('.sc-step').forEach(function(el, i) {
            el.querySelector('.sc-step-num').textContent = i + 1;
            el.querySelector('.sc-step-summary').textContent = 'Крок ' + (i + 1);
        });
    },

    updateStepSummary: function(el) {
        var step    = el.closest('.sc-step');
        var exec    = step.querySelector('.sc-executor').value;
        var action  = step.querySelector('.sc-action-type').value;
        var delay   = step.querySelector('.sc-delay').value;
        var execLbl = { robot:'🤖 Робот', operator:'👤 Оператор', ai:'✨ AI' }[exec] || exec;
        var actLbl  = { send_message:'Надіслати повідомлення', send_invoice:'Рахунок',
                        create_task:'Задача оператору', change_status:'Змінити статус', wait:'Очікувати' }[action] || action;
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
                    + '<div style="font-size:10px;color:var(--text-muted);margin-top:3px">Змінні: {{order.number}}, {{order.sum_total}}, {{order.status}}</div>';
            }
        }

        if (action === 'create_task') {
            var tt = params.task_type || 'call_back';
            var taskTypes = {call_back:'Передзвонити', follow_up:'Нагадати', send_docs:'Надіслати документи', payment:'Платіж', meeting:'Зустріч', other:'Інше'};
            var pri = params.priority || 3;
            html += '<label>Назва задачі</label>'
                + '<input type="text" class="sc-task-title" placeholder="напр. Зателефонувати про оплату" value="' + (params.task_title || '') + '">'
                + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:6px">'
                + '<div><label>Тип</label><select class="sc-task-type">';
            for (var k in taskTypes) { html += '<option value="'+k+'"' + (tt===k?' selected':'') + '>'+taskTypes[k]+'</option>'; }
            html += '</select></div>'
                + '<div><label>Пріоритет</label><select class="sc-task-priority">'
                + ['↓ Низький','→ Нормальний','↑ Важливий','⚡ Терміновий','🔥 Критичний'].map(function(l,i){ return '<option value="'+(i+1)+'"'+(pri===(i+1)?' selected':'')+'>'+l+'</option>'; }).join('')
                + '</select></div></div>'
                + '<label style="margin-top:6px">Дедлайн (через годин)</label>'
                + '<input type="number" class="sc-task-due-hours" min="0" value="' + (params.due_hours || '') + '" placeholder="24">';
        }

        if (action === 'change_status') {
            var statuses = {new:'Новий',confirmed:'Підтверджено',in_progress:'В роботі',waiting_payment:'Очікує оплату',paid:'Оплачено',shipped:'Відвантажено',completed:'Виконано',cancelled:'Скасовано'};
            html += '<label>Новий статус</label><select class="sc-new-status">';
            for (var sk in statuses) { html += '<option value="'+sk+'"'+(params.status===sk?' selected':'')+'>'+statuses[sk]+'</option>'; }
            html += '</select>';
        }

        if (action === 'wait') {
            html = '<div style="font-size:12px;color:var(--text-muted)">Крок очікування — використовується як затримка перед наступним кроком. Вкажіть затримку вище.</div>';
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
            params.task_title  = step.querySelector('.sc-task-title')    ? step.querySelector('.sc-task-title').value : '';
            params.task_type   = step.querySelector('.sc-task-type')     ? step.querySelector('.sc-task-type').value : 'other';
            params.priority    = step.querySelector('.sc-task-priority') ? parseInt(step.querySelector('.sc-task-priority').value) : 3;
            var dh = step.querySelector('.sc-task-due-hours');
            if (dh && dh.value) params.due_hours = parseInt(dh.value);
        }
        if (action === 'change_status') {
            params.status = step.querySelector('.sc-new-status') ? step.querySelector('.sc-new-status').value : '';
        }
        return params;
    },

    // ── Save ──────────────────────────────────────────────────────────────────

    save: function() {
        var name = document.getElementById('scTriggerName').value.trim();
        var scenName = document.getElementById('scScenarioName').value.trim();

        if (!name) { SC.showError('Вкажіть назву тригера'); return; }
        if (!scenName) { SC.showError('Вкажіть назву сценарію'); return; }

        // Collect steps
        var steps = [];
        document.querySelectorAll('.sc-step').forEach(function(step, i) {
            var params = SC.collectStepParams(step);
            steps.push({
                step_order:      i,
                executor:        step.querySelector('.sc-executor').value,
                action_type:     step.querySelector('.sc-action-type').value,
                action_params:   JSON.stringify(params),
                delay_minutes:   parseInt(step.querySelector('.sc-delay').value) || 0,
                condition_key:   step.querySelector('.sc-step-cond-key').value,
                condition_op:    step.querySelector('.sc-step-cond-op').value,
                condition_value: step.querySelector('.sc-step-cond-val').value,
            });
        });

        var fd = new FormData();
        fd.append('id',              document.getElementById('scTriggerId').value);
        fd.append('scenario_id',     document.getElementById('scScenarioId').value);
        fd.append('name',            name);
        fd.append('event_type',      document.getElementById('scEventType').value);
        fd.append('condition_key',   document.getElementById('scCondKey').value);
        fd.append('condition_op',    document.getElementById('scCondOp').value);
        fd.append('condition_value', document.getElementById('scCondVal').value);
        fd.append('delay_minutes',   document.getElementById('scTriggerDelay').value);
        fd.append('scenario_name',   scenName);
        fd.append('steps',           JSON.stringify(steps));
        fd.append('status',          document.getElementById('scStatus').checked ? 1 : 0);

        fetch('/sales/api/save_trigger', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (!d.ok) { SC.showError(d.error || 'Помилка'); return; }
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

    showError: function(msg) {
        var el = document.getElementById('scError');
        el.textContent = msg;
        el.style.display = '';
    },

    // ── Queue monitor ──────────────────────────────────────────────────────────

    loadQueue: function() {
        fetch('/sales/api/get_queue')
            .then(function(r){ return r.json(); })
            .then(function(d) {
                var el = document.getElementById('scQueueList');
                if (!d.ok || !d.items.length) {
                    el.innerHTML = '<div style="font-size:12px;color:var(--text-muted);padding:8px 0">Черга порожня</div>';
                    return;
                }
                var html = '';
                d.items.forEach(function(q) {
                    var cls = { pending:'sc-queue-pending', done:'sc-queue-done', failed:'sc-queue-failed', running:'sc-queue-running' }[q.status] || '';
                    html += '<div class="sc-queue-row">'
                        + '<span class="sc-queue-status ' + cls + '">' + q.status + '</span>'
                        + '<span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'
                        + (q.action_type) + (q.counterparty_name ? ' · ' + q.counterparty_name : '') + '</span>'
                        + '<span style="color:var(--text-muted);white-space:nowrap">' + SC.fmtTime(q.fire_at) + '</span>'
                        + '</div>';
                });
                el.innerHTML = html;
            });
    },

    fmtTime: function(dt) {
        if (!dt) return '';
        var d = new Date(dt);
        return ('0'+d.getHours()).slice(-2) + ':' + ('0'+d.getMinutes()).slice(-2);
    },
};

SC.init();
</script>

<?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>