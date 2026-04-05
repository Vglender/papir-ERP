<?php
/**
 * POST /customerorder/api/save_order_delivery
 * Creates or updates an order_delivery record (for pickup, courier, etc.)
 *
 * Params:
 *   id?                — existing id to update (0 = create)
 *   customerorder_id   — required
 *   delivery_method_id — required
 *   status             — pending|sent|delivered|cancelled (default: pending)
 *   sent_at?           — datetime
 *   delivered_at?      — datetime
 *   comment?
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../customerorder_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$id         = isset($_POST['id'])                   ? (int)$_POST['id']                   : 0;
$orderId    = isset($_POST['customerorder_id'])      ? (int)$_POST['customerorder_id']      : 0;
$methodId   = isset($_POST['delivery_method_id'])    ? (int)$_POST['delivery_method_id']    : 0;
$status     = isset($_POST['status'])               ? trim($_POST['status'])               : 'pending';
$sentAt     = isset($_POST['sent_at'])    && trim($_POST['sent_at'])    !== '' ? trim($_POST['sent_at'])    : null;
$deliveredAt= isset($_POST['delivered_at']) && trim($_POST['delivered_at']) !== '' ? trim($_POST['delivered_at']) : null;
$comment    = isset($_POST['comment'])    ? trim($_POST['comment'])    : '';

if ($orderId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'customerorder_id required'));
    exit;
}
if ($methodId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'delivery_method_id required'));
    exit;
}

$allowedStatuses = array('pending', 'sent', 'delivered', 'cancelled');
if (!in_array($status, $allowedStatuses)) $status = 'pending';

$data = array(
    'customerorder_id'   => $orderId,
    'delivery_method_id' => $methodId,
    'status'             => $status,
    'comment'            => $comment,
);
if ($sentAt !== null)      $data['sent_at']      = $sentAt;
if ($deliveredAt !== null) $data['delivered_at'] = $deliveredAt;

if ($id > 0) {
    $r = Database::update('Papir', 'order_delivery', $data, array('id' => $id));
    if (!$r['ok']) {
        echo json_encode(array('ok' => false, 'error' => 'DB error'));
        exit;
    }
    $newId = $id;
} else {
    $r = Database::insert('Papir', 'order_delivery', $data);
    if (!$r['ok']) {
        echo json_encode(array('ok' => false, 'error' => 'DB error'));
        exit;
    }
    $newId = (int)$r['insert_id'];
}

echo json_encode(array('ok' => true, 'id' => $newId));
