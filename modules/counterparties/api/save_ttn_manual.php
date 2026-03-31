<?php
/**
 * POST /counterparties/api/save_ttn_manual
 * Manually register a TTN number for an order (NP or UP).
 * Creates a ttn_novaposhta/ttn_ukrposhta record and links it to the order via document_link.
 * The sync cron will later update tracking status automatically.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}
if (!\Papir\Crm\AuthService::isLoggedIn()) {
    echo json_encode(array('ok' => false, 'error' => 'Unauthorized'));
    exit;
}

$orderId   = isset($_POST['customerorder_id']) ? (int)$_POST['customerorder_id'] : 0;
$carrier   = isset($_POST['carrier'])          ? trim($_POST['carrier'])          : '';   // 'np' or 'up'
$ttnNumber = isset($_POST['ttn_number'])       ? trim($_POST['ttn_number'])       : '';

if ($orderId <= 0 || !in_array($carrier, array('np', 'up')) || $ttnNumber === '') {
    echo json_encode(array('ok' => false, 'error' => 'customerorder_id, carrier (np|up), ttn_number required'));
    exit;
}

// Sanitize TTN number (digits and dashes only)
$ttnNumber = preg_replace('/[^0-9\-]/', '', $ttnNumber);
if (strlen($ttnNumber) < 6) {
    echo json_encode(array('ok' => false, 'error' => 'Невалідний номер ТТН'));
    exit;
}

if ($carrier === 'np') {
    // Check for duplicates
    $rDup = \Database::fetchRow('Papir',
        "SELECT id FROM ttn_novaposhta WHERE int_doc_number = '" . \Database::escape('Papir', $ttnNumber) . "'");
    if ($rDup['ok'] && !empty($rDup['row'])) {
        $ttnId = (int)$rDup['row']['id'];
    } else {
        // Insert minimal NP TTN record; sync cron will populate status later
        $r = \Database::insert('Papir', 'ttn_novaposhta', array(
            'ref'               => 'manual_' . $ttnNumber,
            'int_doc_number'    => $ttnNumber,
            'customerorder_id'  => $orderId,
            'state_name'        => 'Введено вручну — очікує синхронізації',
            'moment'            => date('Y-m-d H:i:s'),
        ));
        if (!$r['ok']) {
            echo json_encode(array('ok' => false, 'error' => 'Не вдалося зберегти ТТН НП'));
            exit;
        }
        $ttnId = $r['insert_id'];
    }

    // Link to order via document_link
    $rLink = \Database::upsertOne('Papir', 'document_link',
        array(
            'from_type' => 'ttn_np',
            'from_id'   => $ttnId,
            'to_type'   => 'customerorder',
            'to_id'     => $orderId,
            'link_type' => 'shipment',
        ),
        array('from_type', 'from_id', 'to_type', 'to_id')
    );

    echo json_encode(array('ok' => true, 'ttn_id' => $ttnId, 'carrier' => 'np', 'ttn_number' => $ttnNumber));

} else {
    // carrier === 'up'
    $rDup = \Database::fetchRow('Papir',
        "SELECT id FROM ttn_ukrposhta WHERE barcode = '" . \Database::escape('Papir', $ttnNumber) . "'");
    if ($rDup['ok'] && !empty($rDup['row'])) {
        $ttnId = (int)$rDup['row']['id'];
    } else {
        $r = \Database::insert('Papir', 'ttn_ukrposhta', array(
            'barcode'            => $ttnNumber,
            'customerorder_id'   => $orderId,
            'lifecycle_status'   => 'CREATED',
            'created_date'       => date('Y-m-d H:i:s'),
        ));
        if (!$r['ok']) {
            echo json_encode(array('ok' => false, 'error' => 'Не вдалося зберегти ТТН УП'));
            exit;
        }
        $ttnId = $r['insert_id'];
    }

    // Link to order via document_link (UP uses from_id as well)
    \Database::upsertOne('Papir', 'document_link',
        array(
            'from_type' => 'ttn_up',
            'from_id'   => $ttnId,
            'to_type'   => 'customerorder',
            'to_id'     => $orderId,
            'link_type' => 'shipment',
        ),
        array('from_type', 'from_id', 'to_type', 'to_id')
    );

    echo json_encode(array('ok' => true, 'ttn_id' => $ttnId, 'carrier' => 'up', 'ttn_number' => $ttnNumber));
}