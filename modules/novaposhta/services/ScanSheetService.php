<?php
namespace Papir\Crm;

/**
 * Scan sheet (registry) operations via NP API.
 */
class ScanSheetService
{
    /**
     * Create or append to a registry.
     * If $scanSheetRef is null — NP creates a new registry.
     *
     * @param string $senderRef
     * @param array  $ttnRefs       array of ttn_novaposhta.ref values
     * @param string|null $scanSheetRef  existing registry ref (or null for new)
     */
    public static function addDocuments($senderRef, $ttnRefs, $scanSheetRef = null)
    {
        $sender = SenderRepository::getByRef($senderRef);
        if (!$sender) return array('ok' => false, 'error' => 'Sender not found');

        $np = new NovaPoshta($sender['api']);

        $docList = array();
        foreach ($ttnRefs as $ref) {
            $docList[] = array('Ref' => $ref);
        }

        $props = array(
            'Sender'            => $sender['Counterparty'],
            'DocumentRefs'      => $docList,
        );
        if ($scanSheetRef) {
            $props['Ref'] = $scanSheetRef;
        }

        $r = $np->call('ScanSheet', 'insertDocuments', $props);
        if (!$r['ok']) return array('ok' => false, 'error' => $r['error']);

        $ssData = isset($r['data'][0]) ? $r['data'][0] : array();
        $ssRef  = isset($ssData['Ref']) ? $ssData['Ref'] : '';

        if ($ssRef) {
            ScanSheetRepository::save(array(
                'Ref'             => $ssRef,
                'Number'          => isset($ssData['Number']) ? $ssData['Number'] : null,
                'DateTime'        => isset($ssData['DateTime']) ? self::parseDate($ssData['DateTime']) : date('Y-m-d H:i:s'),
                'Count'           => isset($ssData['Count']) ? (int)$ssData['Count'] : count($ttnRefs),
                'sender_ref'      => $senderRef,
                'counterparty_ref'=> $sender['Counterparty'],
                'status'          => 'open',
            ));

            // Tag each TTN with the scan sheet ref + fire ttn_handed_to_courier
            foreach ($ttnRefs as $tRef) {
                $eTRef = \Database::escape('Papir', $tRef);
                $eSsRef = \Database::escape('Papir', $ssRef);
                \Database::query('Papir',
                    "UPDATE ttn_novaposhta SET scan_sheet_ref = '{$eSsRef}' WHERE ref = '{$eTRef}'");

                // Look up TTN id → fire handed_to_courier (no-op if no linked order)
                $rTtnId = \Database::fetchRow('Papir',
                    "SELECT id FROM ttn_novaposhta WHERE ref = '{$eTRef}' LIMIT 1");
                if ($rTtnId['ok'] && !empty($rTtnId['row'])) {
                    TtnService::fireTtnHandedToCourier((int)$rTtnId['row']['id']);
                }
            }
        }

        return array('ok' => true, 'scan_sheet_ref' => $ssRef, 'data' => $ssData);
    }

