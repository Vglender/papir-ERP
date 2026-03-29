<?php
/**
 * POST /counterparties/api/save_order_status
 * Quick status update for an order from the workspace panel
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

$orderId     = isset($_POST['order_id'])    ? (int)$_POST['order_id']          : 0;
$status      = isset($_POST['status'])      ? trim($_POST['status'])            : null;
$description = isset($_POST['description']) ? trim($_POST['description'])       : null;

if ($orderId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'Invalid params'));
    exit;
}

$data = array();

if ($status !== null) {
    $allowed = array('draft','new','confirmed','in_progress','waiting_payment','paid','shipped','completed','cancelled');
    if (!in_array($status, $allowed)) {
        echo json_encode(array('ok' => false, 'error' => 'Invalid status'));
        exit;
    }
    $data['status'] = $status;
}

if ($description !== null) {
    $data['description'] = $description;
}

if (empty($data)) {
    echo json_encode(array('ok' => false, 'error' => 'Nothing to update'));
    exit;
}

$r = \Database::update('Papir', 'customerorder',
    $data,
    array('id' => $orderId)
);

if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'DB error'));
    exit;
}

if ($status !== null) {
    \Papir\Crm\AuthService::log('status_change', 'customerorder', $orderId, $status);
}
echo json_encode(array('ok' => true));
