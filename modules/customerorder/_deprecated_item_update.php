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

$itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;

if ($itemId <= 0) {
    echo 'Не передан item_id';
    exit;
}

$result = $controller->updateItem($itemId, $_POST, $employeeId);

if (!$result['ok']) {
    error_log('CustomerOrder item_update error: ' . (isset($result['error']) ? $result['error'] : 'unknown'));
    header('Location: /customerorder?error=item_update');
    exit;
}

header('Location: /customerorder/edit?id=' . (int)$result['order_id']);
exit;