<?php
/**
 * POST /finance/api/link_order
 * Link a payment (bank/cash) to a customerorder via document_link.
 * If the payment has no real counterparty (НЕРАЗОБРАННОЕ or empty),
 * auto-fill counterparty from the order.
 *
 * Params (POST):
 *   payment_id   — finance_bank or finance_cash ID
 *   payment_type — 'paymentin' or 'cashin'
 *   order_id     — customerorder.id
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../finance_bootstrap.php';
require_once __DIR__ . '/../../customerorder/services/OrderFinanceHelper.php';

// НЕРАЗОБРАННОЕ counterparty id (МойСклад artifact for unmatched payments)
define('UNMATCHED_CP_ID', 28352);

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

// Check if already linked
$rExists = Database::exists('Papir', 'document_link', array(
    'from_type' => $paymentType,
    'from_id'   => $paymentId,
    'to_type'   => 'customerorder',
    'to_id'     => $orderId,
));
if ($rExists['ok'] && $rExists['exists']) {
    echo json_encode(array('ok' => true, 'already' => true));
    exit;
}

// Get payment data
$tbl = ($paymentType === 'cashin') ? 'finance_cash' : 'finance_bank';
$rPay = Database::fetchRow('Papir',
    "SELECT * FROM {$tbl} WHERE id = {$paymentId} AND direction = 'in' LIMIT 1");
if (!$rPay['ok'] || empty($rPay['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Payment not found'));
    exit;
}
$pay = $rPay['row'];
$linkedSum = (float)$pay['sum'];

$insResult = Database::insert('Papir', 'document_link', array(
    'from_type'  => $paymentType,
    'from_id'    => $paymentId,
    'to_type'    => 'customerorder',
    'to_id'      => $orderId,
    'link_type'  => 'payment',
    'linked_sum' => $linkedSum,
));

if (!$insResult['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'Не вдалось зберегти привʼязку: ' . (isset($insResult['error']) ? $insResult['error'] : 'unknown')));
    exit;
}

// Recalculate order payment status
OrderFinanceHelper::recalc($orderId);

// ── Auto-fill counterparty from order if payment is unmatched ────────────
$cpUpdated = false;

// Get order's counterparty
$rOrder = Database::fetchRow('Papir',
    "SELECT co.counterparty_id, cp.name AS cp_name, cp.id_ms AS cp_id_ms
     FROM customerorder co
     JOIN counterparty cp ON cp.id = co.counterparty_id
     WHERE co.id = {$orderId} AND co.deleted_at IS NULL LIMIT 1");
$orderCpId   = ($rOrder['ok'] && $rOrder['row']) ? (int)$rOrder['row']['counterparty_id'] : 0;
$orderCpName = ($rOrder['ok'] && $rOrder['row']) ? (string)$rOrder['row']['cp_name']      : '';
$orderCpMs   = ($rOrder['ok'] && $rOrder['row']) ? (string)$rOrder['row']['cp_id_ms']     : '';

if ($orderCpId > 0 && $orderCpId !== UNMATCHED_CP_ID) {
    if ($paymentType === 'paymentin') {
        // finance_bank: локальний cp_id — джерело правди
        $currentCpId = !empty($pay['cp_id']) ? (int)$pay['cp_id'] : 0;
        if ($currentCpId === 0 || $currentCpId === UNMATCHED_CP_ID) {
            Database::update('Papir', 'finance_bank',
                array('cp_id' => $orderCpId),
                array('id' => $paymentId));
            $cpUpdated = true;
        }
    } else {
        // finance_cash: локальний counterparty_id — джерело правди.
        // Заповнюємо також agent_ms (маппінг), якщо локальний cp має id_ms.
        $currentCpId = !empty($pay['counterparty_id']) ? (int)$pay['counterparty_id'] : 0;
        if ($currentCpId === 0 || $currentCpId === UNMATCHED_CP_ID) {
            $upd = array('counterparty_id' => $orderCpId);
            if ($orderCpMs !== '') {
                $upd['agent_ms'] = $orderCpMs;
            }
            Database::update('Papir', 'finance_cash', $upd, array('id' => $paymentId));
            $cpUpdated = true;
        }
    }
}

$resp = array('ok' => true);
if ($cpUpdated) {
    $resp['cp_updated'] = true;
    $resp['cp_id']      = $orderCpId;
    $resp['cp_name']    = $orderCpName;
}
echo json_encode($resp);