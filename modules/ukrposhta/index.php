<?php
require_once __DIR__ . '/ukrposhta_bootstrap.php';
require_once __DIR__ . '/../auth/AuthService.php';
require_once __DIR__ . '/../shared/ShipmentStatus.php';
require_once __DIR__ . '/../../src/ViewHelper.php';

if (!\Papir\Crm\AuthService::getCurrentUser()) {
    header('Location: /login'); exit;
}

$activeNav = 'logistics';
$subNav    = 'up-ttns';
$title     = 'ТТН Укрпошта';

// ── Filters ──────────────────────────────────────────────────────────────────
$search     = isset($_GET['search'])      ? trim($_GET['search'])      : '';
$stateGroup = isset($_GET['state_group']) ? trim($_GET['state_group']) : '';
$dateFrom   = isset($_GET['date_from'])   ? trim($_GET['date_from'])   : '';
$dateTo     = isset($_GET['date_to'])     ? trim($_GET['date_to'])     : '';
$draft      = isset($_GET['draft'])       ? (int)$_GET['draft']        : 1; // default ON — draft first
$senderUuid = isset($_GET['sender_uuid']) ? trim($_GET['sender_uuid']) : '';
$inRegistry = isset($_GET['in_registry']) ? $_GET['in_registry']       : '';
$page       = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$limit      = 50;
$offset     = ($page - 1) * $limit;

$filters = array(
    'search'      => $search,
    'state_group' => $draft ? '' : $stateGroup,
    'date_from'   => $dateFrom,
    'date_to'     => $dateTo,
    'sender_uuid' => $senderUuid,
    'in_registry' => $inRegistry,
    'draft'       => $draft ? 1 : 0,
);

$data       = \Papir\Crm\UpTtnRepository::getList($filters, $limit, $offset);
$rows       = $data['rows'];
$total      = $data['total'];
$totalPages = $total > 0 ? (int)ceil($total / $limit) : 1;

