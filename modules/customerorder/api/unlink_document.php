<?php
/**
 * POST /customerorder/api/unlink_document
 * Removes a document_link between a document and a customerorder.
 *
 * Params:
 *   order_id  — customerorder.id
 *   doc_type  — e.g. demand, paymentin, cashin, ttn_np, salesreturn
 *   doc_id    — document's numeric id
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../customerorder_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$docType = isset($_POST['doc_type']) ? trim($_POST['doc_type']) : '';
$docId   = isset($_POST['doc_id'])   ? (int)$_POST['doc_id']   : 0;

if ($orderId <= 0 || $docType === '' || $docId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'order_id, doc_type, doc_id required'));
    exit;
}

$allowedTypes = array('demand', 'paymentin', 'cashin', 'salesreturn', 'ttn_np', 'ttn_up');
if (!in_array($docType, $allowedTypes)) {
    echo json_encode(array('ok' => false, 'error' => 'Unsupported doc_type'));
    exit;
}

$dtEsc = Database::escape('Papir', $docType);
$r = Database::query('Papir',
    "DELETE FROM document_link
     WHERE from_type='{$dtEsc}' AND from_id={$docId}
       AND to_type='customerorder' AND to_id={$orderId}
     LIMIT 1");

if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'DB error'));
    exit;
}

echo json_encode(array('ok' => true, 'deleted' => $r['affected_rows']));
