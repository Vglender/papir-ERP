<?php
// NP StatusCode → badge class (matches NP API TrackingDocument.getStatusDocuments)
function npStateClass($stateDefine) {
    $map = array(
        1   => 'badge-gray',    // Не передана відправником
        2   => 'badge-gray',    // Видалена
        3   => 'badge-gray',    // Номер не знайдено
        4   => 'badge-blue',    // У місті відправника
        5   => 'badge-blue',    // В дорозі
        6   => 'badge-blue',    // У місті одержувача
        7   => 'badge-orange',  // Прибуло на відділення
        8   => 'badge-orange',  // Прийнято на відділенні
        9   => 'badge-green',   // Отримана
        10  => 'badge-orange',  // Повернення до відправника
        11  => 'badge-gray',    // Повернення отримано
        41  => 'badge-blue',    // Змінено адресу
        101 => 'badge-blue',    // Кур'єр
        102 => 'badge-red',     // Відмовлено від отримання
        103 => 'badge-orange',  // Зворотня доставка
        104 => 'badge-blue',    // Адресна доставка
        105 => 'badge-orange',  // Прибув у нове відділення
        106 => 'badge-red',     // Відмовлено
    );
    return isset($map[$stateDefine]) ? $map[$stateDefine] : 'badge-gray';
}

$chipSearchJs = filemtime('/var/www/papir/modules/shared/chip-search.js');
$curUrl = '/novaposhta/ttns';

function npTtnPageUrl($p, $search, $stateGroup, $dateFrom, $dateTo, $draft, $senderRef) {
    $q = array('page' => $p);
    if ($search)                $q['search']      = $search;
    if ($stateGroup && !$draft) $q['state_group'] = $stateGroup;
    if ($dateFrom)              $q['date_from']   = $dateFrom;
    if ($dateTo)                $q['date_to']     = $dateTo;
    if (!$draft)                $q['draft']       = '0';
    if ($senderRef)             $q['sender_ref']  = $senderRef;
    return '/novaposhta/ttns?' . http_build_query($q);
}

