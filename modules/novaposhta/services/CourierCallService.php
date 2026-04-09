<?php
namespace Papir\Crm;

/**
 * Handles barcode scan for registry:
 *  1. Look up TTN by int_doc_number
 *  2. Add TTN to scan sheet (existing open or new)
 *  3. Link TTN to courier call (find with capacity or create new)
 *  4. Persist all links to DB
 */
class CourierCallService
{
    const TIME_INTERVALS = array(
        'CityPickingTimeInterval1'  => array('08:00', '09:00'),
        'CityPickingTimeInterval2'  => array('09:00', '10:00'),
        'CityPickingTimeInterval3'  => array('10:00', '12:00'),
        'CityPickingTimeInterval4'  => array('12:00', '14:00'),
        'CityPickingTimeInterval5'  => array('13:00', '14:00'),
        'CityPickingTimeInterval6'  => array('14:00', '16:00'),
        'CityPickingTimeInterval7'  => array('16:00', '18:00'),
        'CityPickingTimeInterval8'  => array('18:00', '19:00'),
        'CityPickingTimeInterval9'  => array('19:00', '20:00'),
        'CityPickingTimeInterval10' => array('20:00', '21:00'),
    );

    const ALL_INTERVALS = array(
        'CityPickingTimeInterval1',
        'CityPickingTimeInterval2',
        'CityPickingTimeInterval3',
        'CityPickingTimeInterval4',
        'CityPickingTimeInterval5',
        'CityPickingTimeInterval6',
        'CityPickingTimeInterval7',
        'CityPickingTimeInterval8',
        'CityPickingTimeInterval9',
        'CityPickingTimeInterval10',
    );

