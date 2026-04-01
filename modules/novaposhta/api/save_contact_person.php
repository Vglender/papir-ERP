<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$senderRef  = isset($_POST['sender_ref'])   ? trim($_POST['sender_ref'])   : '';
$lastName   = isset($_POST['last_name'])    ? trim($_POST['last_name'])    : '';
$firstName  = isset($_POST['first_name'])   ? trim($_POST['first_name'])   : '';
$middleName = isset($_POST['middle_name'])  ? trim($_POST['middle_name'])  : '';
$phone      = isset($_POST['phone'])        ? trim($_POST['phone'])        : '';

if (!$senderRef || !$lastName || !$firstName || !$phone) {
    echo json_encode(array('ok' => false, 'error' => 'sender_ref, last_name, first_name, phone required'));
    exit;
}

$sender = \Papir\Crm\SenderRepository::getByRef($senderRef);
if (!$sender || !$sender['api']) {
    echo json_encode(array('ok' => false, 'error' => 'Sender or API key not found'));
    exit;
}

// Normalize phone to 0XXXXXXXXX
$digits = preg_replace('/\D/', '', $phone);
if (strlen($digits) === 12 && substr($digits, 0, 2) === '38') {
    $digits = substr($digits, 2);
}
if (strlen($digits) === 11 && $digits[0] === '8') {
    $digits = substr($digits, 1);
}

$np = new \Papir\Crm\NovaPoshta($sender['api']);
$r  = $np->call('ContactPerson', 'save', array(
    'CounterpartyRef' => $senderRef,
    'FirstName'       => $firstName,
    'LastName'        => $lastName,
    'MiddleName'      => $middleName,
    'Phone'           => $digits,
));

if (!$r['ok'] || empty($r['data'][0]['Ref'])) {
    echo json_encode(array('ok' => false, 'error' => $r['error'] ?: 'NP API error'));
    exit;
}

$npRef    = $r['data'][0]['Ref'];
$fullName = trim($lastName . ' ' . $firstName . ' ' . $middleName);

\Database::upsertOne('Papir', 'np_sender_contact_persons', array(
    'Ref'        => $npRef,
    'sender_ref' => $senderRef,
    'full_name'  => $fullName,
    'phone'      => $digits,
    'updated_at' => date('Y-m-d H:i:s'),
), array('Ref'));

echo json_encode(array(
    'ok'      => true,
    'contact' => array('Ref' => $npRef, 'full_name' => $fullName, 'phone' => $digits),
));