$draftOldThreshold = strtotime('-2 days');
?>
<style>
.np-toolbar { display:flex; align-items:center; gap:8px; margin-bottom:10px; }
.np-toolbar h1 { margin:0; font-size:18px; font-weight:700; flex-shrink:0; }
.np-search-wrap { flex:1; min-width:160px; }
.np-toolbar .btn { height:34px; padding:0 12px; }
.np-toolbar .chip-input { min-height:34px; max-height:34px; overflow:hidden; }
.np-ttn-num { font-family: monospace; font-size: 12px; letter-spacing: 0.5px; }
.ttn-order-empty { cursor: pointer; border-bottom: 1px dashed #d1d5db; }
.ttn-order-empty:hover { color: #2563eb; border-color: #2563eb; }
.ttn-order-input-wrap { display:inline-flex; align-items:center; gap:3px; }
.ttn-order-input-wrap input { width:72px; font-size:12px; border:1px solid #d1d5db; border-radius:3px; padding:1px 4px; }
.ttn-order-input-wrap button { font-size:11px; padding:1px 5px; cursor:pointer; border:1px solid; border-radius:3px; line-height:1.4; }
.ttn-order-save { background:#2563eb; color:#fff; border-color:#2563eb; }
.ttn-order-cancel { background:#fff; color:#6b7280; border-color:#d1d5db; }

/* Draft old rows highlight */
tr.ttn-draft-old > td { background:#fef3c7 !important; }
tr.ttn-draft-old > td:first-child { border-left:3px solid #f59e0b; }

/* Bulk actions */
.ttn-bulk-wrap { display:none; align-items:center; gap:6px; flex-shrink:0; }
.ttn-bulk-wrap.visible { display:flex; }
.ttn-bulk-count { font-size:13px; font-weight:600; color:#fff; background:#2563eb; border-radius:4px; padding:2px 10px; }
.ttn-bulk-drop-wrap { position:relative; }
.ttn-bulk-drop {
    position:absolute; right:0; top:100%; z-index:400;
    background:#fff; border:1px solid #d1d5db;
    border-radius:6px; box-shadow:0 4px 16px rgba(0,0,0,.13);
    min-width:192px; display:none; white-space:nowrap; overflow:hidden;
}
.ttn-bulk-drop.open { display:block; }
.ttn-bulk-item {
    display:block; width:100%; text-align:left;
    padding:7px 14px; background:none; border:none;
    cursor:pointer; font-size:13px; color:#111; line-height:1.4;
}
.ttn-bulk-item:hover { background:#f3f4f6; }
.ttn-bulk-item.danger { color:#dc2626; }
.ttn-bulk-item.danger:hover { background:#fef2f2; }
.ttn-bulk-sep { border:none; border-top:1px solid #e5e7eb; margin:3px 0; }

/* TTN status badges */
.ttn-badges { display:inline-flex; gap:3px; vertical-align:middle; margin-left:4px; }
.ttn-badge { display:inline-flex; align-items:center; justify-content:center;
             width:18px; height:18px; border-radius:3px; cursor:default; }
.ttn-badge svg { width:11px; height:11px; }
.ttn-badge-cod      { background:#fff7ed; color:#c2410c; }


/* TTN Detail Modal */
.ttn-modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; display:flex; align-items:center; justify-content:center; padding:20px 16px; }
.ttn-modal-overlay.hidden { display:none; }
.ttn-modal-box { background:#fff; border-radius:12px; width:100%; max-width:760px; max-height:calc(100vh - 40px); box-shadow:0 8px 48px rgba(0,0,0,.28); display:flex; flex-direction:column; overflow:hidden; }
/* Header */
.ttn-mh { padding:14px 20px 12px; border-bottom:1px solid #e5e7eb; flex-shrink:0; }
.ttn-mh-meta { font-size:11px; color:#6b7280; margin-bottom:4px; }
.ttn-mh-num { display:flex; align-items:center; gap:10px; }
.ttn-mh-num h2 { margin:0; font-size:26px; font-weight:700; font-family:monospace; letter-spacing:1px; color:#111; flex:1; }
.ttn-mh-num .badge { font-size:11px; }
.ttn-mh-close { background:none; border:none; font-size:22px; cursor:pointer; color:#6b7280; padding:2px 6px; border-radius:4px; line-height:1; margin-left:4px; }
.ttn-mh-close:hover { background:#f3f4f6; color:#111; }
/* Body: two columns */
.ttn-mb { display:grid; grid-template-columns:1fr 1fr; overflow-y:auto; flex:1; }
.ttn-mb-left { padding:16px 20px; border-right:1px solid #f0f0f0; }
.ttn-mb-right { padding:16px 20px; }
/* Timeline */
.ttn-timeline { position:relative; padding-left:20px; margin-bottom:16px; }
.ttn-timeline::before { content:''; position:absolute; left:6px; top:8px; bottom:8px; width:1.5px; background:#e5e7eb; }
.ttn-tl-item { position:relative; margin-bottom:14px; }
.ttn-tl-item:last-child { margin-bottom:0; }
.ttn-tl-dot { position:absolute; left:-20px; top:4px; width:12px; height:12px; border-radius:50%; background:#fff; border:2px solid #9ca3af; }
.ttn-tl-dot.active { border-color:#2563eb; background:#2563eb; }
.ttn-tl-date { font-size:13px; font-weight:600; color:#111; }
.ttn-tl-place { font-size:12px; color:#6b7280; margin-top:2px; }
.ttn-tl-status { margin-top:5px; }
/* Additional services */
.ttn-services { border-top:1px solid #e5e7eb; padding-top:12px; margin-top:4px; }
.ttn-services-title { font-size:11px; color:#6b7280; margin-bottom:8px; }
.ttn-service-item { display:flex; align-items:center; gap:6px; font-size:13px; font-weight:600; color:#111; }
/* Right column fields */
.ttn-rf { margin-bottom:14px; }
.ttn-rf:last-child { margin-bottom:0; }
.ttn-rf-label { font-size:11px; color:#6b7280; margin-bottom:2px; }
.ttn-rf-val { font-size:14px; color:#111; font-weight:500; }
.ttn-rf-val.large { font-size:15px; }
.ttn-rf-row { display:grid; grid-template-columns:1fr 1fr; gap:0 20px; }
/* Cost bar */
.ttn-cost-bar { border-top:1px solid #e5e7eb; padding:12px 20px; display:grid; grid-template-columns:auto 1fr 1fr auto; gap:0 24px; align-items:end; flex-shrink:0; background:#fafafa; }
.ttn-cost-bar .ttn-rf-val { font-size:16px; font-weight:700; }
/* Specs row */
.ttn-specs { border-top:1px solid #e5e7eb; padding:10px 20px; display:grid; grid-template-columns:auto auto auto auto 1fr auto; gap:0 24px; align-items:start; flex-shrink:0; }
/* Extra row */
.ttn-extra { border-top:1px solid #e5e7eb; padding:10px 20px; display:grid; grid-template-columns:1fr 2fr; gap:0 24px; flex-shrink:0; }
/* Footer */
.ttn-mf { border-top:1px solid #e5e7eb; padding:10px 20px; display:flex; align-items:center; gap:4px; flex-shrink:0; }
.ttn-mf-hide { background:none; border:none; cursor:pointer; font-size:12px; color:#6b7280; display:flex; align-items:center; gap:4px; padding:4px 8px; border-radius:4px; margin-right:auto; }
.ttn-mf-hide:hover { background:#f3f4f6; }
.ttn-act-icon { background:none; border:none; cursor:pointer; padding:6px 10px; border-radius:6px; color:#374151; display:inline-flex; align-items:center; justify-content:center; }
.ttn-act-icon:hover { background:#f3f4f6; }
.ttn-act-icon.danger:hover { background:#fef2f2; color:#dc2626; }
.ttn-act-icon svg { width:18px; height:18px; }
.ttn-act-icon[disabled] { opacity:.4; pointer-events:none; }
.ttn-edit-btn { margin-left:8px; display:flex; align-items:center; gap:6px; font-size:13px; font-weight:600; }
/* Edit mode inputs */
.ttn-ef { width:100%; font-size:13px; padding:4px 7px; border:1px solid #d1d5db; border-radius:5px; box-sizing:border-box; }
.ttn-ef:focus { border-color:#2563eb; outline:none; }
.ttn-ef-sm { width:80px; }
.ttn-ef-md { width:140px; }
.ttn-search-wrap { position:relative; }
.ttn-search-dd { position:fixed; background:#fff; border:1px solid #d1d5db; border-radius:0 0 6px 6px; max-height:220px; overflow-y:auto; z-index:2000; box-shadow:0 4px 16px rgba(0,0,0,.15); }
.ttn-search-opt { padding:7px 10px; cursor:pointer; font-size:13px; }
.ttn-search-opt:hover { background:#f3f4f6; }
/* Seats table */
.ttn-seats-section { margin-top:10px; border-top:1px dashed #e5e7eb; padding-top:10px; }
.ttn-seats-table { width:100%; border-collapse:collapse; }
.ttn-seats-table th { font-size:11px; color:#9ca3af; font-weight:500; text-align:center; padding:2px 3px; border-bottom:1px solid #e5e7eb; }
.ttn-seats-table th:first-child { text-align:left; width:18px; }
.ttn-seats-table td { padding:2px 3px; text-align:center; vertical-align:middle; }
.ttn-seats-table td:first-child { text-align:left; color:#9ca3af; font-size:11px; }
.ttn-seats-table .ttn-ef { font-size:12px; padding:2px 4px; text-align:center; }
.ttn-seats-table .ttn-ef-sm { width:56px; }
.ttn-seat-del { background:none; border:none; cursor:pointer; color:#d1d5db; padding:1px 4px; border-radius:3px; font-size:13px; line-height:1; }
.ttn-seat-del:hover { color:#ef4444; background:#fef2f2; }
.ttn-seats-footer { display:flex; align-items:center; margin-top:5px; gap:8px; }
.ttn-seats-totals { font-size:11px; color:#6b7280; margin-left:auto; }
.ttn-cod-link { display:inline-flex; align-items:center; gap:4px; font-size:11px; color:#6b7280; cursor:pointer; white-space:nowrap; }
.ttn-modal-loading { text-align:center; padding:60px; color:#6b7280; font-size:14px; }
.ttn-modal-error { color:#dc2626; padding:30px; font-size:14px; }

/* Context menu */
.ttn-actions-wrap { position:relative; }
.ttn-act-btn { background:none; border:none; cursor:pointer; padding:2px 6px; color:#6b7280; font-size:15px; line-height:1; border-radius:3px; }
.ttn-act-btn:hover { background:#f3f4f6; color:#111; }
.ttn-actions-drop {
    position:fixed; z-index:1200;
    background:#fff; border:1px solid #d1d5db;
    border-radius:6px; box-shadow:0 4px 16px rgba(0,0,0,.13);
    min-width:176px; display:none; white-space:nowrap; overflow:hidden;
}
.ttn-actions-drop.open { display:block; }
.ttn-act-item {
    display:block; width:100%; text-align:left;
    padding:7px 14px; background:none; border:none;
    cursor:pointer; font-size:13px; color:#111; line-height:1.4;
}
.ttn-act-item:hover { background:#f3f4f6; }
.ttn-act-item.danger { color:#dc2626; }
.ttn-act-item.danger:hover { background:#fef2f2; }
.ttn-act-sep { border:none; border-top:1px solid #e5e7eb; margin:3px 0; }
.ttn-act-sub-label { display:block; padding:4px 14px 2px; font-size:11px; color:#6b7280; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
</style>

<div class="page-wrap-lg">

  <!-- Toolbar -->
  <form method="get" action="<?php echo $curUrl; ?>" id="ttnFilterForm">
    <div class="np-toolbar">
      <h1>ТТН Нова Пошта</h1>
      <button type="button" class="btn btn-sm btn-ghost" id="ttnBtnSync"
              title="Завантажити нові ТТН з кабінету НП (останні 26 год.)">
        <span id="ttnSyncIcon">↻</span> З НП
      </button>
      <div class="np-search-wrap">
        <div class="chip-input" id="ttnChipBox">
          <input type="text" class="chip-typer" id="ttnChipTyper"
                 placeholder="ТТН, замовлення, одержувач, місто…" autocomplete="off">
          <div class="chip-actions">
            <button type="button" class="chip-act-btn chip-act-clear hidden" id="ttnChipClear" title="Очистити">&#x2715;</button>
            <button type="submit" class="chip-act-btn chip-act-submit" title="Пошук">
              <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="6.5" cy="6.5" r="4.5" stroke="currentColor" stroke-width="1.6"/><path d="M10 10l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            </button>
          </div>
        </div>
        <input type="hidden" name="search" id="ttnSearchHidden" value="<?php echo ViewHelper::h($search); ?>">
      </div>
      <input type="hidden" name="page" value="1">
      <?php if ($stateGroup && !$draft): ?><input type="hidden" name="state_group" value="<?php echo ViewHelper::h($stateGroup); ?>"><?php endif; ?>
      <?php if ($dateFrom):   ?><input type="hidden" name="date_from"   value="<?php echo ViewHelper::h($dateFrom); ?>"><?php endif; ?>
      <?php if ($dateTo):     ?><input type="hidden" name="date_to"     value="<?php echo ViewHelper::h($dateTo); ?>"><?php endif; ?>
      <?php if (!$draft):     ?><input type="hidden" name="draft"       value="0"><?php endif; ?>
      <?php if ($senderRef):  ?><input type="hidden" name="sender_ref"  value="<?php echo ViewHelper::h($senderRef); ?>"><?php endif; ?>
      <!-- Bulk actions (shown when rows selected) -->
      <div class="ttn-bulk-wrap" id="ttnBulkWrap">
        <span class="ttn-bulk-count" id="ttnBulkCount">0</span>
        <div class="ttn-bulk-drop-wrap">
          <button type="button" class="btn btn-sm" id="ttnBulkDropBtn">Дії ▾</button>
          <div class="ttn-bulk-drop" id="ttnBulkDrop">
            <button type="button" class="ttn-bulk-item" id="ttnBulkPrint100">🖨 Наклейка 100×100</button>
            <button type="button" class="ttn-bulk-item" id="ttnBulkPrintA4">🖨 Наклейка A4 / 6</button>
            <hr class="ttn-bulk-sep">
            <button type="button" class="ttn-bulk-item danger" id="ttnBulkDelete">Видалити вибрані</button>
          </div>
        </div>
        <button type="button" class="btn btn-sm btn-ghost" id="ttnBulkClear" title="Скинути вибір">✕</button>
      </div>
    </div>
  </form>

  <!-- Filter bar -->
  <div class="filter-bar">
    <!-- Draft checkbox -->
    <div class="filter-bar-group">
      <label class="filter-pill <?php echo $draft ? 'active' : ''; ?>" id="draftPill" style="cursor:pointer;user-select:none">
        <input type="checkbox" id="draftCb" <?php echo $draft ? 'checked' : ''; ?>
               style="margin-right:4px;vertical-align:middle"> Чернетки
      </label>
    </div>
    <div class="filter-bar-sep"></div>
    <!-- State group (only shown when draft is off) -->
    <div class="filter-bar-group" <?php echo $draft ? 'style="opacity:.35;pointer-events:none"' : ''; ?>>
      <span class="filter-bar-label">Статус</span>
      <?php
      $stateOptions = array(
          ''        => 'Усі',
          'transit' => 'В дорозі',
          'branch'  => 'На відділенні',
          'received'=> 'Отримана',
          'return'  => 'Повертається',
          'refused' => 'Відмова',
      );
      foreach ($stateOptions as $val => $lbl):
        $active = (!$draft && $stateGroup === $val) ? ' active' : '';
        $q = array('page'=>1, 'draft'=>'0');
        if ($search)     $q['search']      = $search;
        if ($val !== '') $q['state_group'] = $val;
        if ($dateFrom)   $q['date_from']   = $dateFrom;
        if ($dateTo)     $q['date_to']     = $dateTo;
        if ($senderRef)  $q['sender_ref']  = $senderRef;
      ?>
        <a href="<?php echo $curUrl . '?' . http_build_query($q); ?>"
           class="filter-pill<?php echo $active; ?>"><?php echo ViewHelper::h($lbl); ?></a>
      <?php endforeach; ?>
    </div>
    <div class="filter-bar-sep"></div>
    <div class="filter-bar-group">
      <span class="filter-bar-label">Дата</span>
      <?php
        $today     = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $todayActive     = ($dateFrom === $today     && $dateTo === $today);
        $yesterdayActive = ($dateFrom === $yesterday && $dateTo === $yesterday);
        function npDateUrl($from, $to, $search, $stateGroup, $draft, $senderRef) {
            $q = array('page'=>1, 'date_from'=>$from, 'date_to'=>$to);
            if ($search)                $q['search']      = $search;
            if ($stateGroup && !$draft) $q['state_group'] = $stateGroup;
            if (!$draft)                $q['draft']       = '0';
            if ($senderRef)             $q['sender_ref']  = $senderRef;
            return '/novaposhta/ttns?' . http_build_query($q);
        }
      ?>
      <a href="<?php echo npDateUrl($today, $today, $search, $stateGroup, $draft, $senderRef); ?>"
         class="filter-pill<?php echo $todayActive ? ' active' : ''; ?>">Сьогодні</a>
      <a href="<?php echo npDateUrl($yesterday, $yesterday, $search, $stateGroup, $draft, $senderRef); ?>"
         class="filter-pill<?php echo $yesterdayActive ? ' active' : ''; ?>">Вчора</a>
      <form method="get" action="<?php echo $curUrl; ?>" style="display:inline-flex;gap:4px;align-items:center;margin-left:4px">
        <?php if ($stateGroup && !$draft): ?><input type="hidden" name="state_group" value="<?php echo ViewHelper::h($stateGroup); ?>"><?php endif; ?>
        <?php if ($search):     ?><input type="hidden" name="search"      value="<?php echo ViewHelper::h($search); ?>"><?php endif; ?>
        <?php if (!$draft):     ?><input type="hidden" name="draft"       value="0"><?php endif; ?>
        <?php if ($senderRef):  ?><input type="hidden" name="sender_ref"  value="<?php echo ViewHelper::h($senderRef); ?>"><?php endif; ?>
        <input type="hidden" name="page" value="1">
        <input type="date" name="date_from" value="<?php echo ViewHelper::h($dateFrom); ?>"
               style="font-size:12px;border:1px solid #d1d5db;border-radius:4px;padding:3px 6px;height:28px">
        <span style="font-size:11px;color:#9ca3af">—</span>
        <input type="date" name="date_to" value="<?php echo ViewHelper::h($dateTo); ?>"
               style="font-size:12px;border:1px solid #d1d5db;border-radius:4px;padding:3px 6px;height:28px">
        <button type="submit" class="btn btn-sm" style="height:28px;padding:0 8px">OK</button>
        <?php if ($dateFrom || $dateTo): ?>
          <?php
            $resetQ = array('page'=>1);
            if ($search)                $resetQ['search']      = $search;
            if ($stateGroup && !$draft) $resetQ['state_group'] = $stateGroup;
            if (!$draft)                $resetQ['draft']       = '0';
            if ($senderRef)             $resetQ['sender_ref']  = $senderRef;
          ?>
          <a href="<?php echo $curUrl . '?' . http_build_query($resetQ); ?>"
             class="btn btn-sm btn-ghost" style="height:28px;padding:0 8px">✕</a>
        <?php endif; ?>
      </form>
    </div>
    <?php if (count($senders) > 1): ?>
    <div class="filter-bar-sep"></div>
    <div class="filter-bar-group">
      <span class="filter-bar-label">Відправник</span>
      <select id="ttnSenderSelect" style="font-size:12px;border:1px solid #d1d5db;border-radius:4px;padding:2px 6px;height:26px;color:#374151">
        <option value="">Всі</option>
        <?php foreach ($senders as $s): ?>
          <option value="<?php echo ViewHelper::h($s['Ref']); ?>"
            <?php echo ($senderRef === $s['Ref']) ? 'selected' : ''; ?>>
            <?php echo ViewHelper::h($s['Description']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <button type="button" class="filter-bar-gear" title="Налаштувати фільтри">
      <svg viewBox="0 0 16 16" fill="none"><path d="M8 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4z" stroke="currentColor" stroke-width="1.4"/><path d="M8 1v1.5M8 13.5V15M1 8h1.5M13.5 8H15M3.05 3.05l1.06 1.06M11.89 11.89l1.06 1.06M3.05 12.95l1.06-1.06M11.89 4.11l1.06-1.06" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
    </button>
  </div>

  <!-- Table -->
  <table class="crm-table">
    <thead>
      <tr>
        <th style="width:28px;padding:6px 8px"><input type="checkbox" id="ttnCheckAll" title="Вибрати всі"></th>
        <th>ТТН</th>
        <th>Замовлення</th>
        <th>Одержувач</th>
        <th>Місто / Відділення</th>
        <th>Статус</th>
        <th title="Орієнтовна дата доставки">Доставка</th>
        <th>Накл.</th>
        <th>Кг</th>
        <th>Відправник</th>
        <th>Дата</th>
        <th style="width:32px"></th>
      </tr>
    </thead>
    <tbody id="ttnTableBody">
    <?php if (empty($rows)): ?>
      <tr><td colspan="12" style="text-align:center;color:#9ca3af;padding:32px">ТТН не знайдено</td></tr>
    <?php else: ?>
      <?php foreach ($rows as $row):
        $isDraftOld = $draft && $row['moment'] && strtotime($row['moment']) < $draftOldThreshold;
      ?>
        <tr<?php if ($isDraftOld): ?> class="ttn-draft-old"<?php endif; ?>
            data-ttn-id="<?php echo (int)$row['id']; ?>"
            data-ttn-ref="<?php echo ViewHelper::h($row['ref']); ?>"
            data-sender-ref="<?php echo ViewHelper::h($row['sender_ref']); ?>">
          <td style="padding:6px 8px">
            <input type="checkbox" class="ttn-row-check" value="<?php echo (int)$row['id']; ?>">
          </td>
          <td class="np-ttn-num">
            <?php if ($row['int_doc_number']): ?>
              <a href="#" class="ttn-num-link" data-ttn-id="<?php echo (int)$row['id']; ?>"
                 style="text-decoration:none;color:inherit">
                <?php echo ViewHelper::h($row['int_doc_number']); ?>
              </a>
              <?php if (!empty($row['backward_delivery_money']) && $row['backward_delivery_money'] > 0): ?>
                <span class="ttn-badges"><span class="ttn-badge ttn-badge-cod" title="Зворотня доставка <?php echo number_format((float)$row['backward_delivery_money'], 0, '.', ' '); ?> грн"><svg viewBox="0 0 16 16" fill="none"><path d="M13 5H6a3 3 0 0 0 0 6h7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M11 3l2 2-2 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span></span>
              <?php endif; ?>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td class="ttn-order-cell" data-ttn-id="<?php echo (int)$row['id']; ?>">
            <?php if ($row['customerorder_id']): ?>
              <a href="/customerorder/edit?id=<?php echo (int)$row['customerorder_id']; ?>" target="_blank" class="fs-12 ttn-order-link">
                #<?php echo (int)$row['customerorder_id']; ?>
              </a>
            <?php else: ?>
              <span class="text-muted ttn-order-empty" title="Натисніть щоб прив'язати замовлення">—</span>
            <?php endif; ?>
          </td>
          <td>
            <div><?php echo ViewHelper::h($row['recipient_contact_person'] ?: '—'); ?></div>
            <?php if ($row['recipients_phone']): ?>
              <div class="fs-12 text-muted"><?php echo ViewHelper::h($row['recipients_phone']); ?></div>
            <?php endif; ?>
          </td>
          <td>
            <div class="fs-12"><?php echo ViewHelper::h($row['city_recipient_desc'] ?: '—'); ?></div>
            <?php if ($row['recipient_address_desc']): ?>
              <div class="fs-12 text-muted truncate" style="max-width:180px">
                <?php echo ViewHelper::h($row['recipient_address_desc']); ?>
              </div>
            <?php endif; ?>
          </td>
          <td>
            <?php $cls = npStateClass((int)$row['state_define']); ?>
            <span class="badge <?php echo $cls; ?>">
              <?php echo ViewHelper::h($row['state_name'] ?: '—'); ?>
            </span>
          </td>
          <td class="fs-12 text-muted">
            <?php echo $row['estimated_delivery_date'] ? date('d.m', strtotime($row['estimated_delivery_date'])) : '—'; ?>
          </td>
          <td class="nowrap">
            <?php echo $row['backward_delivery_money'] > 0
                ? '<span class="fw-600">₴' . number_format((float)$row['backward_delivery_money'], 0, '.', ' ') . '</span>'
                : '<span class="text-muted">—</span>'; ?>
          </td>
          <td class="fs-12"><?php echo $row['weight'] ? (float)$row['weight'] : '—'; ?></td>
          <td class="fs-12 text-muted"><?php echo ViewHelper::h($row['sender_desc'] ?: '—'); ?></td>
          <td class="fs-12 text-muted nowrap">
            <?php
              if ($row['moment']) {
                  $mTs = strtotime($row['moment']);
                  echo date('d.m.Y', $mTs);
                  if ($isDraftOld) {
                      $days = floor((time() - $mTs) / 86400);
                      echo ' <span style="color:#b45309;font-size:11px">(' . $days . 'д)</span>';
                  }
              } else {
                  echo '—';
              }
            ?>
          </td>
          <td style="padding:4px 6px">
            <div class="ttn-actions-wrap">
              <button type="button" class="ttn-act-btn" title="Дії">&#8942;</button>
              <div class="ttn-actions-drop">
                <span class="ttn-act-sub-label">Наклейка</span>
                <button type="button" class="ttn-act-item ttn-act-print"
                        data-format="100x100">🖨 Термо 100×100</button>
                <button type="button" class="ttn-act-item ttn-act-print"
                        data-format="a4_6">🖨 A4 / 6 на аркуші</button>
                <hr class="ttn-act-sep">
                <button type="button" class="ttn-act-item danger ttn-act-delete">Видалити ТТН</button>
              </div>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>

  <!-- Pagination -->
  <div id="ttnPagination">
  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="<?php echo npTtnPageUrl($page-1, $search, $stateGroup, $dateFrom, $dateTo, $draft, $senderRef); ?>" data-page="<?php echo $page-1; ?>">&laquo;</a>
    <?php endif; ?>
    <?php for ($p = max(1, $page-3); $p <= min($totalPages, $page+3); $p++): ?>
      <?php if ($p === $page): ?>
        <span class="cur"><?php echo $p; ?></span>
      <?php else: ?>
        <a href="<?php echo npTtnPageUrl($p, $search, $stateGroup, $dateFrom, $dateTo, $draft, $senderRef); ?>" data-page="<?php echo $p; ?>"><?php echo $p; ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
      <a href="<?php echo npTtnPageUrl($page+1, $search, $stateGroup, $dateFrom, $dateTo, $draft, $senderRef); ?>" data-page="<?php echo $page+1; ?>">&raquo;</a>
    <?php endif; ?>
    <span class="dots"><?php echo number_format($total, 0, '.', ' '); ?> ТТН</span>
  </div>
  <?php endif; ?>
  </div>

</div>

<!-- TTN Detail Modal -->
<div class="ttn-modal-overlay hidden" id="ttnModal">
  <div class="ttn-modal-box" id="ttnModalBox">
    <div class="ttn-modal-loading">Завантаження…</div>
  </div>
</div>
</div>


<script src="/modules/shared/chip-search.js?v=<?php echo $chipSearchJs; ?>"></script>
<script src="/modules/shared/chat-modal.js?v=<?php echo filemtime('/var/www/papir/modules/shared/chat-modal.js'); ?>"></script>
<script src="/modules/shared/send-templates.js?v=<?php echo filemtime('/var/www/papir/modules/shared/send-templates.js'); ?>"></script>
<script>
// ── Live search ──────────────────────────────────────────────────────────────
(function () {
    var clearBtn = document.getElementById('ttnChipClear');
    var chipBox  = document.getElementById('ttnChipBox');
    var typer    = document.getElementById('ttnChipTyper');
    var hidden   = document.getElementById('ttnSearchHidden');
    var form     = document.getElementById('ttnFilterForm');
    var tbody    = document.getElementById('ttnTableBody');
    var pagDiv   = document.getElementById('ttnPagination');
    if (!tbody || !pagDiv) return;

    var _debTimer = null;

    // Combines committed chips (hidden.value) + typer text into one search string
    function getSearchValue() {
        var chips = hidden ? hidden.value.trim() : '';
        var text  = typer  ? typer.value.trim()  : '';
        if (chips && text) return chips + '|||' + text;
        return chips || text;
    }

    function buildApiParams(page) {
        var url = new URL(window.location.href);
        url.searchParams.set('page', page || 1);
        var sv = getSearchValue();
        if (sv) { url.searchParams.set('search', sv); }
        else    { url.searchParams.delete('search'); }
        return url.searchParams.toString();
    }

    function updateUrl(page) {
        var url = new URL(window.location.href);
        url.searchParams.set('page', page || 1);
        var chips = hidden ? hidden.value.trim() : '';
        if (chips) { url.searchParams.set('search', chips); }
        else       { url.searchParams.delete('search'); }
        history.pushState(null, '', url.toString());
    }

    function loadTable(page) {
        var params = buildApiParams(page || 1);
        tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;padding:32px;color:#9ca3af">Завантаження…</td></tr>';
        fetch('/novaposhta/api/get_ttn_table?' + params)
            .then(function(r){ return r.json(); })
            .then(function(res) {
                if (!res.ok) return;
                tbody.innerHTML = res.rows_html;
                pagDiv.innerHTML = res.pagination_html
                    ? '<div class="pagination">' + res.pagination_html + '</div>'
                    : '';
                updateUrl(page || 1);
            });
    }

    // Override form.submit — ChipSearch вызывает его при Enter в пустом typer
    if (form) form.submit = function() { loadTable(1); };
    // ChipSearch ПЕРВЫМ добавляет submit-listener для flush typer→hidden
    ChipSearch.init('ttnChipBox', 'ttnChipTyper', 'ttnSearchHidden', form, {noComma: true});
    // Наш listener ПОСЛЕ ChipSearch — e.preventDefault() + loadTable
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            loadTable(1);
        });
    }

    // Chip add/remove → immediate search (hidden.value already updated by ChipSearch)
    new MutationObserver(function() {
        clearTimeout(_debTimer);
        loadTable(1);
    }).observe(chipBox, { childList: true });

    // Typer input → debounce 300ms (search includes uncommitted text)
    // Не запускаємо пошук якщо введено 1-2 символи — надто коротко для будь-якого поля
    // (14 цифр = ТТН, 9-13 = телефон, 3+ = ім'я/місто)
    typer.addEventListener('input', function() {
        clearTimeout(_debTimer);
        var val = getSearchValue();
        if (val.length > 0 && val.length < 3) return; // чекаємо ще символів
        _debTimer = setTimeout(function() { loadTable(1); }, 300);
    });

    // Clear button
    function updateClear() {
        var has = chipBox.querySelectorAll('.chip').length > 0 || typer.value.trim() !== '';
        clearBtn.classList.toggle('hidden', !has);
    }
    new MutationObserver(updateClear).observe(chipBox, { childList: true });
    typer.addEventListener('input', updateClear);

    clearBtn.addEventListener('click', function () {
        chipBox.querySelectorAll('.chip').forEach(function(c){ c.remove(); });
        typer.value  = '';
        hidden.value = '';
        clearBtn.classList.add('hidden');
        loadTable(1);
    });
    updateClear();

    // Pagination click delegation
    pagDiv.addEventListener('click', function(e) {
        var a = e.target.closest('a[data-page]');
        if (!a) return;
        e.preventDefault();
        loadTable(parseInt(a.dataset.page, 10));
    });

    // Expose for external callers (delete/track refresh)
    window._ttnLoadTable = loadTable;
}());

// ── Sender select ────────────────────────────────────────────────────────
(function () {
    var sel = document.getElementById('ttnSenderSelect');
    if (!sel) return;
    sel.addEventListener('change', function () {
        var url = new URL(window.location.href);
        if (sel.value) {
            url.searchParams.set('sender_ref', sel.value);
        } else {
            url.searchParams.delete('sender_ref');
        }
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    });
}());

// ── Draft checkbox ───────────────────────────────────────────────────────
(function () {
    var cb = document.getElementById('draftCb');
    if (!cb) return;
    cb.addEventListener('change', function () {
        var url = new URL(window.location.href);
        if (cb.checked) {
            url.searchParams.delete('draft');
            url.searchParams.delete('state_group');
        } else {
            url.searchParams.set('draft', '0');
        }
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    });
}());

// ── Sync button ──────────────────────────────────────────────────────────
(function () {
    var btn  = document.getElementById('ttnBtnSync');
    var icon = document.getElementById('ttnSyncIcon');
    if (!btn) return;

    btn.addEventListener('click', function () {
        btn.disabled = true;
        icon.textContent = '…';

        fetch('/novaposhta/api/sync_ttns_quick', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'hours=26'
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            btn.disabled = false;
            icon.textContent = '↻';
            if (!res.ok) {
                showToast('Помилка: ' + (res.error || ''), true);
                return;
            }
            var msg = 'Синхронізовано: +' + res.inserted + ' нових, ' + res.updated + ' оновлено';
            if (res.errors && res.errors.length) msg += ' ⚠ ' + res.errors.join('; ');
            showToast(msg);
            if (res.inserted > 0) setTimeout(function () {
                if (window._ttnLoadTable) { window._ttnLoadTable(1); } else { window.location.reload(); }
            }, 800);
        })
        .catch(function () {
            btn.disabled = false;
            icon.textContent = '↻';
            showToast('Мережева помилка', true);
        });
    });
}());

// ── Inline order linking ─────────────────────────────────────────────────
(function () {
    function openOrderInput(cell) {
        var ttnId = cell.dataset.ttnId;
        cell.innerHTML = '<div class="ttn-order-input-wrap">' +
            '<input type="text" placeholder="#номер" id="oi-' + ttnId + '">' +
            '<button class="ttn-order-save" data-ttn="' + ttnId + '">✓</button>' +
            '<button class="ttn-order-cancel" data-ttn="' + ttnId + '">✕</button>' +
            '</div>';
        var inp = cell.querySelector('input');
        inp.focus();
        inp.addEventListener('keydown', function (e) {
            if (e.key === 'Enter')  cell.querySelector('.ttn-order-save').click();
            if (e.key === 'Escape') cell.querySelector('.ttn-order-cancel').click();
        });
        cell.querySelector('.ttn-order-cancel').addEventListener('click', function () {
            cell.innerHTML = '<span class="text-muted ttn-order-empty" title="Натисніть щоб прив\'язати замовлення">—</span>';
            bindEmpty(cell);
        });
        cell.querySelector('.ttn-order-save').addEventListener('click', function () {
            var raw = inp.value.trim().replace(/^#/, '');
            var orderId = parseInt(raw, 10);
            if (!orderId) { inp.style.borderColor = '#dc2626'; inp.focus(); return; }
            saveOrderLink(cell, ttnId, orderId);
        });
    }

    function saveOrderLink(cell, ttnId, orderId) {
        cell.innerHTML = '<span class="text-muted fs-12">…</span>';
        fetch('/novaposhta/api/set_ttn_order', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'ttn_id=' + ttnId + '&order_id=' + orderId
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (!res.ok) {
                showToast((res.error || 'Помилка'), true);
                cell.innerHTML = '<span class="text-muted ttn-order-empty" title="Натисніть щоб прив\'язати замовлення">—</span>';
                bindEmpty(cell);
                return;
            }
            cell.innerHTML = '<a href="/customerorder/edit?id=' + orderId + '" target="_blank" class="fs-12 ttn-order-link">#' + orderId + '</a>';
            bindLink(cell);
            showToast('Прив\'язано до замовлення #' + orderId);
        }).catch(function () {
            showToast('Мережева помилка', true);
            cell.innerHTML = '<span class="text-muted ttn-order-empty" title="Натисніть щоб прив\'язати замовлення">—</span>';
            bindEmpty(cell);
        });
    }

    // Event delegation — works for dynamically injected rows
    document.addEventListener('click', function(e) {
        var span = e.target.closest('.ttn-order-empty');
        if (span) { openOrderInput(span.closest('.ttn-order-cell')); }
    });
    document.addEventListener('dblclick', function(e) {
        var link = e.target.closest('.ttn-order-link');
        if (link) { e.preventDefault(); openOrderInput(link.closest('.ttn-order-cell')); }
    });
}());

// ── Checkboxes + bulk actions ────────────────────────────────────────────
(function () {
    var checkAll    = document.getElementById('ttnCheckAll');
    var bulkWrap    = document.getElementById('ttnBulkWrap');
    var bulkCount   = document.getElementById('ttnBulkCount');
    var bulkDropBtn = document.getElementById('ttnBulkDropBtn');
    var bulkDrop    = document.getElementById('ttnBulkDrop');
    var bulkClear   = document.getElementById('ttnBulkClear');

    function getChecked() {
        return Array.prototype.slice.call(document.querySelectorAll('.ttn-row-check:checked'));
    }

    function updateBulkBar() {
        var checked = getChecked();
        var n = checked.length;
        if (n > 0) {
            bulkCount.textContent = n + ' вибрано';
            bulkWrap.classList.add('visible');
        } else {
            bulkWrap.classList.remove('visible');
            bulkDrop.classList.remove('open');
        }
        // sync "select all" state
        var all = document.querySelectorAll('.ttn-row-check');
        checkAll.indeterminate = (n > 0 && n < all.length);
        checkAll.checked = (n > 0 && n === all.length);
    }

    // Event delegation for row checkboxes — works after AJAX tbody reload
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('ttn-row-check')) updateBulkBar();
    });

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            document.querySelectorAll('.ttn-row-check').forEach(function (cb) {
                cb.checked = checkAll.checked;
            });
            updateBulkBar();
        });
    }

    if (bulkClear) {
        bulkClear.addEventListener('click', function () {
            document.querySelectorAll('.ttn-row-check').forEach(function (cb) { cb.checked = false; });
            if (checkAll) { checkAll.checked = false; checkAll.indeterminate = false; }
            updateBulkBar();
        });
    }

    // ── Dropdown toggle ──
    if (bulkDropBtn) {
        bulkDropBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            bulkDrop.classList.toggle('open');
        });
    }
    document.addEventListener('click', function (e) {
        if (bulkDrop && !e.target.closest('.ttn-bulk-drop-wrap')) {
            bulkDrop.classList.remove('open');
        }
    });

    // ── Bulk print ──
    function bulkPrint(format) {
        bulkDrop.classList.remove('open');
        var checked = getChecked();
        if (!checked.length) return;
        var ids = checked.map(function (cb) { return cb.value; }).join(',');

        fetch('/novaposhta/api/bulk_print_ttns', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'ids=' + encodeURIComponent(ids) + '&format=' + encodeURIComponent(format)
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res.ok) { showToast('Помилка: ' + (res.error || ''), true); return; }
            res.urls.forEach(function (url) { window.open(url, '_blank'); });
            if (res.skipped && res.skipped.length) {
                showToast('⚠ ' + res.skipped.length + ' ТТН без номера ЕН — пропущено', true);
            }
        })
        .catch(function () { showToast('Мережева помилка', true); });
    }

    var btn100 = document.getElementById('ttnBulkPrint100');
    var btnA4  = document.getElementById('ttnBulkPrintA4');
    if (btn100) btn100.addEventListener('click', function () { bulkPrint('100x100'); });
    if (btnA4)  btnA4.addEventListener('click',  function () { bulkPrint('a4_6'); });

    // ── Bulk delete ──
    var btnDel = document.getElementById('ttnBulkDelete');
    if (btnDel) {
        btnDel.addEventListener('click', function () {
            bulkDrop.classList.remove('open');
            var checked = getChecked();
            if (!checked.length) return;
            var n = checked.length;
            if (!confirm('Видалити ' + n + ' ТТН? Вони будуть видалені в НП (якщо API дозволяє).')) return;

            btnDel.disabled = true;
            var ids = checked.map(function (cb) { return cb.value; }).join(',');

            fetch('/novaposhta/api/bulk_delete_ttns', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'ids=' + encodeURIComponent(ids)
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                btnDel.disabled = false;
                // Remove deleted rows
                if (res.deleted && res.deleted.length) {
                    res.deleted.forEach(function (id) {
                        var cb = document.querySelector('.ttn-row-check[value="' + id + '"]');
                        if (cb) { var tr = cb.closest('tr'); if (tr) tr.remove(); }
                    });
                }
                if (res.errors && res.errors.length) {
                    var msg = 'Видалено: ' + (res.deleted ? res.deleted.length : 0) + '\n\nПомилки:\n';
                    res.errors.forEach(function (e) { msg += '• ТТН #' + e.id + ': ' + e.error + '\n'; });
                    alert(msg);
                } else {
                    showToast('Видалено ' + (res.deleted ? res.deleted.length : 0) + ' ТТН');
                }
                // Якщо рядків у таблиці більше нема — оновити через AJAX
                var remaining = document.querySelectorAll('tbody .ttn-row-check').length;
                if (remaining === 0 && window._ttnLoadTable) {
                    window._ttnLoadTable(1);
                    return;
                }
                document.querySelectorAll('.ttn-row-check').forEach(function (cb) { cb.checked = false; });
                if (checkAll) { checkAll.checked = false; checkAll.indeterminate = false; }
                updateBulkBar();
            })
            .catch(function () {
                btnDel.disabled = false;
                alert('Мережева помилка');
            });
        });
    }
}());

// ── Context menu (actions ⋮) ─────────────────────────────────────────────
(function () {
    var openDrop = null;

    function closeAll() {
        if (openDrop) { openDrop.classList.remove('open'); openDrop = null; }
    }

    document.addEventListener('click', function (e) {
        // Toggle button
        var actBtn = e.target.closest('.ttn-act-btn');
        if (actBtn) {
            e.stopPropagation();
            var wrap = actBtn.closest('.ttn-actions-wrap');
            var drop = wrap ? wrap.querySelector('.ttn-actions-drop') : null;
            if (!drop) return;
            if (drop === openDrop) { closeAll(); return; }
            closeAll();
            var rect = actBtn.getBoundingClientRect();
            drop.style.top  = (rect.bottom + 2) + 'px';
            drop.style.left = '';
            drop.style.right = '';
            drop.classList.add('open');
            // Перевіряємо чи не виходить за правий край
            var dropRect = drop.getBoundingClientRect();
            if (dropRect.right > window.innerWidth - 4) {
                drop.style.left = (rect.right - dropRect.width) + 'px';
            } else {
                drop.style.left = rect.left + 'px';
            }
            openDrop = drop;
            return;
        }
        // Click outside
        if (!e.target.closest('.ttn-actions-drop')) closeAll();

        // ── Delete ──
        var delBtn = e.target.closest('.ttn-act-delete');
        if (delBtn) {
            e.stopPropagation();
            closeAll();
            var tr    = delBtn.closest('tr');
            var ttnId = tr ? tr.dataset.ttnId : null;
            if (!ttnId) return;
            if (!confirm('Видалити ТТН? Вона буде видалена в НП (якщо API дозволяє) і у нас.')) return;

            delBtn.disabled = true;
            fetch('/novaposhta/api/delete_ttn', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'ttn_id=' + ttnId
            })
            .then(function(r){ return r.json(); })
            .then(function(res) {
                delBtn.disabled = false;
                if (!res.ok) { alert('Не вдалося видалити:\n' + (res.error || 'Невідома помилка')); return; }
                showToast('ТТН видалено');
                if (tr) tr.remove();
            })
            .catch(function() { delBtn.disabled = false; alert('Мережева помилка при видаленні'); });
            return;
        }

        // ── Print sticker ──
        var printBtn = e.target.closest('.ttn-act-print');
        if (printBtn) {
            e.stopPropagation();
            closeAll();
            var tr2   = printBtn.closest('tr');
            var ttnId2 = tr2 ? tr2.dataset.ttnId : null;
            var fmt   = printBtn.dataset.format;
            if (!ttnId2) return;
            window.open('/novaposhta/api/print_ttn_sticker?ttn_id=' + ttnId2 + '&format=' + encodeURIComponent(fmt), '_blank');
            return;
        }
    });
}());
</script>

<script>
// ── TTN Detail Modal ─────────────────────────────────────────────────────
(function () {
    var overlay = document.getElementById('ttnModal');
    var box     = document.getElementById('ttnModalBox');
    var _ttn    = null;
    var _senders = [];
    var _addresses = [];
    var _openSheet = null;
    var _editing   = false;
    var _cityTimer = null;
    var _whTimer   = null;
    var _seatsMode = 'simple'; // 'simple' | 'detailed'

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

    // ── Open / Close ──────────────────────────────────────────────────────
    function openModal(ttnId) {
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
                // Auto-switch to detailed mode if seats data exists
                var hasSeatData = false;
                try { hasSeatData = JSON.parse(res.ttn.options_seat || '[]').length > 0; } catch(e) {}
                _seatsMode  = hasSeatData ? 'detailed' : 'simple';
                render();
            })
            .catch(function(){ box.innerHTML = '<div class="ttn-modal-error">Мережева помилка</div>'; });
    }
    function closeModal() {
        overlay.classList.add('hidden');
        document.body.style.overflow = '';
        _ttn = null; _editing = false; _seatsMode = 'simple';
    }
    overlay.addEventListener('click', function(e){ if(e.target===overlay) closeModal(); });
    document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeModal(); });
    document.addEventListener('click', function(e){
        var link = e.target.closest('.ttn-num-link');
        if (!link) return;
        e.preventDefault();
        openModal(link.dataset.ttnId);
    });

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

        // Additional services (view mode only — in edit mode COD is in cost bar)
        if (!_editing && t.backward_delivery_money > 0) {
            html += '<div class="ttn-services">';
            html += '<div class="ttn-services-title">Додаткові послуги</div>';
            html += '<div class="ttn-service-item">';
            html += '<svg viewBox="0 0 16 16" fill="none" style="width:16px;height:16px;flex-shrink:0"><path d="M13 5H6a3 3 0 0 0 0 6h7" stroke="#c2410c" stroke-width="1.5" stroke-linecap="round"/><path d="M11 3l2 2-2 2" stroke="#c2410c" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            html += 'Контроль оплати: <span style="color:#c2410c">' + h(parseFloat(t.backward_delivery_money).toLocaleString('uk')) + ' грн</span>';
            html += '</div></div>';
        }
        // Seats section lives in left column (edit mode)
        if (_editing) {
            html += renderSeatsSection(t);
        }
        html += '</div>'; // left

        // Right column
        html += '<div class="ttn-mb-right">';

        if (_editing) {
            html += renderEditRight(t);
        } else {
            // Description + declared value
            html += '<div class="ttn-rf-row" style="margin-bottom:14px">';
            html += '<div class="ttn-rf"><div class="ttn-rf-label">Опис відправлення</div><div class="ttn-rf-val">' + h(dash(t.description)) + '</div></div>';
            html += '<div class="ttn-rf"><div class="ttn-rf-label">Оголошена цінність</div><div class="ttn-rf-val">' + (t.declared_value ? h(t.declared_value) + ' грн' : '—') + '</div></div>';
            html += '</div>';
            // Recipient
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

        // Extra: description + additional info + declared value
        html += '<div class="ttn-extra">';
        if (_editing) {
            // Опис відправлення (Description)
            html += '<div class="ttn-rf"><div class="ttn-rf-label">Опис відправлення</div><div class="ttn-rf-val">';
            html += '<input type="text" class="ttn-ef" id="ef_desc" value="' + h(t.description||'') + '" maxlength="200" placeholder="Товар">';
            html += '</div></div>';
            // Оголошена вартість
            html += '<div class="ttn-rf"><div class="ttn-rf-label">Оголошена вартість, грн</div><div class="ttn-rf-val">';
            html += '<input type="number" class="ttn-ef ttn-ef-md" id="ef_declared" min="1" step="1" value="' + h(parseInt(t.declared_value)||1) + '">';
            html += '<div id="ef_declared_hint" style="font-size:11px;color:#6b7280;margin-top:2px"></div>';
            html += '</div></div>';
            // Додаткова інформація (AdditionalInformation)
            html += '<div class="ttn-rf" style="grid-column:1/-1"><div class="ttn-rf-label">Додаткова інформація про відправлення</div><div class="ttn-rf-val">';
            html += '<input type="text" class="ttn-ef" id="ef_add_info" value="' + h(t.additional_information||'') + '" maxlength="500" placeholder="Інформація про позиції замовлення…">';
            html += '</div></div>';
        } else {
            // View mode
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

        // Icon: delete
        html += iconBtn('ttnActDelete', 'danger', 'Видалити', '<path d="M3 5h10M8 5V3M6 5v9M10 5v9M4 5l.5 9h7l.5-9" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>');
        // Icon: add to registry
        // Show if no scan_sheet_ref, OR if scan_sheet_ref exists but registry is not in our DB (orphaned) or is closed
        if (t.int_doc_number && (!t.scan_sheet_ref || t.scan_sheet_status !== 'open')) {
            html += iconBtn('ttnActSheet', '', _openSheet ? 'Додати до реєстру' : 'Новий реєстр', '<rect x="2" y="2" width="12" height="12" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M5 6h6M5 8.5h6M5 11h4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>');
        }
        // Icon: duplicate
        if (t.can_duplicate) {
            html += iconBtn('ttnActDup', '', 'Дублювати', '<rect x="5" y="4" width="8" height="10" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M3 12V3a1 1 0 0 1 1-1h7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>');
        }
        // Icon: print
        if (t.int_doc_number) {
            html += iconBtn('ttnActP100', '', 'Друк 100×100', '<rect x="3" y="1" width="10" height="6" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="3" y="9" width="10" height="6" rx="1" stroke="currentColor" stroke-width="1.4"/><path d="M1 7h14v4H1z" fill="none" stroke="currentColor" stroke-width="1.4"/><circle cx="12" cy="9.5" r=".8" fill="currentColor"/>');
            html += iconBtn('ttnActPA4',  '', 'Друк A4/6',   '<rect x="3" y="1" width="10" height="6" rx="1" stroke="currentColor" stroke-width="1.4"/><rect x="3" y="9" width="10" height="6" rx="1" stroke="currentColor" stroke-width="1.4"/><path d="M1 7h14v4H1z" fill="none" stroke="currentColor" stroke-width="1.4"/><circle cx="12" cy="9.5" r=".8" fill="currentColor"/>');
        }

        // Chat + Send buttons (if counterparty is known)
        if (t.counterparty_id) {
            html += '<span style="margin-left:auto;display:inline-flex;gap:4px">';
            html += '<button type="button" class="btn btn-ghost btn-sm js-ttn-send"'
                  + ' data-cp="'     + h(String(t.counterparty_id))        + '"'
                  + ' data-ttn="'    + h(String(t.int_doc_number || ''))   + '"'
                  + ' data-status="' + h(String(t.state_name     || ''))   + '">'
                  + '&#128228; &#1053;&#1072;&#1076;&#1110;&#1089;&#1083;&#1072;&#1090;&#1080; &#9662;</button>';
            html += '<button type="button" class="btn btn-ghost btn-sm"'
                  + ' onclick="ChatModal.open(' + h(String(t.counterparty_id)) + ')">'
                  + '&#128172; &#1063;&#1072;&#1090;</button>';
            html += '</span>';
        }

        // Edit / Save button
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
        // Note: bindSeatsTable() is called inside bindEvents() when _editing=true
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

    // ── Seats section (left column, edit mode) ────────────────────────────
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
            // Detailed table
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
            // Simple: one row
            var seatsCount = seats.length;
            html += '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">';
            html += '<div><div class="ttn-rf-label">Вага, кг</div><input type="number" class="ttn-ef ttn-ef-sm" id="ef_weight" step="0.01" min="0.1" value="' + h(totalW || parseFloat(t.weight) || 0.5) + '"></div>';
            html += '<div><div class="ttn-rf-label">Місць</div><input type="number" class="ttn-ef ttn-ef-sm" id="ef_seats" min="1" value="' + h(seatsCount) + '"></div>';
            html += '</div>';
        }
        html += '</div>'; // seats-section
        return html;
    }

    function renderEditRight(t) {
        var html = '';
        // Прізвище + Ім'я в одну строку
        html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:6px">';
        html += '<div><div class="ttn-rf-label">Прізвище</div><input type="text" class="ttn-ef" id="ef_last" value="' + h(getNamePart(t.recipient_contact_person,0)) + '"></div>';
        html += '<div><div class="ttn-rf-label">Ім\'я</div><input type="text" class="ttn-ef" id="ef_first" value="' + h(getNamePart(t.recipient_contact_person,1)) + '"></div>';
        html += '</div>';
        // Hidden middle name — still sent (empty) so server doesn't break
        html += '<input type="hidden" id="ef_mid" value="">';
        // Телефон + живий пошук контрагента
        html += '<div style="margin-bottom:6px"><div class="ttn-rf-label">Телефон</div>';
        html += '<div class="ttn-search-wrap">';
        html += '<input type="text" class="ttn-ef" id="ef_phone" value="' + h(t.recipients_phone||'') + '" placeholder="+380…" autocomplete="off">';
        html += '<div class="ttn-search-dd" id="ef_phone_dd" style="display:none"></div>';
        html += '</div></div>';
        // Місто
        html += '<div style="margin-bottom:6px"><div class="ttn-rf-label">Місто</div><div class="ttn-search-wrap">';
        html += '<input type="text" class="ttn-ef" id="ef_city_text" value="' + h(t.city_recipient_desc||'') + '" placeholder="Пошук міста…" autocomplete="off">';
        html += '<input type="hidden" id="ef_city_ref" value="' + h(t.city_recipient_ref||'') + '">';
        html += '<div class="ttn-search-dd" id="ef_city_dd" style="display:none"></div>';
        html += '</div></div>';
        // Відділення
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

        // #1: Оголошена вартість >= Контроль оплати (авто-підняття + підказки)
        var codInp  = document.getElementById('ef_cod');
        var declInp = document.getElementById('ef_declared');
        var codHint  = document.getElementById('ef_cod_hint');
        var declHint = document.getElementById('ef_declared_hint');

        function updateCodDeclHints() {
            if (!codInp || !declInp) return;
            var cod  = parseFloat(codInp.value)  || 0;
            var decl = parseFloat(declInp.value) || 0;
            if (cod > 0 && decl < cod) {
                // Auto-raise declared
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

        if (codInp)  { codInp.addEventListener('input',  updateCodDeclHints); }
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
        // Ініціальний стан підказок
        updateCodDeclHints();

        // Seats table
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
                setTimeout(function(){ window.location.reload(); },600);
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
                var cb = document.querySelector('.ttn-row-check[value="'+_ttn.id+'"]');
                if (cb) { var tr=cb.closest('tr'); if(tr) tr.remove(); }
                closeModal();
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
        // Sync readonly summary fields
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
            // Renumber
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
        // Mode toggle
        var toggle = document.getElementById('seatsDetailedToggle');
        if (toggle) {
            toggle.addEventListener('change', function() {
                if (this.checked) {
                    // Switch simple → detailed: read weight/seats from simple inputs
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
                    // Switch detailed → simple: sum weights from table
                    var seats = collectSeats();
                    var sumW = 0; seats.forEach(function(s){ sumW += s.weight; });
                    sumW = Math.round(sumW * 100) / 100;
                    _ttn.options_seat = '[]';
                    _ttn.weight = sumW || _ttn.weight;
                    _ttn.seats_amount = seats.length;
                    _seatsMode = 'simple';
                }
                // Re-render just the seats section
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

    // ── City / Warehouse search ───────────────────────────────────────────
    // ── Dropdown helpers (click-based, not mousedown) ────────────────────
    var _openDd = null; // { inp, dd }

    function posDd(inp, dd) {
        var r = inp.getBoundingClientRect();
        dd.style.top   = r.bottom + 'px';
        dd.style.left  = r.left + 'px';
        dd.style.width = r.width + 'px';
    }

    // Hide dropdown when clicking outside input+dd
    document.addEventListener('mousedown', function(e) {
        if (!_openDd) return;
        var inp = _openDd.inp;
        var dd  = _openDd.dd;
        if (e.target !== inp && !dd.contains(e.target)) {
            dd.classList.add('hidden');
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
                            // Заповнюємо ім'я
                            var parts = (cp.name || '').trim().split(/\s+/);
                            var lastEl  = document.getElementById('ef_last');
                            var firstEl = document.getElementById('ef_first');
                            if (lastEl  && parts[0]) lastEl.value  = parts[0];
                            if (firstEl && parts[1]) firstEl.value = parts[1];
                            // Нормалізуємо телефон з БД
                            if (phone) {
                                var digits = phone.replace(/\D/g,'');
                                // 380XXXXXXXXX → 0XXXXXXXXX
                                if (digits.length === 12 && digits.substr(0,2)==='38') digits = digits.substr(2);
                                // 8XXXXXXXXX → 0XXXXXXXXX
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
        // Seats: detailed or simple
        if (_seatsMode === 'detailed') {
            var seats = collectSeats();
            var totalW = 0; seats.forEach(function(s){ totalW += s.weight; });
            totalW = Math.round(totalW * 100) / 100;
            p += '&options_seat=' + encodeURIComponent(JSON.stringify(seats));
            p += '&weight=' + (totalW || 0.5);
            p += '&seats_amount=' + seats.length;
            p += '&manual_handling=0'; // per-seat manual stored inside options_seat
        } else {
            // Simple mode
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
        }).catch(function(){ if(btn){btn.disabled=false;btn.textContent='💾 Зберегти';} showToast('Мережева помилка',true); });
    }
}());

// Delegated handler for "Надіслати ▾" buttons in TTN detail panel
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.js-ttn-send');
    if (!btn) return;
    var cpId = parseInt(btn.getAttribute('data-cp'), 10) || 0;
    if (!cpId) return;
    SendTemplates.show(btn, {
        cpId:    cpId,
        context: 'ttn',
        channel: 'viber',
        vars: {
            '{ttn_number}': btn.getAttribute('data-ttn')    || '',
            '{ttn_status}': btn.getAttribute('data-status') || '',
            '{status}':     btn.getAttribute('data-status') || ''
        }
    });
});

</script>
