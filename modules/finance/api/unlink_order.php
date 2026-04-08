<?php
/**
 * POST /finance/api/unlink_order
 * Remove link between a payment and a customerorder.
 *
 * Params (POST):
 *   payment_id   — finance_bank or finance_cash ID
 *   payment_type — 'paymentin' or 'cashin'
 *   order_id     — customerorder.id
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../finance_bootstrap.php';
require_once __DIR__ . '/../../customerorder/services/OrderFinanceHelper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$paymentId   = isset($_POST['payment_id'])   ? (int)$_POST['payment_id']       : 0;
$paymentType = isset($_POST['payment_type']) ? trim($_POST['payment_type'])     : '';
$orderId     = isset($_POST['order_id'])     ? (int)$_POST['order_id']          : 0;

if ($paymentId <= 0 || $orderId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'payment_id and order_id required'));
    exit;
}
if (!in_array($paymentType, array('paymentin', 'cashin'))) {
    echo json_encode(array('ok' => false, 'error' => 'Invalid payment_type'));
    exit;
}

Database::query('Papir',
    "DELETE FROM document_link
     WHERE from_type = '" . Database::escape('Papir', $paymentType) . "'
       AND from_id   = {$paymentId}
       AND to_type   = 'customerorder'
       AND to_id     = {$orderId}");

// Recalculate order payment status
OrderFinanceHelper::recalc($orderId);

echo json_encode(array('ok' => true));