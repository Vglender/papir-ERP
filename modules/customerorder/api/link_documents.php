<?php
/**
 * POST /customerorder/api/link_documents
 * Creates document_link records to manually link existing documents to an order.
 *
 * Params:
 *   order_id  — customerorder.id
 *   docs      — JSON: [{"type":"demand","id":123}, ...]
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../customerorder_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$orderId  = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$docsJson = isset($_POST['docs'])     ? trim($_POST['docs'])     : '';

if ($orderId <= 0 || $docsJson === '') {
    echo json_encode(array('ok' => false, 'error' => 'order_id and docs required'));
    exit;
}

$docs = json_decode($docsJson, true);
if (!is_array($docs) || empty($docs)) {
    echo json_encode(array('ok' => false, 'error' => 'Invalid docs'));
    exit;
}

// Load link_type map from document_type_transition
$rTrans = Database::fetchAll('Papir',
    "SELECT to_type, link_type FROM document_type_transition WHERE from_type='customerorder'");
$linkTypeMap = array();
if ($rTrans['ok']) {
    foreach ($rTrans['rows'] as $r) {
        $linkTypeMap[$r['to_type']] = $r['link_type'];
    }
}

// Load amounts for linked docs
function ld_docAmount($type, $id) {
    if ($type === 'demand') {
        $r = Database::fetchRow('Papir', "SELECT sum_total FROM demand WHERE id=" . (int)$id . " LIMIT 1");
        return ($r['ok'] && $r['row']) ? (float)$r['row']['sum_total'] : 0.0;
    }
    if ($type === 'paymentin') {
        $r = Database::fetchRow('Papir', "SELECT sum FROM finance_bank WHERE id=" . (int)$id . " AND direction='in' LIMIT 1");
        return ($r['ok'] && $r['row']) ? (float)$r['row']['sum'] : 0.0;
    }
    if ($type === 'cashin') {
        $r = Database::fetchRow('Papir', "SELECT sum FROM finance_cash WHERE id=" . (int)$id . " AND direction='in' LIMIT 1");
        return ($r['ok'] && $r['row']) ? (float)$r['row']['sum'] : 0.0;
    }
    if ($type === 'salesreturn') {
        $r = Database::fetchRow('Papir', "SELECT sum_total FROM salesreturn WHERE id=" . (int)$id . " LIMIT 1");
        return ($r['ok'] && $r['row']) ? (float)$r['row']['sum_total'] : 0.0;
    }
    return 0.0;
}

$linked = 0;
$allowedTypes = array('demand', 'paymentin', 'cashin', 'salesreturn', 'ttn_np', 'ttn_up');

foreach ($docs as $doc) {
    $docType = isset($doc['type']) ? trim((string)$doc['type']) : '';
    $docId   = isset($doc['id'])   ? (int)$doc['id']           : 0;

    if (!in_array($docType, $allowedTypes) || $docId <= 0) continue;

    // Skip if already linked
    $rExists = Database::exists('Papir', 'document_link', array(
        'from_type' => $docType,
        'from_id'   => $docId,
        'to_type'   => 'customerorder',
        'to_id'     => $orderId,
    ));
    if ($rExists['ok'] && $rExists['exists']) continue;

    $linkType   = isset($linkTypeMap[$docType]) ? $linkTypeMap[$docType] : 'manual';
    $linkedSum  = ld_docAmount($docType, $docId);

    Database::insert('Papir', 'document_link', array(
        'from_type'  => $docType,
        'from_id'    => $docId,
        'to_type'    => 'customerorder',
        'to_id'      => $orderId,
        'link_type'  => $linkType,
        'linked_sum' => $linkedSum > 0 ? $linkedSum : null,
    ));
    $linked++;
}

// If TTN was linked: clear next_action='ship' and fire order_ttn_created scenarios.
// ttn_np — повністю обробляється (сценарії + повідомлення клієнту).
// ttn_up — TODO: коли підключимо Укрпошту, додати окремий fire.
if ($linked > 0) {
    $linkedTtnNpIds = array();
    foreach ($docs as $doc) {
        if (!isset($doc['type']) || !isset($doc['id'])) continue;
        if ($doc['type'] === 'ttn_np') {
            $linkedTtnNpIds[] = (int)$doc['id'];
        }
    }

    if (!empty($linkedTtnNpIds)) {
        // Clear "ship" next_action
        Database::query('Papir',
            "UPDATE customerorder
             SET next_action = NULL, next_action_label = NULL, updated_at = NOW()
             WHERE id = {$orderId} AND next_action = 'ship'");

        // Fire order_ttn_created для кожної прив'язаної НП ТТН
        require_once __DIR__ . '/../../novaposhta/services/TtnService.php';
        foreach ($linkedTtnNpIds as $ttnId) {
            \Papir\Crm\TtnService::fireOrderTtnCreated($orderId, $ttnId);
        }
    }
}

echo json_encode(array('ok' => true, 'linked' => $linked));
