<?php
/**
 * POST /counterparties/api/save_order_delivery
 * Create or update an order_delivery record (courier/pickup delivery fact).
 * For TTN-based carriers (Nova Poshta, Ukrposhta) use save_ttn_manual instead.
 * Fires order_delivery_created trigger for scenario processing.
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

$id               = isset($_POST['id'])                 ? (int)$_POST['id']                 : 0;
$orderId          = isset($_POST['customerorder_id'])   ? (int)$_POST['customerorder_id']   : 0;
$deliveryMethodId = isset($_POST['delivery_method_id']) ? (int)$_POST['delivery_method_id'] : 0;
$status           = isset($_POST['status'])             ? trim($_POST['status'])             : 'pending';
$sentAt           = isset($_POST['sent_at'])            ? trim($_POST['sent_at'])            : null;
$deliveredAt      = isset($_POST['delivered_at'])       ? trim($_POST['delivered_at'])       : null;
$comment          = isset($_POST['comment'])            ? trim($_POST['comment'])            : null;

$allowedStatuses = array('pending', 'sent', 'delivered', 'cancelled');
if (!in_array($status, $allowedStatuses)) {
    echo json_encode(array('ok' => false, 'error' => 'Invalid status'));
    exit;
}

$sentAt      = ($sentAt && strlen($sentAt) >= 10)           ? $sentAt      : null;
$deliveredAt = ($deliveredAt && strlen($deliveredAt) >= 10) ? $deliveredAt : null;

$u = \Papir\Crm\AuthService::getCurrentUser();
$employeeId = ($u && isset($u["employee_id"])) ? (int)$u["employee_id"] : null;

if ($id > 0) {
    // Update existing — fetch orderId from DB for auto-ship check
    $rRec = \Database::fetchRow('Papir', "SELECT customerorder_id FROM order_delivery WHERE id={$id}");
    if ($rRec['ok'] && !empty($rRec['row'])) {
        $orderId = (int)$rRec['row']['customerorder_id'];
    }

    $data = array('status' => $status, 'comment' => $comment);
    if ($sentAt)      $data['sent_at']      = $sentAt;
    if ($deliveredAt) $data['delivered_at'] = $deliveredAt;

    $r = \Database::update('Papir', 'order_delivery', $data, array('id' => $id));
    if (!$r['ok']) {
        echo json_encode(array('ok' => false, 'error' => 'DB error'));
        exit;
    }
} else {
    // Create new
    if ($orderId <= 0 || $deliveryMethodId <= 0) {
        echo json_encode(array('ok' => false, 'error' => 'customerorder_id and delivery_method_id required'));
        exit;
    }
    $data = array(
        'customerorder_id'   => $orderId,
        'delivery_method_id' => $deliveryMethodId,
        'status'             => $status,
        'comment'            => $comment,
        'created_by'         => $employeeId,
    );
    if ($sentAt)      $data['sent_at']      = $sentAt;
    if ($deliveredAt) $data['delivered_at'] = $deliveredAt;

    $r = \Database::insert('Papir', 'order_delivery', $data);
    if (!$r['ok']) {
        echo json_encode(array('ok' => false, 'error' => 'DB error'));
        exit;
    }
    $id = (int)$r['insert_id'];
}

// Fire trigger event for scenarios
if ($orderId > 0) {
    $rOrd = \Database::fetchRow('Papir',
        "SELECT * FROM customerorder WHERE id={$orderId} LIMIT 1");
    if ($rOrd['ok'] && !empty($rOrd['row'])) {
        $order = $rOrd['row'];
        TriggerEngine::fire('order_delivery_created', array(
            'order'           => $order,
            'order_id'        => $orderId,
            'counterparty_id' => (int)$order['counterparty_id'],
            'delivery_status' => $status,
            'delivery_id'     => $id,
        ));
        TaskQueueRunner::runPending();
    }
}

echo json_encode(array('ok' => true, 'id' => $id));
