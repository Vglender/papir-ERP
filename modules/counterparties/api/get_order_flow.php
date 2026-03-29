<?php
/**
 * GET /counterparties/api/get_order_flow?order_id=X&id_ms=UUID
 * Returns document chain: order + items, demands, TTNs, payments, returns
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$idMs    = isset($_GET['id_ms'])    ? trim($_GET['id_ms'])    : '';

if ($orderId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'order_id required'));
    exit;
}

// ── Order + items ─────────────────────────────────────────────────────────────
$rOrder = \Database::fetchRow('Papir',
    "SELECT id, version, number, status, payment_status, shipment_status,
            sum_items, sum_discount, sum_vat, sum_total,
            moment, description, applicable, sales_channel
     FROM customerorder
     WHERE id = {$orderId} AND deleted_at IS NULL LIMIT 1");

if (!$rOrder['ok'] || empty($rOrder['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Order not found'));
    exit;
}
$order = $rOrder['row'];

$rItems = \Database::fetchAll('Papir',
    "SELECT ci.id, ci.product_id, ci.line_no, ci.quantity,
            ci.price, ci.discount_percent, ci.vat_rate, ci.vat_amount,
            ci.sum_without_discount, ci.sum_row AS sum,
            ci.stock_quantity, ci.shipped_quantity, ci.reserved_quantity,
            COALESCE(NULLIF(ci.product_name,''),
                     NULLIF(pd_uk.name,''), NULLIF(pd_ru.name,''), '') AS name,
            COALESCE(NULLIF(ci.sku,''), pp.product_article, '') AS article
     FROM customerorder_item ci
     LEFT JOIN product_papir pp ON pp.product_id = ci.product_id
     LEFT JOIN product_description pd_uk ON pd_uk.product_id = ci.product_id AND pd_uk.language_id = 2
     LEFT JOIN product_description pd_ru ON pd_ru.product_id = ci.product_id AND pd_ru.language_id = 1
     WHERE ci.customerorder_id = {$orderId}
     ORDER BY ci.line_no ASC");
$items = ($rItems['ok']) ? $rItems['rows'] : array();

// ── Demands ───────────────────────────────────────────────────────────────────
$rDemands = \Database::fetchAll('Papir',
    "SELECT id, number, status, sum_total, sum_paid, moment
     FROM demand
     WHERE customerorder_id = {$orderId} AND deleted_at IS NULL
     ORDER BY moment ASC");
$demands = ($rDemands['ok']) ? $rDemands['rows'] : array();

// ── TTN Nova Poshta ───────────────────────────────────────────────────────────
$rNp = \Database::fetchAll('Papir',
    "SELECT id, int_doc_number, state_name, state_define, backward_delivery_money,
            city_recipient_desc, estimated_delivery_date, arrived, moment
     FROM ttn_novaposhta
     WHERE customerorder_id = {$orderId} AND (deletion_mark IS NULL OR deletion_mark = 0)
     ORDER BY id ASC");
$ttnsNp = ($rNp['ok']) ? $rNp['rows'] : array();

// ── TTN Ukrposhta ─────────────────────────────────────────────────────────────
$rUp = \Database::fetchAll('Papir',
    "SELECT id, barcode, lifecycle_status, postPayUah,
            recipient_city, lifecycle_statusDate AS moment
     FROM ttn_ukrposhta
     WHERE customerorder_id = {$orderId}
     ORDER BY id ASC");
$ttnsUp = ($rUp['ok']) ? $rUp['rows'] : array();

// ── Payments via agent_ms ─────────────────────────────────────────────────────
$payments = array();
if ($idMs !== '') {
    $idMsEsc = \Database::escape('Papir', $idMs);

    $rBank = \Database::fetchAll('Papir',
        "SELECT id, 'bank' AS source, doc_number, moment, operations AS amount
         FROM finance_bank
         WHERE agent_ms = '{$idMsEsc}' AND direction = 'in' AND is_posted = 1
         ORDER BY moment ASC");

    $rCash = \Database::fetchAll('Papir',
        "SELECT id, 'cash' AS source, doc_number, moment, operations AS amount
         FROM finance_cash
         WHERE direction = 'in' AND is_posted = 1
         ORDER BY moment ASC LIMIT 10");

    if ($rBank['ok']) foreach ($rBank['rows'] as $p) { $payments[] = $p; }
    if ($rCash['ok']) foreach ($rCash['rows'] as $p) { $payments[] = $p; }

    // Sort combined payments by moment
    usort($payments, function($a, $b) {
        return strcmp($a['moment'], $b['moment']);
    });
}

// ── Sales Returns (via demand_id) ─────────────────────────────────────────────
$returns = array();
if (!empty($demands)) {
    $demandIds = array();
    foreach ($demands as $dem) { $demandIds[] = (int)$dem['id']; }
    $inList = implode(',', $demandIds);

    $rRet = \Database::fetchAll('Papir',
        "SELECT id, number, sum_total, moment, demand_id, description
         FROM salesreturn
         WHERE demand_id IN ({$inList})
         ORDER BY moment ASC");
    $returns = ($rRet['ok']) ? $rRet['rows'] : array();
}

// ── Aggregates ────────────────────────────────────────────────────────────────
$sumPaid = 0;
foreach ($demands as $d) { $sumPaid += (float)$d['sum_paid']; }

$sumPayments = 0;
foreach ($payments as $p) { $sumPayments += (float)$p['amount']; }

echo json_encode(array(
    'ok'           => true,
    'order'        => $order,
    'items'        => $items,
    'demands'      => $demands,
    'ttns_np'      => $ttnsNp,
    'ttns_up'      => $ttnsUp,
    'payments'     => $payments,
    'returns'      => $returns,
    'sum_paid'     => $sumPaid,
    'sum_payments' => $sumPayments,
));
