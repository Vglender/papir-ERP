<?php
// /var/www/papir/modules/customerorder/edit.php

require_once __DIR__ . '/customerorder_bootstrap.php';
require_once __DIR__ . '/customerorder_helpers.php'; // Исправлено! добавил /

$repository = new CustomerOrderRepository();
$service = new CustomerOrderService($repository);
$controller = new CustomerOrderController($service);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $result = $controller->edit($id);
} else {
    $result = array(
        'ok' => true,
        'order' => array(
            'status' => 'draft',
            'payment_status' => 'not_paid',
            'shipment_status' => 'not_shipped',
            'applicable' => 1,
            'currency_code' => 'UAH',
            'sales_channel' => 'manual',
        ),
        'items' => array(),
        'attributes' => array(),
        'history' => array(),
    );
}

// Загружаем справочники
$organizations = Database::fetchAll('Papir', "SELECT `id`, `name` FROM `organization` WHERE `status` = 1 ORDER BY `name` ASC");
$organizations = $organizations['ok'] ? $organizations['rows'] : array();

$stores = Database::fetchAll('Papir', "SELECT `id`, `name` FROM `store` WHERE `status` = 1 ORDER BY `name` ASC");
$stores = $stores['ok'] ? $stores['rows'] : array();

$employees = Database::fetchAll('Papir', "SELECT `id`, `full_name` FROM `employee` WHERE `status` = 1 ORDER BY `full_name` ASC");
$employees = $employees['ok'] ? $employees['rows'] : array();

$contracts = Database::fetchAll('Papir', "SELECT `id`, `number`, `title` FROM `contract` WHERE `status` = 'active' ORDER BY `number` ASC");
$contracts = $contracts['ok'] ? $contracts['rows'] : array();

$counterparties = Database::fetchAll('Papir', "SELECT `id`, `name` FROM `counterparty` WHERE `status` = 1 AND `type` IN ('company', 'fop') ORDER BY `name` ASC");
$counterparties = $counterparties['ok'] ? $counterparties['rows'] : array();

$contactPersons = Database::fetchAll('Papir', "SELECT `id`, `name` FROM `counterparty` WHERE `status` = 1 AND `type` = 'person' ORDER BY `name` ASC");
$contactPersons = $contactPersons['ok'] ? $contactPersons['rows'] : array();

// ИСПОЛЬЗУЕМ HELPER для банковских счетов
$organizationBankAccounts = getOrganizationBankAccounts(
    !empty($result['order']['organization_id']) ? $result['order']['organization_id'] : null
);

// Валюты и каналы продаж (можно тоже вынести в helpers)
$currencies = array(
    array('code' => 'UAH', 'name' => 'Гривня'),
    array('code' => 'EUR', 'name' => 'Євро'),
    array('code' => 'USD', 'name' => 'Долар'),
);

$salesChannels = array(
    array('code' => 'manual', 'name' => 'Ручне введення'),
    array('code' => 'site', 'name' => 'Сайт'),
    array('code' => 'marketplace', 'name' => 'Маркетплейс'),
    array('code' => 'api', 'name' => 'API'),
);

if (!isset($organizationBankAccounts)) {
    $organizationBankAccounts = array();
}

// Подключаем шаблон
require __DIR__ . '/views/edit.php';