<?php
/**
 * GET /customerorder/api/get_order_shipments?order_id=X
 * Returns TTNs (НП) and order_delivery records for this order.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../customerorder_bootstrap.php';

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($orderId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'order_id required'));
    exit;
}

// TTNs linked via customerorder_id column
$rTtns = Database::fetchAll('Papir',
    "SELECT id, int_doc_number, state_name, state_define, deletion_mark,
            recipient_contact_person, city_recipient_desc, cost_on_site,
            backward_delivery_money, afterpayment_on_goods_cost,
            recipients_phone, weight, seats_amount, created_at, moment
     FROM ttn_novaposhta
     WHERE customerorder_id = {$orderId}
       AND (deletion_mark IS NULL OR deletion_mark = 0)
     ORDER BY id ASC");
$ttns = ($rTtns['ok']) ? $rTtns['rows'] : array();

// Also get TTNs linked via document_link (if customerorder_id not set on them)
$rLinks = Database::fetchAll('Papir',
    "SELECT from_id FROM document_link
     WHERE to_type='customerorder' AND to_id={$orderId} AND from_type='ttn_np'");
if ($rLinks['ok'] && !empty($rLinks['rows'])) {
    $knownIds = array();
    foreach ($ttns as $t) { $knownIds[] = (int)$t['id']; }
    $missing = array();
    foreach ($rLinks['rows'] as $row) {
        $lid = (int)$row['from_id'];
        if ($lid > 0 && !in_array($lid, $knownIds)) $missing[] = $lid;
    }
    if (!empty($missing)) {
        $inIds = implode(',', $missing);
        $rExtra = Database::fetchAll('Papir',
            "SELECT id, int_doc_number, state_name, state_define, deletion_mark,
                    recipient_contact_person, city_recipient_desc, cost_on_site,
                    backward_delivery_money, afterpayment_on_goods_cost,
                    recipients_phone, weight, seats_amount, created_at, moment
             FROM ttn_novaposhta
             WHERE id IN ({$inIds})
               AND (deletion_mark IS NULL OR deletion_mark = 0)
             ORDER BY id ASC");
        if ($rExtra['ok']) $ttns = array_merge($ttns, $rExtra['rows']);
    }
}

// Order delivery records (pickup, courier, etc.)
$rDels = Database::fetchAll('Papir',
    "SELECT od.id, od.delivery_method_id, od.status, od.sent_at, od.delivered_at, od.comment,
            dm.code, dm.name_uk
     FROM order_delivery od
     JOIN delivery_method dm ON dm.id = od.delivery_method_id
     WHERE od.customerorder_id = {$orderId}
     ORDER BY od.id ASC");
$deliveries = ($rDels['ok']) ? $rDels['rows'] : array();

// Demand (відвантаження) records linked via customerorder_id
$rDemands = Database::fetchAll('Papir',
    "SELECT id, number, moment, status, sum_total
     FROM demand
     WHERE customerorder_id = {$orderId}
     ORDER BY id ASC");
$demands = ($rDemands['ok']) ? $rDemands['rows'] : array();

// Also get demands linked only via document_link (from_ms_id), without customerorder_id
$rDLinks = Database::fetchAll('Papir',
    "SELECT from_ms_id FROM document_link
     WHERE to_type='customerorder' AND to_id={$orderId} AND from_type='demand'
       AND from_id IS NULL AND from_ms_id IS NOT NULL AND from_ms_id != ''");
if ($rDLinks['ok'] && !empty($rDLinks['rows'])) {
    $knownMsIds = array();
    // demands loaded via customerorder_id don't have id_ms in the SELECT above,
    // so we just collect all link ms_ids and exclude duplicates after fetching
    $msIds = array();
    foreach ($rDLinks['rows'] as $row) {
        $msIds[] = "'" . Database::escape('Papir', $row['from_ms_id']) . "'";
    }
    if (!empty($msIds)) {
        $rExtra = Database::fetchAll('Papir',
            "SELECT id, number, moment, status, sum_total
             FROM demand
             WHERE id_ms IN (" . implode(',', $msIds) . ")
               AND (customerorder_id IS NULL OR customerorder_id != {$orderId})
             ORDER BY id ASC");
        if ($rExtra['ok']) {
            $demands = array_merge($demands, $rExtra['rows']);
        }
    }
}

echo json_encode(array(
    'ok'         => true,
    'ttns'       => $ttns,
    'deliveries' => $deliveries,
    'demands'    => $demands,
));
