<?php
require_once __DIR__ . '/ukrposhta_bootstrap.php';
require_once __DIR__ . '/../auth/AuthService.php';
require_once __DIR__ . '/../../src/ViewHelper.php';

if (!\Papir\Crm\AuthService::getCurrentUser()) {
    header('Location: /login'); exit;
}

$activeNav = 'logistics';
$subNav    = 'up-groups';
$title     = 'Реєстри Укрпошти';

$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo   = isset($_GET['date_to'])   ? trim($_GET['date_to'])   : '';
$search   = isset($_GET['search'])    ? trim($_GET['search'])    : '';
$type     = isset($_GET['type'])      ? trim($_GET['type'])      : '';
$closed   = isset($_GET['closed'])    ? $_GET['closed']          : '';
$page     = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$limit    = 50;
$offset   = ($page - 1) * $limit;

$filters = array(
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
    'search'    => $search,
    'type'      => $type,
    'closed'    => $closed,
);

$data       = \Papir\Crm\UpGroupRepository::getList($filters, $limit, $offset);
$rows       = $data['rows'];
$total      = $data['total'];
$totalPages = $total > 0 ? (int)ceil($total / $limit) : 1;

$curUrl = '/ukrposhta/groups';
$today     = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

require_once __DIR__ . '/../shared/layout.php';
?>
<style>
.upg-toolbar { display:flex; align-items:center; gap:8px; margin-bottom:10px; flex-wrap:wrap; }
.upg-toolbar h1 { margin:0; font-size:18px; font-weight:700; }
.upg-toolbar .btn { height:34px; padding:0 12px; }
.upg-expand-btn { background:none; border:none; cursor:pointer; padding:2px 4px; color:#6b7280; font-size:13px; line-height:1; }
.upg-expand-btn:hover { color:#111; }
.upg-sub-row { display:none; }
.upg-sub-row.open { display:table-row; }
.upg-sub-cell { padding:0 !important; background:#f9fafb; }
.upg-ttn-table { width:100%; font-size:11px; border-collapse:collapse; background:#fff; }
.upg-ttn-table th, .upg-ttn-table td { padding:4px 8px; border-bottom:1px solid #f3f4f6; text-align:left; }
.upg-ttn-table tr:last-child td { border-bottom:none; }
.upg-ttn-table tr:hover td { background:#f9fafb; }
.upg-row-actions { display:flex; gap:4px; }
</style>

<div class="page-wrap-lg">
  <div class="upg-toolbar">
    <h1>Реєстри Укрпошти</h1>
    <button type="button" class="btn btn-primary" id="upgBtnCreate">+ Новий реєстр</button>
    <button type="button" class="btn btn-sm btn-ghost" id="upgBtnSync" title="Синхронізувати реєстри з Укрпошти">↻ Синхр.</button>
    <div style="flex:1"></div>
    <form method="get" action="<?php echo $curUrl; ?>" style="display:flex;gap:6px;align-items:center">
      <input type="text" name="search" value="<?php echo ViewHelper::h($search); ?>" placeholder="Назва або UUID…"
             style="padding:6px 10px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;height:34px">
      <select name="type" style="padding:6px 10px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;height:34px">
        <option value="">Усі типи</option>
        <option value="STANDARD"<?php echo $type==='STANDARD'?' selected':''; ?>>Стандарт</option>
        <option value="EXPRESS"<?php echo $type==='EXPRESS'?' selected':''; ?>>Експрес</option>
      </select>
      <button type="submit" class="btn btn-sm">Фільтр</button>
    </form>
  </div>

  <div class="filter-bar">
    <div class="filter-bar-group">
      <span class="filter-bar-label">Дата</span>
      <?php
        $todayActive     = ($dateFrom === $today     && $dateTo === $today);
        $yesterdayActive = ($dateFrom === $yesterday && $dateTo === $yesterday);
        $dateQs = function($from, $to) use ($search, $type, $closed) {
            return $from && $to
                ? '/ukrposhta/groups?' . http_build_query(array_filter(array('date_from'=>$from,'date_to'=>$to,'search'=>$search,'type'=>$type,'closed'=>$closed), 'strlen'))
                : '/ukrposhta/groups';
        };
      ?>
      <a href="<?php echo $dateQs($today, $today); ?>"         class="filter-pill <?php echo $todayActive?'active':''; ?>">Сьогодні</a>
      <a href="<?php echo $dateQs($yesterday, $yesterday); ?>" class="filter-pill <?php echo $yesterdayActive?'active':''; ?>">Вчора</a>
    </div>
    <div class="filter-bar-sep"></div>
    <div class="filter-bar-group">
      <span class="filter-bar-label">Стан</span>
      <?php
      $closedOptions = array(''=>'Усі','0'=>'Відкриті','1'=>'Закриті');
      foreach ($closedOptions as $val => $lbl):
          $active = ((string)$closed === (string)$val && $val !== '') ? ' active' : '';
          $q = array_filter(array('search'=>$search,'type'=>$type,'date_from'=>$dateFrom,'date_to'=>$dateTo,'closed'=>$val), 'strlen');
      ?>
        <a href="<?php echo $curUrl . '?' . http_build_query($q); ?>" class="filter-pill<?php echo $active; ?>"><?php echo $lbl; ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <div style="font-size:12px;color:#6b7280;margin-bottom:6px">
    Знайдено: <?php echo (int)$total; ?> реєстрів
  </div>

  <table class="crm-table">
    <thead>
      <tr>
        <th style="width:28px"></th>
        <th>Назва / Дата</th>
        <th style="text-align:right">ТТН</th>
        <th>Тип</th>
        <th style="text-align:right;background:#f0fdf4;color:#166534">Сума оголошена</th>
        <th style="text-align:right;background:#fff7ed;color:#9a3412">Накл. платіж</th>
        <th>Статус</th>
        <th style="width:220px"></th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="8" style="text-align:center;color:#9ca3af;padding:32px">Реєстрів не знайдено</td></tr>
    <?php else: ?>
      <?php foreach ($rows as $g):
        $uuid = $g['uuid'];
        $shortUuid = substr($uuid, 0, 8);
      ?>
        <tr data-uuid="<?php echo ViewHelper::h($uuid); ?>">
          <td>
            <button type="button" class="upg-expand-btn" data-uuid="<?php echo ViewHelper::h($uuid); ?>" title="Показати ТТН">▶</button>
          </td>
          <td class="fw-600">
            <div><?php echo ViewHelper::h($g['name']); ?></div>
            <div class="fs-12 text-muted"><?php echo $shortUuid; ?>… · <?php echo date('d.m.Y H:i', strtotime($g['created'])); ?></div>
          </td>
          <td style="text-align:right"><?php echo (int)$g['ttn_count']; ?></td>
          <td><span class="badge badge-gray"><?php echo ViewHelper::h($g['type']); ?></span></td>
          <td style="text-align:right;background:#f0fdf4" class="nowrap">
            <?php echo number_format((float)$g['total_cost'], 2, '.', ' '); ?> грн
          </td>
          <td style="text-align:right;background:#fff7ed" class="nowrap">
            <?php echo number_format((float)$g['total_postpay'], 2, '.', ' '); ?> грн
          </td>
          <td>
            <?php if ((int)$g['closed']): ?>
              <span class="badge badge-gray">Закритий</span>
            <?php else: ?>
              <span class="badge badge-blue">Відкритий</span>
            <?php endif; ?>
            <?php if ((int)$g['byCourier']): ?>
              <span class="badge badge-orange" title="Виклик кур'єра">Кур'єр</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="upg-row-actions">
              <button type="button" class="btn btn-xs btn-ghost upg-btn-print" data-uuid="<?php echo ViewHelper::h($uuid); ?>" title="Друк форми 103а">🖨 Друк</button>
              <?php if (!(int)$g['closed']): ?>
              <button type="button" class="btn btn-xs btn-ghost upg-btn-close" data-uuid="<?php echo ViewHelper::h($uuid); ?>" title="Закрити реєстр">Закрити</button>
              <?php else: ?>
              <button type="button" class="btn btn-xs btn-ghost upg-btn-reopen" data-uuid="<?php echo ViewHelper::h($uuid); ?>" title="Відкрити назад">Відкрити</button>
              <?php endif; ?>
              <button type="button" class="btn btn-xs btn-ghost upg-btn-delete" data-uuid="<?php echo ViewHelper::h($uuid); ?>" title="Видалити локально">✕</button>
            </div>
          </td>
        </tr>
        <tr class="upg-sub-row" id="upg-sub-<?php echo ViewHelper::h($uuid); ?>">
          <td colspan="8" class="upg-sub-cell">
            <div id="upg-sub-content-<?php echo ViewHelper::h($uuid); ?>" style="padding:8px 12px 8px 40px">
              <span style="color:#9ca3af;font-size:12px">Завантаження…</span>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>

  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php for ($p = max(1, $page-3); $p <= min($totalPages, $page+3); $p++): ?>
      <?php
        $qs = $_GET; $qs['page'] = $p;
        $url = '/ukrposhta/groups?' . http_build_query($qs);
      ?>
      <?php if ($p === $page): ?>
        <span class="cur"><?php echo $p; ?></span>
      <?php else: ?>
        <a href="<?php echo $url; ?>"><?php echo $p; ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    <span class="dots"><?php echo number_format($total, 0, '.', ' '); ?> реєстрів</span>
  </div>
  <?php endif; ?>
</div>

<script>
(function() {
    function postJson(url, data) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data || {}),
        }).then(function(r){ return r.json(); });
    }

    // Create
    document.getElementById('upgBtnCreate').addEventListener('click', function() {
        var name = prompt('Назва реєстру:', new Date().toLocaleString('uk-UA'));
        if (!name) return;
        var type = confirm('OK = STANDARD, Cancel = EXPRESS?') ? 'STANDARD' : 'EXPRESS';
        postJson('/ukrposhta/api/create_group', { name: name, type: type }).then(function(j) {
            if (j.ok) location.reload(); else alert('Помилка: ' + (j.error || '—'));
        });
    });

    // Sync
    document.getElementById('upgBtnSync').addEventListener('click', function() {
        var btn = this; btn.disabled = true;
        postJson('/ukrposhta/api/sync_groups', {}).then(function(j) {
            btn.disabled = false;
            if (j.ok) {
                alert('Синхронізовано ' + (j.synced_groups || 0) + ' реєстрів, ' + (j.synced_links || 0) + ' зв\'язків');
                location.reload();
            } else alert('Помилка: ' + (j.error || '—'));
        });
    });

    // Expand sub-row
    document.querySelectorAll('.upg-expand-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var uuid = btn.dataset.uuid;
            var subRow = document.getElementById('upg-sub-' + uuid);
            var content = document.getElementById('upg-sub-content-' + uuid);
            var isOpen = subRow.classList.toggle('open');
            btn.innerHTML = isOpen ? '▼' : '▶';
            if (isOpen && !content.dataset.loaded) {
                fetch('/ukrposhta/api/get_group_shipments?group_uuid=' + encodeURIComponent(uuid))
                    .then(function(r){ return r.json(); })
                    .then(function(j) {
                        if (!j.ok) { content.innerHTML = '<span style="color:#dc2626">' + (j.error || '—') + '</span>'; return; }
                        if (!j.rows.length) { content.innerHTML = '<span style="color:#9ca3af">Реєстр порожній</span>'; return; }
                        var html = '<table class="upg-ttn-table"><thead><tr>'
                                 + '<th>Трек</th><th>Одержувач</th><th>Місто</th><th>Статус</th>'
                                 + '<th>Оголош.</th><th>Наклад.</th><th></th></tr></thead><tbody>';
                        j.rows.forEach(function(t) {
                            html += '<tr>'
                                  + '<td style="font-family:monospace">' + (t.barcode || '—') + '</td>'
                                  + '<td>' + (t.recipient_name || '—') + '<br><span style="color:#9ca3af">' + (t.recipient_phoneNumber || '') + '</span></td>'
                                  + '<td>' + (t.recipient_city || '—') + '</td>'
                                  + '<td>' + (t.lifecycle_status || '—') + '</td>'
                                  + '<td>' + (parseFloat(t.declaredPrice) || 0) + ' ₴</td>'
                                  + '<td>' + (parseFloat(t.postPayUah) || 0) + ' ₴</td>'
                                  + '<td><button type="button" class="btn btn-xs btn-ghost" data-ttn-uuid="' + (t.uuid || '') + '" data-action="unlink">⊘ Відʼєднати</button></td>'
                                  + '</tr>';
                        });
                        html += '</tbody></table>';
                        content.innerHTML = html;
                        content.dataset.loaded = '1';
                        // Wire up unlink buttons in sub-row
                        content.querySelectorAll('[data-action="unlink"]').forEach(function(b) {
                            b.addEventListener('click', function() {
                                var uuid = b.dataset.ttnUuid;
                                if (!confirm('Відʼєднати ТТН від реєстру?')) return;
                                postJson('/ukrposhta/api/remove_from_group', { shipment_uuid: uuid })
                                    .then(function(j) {
                                        if (j.ok) { b.closest('tr').remove(); }
                                        else alert('Помилка: ' + (j.error || '—'));
                                    });
                            });
                        });
                    });
            }
        });
    });

    // Print
    document.querySelectorAll('.upg-btn-print').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var uuid = btn.dataset.uuid;
            window.open('/ukrposhta/api/print_registry?group_uuid=' + encodeURIComponent(uuid), '_blank');
        });
    });

    // Close / reopen / delete
    ['close','reopen','delete'].forEach(function(action) {
        document.querySelectorAll('.upg-btn-' + action).forEach(function(btn) {
            btn.addEventListener('click', function() {
                var uuid = btn.dataset.uuid;
                var msg = action === 'delete' ? 'Видалити реєстр локально?' : (action === 'close' ? 'Закрити реєстр?' : 'Відкрити знову?');
                if (!confirm(msg)) return;
                postJson('/ukrposhta/api/' + action + '_group', { group_uuid: uuid })
                    .then(function(j) {
                        if (j.ok) location.reload(); else alert('Помилка: ' + (j.error || '—'));
                    });
            });
        });
    });
})();
</script>
<?php require_once __DIR__ . '/../shared/layout_end.php'; ?>