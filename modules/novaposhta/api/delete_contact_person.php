<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$senderRef  = isset($_POST['sender_ref'])  ? trim($_POST['sender_ref'])  : '';
$contactRef = isset($_POST['contact_ref']) ? trim($_POST['contact_ref']) : '';

if (!$senderRef || !$contactRef) {
    echo json_encode(array('ok' => false, 'error' => 'sender_ref and contact_ref required'));
    exit;
}

$sender = \Papir\Crm\SenderRepository::getByRef($senderRef);
if (!$sender || !$sender['api']) {
    echo json_encode(array('ok' => false, 'error' => 'Sender or API key not found'));
    exit;
}

// Try NP API — ignore "Loyalty user can not delete" restriction
$np = new \Papir\Crm\NovaPoshta($sender['api']);
$r  = $np->call('ContactPerson', 'delete', array('Ref' => $contactRef));

$npWarning = null;
if (!$r['ok']) {
    $err = $r['error'] ?: '';
    // NP does not allow deleting contact persons for Loyalty counterparties — delete locally only
    if (stripos($err, 'Loyalty') !== false || stripos($err, 'can not delete') !== false) {
        $npWarning = 'Видалено лише локально (НП API не дозволяє видаляти контактних осіб для цього контрагента)';
    } else {
        echo json_encode(array('ok' => false, 'error' => $err));
        exit;
    }
}

$ec = \Database::escape('Papir', $contactRef);
\Database::query('Papir', "DELETE FROM np_sender_contact_persons WHERE Ref = '{$ec}'");

$result = array('ok' => true);
if ($npWarning) {
    $result['warning'] = $npWarning;
}
echo json_encode($result);
