<?php
function ssPageUrl($p, $senderRef, $dateFrom, $dateTo) {
    $q = array('page' => $p);
    if ($senderRef) $q['sender_ref'] = $senderRef;
    if ($dateFrom)  $q['date_from']  = $dateFrom;
    if ($dateTo)    $q['date_to']    = $dateTo;
    return '/novaposhta/scansheets?' . http_build_query($q);
}
$curUrl  = '/novaposhta/scansheets';
$today   = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
?>
<style>
.ss-toolbar { display:flex; align-items:center; gap:8px; margin-bottom:10px; }
.ss-toolbar h1 { margin:0; font-size:18px; font-weight:700; flex-shrink:0; }
.ss-toolbar .btn { height:34px; padding:0 12px; }
.ss-sender-btn.active { background:#2563eb; color:#fff; border-color:#2563eb; }
.ss-expand-btn { background:none; border:none; cursor:pointer; padding:2px 4px; color:#6b7280; font-size:13px; line-height:1; }
.ss-expand-btn:hover { color:#111; }
.ss-sub-row { display:none; }
.ss-sub-row.open { display:table-row; }
.ss-sub-cell { padding:0 !important; }
.ss-ttn-table { width:100%; font-size:11px; border-collapse:collapse; }
.ss-ttn-table td { padding:4px 10px; border-bottom:1px solid #f3f4f6; }
.ss-ttn-table tr:last-child td { border-bottom:none; }
.ss-ttn-table tr:hover td { background:#f9fafb; }
.ss-row-actions { display:flex; gap:4px; }
</style>

<div class="page-wrap-lg">

  <!-- Toolbar -->
  <div class="ss-toolbar">
    <h1>Реєстри НП</h1>
    <button type="button" class="btn btn-primary" id="ssBtnCreate">+ Новий реєстр</button>
    <div style="flex:1"></div>
    <?php foreach ($senders as $s): ?>
      <button type="button"
              class="btn btn-sm ss-sender-btn <?php echo ($senderRef===$s['Ref'])?'active':''; ?>"
              onclick="window.location='<?php echo $curUrl . '?sender_ref=' . urlencode($s['Ref']); ?>'">
        <?php echo ViewHelper::h($s['Description']); ?>
      </button>
    <?php endforeach; ?>
    <button type="button" class="btn btn-sm" id="ssBtnSync" title="Синхронізувати з НП" style="margin-left:4px">↻ Синхр.</button>
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
  <table class="crm-table" id="ssTable">
    <thead>
      <tr>
        <th style="width:28px"></th>
        <th><input type="checkbox" id="ssCheckAll" title="Вибрати всі"></th>
        <th>Номер реєстру</th>
        <th style="text-align:right">ТТН</th>
        <th style="text-align:right">Місць</th>
        <th style="text-align:right;background:#f0fdf4;color:#166534">Сума ТТН</th>
        <th style="text-align:right;background:#fff7ed;color:#9a3412">Накл. платіж</th>
        <th>Дата</th>
        <th>Статус</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="10" style="text-align:center;color:#9ca3af;padding:32px">Реєстрів не знайдено</td></tr>
    <?php else: ?>
      <?php foreach ($rows as $row): ?>
        <?php $ssRef = $row['Ref']; ?>
        <tr data-ref="<?php echo ViewHelper::h($ssRef); ?>" class="ss-main-row">
          <td>
            <button type="button" class="ss-expand-btn" data-ref="<?php echo ViewHelper::h($ssRef); ?>"
                    data-sender="<?php echo ViewHelper::h($row['sender_ref']); ?>"
                    title="Показати ТТН">▶</button>
          </td>
          <td><input type="checkbox" class="ss-check" value="<?php echo ViewHelper::h($ssRef); ?>"></td>
          <td class="fw-600">
            <?php echo ViewHelper::h($row['Number'] ?: $ssRef); ?>
          </td>
          <td style="text-align:right"><?php echo (int)$row['Count']; ?></td>
          <td style="text-align:right" class="nowrap">
            <?php echo $row['total_seats'] !== null ? (int)$row['total_seats'] : '—'; ?>
          </td>
          <td style="text-align:right;background:#f0fdf4" class="nowrap">
            <?php echo $row['total_cost'] !== null ? number_format((float)$row['total_cost'], 2, '.', ' ') . ' грн' : '—'; ?>
          </td>
          <td style="text-align:right;background:#fff7ed" class="nowrap">
            <?php echo $row['total_redelivery'] !== null ? number_format((float)$row['total_redelivery'], 2, '.', ' ') . ' грн' : '—'; ?>
          </td>
          <td class="fs-12 text-muted nowrap">
            <?php echo $row['DateTime'] ? date('d.m.Y H:i', strtotime($row['DateTime'])) : '—'; ?>
          </td>
          <td>
            <?php if ($row['status'] === 'open'): ?>
              <span class="badge badge-blue">Відкритий</span>
            <?php else: ?>
              <span class="badge badge-gray">Закритий</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="ss-row-actions">
              <button type="button" class="btn btn-xs btn-ghost ss-btn-print"
                      data-ref="<?php echo ViewHelper::h($ssRef); ?>"
                      data-sender="<?php echo ViewHelper::h($row['sender_ref']); ?>"
                      title="Друк реєстру">🖨 Друк</button>
              <button type="button" class="btn btn-xs btn-ghost ss-btn-delete"
                      data-ref="<?php echo ViewHelper::h($ssRef); ?>"
                      data-sender="<?php echo ViewHelper::h($row['sender_ref']); ?>"
                      title="Розформувати реєстр">Розформ.</button>
            </div>
          </td>
        </tr>
        <!-- Expandable sub-row for TTNs -->
        <tr class="ss-sub-row" id="ss-sub-<?php echo ViewHelper::h($ssRef); ?>">
          <td colspan="10" class="ss-sub-cell">
            <div class="ss-sub-content" id="ss-sub-content-<?php echo ViewHelper::h($ssRef); ?>"
                 style="padding:8px 12px 8px 40px">
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
      <a href="<?php echo ssPageUrl($page-1, $senderRef, $dateFrom, $dateTo); ?>">&laquo;</a>
    <?php endif; ?>
    <?php for ($p = max(1, $page-3); $p <= min($totalPages, $page+3); $p++): ?>
      <?php if ($p === $page): ?>
        <span class="cur"><?php echo $p; ?></span>
      <?php else: ?>
        <a href="<?php echo ssPageUrl($p, $senderRef, $dateFrom, $dateTo); ?>"><?php echo $p; ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
      <a href="<?php echo ssPageUrl($page+1, $senderRef, $dateFrom, $dateTo); ?>">&raquo;</a>
    <?php endif; ?>
    <span class="dots"><?php echo number_format($total, 0, '.', ' '); ?> реєстрів</span>
  </div>
  <?php endif; ?>

</div>

<!-- Modal: create scan sheet -->
<div class="modal-overlay hidden" id="ssCreateModal">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-head">
      <span>Новий реєстр</span>
      <button type="button" class="modal-close" id="ssModalClose">&#x2715;</button>
    </div>
    <div class="modal-body">
      <div class="form-row">
        <label>Відправник</label>
        <select id="ssSenderSelect">
          <option value="">— оберіть —</option>
          <?php foreach ($senders as $s): ?>
            <option value="<?php echo ViewHelper::h($s['Ref']); ?>"
              <?php echo ($senderRef === $s['Ref']) ? 'selected' : ''; ?>>
              <?php echo ViewHelper::h($s['Description']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <label>ТТН (NP refs, через кому)</label>
        <textarea id="ssTtnRefsInput" rows="4"
                  placeholder="UUID1, UUID2, …" style="font-size:11px;font-family:monospace"></textarea>
      </div>
      <div class="modal-error hidden" id="ssModalError"></div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn" id="ssModalCancel">Скасувати</button>
      <button type="button" class="btn btn-primary" id="ssModalSave">Створити реєстр</button>
    </div>
  </div>
</div>

<script>
(function(){
  // ── Create modal ──────────────────────────────────────────────────────────
  var modal      = document.getElementById('ssCreateModal');
  var btnCreate  = document.getElementById('ssBtnCreate');
  var btnClose   = document.getElementById('ssModalClose');
  var btnCancel  = document.getElementById('ssModalCancel');
  var btnSave    = document.getElementById('ssModalSave');
  var errEl      = document.getElementById('ssModalError');
  var senderSel  = document.getElementById('ssSenderSelect');
  var refsInput  = document.getElementById('ssTtnRefsInput');

  function openModal() { modal.classList.remove('hidden'); }
  function closeModal(){ modal.classList.add('hidden'); errEl.classList.add('hidden'); errEl.textContent = ''; }

  if (btnCreate) btnCreate.addEventListener('click', openModal);
  if (btnClose)  btnClose.addEventListener('click', closeModal);
  if (btnCancel) btnCancel.addEventListener('click', closeModal);
  modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });

  if (btnSave) btnSave.addEventListener('click', function(){
    var sRef = senderSel.value.trim();
    var refs = refsInput.value.trim();
    if (!sRef) { errEl.textContent = 'Оберіть відправника'; errEl.classList.remove('hidden'); return; }
    if (!refs) { errEl.textContent = 'Введіть ТТН refs'; errEl.classList.remove('hidden'); return; }
    btnSave.disabled = true; btnSave.textContent = 'Збереження…';
    fetch('/novaposhta/api/create_scansheet', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'sender_ref=' + encodeURIComponent(sRef) + '&ttn_refs=' + encodeURIComponent(refs)
    }).then(function(r){ return r.json(); }).then(function(res){
      if (!res.ok) {
        errEl.textContent = res.error || 'Помилка';
        errEl.classList.remove('hidden');
        btnSave.disabled = false; btnSave.textContent = 'Створити реєстр';
        return;
      }
      showToast('Реєстр створено');
      closeModal();
      window.location.reload();
    }).catch(function(){
      errEl.textContent = 'Мережева помилка';
      errEl.classList.remove('hidden');
      btnSave.disabled = false; btnSave.textContent = 'Створити реєстр';
    });
  });

  // ── Delete (розформувати) ─────────────────────────────────────────────────
  document.querySelectorAll('.ss-btn-delete').forEach(function(btn){
    btn.addEventListener('click', function(){
      var ref    = btn.dataset.ref;
      var sender = btn.dataset.sender;
      if (!confirm('Розформувати реєстр? ТТН не будуть видалені.')) return;
      fetch('/novaposhta/api/delete_scansheet', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'sender_ref=' + encodeURIComponent(sender) + '&scan_sheet_ref=' + encodeURIComponent(ref)
      }).then(function(r){ return r.json(); }).then(function(res){
        if (!res.ok) { alert('Помилка: ' + (res.error||'')); return; }
        showToast('Реєстр розформовано');
        var tr = btn.closest('tr');
        if (tr) {
          var sub = document.getElementById('ss-sub-' + ref);
          if (sub) sub.remove();
          tr.remove();
        }
      });
    });
  });

  // ── Print ─────────────────────────────────────────────────────────────────
  document.querySelectorAll('.ss-btn-print').forEach(function(btn){
    btn.addEventListener('click', function(){
      var ref    = btn.dataset.ref;
      var sender = btn.dataset.sender;
      var url = '/novaposhta/print/scansheet?ref=' + encodeURIComponent(ref) + '&sender_ref=' + encodeURIComponent(sender);
      window.open(url, '_blank');
    });
  });

  // ── Expand rows ───────────────────────────────────────────────────────────
  var loadedRefs = {};

  document.querySelectorAll('.ss-expand-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      var ref    = btn.dataset.ref;
      var sender = btn.dataset.sender;
      var subRow = document.getElementById('ss-sub-' + ref);
      if (!subRow) return;

      var isOpen = subRow.classList.contains('open');
      if (isOpen) {
        subRow.classList.remove('open');
        btn.textContent = '▶';
        return;
      }

      subRow.classList.add('open');
      btn.textContent = '▼';

      if (loadedRefs[ref]) return; // already loaded

      var content = document.getElementById('ss-sub-content-' + ref);
      fetch('/novaposhta/api/get_scansheet_ttns?scan_sheet_ref=' + encodeURIComponent(ref) + '&sender_ref=' + encodeURIComponent(sender))
        .then(function(r){ return r.json(); })
        .then(function(res){
          loadedRefs[ref] = true;
          if (!res.ok) {
            content.innerHTML = '<span style="color:#dc2626;font-size:12px">Помилка: ' + escHtml(res.error||'') + '</span>';
            return;
          }
          if (!res.ttns || !res.ttns.length) {
            var msg = res.warning || 'ТТН не знайдено';
            content.innerHTML = '<span style="color:#9ca3af;font-size:12px">' + escHtml(msg) + '</span>';
            return;
          }
          if (res.warning) {
            var warnDiv = document.createElement('div');
            warnDiv.style.cssText = 'font-size:11px;color:#92400e;background:#fef3c7;padding:4px 8px;border-radius:3px;margin-bottom:6px';
            warnDiv.textContent = res.warning;
            content.innerHTML = '';
            content.appendChild(warnDiv);
          }
          var rows = res.ttns.map(function(t){
            var cost   = t.cost        ? parseFloat(t.cost).toFixed(2)                   : '—';
            var redel  = t.backward_delivery_money ? parseFloat(t.backward_delivery_money).toFixed(2) : '—';
            var status = t.state_name  ? '<span class="badge badge-gray fs-12" style="font-size:10px">' + escHtml(t.state_name) + '</span>' : '—';
            return '<tr>' +
              '<td class="fw-600">' + escHtml(t.int_doc_number || t.ref) + '</td>' +
              '<td>' + escHtml(t.recipient_contact_person || '—') + '</td>' +
              '<td>' + escHtml(t.city_recipient_desc || '—') + '</td>' +
              '<td style="text-align:right">' + cost + ' грн</td>' +
              '<td style="text-align:right">' + redel + ' грн</td>' +
              '<td>' + status + '</td>' +
              '</tr>';
          }).join('');
          content.innerHTML = '<table class="ss-ttn-table">' +
            '<thead><tr>' +
            '<th style="width:160px">№ ТТН</th>' +
            '<th>Отримувач</th>' +
            '<th>Місто</th>' +
            '<th style="text-align:right">Вартість</th>' +
            '<th style="text-align:right">Накл. платіж</th>' +
            '<th>Статус</th>' +
            '</tr></thead><tbody>' + rows + '</tbody></table>';
        })
        .catch(function(){
          content.innerHTML = '<span style="color:#dc2626;font-size:12px">Помилка завантаження</span>';
        });
    });
  });

  function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  // ── Select all checkbox ────────────────────────────────────────────────────
  var checkAll = document.getElementById('ssCheckAll');
  if (checkAll) {
    checkAll.addEventListener('change', function(){
      document.querySelectorAll('.ss-check').forEach(function(c){ c.checked = checkAll.checked; });
    });
  }

  // ── Sync button ───────────────────────────────────────────────────────────
  var btnSync = document.getElementById('ssBtnSync');
  if (btnSync) btnSync.addEventListener('click', function(){
    var sRef = '<?php echo ViewHelper::h($senderRef ? $senderRef : (isset($senders[0]['Ref']) ? $senders[0]['Ref'] : '')); ?>';
    if (!sRef) { alert('Оберіть відправника'); return; }
    btnSync.disabled = true; btnSync.textContent = '…';
    fetch('/novaposhta/api/sync_scansheats', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'all_senders=1'
    }).then(function(r){ return r.json(); }).then(function(res){
      btnSync.disabled = false; btnSync.textContent = '↻ Синхр.';
      if (!res.ok) { alert('Помилка: ' + (res.error||'')); return; }
      showToast('Синхронізовано: ' + res.count + ' реєстрів');
      window.location.reload();
    });
  });
}());
</script>
