<?php
/**
 * Save Prom.ua mapping tables (status, delivery, payment).
 * Writes to existing site-wide mapping tables with prom-specific keys.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../auth/AuthService.php';

$user = \Papir\Crm\AuthService::getCurrentUser();
if (!$user || empty($user['is_admin'])) {
    echo json_encode(array('ok' => false, 'error' => 'Доступ заборонено'));
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$type  = isset($input['type']) ? $input['type'] : '';
$map   = isset($input['map'])  ? $input['map']  : array();

if (!in_array($type, array('status', 'delivery', 'payment'))) {
    echo json_encode(array('ok' => false, 'error' => 'Невідомий тип мепінгу'));
    exit;
}

$promSiteId = 3; // sites.site_id for prom

if ($type === 'status') {
    // Delete existing prom status mappings
    \Database::query('Papir',
        "DELETE FROM order_status_site_mapping WHERE site_id = " . $promSiteId
    );
    // Insert new
    foreach ($map as $papirCode => $promStatusId) {
        $papirCode = \Database::escape('Papir', $papirCode);
        $promStatusId = (int) $promStatusId;
        \Database::query('Papir',
            "INSERT INTO order_status_site_mapping (papir_code, site_id, site_status_id)
             VALUES ('{$papirCode}', {$promSiteId}, {$promStatusId})
             ON DUPLICATE KEY UPDATE site_status_id = {$promStatusId}"
        );
    }
}

if ($type === 'delivery') {
    // Delete existing prom delivery mappings
    \Database::query('Papir',
        "DELETE FROM site_delivery_method_map WHERE shipping_code LIKE 'prom.%'"
    );
    // Delivery code lookup
    $deliveryCodeMap = array(
        1 => 'pickup',
        2 => 'courier',
        3 => 'novaposhta.warehouse',
        4 => 'ukrposhta',
    );
    foreach ($map as $promCode => $deliveryMethodId) {
        $promCode = \Database::escape('Papir', $promCode);
        $deliveryMethodId = (int) $deliveryMethodId;
        $delCode = isset($deliveryCodeMap[$deliveryMethodId]) ? $deliveryCodeMap[$deliveryMethodId] : '';
        $delCode = \Database::escape('Papir', $delCode);
        \Database::query('Papir',
            "INSERT INTO site_delivery_method_map (shipping_code, delivery_method_id, delivery_code)
             VALUES ('{$promCode}', {$deliveryMethodId}, '{$delCode}')
             ON DUPLICATE KEY UPDATE delivery_method_id = {$deliveryMethodId}, delivery_code = '{$delCode}'"
        );
    }
}

if ($type === 'payment') {
    // Delete existing prom payment mappings
    \Database::query('Papir',
        "DELETE FROM site_payment_method_map WHERE payment_code LIKE 'prom.%'"
    );
    foreach ($map as $promCode => $paymentMethodId) {
        $promCode = \Database::escape('Papir', $promCode);
        $paymentMethodId = (int) $paymentMethodId;
        \Database::query('Papir',
            "INSERT INTO site_payment_method_map (payment_code, payment_method_id)
             VALUES ('{$promCode}', {$paymentMethodId})
             ON DUPLICATE KEY UPDATE payment_method_id = {$paymentMethodId}"
        );
    }
}

echo json_encode(array('ok' => true, 'type' => $type, 'count' => count($map)));