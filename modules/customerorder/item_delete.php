<?php

require_once __DIR__ . '/customerorder_bootstrap.php';

$repository = new CustomerOrderRepository();
$service = new CustomerOrderService($repository);
$controller = new CustomerOrderController($service);

// Временно
$employeeId = 1;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /customerorder');
    exit;
}

$itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;

if ($itemId <= 0) {
    echo 'Не передан item_id';
    exit;
}

$result = $controller->deleteItem($itemId, $employeeId);

if (!$result['ok']) {
    echo '<pre>';
    print_r($result);
    echo '</pre>';
    exit;
}

header('Location: /customerorder/edit?id=' . (int)$result['order_id']);
exit;