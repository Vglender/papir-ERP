<?php

require_once __DIR__ . '/customerorder_bootstrap.php';

$repository = new CustomerOrderRepository();
$service = new CustomerOrderService($repository);
$controller = new CustomerOrderController($service);

$result = $controller->index($_GET);

require __DIR__ . '/views/registry.php';