<?php
/**
 * GET /counterparties/api/get_orders?id=COUNTERPARTY_ID&limit=20
 * Returns recent orders for the orders tab in workspace
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

$cpId  = isset($_GET['id'])    ? (int)$_GET['id']    : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if ($cpId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'id required'));
    exit;
}
if ($limit < 1 || $limit > 100) $limit = 20;

$statusLabels = array(
    'draft'           => 'Чернетка',
    'new'             => 'Новий',
    'confirmed'       => 'Підтверджено',
    'in_progress'     => 'В роботі',
    'waiting_payment' => 'Очікує оплату',
    'paid'            => 'Оплачено',
    'shipped'         => 'Відвантажено',
    'completed'       => 'Виконано',
    'cancelled'       => 'Скасовано',
);

$r = Database::fetchAll('Papir',
    "SELECT id, number, moment, sum_total, status, payment_status, shipment_status, comment
     FROM customerorder
     WHERE counterparty_id = {$cpId} AND deleted_at IS NULL
     ORDER BY moment DESC
     LIMIT {$limit}");

$orders = array();
if ($r['ok']) {
    foreach ($r['rows'] as $o) {
        $status = $o['status'];

        // Визначення джерела трафіку з oc_remarketing_orders
        $trafficSource = null;
        $num = $o['number'];
        if (preg_match('/^(\d+)(OFF|MFF)$/i', $num, $m)) {
            $ocOrderId = (int)$m[1];
            $dbAlias   = strtolower($m[2]) === 'off' ? 'off' : 'mff';
            $rm = Database::fetchRow($dbAlias,
                "SELECT gclid, fbclid, utm_source, utm_medium, utm_campaign
                 FROM oc_remarketing_orders WHERE order_id = {$ocOrderId} LIMIT 1");
            if ($rm['ok'] && !empty($rm['row'])) {
                $row = $rm['row'];
                if (!empty($row['gclid'])) {
                    $trafficSource = array('label' => 'Google Ads', 'color' => '#1a73e8', 'campaign' => $row['utm_campaign']);
                } elseif (!empty($row['fbclid'])) {
                    $trafficSource = array('label' => 'Facebook Ads', 'color' => '#1877f2', 'campaign' => $row['utm_campaign']);
                } elseif (!empty($row['utm_source'])) {
                    $src = strtolower($row['utm_source']);
                    if (strpos($src, 'google') !== false) {
                        $trafficSource = array('label' => 'Google', 'color' => '#34a853', 'campaign' => $row['utm_campaign']);
                    } elseif (strpos($src, 'facebook') !== false || strpos($src, 'fb') !== false) {
                        $trafficSource = array('label' => 'Facebook', 'color' => '#1877f2', 'campaign' => $row['utm_campaign']);
                    } else {
                        $label = $row['utm_source'];
                        if (!empty($row['utm_medium'])) $label .= ' / ' . $row['utm_medium'];
                        $trafficSource = array('label' => $label, 'color' => '#6b7280', 'campaign' => $row['utm_campaign']);
                    }
                }
            }
        }

        $orders[] = array(
            'id'              => (int)$o['id'],
            'number'          => $o['number'],
            'moment'          => $o['moment'],
            'sum_total'       => (float)$o['sum_total'],
            'status'          => $status,
            'status_label'    => isset($statusLabels[$status]) ? $statusLabels[$status] : $status,
            'payment_status'  => $o['payment_status'],
            'shipment_status' => $o['shipment_status'],
            'traffic_source'  => $trafficSource,
        );
    }
}

echo json_encode(array('ok' => true, 'orders' => $orders));
