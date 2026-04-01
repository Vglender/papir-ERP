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


// ── Traffic source (Google Analytics / remarketing) ──────────────────────
$trafficSource = null;
if ($id > 0 && !empty($result['order']['number'])) {
    $num = $result['order']['number'];
    if (preg_match('/^(\d+)(OFF|MFF)$/i', $num, $m)) {
        $ocOrderId = (int)$m[1];
        $dbAlias   = strtolower($m[2]) === 'off' ? 'off' : 'mff';
        $tr = Database::fetchRow($dbAlias,
            "SELECT utm_source, utm_medium, utm_campaign, gclid, fbclid, ga4_uuid
             FROM oc_remarketing_orders WHERE order_id = {$ocOrderId} LIMIT 1");
        if ($tr['ok'] && !empty($tr['row'])) {
            $row = $tr['row'];
            // Визначаємо канал
            if (!empty($row['gclid'])) {
                $channel = 'google_ads';
                $label   = 'Google Ads';
                $color   = '#4285f4';
                $bg      = '#e8f0fe';
            } elseif (!empty($row['fbclid'])) {
                $channel = 'facebook';
                $label   = 'Facebook Ads';
                $color   = '#1877f2';
                $bg      = '#e7f0ff';
            } elseif (!empty($row['utm_source'])) {
                $src     = $row['utm_source'];
                $med     = $row['utm_medium'];
                if (stripos($src, 'google') !== false && stripos($med, 'organic') !== false) {
                    $channel = 'organic_google'; $label = 'Google Organic'; $color = '#16a34a'; $bg = '#f0fdf4';
                } elseif (stripos($src, 'facebook') !== false || stripos($src, 'instagram') !== false) {
                    $channel = 'social'; $label = ucfirst($src); $color = '#9333ea'; $bg = '#faf5ff';
                } else {
                    $channel = 'utm'; $label = $src . ($med ? '/' . $med : ''); $color = '#ea580c'; $bg = '#fff7ed';
                }
            } elseif (!empty($row['ga4_uuid'])) {
                $channel = 'direct'; $label = 'Прямий'; $color = '#6b7280'; $bg = '#f3f4f6';
            }
            if (isset($channel)) {
                $trafficSource = array(
                    'channel'  => $channel,
                    'label'    => $label,
                    'color'    => $color,
                    'bg'       => $bg,
                    'utm_source'   => $row['utm_source'],
                    'utm_medium'   => $row['utm_medium'],
                    'utm_campaign' => $row['utm_campaign'],
                    'gclid'    => $row['gclid'],
                    'ga4_uuid' => $row['ga4_uuid'],
                );
            }
        }
    }
}

// Подключаем шаблон
require __DIR__ . '/views/edit.php';