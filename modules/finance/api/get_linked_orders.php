<?php
/**
 * GET /finance/api/get_linked_orders
 * Returns orders linked to a given payment.
 *
 * Params (GET):
 *   payment_id   — finance_bank or finance_cash ID
 *   payment_type — 'paymentin' or 'cashin'
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../finance_bootstrap.php';

$paymentId   = isset($_GET['payment_id'])   ? (int)$_GET['payment_id']       : 0;
$paymentType = isset($_GET['payment_type']) ? trim($_GET['payment_type'])     : '';

if ($paymentId <= 0 || !in_array($paymentType, array('paymentin', 'cashin'))) {
    echo json_encode(array('ok' => false, 'error' => 'payment_id and payment_type required'));
    exit;
}

$rLinks = Database::fetchAll('Papir',
    "SELECT dl.to_id, dl.linked_sum,
            co.number, co.moment, co.status, co.sum_total, co.payment_status, co.sum_paid
     FROM document_link dl
     JOIN customerorder co ON co.id = dl.to_id AND co.deleted_at IS NULL
     WHERE dl.from_type = '" . Database::escape('Papir', $paymentType) . "'
       AND dl.from_id   = {$paymentId}
       AND dl.to_type   = 'customerorder'
     ORDER BY co.moment DESC");

$rows = array();
if ($rLinks['ok']) {
    foreach ($rLinks['rows'] as $r) {
        $rows[] = array(
            'id'             => (int)$r['to_id'],
            'number'         => $r['number'] ?: ('#' . $r['to_id']),
            'moment'         => $r['moment'],
            'status'         => $r['status'],
            'sum_total'      => (float)$r['sum_total'],
            'sum_total_fmt'  => number_format((float)$r['sum_total'], 2, '.', ' ') . ' ₴',
            'linked_sum'     => (float)$r['linked_sum'],
            'linked_sum_fmt' => $r['linked_sum'] ? number_format((float)$r['linked_sum'], 2, '.', ' ') . ' ₴' : '',
            'payment_status' => $r['payment_status'],
        );
    }
}

echo json_encode(array('ok' => true, 'rows' => $rows));