<?php

require_once __DIR__ . '/customerorder_bootstrap.php';
require_once __DIR__ . '/../shared/CsrfService.php';

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

if (!CsrfService::verify()) {
    error_log('CSRF token mismatch in customerorder/save');
    header('Location: /customerorder?error=csrf');
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

// Получаем данные строк из items_json
$items = [];
if (!empty($_POST['items_json'])) {
    $items = json_decode($_POST['items_json'], true);
}

if ($id > 0) {
    // Сначала сохраняем шапку заказа
    $save = $controller->save($id, $_POST, $employeeId);
    
    if (!$save['ok']) {
        error_log('CustomerOrder save error: ' . (isset($save['error']) ? $save['error'] : 'unknown'));
        header('Location: /customerorder/edit?id=' . $id . '&error=save');
        exit;
    }
    
    // ПОТОМ обновляем строки заказа, если они есть
    if (!empty($items)) {
        $updateItems = $service->updateItems($id, $items, $employeeId);
        
        if (!$updateItems['ok']) {
            error_log('CustomerOrder items update error: ' . (isset($updateItems['error']) ? $updateItems['error'] : 'unknown'));
            header('Location: /customerorder/edit?id=' . $id . '&error=items');
            exit;
        }
    }
    
    header('Location: /customerorder/edit?id=' . $id);
    exit;
}

// Создание нового заказа
$create = $controller->create($_POST, $employeeId);

if (!$create['ok']) {
    error_log('CustomerOrder create error: ' . (isset($create['error']) ? $create['error'] : 'unknown'));
    header('Location: /customerorder?error=create');
    exit;
}

// После создания заказа, если есть строки, добавляем их
if (!empty($items) && isset($create['id'])) {
    $updateItems = $service->updateItems($create['id'], $items, $employeeId);
    
    if (!$updateItems['ok']) {
        error_log('CustomerOrder new order items error: ' . (isset($updateItems['error']) ? $updateItems['error'] : 'unknown'));
    }
}

header('Location: /customerorder/edit?id=' . (int)$create['id']);
exit;