    /**
     * Get list of scan sheets from NP API + sync to DB.
     * Calls getScanSheet per sheet to get TotalCost, TotalRedeliverySum, Printed.
     */
    public static function syncList($senderRef)
    {
        $sender = SenderRepository::getByRef($senderRef);
        if (!$sender) return array('ok' => false, 'error' => 'Sender not found');

        $np = new NovaPoshta($sender['api']);
        $r  = $np->call('ScanSheet', 'getScanSheetList', array(
            'Counterparty' => $sender['Counterparty'],
        ));
        if (!$r['ok']) return array('ok' => false, 'error' => $r['error']);

        $npRefs = array();

        foreach ($r['data'] as $ss) {
            if (empty($ss['Ref'])) continue;
            $npRefs[] = $ss['Ref'];

            $detail    = null;
            $rDetail   = $np->call('ScanSheet', 'getScanSheet', array('Ref' => $ss['Ref']));
            if ($rDetail['ok'] && !empty($rDetail['data'][0])) {
                $detail = $rDetail['data'][0];
            }

            $printed    = ($detail && !empty($detail['Printed']))              ? 1    : 0;
            $totalCost  = ($detail && isset($detail['TotalCost']))             ? (float)$detail['TotalCost']          : null;
            $totalRedel = ($detail && isset($detail['TotalRedeliverySum']))    ? (float)$detail['TotalRedeliverySum']  : null;

            ScanSheetRepository::save(array(
                'Ref'              => $ss['Ref'],
                'Number'           => isset($ss['Number'])   ? $ss['Number']   : null,
                'DateTime'         => isset($ss['DateTime']) ? self::parseDate($ss['DateTime']) : null,
                'Count'            => isset($ss['Count'])    ? (int)$ss['Count'] : 0,
                'total_cost'       => $totalCost,
                'total_redelivery' => $totalRedel,
                'sender_ref'       => $senderRef,
                'printed'          => $printed,
                'status'           => $printed ? 'closed' : 'open',
            ));
        }

        // Mark registries that NP no longer returns as disbanded (closed)
        // and clear scan_sheet_ref on their TTNs
        $disbanded = 0;
        if (!empty($npRefs)) {
            $eSender = \Database::escape('Papir', $senderRef);
            $inList  = implode("','", array_map(function($ref) {
                return \Database::escape('Papir', $ref);
            }, $npRefs));

            $rMissing = \Database::fetchAll('Papir',
                "SELECT Ref FROM np_scan_sheets
                 WHERE sender_ref = '{$eSender}' AND status = 'open'
                   AND Number IS NOT NULL AND Ref NOT IN ('{$inList}')");

            if ($rMissing['ok']) {
                foreach ($rMissing['rows'] as $miss) {
                    $eMissRef = \Database::escape('Papir', $miss['Ref']);
                    // Clear scan_sheet_ref on TTNs belonging to this disbanded registry
                    \Database::query('Papir',
                        "UPDATE ttn_novaposhta SET scan_sheet_ref = NULL
                         WHERE scan_sheet_ref = '{$eMissRef}'");
                    // Mark as disbanded
                    \Database::query('Papir',
                        "UPDATE np_scan_sheets SET status = 'disbanded'
                         WHERE Ref = '{$eMissRef}'");
                    $disbanded++;
                }
            }
        } elseif (count($r['data']) === 0) {
            // NP returned empty list — mark all our open registries for this sender as disbanded
            $eSender = \Database::escape('Papir', $senderRef);
            $rAll = \Database::fetchAll('Papir',
                "SELECT Ref FROM np_scan_sheets
                 WHERE sender_ref = '{$eSender}' AND status = 'open' AND Number IS NOT NULL");
            if ($rAll['ok']) {
                foreach ($rAll['rows'] as $row) {
                    $eMissRef = \Database::escape('Papir', $row['Ref']);
                    \Database::query('Papir',
                        "UPDATE ttn_novaposhta SET scan_sheet_ref = NULL WHERE scan_sheet_ref = '{$eMissRef}'");
                    \Database::query('Papir',
                        "UPDATE np_scan_sheets SET status = 'disbanded' WHERE Ref = '{$eMissRef}'");
                    $disbanded++;
                }
            }
        }

        return array('ok' => true, 'count' => count($r['data']), 'disbanded' => $disbanded);
    }

    /**
     * Get scan sheet details (list of TTNs in it) from NP API.
     */
    public static function getDetail($senderRef, $scanSheetRef)
    {
        $sender = SenderRepository::getByRef($senderRef);
        if (!$sender) return array('ok' => false, 'error' => 'Sender not found');

        $np = new NovaPoshta($sender['api']);
        $r  = $np->call('ScanSheet', 'getScanSheet', array('Ref' => $scanSheetRef));
        if (!$r['ok']) return array('ok' => false, 'error' => $r['error']);

        return array('ok' => true, 'data' => isset($r['data'][0]) ? $r['data'][0] : array());
    }

    /**
     * Disband (delete) a scan sheet. TTNs are freed but NOT deleted.
     */
    public static function delete($senderRef, $scanSheetRef)
    {
        $sender = SenderRepository::getByRef($senderRef);
        if (!$sender) return array('ok' => false, 'error' => 'Sender not found');

        $np = new NovaPoshta($sender['api']);
        $r  = $np->call('ScanSheet', 'deleteScanSheet', array('ScanSheetRefs' => $scanSheetRef));
        if (!$r['ok']) return array('ok' => false, 'error' => $r['error']);

        // Clear scan_sheet_ref on TTNs that belonged to this sheet
        $eSsRef = \Database::escape('Papir', $scanSheetRef);
        \Database::query('Papir',
            "UPDATE ttn_novaposhta SET scan_sheet_ref = NULL WHERE scan_sheet_ref = '{$eSsRef}'");

        ScanSheetRepository::delete($scanSheetRef);
        return array('ok' => true);
    }

    private static function parseDate($str)
    {
        if (!$str) return null;
        $ts = strtotime($str);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
}