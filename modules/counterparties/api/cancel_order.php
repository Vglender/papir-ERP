<?php
/**
 * POST /counterparties/api/cancel_order
 * Cascade cancellation: cancels order, archives active demands,
 * logs refund_needed if payments exist.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

if (!\Papir\Crm\AuthService::isLoggedIn()) {
    echo json_encode(array('ok' => false, 'error' => 'Unauthorized'));
    exit;
}

$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
if ($orderId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'Invalid params'));
    exit;
}

// Fetch current order
$rOrder = \Database::fetchRow('Papir',
    "SELECT id, status, number FROM customerorder WHERE id={$orderId} AND deleted_at IS NULL");
if (!$rOrder['ok'] || empty($rOrder['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Order not found'));
    exit;
}
$order     = $rOrder['row'];
$oldStatus = $order['status'];

if ($oldStatus === 'cancelled') {
    echo json_encode(array('ok' => false, 'error' => 'Замовлення вже скасовано'));
    exit;
}

// Get linked active demands
$rDemands = \Database::fetchAll('Papir',
    "SELECT d.id, d.number, d.status
     FROM document_link dl
     JOIN demand d ON (d.id_ms = dl.from_ms_id OR (dl.from_ms_id IS NULL AND d.id = dl.from_id))
     WHERE dl.from_type = 'demand'
       AND dl.to_type   = 'customerorder'
       AND dl.to_id     = {$orderId}
       AND d.deleted_at IS NULL
       AND d.status NOT IN ('cancelled','returned')");
$demands = ($rDemands['ok']) ? $rDemands['rows'] : array();

// Sum linked payments
$rPay = \Database::fetchRow('Papir',
    "SELECT COALESCE(SUM(dl.linked_sum), 0) AS total
     FROM document_link dl
     WHERE dl.from_type IN ('paymentin', 'cashin')
       AND dl.to_type   = 'customerorder'
       AND dl.to_id     = {$orderId}");
$paymentSum   = ($rPay['ok'] && !empty($rPay['row'])) ? (float)$rPay['row']['total'] : 0.0;
$refundNeeded = ($paymentSum > 0);

// Cancel the order
$rUpd = \Database::update('Papir', 'customerorder',
    array('status' => 'cancelled'),
    array('id' => $orderId));
if (!$rUpd['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'DB error'));
    exit;
}

// Archive (cancel) active demands
$nCancelled = 0;
foreach ($demands as $dem) {
    $rDemUpd = \Database::update('Papir', 'demand',
        array('status' => 'cancelled'),
        array('id' => (int)$dem['id']));
    if ($rDemUpd['ok'] && $rDemUpd['affected_rows'] > 0) {
        $nCancelled++;
    }
}

// History: status change
$commentParts = array('Скасування замовлення оператором');
if ($nCancelled > 0) {
    $commentParts[] = $nCancelled . ' відвантажень анульовано';
}
\Database::insert('Papir', 'customerorder_history', array(
    'customerorder_id' => $orderId,
    'event_type'       => 'status_change',
    'field_name'       => 'status',
    'old_value'        => $oldStatus,
    'new_value'        => 'cancelled',
    'is_auto'          => 0,
    'comment'          => implode(', ', $commentParts),
));

// History: refund needed
if ($refundNeeded) {
    \Database::insert('Papir', 'customerorder_history', array(
        'customerorder_id' => $orderId,
        'event_type'       => 'refund_needed',
        'field_name'       => 'payment',
        'new_value'        => (string)$paymentSum,
        'is_auto'          => 0,
        'comment'          => 'Необхідне повернення коштів: ₴' . number_format($paymentSum, 2, '.', ' '),
    ));
}

\Papir\Crm\AuthService::log('cancel', 'customerorder', $orderId, 'cancelled');

echo json_encode(array(
    'ok'            => true,
    'n_demands'     => $nCancelled,
    'refund_needed' => $refundNeeded,
    'refund_sum'    => $paymentSum,
));
