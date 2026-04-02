<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$id) {
    echo json_encode(array('ok' => false, 'error' => 'id required'));
    exit;
}

$call = \Papir\Crm\CourierCallRepository::getById($id);
if (!$call) {
    echo json_encode(array('ok' => false, 'error' => 'Not found'));
    exit;
}

// Try to cancel in NP API if barcode exists
$npError = null;
if ($call['Barcode']) {
    $sender = \Papir\Crm\SenderRepository::getByRef($call['sender_ref']);
    if ($sender && $sender['api']) {
        $np = new \Papir\Crm\NovaPoshta($sender['api']);
        $r  = $np->call('CarCallGeneral', 'deleteCarCall', array(
            'Number' => $call['Barcode'],
        ));
        if (!$r['ok']) {
            $npError = $r['error'];
        }
    }
}

\Papir\Crm\CourierCallRepository::delete($id);

echo json_encode(array(
    'ok'       => true,
    'np_error' => $npError, // null = cancelled in NP too; string = local-only delete
));