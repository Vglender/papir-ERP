<?php
/**
 * GET /counterparties/api/get_demand_detail?demand_id=X
 * Returns demand header + items + payment info (own payments or order fallback).
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

$demandId = isset($_GET['demand_id']) ? (int)$_GET['demand_id'] : 0;
if ($demandId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'demand_id required'));
    exit;
}

// ── Demand header ─────────────────────────────────────────────────────────────
$rDemand = \Database::fetchRow('Papir',
    "SELECT d.id, d.id_ms, d.number, d.status, d.sum_total, d.sum_vat,
            d.sum_paid, d.moment, d.description, d.customerorder_id, d.updated_at
     FROM demand d
     WHERE d.id = {$demandId} AND d.deleted_at IS NULL LIMIT 1");

if (!$rDemand['ok'] || empty($rDemand['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Demand not found'));
    exit;
}
$demand = $rDemand['row'];

// ── Items ─────────────────────────────────────────────────────────────────────
$rItems = \Database::fetchAll('Papir',
    "SELECT di.id, di.demand_id, di.line_no, di.product_id, di.product_name, di.sku,
            di.quantity, di.price, di.discount_percent, di.vat_rate, di.sum_row,
            di.shipped_quantity, di.reserve,
            COALESCE(NULLIF(di.product_name,''),
                     NULLIF(pd_uk.name,''), NULLIF(pd_ru.name,''), '') AS name,
            COALESCE(NULLIF(di.sku,''), pp.product_article, '') AS article
     FROM demand_item di
     LEFT JOIN product_papir pp ON pp.product_id = di.product_id
     LEFT JOIN product_description pd_uk ON pd_uk.product_id = di.product_id AND pd_uk.language_id = 2
     LEFT JOIN product_description pd_ru ON pd_ru.product_id = di.product_id AND pd_ru.language_id = 1
     WHERE di.demand_id = {$demandId}
     ORDER BY di.line_no ASC");
$items = ($rItems['ok']) ? $rItems['rows'] : array();

// ── Own payments linked to this demand via document_link ──────────────────────
$ownPayments = array();
if (!empty($demand['id_ms'])) {
    $idMs = \Database::escape('Papir', $demand['id_ms']);
    $rBank = \Database::fetchAll('Papir',
        "SELECT fb.id, 'bank' AS source, fb.doc_number, fb.moment, fb.operations AS amount
         FROM document_link dl
         JOIN finance_bank fb ON fb.id_ms = dl.from_ms_id
         WHERE dl.from_type = 'paymentin'
           AND dl.to_type   = 'demand'
           AND dl.to_ms_id  = '{$idMs}'
           AND fb.is_posted = 1");
    $rCash = \Database::fetchAll('Papir',
        "SELECT fc.id, 'cash' AS source, fc.doc_number, fc.moment, fc.operations AS amount
         FROM document_link dl
         JOIN finance_cash fc ON fc.id_ms = dl.from_ms_id
         WHERE dl.from_type = 'cashin'
           AND dl.to_type   = 'demand'
           AND dl.to_ms_id  = '{$idMs}'
           AND fc.is_posted = 1");
    if ($rBank['ok']) foreach ($rBank['rows'] as $p) { $ownPayments[] = $p; }
    if ($rCash['ok']) foreach ($rCash['rows'] as $p) { $ownPayments[] = $p; }
}

// ── Parent order payment_status (fallback) ────────────────────────────────────
$orderPaymentStatus = null;
if (!empty($demand['customerorder_id'])) {
    $orderId = (int)$demand['customerorder_id'];
    $rOrd = \Database::fetchRow('Papir',
        "SELECT payment_status FROM customerorder WHERE id = {$orderId} LIMIT 1");
    if ($rOrd['ok'] && !empty($rOrd['row'])) {
        $orderPaymentStatus = $rOrd['row']['payment_status'];
    }
}

echo json_encode(array(
    'ok'                   => true,
    'demand'               => $demand,
    'items'                => $items,
    'own_payments'         => $ownPayments,
    'order_payment_status' => $orderPaymentStatus,
));
