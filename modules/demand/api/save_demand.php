<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../demand_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$id      = isset($_POST['id'])      ? (int)$_POST['id']      : 0;
$version = isset($_POST['version']) ? (int)$_POST['version']  : 0;

if (!$id) {
    echo json_encode(array('ok' => false, 'error' => 'id required'));
    exit;
}

$repo = new DemandRepository();
$r    = $repo->getById($id);
if (!$r['ok'] || empty($r['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Demand not found'));
    exit;
}

$current = $r['row'];

// ── Optimistic locking ──────────────────────────────────────────────────
if ($version > 0 && (int)$current['version'] !== $version) {
    echo json_encode(array('ok' => false, 'conflict' => true, 'error' => 'Version conflict'));
    exit;
}

// ── Collect fields ──────────────────────────────────────────────────────
$allowed_statuses = array('new','assembling','assembled','shipped','arrived','transfer','robot');

$upd = array('sync_state' => 'changed');

$status = isset($_POST['status']) ? trim($_POST['status']) : null;
if ($status !== null && in_array($status, $allowed_statuses)) {
    $upd['status'] = $status;
}

if (isset($_POST['description'])) {
    $desc = trim($_POST['description']);
    $upd['description'] = $desc !== '' ? $desc : null;
}

if (isset($_POST['applicable'])) {
    $upd['applicable'] = (int)(bool)$_POST['applicable'];
}

if (isset($_POST['counterparty_id'])) {
    $upd['counterparty_id'] = (int)$_POST['counterparty_id'] ?: null;
}
if (isset($_POST['organization_id'])) {
    $upd['organization_id'] = (int)$_POST['organization_id'] ?: null;
}
if (isset($_POST['store_id'])) {
    $upd['store_id'] = (int)$_POST['store_id'] ?: null;
}
if (isset($_POST['manager_employee_id'])) {
    $upd['manager_employee_id'] = (int)$_POST['manager_employee_id'] ?: null;
}
if (isset($_POST['delivery_method_id'])) {
    $upd['delivery_method_id'] = (int)$_POST['delivery_method_id'] ?: null;
}
if (isset($_POST['overhead_costs'])) {
    $upd['overhead_costs'] = round((float)$_POST['overhead_costs'], 2);
}

// Increment version
$newVersion = (int)$current['version'] + 1;
$upd['version'] = $newVersion;

// ── Update demand header ────────────────────────────────────────────────
Database::update('Papir', 'demand', $upd, array('id' => $id));

// ── Process items ───────────────────────────────────────────────────────
$itemsJson = isset($_POST['items']) ? $_POST['items'] : null;
if ($itemsJson !== null) {
    $items = json_decode($itemsJson, true);
    if (is_array($items)) {
        // Gather existing item IDs
        $existingItems = array();
        $rItems = Database::fetchAll('Papir', "SELECT id FROM demand_item WHERE demand_id = {$id}");
        if ($rItems['ok'] && !empty($rItems['rows'])) {
            foreach ($rItems['rows'] as $row) {
                $existingItems[(int)$row['id']] = true;
            }
        }

        $processedIds = array();
        $lineNo = 1;
        $sumTotal = 0;
        $sumVat = 0;

        foreach ($items as $item) {
            if (!empty($item['_deleted'])) {
                // Delete this item
                $itemId = isset($item['id']) ? (int)$item['id'] : 0;
                if ($itemId > 0) {
                    Database::query('Papir', "DELETE FROM demand_item WHERE id = {$itemId} AND demand_id = {$id}");
                }
                continue;
            }

            $qty   = isset($item['quantity'])         ? round((float)$item['quantity'], 3) : 0;
            $price = isset($item['price'])            ? round((float)$item['price'], 2) : 0;
            $disc  = isset($item['discount_percent']) ? round((float)$item['discount_percent'], 3) : 0;
            $vat   = isset($item['vat_rate'])         ? round((float)$item['vat_rate'], 3) : 0;

            // Calculate sum_row
            $gross   = round($qty * $price, 2);
            $discAmt = round($gross * $disc / 100, 2);
            $sumRow  = round($gross - $discAmt, 2);
            $vatAmt  = $vat > 0 ? round($sumRow - $sumRow / (1 + $vat / 100), 2) : 0;

            $sumTotal += $sumRow;
            $sumVat   += $vatAmt;

            $itemData = array(
                'demand_id'        => $id,
                'line_no'          => $lineNo++,
                'product_id'       => isset($item['product_id']) ? ((int)$item['product_id'] ?: null) : null,
                'product_ms_id'    => isset($item['product_ms_id']) ? $item['product_ms_id'] : null,
                'product_name'     => isset($item['product_name']) ? mb_substr(trim($item['product_name']), 0, 255, 'UTF-8') : null,
                'sku'              => isset($item['sku']) ? mb_substr(trim($item['sku']), 0, 64, 'UTF-8') : null,
                'quantity'         => $qty,
                'price'            => $price,
                'discount_percent' => $disc,
                'vat_rate'         => $vat,
                'sum_row'          => $sumRow,
            );

            $itemId = isset($item['id']) ? (int)$item['id'] : 0;
            $isNew  = !empty($item['_isNew']) || $itemId === 0;

            if (!$isNew && $itemId > 0 && isset($existingItems[$itemId])) {
                // Update
                Database::update('Papir', 'demand_item', $itemData, array('id' => $itemId));
                $processedIds[$itemId] = true;
            } else {
                // Insert
                Database::insert('Papir', 'demand_item', $itemData);
            }
        }

        // Delete items that were not in the submitted list (and not explicitly deleted above)
        foreach ($existingItems as $eid => $v) {
            if (!isset($processedIds[$eid])) {
                Database::query('Papir', "DELETE FROM demand_item WHERE id = {$eid} AND demand_id = {$id}");
            }
        }

        // Update demand totals from items
        Database::update('Papir', 'demand', array(
            'sum_total' => round($sumTotal, 2),
            'sum_vat'   => round($sumVat, 2),
        ), array('id' => $id));
    }
}

