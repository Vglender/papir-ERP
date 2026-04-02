<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$senderRef = isset($_POST['sender_ref']) ? trim($_POST['sender_ref']) : '';
if (!$senderRef) {
    echo json_encode(array('ok' => false, 'error' => 'sender_ref required'));
    exit;
}

$es      = \Database::escape('Papir', $senderRef);
$linked  = 0;
$created = 0;

// Find all TTNs that have car_call set (from NP sync) for this sender
$rTtns = \Database::fetchAll('Papir',
    "SELECT id, int_doc_number, car_call, weight
     FROM ttn_novaposhta
     WHERE sender_ref = '{$es}'
       AND car_call IS NOT NULL AND car_call != ''
       AND deletion_mark = 0");

if (!$rTtns['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'DB error'));
    exit;
}

foreach ($rTtns['rows'] as $ttn) {
    $barcode = \Database::escape('Papir', $ttn['car_call']);

    // Find or create the courier call record by barcode
    $rCall = \Database::fetchRow('Papir',
        "SELECT id FROM np_courier_calls WHERE Barcode = '{$barcode}' LIMIT 1");

    if ($rCall['ok'] && $rCall['row']) {
        $callId = (int)$rCall['row']['id'];
    } else {
        // Auto-create courier call record from barcode (minimal data)
        $ins = \Database::insert('Papir', 'np_courier_calls', array(
            'Barcode'    => $ttn['car_call'],
            'sender_ref' => $senderRef,
            'status'     => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ));
        if (!$ins['ok']) continue;
        $callId = (int)$ins['id'];
        $created++;
    }

    // Link TTN to call
    \Papir\Crm\CourierCallRepository::upsertTtn(
        $callId,
        $ttn['int_doc_number'],
        (int)$ttn['id'],
        $ttn['weight']
    );
    $linked++;
}

echo json_encode(array('ok' => true, 'linked' => $linked, 'created' => $created));
