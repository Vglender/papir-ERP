<?php
/**
 * Printable scan sheet — NP-style layout with per-TTN barcodes (JsBarcode CODE128)
 * GET /novaposhta/print/scansheet?ref=X&sender_ref=Y
 */
require_once __DIR__ . '/novaposhta_bootstrap.php';

$ref       = isset($_GET['ref'])        ? trim($_GET['ref'])        : '';
$senderRef = isset($_GET['sender_ref']) ? trim($_GET['sender_ref']) : '';

if (!$ref) { echo '<p>ref required</p>'; exit; }

$eRef = \Database::escape('Papir', $ref);

$rSs = \Database::fetchRow('Papir',
    "SELECT ss.*,
            s.Description          AS sender_name,
            s.CityDescription      AS sender_city,
            s.FirstName            AS sender_first,
            s.LastName             AS sender_last,
            s.MiddleName           AS sender_middle,
            s.CounterpartyFullName AS sender_full_name
     FROM np_scan_sheets ss
     LEFT JOIN np_sender s ON s.Ref = ss.sender_ref
     WHERE ss.Ref = '{$eRef}' LIMIT 1");

if (!$rSs['ok'] || !$rSs['row']) {
    echo '<p>Реєстр не знайдено</p>'; exit;
}
$ss = $rSs['row'];

// ── Load TTNs ──────────────────────────────────────────────────────────────
$ttns     = array();
$note     = '';
$noteFull = false;

$ttnFields = "ref, int_doc_number, recipient_contact_person, city_recipient_desc,
              recipients_phone, cost, backward_delivery_money,
              weight, seats_amount, service_type";

// 1. Primary: TTNs linked by scan_sheet_ref
$rTtns = \Database::fetchAll('Papir',
    "SELECT {$ttnFields}
     FROM ttn_novaposhta
     WHERE scan_sheet_ref = '{$eRef}' AND deletion_mark = 0
     ORDER BY int_doc_number");

if ($rTtns['ok'] && !empty($rTtns['rows'])) {
    $ttns = $rTtns['rows'];
}

// 2. If DB count < expected — supplement via NP API (scan sheet created externally
//    or scan_sheet_ref was never written for some TTNs)
$expectedCount = (int)$ss['Count'];
if (count($ttns) < $expectedCount && $ss['sender_ref']) {
    $sender = \Papir\Crm\SenderRepository::getByRef($ss['sender_ref']);
    if ($sender) {
        $np      = new \Papir\Crm\NovaPoshta($sender['api']);
        $rDetail = $np->call('ScanSheet', 'getScanSheet', array('Ref' => $ref));
        if ($rDetail['ok'] && !empty($rDetail['data'][0]['DocumentRefs'])) {
            // Collect all refs from NP API
            $apiRefs = array();
            foreach ($rDetail['data'][0]['DocumentRefs'] as $d) {
                $r = is_array($d) ? (isset($d['Ref']) ? $d['Ref'] : '') : (string)$d;
                if ($r) $apiRefs[] = $r;
            }

            // Find which refs are already in $ttns (by ref field)
            $linkedRefs = array();
            foreach ($ttns as $t) {
                if (!empty($t['ref'])) $linkedRefs[$t['ref']] = true;
            }

            // Missing = in NP API but not yet in $ttns
            $missingRefs = array();
            foreach ($apiRefs as $r) {
                if (!isset($linkedRefs[$r])) $missingRefs[] = $r;
            }

            if (!empty($missingRefs)) {
                // Build safe IN list
                $inList = implode("','", array_map(function($r) {
                    return \Database::escape('Papir', $r);
                }, $missingRefs));

                $rMissing = \Database::fetchAll('Papir',
                    "SELECT {$ttnFields}
                     FROM ttn_novaposhta
                     WHERE ref IN ('{$inList}') AND deletion_mark = 0");

                if ($rMissing['ok'] && !empty($rMissing['rows'])) {
                    // Fix scan_sheet_ref for future calls
                    \Database::query('Papir',
                        "UPDATE ttn_novaposhta SET scan_sheet_ref = '{$eRef}'
                         WHERE ref IN ('{$inList}') AND deletion_mark = 0");

                    $ttns = array_merge($ttns, $rMissing['rows']);
                    $note = 'Частину ТТН підтягнуто з API НП (scan_sheet_ref не було проставлено)';
                }
            }

            // Sort merged list by int_doc_number
            usort($ttns, function($a, $b) {
                return strcmp($a['int_doc_number'], $b['int_doc_number']);
            });

            // Note if we still have fewer than expected
            if (count($ttns) < $expectedCount) {
                $note = ($note ? $note . '. ' : '') .
                        'Деякі ТТН відсутні в нашій системі (створені поза CRM)';
            }
        }
    }
}

// 3. Zero results fallback: match by sender + date (historical scan sheets)
if (empty($ttns) && $ss['DateTime'] && $ss['sender_ref']) {
    $ssDate  = date('Y-m-d', strtotime($ss['DateTime']));
    $eSender = \Database::escape('Papir', $ss['sender_ref']);
    $rFb = \Database::fetchAll('Papir',
        "SELECT {$ttnFields}
         FROM ttn_novaposhta
         WHERE sender_ref = '{$eSender}' AND scan_sheet_ref IS NULL
           AND deletion_mark = 0 AND DATE(moment) = '{$ssDate}'
         ORDER BY int_doc_number");
    if ($rFb['ok'] && !empty($rFb['rows'])) {
        $ttns = $rFb['rows'];
        $note = 'Список ТТН підібрано за датою реєстру (приблизно)';
    }
}

// ── Helpers ────────────────────────────────────────────────────────────────
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function fmtMoney($v) {
    if ($v === null || $v === '') return '—';
    $n = (float)$v;
    return $n > 0 ? number_format($n, 2, '.', ' ') : '—';
}

function serviceLabel($type) {
    $map = array(
        'WarehouseWarehouse' => 'В→В',
        'DoorsWarehouse'     => 'Д→В',
        'WarehouseDoors'     => 'В→Д',
        'DoorsDoors'         => 'Д→Д',
    );
    return isset($map[$type]) ? $map[$type] : '';
}

// ── Totals ─────────────────────────────────────────────────────────────────
$totalCost   = 0.0;
$totalRedel  = 0.0;
$totalWeight = 0.0;
foreach ($ttns as $t) {
    $totalCost   += $t['cost']                   ? (float)$t['cost']                   : 0;
    $totalRedel  += $t['backward_delivery_money'] ? (float)$t['backward_delivery_money'] : 0;
    $totalWeight += $t['weight']                  ? (float)$t['weight']                  : 0;
}

$senderDisplay = $ss['sender_full_name']
    ? $ss['sender_full_name']
    : $ss['sender_name'];
?><!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="UTF-8">
<title>Реєстр <?php echo h($ss['Number'] ?: $ss['Ref']); ?></title>
<script src="/assets/js/JsBarcode.all.min.js"></script>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
      font-family: Arial, sans-serif;
      font-size: 11px;
      color: #111;
      background: #fff;
  }

  /* ── Screen controls ──────────────────────────────────────────────── */
  .no-print {
      position: fixed;
      top: 0; left: 0; right: 0;
      background: #1e3a5f;
      color: #fff;
      padding: 8px 16px;
      display: flex;
      align-items: center;
      gap: 10px;
      z-index: 100;
      font-size: 13px;
  }
  .no-print button {
      padding: 5px 16px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 13px;
  }
  .no-print .btn-print { background: #fff; color: #1e3a5f; font-weight: 700; }
  .no-print .btn-close  { background: rgba(255,255,255,.15); color: #fff; }
  .no-print .title { font-weight: 600; flex: 1; }

  /* ── Document wrapper ─────────────────────────────────────────────── */
  .doc {
      margin-top: 46px; /* push below fixed toolbar */
      padding: 14px 16px 24px;
      max-width: 900px;
  }

  /* ── Header ───────────────────────────────────────────────────────── */
  .doc-header {
      border: 2px solid #d1180b;
      border-radius: 4px;
      margin-bottom: 12px;
      overflow: hidden;
  }
  .doc-header-top {
      background: #d1180b;
      color: #fff;
      display: flex;
      align-items: center;
      padding: 6px 12px;
      gap: 16px;
  }
  .np-logo {
      font-size: 15px;
      font-weight: 900;
      letter-spacing: .5px;
      flex-shrink: 0;
  }
  .doc-title {
      font-size: 15px;
      font-weight: 700;
      flex: 1;
  }
  .doc-status {
      font-size: 11px;
      background: rgba(255,255,255,.2);
      padding: 2px 8px;
      border-radius: 10px;
  }
  .doc-header-meta {
      display: flex;
      flex-wrap: nowrap;
      gap: 0;
      padding: 0;
      align-items: stretch;
  }
  .meta-cells {
      display: flex;
      flex-wrap: wrap;
      flex: 1;
  }
  .meta-cell {
      padding: 6px 14px;
      border-right: 1px solid #e5e7eb;
  }
  .meta-label { font-size: 10px; color: #6b7280; margin-bottom: 2px; }
  .meta-value { font-weight: 600; font-size: 12px; }

  /* штрих-код самого реєстру — правий блок шапки */
  .ss-barcode-wrap {
      flex-shrink: 0;
      padding: 8px 14px;
      border-left: 2px solid #d1180b;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      background: #fff;
      min-width: 180px;
  }
  .ss-barcode-num {
      font-size: 13px;
      font-weight: 700;
      letter-spacing: 1px;
      margin-top: 4px;
      color: #111;
  }
  #ss-barcode-svg {
      display: block;
      max-width: 180px;
      height: 48px;
  }

  /* ── TTN Table ────────────────────────────────────────────────────── */
  table.ss-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 11px;
  }
  .ss-table th {
      background: #f3f4f6;
      border: 1px solid #d1d5db;
      padding: 5px 6px;
      text-align: left;
      font-size: 10px;
      font-weight: 700;
      white-space: nowrap;
  }
  .ss-table td {
      border: 1px solid #e5e7eb;
      padding: 5px 6px;
      vertical-align: middle;
  }
  .ss-table tr:nth-child(even) td { background: #fafafa; }

  .col-num   { width: 28px;  text-align: center; }
  .col-ttn   { width: 160px; }
  .col-recip { /* flex */ }
  .col-svc   { width: 38px;  text-align: center; }
  .col-seats { width: 36px;  text-align: center; }
  .col-weight{ width: 58px;  text-align: right; }
  .col-cost  { width: 72px;  text-align: right; }
  .col-redel { width: 80px;  text-align: right; }

  .ttn-num {
      font-weight: 700;
      font-size: 12px;
      letter-spacing: .5px;
      display: block;
      margin-bottom: 3px;
  }
  .ttn-barcode {
      display: block;
      max-width: 148px;
      height: 36px;
  }

  .recip-name  { font-weight: 600; }
  .recip-phone { color: #4b5563; font-size: 10px; }
  .recip-city  { font-size: 10px; color: #6b7280; }

  /* ── Totals row ───────────────────────────────────────────────────── */
  .ss-totals td {
      background: #f3f4f6;
      font-weight: 700;
      border: 1px solid #d1d5db;
  }

  /* ── Footer: signatures ───────────────────────────────────────────── */
  .doc-footer {
      margin-top: 20px;
      display: flex;
      gap: 24px;
  }
  .sig-block {
      flex: 1;
      border-top: 1px solid #374151;
      padding-top: 4px;
      font-size: 10px;
      color: #374151;
  }
  .sig-label { font-weight: 700; margin-bottom: 12px; display: block; }
  .sig-line {
      border-bottom: 1px solid #9ca3af;
      margin-bottom: 4px;
      height: 24px;
  }
  .sig-hint { font-size: 9px; color: #9ca3af; }

  .note-warn {
      margin: 8px 0 0;
      font-size: 10px;
      color: #92400e;
      background: #fef3c7;
      padding: 4px 8px;
      border-radius: 3px;
  }

  /* ── Print styles ─────────────────────────────────────────────────── */
  @media print {
      @page { size: A4 portrait; margin: 10mm 10mm 12mm; }
      .no-print { display: none !important; }
      .doc { margin-top: 0; padding: 0; max-width: 100%; }
      .ss-table tr { page-break-inside: avoid; }
      body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>
</head>
<body>

<div class="no-print">
  <span class="title">Реєстр НП №&nbsp;<?php echo h($ss['Number'] ?: substr($ss['Ref'], 0, 8) . '…'); ?></span>
  <button class="btn-print" onclick="window.print()">&#x1F5A8;&nbsp;Друкувати</button>
  <button class="btn-close" onclick="window.close()">Закрити</button>
</div>

<div class="doc">

  <!-- ── Header ─────────────────────────────────────────────────────── -->
  <div class="doc-header">
    <div class="doc-header-top">
      <span class="np-logo">НОВА ПОШТА</span>
      <span class="doc-title">
        Реєстр відправлень &numero;&nbsp;<?php echo h($ss['Number'] ?: '—'); ?>
      </span>
      <span class="doc-status"><?php echo $ss['status'] === 'closed' ? 'Закритий' : 'Відкритий'; ?></span>
    </div>
    <div class="doc-header-meta">
    <div class="meta-cells">
      <div class="meta-cell">
        <div class="meta-label">Відправник</div>
        <div class="meta-value"><?php echo h($senderDisplay ?: '—'); ?></div>
      </div>
      <?php if ($ss['sender_city']): ?>
      <div class="meta-cell">
        <div class="meta-label">Місто відправника</div>
        <div class="meta-value"><?php echo h($ss['sender_city']); ?></div>
      </div>
      <?php endif; ?>
      <div class="meta-cell">
        <div class="meta-label">Дата реєстру</div>
        <div class="meta-value"><?php echo $ss['DateTime'] ? date('d.m.Y H:i', strtotime($ss['DateTime'])) : '—'; ?></div>
      </div>
      <div class="meta-cell">
        <div class="meta-label">К-сть ТТН</div>
        <div class="meta-value"><?php echo count($ttns) ?: (int)$ss['Count']; ?></div>
      </div>
      <?php if ($totalRedel > 0): ?>
      <div class="meta-cell">
        <div class="meta-label">Накладний платіж</div>
        <div class="meta-value"><?php echo number_format($totalRedel, 2, '.', ' '); ?> грн</div>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($ss['Number']): ?>
    <div class="ss-barcode-wrap">
      <svg id="ss-barcode-svg"
           jsbarcode-value="<?php echo h($ss['Number']); ?>"
           jsbarcode-format="CODE128"
           jsbarcode-width="2"
           jsbarcode-height="48"
           jsbarcode-margin="0"
           jsbarcode-displayvalue="false"></svg>
      <div class="ss-barcode-num"><?php echo h($ss['Number']); ?></div>
    </div>
    <?php endif; ?>
  </div>
  </div>

  <!-- ── TTN Table ──────────────────────────────────────────────────── -->
  <?php if (!empty($ttns)): ?>
  <table class="ss-table">
    <thead>
      <tr>
        <th class="col-num">#</th>
        <th class="col-ttn">№ ТТН / Штрих-код</th>
        <th class="col-recip">Отримувач</th>
        <th class="col-svc">Тип</th>
        <th class="col-seats">М.</th>
        <th class="col-weight">Вага, кг</th>
        <th class="col-cost">Вартість, грн</th>
        <th class="col-redel">Накл. пл., грн</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($ttns as $i => $t):
        $ttnNum  = $t['int_doc_number'];
        $cost    = $t['cost']                   ? (float)$t['cost']                   : 0;
        $redel   = $t['backward_delivery_money'] ? (float)$t['backward_delivery_money'] : 0;
        $weight  = $t['weight']                  ? (float)$t['weight']                  : 0;
        $seats   = $t['seats_amount']            ? (int)$t['seats_amount']               : 1;
        $svc     = serviceLabel($t['service_type']);
      ?>
      <tr>
        <td class="col-num"><?php echo $i + 1; ?></td>
        <td class="col-ttn">
          <?php if ($ttnNum): ?>
            <span class="ttn-num"><?php echo h($ttnNum); ?></span>
            <svg class="ttn-barcode"
                 jsbarcode-value="<?php echo h($ttnNum); ?>"
                 jsbarcode-format="CODE128C"
                 jsbarcode-width="1.4"
                 jsbarcode-height="36"
                 jsbarcode-margin="0"
                 jsbarcode-displayvalue="false"></svg>
          <?php else: ?>
            <span style="color:#9ca3af">—</span>
          <?php endif; ?>
        </td>
        <td class="col-recip">
          <div class="recip-name"><?php echo h($t['recipient_contact_person'] ?: '—'); ?></div>
          <?php if ($t['recipients_phone']): ?>
            <div class="recip-phone"><?php echo h($t['recipients_phone']); ?></div>
          <?php endif; ?>
          <div class="recip-city"><?php echo h($t['city_recipient_desc'] ?: ''); ?></div>
        </td>
        <td class="col-svc"><?php echo h($svc); ?></td>
        <td class="col-seats"><?php echo $seats; ?></td>
        <td class="col-weight"><?php echo $weight > 0 ? number_format($weight, 3, '.', '') : '—'; ?></td>
        <td class="col-cost"><?php echo $cost > 0 ? number_format($cost, 2, '.', ' ') : '—'; ?></td>
        <td class="col-redel"><?php echo $redel > 0 ? number_format($redel, 2, '.', ' ') : '—'; ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr class="ss-totals">
        <td colspan="4" style="font-weight:700">
          Разом: <?php echo count($ttns); ?> ТТН
        </td>
        <td class="col-seats" style="text-align:center">
          <?php
            $totalSeats = 0;
            foreach ($ttns as $t) $totalSeats += $t['seats_amount'] ? (int)$t['seats_amount'] : 1;
            echo $totalSeats;
          ?>
        </td>
        <td class="col-weight" style="text-align:right">
          <?php echo $totalWeight > 0 ? number_format($totalWeight, 3, '.', '') : '—'; ?>
        </td>
        <td class="col-cost" style="text-align:right">
          <?php echo $totalCost > 0 ? number_format($totalCost, 2, '.', ' ') : '—'; ?>
        </td>
        <td class="col-redel" style="text-align:right">
          <?php echo $totalRedel > 0 ? number_format($totalRedel, 2, '.', ' ') : '—'; ?>
        </td>
      </tr>
    </tfoot>
  </table>

  <?php if ($note): ?>
    <p class="note-warn">&#9432; <?php echo h($note); ?></p>
  <?php endif; ?>

  <!-- ── Signature block ────────────────────────────────────────────── -->
  <div class="doc-footer">
    <div class="sig-block">
      <span class="sig-label">Відправник</span>
      <div class="sig-line"></div>
      <div class="sig-hint">підпис / прізвище</div>
    </div>
    <div class="sig-block">
      <span class="sig-label">Кур'єр Нової Пошти</span>
      <div class="sig-line"></div>
      <div class="sig-hint">підпис / прізвище</div>
    </div>
    <div class="sig-block">
      <span class="sig-label">Дата передачі</span>
      <div class="sig-line"></div>
      <div class="sig-hint">&nbsp;</div>
    </div>
  </div>

  <?php else: ?>
  <p style="color:#9ca3af;margin-top:16px;padding:24px;text-align:center;border:1px solid #e5e7eb;border-radius:4px">
    Список ТТН для цього реєстру недоступний.<br>
    <small>Реєстри, створені до оновлення системи, не містять прив'язки ТТН.</small>
  </p>
  <?php endif; ?>

</div><!-- /.doc -->

<script>
(function () {
    if (typeof JsBarcode === 'undefined') return;

    // Штрих-код реєстру (шапка)
    var ssSvg = document.getElementById('ss-barcode-svg');
    if (ssSvg) {
        try { JsBarcode(ssSvg).init(); } catch (e) {}
    }

    // Штрих-коди ТТН (таблиця)
    var ttnBarcodes = document.querySelectorAll('.ttn-barcode');
    if (ttnBarcodes.length) {
        try { JsBarcode('.ttn-barcode').init(); } catch (e) {}
    }
}());
</script>
</body>
</html>