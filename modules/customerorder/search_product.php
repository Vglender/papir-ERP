<?php

require_once __DIR__ . '/customerorder_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$repository = new CustomerOrderRepository();
$service = new CustomerOrderService($repository);
$controller = new CustomerOrderController($service);

$result = $controller->searchProducts($_GET);

if (!$result['ok']) {
    echo json_encode(array(
        'ok' => false,
        'error' => isset($result['error']) ? $result['error'] : 'Search error',
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(array(
    'ok' => true,
    'items' => $result['rows'],
), JSON_UNESCAPED_UNICODE);
exit;