// ── Recalculate delivery_cost_deduct from TTN + profit ──────────────────
$rTotals = Database::fetchRow('Papir',
    "SELECT COALESCE(SUM(di.sum_row), 0) AS sum_total,
            COALESCE(SUM(di.quantity * COALESCE(pp.price_purchase, 0)), 0) AS cost_total
     FROM demand_item di
     LEFT JOIN product_papir pp ON pp.product_id = di.product_id
     WHERE di.demand_id = {$id}");
if ($rTotals['ok'] && !empty($rTotals['row'])) {
    $calcSumTotal = (float)$rTotals['row']['sum_total'];
    $calcCost     = (float)$rTotals['row']['cost_total'];

    $rDem = Database::fetchRow('Papir', "SELECT overhead_costs FROM demand WHERE id = {$id}");
    $oh = ($rDem['ok'] && $rDem['row']) ? (float)$rDem['row']['overhead_costs'] : 0;

    // Доставка за наш рахунок — із ТТН де payer_type = 'Sender'
    $dc = 0;
    $rTtn = Database::fetchRow('Papir',
        "SELECT SUM(COALESCE(cost_on_site, 0)) AS ttn_cost
         FROM ttn_novaposhta
         WHERE demand_id = {$id} AND payer_type = 'Sender' AND deletion_mark = 0 AND state_id NOT IN (2)");
    if ($rTtn['ok'] && !empty($rTtn['row'])) {
        $dc = (float)$rTtn['row']['ttn_cost'];
    }

    $profit = round($calcSumTotal - $calcCost - $oh - $dc, 2);
    Database::update('Papir', 'demand', array(
        'delivery_cost_deduct' => round($dc, 2),
        'profit'               => $profit,
    ), array('id' => $id));
}

// ── Recalc linked customerorder finance → fires triggers ────────────────
$coId = !empty($current['customerorder_id']) ? (int)$current['customerorder_id'] : 0;
if ($coId) {
    require_once __DIR__ . '/../../customerorder/services/OrderFinanceHelper.php';
    OrderFinanceHelper::recalc($coId);
}

// ── Push to МС ──────────────────────────────────────────────────────────
$sync   = new DemandMsSync();
$result = $sync->push($id);

if (!$result['ok']) {
    // Still return ok to client — save succeeded, sync failed
    Database::update('Papir', 'demand', array(
        'sync_state' => 'error',
        'sync_error' => isset($result['error']) ? mb_substr($result['error'], 0, 500, 'UTF-8') : 'Sync error',
    ), array('id' => $id));
}

// ── Return fresh data ───────────────────────────────────────────────────
$fresh = $repo->getById($id);
echo json_encode(array(
    'ok'      => true,
    'version' => $newVersion,
    'demand'  => $fresh['ok'] ? $fresh['row'] : array(),
));