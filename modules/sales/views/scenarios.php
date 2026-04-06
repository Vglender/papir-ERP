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
.sc-layout { display: grid; grid-template-columns: 320px 1fr; gap: 20px; align-items: start; }
@media(max-width:900px){ .sc-layout { grid-template-columns: 1fr; } }

/* ── Left panel ────────────────────────────────────────────────── */
.sc-section-head {
    display: flex; align-items: center; justify-content: space-between;
    font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;
    letter-spacing: .4px; padding: 0 2px 8px; border-bottom: 1px solid var(--border);
    margin-bottom: 8px;
}
.sc-section-head .btn-xs { text-transform: none; letter-spacing: 0; font-size: 11px; }
.sc-left-section { margin-bottom: 20px; }

.sc-event-badge {
    display: inline-block; padding: 1px 6px; border-radius: 4px;
    font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px;
    flex-shrink: 0;
}
.sc-event-badge-created  { background: #dcfce7; color: #166534; }
.sc-event-badge-status   { background: #dbeafe; color: #1e40af; }
.sc-event-badge-cancelled{ background: #fee2e2; color: #991b1b; }
.sc-event-badge-task     { background: #ede9fe; color: #5b21b6; }
.sc-event-badge-doc      { background: #f3f4f6; color: #374151; }

.sc-trigger-row, .sc-scenario-row {
    display: flex; align-items: center; gap: 8px; padding: 8px 10px;
    border: 1px solid var(--border); border-radius: 7px; margin-bottom: 5px; cursor: pointer;
    background: var(--bg-card); transition: border-color .12s;
}
.sc-trigger-row:hover, .sc-scenario-row:hover { border-color: var(--blue-light); }
.sc-trigger-row.selected, .sc-scenario-row.selected { border-color: var(--blue); background: var(--blue-bg); }
.sc-row-name { font-weight: 600; font-size: 13px; flex: 1; min-width: 0;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sc-row-sub { font-size: 11px; color: var(--text-muted); margin-top: 1px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sc-row-meta { display: flex; gap: 5px; align-items: center; flex-shrink: 0; }
.sc-status-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.sc-status-on  { background: #22c55e; }
.sc-status-off { background: #d1d5db; }
.sc-fired-cnt { font-size: 10px; color: var(--text-muted); }
.sc-step-cnt { font-size: 10px; background: var(--bg-header); border-radius: 10px;
    padding: 1px 6px; color: var(--text-muted); font-weight: 600; }
.sc-trig-cnt { font-size: 10px; background: #fef3c7; border-radius: 10px;
    padding: 1px 6px; color: #92400e; font-weight: 600; }

/* ── Queue monitor ────────────────────────────────────────────── */
.sc-queue-wrap { border-top: 1px solid var(--border); padding-top: 12px; }
.sc-queue-row { display: flex; gap: 8px; align-items: center; padding: 5px 0;
    border-bottom: 1px solid var(--border); font-size: 12px; }
.sc-queue-row:last-child { border-bottom: none; }
.sc-queue-status { padding: 1px 6px; border-radius: 4px; font-weight: 600;
    font-size: 10px; text-transform: uppercase; flex-shrink: 0; }
.sc-queue-pending { background: #fef9c3; color: #854d0e; }
.sc-queue-done    { background: #dcfce7; color: #166534; }
.sc-queue-failed  { background: #fee2e2; color: #991b1b; }
.sc-queue-running { background: #ede9fe; color: #5b21b6; }

/* ── Right panel ───────────────────────────────────────────────── */
.sc-panel { background: var(--bg-card); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 22px; }
.sc-panel-title { font-size: 15px; font-weight: 700; margin-bottom: 18px;
    display: flex; align-items: center; gap: 8px; }
.sc-panel-subtitle { font-size: 10px; font-weight: 600; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: .4px; background: var(--blue-bg);
    border-radius: 4px; padding: 2px 7px; }

/* ── Form sections (numbered) ─────────────────────────────────── */
.sc-form-section {
    position: relative; padding: 16px; padding-left: 48px;
    border: 1px solid var(--border); border-radius: 8px; margin-bottom: 12px;
    background: var(--bg-body);
}
.sc-form-section-num {
    position: absolute; left: 14px; top: 15px;
    width: 22px; height: 22px; border-radius: 50%;
    background: var(--blue); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700; flex-shrink: 0;
}
.sc-form-section-label {
    font-size: 10px; font-weight: 700; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: .5px; margin-bottom: 10px;
}
.sc-field { margin-bottom: 10px; }
.sc-field:last-child { margin-bottom: 0; }
.sc-field label { display: block; font-size: 11px; font-weight: 600;
    color: var(--text-muted); text-transform: uppercase; letter-spacing: .3px; margin-bottom: 3px; }
.sc-field input, .sc-field select, .sc-field textarea {
    width: 100%; padding: 7px 9px; border: 1px solid var(--border-input); border-radius: var(--radius-sm);
    font-size: 13px; font-family: var(--font); background: #fff; outline: none; box-sizing: border-box;
}
.sc-field input:focus, .sc-field select:focus, .sc-field textarea:focus {
    border-color: var(--blue-light);
}

/* Status toggle row */
.sc-header-row { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
.sc-header-row .sc-field { flex: 1; margin-bottom: 0; }
.sc-toggle-label { display: flex; align-items: center; gap: 6px; font-size: 12px;
    cursor: pointer; flex-shrink: 0; color: var(--text-muted); white-space: nowrap; }
.sc-toggle-label input { width: auto; margin: 0; }

/* Scenario picker */
.sc-picker-wrap { display: flex; gap: 6px; align-items: center; }
.sc-picker-wrap select { flex: 1; }
.sc-picker-link { font-size: 11px; color: var(--blue); white-space: nowrap; cursor: pointer; }
.sc-picker-link:hover { text-decoration: underline; }

/* ── Conditions builder ────────────────────────────────────────── */
.sc-cond-logic-row { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.sc-cond-logic-row span { font-size: 11px; color: var(--text-muted); }
.sc-cond-logic-row label { display: flex; align-items: center; gap: 4px;
    font-size: 11px; cursor: pointer; padding: 3px 8px; border: 1px solid var(--border-input);
    border-radius: 4px; background: #fff; }
.sc-cond-logic-row label:has(input:checked) { border-color: var(--blue); background: var(--blue-bg);
    color: var(--blue); font-weight: 600; }
.sc-cond-rule {
    display: flex; align-items: flex-start; gap: 6px; padding: 8px 10px;
    background: #fff; border: 1px solid var(--border); border-radius: 6px; margin-bottom: 6px;
}
.sc-cond-rule-inputs { flex: 1; min-width: 0; display: flex; gap: 6px; align-items: flex-start; flex-wrap: wrap; }
.sc-cond-rule-del { flex-shrink: 0; border: none; background: none; cursor: pointer;
    color: #9ca3af; font-size: 16px; line-height: 1; padding: 0 2px; align-self: center; }
.sc-cond-rule-del:hover { color: #ef4444; }
.sc-cond-pills { display: flex; flex-wrap: wrap; gap: 4px; }
.sc-cond-pill { display: flex; align-items: center; gap: 4px; padding: 3px 8px;
    border: 1px solid var(--border-input); border-radius: 12px; font-size: 11px;
    cursor: pointer; background: #fff; transition: background .1s, border-color .1s; }
.sc-cond-pill:hover { border-color: var(--blue-light); }
.sc-cond-pill input[type=checkbox] { width: 12px; height: 12px; margin: 0; cursor: pointer; }
.sc-cond-pill.checked { background: var(--blue-bg); border-color: var(--blue);
    font-weight: 600; color: var(--blue); }
.sc-cond-empty { font-size: 11px; color: var(--text-muted); padding: 4px 0;
    font-style: italic; }

/* ── Form actions ──────────────────────────────────────────────── */
.sc-actions { display: flex; gap: 8px; margin-top: 16px; align-items: center; }
.sc-actions .btn-danger { margin-left: auto; }

/* ── Timeline steps ────────────────────────────────────────────── */
.sc-scenario-name-row { margin-bottom: 16px; }
.sc-scenario-name-row input {
    font-size: 16px; font-weight: 600; border: none; border-bottom: 2px solid var(--border);
    border-radius: 0; padding: 4px 0; background: transparent; outline: none;
    width: 100%; box-sizing: border-box;
}
.sc-scenario-name-row input:focus { border-bottom-color: var(--blue); }

.sc-timeline { position: relative; padding-left: 32px; }
.sc-timeline::before {
    content: ''; position: absolute; left: 11px; top: 22px;
    bottom: 22px; width: 2px; background: var(--border);
}
.sc-step {
    position: relative; margin-bottom: 10px;
}
.sc-step-circle {
    position: absolute; left: -32px; top: 14px;
    width: 24px; height: 24px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700; color: #fff;
    z-index: 1; flex-shrink: 0;
}
.sc-step-circle-robot    { background: #3b82f6; }
.sc-step-circle-operator { background: #f59e0b; }
.sc-step-circle-ai       { background: #8b5cf6; }

.sc-step-card {
    background: #fff; border: 1px solid var(--border); border-radius: 8px;
    overflow: hidden;
}
.sc-step-head {
    display: flex; align-items: center; gap: 8px; padding: 10px 12px;
    background: var(--bg-header); border-bottom: 1px solid var(--border);
    cursor: default;
}
.sc-step-info { flex: 1; min-width: 0; }
.sc-step-title { font-size: 12px; font-weight: 700; }
.sc-step-meta  { font-size: 11px; color: var(--text-muted); margin-top: 1px; }
.sc-step-del { border: none; background: none; cursor: pointer; color: #9ca3af;
    font-size: 16px; line-height: 1; padding: 0 4px; flex-shrink: 0; }
.sc-step-del:hover { color: #ef4444; }
.sc-step-body { padding: 12px; }
.sc-step-grid { display: grid; grid-template-columns: 1fr 1fr 100px; gap: 8px; }
.sc-step-params { margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--border); }
.sc-step-params label { font-size: 11px; color: var(--text-muted); display: block;
    font-weight: 600; text-transform: uppercase; letter-spacing: .3px; margin-bottom: 3px; }
.sc-step-params input, .sc-step-params select, .sc-step-params textarea {
    width: 100%; padding: 6px 8px; border: 1px solid var(--border-input);
    border-radius: 5px; font-size: 12px; font-family: var(--font); box-sizing: border-box;
    background: #fff; outline: none;
}
.sc-step-params input:focus, .sc-step-params select:focus, .sc-step-params textarea:focus {
    border-color: var(--blue-light);
}
.sc-step-params-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 4px; }
.sc-msg-vars { font-size: 10px; color: var(--text-muted); margin-top: 3px; }

.sc-add-step {
    display: flex; align-items: center; gap: 6px; padding: 10px 12px;
    border: 1px dashed var(--border); border-radius: 7px; background: none;
    cursor: pointer; font-size: 12px; color: var(--text-muted);
    transition: border-color .12s; width: 100%; margin-top: 6px;
}
.sc-add-step:hover { border-color: var(--blue-light); color: var(--blue); }

/* ── Empty state ───────────────────────────────────────────────── */
.sc-empty-diagram {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    margin: 20px 0 24px;
}
.sc-empty-node {
    text-align: center; background: var(--bg-header);
    border: 1px solid var(--border); border-radius: 8px;
    padding: 10px 12px; min-width: 90px;
}
.sc-empty-node-icon { font-size: 18px; margin-bottom: 3px; }
.sc-empty-node-label { font-size: 10px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .3px; }
.sc-empty-arrow { color: var(--border); font-size: 16px; flex-shrink: 0; }

/* ── Event type select with descriptions ──────────────────────── */
.sc-event-select-wrap { position: relative; }
.sc-event-desc { font-size: 11px; color: var(--text-muted); margin-top: 4px; min-height: 15px; }
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
        <button class="btn btn-xs btn-ghost" onclick="SC.newTrigger()">+ Додати</button>
      </div>
      <?php if (empty($triggers)): ?>
        <div style="font-size:12px;color:var(--text-muted);padding:8px 4px;font-style:italic">Тригерів ще немає</div>
      <?php else:
        // Group by event_type for display
        $grouped = array();
        foreach ($triggers as $t) { $grouped[$t['event_type']][] = $t; }
        $eventBadgeMap = array(
            'order_created'        => array('created', '📦 Новий заказ'),
            'order_status_changed' => array('status',  '🔄 Зміна статусу'),
            'order_cancelled'      => array('cancelled','❌ Скасування'),
            'task_done'            => array('task',    '✅ Задача виконана'),
            'task_created'         => array('task',    '📌 Нова задача'),
            'document_created'     => array('doc',     '📄 Документ'),
        );
        foreach ($grouped as $eventType => $rows):
            $badge = isset($eventBadgeMap[$eventType]) ? $eventBadgeMap[$eventType] : array('doc', $eventType);
        ?>
        <div style="margin-bottom:4px">
          <div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.3px;padding:3px 4px 5px">
            <?php echo htmlspecialchars($badge[1]); ?>
          </div>
          <?php foreach ($rows as $t): ?>
          <div class="sc-trigger-row" data-id="<?php echo $t['id']; ?>" onclick="SC.selectTrigger(<?php echo $t['id']; ?>)">
            <span class="sc-status-dot <?php echo $t['status'] ? 'sc-status-on' : 'sc-status-off'; ?>"></span>
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
        <button class="btn btn-xs btn-ghost" onclick="SC.newScenario()">+ Додати</button>
      </div>
      <?php if (empty($scenarios)): ?>
        <div style="font-size:12px;color:var(--text-muted);padding:8px 4px;font-style:italic">Сценаріїв ще немає</div>
      <?php else: foreach ($scenarios as $s): ?>
        <div class="sc-scenario-row" data-id="<?php echo $s['id']; ?>" onclick="SC.selectScenario(<?php echo $s['id']; ?>)">
          <div style="flex:1;min-width:0">
            <div class="sc-row-name"><?php echo htmlspecialchars($s['name']); ?></div>
          </div>
          <div class="sc-row-meta">
            <?php if ($s['step_count']): ?>
            <span class="sc-step-cnt"><?php echo $s['step_count']; ?> кр.</span>
            <?php endif; ?>
            <?php if ($s['trigger_count']): ?>
            <span class="sc-trig-cnt">⚡<?php echo $s['trigger_count']; ?></span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- Queue -->
    <div class="sc-queue-wrap">
      <div class="sc-section-head" style="margin-bottom:8px">
        <span>Черга завдань</span>
        <button class="btn btn-xs btn-ghost" onclick="SC.loadQueue()">↻</button>
      </div>
      <div id="scQueueList">
        <div style="font-size:12px;color:var(--text-muted);font-style:italic">Завантаження…</div>
      </div>
    </div>

  </div>
</div>

<!-- ── Right panel ────────────────────────────────────────────── -->
<div id="scPanel">

  <!-- Empty state -->
  <div class="sc-panel" id="scEmptyPanel">
    <div style="padding: 32px 24px; text-align: center;">
      <div class="sc-empty-diagram">
        <div class="sc-empty-node">
          <div class="sc-empty-node-icon">📦</div>
          <div class="sc-empty-node-label">Подія</div>
        </div>
        <div class="sc-empty-arrow">→</div>
        <div class="sc-empty-node">
          <div class="sc-empty-node-icon">⚡</div>
          <div class="sc-empty-node-label">Тригер</div>
        </div>
        <div class="sc-empty-arrow">→</div>
        <div class="sc-empty-node">
          <div class="sc-empty-node-icon">📋</div>
          <div class="sc-empty-node-label">Сценарій</div>
        </div>
        <div class="sc-empty-arrow">→</div>
        <div class="sc-empty-node">
          <div class="sc-empty-node-icon">🤖</div>
          <div class="sc-empty-node-label">Крок</div>
        </div>
      </div>
      <div style="font-weight:700;font-size:14px;margin-bottom:8px">Автоматизація комунікацій</div>
      <div style="font-size:12px;color:var(--text-muted);max-width:340px;margin:0 auto 20px;line-height:1.7">
        <b>Сценарій</b> — ланцюжок кроків: надіслати повідомлення, створити задачу, змінити статус.<br>
        <b>Тригер</b> — задає умову запуску сценарію при настанні події.
      </div>
      <div style="font-size:11px;color:var(--text-muted);background:var(--bg-header);border-radius:8px;padding:10px 14px;display:inline-block;text-align:left;line-height:1.9">
        <b>Приклад:</b> клієнт зробив замовлення НП на суму ≥ 500 грн →<br>
        Відразу: надіслати Viber «Дякуємо за замовлення…»<br>
        Через 1 год: надіслати рахунок на оплату<br>
        Через 2 год: створити задачу «Уточнити оплату»
      </div>
      <div style="margin-top:20px;display:flex;gap:8px;justify-content:center">
        <button class="btn btn-ghost" onclick="SC.newScenario()">📋 Створити сценарій</button>
        <button class="btn btn-primary" onclick="SC.newTrigger()">⚡ Створити тригер</button>
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

    <!-- Name + active toggle -->
    <div class="sc-header-row">
      <div class="sc-field">
        <label>Назва тригера</label>
        <input type="text" id="scTriggerName" placeholder="напр. Новий НП замовлення ≥ 500 грн">
      </div>
      <label class="sc-toggle-label">
        <input type="checkbox" id="scTriggerStatus" checked> Активний
      </label>
    </div>

    <!-- Section 1: Event -->
    <div class="sc-form-section">
      <div class="sc-form-section-num">1</div>
      <div class="sc-form-section-label">Подія</div>
      <div class="sc-field" style="margin-bottom:0">
        <select id="scEventType" onchange="SC.updateEventDesc()" style="font-size:13px">
          <option value="order_created">📦 Новий заказ</option>
          <option value="order_status_changed">🔄 Зміна статусу заказу</option>
          <option value="order_cancelled">❌ Скасування заказу</option>
          <option value="task_done">✅ Виконання задачі</option>
          <option value="task_created">📌 Створення задачі</option>
          <option value="document_created">📄 Новий документ</option>
        </select>
        <div class="sc-event-desc" id="scEventDesc">Спрацьовує коли з МойСклад надходить нове замовлення</div>
      </div>
    </div>

    <!-- Section 2: Conditions -->
    <div class="sc-form-section">
      <div class="sc-form-section-num">2</div>
      <div class="sc-form-section-label">Умови запуску <span style="text-transform:none;letter-spacing:0;font-weight:400">(необов'язково — без умов спрацьовує завжди)</span></div>

      <div class="sc-cond-logic-row">
        <span>Логіка:</span>
        <label><input type="radio" name="scCondLogic" id="scCondLogicAnd" value="AND" checked> AND — всі умови</label>
        <label><input type="radio" name="scCondLogic" id="scCondLogicOr" value="OR"> OR — хоча б одна</label>
      </div>

      <div id="scCondRules">
        <div class="sc-cond-empty" id="scCondEmpty">Умов немає — тригер спрацьовує на будь-яку подію</div>
      </div>
      <button type="button" class="btn btn-ghost btn-xs" onclick="SC.addCondRule()" style="margin-top:8px">+ Додати умову</button>
    </div>

    <!-- Section 3: Scenario + delay -->
    <div class="sc-form-section">
      <div class="sc-form-section-num">3</div>
      <div class="sc-form-section-label">Сценарій</div>
      <div class="sc-picker-wrap" style="margin-bottom:10px">
        <select id="scScenarioPicker" style="font-size:13px">
          <option value="">— Оберіть сценарій —</option>
          <?php foreach ($scenarios as $s): ?>
          <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?><?php if ($s['step_count']): ?> (<?php echo $s['step_count']; ?> кр.)<?php endif; ?></option>
          <?php endforeach; ?>
        </select>
        <span class="sc-picker-link" onclick="SC.newScenario()">+ Новий</span>
      </div>
      <div class="sc-field" style="margin-bottom:0">
        <label>Додаткова затримка перед стартом (хв)</label>
        <input type="number" id="scTriggerDelay" value="0" min="0" style="width:120px">
      </div>
    </div>

    <div class="sc-actions">
      <button class="btn btn-primary" onclick="SC.saveTrigger()">Зберегти</button>
      <button class="btn btn-ghost" onclick="SC.cancel()">Скасувати</button>
      <button class="btn btn-danger btn-sm" id="scTriggerDeleteBtn" onclick="SC.deleteTrigger()" style="display:none">Видалити</button>
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

    <div class="sc-scenario-name-row">
      <input type="text" id="scScenarioName" placeholder="Назва сценарію (напр. Підтвердження НП)">
    </div>

    <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:12px">
      Кроки сценарію
    </div>
    <div class="sc-timeline" id="scStepList"></div>
    <button class="sc-add-step" onclick="SC.addStep(null)">
      <span style="font-size:16px;line-height:1">+</span> Додати крок
    </button>

    <div class="sc-actions">
      <button class="btn btn-primary" onclick="SC.saveScenario()">Зберегти</button>
      <button class="btn btn-ghost" onclick="SC.cancel()">Скасувати</button>
      <button class="btn btn-danger btn-sm" id="scScenarioDeleteBtn" onclick="SC.deleteScenario()" style="display:none">Видалити</button>
    </div>
    <div class="modal-error" id="scScenarioError" style="display:none;margin-top:8px"></div>
  </div>

</div><!-- /scPanel -->
</div><!-- /sc-layout -->
</div>

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
    {key:'order.payment_method_id',    label:'Тип оплати',              type:'multiselect', opts: SC_PAYMENT_METHODS},
    {key:'order.delivery_method_id',   label:'Тип доставки',            type:'multiselect', opts: SC_DELIVERY_METHODS},
    {key:'order.status',               label:'Статус заказу',           type:'multiselect', opts: SC_ORDER_STATUSES},
    {key:'order.sum_total',            label:'Сума замовлення',          type:'number'},
    {key:'order.wait_call',            label:'Чекає на дзвінок',        type:'boolean'},
    {key:'order.all_items_in_stock',   label:'Всі позиції є в наявності',type:'boolean'},
    {key:'order.comment_manager',      label:'Коментар менеджера',      type:'text'}
];
var SC_EVENT_DESCS = {
    'order_created':        'Спрацьовує коли з МойСклад надходить нове замовлення',
    'order_status_changed': 'Спрацьовує при кожній зміні статусу замовлення',
    'order_cancelled':      'Спрацьовує коли замовлення переходить у статус «Скасовано»',
    'task_done':            'Спрацьовує коли оператор завершує задачу (✓ Done)',
    'task_created':         'Спрацьовує при створенні нової задачі',
    'document_created':     'Спрацьовує при створенні нового документа'
};

var SC = {
    _stepIdx: 0,

    init: function() {
        this.loadQueue();
        setInterval(function(){ SC.loadQueue(); }, 30000);
    },

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

    // ── Event description ─────────────────────────────────────────────────────

    updateEventDesc: function() {
        var val = document.getElementById('scEventType').value;
        var el  = document.getElementById('scEventDesc');
        if (el) el.textContent = SC_EVENT_DESCS[val] || '';
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
        document.getElementById('scScenarioPicker').value = trigger ? (trigger.scenario_id || '') : '';
        this.updateEventDesc();
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
        var fd = new FormData(); fd.append('id', id);
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
        document.getElementById('scScenarioTitle').textContent       = isNew ? 'Новий сценарій' : scenario.name;
        document.getElementById('scScenarioId').value                = scenario ? scenario.id : 0;
        document.getElementById('scScenarioName').value              = scenario ? scenario.name : '';
        document.getElementById('scScenarioDeleteBtn').style.display = isNew ? 'none' : '';
        document.getElementById('scScenarioError').style.display     = 'none';

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
        var fd = new FormData(); fd.append('id', id);
        fetch('/sales/api/delete_scenario', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (!d.ok) { alert(d.error || 'Помилка'); return; }
                window.location.reload();
            });
    },

    // ── Steps ─────────────────────────────────────────────────────────────────

    addStep: function(data) {
        var idx  = this._stepIdx++;
        var exec = data ? (data.executor || 'robot') : 'robot';
        var act  = data ? (data.action_type || 'send_message') : 'send_message';
        var delay= data ? (data.delay_minutes || 0) : 0;
        var num  = document.querySelectorAll('.sc-step').length + 1;
        var circleClass = {robot:'sc-step-circle-robot', operator:'sc-step-circle-operator', ai:'sc-step-circle-ai'}[exec] || 'sc-step-circle-robot';

        var actionOpts = [
            ['send_message','💬 Надіслати повідомлення'],
            ['send_invoice','🧾 Надіслати рахунок'],
            ['create_task', '📌 Створити задачу оператору'],
            ['change_status','🔄 Змінити статус заказу'],
            ['create_demand','📦 Створити відвантаження'],
            ['wait',         '⏳ Очікувати']
        ];
        var execOpts = [
            ['robot','🤖 Робот — автоматично'],
            ['operator','👤 Оператор — вручну'],
            ['ai','✨ AI — асистент']
        ];

        var execHtml = execOpts.map(function(o){
            return '<option value="'+o[0]+'"'+(exec===o[0]?' selected':'')+'>'+o[1]+'</option>';
        }).join('');
        var actHtml = actionOpts.map(function(o){
            return '<option value="'+o[0]+'"'+(act===o[0]?' selected':'')+'>'+o[1]+'</option>';
        }).join('');

        var el = document.createElement('div');
        el.className = 'sc-step';
        el.dataset.stepIdx = idx;
        el.innerHTML =
            '<div class="sc-step-circle '+circleClass+'" id="scCircle'+idx+'">'+num+'</div>'
            + '<div class="sc-step-card">'
            +   '<div class="sc-step-head">'
            +     '<div class="sc-step-info">'
            +       '<div class="sc-step-title" id="scStepTitle'+idx+'">Крок '+num+'</div>'
            +       '<div class="sc-step-meta" id="scStepMeta'+idx+'"></div>'
            +     '</div>'
            +     '<button class="sc-step-del" onclick="SC.removeStep(event,'+idx+')" title="Видалити крок">×</button>'
            +   '</div>'
            +   '<div class="sc-step-body">'
            +     '<div class="sc-step-grid">'
            +       '<div class="sc-field"><label>Виконавець</label>'
            +         '<select class="sc-executor" onchange="SC.onExecChange(this)">'+execHtml+'</select></div>'
            +       '<div class="sc-field"><label>Дія</label>'
            +         '<select class="sc-action-type" onchange="SC.renderStepParams(this);SC.updateStepMeta(this)">'+actHtml+'</select></div>'
            +       '<div class="sc-field"><label>Затримка (хв)</label>'
            +         '<input type="number" class="sc-delay" value="'+delay+'" min="0" style="width:100%" onchange="SC.updateStepMeta(this)"></div>'
            +     '</div>'
            +     '<div class="sc-step-params"></div>'
            +   '</div>'
            + '</div>';

        document.getElementById('scStepList').appendChild(el);
        var aType = el.querySelector('.sc-action-type');
        this.renderStepParams(aType, data ? data.action_params : null);
        this.updateStepMeta(aType);
    },

    onExecChange: function(sel) {
        var step = sel.closest('.sc-step');
        var idx  = step.dataset.stepIdx;
        var exec = sel.value;
        var circleClass = {robot:'sc-step-circle-robot', operator:'sc-step-circle-operator', ai:'sc-step-circle-ai'}[exec] || 'sc-step-circle-robot';
        var circle = document.getElementById('scCircle' + idx);
        if (circle) {
            circle.className = 'sc-step-circle ' + circleClass;
        }
        this.updateStepMeta(sel);
    },

    removeStep: function(e, idx) {
        e.stopPropagation();
        var el = document.querySelector('.sc-step[data-step-idx="'+idx+'"]');
        if (el) el.remove();
        this.renumberSteps();
    },

    renumberSteps: function() {
        var execColors = {robot:'sc-step-circle-robot', operator:'sc-step-circle-operator', ai:'sc-step-circle-ai'};
        document.querySelectorAll('.sc-step').forEach(function(el, i) {
            var idx = el.dataset.stepIdx;
            var circle = document.getElementById('scCircle' + idx);
            if (circle) circle.textContent = i + 1;
            var title = document.getElementById('scStepTitle' + idx);
            if (title) title.textContent = 'Крок ' + (i + 1);
        });
    },

    updateStepMeta: function(el) {
        var step  = el.closest('.sc-step');
        var idx   = step.dataset.stepIdx;
        var exec  = step.querySelector('.sc-executor').value;
        var action= step.querySelector('.sc-action-type').value;
        var delay = step.querySelector('.sc-delay').value;
        var execLbl = {robot:'🤖 Робот', operator:'👤 Оператор', ai:'✨ AI'}[exec] || exec;
        var actLbl  = {send_message:'Повідомлення', send_invoice:'Рахунок',
                       create_task:'Задача оператору', change_status:'Зміна статусу',
                       create_demand:'Відвантаження', wait:'Очікування'}[action] || action;
        var meta = document.getElementById('scStepMeta' + idx);
        if (meta) {
            meta.textContent = execLbl + ' · ' + actLbl + (delay > 0 ? ' · через ' + delay + ' хв' : '');
        }
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
            var allChs = [
                {id:'telegram', icon:'✈', label:'Telegram'},
                {id:'viber',    icon:'📱', label:'Viber'},
                {id:'sms',      icon:'💬', label:'SMS'},
                {id:'email',    icon:'📧', label:'Email'}
            ];
            var enabledChs;
            if (params.priority_channels && params.priority_channels.length) {
                enabledChs = params.priority_channels;
            } else if (params.channels && params.channels.length) {
                enabledChs = params.channels;
            } else {
                enabledChs = ['telegram','viber','sms'];
            }
            html += '<label>Пріоритет каналів <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:10px;color:var(--text-muted)">(спрацює перший доступний)</span></label>'
                + '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px">';
            allChs.forEach(function(ch) {
                var checked = enabledChs.indexOf(ch.id) !== -1;
                var order   = checked ? (enabledChs.indexOf(ch.id) + 1) : '';
                var border  = checked ? 'var(--blue)' : 'var(--border-input)';
                var bg      = checked ? 'var(--blue-bg)' : '#fff';
                var clr     = checked ? 'var(--blue)' : 'inherit';
                html += '<label class="sc-ch-pill" style="display:flex;align-items:center;gap:5px;padding:5px 10px;'
                    + 'border:1px solid '+border+';border-radius:20px;font-size:12px;cursor:pointer;'
                    + 'font-weight:400;text-transform:none;letter-spacing:0;background:'+bg+';color:'+clr+';">'
                    + '<input type="checkbox" class="sc-ch-'+ch.id+'" data-channel="'+ch.id+'" style="width:auto;margin:0"'+(checked?' checked':'')+' onchange="SC.refreshChannelPills(this)">'
                    + ch.icon+' '+ch.label
                    + (order ? ' <b style="font-size:10px;margin-left:2px">'+order+'</b>' : '<b style="font-size:10px;margin-left:2px;display:none"> </b>')
                    + '</label>';
            });
            html += '</div>';
            if (action === 'send_message') {
                html += '<label>Текст повідомлення</label>'
                    + '<textarea class="sc-msg-text" rows="4" placeholder="{{counterparty.name}}, ваше замовлення №{{order.number}} на суму {{order.sum_total}} грн прийнято…">'
                    + (params.text || '') + '</textarea>'
                    + '<div class="sc-msg-vars">Змінні: {{order.number}}, {{order.sum_total}}, {{counterparty.name}}</div>';
            }
        }

        if (action === 'create_demand') {
            var dStatuses = [['new','Новий (чернетка)'],['assembling','Комплектується']];
            html += '<label>Статус відвантаження</label><select class="sc-demand-status">';
            dStatuses.forEach(function(s) { html += '<option value="'+s[0]+'"'+(params.status===s[0]?' selected':'')+'>'+s[1]+'</option>'; });
            html += '</select>'
                + '<label style="margin-top:8px;display:block">Опис</label>'
                + '<input type="text" class="sc-demand-desc" placeholder="Авто-відвантаження" value="'+(params.description||'')+'">';
        }

        if (action === 'create_task') {
            var tt  = params.task_type || 'call_back';
            var pri = params.priority  || 3;
            var taskTypes = {call_back:'Передзвонити', follow_up:'Нагадати',
                send_docs:'Надіслати документи', payment:'Контроль оплати',
                meeting:'Зустріч', other:'Інше'};
            html += '<label>Назва задачі</label>'
                + '<input type="text" class="sc-task-title" placeholder="напр. Уточнити оплату" value="' + (params.task_title || '') + '">'
                + '<div class="sc-step-params-grid">'
                + '<div><label>Тип задачі</label><select class="sc-task-type">';
            for (var k in taskTypes) { html += '<option value="'+k+'"'+(tt===k?' selected':'')+'>'+taskTypes[k]+'</option>'; }
            html += '</select></div>'
                + '<div><label>Пріоритет</label><select class="sc-task-priority">'
                + ['↓ Низький','→ Нормальний','↑ Важливий','⚡ Терміновий','🔥 Критичний'].map(function(l,i){
                    return '<option value="'+(i+1)+'"'+(pri===(i+1)?' selected':'')+'>'+l+'</option>';
                  }).join('')
                + '</select></div></div>'
                + '<label style="margin-top:8px">Дедлайн — через скільки годин</label>'
                + '<input type="number" class="sc-task-due-hours" min="0" style="width:100px" value="' + (params.due_hours || '') + '" placeholder="24">';
        }

        if (action === 'change_status') {
            var statuses = [['new','Новий'],['confirmed','Підтверджено'],['in_progress','В роботі'],
                ['waiting_payment','Очікує оплату'],['paid','Оплачено'],
                ['shipped','Відвантажено'],['completed','Виконано'],['cancelled','Скасовано']];
            html += '<label>Новий статус замовлення</label><select class="sc-new-status">';
            statuses.forEach(function(s) { html += '<option value="'+s[0]+'"'+(params.status===s[0]?' selected':'')+'>'+s[1]+'</option>'; });
            html += '</select>';
        }

        if (action === 'wait') {
            html = '<div style="font-size:12px;color:var(--text-muted);padding:4px 0">Крок-пауза — час очікування задається полем «Затримка» вище.</div>';
        }

        wrap.innerHTML = html;
    },

    collectStepParams: function(step) {
        var action = step.querySelector('.sc-action-type').value;
        var params = {};
        if (action === 'send_message' || action === 'send_invoice') {
            var chs = [];
            ['telegram','viber','sms','email'].forEach(function(ch) {
                var el = step.querySelector('.sc-ch-' + ch);
                if (el && el.checked) chs.push(ch);
            });
            params.mode = 'priority';
            params.priority_channels = chs;
            if (action === 'send_message') {
                params.text = step.querySelector('.sc-msg-text') ? step.querySelector('.sc-msg-text').value : '';
            }
        }
        if (action === 'create_demand') {
            params.status      = step.querySelector('.sc-demand-status') ? step.querySelector('.sc-demand-status').value : 'new';
            params.description = step.querySelector('.sc-demand-desc')   ? step.querySelector('.sc-demand-desc').value   : '';
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
        var emptyEl = document.getElementById('scCondEmpty');
        if (emptyEl) emptyEl.style.display = 'none';

        var container = document.getElementById('scCondRules');
        var fieldDef  = null;
        if (ruleDef && ruleDef.key) {
            for (var i = 0; i < SC_COND_FIELDS.length; i++) {
                if (SC_COND_FIELDS[i].key === ruleDef.key) { fieldDef = SC_COND_FIELDS[i]; break; }
            }
        }

        var ruleEl = document.createElement('div');
        ruleEl.className = 'sc-cond-rule';

        var fieldHtml = '<select style="flex-shrink:0;width:160px;font-size:12px;padding:5px 6px;border:1px solid var(--border-input);border-radius:5px;font-family:var(--font)" onchange="SC.onCondFieldChange(this)">'
            + '<option value="">— Оберіть поле —</option>';
        SC_COND_FIELDS.forEach(function(f) {
            fieldHtml += '<option value="'+f.key+'"'+(fieldDef && fieldDef.key===f.key?' selected':'')+'>'+f.label+'</option>';
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
                html += '<label class="sc-cond-pill'+(chk?' checked':'')+'">'
                    + '<input type="checkbox" value="'+opt.id+'"'+chk+' onchange="SC.onPillChange(this)">'
                    + opt.name+'</label>';
            });
            html += '</div>';
        } else if (fieldDef.type === 'number') {
            var op  = savedOp  || '>=';
            var val = (savedValue !== null && savedValue !== undefined) ? savedValue : '';
            html += '<select style="width:68px;font-size:12px;padding:5px;border:1px solid var(--border-input);border-radius:5px;font-family:var(--font)">'
                + ['>=','<=','>','<'].map(function(o){ return '<option value="'+o+'"'+(op===o?' selected':'')+'>'+o+'</option>'; }).join('')
                + '</select>'
                + '<input type="number" style="width:90px;font-size:12px;padding:5px 6px;border:1px solid var(--border-input);border-radius:5px;font-family:var(--font)" placeholder="500" value="'+val+'">';
        } else if (fieldDef.type === 'text') {
            var val2 = (savedValue !== null && savedValue !== undefined) ? savedValue : '';
            html += '<span style="font-size:11px;color:var(--text-muted);white-space:nowrap;padding:0 4px;align-self:center">містить</span>'
                + '<input type="text" style="font-size:12px;padding:5px 8px;border:1px solid var(--border-input);border-radius:5px;font-family:var(--font);flex:1;min-width:100px" placeholder="текст…" value="'+val2+'">';
        } else if (fieldDef.type === 'boolean') {
            var bval = (savedValue !== null && savedValue !== undefined) ? String(savedValue) : '1';
            html += '<select class="sc-cond-bool" style="font-size:12px;padding:5px 8px;border:1px solid var(--border-input);border-radius:5px;font-family:var(--font)">'
                + '<option value="1"'+(bval==='1'?' selected':'')+'>Так</option>'
                + '<option value="0"'+(bval==='0'?' selected':'')+'>Ні</option>'
                + '</select>';
        }

        wrap.innerHTML = html;
    },

    removeCondRule: function(btn) {
        btn.closest('.sc-cond-rule').remove();
        var container = document.getElementById('scCondRules');
        var emptyEl = document.getElementById('scCondEmpty');
        if (emptyEl) emptyEl.style.display = container.querySelectorAll('.sc-cond-rule').length === 0 ? '' : 'none';
    },

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
            } else if (fieldDef.type === 'boolean') {
                var bsel = ruleEl.querySelector('.sc-cond-bool');
                rules.push({key: key, op: '=', value: bsel ? bsel.value : '1'});
            }
        });
        if (!rules.length) return null;
        var logicEl = document.querySelector('input[name="scCondLogic"]:checked');
        return {logic: logicEl ? logicEl.value : 'AND', rules: rules};
    },

    loadConditions: function(condJson) {
        var container = document.getElementById('scCondRules');
        // remove all rules but keep empty placeholder
        container.querySelectorAll('.sc-cond-rule').forEach(function(r){ r.remove(); });
        document.getElementById('scCondLogicAnd').checked = true;
        var emptyEl = document.getElementById('scCondEmpty');
        if (emptyEl) emptyEl.style.display = '';

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
                if (!d.ok || !d.items || !d.items.length) {
                    el.innerHTML = '<div style="font-size:12px;color:var(--text-muted);font-style:italic;padding:4px 0">Черга порожня</div>';
                    return;
                }
                var html = '';
                d.items.forEach(function(q) {
                    var cls = {pending:'sc-queue-pending',done:'sc-queue-done',failed:'sc-queue-failed',running:'sc-queue-running'}[q.status] || '';
                    var actLbl = {send_message:'Повідомлення',send_invoice:'Рахунок',create_task:'Задача',change_status:'Статус',wait:'Пауза'}[q.action_type] || q.action_type;
                    var who = q.counterparty_name ? q.counterparty_name : '—';
                    var time = q.status === 'done' ? SC.fmtTime(q.done_at) : SC.fmtTime(q.fire_at);
                    html += '<div class="sc-queue-row">'
                        + '<span class="sc-queue-status '+cls+'">'+q.status+'</span>'
                        + '<span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+actLbl+' · '+who+'</span>'
                        + '<span style="color:var(--text-muted);white-space:nowrap;font-size:11px">'+time+'</span>'
                        + '</div>';
                });
                el.innerHTML = html;
            }).catch(function(){});
    },

    fmtTime: function(dt) {
        if (!dt) return '';
        var d = new Date(dt);
        return ('0'+d.getHours()).slice(-2)+':'+('0'+d.getMinutes()).slice(-2);
    },

    refreshChannelPills: function(cb) {
        // Перерахувати номери пріоритетів на всіх пілюлях каналів
        var wrap = cb.closest('div');
        if (!wrap) return;
        var order = 1;
        wrap.querySelectorAll('.sc-ch-pill').forEach(function(pill) {
            var input = pill.querySelector('input[type=checkbox]');
            var badge = pill.querySelector('b');
            if (input && input.checked) {
                pill.style.borderColor = 'var(--blue)';
                pill.style.background  = 'var(--blue-bg)';
                pill.style.color       = 'var(--blue)';
                if (badge) { badge.textContent = ' ' + order; badge.style.display = ''; }
                order++;
            } else {
                pill.style.borderColor = 'var(--border-input)';
                pill.style.background  = '#fff';
                pill.style.color       = 'inherit';
                if (badge) { badge.textContent = ''; badge.style.display = 'none'; }
            }
        });
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