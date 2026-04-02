<?php
/**
 * POST /novaposhta/api/update_ttn_detail
 * Updates an editable (state_define=1, not printed) TTN.
 *
 * Fields (all optional, only provided fields are updated):
 *   ttn_id             int
 *   weight             float
 *   seats_amount       int
 *   description        string
 *   declared_value     int   (оголошена вартість)
 *   payer_type         Sender|Recipient|ThirdPerson
 *   payment_method     Cash|NonCash
 *   backward_delivery_money  float (0 = remove COD)
 *   recipient_last_name / first_name / middle_name / phone
 *   city_recipient_ref / city_recipient_desc
 *   recipient_address_ref / recipient_address_desc  (NP ref of warehouse or address)
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
if ((int)$ttn['state_define'] !== 1) {
    echo json_encode(array('ok' => false, 'error' => 'Редагування доступне лише для чернеток (статус 1)'));
    exit;
}
if (!$ttn['sender_api']) {
    echo json_encode(array('ok' => false, 'error' => 'API ключ відправника не знайдено'));
    exit;
}

$np = new \Papir\Crm\NovaPoshta($ttn['sender_api']);
$npProps = array('Ref' => $ttn['ref']);
$dbUpd   = array('id' => $ttnId);

// ── Simple scalar fields ──────────────────────────────────────────────────
if (isset($_POST['weight']) && $_POST['weight'] !== '') {
    $v = (float)$_POST['weight'];
    $npProps['Weight'] = $v;
    $dbUpd['weight']   = $v;
}
if (isset($_POST['seats_amount']) && $_POST['seats_amount'] !== '') {
    $v = max(1, (int)$_POST['seats_amount']);
    $npProps['SeatsAmount'] = $v;
    $dbUpd['seats_amount']  = $v;
}
if (isset($_POST['description']) && $_POST['description'] !== '') {
    $v = trim($_POST['description']);
    $npProps['Description'] = $v;
    $dbUpd['description']   = $v;
}
if (isset($_POST['declared_value']) && $_POST['declared_value'] !== '') {
    $v = max(1, (int)$_POST['declared_value']);
    $npProps['Cost']          = $v;
    $dbUpd['declared_value']  = $v;
}
if (isset($_POST['payer_type']) && in_array($_POST['payer_type'], array('Sender','Recipient','ThirdPerson'))) {
    $v = $_POST['payer_type'];
    $npProps['PayerType'] = $v;
    $dbUpd['payer_type']  = $v;
}
if (isset($_POST['payment_method']) && in_array($_POST['payment_method'], array('Cash','NonCash'))) {
    $v = $_POST['payment_method'];
    $npProps['PaymentMethod'] = $v;
    $dbUpd['payment_method']  = $v;
}
if (isset($_POST['backward_delivery_money'])) {
    $v = (float)$_POST['backward_delivery_money'];
    if ($v > 0) {
        $npProps['BackwardDeliveryData'] = array(array(
            'PayerType'        => 'Recipient',
            'CargoType'        => 'Money',
            'RedeliveryString' => (string)(int)round($v),
        ));
    } else {
        $npProps['BackwardDeliveryData'] = array();
        $v = 0;
    }
    $dbUpd['backward_delivery_money'] = $v;
}

// ── Recipient update ──────────────────────────────────────────────────────
$recipientChanged  = isset($_POST['recipient_last_name']) || isset($_POST['recipient_phone']);
$cityChanged       = isset($_POST['city_recipient_ref']) && $_POST['city_recipient_ref'];
$addressChanged    = isset($_POST['recipient_address_ref']) && $_POST['recipient_address_ref'];

if ($recipientChanged || $cityChanged || $addressChanged) {
    // Merge new values over existing
    $lastName   = isset($_POST['recipient_last_name'])   ? trim($_POST['recipient_last_name'])   : '';
    $firstName  = isset($_POST['recipient_first_name'])  ? trim($_POST['recipient_first_name'])  : '';
    $middleName = isset($_POST['recipient_middle_name']) ? trim($_POST['recipient_middle_name']) : '';
    $phone      = isset($_POST['recipient_phone'])       ? trim($_POST['recipient_phone'])       : $ttn['recipients_phone'];

    $cityRef    = $cityChanged   ? trim($_POST['city_recipient_ref'])  : ($ttn['city_recipient_ref'] ?: '');
    $addressRef = $addressChanged? trim($_POST['recipient_address_ref']): $ttn['recipient_address'];

    // Rebuild contact person name for DB
    $fullName = trim($lastName . ' ' . $firstName . ' ' . $middleName);

    if ($cityRef && $phone) {
        // Re-create/find NP recipient counterparty
        $recipientType = !empty($_POST['recipient_type']) ? trim($_POST['recipient_type']) : 'PrivatePerson';
        if ($recipientType === 'PrivatePerson') {
            $cpProps = array(
                'CounterpartyProperty' => 'Recipient',
                'CityRef'              => $cityRef,
                'CounterpartyType'     => 'PrivatePerson',
                'FirstName'            => $firstName,
                'MiddleName'           => $middleName,
                'LastName'             => $lastName,
                'Phone'                => \Papir\Crm\TtnService::normalizePhone($phone),
            );
        } else {
            $cpProps = array(
                'CounterpartyProperty' => 'Recipient',
                'CityRef'              => $cityRef,
                'CounterpartyType'     => 'Organization',
                'CounterpartyFullName' => $lastName,
                'Phone'                => \Papir\Crm\TtnService::normalizePhone($phone),
            );
        }
        $rCp = $np->call('Counterparty', 'save', $cpProps);
        if (!$rCp['ok']) {
            echo json_encode(array('ok' => false, 'error' => 'Помилка оновлення одержувача: ' . $rCp['error']));
            exit;
        }
        $newRecipientRef = isset($rCp['data'][0]['Ref']) ? $rCp['data'][0]['Ref'] : '';
        if ($newRecipientRef) {
            $npProps['Recipient']      = $newRecipientRef;
            $npProps['CityRecipient']  = $cityRef;
            $npProps['RecipientsPhone']= \Papir\Crm\TtnService::normalizePhone($phone);
            $dbUpd['recipient_np_ref']     = $newRecipientRef;
            $dbUpd['city_recipient_ref']   = $cityRef;
        }

        // Get contact person ref for updated recipient
        $rContact = $np->call('Counterparty', 'getCounterpartyContactPersons',
            array('Ref' => $newRecipientRef, 'Page' => 1));
        $contactRef = (!empty($rContact['data'][0]['Ref'])) ? $rContact['data'][0]['Ref'] : $newRecipientRef;
        $npProps['ContactRecipient'] = $contactRef;
    }

    if ($addressRef) {
        $npProps['RecipientAddress'] = $addressRef;
        $dbUpd['recipient_address']  = $addressRef;
    }
    if (isset($_POST['recipient_address_desc'])) {
        $dbUpd['recipient_address_desc'] = trim($_POST['recipient_address_desc']);
    }
    if (isset($_POST['city_recipient_desc'])) {
        $dbUpd['city_recipient_desc'] = trim($_POST['city_recipient_desc']);
    }
    if ($fullName) {
        $dbUpd['recipient_contact_person'] = $fullName;
    }
    if ($phone) {
        $dbUpd['recipients_phone'] = $phone;
        $npProps['RecipientsPhone'] = \Papir\Crm\TtnService::normalizePhone($phone);
    }
}

// ── OptionsSeat (per-seat dimensions, manual handling per seat) ───────────
if (isset($_POST['options_seat'])) {
    $rawSeats = trim($_POST['options_seat']);
    $decoded  = json_decode($rawSeats, true);
    if (is_array($decoded) && !empty($decoded)) {
        $seatsArr = array();
        $anyManual = false;
        foreach ($decoded as $s) {
            $w  = isset($s['weight']) ? (float)$s['weight'] : 0;
            $l  = isset($s['length']) ? (int)$s['length']   : 0;
            $wi = isset($s['width'])  ? (int)$s['width']    : 0;
            $hh = isset($s['height']) ? (int)$s['height']   : 0;
            $vol = ($l > 0 && $wi > 0 && $hh > 0) ? round($l * $wi * $hh / 4000, 2) : 0;
            $seat = array(
                'weight'           => (string)$w,
                'volumetricWidth'  => (string)$wi,
                'volumetricLength' => (string)$l,
                'volumetricHeight' => (string)$hh,
                'volumetricVolume' => (string)$vol,
            );
            $isManual = !empty($s['manual']);
            if ($isManual) {
                $seat['optionsSeat'] = 'MANUALSORT';
                $anyManual = true;
            }
            $seatsArr[] = $seat;
        }
        if ($seatsArr) {
            $npProps['OptionsSeat'] = $seatsArr;
        }
        $dbUpd['options_seat']    = $rawSeats;
        $dbUpd['manual_handling'] = $anyManual ? 1 : 0;
    } else {
        // Empty array — clear seats data
        $dbUpd['options_seat']    = null;
        $dbUpd['manual_handling'] = 0;
    }
}

// ── Call NP API update ────────────────────────────────────────────────────
if (count($npProps) > 1) { // more than just Ref
    $r = $np->call('InternetDocument', 'update', $npProps);
    if (!$r['ok']) {
        echo json_encode(array('ok' => false, 'error' => 'НП API: ' . $r['error']));
        exit;
    }
}

// ── Update DB ─────────────────────────────────────────────────────────────
if (count($dbUpd) > 1) { // more than just id
    \Papir\Crm\TtnRepository::save($dbUpd);
}

// Return updated TTN
$updated = \Papir\Crm\TtnRepository::getById($ttnId);
echo json_encode(array('ok' => true, 'ttn' => $updated));