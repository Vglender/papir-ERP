<?php
// /var/www/papir/modules/customerorder/edit.php

require_once __DIR__ . '/customerorder_bootstrap.php';
require_once __DIR__ . '/customerorder_helpers.php'; // Исправлено! добавил /
require_once __DIR__ . '/../shared/StatusColors.php';

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

$projects = Database::fetchAll('Papir', "SELECT `id`, `name` FROM `project` WHERE `status` = 1 ORDER BY `name` ASC");
$projects = $projects['ok'] ? $projects['rows'] : array();

// Counterparty and contact person are loaded via picker (search API), not full list.
// For existing orders we just need the current counterparty name to display.
$counterpartyName    = '';
$contactPersonName   = '';
if (!empty($result['order']['counterparty_id'])) {
    $rCp = Database::fetchRow('Papir',
        "SELECT id, name FROM counterparty WHERE id = " . (int)$result['order']['counterparty_id'] . " LIMIT 1");
    if ($rCp['ok'] && !empty($rCp['row'])) $counterpartyName = $rCp['row']['name'];
}
if (!empty($result['order']['contact_person_id'])) {
    $rPerson = Database::fetchRow('Papir',
        "SELECT id, name FROM counterparty WHERE id = " . (int)$result['order']['contact_person_id'] . " LIMIT 1");
    if ($rPerson['ok'] && !empty($rPerson['row'])) $contactPersonName = $rPerson['row']['name'];
}

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

$rDMs = Database::fetchAll('Papir',
    "SELECT id, code, name_uk, has_ttn FROM delivery_method WHERE status=1 ORDER BY sort_order");
$deliveryMethods = $rDMs['ok'] ? $rDMs['rows'] : array();

$rPMs = Database::fetchAll('Papir',
    "SELECT id, code, name_uk FROM payment_method WHERE status=1 ORDER BY sort_order");
$paymentMethods = $rPMs['ok'] ? $rPMs['rows'] : array();

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

// Доступні переходи для створення документів із замовлення
$rTrans = Database::fetchAll('Papir',
    "SELECT dtt.`to_type`, dtt.`link_type`, dtt.`description`, dt.`name_uk`
     FROM `document_type_transition` dtt
     JOIN `document_type` dt ON dt.`code` = dtt.`to_type`
     WHERE dtt.`from_type` = 'customerorder'
     ORDER BY dt.`sort_order`");
$docTransitions = ($rTrans['ok'] && !empty($rTrans['rows'])) ? $rTrans['rows'] : array();

// Подключаем шаблон
require __DIR__ . '/views/edit.php';