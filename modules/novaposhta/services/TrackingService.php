<?php
namespace Papir\Crm;

/**
 * TTN tracking: batch update statuses from NP API.
 * Fires 'ttn_status_changed' event via TriggerEngine when state changes.
 */
class TrackingService
{
    // NP state_define values (final — stop tracking)
    const FINAL_STATES = array(9, 10, 106); // delivered, returned, cancelled

    // NP state_define groupings for order status mapping
    const STATES_SHIPPED  = array(1, 4, 5, 6, 7, 8, 101, 104, 105); // in transit / at branch / courier
    const STATES_RECEIVED = array(9);                                 // delivered to client
    const STATES_RETURN   = array(10, 11, 103);                       // returning / returned

    /**
     * Track a batch of TTNs (up to 100 per API call).
     * Groups by sender API key, calls TrackingDocument.getStatusDocuments.
     *
     * @param array $ttns  rows from TtnRepository::getForTracking()
     * @return array ['updated'=>int, 'errors'=>array]
     */
    public static function trackBatch($ttns)
    {
        if (empty($ttns)) return array('updated' => 0, 'errors' => array());

        // Group by sender API key; bump TTNs with no API key so they don't block the queue
        $byApi = array();
        foreach ($ttns as $ttn) {
            $apiKey = $ttn['sender_api'] ?: '';
            if (!$apiKey) {
                // Unknown sender — bump timestamp so this TTN goes to back of queue
                \Database::query('Papir',
                    "UPDATE ttn_novaposhta SET date_last_updated_status=NOW(), updated_at=NOW()
                     WHERE id=" . (int)$ttn['id']);
                continue;
            }
            if (!isset($byApi[$apiKey])) $byApi[$apiKey] = array();
            $byApi[$apiKey][] = $ttn;
        }

        $updated = 0;
        $errors  = array();

        foreach ($byApi as $apiKey => $group) {
            $np = new NovaPoshta($apiKey);

            // NP allows max 100 documents per request
            $chunks = array_chunk($group, 100);
            foreach ($chunks as $chunk) {
                $docs = array();
                foreach ($chunk as $ttn) {
                    $docs[] = array('DocumentNumber' => $ttn['int_doc_number'], 'Phone' => '');
                }

                $r = $np->call('TrackingDocument', 'getStatusDocuments',
                    array('Documents' => $docs, 'Language' => 'uk'));

                if (!$r['ok']) {
                    $errors[] = 'Tracking API error: ' . $r['error'];
                    // Still bump date_last_updated_status so these don't clog the queue
                    foreach ($chunk as $ttn) {
                        \Database::query('Papir',
                            "UPDATE ttn_novaposhta SET date_last_updated_status=NOW(), updated_at=NOW()
                             WHERE id=" . (int)$ttn['id']);
                    }
                    continue;
                }

                // Build index of returned doc numbers for fast lookup
                $returnedNums = array();
                foreach ($r['data'] as $status) {
                    $docNum = isset($status['Number']) ? $status['Number'] : '';
                    if ($docNum) $returnedNums[$docNum] = $status;
                }

                foreach ($chunk as $ttn) {
                    if (!isset($returnedNums[$ttn['int_doc_number']])) {
                        // NP doesn't know this TTN (old/invalid) — bump timestamp to skip next time
                        \Database::query('Papir',
                            "UPDATE ttn_novaposhta SET date_last_updated_status=NOW(), updated_at=NOW()
                             WHERE id=" . (int)$ttn['id']);
                        continue;
                    }

                    $status      = $returnedNums[$ttn['int_doc_number']];
                    $stateId     = isset($status['StatusCode'])        ? (int)$status['StatusCode']        : null;
                    $stateName   = isset($status['Status'])            ? $status['Status']                  : '';
                    $stateDefine = isset($status['StatusCode'])        ? (int)$status['StatusCode']         : null;
                    $estDelivery = isset($status['ScheduledDeliveryDate']) ? self::parseDate($status['ScheduledDeliveryDate']) : null;
                    $dateStorage = isset($status['DateFirstDayStorage'])   ? self::parseDate($status['DateFirstDayStorage'])   : null;
                    $arrived     = isset($status['ActualDeliveryDate'])    ? self::parseDate($status['ActualDeliveryDate'])     : null;

                    $oldStateDefine = isset($ttn['state_define']) ? (int)$ttn['state_define'] : null;

                    TtnRepository::updateStatus(
                        $ttn['id'],
                        $stateId, $stateName, $stateDefine,
                        $estDelivery, $dateStorage, $arrived
                    );
                    $updated++;

                    // Fire event if state actually changed and TTN is linked to an order
                    if ($stateDefine !== null && $stateDefine !== $oldStateDefine) {
                        self::fireTtnStatusChanged($ttn, $oldStateDefine, $stateDefine, $stateName);
                    }
                }
            }
        }

        return array('updated' => $updated, 'errors' => $errors);
    }

    /**
     * Track a single TTN by int_doc_number (any sender key — iterate).
     */
    public static function trackOne($ttnId)
    {
        $ttn = TtnRepository::getById($ttnId);
        if (!$ttn || !$ttn['int_doc_number']) {
            return array('ok' => false, 'error' => 'TTN not found or no doc number');
        }
        if (!$ttn['sender_api']) {
            return array('ok' => false, 'error' => 'Sender API key not found');
        }

        $np = new NovaPoshta($ttn['sender_api']);
        $r = $np->call('TrackingDocument', 'getStatusDocuments', array(
            'Documents' => array(array(
                'DocumentNumber' => $ttn['int_doc_number'],
                'Phone'          => $ttn['recipients_phone'] ?: '',
            )),
            'Language' => 'uk',
        ));

        if (!$r['ok']) return array('ok' => false, 'error' => $r['error']);
        if (empty($r['data'])) return array('ok' => false, 'error' => 'No tracking data returned');

        $status = $r['data'][0];
        $oldStateDefine = isset($ttn['state_define']) ? (int)$ttn['state_define'] : null;
        $newStateDefine = isset($status['StatusCode']) ? (int)$status['StatusCode'] : null;

        TtnRepository::updateStatus(
            $ttnId,
            isset($status['StatusCode'])           ? (int)$status['StatusCode']  : null,
            isset($status['Status'])               ? $status['Status']            : '',
            $newStateDefine,
            isset($status['ScheduledDeliveryDate']) ? self::parseDate($status['ScheduledDeliveryDate']) : null,
            isset($status['DateFirstDayStorage'])   ? self::parseDate($status['DateFirstDayStorage'])   : null,
            isset($status['ActualDeliveryDate'])    ? self::parseDate($status['ActualDeliveryDate'])     : null
        );

        // Fire event if state changed
        if ($newStateDefine !== null && $newStateDefine !== $oldStateDefine) {
            self::fireTtnStatusChanged($ttn, $oldStateDefine, $newStateDefine,
                isset($status['Status']) ? $status['Status'] : '');
        }

        return array('ok' => true, 'status' => $status);
    }

    /**
     * Fire ttn_status_changed event via TriggerEngine.
     * Only fires if the TTN is linked to an order.
     */
    private static function fireTtnStatusChanged($ttn, $oldStateDefine, $newStateDefine, $stateName)
    {
        // TTN must be linked to an order
        $orderId = isset($ttn['customerorder_id']) ? (int)$ttn['customerorder_id'] : 0;
        if (!$orderId) {
            // Try via document_link
            $r = \Database::fetchRow('Papir',
                "SELECT dl.to_id AS order_id, co.counterparty_id
                 FROM document_link dl
                 JOIN customerorder co ON co.id = dl.to_id
                 WHERE dl.from_type = 'ttn_np' AND dl.from_id = " . (int)$ttn['id'] . "
                   AND dl.to_type = 'customerorder'
                 LIMIT 1");
            if ($r['ok'] && !empty($r['row'])) {
                $orderId = (int)$r['row']['order_id'];
                $cpId    = (int)$r['row']['counterparty_id'];
            }
        }
        if (!$orderId) return;

        $cpId = isset($cpId) ? $cpId : (isset($ttn['counterparty_id']) ? (int)$ttn['counterparty_id'] : 0);

        \TriggerEngine::fire('ttn_status_changed', array(
            'order_id'        => $orderId,
            'counterparty_id' => $cpId,
            'ttn' => array(
                'id'               => (int)$ttn['id'],
                'int_doc_number'   => $ttn['int_doc_number'],
                'old_state_define' => $oldStateDefine,
                'new_state_define' => $newStateDefine,
                'state_name'       => $stateName,
            ),
        ));
    }

    private static function parseDate($str)
    {
        if (!$str) return null;
        $ts = strtotime($str);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
}
