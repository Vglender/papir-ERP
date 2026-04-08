<?php

require_once __DIR__ . '/customerorder_bootstrap.php';

$repository = new CustomerOrderRepository();
$service = new CustomerOrderService($repository);
$controller = new CustomerOrderController($service);

$currentUser = \Papir\Crm\AuthService::getCurrentUser();
if (!$currentUser) {
    header('Location: /login');
    exit;
}
$employeeId = (int)$currentUser['employee_id'];

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
    error_log('CustomerOrder item_add error: ' . (isset($result['error']) ? $result['error'] : 'unknown'));
    header('Location: /customerorder/edit?id=' . $orderId . '&error=item_add');
    exit;
}

header('Location: /customerorder/edit?id=' . $orderId);
exit;