// Глобальний лічильник чернеток (незалежно від поточного фільтра),
// щоб користувач бачив скільки ТТН ще треба передати перевізнику.
$draftCnt = 0;
$r = \Database::fetchRow('Papir',
    "SELECT COUNT(*) AS c FROM ttn_ukrposhta
     WHERE lifecycle_status IS NULL OR lifecycle_status IN ('CREATED','REGISTERED','UNKNOWN')");
if ($r['ok'] && !empty($r['row'])) $draftCnt = (int)$r['row']['c'];

$lifecycleLabels = \Papir\Crm\UpDefaults::lifecycleLabels();
$draftOldThreshold = strtotime('-2 days');
$curUrl = '/ukrposhta/ttns';

function upTtnPageUrl($p, $search, $stateGroup, $dateFrom, $dateTo, $draft, $senderUuid, $inRegistry = '') {
    $q = array('page' => $p);
    if ($search)                $q['search']      = $search;
    if ($stateGroup && !$draft) $q['state_group'] = $stateGroup;
    if ($dateFrom)              $q['date_from']   = $dateFrom;
    if ($dateTo)                $q['date_to']     = $dateTo;
    if (!$draft)                $q['draft']       = '0';
    if ($senderUuid)            $q['sender_uuid'] = $senderUuid;
    if ($inRegistry !== '')     $q['in_registry'] = $inRegistry;
    return '/ukrposhta/ttns?' . http_build_query($q);
}

require_once __DIR__ . '/../shared/layout.php';
?>
<link rel="stylesheet" href="/modules/shared/np-ttn-create-modal.css?v=<?php echo filemtime('/var/www/papir/modules/shared/np-ttn-create-modal.css'); ?>">
<style>
.up-toolbar { display:flex; align-items:center; gap:8px; margin-bottom:10px; }
.up-toolbar h1 { margin:0; font-size:18px; font-weight:700; flex-shrink:0; }
.up-search-wrap { flex:1; min-width:160px; }
.up-toolbar .btn { height:34px; padding:0 12px; }
.up-ttn-num { font-family: monospace; font-size: 12px; letter-spacing: 0.5px; }
.up-badges { display:inline-flex; gap:3px; vertical-align:middle; margin-left:4px; }
.up-badge-sm { display:inline-flex; align-items:center; justify-content:center;
               width:18px; height:18px; border-radius:3px; cursor:default; font-size:11px; }
.up-badge-sm.cod      { background:#fff7ed; color:#c2410c; }
.up-badge-sm.registry { background:#d1fae5; color:#059669; }
.up-badge-sm.printed  { background:#dbeafe; color:#2563eb; }

tr.ttn-draft-old > td { background:#fef3c7 !important; }
tr.ttn-draft-old > td:first-child { border-left:3px solid #f59e0b; }

.up-actions-wrap { position:relative; }
.up-act-btn { background:none; border:none; cursor:pointer; padding:2px 6px; color:#6b7280; font-size:15px; line-height:1; border-radius:3px; }
.up-act-btn:hover { background:#f3f4f6; color:#111; }
.up-actions-drop {
    position:fixed; z-index:1200; background:#fff; border:1px solid #d1d5db;
    border-radius:6px; box-shadow:0 4px 16px rgba(0,0,0,.13);
    min-width:200px; display:none; white-space:nowrap; overflow:hidden;
}
.up-actions-drop.open { display:block; }
.up-act-item { display:block; width:100%; text-align:left; padding:7px 14px; background:none; border:none; cursor:pointer; font-size:13px; color:#111; line-height:1.4; }
.up-act-item:hover { background:#f3f4f6; }
.up-act-item.danger { color:#dc2626; }
.up-act-item.danger:hover { background:#fef2f2; }
.up-act-sep { border:none; border-top:1px solid #e5e7eb; margin:3px 0; }
.up-act-sub-label { display:block; padding:4px 14px 2px; font-size:11px; color:#6b7280; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }

.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1500; align-items:flex-start; justify-content:center; padding-top:40px; }
.modal-overlay.open { display:flex; }
.modal-box { background:#fff; border-radius:8px; box-shadow:0 10px 40px rgba(0,0,0,.25); max-width:98vw; }
.modal-head { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-bottom:1px solid #e5e7eb; font-weight:600; font-size:14px; }
.modal-close { background:none; border:none; cursor:pointer; font-size:16px; color:#6b7280; }
.modal-close:hover { color:#111; }
</style>

<div class="page-wrap-lg">

  <!-- Toolbar -->
  <form method="get" action="<?php echo $curUrl; ?>" id="upFilterForm">
    <div class="up-toolbar">
      <h1>ТТН Укрпошта</h1>
      <button type="button" class="btn btn-sm btn-primary" id="upBtnNew" title="Створити ТТН Укрпошти">
        + Нова ТТН
      </button>
      <div class="up-search-wrap">
        <input type="text" name="search" value="<?php echo ViewHelper::h($search); ?>"
               placeholder="Трек-номер, замовлення, ПІБ, телефон, місто…"
               style="width:100%;padding:6px 10px;border:1px solid #d1d5db;border-radius:4px;height:34px;font-size:13px;">
      </div>
      <?php if ($stateGroup && !$draft): ?><input type="hidden" name="state_group" value="<?php echo ViewHelper::h($stateGroup); ?>"><?php endif; ?>
      <?php if ($dateFrom):   ?><input type="hidden" name="date_from"   value="<?php echo ViewHelper::h($dateFrom); ?>"><?php endif; ?>
      <?php if ($dateTo):     ?><input type="hidden" name="date_to"     value="<?php echo ViewHelper::h($dateTo); ?>"><?php endif; ?>
      <?php if (!$draft):     ?><input type="hidden" name="draft"       value="0"><?php endif; ?>
      <?php if ($senderUuid): ?><input type="hidden" name="sender_uuid" value="<?php echo ViewHelper::h($senderUuid); ?>"><?php endif; ?>
      <?php if ($inRegistry !== ''): ?><input type="hidden" name="in_registry" value="<?php echo ViewHelper::h($inRegistry); ?>"><?php endif; ?>
      <button type="submit" class="btn btn-sm btn-ghost">Фільтр</button>
    </div>
  </form>

  <!-- Filter bar -->
  <div class="filter-bar">
    <div class="filter-bar-group">
      <label class="filter-pill <?php echo $draft ? 'active' : ''; ?>" id="draftPill" style="cursor:pointer;user-select:none">
        <input type="checkbox" id="draftCb" <?php echo $draft ? 'checked' : ''; ?> style="margin-right:4px;vertical-align:middle">
        Чернетки
        <?php if ($draftCnt > 0): ?>
          <span style="background:#f59e0b;color:#fff;font-size:10px;padding:1px 6px;border-radius:8px;margin-left:4px;font-weight:600"><?php echo $draftCnt; ?></span>
        <?php endif; ?>
      </label>
    </div>
    <div class="filter-bar-sep"></div>
    <div class="filter-bar-group" <?php echo $draft ? 'style="opacity:.35;pointer-events:none"' : ''; ?>>
      <span class="filter-bar-label">Статус</span>
      <?php
      // Мапимо канонічні статуси Папір (ShipmentStatus) на UP lifecycle-групи.
      $stateOptions = array(
          ''         => 'Усі',
          'transit'  => 'В дорозі',
          'branch'   => 'На відділенні',
          'received' => 'Отримано',
          'return'   => 'Повертається',
          'returned' => 'Повернення отримано',
          'cancel'   => 'Скасовано',
      );
      foreach ($stateOptions as $val => $lbl):
        $active = (!$draft && $stateGroup === $val) ? ' active' : '';
        $q = array('page' => 1, 'draft' => '0');
        if ($search)     $q['search']      = $search;
        if ($val !== '') $q['state_group'] = $val;
        if ($dateFrom)   $q['date_from']   = $dateFrom;
        if ($dateTo)     $q['date_to']     = $dateTo;
        if ($senderUuid) $q['sender_uuid'] = $senderUuid;
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
        $dateQs = function($from, $to) use ($search, $stateGroup, $draft, $senderUuid) {
            $q = array('page'=>1, 'date_from'=>$from, 'date_to'=>$to);
            if ($search)                $q['search']      = $search;
            if ($stateGroup && !$draft) $q['state_group'] = $stateGroup;
            if (!$draft)                $q['draft']       = '0';
            if ($senderUuid)            $q['sender_uuid'] = $senderUuid;
            return '/ukrposhta/ttns?' . http_build_query($q);
        };
      ?>
      <a href="<?php echo $dateQs($today, $today); ?>"         class="filter-pill<?php echo $todayActive     ? ' active':''; ?>">Сьогодні</a>
      <a href="<?php echo $dateQs($yesterday, $yesterday); ?>" class="filter-pill<?php echo $yesterdayActive ? ' active':''; ?>">Вчора</a>
      <form method="get" action="<?php echo $curUrl; ?>" style="display:inline-flex;gap:4px;align-items:center;margin-left:4px">
        <?php if ($stateGroup && !$draft): ?><input type="hidden" name="state_group" value="<?php echo ViewHelper::h($stateGroup); ?>"><?php endif; ?>
        <?php if ($search):     ?><input type="hidden" name="search"      value="<?php echo ViewHelper::h($search); ?>"><?php endif; ?>
        <?php if (!$draft):     ?><input type="hidden" name="draft"       value="0"><?php endif; ?>
        <?php if ($senderUuid): ?><input type="hidden" name="sender_uuid" value="<?php echo ViewHelper::h($senderUuid); ?>"><?php endif; ?>
        <input type="hidden" name="page" value="1">
        <input type="date" name="date_from" value="<?php echo ViewHelper::h($dateFrom); ?>" style="font-size:12px;border:1px solid #d1d5db;border-radius:4px;padding:3px 6px;height:28px">
        <span style="font-size:11px;color:#9ca3af">—</span>
        <input type="date" name="date_to"   value="<?php echo ViewHelper::h($dateTo); ?>"   style="font-size:12px;border:1px solid #d1d5db;border-radius:4px;padding:3px 6px;height:28px">
        <button type="submit" class="btn btn-sm" style="height:28px;padding:0 8px">OK</button>
        <?php if ($dateFrom || $dateTo): ?>
          <?php
            $resetQ = array('page'=>1);
            if ($search)                $resetQ['search']      = $search;
            if ($stateGroup && !$draft) $resetQ['state_group'] = $stateGroup;
            if (!$draft)                $resetQ['draft']       = '0';
            if ($senderUuid)            $resetQ['sender_uuid'] = $senderUuid;
          ?>
          <a href="<?php echo $curUrl . '?' . http_build_query($resetQ); ?>" class="btn btn-sm btn-ghost" style="height:28px;padding:0 8px">✕</a>
        <?php endif; ?>
      </form>
    </div>
    <div class="filter-bar-sep"></div>
    <div class="filter-bar-group">
      <span class="filter-bar-label">Реєстр</span>
      <?php
      $regOptions = array('' => 'Усі', '1' => 'У реєстрі', '0' => 'Без реєстру');
      foreach ($regOptions as $val => $lbl):
          $active = ($inRegistry === $val && $val !== '') ? ' active' : '';
          $q = array_filter(array(
              'page'       => 1,
              'search'     => $search,
              'state_group'=> (!$draft ? $stateGroup : ''),
              'date_from'  => $dateFrom,
              'date_to'    => $dateTo,
              'draft'      => $draft ? null : '0',
              'sender_uuid'=> $senderUuid,
              'in_registry'=> $val,
          ), 'strlen');
      ?>
        <a href="<?php echo $curUrl . '?' . http_build_query($q); ?>"
           class="filter-pill<?php echo $active; ?>"><?php echo ViewHelper::h($lbl); ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Count -->
  <div style="font-size:12px;color:#6b7280;margin-bottom:6px">
    Знайдено: <?php echo (int)$total; ?> ТТН
  </div>

  <!-- Table -->
  <table class="crm-table">
    <thead>
      <tr>
        <th>Трек-номер</th>
        <th>Замовлення</th>
        <th>Одержувач</th>
        <th>Місто / Відділення</th>
        <th>Статус</th>
        <th>Накл.</th>
        <th>Оголош.</th>
        <th>Кг</th>
        <th>Тип</th>
        <th>Дата</th>
        <th style="width:32px"></th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="11" style="text-align:center;color:#9ca3af;padding:32px">ТТН не знайдено</td></tr>
    <?php else: ?>
      <?php foreach ($rows as $row):
          $ts = $row['created_date'] ? strtotime($row['created_date']) : ($row['lifecycle_statusDate'] ? strtotime($row['lifecycle_statusDate']) : time());
          $isDraftOld = $draft && $ts < $draftOldThreshold;
          $lifecycle  = (string)$row['lifecycle_status'];
          // Канонічний статус Папір — основний badge
          $canon      = \ShipmentStatus::fromUp($lifecycle);
          $canonLabel = \ShipmentStatus::label($canon);
          $badgeCls   = \ShipmentStatus::badgeClass($canon);
          // Детальний lifecycle UP — в tooltip (інформаційно)
          $lifeLabel  = isset($lifecycleLabels[$lifecycle]) ? $lifecycleLabels[$lifecycle] : ($lifecycle ?: '—');
          $tooltip    = $lifeLabel;
          if (!empty($row['state_description']) && $row['state_description'] !== $lifeLabel) {
              $tooltip .= ' · ' . $row['state_description'];
          }
          $inReg      = \Papir\Crm\UpGroupLinkRepository::getGroupUuid($row['uuid']);
      ?>
        <tr<?php if ($isDraftOld): ?> class="ttn-draft-old"<?php endif; ?>
            data-ttn-id="<?php echo (int)$row['id']; ?>"
            data-ttn-uuid="<?php echo ViewHelper::h($row['uuid']); ?>"
            data-barcode="<?php echo ViewHelper::h($row['barcode']); ?>">
          <td class="up-ttn-num">
            <?php if ($row['barcode']): ?>
              <?php if ($row['label']): ?>
              <a href="<?php echo ViewHelper::h($row['label']); ?>" target="_blank" style="text-decoration:none;color:inherit">
                <?php echo ViewHelper::h($row['barcode']); ?>
              </a>
              <?php else: ?>
              <?php echo ViewHelper::h($row['barcode']); ?>
              <?php endif; ?>
              <span class="up-badges">
                <?php if ((float)$row['postPayUah'] > 0): ?>
                  <span class="up-badge-sm cod" title="Накладений платіж <?php echo number_format((float)$row['postPayUah'], 0, '.', ' '); ?> грн">₴</span>
                <?php endif; ?>
                <?php if ($row['label']): ?>
                  <span class="up-badge-sm printed" title="Стікер збережено">🖨</span>
                <?php endif; ?>
                <?php if ($inReg): ?>
                  <span class="up-badge-sm registry" title="У реєстрі">⦿</span>
                <?php endif; ?>
              </span>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($row['customerorder_id']): ?>
              <a href="/customerorder/edit?id=<?php echo (int)$row['customerorder_id']; ?>" target="_blank" class="fs-12">
                #<?php echo (int)$row['customerorder_id']; ?>
              </a>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td>
            <div><?php echo ViewHelper::h($row['recipient_name'] ?: '—'); ?></div>
            <?php if ($row['recipient_phoneNumber']): ?>
              <div class="fs-12 text-muted"><?php echo ViewHelper::h($row['recipient_phoneNumber']); ?></div>
            <?php endif; ?>
          </td>
          <td>
            <div class="fs-12"><?php echo ViewHelper::h($row['recipient_city'] ?: '—'); ?></div>
            <?php if ($row['postcode']): ?>
              <div class="fs-12 text-muted"><?php echo ViewHelper::h($row['postcode']); ?></div>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge <?php echo $badgeCls; ?>" title="<?php echo ViewHelper::h($tooltip); ?>">
              <?php echo ViewHelper::h($canonLabel); ?>
            </span>
            <?php if ($canon !== 'draft' && $lifeLabel && $lifeLabel !== $canonLabel): ?>
              <div class="fs-12 text-muted" style="margin-top:2px"><?php echo ViewHelper::h($lifeLabel); ?></div>
            <?php endif; ?>
          </td>
          <td class="nowrap">
            <?php echo (float)$row['postPayUah'] > 0
                ? '<span class="fw-600">₴' . number_format((float)$row['postPayUah'], 0, '.', ' ') . '</span>'
                : '<span class="text-muted">—</span>'; ?>
          </td>
          <td class="fs-12 text-muted nowrap">
            <?php echo (float)$row['declaredPrice'] > 0 ? '₴' . number_format((float)$row['declaredPrice'], 0, '.', ' ') : '—'; ?>
          </td>
          <td class="fs-12"><?php echo $row['weight'] ? round((float)$row['weight'] / 1000, 2) : '—'; ?></td>
          <td class="fs-12 text-muted"><?php echo ViewHelper::h($row['deliveryType'] ?: ''); ?> · <?php echo ViewHelper::h($row['type'] ?: ''); ?></td>
          <td class="fs-12 text-muted nowrap">
            <?php echo date('d.m.Y', $ts); ?>
            <?php if ($isDraftOld): ?>
              <span style="color:#b45309;font-size:11px">(<?php echo floor((time() - $ts) / 86400); ?>д)</span>
            <?php endif; ?>
          </td>
          <td style="padding:4px 6px">
            <div class="up-actions-wrap">
              <button type="button" class="up-act-btn" title="Дії">&#8942;</button>
              <div class="up-actions-drop">
                <?php if ($row['barcode']): ?>
                <button type="button" class="up-act-item up-act-print" data-ttn-id="<?php echo (int)$row['id']; ?>">🖨 Друк стікера</button>
                <button type="button" class="up-act-item up-act-track" data-ttn-id="<?php echo (int)$row['id']; ?>">↻ Оновити статус</button>
                <?php endif; ?>
                <button type="button" class="up-act-item up-act-edit"    data-ttn-id="<?php echo (int)$row['id']; ?>">✎ Редагувати</button>
                <button type="button" class="up-act-item up-act-refresh" data-ttn-id="<?php echo (int)$row['id']; ?>">↺ Перечитати з API</button>
                <?php if ($inReg): ?>
                <button type="button" class="up-act-item up-act-unlink" data-ttn-uuid="<?php echo ViewHelper::h($row['uuid']); ?>">⊘ Відʼєднати від реєстру</button>
                <?php else: ?>
                <button type="button" class="up-act-item up-act-link" data-barcode="<?php echo ViewHelper::h($row['barcode']); ?>">⦿ Додати в реєстр</button>
                <?php endif; ?>
                <hr class="up-act-sep">
                <button type="button" class="up-act-item danger up-act-delete" data-ttn-id="<?php echo (int)$row['id']; ?>">Видалити ТТН</button>
              </div>
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
      <a href="<?php echo upTtnPageUrl($page-1, $search, $stateGroup, $dateFrom, $dateTo, $draft, $senderUuid, $inRegistry); ?>">&laquo;</a>
    <?php endif; ?>
    <?php for ($p = max(1, $page-3); $p <= min($totalPages, $page+3); $p++): ?>
      <?php if ($p === $page): ?>
        <span class="cur"><?php echo $p; ?></span>
      <?php else: ?>
        <a href="<?php echo upTtnPageUrl($p, $search, $stateGroup, $dateFrom, $dateTo, $draft, $senderUuid, $inRegistry); ?>"><?php echo $p; ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
      <a href="<?php echo upTtnPageUrl($page+1, $search, $stateGroup, $dateFrom, $dateTo, $draft, $senderUuid, $inRegistry); ?>">&raquo;</a>
    <?php endif; ?>
    <span class="dots"><?php echo number_format($total, 0, '.', ' '); ?> ТТН</span>
  </div>
  <?php endif; ?>
</div>

<!-- ═══ TTN UP CREATE/EDIT MODAL ═══ -->
<div class="modal-overlay" id="upTtnModal">
    <div class="modal-box" style="width:560px;">
        <div class="modal-head">
            <span id="upTtnModalTitle">Нова ТТН Укрпошта</span>
            <button class="modal-close" id="upTtnModalClose">&#x2715;</button>
        </div>
        <div class="modal-body" id="upTtnBody" style="overflow-y:auto; max-height:calc(100vh - 180px); padding:14px 16px;">
            <div style="text-align:center; color:#9ca3af; padding:30px;">Завантаження…</div>
        </div>
    </div>
</div>

<script src="/modules/shared/up-ttn-create-modal.js?v=<?php echo @filemtime('/var/www/papir/modules/shared/up-ttn-create-modal.js') ?: time(); ?>"></script>
<script>
(function() {
    // Draft toggle auto-submit
    var draftCb = document.getElementById('draftCb');
    if (draftCb) {
        draftCb.addEventListener('change', function() {
            var form = document.getElementById('upFilterForm');
            var existing = form.querySelector('input[name="draft"]');
            if (existing) existing.remove();
            if (!this.checked) {
                var h = document.createElement('input');
                h.type = 'hidden'; h.name = 'draft'; h.value = '0';
                form.appendChild(h);
            }
            form.submit();
        });
    }

    // "+ Нова ТТН" button
    var btnNew = document.getElementById('upBtnNew');
    if (btnNew) {
        btnNew.addEventListener('click', function() {
            if (window.UpTtnCreateModal) window.UpTtnCreateModal.open(0);
        });
    }

    // Actions dropdown
    document.querySelectorAll('.up-actions-wrap').forEach(function(wrap) {
        var btn  = wrap.querySelector('.up-act-btn');
        var drop = wrap.querySelector('.up-actions-drop');
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            document.querySelectorAll('.up-actions-drop.open').forEach(function(d) { if (d !== drop) d.classList.remove('open'); });
            if (drop.classList.toggle('open')) {
                var r = btn.getBoundingClientRect();
                drop.style.top  = (r.bottom + 4) + 'px';
                drop.style.left = (r.right - 200) + 'px';
            }
        });
    });
    document.addEventListener('click', function() {
        document.querySelectorAll('.up-actions-drop.open').forEach(function(d) { d.classList.remove('open'); });
    });

    function postForm(url, data) {
        var fd = new FormData();
        Object.keys(data).forEach(function(k) { fd.append(k, data[k]); });
        return fetch(url, { method: 'POST', body: fd }).then(function(r) { return r.json(); });
    }

    document.addEventListener('click', function(e) {
        var btn;

        if ((btn = e.target.closest('.up-act-print'))) {
            var id = btn.dataset.ttnId;
            window.open('/ukrposhta/api/print_sticker?ttn_id=' + id, '_blank');
            return;
        }
        if ((btn = e.target.closest('.up-act-track')) || (btn = e.target.closest('.up-act-refresh'))) {
            var isTrack = btn.classList.contains('up-act-track');
            var id = btn.dataset.ttnId;
            btn.disabled = true;
            postForm(isTrack ? '/ukrposhta/api/track_ttn' : '/ukrposhta/api/refresh_ttn', { ttn_id: id })
                .then(function(j) { btn.disabled = false; if (j.ok) location.reload(); else alert('Помилка: ' + (j.error || '—')); });
            return;
        }
        if ((btn = e.target.closest('.up-act-edit'))) {
            var id = btn.dataset.ttnId;
            if (window.UpTtnCreateModal) window.UpTtnCreateModal.open(0, { editTtnId: parseInt(id, 10) });
            return;
        }
        if ((btn = e.target.closest('.up-act-delete'))) {
            var id = btn.dataset.ttnId;
            if (!confirm('Видалити ТТН? Це видалить її на сервері Укрпошти.')) return;
            postForm('/ukrposhta/api/delete_ttn', { ttn_id: id })
                .then(function(j) {
                    if (j.ok) {
                        var tr = btn.closest('tr'); if (tr) tr.remove();
                    } else alert('Помилка: ' + (j.error || '—'));
                });
            return;
        }
        if ((btn = e.target.closest('.up-act-link'))) {
            var barcode = btn.dataset.barcode;
            if (!barcode) return;
            fetch('/ukrposhta/api/add_to_group', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ barcode: barcode })
            }).then(function(r){ return r.json(); }).then(function(j) {
                if (j.ok) location.reload(); else alert('Помилка: ' + (j.error || '—'));
            });
            return;
        }
        if ((btn = e.target.closest('.up-act-unlink'))) {
            var uuid = btn.dataset.ttnUuid;
            if (!confirm('Відʼєднати від реєстру?')) return;
            fetch('/ukrposhta/api/remove_from_group', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ shipment_uuid: uuid })
            }).then(function(r){ return r.json(); }).then(function(j) {
                if (j.ok) location.reload(); else alert('Помилка: ' + (j.error || '—'));
            });
            return;
        }
    });
})();
</script>

<?php require_once __DIR__ . '/../shared/layout_end.php'; ?>