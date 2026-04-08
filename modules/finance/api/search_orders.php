<?php
/**
 * GET /finance/api/search_orders
 * Search customerorder for linking to a payment.
 *
 * Params (GET):
 *   cp_id       — counterparty.id (filters by counterparty; if 0 — search all, but q is required)
 *   q           — order number search (optional when cp_id set, required otherwise)
 *   payment_id  — finance_bank or finance_cash ID (to exclude already linked)
 *   payment_type — 'paymentin' or 'cashin'
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../finance_bootstrap.php';

// НЕРАЗОБРАННОЕ counterparty id (МойСклад artifact for unmatched payments)
define('UNMATCHED_CP_ID', 28352);

$cpId        = isset($_GET['cp_id'])        ? (int)$_GET['cp_id']            : 0;
$q           = isset($_GET['q'])            ? trim($_GET['q'])                : '';
$paymentId   = isset($_GET['payment_id'])   ? (int)$_GET['payment_id']       : 0;
$paymentType = isset($_GET['payment_type']) ? trim($_GET['payment_type'])     : '';

// Treat НЕРАЗОБРАННОЕ as "no counterparty"
if ($cpId === UNMATCHED_CP_ID) {
    $cpId = 0;
}

// If no counterparty, require order number search
if ($cpId <= 0 && $q === '') {
    echo json_encode(array('ok' => true, 'rows' => array()));
    exit;
}

// Collect already-linked order IDs for this payment
$linkedOrderIds = array();
if ($paymentId > 0 && in_array($paymentType, array('paymentin', 'cashin'))) {
    $rLinked = Database::fetchAll('Papir',
        "SELECT to_id FROM document_link
         WHERE from_type='" . Database::escape('Papir', $paymentType) . "'
           AND from_id={$paymentId}
           AND to_type='customerorder'");
    if ($rLinked['ok']) {
        foreach ($rLinked['rows'] as $r) {
            $linkedOrderIds[] = (int)$r['to_id'];
        }
    }
}

$where = "co.deleted_at IS NULL";

if ($cpId > 0) {
    $where .= " AND co.counterparty_id = {$cpId}";
}

if (!empty($linkedOrderIds)) {
    $where .= " AND co.id NOT IN (" . implode(',', $linkedOrderIds) . ")";
}

if ($q !== '') {
    $escaped = Database::escape('Papir', $q);
    $where .= " AND co.number LIKE '%{$escaped}%'";
}

$r = Database::fetchAll('Papir',
    "SELECT co.id, co.number, co.moment, co.status, co.sum_total, co.payment_status,
            co.sum_paid, cp.name AS cp_name
     FROM customerorder co
     LEFT JOIN counterparty cp ON cp.id = co.counterparty_id
     WHERE {$where}
     ORDER BY co.moment DESC
     LIMIT 30");

$rows = array();
if ($r['ok']) {
    foreach ($r['rows'] as $row) {
        $item = array(
            'id'             => (int)$row['id'],
            'number'         => $row['number'] ?: ('#' . $row['id']),
            'moment'         => $row['moment'],
            'status'         => $row['status'],
            'sum_total'      => (float)$row['sum_total'],
            'sum_total_fmt'  => number_format((float)$row['sum_total'], 2, '.', ' ') . ' ₴',
            'payment_status' => $row['payment_status'],
            'sum_paid'       => (float)$row['sum_paid'],
        );
        // Include counterparty name when searching without cp_id filter
        if ($cpId <= 0 && !empty($row['cp_name'])) {
            $item['cp_name'] = $row['cp_name'];
        }
        $rows[] = $item;
    }
}

echo json_encode(array('ok' => true, 'rows' => $rows));