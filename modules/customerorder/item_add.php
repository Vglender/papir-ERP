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

$orderId = isset($_POST['customerorder_id']) ? (int)$_POST['customerorder_id'] : 0;

if ($orderId <= 0) {
    echo 'Не передан customerorder_id';
    exit;
}

$result = $controller->addItem($orderId, $_POST, $employeeId);

if (!$result['ok']) {
    echo '<pre>';
    print_r($result);
    echo '</pre>';
    exit;
}

header('Location: /customerorder/edit?id=' . $orderId);
exit;