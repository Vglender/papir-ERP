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

// Build pagination URL helper
function npTtnPageUrl($p, $search, $stateGroup, $dateFrom, $dateTo) {
    $q = array('page' => $p);
    if ($search)     $q['search']      = $search;
    if ($stateGroup) $q['state_group'] = $stateGroup;
    if ($dateFrom)   $q['date_from']   = $dateFrom;
    if ($dateTo)     $q['date_to']     = $dateTo;
    return '/novaposhta/ttns?' . http_build_query($q);
}
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
.ttn-order-empty { cursor: pointer; border-bottom: 1px dashed #d1d5db; }
.ttn-order-empty:hover { color: #2563eb; border-color: #2563eb; }
.ttn-order-input-wrap { display:inline-flex; align-items:center; gap:3px; }
.ttn-order-input-wrap input { width:72px; font-size:12px; border:1px solid #d1d5db; border-radius:3px; padding:1px 4px; }
.ttn-order-input-wrap button { font-size:11px; padding:1px 5px; cursor:pointer; border:1px solid; border-radius:3px; line-height:1.4; }
.ttn-order-save { background:#2563eb; color:#fff; border-color:#2563eb; }
.ttn-order-cancel { background:#fff; color:#6b7280; border-color:#d1d5db; }
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
      <?php if ($stateGroup): ?><input type="hidden" name="state_group" value="<?php echo ViewHelper::h($stateGroup); ?>"><?php endif; ?>
      <?php if ($dateFrom):   ?><input type="hidden" name="date_from"   value="<?php echo ViewHelper::h($dateFrom); ?>"><?php endif; ?>
      <?php if ($dateTo):     ?><input type="hidden" name="date_to"     value="<?php echo ViewHelper::h($dateTo); ?>"><?php endif; ?>
    </div>
  </form>

  <!-- Filter bar -->
  <div class="filter-bar">
    <div class="filter-bar-group">
      <span class="filter-bar-label">Статус</span>
      <?php
      // state_group keys map to TtnRepository::$groupMap
      $stateOptions = array(
          ''        => 'Усі',
          'transit' => 'В дорозі',     // NP StatusCode 4,5,6
          'branch'  => 'На відділенні',// NP StatusCode 7,8,105
          'received'=> 'Отримана',     // NP StatusCode 9
          'return'  => 'Повертається', // NP StatusCode 10,11,103
          'refused' => 'Відмова',      // NP StatusCode 102,106
      );
      foreach ($stateOptions as $val => $lbl):
        $active = ($stateGroup === $val) ? ' active' : '';
        $q = array('page'=>1);
        if ($search)     $q['search']      = $search;
        if ($val !== '') $q['state_group'] = $val;
        if ($dateFrom)   $q['date_from']   = $dateFrom;
        if ($dateTo)     $q['date_to']     = $dateTo;
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
        function npDateUrl($from, $to, $search, $stateGroup) {
            $q = array('page'=>1, 'date_from'=>$from, 'date_to'=>$to);
            if ($search)     $q['search']      = $search;
            if ($stateGroup) $q['state_group'] = $stateGroup;
            return '/novaposhta/ttns?' . http_build_query($q);
        }
      ?>
      <a href="<?php echo npDateUrl($today, $today, $search, $stateGroup); ?>"
         class="filter-pill<?php echo $todayActive ? ' active' : ''; ?>">Сьогодні</a>
      <a href="<?php echo npDateUrl($yesterday, $yesterday, $search, $stateGroup); ?>"
         class="filter-pill<?php echo $yesterdayActive ? ' active' : ''; ?>">Вчора</a>
      <form method="get" action="<?php echo $curUrl; ?>" style="display:inline-flex;gap:4px;align-items:center;margin-left:4px">
        <?php if ($stateGroup): ?><input type="hidden" name="state_group" value="<?php echo ViewHelper::h($stateGroup); ?>"><?php endif; ?>
        <?php if ($search):     ?><input type="hidden" name="search"      value="<?php echo ViewHelper::h($search); ?>"><?php endif; ?>
        <input type="hidden" name="page" value="1">
        <input type="date" name="date_from" value="<?php echo ViewHelper::h($dateFrom); ?>"
               style="font-size:12px;border:1px solid #d1d5db;border-radius:4px;padding:3px 6px;height:28px">
        <span style="font-size:11px;color:#9ca3af">—</span>
        <input type="date" name="date_to" value="<?php echo ViewHelper::h($dateTo); ?>"
               style="font-size:12px;border:1px solid #d1d5db;border-radius:4px;padding:3px 6px;height:28px">
        <button type="submit" class="btn btn-sm" style="height:28px;padding:0 8px">OK</button>
        <?php if ($dateFrom || $dateTo): ?>
          <?php $resetQ = array('page'=>1); if ($search) $resetQ['search']=$search; if ($stateGroup) $resetQ['state_group']=$stateGroup; ?>
          <a href="<?php echo $curUrl . '?' . http_build_query($resetQ); ?>"
             class="btn btn-sm btn-ghost" style="height:28px;padding:0 8px">✕</a>
        <?php endif; ?>
      </form>
    </div>
    <button type="button" class="filter-bar-gear" title="Налаштувати фільтри">
      <svg viewBox="0 0 16 16" fill="none"><path d="M8 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4z" stroke="currentColor" stroke-width="1.4"/><path d="M8 1v1.5M8 13.5V15M1 8h1.5M13.5 8H15M3.05 3.05l1.06 1.06M11.89 11.89l1.06 1.06M3.05 12.95l1.06-1.06M11.89 4.11l1.06-1.06" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
    </button>
  </div>

  <!-- Table -->
  <table class="crm-table">
    <thead>
      <tr>
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
      </tr>
    </thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="10" style="text-align:center;color:#9ca3af;padding:32px">ТТН не знайдено</td></tr>
    <?php else: ?>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td class="np-ttn-num">
            <?php if ($row['int_doc_number']): ?>
              <a href="https://novaposhta.ua/tracking/?cargo_number=<?php echo ViewHelper::h($row['int_doc_number']); ?>"
                 target="_blank" style="text-decoration:none;color:inherit">
                <?php echo ViewHelper::h($row['int_doc_number']); ?>
              </a>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td class="ttn-order-cell" data-ttn-id="<?php echo (int)$row['id']; ?>">
            <?php if ($row['customerorder_id']): ?>
              <a href="/customerorder/edit?id=<?php echo (int)$row['customerorder_id']; ?>" class="fs-12 ttn-order-link">
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
            <?php
            $cls = npStateClass((int)$row['state_define']);
            ?>
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
            <?php echo $row['moment'] ? date('d.m.Y', strtotime($row['moment'])) : '—'; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="<?php echo npTtnPageUrl($page-1, $search, $stateGroup, $dateFrom, $dateTo); ?>">&laquo;</a>
    <?php endif; ?>
    <?php for ($p = max(1, $page-3); $p <= min($totalPages, $page+3); $p++): ?>
      <?php if ($p === $page): ?>
        <span class="cur"><?php echo $p; ?></span>
      <?php else: ?>
        <a href="<?php echo npTtnPageUrl($p, $search, $stateGroup, $dateFrom, $dateTo); ?>"><?php echo $p; ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
      <a href="<?php echo npTtnPageUrl($page+1, $search, $stateGroup, $dateFrom, $dateTo); ?>">&raquo;</a>
    <?php endif; ?>
    <span class="dots"><?php echo number_format($total, 0, '.', ' '); ?> ТТН</span>
  </div>
  <?php endif; ?>

</div>

<script src="/modules/shared/chip-search.js?v=<?php echo $chipSearchJs; ?>"></script>
<script>
ChipSearch.init('ttnChipBox', 'ttnChipTyper', 'ttnSearchHidden', null, {noComma: true});

(function () {
    var clearBtn = document.getElementById('ttnChipClear');
    var chipBox  = document.getElementById('ttnChipBox');
    var typer    = document.getElementById('ttnChipTyper');
    var hidden   = document.getElementById('ttnSearchHidden');
    var form     = document.getElementById('ttnFilterForm');
    if (!clearBtn || !chipBox || !typer || !hidden) return;

    function updateClear() {
        var has = chipBox.querySelectorAll('.chip').length > 0 || typer.value.trim() !== '';
        clearBtn.classList.toggle('hidden', !has);
    }
    new MutationObserver(updateClear).observe(chipBox, { childList: true });
    typer.addEventListener('input', updateClear);

    clearBtn.addEventListener('click', function () {
        chipBox.querySelectorAll('.chip').forEach(function(c){ c.remove(); });
        typer.value = '';
        hidden.value = '';
        clearBtn.classList.add('hidden');
        var pi = form.querySelector('input[name="page"]');
        if (pi) pi.value = 1;
        form.submit();
    });
    updateClear();
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
            var msg = 'Синхронізовано: +'  + res.inserted + ' нових, ' + res.updated + ' оновлено';
            if (res.errors && res.errors.length) {
                msg += ' ⚠ ' + res.errors.join('; ');
            }
            showToast(msg);
            if (res.inserted > 0) {
                setTimeout(function () { window.location.reload(); }, 800);
            }
        })
        .catch(function () {
            btn.disabled = false;
            icon.textContent = '↻';
            showToast('Мережева помилка', true);
        });
    });
}());

// ── Inline order linking ─────────────────────────────────────────────────────
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
            cell.innerHTML = '<a href="/customerorder/edit?id=' + orderId + '" class="fs-12 ttn-order-link">#' + orderId + '</a>';
            bindLink(cell);
            showToast('Прив\'язано до замовлення #' + orderId);
        }).catch(function () {
            showToast('Мережева помилка', true);
            cell.innerHTML = '<span class="text-muted ttn-order-empty" title="Натисніть щоб прив\'язати замовлення">—</span>';
            bindEmpty(cell);
        });
    }

    function bindEmpty(cell) {
        var span = cell.querySelector('.ttn-order-empty');
        if (span) span.addEventListener('click', function () { openOrderInput(cell); });
    }

    function bindLink(cell) {
        var link = cell.querySelector('.ttn-order-link');
        if (link) link.addEventListener('dblclick', function (e) { e.preventDefault(); openOrderInput(cell); });
    }

    document.querySelectorAll('.ttn-order-cell').forEach(function (cell) {
        bindEmpty(cell);
        bindLink(cell);
    });
}());
</script>