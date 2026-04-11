<?php
/**
 * POST /customerorder/api/create_document
 * Creates a linked document from a customer order.
 *
 * Params:
 *   order_id  — source customerorder.id
 *   to_type   — target document type (demand, salesreturn, invoiceout, paymentin, cashin)
 *   link_type — value for document_link.link_type
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../demand/demand_bootstrap.php';
require_once __DIR__ . '/../../finance/api/finance_ms_sync.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$orderId  = isset($_POST['order_id'])  ? (int)$_POST['order_id']         : 0;
$toType   = isset($_POST['to_type'])   ? trim($_POST['to_type'])          : '';
$linkType = isset($_POST['link_type']) ? trim($_POST['link_type'])        : '';

if ($orderId <= 0 || $toType === '') {
    echo json_encode(array('ok' => false, 'error' => 'order_id and to_type required'));
    exit;
}

// Load source order
$rOrder = Database::fetchRow('Papir',
    "SELECT id, counterparty_id, contact_person_id, organization_id, store_id,
            manager_employee_id, currency_code, description, sales_channel,
            sum_total, applicable
     FROM customerorder
     WHERE id = {$orderId} AND deleted_at IS NULL LIMIT 1");
if (!$rOrder['ok'] || empty($rOrder['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Order not found'));
    exit;
}
$order = $rOrder['row'];

// Load order items
$rItems = Database::fetchAll('Papir',
    "SELECT ci.product_id, ci.product_ms_id, ci.product_name, ci.sku,
            ci.quantity, ci.price, ci.discount_percent, ci.vat_rate,
            ci.discount_amount, ci.vat_amount, ci.sum_row, ci.line_no, ci.weight
     FROM customerorder_item ci
     WHERE ci.customerorder_id = {$orderId}
     ORDER BY ci.line_no ASC");
$orderItems = ($rItems['ok'] && !empty($rItems['rows'])) ? $rItems['rows'] : array();

// ── Helpers ───────────────────────────────────────────────────────────────────

function generateUuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000,
        mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));
}

function insertDocumentLink($fromType, $fromId, $toType, $toId, $linkType, $linkedSum) {
    Database::insert('Papir', 'document_link', array(
        'from_type'   => $fromType,
        'from_id'     => $fromId,
        'to_type'     => $toType,
        'to_id'       => $toId,
        'link_type'   => $linkType,
        'linked_sum'  => $linkedSum,
    ));
}

// ── Create demand ─────────────────────────────────────────────────────────────

if ($toType === 'demand') {

    // Calculate totals from items
    $sumTotal = 0.0;
    $sumVat   = 0.0;
    foreach ($orderItems as $it) {
        $sumTotal += (float)$it['sum_row'];
        $sumVat   += (float)$it['vat_amount'];
    }
    $sumTotal = round($sumTotal * 100) / 100;
    $sumVat   = round($sumVat   * 100) / 100;

    $cpId     = !empty($order['counterparty_id'])   ? (int)$order['counterparty_id']   : null;
    $orgId    = !empty($order['organization_id'])   ? (int)$order['organization_id']   : null;
    $storeId  = !empty($order['store_id'])          ? (int)$order['store_id']          : null;
    $descEsc  = Database::escape('Papir', (string)$order['description']);
    $channel  = Database::escape('Papir', (string)$order['sales_channel']);

    $managerId = !empty($order['manager_employee_id']) ? (int)$order['manager_employee_id'] : null;

    $newDemand = array(
        'uuid'                => generateUuid(),
        'source'              => 'papir',
        'moment'              => date('Y-m-d H:i:s'),
        'applicable'          => 0,
        'status'              => 'new',
        'counterparty_id'     => $cpId,
        'organization_id'     => $orgId,
        'store_id'            => $storeId,
        'manager_employee_id' => $managerId,
        'customerorder_id'    => $orderId,
        'sum_total'           => $sumTotal,
        'sum_vat'             => $sumVat,
        'sum_paid'            => 0,
        'profit'              => 0,
        'profit_real'         => 0,
        'sales_channel'       => $order['sales_channel'] !== null ? $order['sales_channel'] : null,
        'description'         => $order['description'] !== null ? $order['description'] : null,
        'sync_state'          => 'new',
    );

    $rIns = Database::insert('Papir', 'demand', $newDemand);
    if (!$rIns['ok'] || empty($rIns['insert_id'])) {
        echo json_encode(array('ok' => false, 'error' => 'Failed to create demand'));
        exit;
    }
    $newDemandId = (int)$rIns['insert_id'];

    // Copy items
    $ln = 1;
    foreach ($orderItems as $it) {
        $pid   = !empty($it['product_id'])   ? (int)$it['product_id']   : null;
        $pmsId = !empty($it['product_ms_id']) ? $it['product_ms_id']    : null;
        $qty   = (float)$it['quantity'];
        $price = (float)$it['price'];
        $disc  = (float)$it['discount_percent'];
        $vat   = (float)$it['vat_rate'];
        $gross = round($qty * $price * 100) / 100;
        $discAmt = round($gross * $disc / 100 * 100) / 100;
        $sumRow  = round(($gross - $discAmt) * 100) / 100;

        $nameEsc = Database::escape('Papir', (string)$it['product_name']);
        $skuEsc  = Database::escape('Papir', (string)$it['sku']);
        $pmsEsc  = $pmsId ? "'" . Database::escape('Papir', $pmsId) . "'" : 'NULL';
        $pidSql  = $pid ? $pid : 'NULL';

        Database::query('Papir',
            "INSERT INTO demand_item
                (demand_id, line_no, product_id, product_ms_id, product_name, sku,
                 quantity, price, discount_percent, vat_rate, sum_row,
                 shipped_quantity, reserve, in_transit, overhead)
             VALUES
                ({$newDemandId}, {$ln}, {$pidSql}, {$pmsEsc}, '{$nameEsc}', '{$skuEsc}',
                 {$qty}, {$price}, {$disc}, {$vat}, {$sumRow},
                 0, 0, 0, 0)");
        $ln++;
    }

    // Link: from=demand → to=customerorder (конвенція системи)
    insertDocumentLink('demand', $newDemandId, 'customerorder', $orderId, $linkType, $sumTotal);

    // Push to МойСклад
    $sync       = new DemandMsSync();
    $syncResult = $sync->push($newDemandId);
    // Sync errors are non-fatal — demand edit page shows sync badge for retry

    echo json_encode(array(
        'ok'          => true,
        'redirect_url' => '/demand/edit?id=' . $newDemandId,
        'sync'        => $syncResult,
    ));
    exit;
}

// ── Load counterparty.id_ms and organization.id_ms (needed for payments) ─────

$rCpOrg = Database::fetchRow('Papir',
    "SELECT cp.id_ms AS cp_ms, o.id_ms AS org_ms
     FROM customerorder co
     LEFT JOIN counterparty cp  ON cp.id = co.counterparty_id
     LEFT JOIN organization o   ON o.id  = co.organization_id
     WHERE co.id = {$orderId} LIMIT 1");
$cpMs  = ($rCpOrg['ok'] && $rCpOrg['row']) ? (string)$rCpOrg['row']['cp_ms']  : '';
$orgMs = ($rCpOrg['ok'] && $rCpOrg['row']) ? (string)$rCpOrg['row']['org_ms'] : '';

$sumTotal = round((float)$order['sum_total'] * 100) / 100;
$cpId     = !empty($order['counterparty_id']) ? (int)$order['counterparty_id'] : 0;
$orgIdL   = !empty($order['organization_id']) ? (int)$order['organization_id'] : 0;
$moment   = date('Y-m-d H:i:s');

// ── Create paymentin (finance_bank direction=in) ──────────────────────────────

if ($toType === 'paymentin') {

    $rIns = Database::insert('Papir', 'finance_bank', array(
        'direction'    => 'in',
        'moment'       => $moment,
        'sum'          => $sumTotal,
        'cp_id'        => $cpId > 0 ? $cpId : null,
        'agent_ms'     => $cpMs !== '' ? $cpMs : null,
        'agent_ms_type'=> 'counterparty',
        'organization_ms' => $orgMs,
        'description'  => $order['description'] !== null ? $order['description'] : null,
        'source'       => 'papir',
        'is_posted'    => 1,
    ));
    if (!$rIns['ok'] || empty($rIns['insert_id'])) {
        echo json_encode(array('ok' => false, 'error' => 'Failed to create paymentin'));
        exit;
    }
    $newId = (int)$rIns['insert_id'];

    // Link: from=paymentin → to=customerorder
    insertDocumentLink('paymentin', $newId, 'customerorder', $orderId, $linkType, $sumTotal);

    // Push to МС
    $syncResult = finance_ms_push($newId, array(
        'direction'       => 'in',
        'moment'          => $moment,
        'sum'             => $sumTotal,
        'doc_number'      => '',
        'description'     => (string)$order['description'],
        'payment_purpose' => '',
        'cp_id'           => $cpId,
        'expense_category_id' => 0,
        'is_posted'       => 1,
        'organization_ms' => $orgMs,
        'agent_ms_type'   => 'counterparty',
    ), '');

    echo json_encode(array(
        'ok'           => true,
        'redirect_url' => '/finance/bank?search=' . $newId . '&direction=in',
        'sync'         => $syncResult,
    ));
    exit;
}

// ── Create cashin (finance_cash direction=in) ─────────────────────────────────

if ($toType === 'cashin') {

    $rIns = Database::insert('Papir', 'finance_cash', array(
        'direction'    => 'in',
        'moment'       => $moment,
        'sum'          => $sumTotal,
        'counterparty_id' => $cpId   > 0 ? $cpId   : null,
        'organization_id' => $orgIdL > 0 ? $orgIdL : null,
        'agent_ms'     => $cpMs !== '' ? $cpMs : null,
        'agent_ms_type'=> 'counterparty',
        'organization_ms' => $orgMs,
        'description'  => $order['description'] !== null ? $order['description'] : null,
        'source'       => 'papir',
        'is_posted'    => 1,
    ));
    if (!$rIns['ok'] || empty($rIns['insert_id'])) {
        echo json_encode(array('ok' => false, 'error' => 'Failed to create cashin'));
        exit;
    }
    $newId = (int)$rIns['insert_id'];

    // Link: from=cashin → to=customerorder
    insertDocumentLink('cashin', $newId, 'customerorder', $orderId, $linkType, $sumTotal);

    // Push to МС
    $syncResult = finance_ms_cash_push($newId, array(
        'direction'       => 'in',
        'moment'          => $moment,
        'sum'             => $sumTotal,
        'doc_number'      => '',
        'description'     => (string)$order['description'],
        'payment_purpose' => '',
        'counterparty_id' => $cpId,
        'organization_id' => $orgIdL,
        'agent_ms'        => $cpMs,
        'agent_ms_type'   => 'counterparty',
        'expense_category_id' => 0,
        'is_posted'       => 1,
        'organization_ms' => $orgMs,
    ), '');

    echo json_encode(array(
        'ok'           => true,
        'redirect_url' => '/finance/cash?search=' . $newId . '&direction=in',
        'sync'         => $syncResult,
    ));
    exit;
}

// ── Unsupported types (no MS sync service / no edit page yet) ─────────────────

$typeNames = array(
    'salesreturn' => 'Повернення покупця',
    'invoiceout'  => 'Рахунок покупцю',
);
$name = isset($typeNames[$toType]) ? $typeNames[$toType] : $toType;

echo json_encode(array(
    'ok'    => false,
    'error' => 'Редактор "' . $name . '" ще не реалізований',
    'todo'  => true,
));
