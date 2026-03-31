<?php
/**
 * POST /counterparties/api/save_return_logistics
 * Register a return for an order. Scenarios:
 *   novaposhta_ttn   — manual NP return TTN number entry
 *   ukrposhta_ttn    — manual UP return TTN number entry
 *   manual           — other method (courier, in person, etc.)
 *   left_with_client — item left with client (defect, goodwill); closed immediately
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

$orderId     = isset($_POST['order_id'])     ? (int)$_POST['order_id']          : 0;
$returnType  = isset($_POST['return_type'])  ? trim($_POST['return_type'])       : '';
$ttnNumber   = isset($_POST['ttn_number'])   ? trim($_POST['ttn_number'])        : '';
$description = isset($_POST['description'])  ? trim($_POST['description'])       : '';

$allowed = array('novaposhta_ttn', 'ukrposhta_ttn', 'manual', 'left_with_client');
if ($orderId <= 0 || !in_array($returnType, $allowed)) {
    echo json_encode(array('ok' => false, 'error' => 'Invalid params'));
    exit;
}

// TTN type requires a number
if (($returnType === 'novaposhta_ttn' || $returnType === 'ukrposhta_ttn') && $ttnNumber === '') {
    echo json_encode(array('ok' => false, 'error' => 'Введіть номер ТТН'));
    exit;
}

// Verify order exists
$rOrder = \Database::fetchRow('Papir',
    "SELECT id, status FROM customerorder WHERE id={$orderId} AND deleted_at IS NULL");
if (!$rOrder['ok'] || empty($rOrder['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Order not found'));
    exit;
}

// Build record
$data = array(
    'customerorder_id' => $orderId,
    'return_type'      => $returnType,
    'status'           => ($returnType === 'left_with_client') ? 'received' : 'expected',
);

if ($returnType === 'novaposhta_ttn' || $returnType === 'ukrposhta_ttn') {
    $data['return_ttn_number'] = $ttnNumber;
    if ($description !== '') {
        $data['comment'] = $description;
    }
} elseif ($returnType === 'manual') {
    if ($description !== '') {
        $data['manual_description'] = $description;
    }
} elseif ($returnType === 'left_with_client') {
    $data['received_at'] = date('Y-m-d');
    if ($description !== '') {
        $data['manual_description'] = $description;
    }
}

$rIns = \Database::insert('Papir', 'return_logistics', $data);
if (!$rIns['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'DB error'));
    exit;
}

// Log in order history
$typeLabels = array(
    'novaposhta_ttn'   => 'Зворотна ТТН НП',
    'ukrposhta_ttn'    => 'Зворотна ТТН УП',
    'manual'           => 'Інший спосіб',
    'left_with_client' => 'Залишили клієнту',
);
$comment = 'Повернення: ' . (isset($typeLabels[$returnType]) ? $typeLabels[$returnType] : $returnType);
if ($ttnNumber !== '') $comment .= ' #' . $ttnNumber;
if ($description !== '') $comment .= ' — ' . $description;

\Database::insert('Papir', 'customerorder_history', array(
    'customerorder_id' => $orderId,
    'event_type'       => 'return_registered',
    'field_name'       => 'return_type',
    'new_value'        => $returnType,
    'is_auto'          => 0,
    'comment'          => $comment,
));

\Papir\Crm\AuthService::log('return_registered', 'customerorder', $orderId, $returnType);

echo json_encode(array('ok' => true));