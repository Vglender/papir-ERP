<?php
function ccPageUrl($p, $senderRef, $dateFrom, $dateTo) {
    $q = array('page' => $p);
    if ($senderRef) $q['sender_ref'] = $senderRef;
    if ($dateFrom)  $q['date_from']  = $dateFrom;
    if ($dateTo)    $q['date_to']    = $dateTo;
    return '/novaposhta/courier-calls?' . http_build_query($q);
}
$curUrl    = '/novaposhta/courier-calls';
$today     = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
?>
<style>
.cc-toolbar { display:flex; align-items:center; gap:8px; margin-bottom:10px; }
.cc-toolbar h1 { margin:0; font-size:18px; font-weight:700; flex-shrink:0; }
.cc-toolbar .btn { height:34px; padding:0 12px; }
.cc-sender-btn.active { background:#2563eb; color:#fff; border-color:#2563eb; }

/* Expanded TTN sub-row */
.cc-expand-btn { background:none; border:none; cursor:pointer; padding:2px 4px; color:#6b7280; font-size:13px; line-height:1; }
.cc-expand-btn:hover { color:#111; }
.cc-sub-row { display:none; }
.cc-sub-row.open { display:table-row; }
.cc-sub-cell { padding:0 !important; }
.cc-ttn-table { width:100%; font-size:11px; border-collapse:collapse; }
.cc-ttn-table td { padding:4px 10px; border-bottom:1px solid #f3f4f6; }
.cc-ttn-table tr:last-child td { border-bottom:none; }
.cc-ttn-table tr:hover td { background:#f9fafb; }

/* Create modal */
.cc-modal-row { display:grid; gap:6px; margin-bottom:10px; }
.cc-modal-row label { font-size:12px; font-weight:600; color:#374151; }
.cc-modal-row select,
.cc-modal-row input  { width:100%; font-size:13px; padding:6px 8px;
                        border:1px solid #d1d5db; border-radius:6px; }
.cc-modal-row select:focus,
.cc-modal-row input:focus  { outline:none; border-color:#0d9488; }
.cc-field-row { display:grid; grid-template-columns:1fr 1fr; gap:8px; }

/* Table extras */
.cc-barcode { font-family:monospace; font-weight:600; color:#0d9488; font-size:13px; }
.cc-interval { font-size:11px; color:#64748b; }
</style>

<div class="page-wrap-lg">

  <!-- Toolbar -->
  <div class="cc-toolbar">
    <h1>Виклики кур'єра</h1>
    <button type="button" class="btn btn-primary" id="ccBtnCreate">+ Новий виклик</button>
    <div style="flex:1"></div>
    <?php foreach ($senders as $s): ?>
      <button type="button"
              class="btn btn-sm cc-sender-btn <?php echo ($senderRef === $s['Ref']) ? 'active' : ''; ?>"
              onclick="window.location='<?php echo $curUrl . '?sender_ref=' . urlencode($s['Ref']); ?>'">
        <?php echo ViewHelper::h($s['Description']); ?>
      </button>
    <?php endforeach; ?>
    <button type="button" class="btn btn-sm" id="ccBtnSync" title="Прив'язати ТТН до викликів за датою відправки" style="margin-left:4px">↻ Прив'язати ТТН</button>
  </div>

  <!-- Filter bar -->
  <div class="filter-bar">
    <div class="filter-bar-group">
      <span class="filter-bar-label">Дата</span>
      <?php
        $todayActive     = ($dateFrom === $today     && $dateTo === $today);
        $yesterdayActive = ($dateFrom === $yesterday && $dateTo === $yesterday);
      ?>
      <a href="<?php echo $curUrl . '?' . http_build_query(array_filter(array('sender_ref'=>$senderRef,'date_from'=>$today,'date_to'=>$today))); ?>"
         class="filter-pill <?php echo $todayActive ? 'active' : ''; ?>">Сьогодні</a>
      <a href="<?php echo $curUrl . '?' . http_build_query(array_filter(array('sender_ref'=>$senderRef,'date_from'=>$yesterday,'date_to'=>$yesterday))); ?>"
         class="filter-pill <?php echo $yesterdayActive ? 'active' : ''; ?>">Вчора</a>
    </div>
    <div class="filter-bar-sep"></div>
    <div class="filter-bar-group">
      <span class="filter-bar-label">Період</span>
      <form method="get" action="<?php echo $curUrl; ?>" style="display:inline-flex;gap:4px;align-items:center">
        <?php if ($senderRef): ?><input type="hidden" name="sender_ref" value="<?php echo ViewHelper::h($senderRef); ?>"><?php endif; ?>
        <input type="hidden" name="page" value="1">
        <input type="date" name="date_from" value="<?php echo ViewHelper::h($dateFrom); ?>"
               style="font-size:12px;border:1px solid #d1d5db;border-radius:4px;padding:3px 6px;height:28px">
        <span style="font-size:11px;color:#9ca3af">—</span>
        <input type="date" name="date_to" value="<?php echo ViewHelper::h($dateTo); ?>"
               style="font-size:12px;border:1px solid #d1d5db;border-radius:4px;padding:3px 6px;height:28px">
        <button type="submit" class="btn btn-sm" style="height:28px;padding:0 8px">OK</button>
        <?php if ($dateFrom || $dateTo): ?>
          <a href="<?php echo $curUrl . ($senderRef ? '?sender_ref='.urlencode($senderRef) : ''); ?>"
             class="btn btn-sm btn-ghost" style="height:28px;padding:0 8px">✕</a>
        <?php endif; ?>
      </form>
    </div>
    <button type="button" class="filter-bar-gear" title="Налаштувати фільтри">
      <svg viewBox="0 0 16 16" fill="none"><path d="M8 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4z" stroke="currentColor" stroke-width="1.4"/><path d="M8 1v1.5M8 13.5V15M1 8h1.5M13.5 8H15M3.05 3.05l1.06 1.06M11.89 11.89l1.06 1.06M3.05 12.95l1.06-1.06M11.89 4.11l1.06-1.06" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
    </button>
  </div>

  <!-- Table -->
  <table class="crm-table" id="ccTable">
    <thead>
      <tr>
        <th style="width:28px"></th>
        <th>Штрих-код заявки</th>
        <th>Дата виклику</th>
        <th>Часовий інтервал</th>
        <th style="text-align:right">План. вага</th>
        <th style="text-align:right">ТТН</th>
        <th style="text-align:right">Факт. вага</th>
        <th>Адреса відправки</th>
        <th>Відправник</th>
        <th>Статус</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="11" style="text-align:center;color:#9ca3af;padding:32px">Викликів не знайдено</td></tr>
    <?php else: ?>
      <?php foreach ($rows as $row): ?>
        <?php $ccId = (int)$row['id']; ?>
        <tr data-id="<?php echo $ccId; ?>" class="cc-main-row">
          <td>
            <button type="button" class="cc-expand-btn" data-id="<?php echo $ccId; ?>" title="ТТН">▶</button>
          </td>
          <td>
            <span class="cc-barcode"><?php echo ViewHelper::h($row['Barcode'] ?: '—'); ?></span>
          </td>
          <td class="nowrap">
            <?php echo ViewHelper::h($row['preferred_delivery_date'] ?: '—'); ?>
          </td>
          <td>
            <?php if ($row['time_interval_start'] && $row['time_interval_end']): ?>
              <span class="cc-interval"><?php echo ViewHelper::h($row['time_interval_start']); ?>–<?php echo ViewHelper::h($row['time_interval_end']); ?></span>
            <?php else: ?>
              <span class="cc-interval text-muted"><?php echo ViewHelper::h($row['time_interval'] ?: '—'); ?></span>
            <?php endif; ?>
          </td>
          <td style="text-align:right" class="nowrap">
            <?php echo $row['planned_weight'] !== null ? number_format((float)$row['planned_weight'], 1, '.', '') . ' кг' : '—'; ?>
          </td>
          <td style="text-align:right">
            <?php echo $row['ttn_count'] > 0 ? '<span class="badge badge-blue">' . $row['ttn_count'] . '</span>' : '—'; ?>
          </td>
          <td style="text-align:right" class="nowrap">
            <?php echo $row['total_weight'] !== null ? number_format((float)$row['total_weight'], 1, '.', '') . ' кг' : '—'; ?>
          </td>
          <td>
            <?php if ($row['address_city']): ?>
              <span class="text-muted fs-12"><?php echo ViewHelper::h($row['address_city']); ?> · </span>
            <?php endif; ?>
            <?php echo ViewHelper::h($row['address_desc'] ?: '—'); ?>
          </td>
          <td class="text-muted fs-12"><?php echo ViewHelper::h($row['sender_desc'] ?: '—'); ?></td>
          <td>
            <?php if ($row['status'] === 'done'): ?>
              <span class="badge badge-green">Виконано</span>
            <?php elseif ($row['status'] === 'cancelled'): ?>
              <span class="badge badge-gray">Скасовано</span>
            <?php else: ?>
              <span class="badge badge-blue">Активна</span>
            <?php endif; ?>
          </td>
          <td>
            <button type="button" class="btn btn-xs btn-ghost cc-btn-delete"
                    data-id="<?php echo $ccId; ?>"
                    data-barcode="<?php echo ViewHelper::h($row['Barcode']); ?>"
                    title="Скасувати виклик">Скасувати</button>
          </td>
        </tr>
        <!-- Expanded TTN sub-row -->
        <tr class="cc-sub-row" id="cc-sub-<?php echo $ccId; ?>">
          <td colspan="11" class="cc-sub-cell">
            <div id="cc-sub-content-<?php echo $ccId; ?>" style="padding:8px 12px 8px 44px">
              <span style="color:#9ca3af;font-size:12px">Завантаження…</span>
            </div>
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
      <a href="<?php echo ccPageUrl($page-1, $senderRef, $dateFrom, $dateTo); ?>">&laquo;</a>
    <?php endif; ?>
    <?php for ($p = max(1, $page-3); $p <= min($totalPages, $page+3); $p++): ?>
      <?php if ($p === $page): ?>
        <span class="cur"><?php echo $p; ?></span>
      <?php else: ?>
        <a href="<?php echo ccPageUrl($p, $senderRef, $dateFrom, $dateTo); ?>"><?php echo $p; ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
      <a href="<?php echo ccPageUrl($page+1, $senderRef, $dateFrom, $dateTo); ?>">&raquo;</a>
    <?php endif; ?>
    <span class="dots"><?php echo number_format($total, 0, '.', ' '); ?> викликів</span>
  </div>
  <?php endif; ?>

</div>

<!-- ═══ Create courier call modal ════════════════════════════════════════════ -->
<div class="modal-overlay" id="ccModal" style="display:none">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-head">
      <span>Новий виклик кур'єра</span>
      <button type="button" class="modal-close" id="ccModalClose">&#x2715;</button>
    </div>
    <div class="modal-body">

      <div class="cc-modal-row">
        <label>Контактна особа *</label>
        <select id="ccContact">
          <option value="">— оберіть —</option>
          <?php foreach ($contacts as $c): ?>
            <option value="<?php echo ViewHelper::h($c['Ref']); ?>">
              <?php echo ViewHelper::h($c['full_name']); ?>
              <?php if ($c['phone']): ?>(<?php echo ViewHelper::h($c['phone']); ?>)<?php endif; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="cc-modal-row">
        <label>Адреса відправки *</label>
        <select id="ccAddress">
          <option value="">— оберіть —</option>
          <?php foreach ($addresses as $a): ?>
            <option value="<?php echo ViewHelper::h($a['Ref']); ?>"
                    data-city="<?php echo ViewHelper::h($a['CityDescription']); ?>">
              <?php if ($a['CityDescription']): ?><?php echo ViewHelper::h($a['CityDescription']); ?> · <?php endif; ?>
              <?php echo ViewHelper::h($a['Description']); ?>
              <?php if ($a['is_default']): ?>(default)<?php endif; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="cc-field-row">
        <div class="cc-modal-row">
          <label>Дата виклику *</label>
          <input type="date" id="ccDate" min="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="cc-modal-row">
          <label>Часовий інтервал *</label>
          <select id="ccInterval">
            <option value="">— оберіть —</option>
            <option value="CityPickingTimeInterval1"  data-start="08:00" data-end="09:00">08:00–09:00</option>
            <option value="CityPickingTimeInterval2"  data-start="09:00" data-end="10:00">09:00–10:00</option>
            <option value="CityPickingTimeInterval3"  data-start="10:00" data-end="12:00">10:00–12:00</option>
            <option value="CityPickingTimeInterval4"  data-start="12:00" data-end="14:00">12:00–14:00</option>
            <option value="CityPickingTimeInterval5"  data-start="13:00" data-end="14:00">13:00–14:00</option>
            <option value="CityPickingTimeInterval6"  data-start="14:00" data-end="16:00">14:00–16:00</option>
            <option value="CityPickingTimeInterval7"  data-start="16:00" data-end="18:00">16:00–18:00</option>
            <option value="CityPickingTimeInterval8"  data-start="18:00" data-end="19:00">18:00–19:00</option>
            <option value="CityPickingTimeInterval9"  data-start="19:00" data-end="20:00">19:00–20:00</option>
            <option value="CityPickingTimeInterval10" data-start="20:00" data-end="21:00">20:00–21:00</option>
          </select>
          <div id="ccIntervalNote" style="font-size:11px;color:#94a3b8;margin-top:3px;display:none">
            Для сьогодні доступні лише майбутні інтервали
          </div>
        </div>
      </div>

      <div class="cc-modal-row" style="max-width:160px">
        <label>Планова вага, кг *</label>
        <input type="number" id="ccWeight" min="0.1" step="0.1" placeholder="0.0">
      </div>

      <div class="modal-error" id="ccError" style="display:none"></div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-primary" id="ccBtnSave">Створити заявку</button>
      <button type="button" class="btn btn-ghost" id="ccModalCancel">Скасувати</button>
    </div>
  </div>
</div>

<script>
(function () {
    var senderRef = <?php echo json_encode($senderRef); ?>;

    // ── Modal open/close ──────────────────────────────────────────────────
    function openModal() { document.getElementById('ccModal').style.display = 'flex'; }
    function closeModal() {
        document.getElementById('ccModal').style.display = 'none';
        document.getElementById('ccError').style.display = 'none';
        document.getElementById('ccContact').value  = '';
        document.getElementById('ccAddress').value  = '';
        document.getElementById('ccDate').value     = '';
        document.getElementById('ccInterval').value = '';
        document.getElementById('ccWeight').value   = '';
        if (intervalNote) intervalNote.style.display = 'none';
        // Re-enable all interval options
        Array.prototype.forEach.call(document.getElementById('ccInterval').options, function(o) { o.disabled = false; });
    }

    document.getElementById('ccBtnCreate').addEventListener('click', openModal);
    document.getElementById('ccModalClose').addEventListener('click', closeModal);
    document.getElementById('ccModalCancel').addEventListener('click', closeModal);

    // ── Filter past time slots when today is selected ────────────────────
    var intervalSel  = document.getElementById('ccInterval');
    var intervalNote = document.getElementById('ccIntervalNote');
    var todayStr     = '<?php echo date('Y-m-d'); ?>';

    function updateIntervalOptions() {
        var dateRaw = document.getElementById('ccDate').value;
        var isToday = (dateRaw === todayStr);
        var nowH    = new Date().getHours();
        var nowM    = new Date().getMinutes();
        var nowMins = nowH * 60 + nowM;

        Array.prototype.forEach.call(intervalSel.options, function(opt) {
            if (!opt.dataset.start) return; // placeholder option
            var endTime = opt.dataset.end || '23:59';
            var ep = endTime.split(':');
            var endMins = parseInt(ep[0], 10) * 60 + parseInt(ep[1], 10);
            // Disable past slots only for today; require 30 min buffer
            opt.disabled = isToday && (endMins <= nowMins + 30);
        });

        // Reset selection if currently selected option became disabled
        if (intervalSel.value && intervalSel.options[intervalSel.selectedIndex] &&
            intervalSel.options[intervalSel.selectedIndex].disabled) {
            intervalSel.value = '';
        }

        if (intervalNote) intervalNote.style.display = isToday ? 'block' : 'none';
    }

    document.getElementById('ccDate').addEventListener('change', updateIntervalOptions);

    // ── Save ──────────────────────────────────────────────────────────────
    document.getElementById('ccBtnSave').addEventListener('click', function () {
        var btn       = this;
        var contact   = document.getElementById('ccContact').value;
        var address   = document.getElementById('ccAddress').value;
        var dateRaw   = document.getElementById('ccDate').value; // Y-m-d
        var intSel    = document.getElementById('ccInterval');
        var weight    = document.getElementById('ccWeight').value.trim();
        var errEl     = document.getElementById('ccError');
        var interval  = intSel.value;
        var selOpt    = intSel.options[intSel.selectedIndex];
        var timeStart = selOpt ? (selOpt.dataset.start || '') : '';
        var timeEnd   = selOpt ? (selOpt.dataset.end   || '') : '';
        // Convert Y-m-d → dd.mm.yyyy for NP API
        var date = '';
        if (dateRaw) {
            var dp = dateRaw.split('-');
            date = dp[2] + '.' + dp[1] + '.' + dp[0];
        }
        var addrDesc  = '';
        var addrOpt   = document.getElementById('ccAddress').options[document.getElementById('ccAddress').selectedIndex];
        if (addrOpt) addrDesc = addrOpt.textContent.trim();

        if (!contact || !address || !date || !interval || !weight) {
            errEl.textContent = 'Заповніть всі поля';
            errEl.style.display = 'block';
            return;
        }
        errEl.style.display = 'none';
        btn.disabled = true;
        btn.textContent = 'Зберігається…';

        var body = 'sender_ref='     + encodeURIComponent(senderRef) +
                   '&contact_ref='   + encodeURIComponent(contact) +
                   '&address_ref='   + encodeURIComponent(address) +
                   '&address_desc='  + encodeURIComponent(addrDesc) +
                   '&delivery_date=' + encodeURIComponent(date) +
                   '&time_interval=' + encodeURIComponent(interval) +
                   '&time_start='    + encodeURIComponent(timeStart) +
                   '&time_end='      + encodeURIComponent(timeEnd) +
                   '&planned_weight='+ encodeURIComponent(weight);

        fetch('/novaposhta/api/create_courier_call', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            btn.disabled = false;
            btn.textContent = 'Створити заявку';
            if (!res.ok) {
                errEl.textContent = res.error || 'Помилка';
                errEl.style.display = 'block';
                return;
            }
            closeModal();
            window.location.reload();
        })
        .catch(function () {
            btn.disabled = false;
            btn.textContent = 'Створити заявку';
            errEl.textContent = 'Помилка мережі';
            errEl.style.display = 'block';
        });
    });

    // ── Expand TTNs ───────────────────────────────────────────────────────
    var expandedCalls = {};

    document.querySelectorAll('.cc-expand-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id      = btn.dataset.id;
            var subRow  = document.getElementById('cc-sub-' + id);
            var content = document.getElementById('cc-sub-content-' + id);
            if (!subRow) return;

            var isOpen = subRow.classList.contains('open');
            if (isOpen) {
                subRow.classList.remove('open');
                btn.textContent = '▶';
                return;
            }
            subRow.classList.add('open');
            btn.textContent = '▼';

            if (expandedCalls[id]) return; // already loaded
            expandedCalls[id] = true;

            fetch('/novaposhta/api/get_courier_call_ttns?call_id=' + encodeURIComponent(id))
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok || !res.ttns || !res.ttns.length) {
                    content.innerHTML = '<span style="color:#9ca3af;font-size:12px">ТТН не прив\'язано</span>';
                    return;
                }
                var html = '<table class="cc-ttn-table"><thead><tr>' +
                    '<th style="text-align:left">ТТН</th>' +
                    '<th style="text-align:right">Вага, кг</th>' +
                    '<th>Отримувач</th>' +
                    '<th>Телефон</th>' +
                    '<th>Статус</th>' +
                    '</tr></thead><tbody>';
                res.ttns.forEach(function (t) {
                    var w = t.weight !== null && t.weight !== undefined
                        ? parseFloat(t.weight).toFixed(1)
                        : (t.ttn_weight !== null && t.ttn_weight !== undefined ? parseFloat(t.ttn_weight).toFixed(1) : '—');
                    html += '<tr>' +
                        '<td class="cc-barcode" style="font-size:12px">' + esc(t.int_doc_number) + '</td>' +
                        '<td style="text-align:right">' + w + '</td>' +
                        '<td>' + esc(t.recipient || '—') + '</td>' +
                        '<td class="text-muted fs-12">' + esc(t.phone || '—') + '</td>' +
                        '<td class="fs-12 text-muted">' + esc(t.state_name || '—') + '</td>' +
                        '</tr>';
                });
                html += '</tbody></table>';
                content.innerHTML = html;
            })
            .catch(function () {
                content.innerHTML = '<span style="color:#ef4444;font-size:12px">Помилка завантаження</span>';
                expandedCalls[id] = false;
            });
        });
    });

    function esc(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Sync (archive) ────────────────────────────────────────────────────
    document.getElementById('ccBtnSync').addEventListener('click', function () {
        if (!senderRef) { alert('Оберіть відправника'); return; }
        var btn = this;
        btn.disabled = true;
        btn.textContent = '…';
        fetch('/novaposhta/api/sync_courier_calls', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'sender_ref=' + encodeURIComponent(senderRef)
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            btn.disabled = false;
            btn.textContent = '↻ Синхр.';
            if (!res.ok) { alert('Помилка: ' + (res.error || '')); return; }
            showToast('Прив\'язано ТТН: ' + res.linked);
            if (res.linked > 0) window.location.reload();
        })
        .catch(function () {
            btn.disabled = false;
            btn.textContent = '↻ Синхр.';
        });
    });

    // ── Delete ────────────────────────────────────────────────────────────
    document.querySelectorAll('.cc-btn-delete').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id      = btn.dataset.id;
            var barcode = btn.dataset.barcode || id;
            if (!confirm('Скасувати виклик ' + barcode + '?\nЗаявку буде видалено і в НП API (якщо можливо).')) return;
            btn.disabled = true;
            fetch('/novaposhta/api/delete_courier_call', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'id=' + encodeURIComponent(id)
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok) { btn.disabled = false; alert('Помилка: ' + (res.error || '')); return; }
                var tr = btn.closest('tr');
                if (tr) tr.remove();
                if (res.np_error) showToast('Локально видалено. НП: ' + res.np_error);
                else showToast('Виклик скасовано');
            })
            .catch(function () { btn.disabled = false; });
        });
    });

}());
</script>