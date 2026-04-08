<?php
/**
 * POST /novaposhta/api/update_ttn_detail
 * Updates an editable (state_define=1, not printed) TTN via NP InternetDocument.update.
 *
 * Builds a FULL payload (required by NP API) from:
 *   - np_sender + np_sender_address  → sender fields
 *   - ttn_novaposhta + POST overrides → recipient/cargo fields
 *   - NP API Counterparty.save       → recipient Ref (create/re-create as needed)
 *
 * Fields (all optional overrides, only provided fields update the state):
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
 *   recipient_address_ref / recipient_address_desc
 *   recipient_type     PrivatePerson|Organization
 *   options_seat       JSON array
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

$np    = new \Papir\Crm\NovaPoshta($ttn['sender_api']);
$dbUpd = array('id' => $ttnId);

// ── Apply POST overrides to $ttn (scalar cargo fields) ────────────────────
if (isset($_POST['weight']) && $_POST['weight'] !== '') {
    $ttn['weight']   = (float)$_POST['weight'];
    $dbUpd['weight'] = $ttn['weight'];
}
if (isset($_POST['seats_amount']) && $_POST['seats_amount'] !== '') {
    $ttn['seats_amount']   = max(1, (int)$_POST['seats_amount']);
    $dbUpd['seats_amount'] = $ttn['seats_amount'];
}
if (isset($_POST['description'])) {
    $ttn['description']   = trim($_POST['description']);
    $dbUpd['description'] = $ttn['description'];
}
if (isset($_POST['additional_information'])) {
    $ttn['additional_information']   = trim($_POST['additional_information']);
    $dbUpd['additional_information'] = $ttn['additional_information'];
}
if (isset($_POST['declared_value']) && $_POST['declared_value'] !== '') {
    $ttn['declared_value']   = max(1, (int)$_POST['declared_value']);
    $dbUpd['declared_value'] = $ttn['declared_value'];
}
if (isset($_POST['payer_type']) && in_array($_POST['payer_type'], array('Sender','Recipient','ThirdPerson'))) {
    $ttn['payer_type']   = $_POST['payer_type'];
    $dbUpd['payer_type'] = $ttn['payer_type'];
}
if (isset($_POST['payment_method']) && in_array($_POST['payment_method'], array('Cash','NonCash'))) {
    $ttn['payment_method']   = $_POST['payment_method'];
    $dbUpd['payment_method'] = $ttn['payment_method'];
}
if (isset($_POST['backward_delivery_money'])) {
    $ttn['backward_delivery_money']   = (float)$_POST['backward_delivery_money'];
    $dbUpd['backward_delivery_money'] = $ttn['backward_delivery_money'];
}

// #1: Оголошена вартість не може бути менша за накладений платіж
$cod = (float)($ttn['backward_delivery_money'] ?: 0);
if ($cod > 0 && (int)($ttn['declared_value'] ?: 0) < (int)ceil($cod)) {
    $ttn['declared_value']   = (int)ceil($cod);
    $dbUpd['declared_value'] = $ttn['declared_value'];
}

// ── Apply POST overrides to $ttn (recipient fields) ───────────────────────
$recipientChanged = isset($_POST['recipient_last_name']) || isset($_POST['recipient_phone']);
$cityChanged      = isset($_POST['city_recipient_ref']) && $_POST['city_recipient_ref'] !== '';
$addressChanged   = isset($_POST['recipient_address_ref']) && $_POST['recipient_address_ref'] !== '';

// New name parts (from POST or split from stored contact person)
$storedParts = explode(' ', trim((string)$ttn['recipient_contact_person']));
$newLastName   = $recipientChanged && isset($_POST['recipient_last_name'])
    ? trim($_POST['recipient_last_name'])
    : (isset($storedParts[0]) ? $storedParts[0] : '');
$newFirstName  = $recipientChanged && isset($_POST['recipient_first_name'])
    ? trim($_POST['recipient_first_name'])
    : (isset($storedParts[1]) ? $storedParts[1] : '');
$newMiddleName = $recipientChanged && isset($_POST['recipient_middle_name'])
    ? trim($_POST['recipient_middle_name'])
    : (isset($storedParts[2]) ? $storedParts[2] : '');

$originalRecipientPhone = (string)$ttn['recipients_phone']; // keep before any override

if (isset($_POST['recipient_phone'])) {
    // Normalize to 380XXXXXXXXX for DB storage
    $ph = preg_replace('/\D/', '', trim($_POST['recipient_phone']));
    if (strlen($ph) === 10 && $ph[0] === '0') $ph = '38' . $ph;
    elseif (strlen($ph) === 11 && $ph[0] === '8') $ph = '3' . $ph;
    $ttn['recipients_phone'] = $ph;
    $dbUpd['recipients_phone'] = $ph;
}
if ($cityChanged) {
    $ttn['city_recipient_ref']  = trim($_POST['city_recipient_ref']);
    $dbUpd['city_recipient_ref'] = $ttn['city_recipient_ref'];
}
if (isset($_POST['city_recipient_desc'])) {
    $ttn['city_recipient_desc']  = trim($_POST['city_recipient_desc']);
    $dbUpd['city_recipient_desc'] = $ttn['city_recipient_desc'];
}
if ($addressChanged) {
    $ttn['recipient_address']  = trim($_POST['recipient_address_ref']);
    $dbUpd['recipient_address'] = $ttn['recipient_address'];
}
if (isset($_POST['recipient_address_desc'])) {
    $dbUpd['recipient_address_desc'] = trim($_POST['recipient_address_desc']);
}

$recipientType = !empty($_POST['recipient_type']) ? trim($_POST['recipient_type']) : 'PrivatePerson';

// ── Resolve sender fields ─────────────────────────────────────────────────
// np_sender has: Counterparty (org ref), Ref (contact person ref), City (city ref)
$sender = \Papir\Crm\SenderRepository::getByRef($ttn['sender_ref']);
if (!$sender) {
    echo json_encode(array('ok' => false, 'error' => 'Відправника не знайдено'));
    exit;
}

// SenderAddress + CitySender resolution.
// np_sender.City is always zero UUID — never use it.
// CitySender must come from np_sender_address.CityRef.
$senderAddrRef = $ttn['sender_address_ref'];
$citySenderRef = $ttn['city_sender_ref'];

// If we have a stored address ref but no city ref — look it up
if ($senderAddrRef && !$citySenderRef) {
    $eAddr = \Database::escape('Papir', $senderAddrRef);
    $rAddr = \Database::fetchRow('Papir',
        "SELECT CityRef FROM np_sender_address WHERE Ref = '{$eAddr}' LIMIT 1");
    if ($rAddr['ok'] && !empty($rAddr['row']['CityRef'])) {
        $citySenderRef = $rAddr['row']['CityRef'];
        $dbUpd['city_sender_ref'] = $citySenderRef;
    }
}

// If still missing address or city — use sender's default address
if (!$senderAddrRef || !$citySenderRef) {
    $defAddr = \Papir\Crm\SenderRepository::getDefaultAddress($ttn['sender_ref']);
    if ($defAddr) {
        if (!$senderAddrRef) {
            $senderAddrRef = $defAddr['Ref'];
            $dbUpd['sender_address_ref'] = $senderAddrRef;
        }
        if (!$citySenderRef && !empty($defAddr['CityRef'])) {
            $citySenderRef = $defAddr['CityRef'];
            $dbUpd['city_sender_ref'] = $citySenderRef;
        }
    }
}

if (!$senderAddrRef) {
    echo json_encode(array('ok' => false, 'error' => 'Адресу відправника не знайдено. Налаштуйте адресу за замовчуванням у відправнику.'));
    exit;
}
if (!$citySenderRef) {
    echo json_encode(array('ok' => false, 'error' => 'Місто відправника не знайдено в адресі відправника.'));
    exit;
}

// ── Resolve CityRecipient Ref ─────────────────────────────────────────────
$cityRecipientRef = $ttn['city_recipient_ref'];

if (!$cityRecipientRef && !empty($ttn['city_recipient_desc'])) {
    $cityMatches = \Papir\Crm\NpReferenceRepository::searchCities($ttn['city_recipient_desc'], 1);
    if (!empty($cityMatches)) {
        $cityRecipientRef = $cityMatches[0]['Ref'];
    } else {
        $rCity = $np->call('Address', 'getCities',
            array('FindByString' => $ttn['city_recipient_desc'], 'Limit' => 1));
        if ($rCity['ok'] && !empty($rCity['data'][0]['Ref'])) {
            $cityRecipientRef = $rCity['data'][0]['Ref'];
            \Papir\Crm\NpReferenceRepository::upsertCity($rCity['data'][0]);
        }
    }
}

if (!$cityRecipientRef) {
    echo json_encode(array('ok' => false, 'error' => 'Не вдалось визначити місто отримувача. Відредагуйте та оберіть місто вручну.'));
    exit;
}

// ── Resolve RecipientAddress ──────────────────────────────────────────────
// Do this BEFORE Counterparty.save so we can save everything to DB in one shot
$recipientAddressRef = $ttn['recipient_address'];

// DEBUG
error_log('[NP_DBG] recipientAddressRef(init)=' . var_export($recipientAddressRef, true)
    . ' cityRecipientRef=' . var_export($cityRecipientRef, true)
    . ' addrDesc=' . var_export($ttn['recipient_address_desc'], true)
    . ' serviceType=' . var_export($ttn['service_type'], true)
    . PHP_EOL, 3, '/var/log/papir/np_api.log');

if (!$recipientAddressRef) {
    $serviceType = $ttn['service_type'] ?: 'WarehouseWarehouse';
    if (strpos($serviceType, 'Warehouse') !== false && !empty($ttn['recipient_address_desc'])) {
        // Strategy 1: full description search in local DB
        $whs = \Papir\Crm\NpReferenceRepository::searchWarehouses(
            $cityRecipientRef, $ttn['recipient_address_desc'], 1);
        error_log('[NP_DBG] Strategy1 result=' . json_encode($whs) . PHP_EOL, 3, '/var/log/papir/np_api.log');
        if (!empty($whs)) {
            $recipientAddressRef = $whs[0]['Ref'];
        }

        // Strategy 2: search by warehouse number extracted from description
        if (!$recipientAddressRef && preg_match('/№\s*(\d+)/u', $ttn['recipient_address_desc'], $m)) {
            $eNum = \Database::escape('Papir', $m[1]);
            $eCityRef = \Database::escape('Papir', $cityRecipientRef);
            $rNum = \Database::fetchRow('Papir',
                "SELECT Ref FROM np_warehouses
                 WHERE CityRef = '{$eCityRef}'
                   AND Number = '{$eNum}'
                   AND (TypeOfWarehouse IS NULL OR TypeOfWarehouse NOT LIKE '%Postamat%')
                 LIMIT 1");
            error_log('[NP_DBG] Strategy2 num=' . $m[1] . ' cityRef=' . $cityRecipientRef . ' result=' . json_encode($rNum) . PHP_EOL, 3, '/var/log/papir/np_api.log');
            if ($rNum['ok'] && !empty($rNum['row']['Ref'])) {
                $recipientAddressRef = $rNum['row']['Ref'];
            }
        }

        // Strategy 3: НП API getWarehouses (FindByString)
        if (!$recipientAddressRef) {
            $rWh = $np->call('Address', 'getWarehouses', array(
                'CityRef'      => $cityRecipientRef,
                'FindByString' => $ttn['recipient_address_desc'],
                'Limit'        => 1,
            ));
            if ($rWh['ok'] && !empty($rWh['data'][0]['Ref'])) {
                $recipientAddressRef = $rWh['data'][0]['Ref'];
                \Papir\Crm\NpReferenceRepository::upsertWarehouse($rWh['data'][0]);
            }
        }

        // Strategy 4: НП API by warehouse number only
        if (!$recipientAddressRef && preg_match('/№\s*(\d+)/u', $ttn['recipient_address_desc'], $m)) {
            $rWh2 = $np->call('Address', 'getWarehouses', array(
                'CityRef'         => $cityRecipientRef,
                'WarehouseNumber' => $m[1],
                'Limit'           => 1,
            ));
            if ($rWh2['ok'] && !empty($rWh2['data'][0]['Ref'])) {
                $recipientAddressRef = $rWh2['data'][0]['Ref'];
                \Papir\Crm\NpReferenceRepository::upsertWarehouse($rWh2['data'][0]);
            }
        }
    }
}

if (!$recipientAddressRef) {
    echo json_encode(array('ok' => false, 'error' => 'Не вдалось визначити відділення отримувача. Відредагуйте та оберіть відділення вручну.'));
    exit;
}

// ── Resolve Recipient counterparty ────────────────────────────────────────
$recipientNpRef = $ttn['recipient_np_ref'];

// Always call Counterparty.save — NP deduplicates by phone and returns the
// canonical Ref for the current phone+city. This self-heals stale refs in DB.
//
// Визначаємо чи змінилось тільки ФІО (без телефону та міста) → передаємо Ref
// Compare normalized phones against the ORIGINAL DB value (before override)
$nameOnlyChange = $recipientNpRef && $recipientChanged && !$cityChanged
    && (!isset($_POST['recipient_phone'])
        || \Papir\Crm\TtnService::normalizePhone(trim($_POST['recipient_phone']))
           === \Papir\Crm\TtnService::normalizePhone($originalRecipientPhone));

if (true) {
    if ($recipientType === 'PrivatePerson') {
        $cpProps = array(
            'CounterpartyProperty' => 'Recipient',
            'CounterpartyType'     => 'PrivatePerson',
            'FirstName'            => $newFirstName,
            'MiddleName'           => $newMiddleName,
            'LastName'             => $newLastName,
            'Phone'                => \Papir\Crm\TtnService::normalizePhone($ttn['recipients_phone']),
        );
        // Якщо тільки ФІО змінилось — передаємо Ref щоб оновити в НП, а не створити нового
        if ($nameOnlyChange && $recipientNpRef) {
            $cpProps['Ref'] = $recipientNpRef;
        }
    } else {
        $cpProps = array(
            'CounterpartyProperty' => 'Recipient',
            'CounterpartyType'     => 'Organization',
            'CounterpartyFullName' => $newLastName,
            'Phone'                => \Papir\Crm\TtnService::normalizePhone($ttn['recipients_phone']),
        );
        if ($nameOnlyChange && $recipientNpRef) {
            $cpProps['Ref'] = $recipientNpRef;
        }
    }
    $rCp = $np->call('CounterpartyGeneral', 'save', $cpProps);
    if (!$rCp['ok']) {
        echo json_encode(array('ok' => false, 'error' => 'Помилка збереження одержувача в НП: ' . $rCp['error']));
        exit;
    }
    $cpRespData = isset($rCp['data'][0]) ? $rCp['data'][0] : array();
    $newRef = isset($cpRespData['Ref']) ? $cpRespData['Ref'] : '';
    if (!$newRef) {
        echo json_encode(array('ok' => false, 'error' => 'НП не повернула Ref одержувача'));
        exit;
    }
    // data[0].Ref = generic PrivatePerson ref (Recipient)
    // data[0].ContactPerson.data[0].Ref = unique contact person ref (ContactRecipient)
    $recipientNpRef = $newRef;
    $contactRef = isset($cpRespData['ContactPerson']['data'][0]['Ref'])
        ? $cpRespData['ContactPerson']['data'][0]['Ref']
        : $newRef;
}

// ── Save resolved refs to DB immediately (before NP update call) ──────────
// This ensures refs survive failed NP attempts and are reused on next try.
$resolvedUpd = array('id' => $ttnId);
if ($cityRecipientRef && $cityRecipientRef !== $ttn['city_recipient_ref']) {
    $resolvedUpd['city_recipient_ref'] = $cityRecipientRef;
}
// Store the unique contact person ref (not the generic PrivatePerson ref)
if (!empty($contactRef) && $contactRef !== $ttn['recipient_np_ref']) {
    $resolvedUpd['recipient_np_ref'] = $contactRef;
}
if ($recipientAddressRef && $recipientAddressRef !== $ttn['recipient_address']) {
    $resolvedUpd['recipient_address'] = $recipientAddressRef;
}
if ($senderAddrRef && $senderAddrRef !== $ttn['sender_address_ref']) {
    $resolvedUpd['sender_address_ref'] = $senderAddrRef;
}
if ($citySenderRef && $citySenderRef !== $ttn['city_sender_ref']) {
    $resolvedUpd['city_sender_ref'] = $citySenderRef;
}
if (count($resolvedUpd) > 1) {
    \Papir\Crm\TtnRepository::save($resolvedUpd);
}

// ── Resolve ContactSender (contact person ref, ≠ org counterparty ref) ───
// np_sender.Ref may equal Counterparty (org ref) — invalid as ContactSender.
// Look up contact person from np_sender_contact_persons matching sender phone.
$contactSenderRef = null;
$senderPhone = \Papir\Crm\TtnService::normalizePhone((string)$ttn['phone_sender']);
// Fallback: якщо phone_sender не збережено — взяти з дефолтного контакту відправника
if (!$senderPhone && !empty($ttn['sender_ref'])) {
    $defContact = \Papir\Crm\SenderRepository::getDefaultContact($ttn['sender_ref']);
    if ($defContact && !empty($defContact['phone'])) {
        $senderPhone = \Papir\Crm\TtnService::normalizePhone($defContact['phone']);
    }
}
if ($senderPhone) {
    $eSenderPhone = \Database::escape('Papir', $senderPhone);
    $eSenderRef   = \Database::escape('Papir', $ttn['sender_ref']);
    $rContact = \Database::fetchRow('Papir',
        "SELECT Ref FROM np_sender_contact_persons
         WHERE sender_ref = '{$eSenderRef}'
           AND REGEXP_REPLACE(phone,'[^0-9]','') LIKE '%{$eSenderPhone}'
         LIMIT 1");
    if ($rContact['ok'] && !empty($rContact['row']['Ref'])) {
        $contactSenderRef = $rContact['row']['Ref'];
    }
}
// Fallback: first contact person for this sender
if (!$contactSenderRef) {
    $eSenderRef = \Database::escape('Papir', $ttn['sender_ref']);
    $rFirst = \Database::fetchRow('Papir',
        "SELECT Ref FROM np_sender_contact_persons WHERE sender_ref = '{$eSenderRef}' LIMIT 1");
    if ($rFirst['ok'] && !empty($rFirst['row']['Ref'])) {
        $contactSenderRef = $rFirst['row']['Ref'];
    }
}
// Last resort: sender Ref (may fail if it equals Counterparty, but better than nothing)
if (!$contactSenderRef) {
    $contactSenderRef = $sender['Ref'];
}

// ── Apply POST overrides for weight/seats (simple mode) ──────────────────
$curWeight = (float)($ttn['weight'] ?: 0.5);
$curSeats  = (int)($ttn['seats_amount'] ?: 1);
if (isset($_POST['weight'])) {
    $curWeight = (float)$_POST['weight'];
    if ($curWeight <= 0) $curWeight = 0.5;
    $dbUpd['weight'] = $curWeight;
}
if (isset($_POST['seats_amount'])) {
    $curSeats = (int)$_POST['seats_amount'];
    if ($curSeats <= 0) $curSeats = 1;
    $dbUpd['seats_amount'] = $curSeats;
}

// ── Build full NP update payload ──────────────────────────────────────────
$npProps = array(
    'Ref'              => $ttn['ref'],
    // Sender
    'Sender'           => $sender['Counterparty'],
    'CitySender'       => $citySenderRef,
    'SenderAddress'    => $senderAddrRef,
    'ContactSender'    => $contactSenderRef,
    'SendersPhone'     => $senderPhone,
    // Recipient
    'Recipient'        => $recipientNpRef,
    'CityRecipient'    => $cityRecipientRef,
    'RecipientAddress' => $recipientAddressRef,
    'ContactRecipient' => $contactRef,
    'RecipientsPhone'  => \Papir\Crm\TtnService::normalizePhone((string)$ttn['recipients_phone']),
    // Cargo
    'ServiceType'      => $ttn['service_type']  ?: 'WarehouseWarehouse',
    'PaymentMethod'    => $ttn['payment_method'] ?: 'Cash',
    'PayerType'        => $ttn['payer_type']     ?: 'Recipient',
    'CargoType'        => 'Cargo',
    'Weight'           => $curWeight,
    'SeatsAmount'      => $curSeats,
    'Description'      => $ttn['description'] ?: 'Товар',
    'Cost'             => (int)  ($ttn['declared_value'] ?: $ttn['cost'] ?: 1),
);

// AdditionalInformation (#6)
if (!empty($ttn['additional_information'])) {
    $npProps['AdditionalInformation'] = $ttn['additional_information'];
}

// COD
// use_payment_control=1 → NovaPay: AfterpaymentOnGoodsCost (поле на рівні документа)
// use_payment_control=0 → готівка: BackwardDeliveryData.CargoType=Money
$cod = (float)($ttn['backward_delivery_money'] ?: 0);
if ($cod > 0) {
    if (!empty($sender['use_payment_control'])) {
        // Контроль оплати (NovaPay)
        $npProps['AfterpaymentOnGoodsCost'] = (string)(int)round($cod);
        $npProps['BackwardDeliveryData']     = array();
    } else {
        // Готівковий наложений платіж
        $npProps['BackwardDeliveryData'] = array(array(
            'PayerType'        => 'Recipient',
            'CargoType'        => 'Money',
            'RedeliveryString' => (string)(int)round($cod),
        ));
    }
} else {
    $npProps['BackwardDeliveryData'] = array();
}

// OptionsSeat
if (isset($_POST['options_seat'])) {
    $rawSeats = trim($_POST['options_seat']);
    $decoded  = json_decode($rawSeats, true);
    if (is_array($decoded) && !empty($decoded)) {
        $seatsArr  = array();
        $anyManual = false;
        foreach ($decoded as $s) {
            $w   = isset($s['weight']) ? (float)$s['weight'] : 0;
            $l   = isset($s['length']) ? (int)$s['length']   : 0;
            $wi  = isset($s['width'])  ? (int)$s['width']    : 0;
            $hh  = isset($s['height']) ? (int)$s['height']   : 0;
            $vol = ($l > 0 && $wi > 0 && $hh > 0) ? round($l * $wi * $hh / 4000, 2) : 0;
            $seat = array(
                'weight'           => (string)$w,
                'volumetricWidth'  => (string)$wi,
                'volumetricLength' => (string)$l,
                'volumetricHeight' => (string)$hh,
                'volumetricVolume' => (string)$vol,
            );
            if (!empty($s['manual'])) {
                $seat['optionsSeat'] = 'MANUALSORT';
                $anyManual = true;
            }
            $seatsArr[] = $seat;
        }
        if ($seatsArr) $npProps['OptionsSeat'] = $seatsArr;
        $dbUpd['options_seat']    = $rawSeats;
        $dbUpd['manual_handling'] = $anyManual ? 1 : 0;
    } else {
        // #5: Порожній масив місць — якщо в БД є manual_handling=1, зберігаємо OptionsSeat з БД
        if (!empty($ttn['manual_handling']) && !empty($ttn['options_seat'])) {
            $dbSeats = json_decode($ttn['options_seat'], true);
            if (is_array($dbSeats) && !empty($dbSeats)) {
                $seatsArr = array();
                foreach ($dbSeats as $s) {
                    $w   = isset($s['weight']) ? (float)$s['weight'] : 0;
                    $l   = isset($s['length']) ? (int)$s['length']   : 0;
                    $wi  = isset($s['width'])  ? (int)$s['width']    : 0;
                    $hh  = isset($s['height']) ? (int)$s['height']   : 0;
                    $vol = ($l > 0 && $wi > 0 && $hh > 0) ? round($l * $wi * $hh / 4000, 2) : 0;
                    $seat = array(
                        'weight' => (string)$w, 'volumetricWidth' => (string)$wi,
                        'volumetricLength' => (string)$l, 'volumetricHeight' => (string)$hh,
                        'volumetricVolume' => (string)$vol, 'optionsSeat' => 'MANUALSORT',
                    );
                    $seatsArr[] = $seat;
                }
                if ($seatsArr) $npProps['OptionsSeat'] = $seatsArr;
            }
        } else {
            // Простий режим — згенерувати OptionsSeat з Weight/SeatsAmount
            // НП API вимагає OptionsSeat навіть без детальних розмірів
            $simpleWeight = $curWeight;
            $simpleSeats  = $curSeats;
            $perSeatW     = $simpleSeats > 1 ? round($simpleWeight / $simpleSeats, 2) : $simpleWeight;
            $seatsArr     = array();
            for ($i = 0; $i < $simpleSeats; $i++) {
                $seatsArr[] = array(
                    'weight'           => (string)$perSeatW,
                    'volumetricWidth'  => '1',
                    'volumetricLength' => '1',
                    'volumetricHeight' => '1',
                    'volumetricVolume' => '0.01',
                );
            }
            $npProps['OptionsSeat'] = $seatsArr;
            $dbUpd['options_seat']    = null;
            $dbUpd['manual_handling'] = 0;
        }
    }
}

// ── Call NP API ───────────────────────────────────────────────────────────
error_log('[NP_DBG] Calling InternetDocument.update Ref=' . $ttn['ref']
    . ' Sender=' . $npProps['Sender']
    . ' CitySender=' . $npProps['CitySender']
    . ' Recipient=' . $npProps['Recipient']
    . ' RecipientsPhone=' . $npProps['RecipientsPhone']
    . ' RecipientAddress=' . $npProps['RecipientAddress']
    . ' COD=' . $cod
    . PHP_EOL, 3, '/var/log/papir/np_api.log');

$r = $np->call('InternetDocument', 'update', $npProps);

error_log('[NP_DBG] NP result ok=' . ($r['ok']?'true':'false') . ' error=' . $r['error'] . PHP_EOL, 3, '/var/log/papir/np_api.log');

if (!$r['ok']) {
    // If NP says document is printed/locked — update our DB flag
    $errLower = mb_strtolower($r['error'], 'UTF-8');
    if (strpos($errLower, 'друковано') !== false
        || strpos($errLower, 'printed') !== false
        || strpos($errLower, 'роздруковано') !== false
        || strpos($errLower, 'не підлягає редагуванню') !== false) {
        \Papir\Crm\TtnRepository::save(array('id' => $ttnId, 'is_printed' => 1));
    }
    echo json_encode(array('ok' => false, 'error' => 'НП API: ' . $r['error']));
    exit;
}

// ── Save user-submitted changes to DB ────────────────────────────────────
if ($recipientChanged) {
    $fullName = trim($newLastName . ' ' . $newFirstName . ' ' . $newMiddleName);
    if ($fullName) $dbUpd['recipient_contact_person'] = $fullName;
}
// Remove resolved refs from dbUpd — already saved before NP call
unset($dbUpd['city_recipient_ref'], $dbUpd['recipient_np_ref'], $dbUpd['recipient_address'],
      $dbUpd['sender_address_ref'], $dbUpd['city_sender_ref']);

if (count($dbUpd) > 1) {
    \Papir\Crm\TtnRepository::save($dbUpd);
}

$updated = \Papir\Crm\TtnRepository::getById($ttnId);
// #4: Додаємо is_editable щоб кнопка редагування не зникала після збереження
if ($updated) {
    $updated['is_editable'] = ((int)$updated['state_define'] === 1 && !(int)$updated['is_printed']);
}
echo json_encode(array('ok' => true, 'ttn' => $updated));
