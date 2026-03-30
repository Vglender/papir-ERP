<?php
/**
 * POST /counterparties/api/save_demand
 * Saves demand header (status, description) + items with insert/update/delete.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$demandId    = isset($_POST['demand_id'])    ? (int)$_POST['demand_id']        : 0;
$status      = isset($_POST['status'])       ? trim($_POST['status'])           : '';
$description = isset($_POST['description'])  ? trim($_POST['description'])      : '';
$itemsJson   = isset($_POST['items'])        ? $_POST['items']                  : '[]';

if ($demandId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'demand_id required'));
    exit;
}

$items = json_decode($itemsJson, true);
if (!is_array($items)) {
    $items = array();
}

// Validate status
$validStatuses = array('new', 'assembling', 'assembled', 'shipped', 'arrived', 'transfer', 'robot');
if (!in_array($status, $validStatuses, true)) {
    $status = 'new';
}

// Verify demand exists
$rCheck = \Database::fetchRow('Papir',
    "SELECT id FROM demand WHERE id = {$demandId} AND deleted_at IS NULL LIMIT 1");
if (!$rCheck['ok'] || empty($rCheck['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Demand not found'));
    exit;
}

// ── Pre-calculate item totals ─────────────────────────────────────────────────
$lineNo   = 1;
$sumTotal = 0.0;
$sumVat   = 0.0;

foreach ($items as $k => $item) {
    if (!empty($item['_deleted'])) continue;
    $qty     = max(0.0, (float)(isset($item['quantity'])        ? $item['quantity']        : 0));
    $price   = max(0.0, (float)(isset($item['price'])           ? $item['price']           : 0));
    $disc    = max(0.0, min(100.0, (float)(isset($item['discount_percent']) ? $item['discount_percent'] : 0)));
    $vatRate = (float)(isset($item['vat_rate']) ? $item['vat_rate'] : 0);
    $gross   = round($qty * $price * 100) / 100;
    $discAmt = round($gross * $disc / 100 * 100) / 100;
    $sumRow  = round(($gross - $discAmt) * 100) / 100;
    $vatAmt  = $vatRate > 0 ? round(($sumRow - $sumRow / (1 + $vatRate / 100)) * 100) / 100 : 0.0;
    $items[$k]['_sumRow'] = $sumRow;
    $items[$k]['_vatAmt'] = $vatAmt;
    $items[$k]['_lineNo'] = $lineNo;
    $sumTotal += $sumRow;
    $sumVat   += $vatAmt;
    $lineNo++;
}

$sumTotal = round($sumTotal * 100) / 100;
$sumVat   = round($sumVat   * 100) / 100;

// ── Process items ─────────────────────────────────────────────────────────────
foreach ($items as $item) {
    $itemId = (int)(isset($item['id']) ? $item['id'] : 0);

    if (!empty($item['_deleted'])) {
        if ($itemId > 0) {
            \Database::query('Papir',
                "DELETE FROM demand_item WHERE id = {$itemId} AND demand_id = {$demandId}");
        }
        continue;
    }

    $productId   = (int)(isset($item['product_id']) ? $item['product_id'] : 0);
    $productName = \Database::escape('Papir', (string)(isset($item['product_name']) ? $item['product_name'] : (isset($item['name']) ? $item['name'] : '')));
    $sku         = \Database::escape('Papir', (string)(isset($item['article']) ? $item['article'] : (isset($item['sku']) ? $item['sku'] : '')));
    $quantity    = (float)(isset($item['quantity'])         ? $item['quantity']         : 0);
    $price       = (float)(isset($item['price'])            ? $item['price']            : 0);
    $disc        = (float)(isset($item['discount_percent']) ? $item['discount_percent'] : 0);
    $vatRate     = (float)(isset($item['vat_rate'])         ? $item['vat_rate']         : 0);
    $sumRow      = $item['_sumRow'];
    $ln          = (int)$item['_lineNo'];

    if ($itemId > 0) {
        \Database::query('Papir',
            "UPDATE demand_item
             SET line_no={$ln}, product_name='{$productName}', sku='{$sku}',
                 quantity={$quantity}, price={$price},
                 discount_percent={$disc}, vat_rate={$vatRate}, sum_row={$sumRow}
             WHERE id={$itemId} AND demand_id={$demandId}");
    } else {
        $productIdSql = $productId > 0 ? $productId : 'NULL';
        \Database::query('Papir',
            "INSERT INTO demand_item
                (demand_id, line_no, product_id, product_name, sku,
                 quantity, price, discount_percent, vat_rate, sum_row,
                 shipped_quantity, reserve, in_transit, overhead)
             VALUES
                ({$demandId}, {$ln}, {$productIdSql}, '{$productName}', '{$sku}',
                 {$quantity}, {$price}, {$disc}, {$vatRate}, {$sumRow},
                 0, 0, 0, 0)");
    }
}

// ── Update demand header ──────────────────────────────────────────────────────
$statusEsc = \Database::escape('Papir', $status);
$descEsc   = \Database::escape('Papir', $description);
\Database::query('Papir',
    "UPDATE demand
     SET status='{$statusEsc}', description='{$descEsc}',
         sum_total={$sumTotal}, sum_vat={$sumVat}
     WHERE id={$demandId}");

// ── Return fresh data ─────────────────────────────────────────────────────────
$rFresh = \Database::fetchRow('Papir',
    "SELECT d.id, d.id_ms, d.number, d.status, d.sum_total, d.sum_vat,
            d.sum_paid, d.moment, d.description, d.customerorder_id, d.updated_at
     FROM demand d WHERE d.id = {$demandId} LIMIT 1");
$demand = ($rFresh['ok'] && !empty($rFresh['row'])) ? $rFresh['row'] : array();

$rItems = \Database::fetchAll('Papir',
    "SELECT di.id, di.demand_id, di.line_no, di.product_id, di.product_name, di.sku,
            di.quantity, di.price, di.discount_percent, di.vat_rate, di.sum_row,
            di.shipped_quantity, di.reserve,
            COALESCE(NULLIF(di.product_name,''),
                     NULLIF(pd_uk.name,''), NULLIF(pd_ru.name,''), '') AS name,
            COALESCE(NULLIF(di.sku,''), pp.product_article, '') AS article
     FROM demand_item di
     LEFT JOIN product_papir pp ON pp.product_id = di.product_id
     LEFT JOIN product_description pd_uk ON pd_uk.product_id = di.product_id AND pd_uk.language_id = 2
     LEFT JOIN product_description pd_ru ON pd_ru.product_id = di.product_id AND pd_ru.language_id = 1
     WHERE di.demand_id = {$demandId}
     ORDER BY di.line_no ASC");
$freshItems = ($rItems['ok']) ? $rItems['rows'] : array();

echo json_encode(array('ok' => true, 'demand' => $demand, 'items' => $freshItems));
