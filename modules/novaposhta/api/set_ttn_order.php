<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$ttnId      = isset($_POST['ttn_id'])      ? (int)$_POST['ttn_id'] : 0;
$orderQuery = isset($_POST['order_query']) ? trim($_POST['order_query']) : '';
// Backward compat: old JS may still send order_id
if ($orderQuery === '' && isset($_POST['order_id'])) {
    $orderQuery = trim($_POST['order_id']);
}

if (!$ttnId) {
    echo json_encode(array('ok' => false, 'error' => 'ttn_id required'));
    exit;
}

// Verify TTN exists
$rTtn = \Database::fetchRow('Papir', "SELECT id FROM ttn_novaposhta WHERE id = {$ttnId} LIMIT 1");
if (!$rTtn['ok'] || !$rTtn['row']) {
    echo json_encode(array('ok' => false, 'error' => 'TTN not found'));
    exit;
}

// Resolve order: by id (pure number) or by number (e.g. "98321OFF")
$orderId    = 0;
$orderNumber = null;
if ($orderQuery !== '') {
    if (ctype_digit($orderQuery)) {
        // Pure numeric — search by id
        $rOrder = \Database::fetchRow('Papir',
            "SELECT id, number FROM customerorder WHERE id = " . (int)$orderQuery . " LIMIT 1");
    } else {
        // Contains letters — search by number
        $safe = \Database::escape('Papir', $orderQuery);
        $rOrder = \Database::fetchRow('Papir',
            "SELECT id, number FROM customerorder WHERE number = '{$safe}' LIMIT 1");
    }
    if (!$rOrder['ok'] || !$rOrder['row']) {
        echo json_encode(array('ok' => false, 'error' => 'Замовлення "' . htmlspecialchars($orderQuery) . '" не знайдено'));
        exit;
    }
    $orderId     = (int)$rOrder['row']['id'];
    $orderNumber = $rOrder['row']['number'];
}

// Оновлюємо customerorder_id в ttn_novaposhta
\Database::update('Papir', 'ttn_novaposhta',
    array('customerorder_id' => $orderId > 0 ? $orderId : null),
    array('id' => $ttnId)
);

// Видаляємо попередній document_link якщо є
\Database::query('Papir',
    "DELETE FROM document_link WHERE from_type='ttn_np' AND from_id={$ttnId} AND to_type='customerorder'"
);

// Створюємо новий document_link (якщо прив'язуємо до заказу)
if ($orderId > 0) {
    \Database::insert('Papir', 'document_link', array(
        'from_type' => 'ttn_np',
        'from_id'   => $ttnId,
        'to_type'   => 'customerorder',
        'to_id'     => $orderId,
        'link_type' => 'shipment',
    ));

    // Clear "ship" next_action since TTN is now linked
    \Database::query('Papir',
        "UPDATE customerorder SET next_action=NULL, next_action_label=NULL, updated_at=NOW()
         WHERE id={$orderId} AND next_action='ship'");
}

echo json_encode(array('ok' => true, 'order_id' => $orderId, 'order_number' => $orderNumber));
