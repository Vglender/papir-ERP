<?php

require_once __DIR__ . '/customerorder_bootstrap.php';

$repository = new CustomerOrderRepository();
$service = new CustomerOrderService($repository);
$controller = new CustomerOrderController($service);

// Временно можно захардкодить, потом подставите из авторизации
$employeeId = 1;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /customerorder');
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

// Получаем данные строк из items_json
$items = [];
if (!empty($_POST['items_json'])) {
    $items = json_decode($_POST['items_json'], true);
    // Логируем для отладки
    error_log("Items from frontend: " . print_r($items, true));
}

if ($id > 0) {
    // Сначала сохраняем шапку заказа
    $save = $controller->save($id, $_POST, $employeeId);
    
    if (!$save['ok']) {
        echo '<pre>';
        print_r($save);
        echo '</pre>';
        exit;
    }
    
    // ПОТОМ обновляем строки заказа, если они есть
    if (!empty($items)) {
        $updateItems = $service->updateItems($id, $items, $employeeId);
        
        if (!$updateItems['ok']) {
            echo '<pre>';
            print_r($updateItems);
            echo '</pre>';
            exit;
        }
        
        error_log("Items updated successfully for order #{$id}");
    }
    
    header('Location: /customerorder/edit?id=' . $id);
    exit;
}

// Создание нового заказа
$create = $controller->create($_POST, $employeeId);

if (!$create['ok']) {
    echo '<pre>';
    print_r($create);
    echo '</pre>';
    exit;
}

// После создания заказа, если есть строки, добавляем их
if (!empty($items) && isset($create['id'])) {
    $updateItems = $service->updateItems($create['id'], $items, $employeeId);
    
    if (!$updateItems['ok']) {
        echo '<pre>';
        print_r($updateItems);
        echo '</pre>';
        exit;
    }
}

header('Location: /customerorder/edit?id=' . (int)$create['id']);
exit;