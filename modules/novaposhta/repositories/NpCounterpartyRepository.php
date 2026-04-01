<?php
namespace Papir\Crm;

/**
 * Counterparties_np — NP cached counterparties (recipients/senders).
 * Linked to Papir counterparty via counterparty_id.
 */
class NpCounterpartyRepository
{
    /**
     * Find NP counterparty by Papir counterparty_id + sender_ref.
     */
    public static function findByCounterparty($counterpartyId, $senderRef)
    {
        $ec = \Database::escape('Papir', $senderRef);
        $r = \Database::fetchRow('Papir',
            "SELECT * FROM Counterparties_np
             WHERE counterparty_id = " . (int)$counterpartyId . "
               AND Ref_sender = '{$ec}'
             LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    /**
     * Find NP counterparty by EDRPOU (for organizations).
     */
    public static function findByEdrpou($edrpou, $senderRef = null)
    {
        $ee = \Database::escape('Papir', $edrpou);
        $sql = "SELECT * FROM Counterparties_np WHERE EDRPOU = '{$ee}'";
        if ($senderRef !== null) {
            $es = \Database::escape('Papir', $senderRef);
            $sql .= " AND Ref_sender = '{$es}'";
        }
        $sql .= " LIMIT 1";
        $r = \Database::fetchRow('Papir', $sql);
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    /**
     * Find NP counterparty by NP Ref.
     */
    public static function findByRef($ref)
    {
        $e = \Database::escape('Papir', $ref);
        $r = \Database::fetchRow('Papir',
            "SELECT * FROM Counterparties_np WHERE Ref = '{$e}' LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    /**
     * Soft-upsert NP counterparty from API response.
     * Links to Papir counterparty if counterparty_id provided.
     */
    public static function upsert($npData, $senderRef = null, $counterpartyId = null)
    {
        $ref = isset($npData['Ref']) ? $npData['Ref'] : '';
        if (!$ref) return false;

        $data = array(
            'Ref'                      => $ref,
            'Counterparty'             => isset($npData['Counterparty'])             ? $npData['Counterparty']             : null,
            'FirstName'                => isset($npData['FirstName'])                ? $npData['FirstName']                : null,
            'LastName'                 => isset($npData['LastName'])                 ? $npData['LastName']                 : null,
            'MiddleName'               => isset($npData['MiddleName'])               ? $npData['MiddleName']               : null,
            'CounterpartyFullName'     => isset($npData['CounterpartyFullName'])     ? $npData['CounterpartyFullName']     : null,
            'OwnershipFormRef'         => isset($npData['OwnershipFormRef'])         ? $npData['OwnershipFormRef']         : null,
            'OwnershipFormDescription' => isset($npData['OwnershipFormDescription']) ? $npData['OwnershipFormDescription'] : null,
            'EDRPOU'                   => isset($npData['EDRPOU'])                   ? $npData['EDRPOU']                   : null,
            'CounterpartyType'         => isset($npData['CounterpartyType'])         ? $npData['CounterpartyType']         : null,
            'CityDescription'          => isset($npData['CityDescription'])          ? $npData['CityDescription']          : null,
        );

        if ($senderRef !== null) {
            $data['Ref_sender'] = $senderRef;
        }
        if ($counterpartyId !== null) {
            $data['counterparty_id'] = (int)$counterpartyId;
        }

        return \Database::upsertOne('Papir', 'Counterparties_np', $data, array('Ref'));
    }

    /**
     * Link existing NP counterparty to Papir counterparty.
     */
    public static function linkToCounterparty($npRef, $counterpartyId)
    {
        $e = \Database::escape('Papir', $npRef);
        return \Database::query('Papir',
            "UPDATE Counterparties_np SET counterparty_id = " . (int)$counterpartyId . "
             WHERE Ref = '{$e}'");
    }

    /**
     * Get or try to match NP counterparty for a Papir counterparty.
     * Returns NP Ref if found, null otherwise.
     */
    public static function getNpRefForCounterparty($counterpartyId, $senderRef)
    {
        $found = self::findByCounterparty($counterpartyId, $senderRef);
        return $found ? $found['Ref'] : null;
    }
}