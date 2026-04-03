<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

$search     = isset($_GET['search'])      ? trim($_GET['search'])      : '';
$senderRef  = isset($_GET['sender_ref'])  ? trim($_GET['sender_ref'])  : '';
$stateGroup = isset($_GET['state_group']) ? trim($_GET['state_group']) : '';
$dateFrom   = isset($_GET['date_from'])   ? trim($_GET['date_from'])   : '';
$dateTo     = isset($_GET['date_to'])     ? trim($_GET['date_to'])     : '';
$draft      = isset($_GET['draft'])       ? (int)$_GET['draft']        : 1;
$page       = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$limit      = 50;
$offset     = ($page - 1) * $limit;

$filters = array(
    'search'      => $search,
    'sender_ref'  => $senderRef,
    'state_group' => $draft ? '' : $stateGroup,
    'date_from'   => $dateFrom,
    'date_to'     => $dateTo,
);
if ($draft) {
    $filters['state_define'] = 1;
    $filters['draft_sort']   = 1;
}

$data       = \Papir\Crm\TtnRepository::getList($filters, $limit, $offset);
$rows       = $data['rows'];
$total      = $data['total'];
$totalPages = $total > 0 ? (int)ceil($total / $limit) : 1;
$draftOldThreshold = strtotime('-2 days');

function npScApi($sd) {
    $map = array(
        1=>'badge-gray', 2=>'badge-gray', 3=>'badge-gray',
        4=>'badge-blue', 5=>'badge-blue', 6=>'badge-blue',
        7=>'badge-orange', 8=>'badge-orange',
        9=>'badge-green',
        10=>'badge-orange', 11=>'badge-gray',
        41=>'badge-blue',
        101=>'badge-blue', 102=>'badge-red', 103=>'badge-orange',
        104=>'badge-blue', 105=>'badge-orange', 106=>'badge-red',
    );
    return isset($map[$sd]) ? $map[$sd] : 'badge-gray';
}

function npTablePageUrl($p, $search, $stateGroup, $dateFrom, $dateTo, $draft, $senderRef) {
    $q = array('page' => $p);
    if ($search)                $q['search']      = $search;
    if ($stateGroup && !$draft) $q['state_group'] = $stateGroup;
    if ($dateFrom)              $q['date_from']   = $dateFrom;
    if ($dateTo)                $q['date_to']     = $dateTo;
    if (!$draft)                $q['draft']       = '0';
    if ($senderRef)             $q['sender_ref']  = $senderRef;
    return '/novaposhta/ttns?' . http_build_query($q);
}

// ── Rows HTML ───────────────────────────────────────────────────────────────
ob_start();
if (empty($rows)): ?>
  <tr><td colspan="12" style="text-align:center;color:#9ca3af;padding:32px">ТТН не знайдено</td></tr>
<?php else:
  foreach ($rows as $row):
    $isDraftOld = $draft && $row['moment'] && strtotime($row['moment']) < $draftOldThreshold;
    $cls = npScApi((int)$row['state_define']);
?>
    <tr<?php echo $isDraftOld ? ' class="ttn-draft-old"' : ''; ?>
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
            <button type="button" class="ttn-act-item ttn-act-print" data-format="100x100">🖨 Термо 100×100</button>
            <button type="button" class="ttn-act-item ttn-act-print" data-format="a4_6">🖨 A4 / 6 на аркуші</button>
            <hr class="ttn-act-sep">
            <button type="button" class="ttn-act-item danger ttn-act-delete">Видалити ТТН</button>
          </div>
        </div>
      </td>
    </tr>
<?php
  endforeach;
endif;
$rowsHtml = ob_get_clean();

// ── Pagination HTML ─────────────────────────────────────────────────────────
ob_start();
if ($totalPages > 1):
    if ($page > 1): ?>
      <a href="<?php echo npTablePageUrl($page-1, $search, $stateGroup, $dateFrom, $dateTo, $draft, $senderRef); ?>" data-page="<?php echo $page-1; ?>">&laquo;</a>
    <?php endif;
    for ($p = max(1, $page-3); $p <= min($totalPages, $page+3); $p++):
        if ($p === $page): ?>
          <span class="cur"><?php echo $p; ?></span>
        <?php else: ?>
          <a href="<?php echo npTablePageUrl($p, $search, $stateGroup, $dateFrom, $dateTo, $draft, $senderRef); ?>" data-page="<?php echo $p; ?>"><?php echo $p; ?></a>
        <?php endif;
    endfor;
    if ($page < $totalPages): ?>
      <a href="<?php echo npTablePageUrl($page+1, $search, $stateGroup, $dateFrom, $dateTo, $draft, $senderRef); ?>" data-page="<?php echo $page+1; ?>">&raquo;</a>
    <?php endif; ?>
    <span class="dots"><?php echo number_format($total, 0, '.', ' '); ?> ТТН</span>
<?php endif;
$paginationHtml = ob_get_clean();

echo json_encode(array(
    'ok'              => true,
    'rows_html'       => $rowsHtml,
    'pagination_html' => $paginationHtml,
    'total'           => $total,
    'total_pages'     => $totalPages,
));