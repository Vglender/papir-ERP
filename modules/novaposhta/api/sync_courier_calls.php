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

// Date range: last 30 days by default, or custom
$dateFrom = isset($_POST['date_from']) ? trim($_POST['date_from']) : date('d.m.Y', strtotime('-30 days'));
$dateTo   = isset($_POST['date_to'])   ? trim($_POST['date_to'])   : date('d.m.Y');

$es = \Database::escape('Papir', $senderRef);

$sender = \Papir\Crm\SenderRepository::getByRef($senderRef);
if (!$sender || !$sender['api']) {
    echo json_encode(array('ok' => false, 'error' => 'Sender or API key not found'));
    exit;
}

$np      = new \Papir\Crm\NovaPoshta($sender['api']);
$linked  = 0;
$created = 0;
$npError = '';

// ── Phase 1: Import courier calls and TTN links from NP API ──────────────────
// InternetDocument.getDocumentList returns CarCall barcode for each TTN

$page     = 1;
$pageSize = 100;

do {
    $r = $np->call('InternetDocument', 'getDocumentList', array(
        'DateTimeFrom' => $dateFrom,
        'DateTimeTo'   => $dateTo,
        'GetFullList'  => '1',
        'Page'         => (string)$page,
    ));

    if (!$r['ok']) {
        $npError = $r['error'];
        break;
    }

    $batch = $r['data'];

    foreach ($batch as $doc) {
        $carCallBarcode = isset($doc['CarCall']) ? trim($doc['CarCall']) : '';
        if (!$carCallBarcode) continue;

        $intDocNumber = isset($doc['IntDocNumber']) ? trim($doc['IntDocNumber']) : '';
        if (!$intDocNumber) continue;

        $eb = \Database::escape('Papir', $carCallBarcode);

        // Find or create courier call record
        $rCall = \Database::fetchRow('Papir',
            "SELECT id FROM np_courier_calls WHERE Barcode = '{$eb}' LIMIT 1");

        if ($rCall['ok'] && $rCall['row']) {
            $callId = (int)$rCall['row']['id'];
        } else {
            $ins = \Database::insert('Papir', 'np_courier_calls', array(
                'Barcode'    => $carCallBarcode,
                'sender_ref' => $senderRef,
                'status'     => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
            ));
            if (!$ins['ok']) continue;
            $callId = (int)$ins['insert_id'];
            $created++;
        }

        // Find matching local TTN
        $en  = \Database::escape('Papir', $intDocNumber);
        $rTtn = \Database::fetchRow('Papir',
            "SELECT id, weight FROM ttn_novaposhta WHERE int_doc_number = '{$en}' LIMIT 1");
        $ttnId  = ($rTtn['ok'] && $rTtn['row']) ? (int)$rTtn['row']['id']   : null;
        $weight = ($rTtn['ok'] && $rTtn['row']) ? $rTtn['row']['weight']    : null;

        // Persist car_call on TTN for future reference
        if ($ttnId) {
            \Database::query('Papir',
                "UPDATE ttn_novaposhta SET car_call = '{$eb}'
                 WHERE id = {$ttnId} AND (car_call IS NULL OR car_call = '')");
        }

        \Papir\Crm\CourierCallRepository::upsertTtn($callId, $intDocNumber, $ttnId, $weight);
        $linked++;
    }

    $page++;
} while (count($batch) >= $pageSize);

// ── Phase 2: Date-based fallback for locally-created calls ───────────────────
// For courier calls in our DB that have a preferred_delivery_date,
// link TTNs created on the same date (covers calls created in our CRM).

$rCalls = \Database::fetchAll('Papir',
    "SELECT id, Barcode, preferred_delivery_date
     FROM np_courier_calls
     WHERE sender_ref = '{$es}' AND preferred_delivery_date != ''
     ORDER BY id DESC");

if ($rCalls['ok']) {
    foreach ($rCalls['rows'] as $call) {
        $callId   = (int)$call['id'];
        $callDate = \Database::escape('Papir', $call['preferred_delivery_date']);

        $rTtns = \Database::fetchAll('Papir',
            "SELECT id, int_doc_number, weight
             FROM ttn_novaposhta
             WHERE sender_ref = '{$es}'
               AND deletion_mark = 0
               AND DATE(moment) = STR_TO_DATE('{$callDate}', '%d.%m.%Y')");

        if (!$rTtns['ok']) continue;

        foreach ($rTtns['rows'] as $ttn) {
            \Papir\Crm\CourierCallRepository::upsertTtn(
                $callId,
                $ttn['int_doc_number'],
                (int)$ttn['id'],
                $ttn['weight']
            );
            $linked++;
        }
    }
}

$result = array('ok' => true, 'linked' => $linked, 'created' => $created);
if ($npError) {
    $result['np_error'] = $npError;
}
echo json_encode($result);