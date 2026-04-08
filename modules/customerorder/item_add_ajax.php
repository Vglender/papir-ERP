<?php

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/customerorder_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$repository = new CustomerOrderRepository();
$service = new CustomerOrderService($repository);
$controller = new CustomerOrderController($service);

$currentUser = \Papir\Crm\AuthService::getCurrentUser();
if (!$currentUser) {
    echo json_encode(array('ok' => false, 'error' => 'unauthorized'), JSON_UNESCAPED_UNICODE);
    exit;
}
$employeeId = (int)$currentUser['employee_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array(
        'ok' => false,
        'error' => 'Method not allowed'
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

$orderId = isset($_POST['customerorder_id']) ? (int)$_POST['customerorder_id'] : 0;

if ($orderId <= 0) {
    echo json_encode(array(
        'ok' => false,
        'error' => 'Не передан customerorder_id'
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

$result = $controller->addItem($orderId, $_POST, $employeeId);

echo json_encode($result, JSON_UNESCAPED_UNICODE);
exit;