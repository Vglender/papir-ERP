<?php
/**
 * POST /novaposhta/api/duplicate_ttn
 * Creates a new TTN as a copy of an existing one.
 *
 * POST: ttn_id
 *
 * If city_recipient_ref is not stored locally — tries to fetch it from NP API.
 * Returns { ok, ttn_id, int_doc_number }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$ttnId = isset($_POST['ttn_id']) ? (int)$_POST['ttn_id'] : 0;
if (!$ttnId) {
    echo json_encode(array('ok' => false, 'error' => 'ttn_id required'));
    exit;
}

$ttn = \Papir\Crm\TtnRepository::getById($ttnId);
if (!$ttn) {
    echo json_encode(array('ok' => false, 'error' => 'TTN not found'));
    exit;
}
if (!$ttn['sender_api']) {
    echo json_encode(array('ok' => false, 'error' => 'API ключ відправника не знайдено'));
    exit;
}

$np = new \Papir\Crm\NovaPoshta($ttn['sender_api']);

// ── Resolve city_recipient_ref ────────────────────────────────────────────
$cityRecipientRef = $ttn['city_recipient_ref'];
$citySenderRef    = $ttn['city_sender_ref'];
$recipientAddress = $ttn['recipient_address'];

if (!$cityRecipientRef && $ttn['int_doc_number']) {
    // Try to get from NP API document list
    $rList = $np->call('InternetDocument', 'getDocumentList', array(
        'IntDocNumber' => $ttn['int_doc_number'],
        'Page'         => 1,
    ));
    if ($rList['ok'] && !empty($rList['data'][0])) {
        $doc = $rList['data'][0];
        $cityRecipientRef = isset($doc['CityRecipient']) ? $doc['CityRecipient'] : '';
        $citySenderRef    = isset($doc['CitySender'])    ? $doc['CitySender']    : $citySenderRef;
        if (empty($recipientAddress) && isset($doc['RecipientAddress'])) {
            $recipientAddress = $doc['RecipientAddress'];
        }
        // Cache to DB for future duplicate
        $cacheUpd = array('id' => $ttnId);
        if ($cityRecipientRef) $cacheUpd['city_recipient_ref'] = $cityRecipientRef;
        if ($citySenderRef)    $cacheUpd['city_sender_ref']    = $citySenderRef;
        if (count($cacheUpd) > 1) \Papir\Crm\TtnRepository::save($cacheUpd);
    }
}

if (!$cityRecipientRef) {
    echo json_encode(array('ok' => false, 'error' => 'Не вдалося визначити місто одержувача. Дублювання неможливе.'));
    exit;
}

// ── Resolve sender address ────────────────────────────────────────────────
$senderAddressRef = $ttn['sender_address_ref'];
if (!$senderAddressRef) {
    $defAddr = \Papir\Crm\SenderRepository::getDefaultAddress($ttn['sender_ref']);
    if ($defAddr) $senderAddressRef = $defAddr['Ref'];
}
if (!$senderAddressRef) {
    echo json_encode(array('ok' => false, 'error' => 'Адреса відправника не знайдена'));
    exit;
}

// ── Parse recipient name ──────────────────────────────────────────────────
$nameParts  = preg_split('/\s+/', trim($ttn['recipient_contact_person']), 3);
$lastName   = isset($nameParts[0]) ? $nameParts[0] : '';
$firstName  = isset($nameParts[1]) ? $nameParts[1] : $lastName;
$middleName = isset($nameParts[2]) ? $nameParts[2] : '';
if (!$firstName) $firstName = $lastName;

// ── Determine service type ────────────────────────────────────────────────
$serviceType = $ttn['service_type'] ?: 'WarehouseWarehouse';
$isWarehouse = in_array($serviceType, array('WarehouseWarehouse', 'DoorsWarehouse'));

// ── Build create params ───────────────────────────────────────────────────
$params = array(
    'sender_ref'              => $ttn['sender_ref'],
    'sender_address_ref'      => $senderAddressRef,
    'city_sender_ref'         => $citySenderRef ?: '',
    'city_sender_desc'        => $ttn['city_sender_desc'],
    'city_recipient_ref'      => $cityRecipientRef,
    'city_recipient_desc'     => $ttn['city_recipient_desc'],
    'recipient_address_desc'  => $ttn['recipient_address_desc'],
    'recipient_last_name'     => $lastName,
    'recipient_first_name'    => $firstName,
    'recipient_middle_name'   => $middleName,
    'recipient_phone'         => $ttn['recipients_phone'],
    'service_type'            => $serviceType,
    'payment_method'          => $ttn['payment_method'] ?: 'Cash',
    'payer_type'              => $ttn['payer_type']     ?: 'Recipient',
    'cargo_type'              => 'Cargo',
    'weight'                  => $ttn['weight']         ?: 0.5,
    'seats_amount'            => $ttn['seats_amount']   ?: 1,
    'description'             => $ttn['description']    ?: 'Товар',
    'cost'                    => $ttn['declared_value'] ?: 1,
    'backward_delivery_money' => $ttn['backward_delivery_money'] ?: 0,
    'customerorder_id'        => $ttn['customerorder_id'],
    'date'                    => date('d.m.Y'),
);

if ($isWarehouse) {
    $params['recipient_warehouse_ref'] = $recipientAddress;
} else {
    // For door delivery, recipient_address is a NP address ref
    $params['recipient_warehouse_ref'] = $recipientAddress; // TtnService handles both via ensureRecipientAddress
}

$result = \Papir\Crm\TtnService::create($params);

if (!$result['ok']) {
    echo json_encode(array('ok' => false, 'error' => $result['error']));
    exit;
}

echo json_encode(array(
    'ok'             => true,
    'ttn_id'         => $result['ttn_id'],
    'int_doc_number' => $result['int_doc_number'],
));