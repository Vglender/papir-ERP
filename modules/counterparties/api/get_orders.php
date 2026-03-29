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
        $orders[] = array(
            'id'              => (int)$o['id'],
            'number'          => $o['number'],
            'moment'          => $o['moment'],
            'sum_total'       => (float)$o['sum_total'],
            'status'          => $status,
            'status_label'    => isset($statusLabels[$status]) ? $statusLabels[$status] : $status,
            'payment_status'  => $o['payment_status'],
            'shipment_status' => $o['shipment_status'],
        );
    }
}

echo json_encode(array('ok' => true, 'orders' => $orders));
