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
<link rel="stylesheet" href="/modules/shared/ttn-detail-modal.css?v=<?php echo filemtime('/var/www/papir/modules/shared/ttn-detail-modal.css'); ?>">
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


/* TTN Detail Modal — loaded from shared CSS */

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

<script src="/modules/shared/ttn-detail-modal.js?v=<?php echo filemtime('/var/www/papir/modules/shared/ttn-detail-modal.js'); ?>"></script>
<script>
// ── TTN Detail Modal — init shared component ────────────────────────────
(function () {
    TtnDetailModal.init();
    TtnDetailModal.onDelete = function(ttnId) {
        // Remove row from table on this page
        var cb = document.querySelector('.ttn-row-check[value="'+ttnId+'"]');
        if (cb) { var tr = cb.closest('tr'); if(tr) tr.remove(); }
    };
    TtnDetailModal.onSave = function() {
        // Reload table to reflect changes
        setTimeout(function(){ window.location.reload(); }, 600);
    };
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
