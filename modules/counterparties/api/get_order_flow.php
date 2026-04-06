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
    "SELECT co.id, co.version, co.number, co.status, co.payment_status, co.shipment_status,
            co.sum_items, co.sum_discount, co.sum_vat, co.sum_total,
            co.moment, co.description, co.applicable, co.sales_channel,
            co.organization_id, co.manager_employee_id,
            co.delivery_method_id,
            dm.code    AS delivery_method_code,
            dm.name_uk AS delivery_method_name,
            dm.has_ttn AS delivery_method_has_ttn,
            co.payment_method_id,
            pm.code    AS payment_method_code,
            pm.name_uk AS payment_method_name,
            o.name  AS org_name,
            o.vat_number AS org_vat_number,
            e.full_name AS manager_name
     FROM customerorder co
     LEFT JOIN organization o    ON o.id  = co.organization_id
     LEFT JOIN employee e        ON e.id  = co.manager_employee_id
     LEFT JOIN delivery_method dm ON dm.id = co.delivery_method_id
     LEFT JOIN payment_method pm  ON pm.id = co.payment_method_id
     WHERE co.id = {$orderId} AND co.deleted_at IS NULL LIMIT 1");

if (!$rOrder['ok'] || empty($rOrder['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Order not found'));
    exit;
}
$order = $rOrder['row'];

// ── Traffic source з oc_remarketing_orders ────────────────────────────────────
$trafficSource = null;
$_num = $order['number'];
if (preg_match('/^(\d+)(OFF|MFF)$/i', $_num, $_m)) {
    $_ocId  = (int)$_m[1];
    $_alias = strtolower($_m[2]) === 'off' ? 'off' : 'mff';
    $_rm = \Database::fetchRow($_alias,
        "SELECT gclid, fbclid, utm_source, utm_medium, utm_campaign
         FROM oc_remarketing_orders WHERE order_id = {$_ocId} LIMIT 1");
    if ($_rm['ok'] && !empty($_rm['row'])) {
        $_r = $_rm['row'];
        if (!empty($_r['gclid'])) {
            $trafficSource = array('label' => 'Google Ads', 'color' => '#1a73e8', 'campaign' => $_r['utm_campaign']);
        } elseif (!empty($_r['fbclid'])) {
            $trafficSource = array('label' => 'Facebook Ads', 'color' => '#1877f2', 'campaign' => $_r['utm_campaign']);
        } elseif (!empty($_r['utm_source'])) {
            $_src = strtolower($_r['utm_source']);
            if (strpos($_src, 'google') !== false) {
                $trafficSource = array('label' => 'Google', 'color' => '#34a853', 'campaign' => $_r['utm_campaign']);
            } elseif (strpos($_src, 'facebook') !== false || strpos($_src, 'fb') !== false) {
                $trafficSource = array('label' => 'Facebook', 'color' => '#1877f2', 'campaign' => $_r['utm_campaign']);
            } else {
                $_lbl = $_r['utm_source'];
                if (!empty($_r['utm_medium'])) $_lbl .= ' / ' . $_r['utm_medium'];
                $trafficSource = array('label' => $_lbl, 'color' => '#6b7280', 'campaign' => $_r['utm_campaign']);
            }
        }
    }
}

// ── Status auto flags ─────────────────────────────────────────────────────────
$rAutoFlags = \Database::fetchAll('Papir',
    "SELECT DISTINCT new_value FROM customerorder_history
     WHERE customerorder_id = {$orderId} AND event_type = 'status_change' AND is_auto = 1");
$statusAutoFlags = array();
if ($rAutoFlags['ok']) {
    foreach ($rAutoFlags['rows'] as $afRow) {
        if ($afRow['new_value']) $statusAutoFlags[$afRow['new_value']] = true;
    }
}

// ── Status before cancellation ────────────────────────────────────────────────
$cancelledFromStatus = null;
if ($order['status'] === 'cancelled') {
    $rCancelFrom = \Database::fetchRow('Papir',
        "SELECT old_value FROM customerorder_history
         WHERE customerorder_id = {$orderId} AND event_type = 'status_change'
           AND new_value = 'cancelled'
         ORDER BY created_at DESC LIMIT 1");
    if ($rCancelFrom['ok'] && !empty($rCancelFrom['row']['old_value'])) {
        $cancelledFromStatus = $rCancelFrom['row']['old_value'];
    }
}

$rItems = \Database::fetchAll('Papir',
    "SELECT ci.id, ci.product_id, ci.line_no, ci.quantity,
            ci.price, ci.discount_percent, ci.vat_rate, ci.vat_amount,
            ci.sum_without_discount, ci.sum_row AS sum,
            ci.stock_quantity, ci.shipped_quantity, ci.reserved_quantity,
            COALESCE(NULLIF(ci.product_name,''),
                     NULLIF(pd_uk.name,''), NULLIF(pd_ru.name,''), '') AS name,
            COALESCE(NULLIF(ci.sku,''), pp.product_article, '') AS article,
            COALESCE(NULLIF(ci.unit,''), pp.unit, 'шт') AS unit,
            (SELECT psi.stock FROM price_supplier_items psi
             WHERE psi.product_id = ci.product_id
               AND psi.pricelist_id = 1
               AND psi.match_type != 'ignored'
             ORDER BY psi.id LIMIT 1) AS stock_sklad
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

// ── Order deliveries (courier / pickup facts) ─────────────────────────────────
$rOdl = \Database::fetchAll('Papir',
    "SELECT od.id, od.delivery_method_id, od.status,
            od.sent_at, od.delivered_at, od.comment, od.created_at,
            dm.code AS method_code, dm.name_uk AS method_name
     FROM order_delivery od
     JOIN delivery_method dm ON dm.id = od.delivery_method_id
     WHERE od.customerorder_id = {$orderId}
       AND od.status != 'cancelled'
     ORDER BY od.created_at ASC");
$orderDeliveries = ($rOdl['ok']) ? $rOdl['rows'] : array();

// ── Return logistics (manually registered returns) ────────────────────────────
$rRetLog = \Database::fetchAll('Papir',
    "SELECT id, return_type, return_ttn_number, manual_description,
            status, received_at, comment, created_at
     FROM return_logistics
     WHERE customerorder_id = {$orderId}
       AND status != 'cancelled'
     ORDER BY created_at ASC");
$returnLogistics = ($rRetLog['ok']) ? $rRetLog['rows'] : array();

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
    'order_deliveries'  => $orderDeliveries,
    'payments'          => $payments,
    'returns'           => $returns,
    'return_logistics'  => $returnLogistics,
    'sum_paid'          => $sumPaid,
    'sum_payments'      => $sumPayments,
    'status_auto_flags'    => $statusAutoFlags,
    'cancelled_from_status' => $cancelledFromStatus,
    'traffic_source'        => $trafficSource,
));