    /**
     * Process a scanned barcode (NP TTN).
     *
     * @param string $intDocNumber  14-digit TTN barcode
     * @return array  ['ok'=>bool, 'data'=>array, 'errors'=>array, 'warnings'=>array]
     */
    public static function processScan($intDocNumber)
    {
        $result = array('ok' => true, 'data' => array(), 'errors' => array(), 'warnings' => array());

        // ── 1. Look up TTN ──────────────────────────────────────────────────
        $ttn = TtnRepository::getByIntDocNumber($intDocNumber);
        if (!$ttn) {
            $result['ok'] = false;
            $result['errors'][] = 'ТТН не знайдено';
            return $result;
        }

        $result['data'] = array(
            'name'   => $ttn['recipient_contact_person'],
            'phone'  => $ttn['recipients_phone'],
            'state'  => $ttn['state_name'],
            'weight' => $ttn['weight'],
            'seats'  => $ttn['seats_amount'],
        );

        $senderRef = $ttn['sender_ref'];
        $ttnRef    = $ttn['ref'];
        $ttnWeight = (float)$ttn['weight'];

        $sender = SenderRepository::getByRef($senderRef);
        if (!$sender || !$sender['api']) {
            $result['ok'] = false;
            $result['errors'][] = 'Відправник не знайдений або не має API ключа';
            return $result;
        }

        $np = new NovaPoshta($sender['api']);

        // ── 2. Add to scan sheet ────────────────────────────────────────────
        $sheetResult = self::addToScanSheet($np, $sender, $ttnRef);

        if (!empty($sheetResult['errors'])) {
            foreach ($sheetResult['errors'] as $e) {
                $result['warnings'][] = 'Реєстр: ' . $e;
            }
        }

        $sheetRef   = $sheetResult['sheet_ref'];
        $isNewSheet = $sheetResult['is_new'];

        if (!empty($sheetResult['sheet_number'])) {
            $result['data']['sheets'] = $sheetResult['sheet_number'];
        }

        // Update scan_sheet_ref on TTN only if NP accepted it (no item-level errors)
        if ($sheetRef && $sheetRef !== $ttn['scan_sheet_ref'] && empty($sheetResult['errors'])) {
            $eSheet = \Database::escape('Papir', $sheetRef);
            $eDoc   = \Database::escape('Papir', $intDocNumber);
            \Database::query('Papir',
                "UPDATE ttn_novaposhta SET scan_sheet_ref = '{$eSheet}'
                 WHERE int_doc_number = '{$eDoc}'");
        }

        // ── 3. Courier call ─────────────────────────────────────────────────
        $senderInfo = self::getSenderInfo($senderRef);
        if (!$senderInfo) {
            $result['warnings'][] = 'Відправник не налаштований для виклику кур\'єра (немає contact_ref або адреси)';
            return $result;
        }

        $plannedWeight = (float)($senderInfo['courier_call_planned_weight'] ?: 300);
        $interval      = $senderInfo['courier_call_interval'] ?: 'CityPickingTimeInterval7';

        $callNumber = null;
        $callNpRef  = null;

        // Find active call with capacity
        $activeCalls = self::getTodayActiveCalls($np);
        $targetCall  = self::findCallWithCapacity($activeCalls, $ttnWeight, $plannedWeight);

        if ($targetCall) {
            $callNumber = $targetCall['Number'];
            $callNpRef  = isset($targetCall['Ref']) ? $targetCall['Ref'] : null;
            self::insertDocumentsToCall($np, array($intDocNumber), $callNumber);
        } else {
            // No call with capacity → create new with interval fallback
            $callResp = self::createCourierCallWithFallback(
                $np, $senderInfo, $plannedWeight, $interval, $activeCalls
            );

            if ($callResp && !empty($callResp['barcode'])) {
                $callNumber = $callResp['barcode'];
                $callNpRef  = isset($callResp['ref']) ? $callResp['ref'] : null;

                // If sheet already existed, move its TTNs to the new call
                if (!$isNewSheet && $sheetRef) {
                    $sheetDocNumbers = self::getSheetIntDocNumbers($sheetRef, $intDocNumber);
                    if ($sheetDocNumbers) {
                        self::insertDocumentsToCall($np, $sheetDocNumbers, $callNumber);
                    }
                }

                self::insertDocumentsToCall($np, array($intDocNumber), $callNumber);

            } elseif ($callResp && !empty($callResp['existing_call'])) {
                $existingCall = $callResp['existing_call'];
                $callNumber = $existingCall['Number'];
                $callNpRef  = isset($existingCall['Ref']) ? $existingCall['Ref'] : null;
                self::insertDocumentsToCall($np, array($intDocNumber), $callNumber);
            } else {
                $result['warnings'][] = 'Не вдалось створити виклик кур\'єра';
            }
        }

        // ── 4. Persist courier call + TTN link to DB ────────────────────────
        if ($callNumber) {
            $result['data']['courier_call'] = $callNumber;
            self::saveCourierCallToDB($senderRef, $callNumber, $senderInfo);
            self::saveTtnToCallInDB($callNumber, $intDocNumber, (int)$ttn['id'], $ttnWeight);
        }

        return $result;
    }

    // ── Scan sheet ──────────────────────────────────────────────────────────

    /**
     * Add TTN to today's open (unprinted) scan sheet, or create a new one.
     */
    private static function addToScanSheet(NovaPoshta $np, $sender, $ttnRef)
    {
        $errors      = array();
        $sheetRef    = null;
        $sheetNumber = null;
        $isNew       = true;

        // Find today's open unprinted sheet
        $rSheets = $np->call('ScanSheet', 'getScanSheetList', array(
            'Counterparty' => $sender['Counterparty'],
        ));

        if ($rSheets['ok']) {
            $today   = date('Y-m-d');
            $closest = null;
            $closestDiff = null;
            $now = time();

            foreach ($rSheets['data'] as $sheet) {
                if (!empty($sheet['Printed'])) continue;
                $dt = strtotime($sheet['DateTime']);
                if (!$dt) continue;
                if (date('Y-m-d', $dt) !== $today) continue;
                if ($dt > $now) continue;
                $diff = $now - $dt;
                if ($closestDiff === null || $diff < $closestDiff) {
                    $closest = $sheet;
                    $closestDiff = $diff;
                }
            }

            if ($closest) {
                // Check local status — don't use locally-closed sheets
                $localSheet = ScanSheetRepository::getByRef($closest['Ref']);
                if (!$localSheet || $localSheet['status'] === 'open') {
                    $sheetRef = $closest['Ref'];
                    $isNew = false;
                }
            }
        }

        // Add TTN to sheet (or create new)
        $props = array('DocumentRefs' => array($ttnRef));
        if ($sheetRef) {
            $props['Ref'] = $sheetRef;
        }

        $r = $np->call('ScanSheet', 'insertDocuments', $props);

        if ($r['ok']) {
            // Collect errors/warnings from NP response items
            self::collectNpMessages($errors, $r['data']);

            if (!empty($r['data'][0]['Ref']))    $sheetRef    = $r['data'][0]['Ref'];
            if (!empty($r['data'][0]['Number'])) $sheetNumber = $r['data'][0]['Number'];

            // Sync sheet to local DB
            if ($sheetRef) {
                $saveData = array(
                    'Ref'        => $sheetRef,
                    'Number'     => $sheetNumber,
                    'DateTime'   => date('Y-m-d H:i:s'),
                    'sender_ref' => $sender['Ref'],
                    'status'     => 'open',
                );
                if (isset($r['data'][0]['Count'])) {
                    $saveData['Count'] = (int)$r['data'][0]['Count'];
                }
                ScanSheetRepository::save($saveData);
            }
        } else {
            $errors[] = $r['error'] ?: 'Помилка додавання до реєстру';
        }

        return array(
            'sheet_ref'    => $sheetRef,
            'sheet_number' => $sheetNumber,
            'is_new'       => $isNew,
            'errors'       => $errors,
        );
    }

    /** Collect errors/warnings from NP insertDocuments response data items */
    private static function collectNpMessages(&$errors, $data)
    {
        if (empty($data)) return;
        foreach ((array)$data as $item) {
            if (!is_array($item)) continue;
            // Top-level item errors (data[].Errors)
            if (!empty($item['Errors'])) {
                foreach ((array)$item['Errors'] as $e) {
                    $errors[] = is_array($e) ? $e['Error'] : $e;
                }
            }
            if (!empty($item['Warnings'])) {
                foreach ((array)$item['Warnings'] as $w) {
                    $errors[] = is_array($w) ? $w['Warning'] : $w;
                }
            }
            // Nested Data.Errors (data[].Data.Errors) — NP hides per-document errors here
            if (!empty($item['Data']['Errors'])) {
                foreach ((array)$item['Data']['Errors'] as $e) {
                    $errors[] = is_array($e)
                        ? (isset($e['Number']) ? $e['Number'] . ': ' : '') . (isset($e['Error']) ? $e['Error'] : '')
                        : $e;
                }
            }
            if (!empty($item['Data']['Warnings'])) {
                foreach ((array)$item['Data']['Warnings'] as $w) {
                    $errors[] = is_array($w)
                        ? (isset($w['Number']) ? $w['Number'] . ': ' : '') . (isset($w['Warning']) ? $w['Warning'] : '')
                        : $w;
                }
            }
        }
        // Log item-level messages so we can debug NP rejections
        if (!empty($errors)) {
            $line = '[' . date('Y-m-d H:i:s') . '] ITEM_ERR ScanSheet.insertDocuments | '
                  . implode('; ', $errors);
            error_log($line . PHP_EOL, 3, NovaPoshta::LOG_FILE);
        }
    }

    // ── Courier call ────────────────────────────────────────────────────────

    /** Get sender info needed for courier call (contact_ref, address_ref, settings) */
    private static function getSenderInfo($senderRef)
    {
        $e = \Database::escape('Papir', $senderRef);
        $r = \Database::fetchRow('Papir',
            "SELECT s.Ref, s.Counterparty, s.contact_ref,
                    s.courier_call_interval, s.courier_call_planned_weight,
                    a.Ref AS address_ref
             FROM np_sender s
             LEFT JOIN np_sender_address a ON a.sender_ref = s.Ref AND a.is_default = 1
             WHERE s.Ref = '{$e}'
             LIMIT 1");

        if (!$r['ok'] || !$r['row']) return null;
        if (!$r['row']['contact_ref'] || !$r['row']['address_ref']) return null;
        return $r['row'];
    }

    /** Get today's active (not Done/Cancelled) courier calls from NP API */
    private static function getTodayActiveCalls(NovaPoshta $np)
    {
        $today = date('d.m.Y');
        $r = $np->call('CarCallGeneral', 'getOrdersListCourierCall', array(
            'DateFrom' => $today,
            'DateTo'   => $today,
        ));

        if (!$r['ok']) return array();

        $active = array();
        foreach ($r['data'] as $call) {
            if (!is_array($call) || !isset($call['Status'])) continue;
            if ($call['Status'] !== 'Done' && $call['Status'] !== 'Cancelled') {
                $active[] = $call;
            }
        }
        return $active;
    }

    /**
     * Find a call where actual weight + new TTN weight <= 110% of planned weight.
     */
    private static function findCallWithCapacity($calls, $ttnWeight, $plannedWeight)
    {
        if (empty($calls)) return null;
        foreach ($calls as $call) {
            if (!isset($call['Number'])) continue;
            $actual = self::getCallActualWeight($call['Number']);
            if (($actual + $ttnWeight) <= $plannedWeight * 1.1) {
                return $call;
            }
        }
        return null;
    }

    /** Actual weight of TTNs in a call (from our DB) */
    private static function getCallActualWeight($callNumber)
    {
        $e = \Database::escape('Papir', $callNumber);
        $r = \Database::fetchRow('Papir',
            "SELECT COALESCE(SUM(cct.weight), 0) AS total
             FROM np_courier_call_ttns cct
             JOIN np_courier_calls cc ON cc.id = cct.courier_call_id
             WHERE cc.Barcode = '{$e}'");
        return ($r['ok'] && $r['row']) ? (float)$r['row']['total'] : 0.0;
    }

    /** Add TTN refs to a courier call via NP API */
    private static function insertDocumentsToCall(NovaPoshta $np, array $ttnRefs, $callNumber)
    {
        return $np->call('CarCallGeneral', 'insertDocuments', array(
            'LinkedDocuments' => $ttnRefs,
            'Number'          => $callNumber,
        ));
    }

    /**
     * Create a courier call, trying successive time intervals if the preferred one fails.
     *
     * Returns:
     *  - ['barcode'=>..., 'ref'=>...]  on success
     *  - ['existing_call'=>...]        if NP says "already have order"
     *  - null                          if all intervals fail
     */
    private static function createCourierCallWithFallback(
        NovaPoshta $np, $senderInfo, $plannedWeight, $preferredInterval, $activeCalls
    ) {
        $startIdx = array_search($preferredInterval, self::ALL_INTERVALS);
        if ($startIdx === false) $startIdx = 0;

        for ($i = $startIdx; $i < count(self::ALL_INTERVALS); $i++) {
            $interval = self::ALL_INTERVALS[$i];

            $r = $np->call('CarCallGeneral', 'saveCourierCall', array(
                'ContactSenderRef'      => $senderInfo['contact_ref'],
                'PreferredDeliveryDate' => date('d.m.Y'),
                'PlanedWeight'          => (string)(int)ceil($plannedWeight),
                'TimeInterval'          => $interval,
                'CounterpartySender'    => $senderInfo['Counterparty'] ?: $senderInfo['Ref'],
                'AddressSenderRef'      => $senderInfo['address_ref'],
            ));

            if ($r['ok'] && !empty($r['data'][0]['Barcode'])) {
                return array(
                    'barcode' => $r['data'][0]['Barcode'],
                    'ref'     => isset($r['data'][0]['Ref']) ? $r['data'][0]['Ref'] : null,
                );
            }

            $firstError = $r['error'] ?: '';

            // "Already have order" for this interval
            if (strpos($firstError, 'already have order') !== false
                || strpos($firstError, 'вже є заявка') !== false) {
                $existing = self::findCallByInterval($activeCalls, $interval);
                if ($existing) {
                    return array('existing_call' => $existing);
                }
                // Try fresh API data
                $freshCalls = self::getTodayActiveCalls($np);
                $existing = self::findCallByInterval($freshCalls, $interval);
                if ($existing) {
                    return array('existing_call' => $existing);
                }
                continue; // try next interval
            }

            // Interval is no longer available
            if (strpos($firstError, 'TimeInterval is incorrect') !== false
                || strpos($firstError, 'incorrect') !== false) {
                continue;
            }

            // Other error — stop
            break;
        }

        return null;
    }

    private static function findCallByInterval($calls, $interval)
    {
        foreach ($calls as $call) {
            if (isset($call['TimeInterval']) && $call['TimeInterval'] === $interval) {
                return $call;
            }
        }
        return null;
    }

    // ── DB persistence ──────────────────────────────────────────────────────

    /** Save courier call to np_courier_calls if not exists */
    private static function saveCourierCallToDB($senderRef, $callNumber, $senderInfo)
    {
        $eCall = \Database::escape('Papir', $callNumber);
        $rExist = \Database::fetchRow('Papir',
            "SELECT id FROM np_courier_calls WHERE Barcode = '{$eCall}' LIMIT 1");
        if ($rExist['ok'] && $rExist['row']) return;

        CourierCallRepository::insert(array(
            'Barcode'              => $callNumber,
            'sender_ref'           => $senderRef,
            'contact_sender_ref'   => $senderInfo ? $senderInfo['contact_ref'] : null,
            'address_sender_ref'   => $senderInfo ? $senderInfo['address_ref'] : null,
            'preferred_delivery_date' => date('d.m.Y'),
            'status'               => 'pending',
        ));
    }

    /** Link TTN to courier call in np_courier_call_ttns */
    private static function saveTtnToCallInDB($callNumber, $intDocNumber, $ttnId, $weight)
    {
        $eCall = \Database::escape('Papir', $callNumber);
        $rCall = \Database::fetchRow('Papir',
            "SELECT id, status FROM np_courier_calls WHERE Barcode = '{$eCall}' LIMIT 1");
        if (!$rCall['ok'] || !$rCall['row']) return;
        if ($rCall['row']['status'] === 'done' || $rCall['row']['status'] === 'cancelled') return;

        $callId = (int)$rCall['row']['id'];
        CourierCallRepository::upsertTtn($callId, $intDocNumber, $ttnId, $weight);
    }

    /** Get int_doc_numbers of TTNs in a scan sheet (excluding a given number) */
    private static function getSheetIntDocNumbers($sheetRef, $excludeDocNumber)
    {
        $eSheet   = \Database::escape('Papir', $sheetRef);
        $eExclude = \Database::escape('Papir', $excludeDocNumber);
        $r = \Database::fetchAll('Papir',
            "SELECT int_doc_number FROM ttn_novaposhta
             WHERE scan_sheet_ref = '{$eSheet}'
               AND deletion_mark = 0
               AND int_doc_number != '{$eExclude}'");
        if (!$r['ok']) return array();
        $nums = array();
        foreach ($r['rows'] as $row) {
            if ($row['int_doc_number']) $nums[] = $row['int_doc_number'];
        }
        return $nums;
    }
}