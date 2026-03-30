<?php
/**
 * GET /counterparties/api/get_order_flow?order_id=X
 * Returns document chain: order + items, demands, TTNs, payments, returns.
 * Document relationships (demands, payments, returns) resolved via document_link table.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

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

// ── Demands via document_link ─────────────────────────────────────────────────
$rDemands = \Database::fetchAll('Papir',
    "SELECT d.id, d.number, d.status, d.sum_total, d.sum_paid, d.moment
     FROM document_link dl
     JOIN demand d ON d.id_ms = dl.from_ms_id
     WHERE dl.from_type = 'demand'
       AND dl.to_type   = 'customerorder'
       AND dl.to_id     = {$orderId}
       AND d.deleted_at IS NULL
     ORDER BY d.moment ASC");
$demands = ($rDemands['ok']) ? $rDemands['rows'] : array();

// ── TTN Nova Poshta via document_link ─────────────────────────────────────────
$rNp = \Database::fetchAll('Papir',
    "SELECT tn.id, tn.int_doc_number, tn.state_name, tn.state_define,
            tn.backward_delivery_money, tn.city_recipient_desc,
            tn.estimated_delivery_date, tn.arrived, tn.moment
     FROM document_link dl
     JOIN ttn_novaposhta tn ON tn.id = dl.from_id
     WHERE dl.from_type = 'ttn_np'
       AND dl.to_type   = 'customerorder'
       AND dl.to_id     = {$orderId}
       AND (tn.deletion_mark IS NULL OR tn.deletion_mark = 0)
     ORDER BY tn.id ASC");
$ttnsNp = ($rNp['ok']) ? $rNp['rows'] : array();

// ── TTN Ukrposhta via document_link ───────────────────────────────────────────
$rUp = \Database::fetchAll('Papir',
    "SELECT tu.id, tu.barcode, tu.lifecycle_status, tu.postPayUah,
            tu.recipient_city, tu.lifecycle_statusDate AS moment
     FROM document_link dl
     JOIN ttn_ukrposhta tu ON tu.id = dl.from_id
     WHERE dl.from_type = 'ttn_up'
       AND dl.to_type   = 'customerorder'
       AND dl.to_id     = {$orderId}
     ORDER BY tu.id ASC");
$ttnsUp = ($rUp['ok']) ? $rUp['rows'] : array();

// ── Payments via document_link ────────────────────────────────────────────────
// paymentin → finance_bank (безналичные)
$rBank = \Database::fetchAll('Papir',
    "SELECT fb.id, 'bank' AS source, fb.doc_number, fb.moment,
            fb.operations AS amount, dl.linked_sum
     FROM document_link dl
     JOIN finance_bank fb ON fb.id_ms = dl.from_ms_id
     WHERE dl.from_type = 'paymentin'
       AND dl.to_type   = 'customerorder'
       AND dl.to_id     = {$orderId}
       AND fb.is_posted = 1
     ORDER BY fb.moment ASC");

// cashin → finance_cash (наличные / касса)
$rCash = \Database::fetchAll('Papir',
    "SELECT fc.id, 'cash' AS source, fc.doc_number, fc.moment,
            fc.operations AS amount, dl.linked_sum
     FROM document_link dl
     JOIN finance_cash fc ON fc.id_ms = dl.from_ms_id
     WHERE dl.from_type = 'cashin'
       AND dl.to_type   = 'customerorder'
       AND dl.to_id     = {$orderId}
       AND fc.is_posted = 1
     ORDER BY fc.moment ASC");

$payments = array();
if ($rBank['ok']) {
    foreach ($rBank['rows'] as $p) { $payments[] = $p; }
}
if ($rCash['ok']) {
    foreach ($rCash['rows'] as $p) { $payments[] = $p; }
}
usort($payments, function($a, $b) {
    return strcmp($a['moment'], $b['moment']);
});

// ── Sales Returns via document_link ──────────────────────────────────────────
// salesreturn → demand → customerorder (двойной JOIN: dl_sr.to_ms_id = dl_dem.from_ms_id)
// to_id для demand в document_link — NULL, поэтому связь идёт через to_ms_id.
// salesreturn использует utf8mb4_0900_ai_ci, остальные utf8mb4_ru_0900_ai_ci → COLLATE на sr.id_ms.
$rRet = \Database::fetchAll('Papir',
    "SELECT sr.id, sr.number, sr.sum_total, sr.moment, sr.demand_id, sr.description
     FROM document_link dl_sr
     JOIN document_link dl_dem
          ON dl_dem.from_ms_id = dl_sr.to_ms_id
         AND dl_dem.from_type  = 'demand'
         AND dl_dem.to_type    = 'customerorder'
         AND dl_dem.to_id      = {$orderId}
     JOIN salesreturn sr
          ON sr.id_ms COLLATE utf8mb4_ru_0900_ai_ci = dl_sr.from_ms_id
     WHERE dl_sr.from_type = 'salesreturn'
       AND dl_sr.to_type   = 'demand'
     ORDER BY sr.moment ASC");
$returns = ($rRet['ok']) ? $rRet['rows'] : array();

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
