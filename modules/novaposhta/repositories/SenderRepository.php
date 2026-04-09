<?php
namespace Papir\Crm;

/**
 * np_sender + np_sender_address.
 *
 * API keys are read from integration_connections (primary) with fallback
 * to np_sender.api (legacy). is_default also comes from integration_connections.
 */
class SenderRepository
{
    /**
     * Enrich sender row with API key + flags from integration_connections.
     */
    private static function enrichWithConnection($sender)
    {
        if (!$sender) return $sender;
        require_once __DIR__ . '/../../integrations/IntegrationSettingsService.php';
        $conn = \IntegrationSettingsService::findConnectionByMeta('novaposhta', 'sender_ref', $sender['Ref']);
        if ($conn) {
            $sender['api']        = $conn['api_key'];
            $sender['is_default'] = (int)$conn['is_default'];
            $sender['conn_active'] = (int)$conn['is_active'];
        }
        return $sender;
    }

    public static function getAll()
    {
        $r = \Database::fetchAll('Papir',
            "SELECT s.*, o.name AS organization_name
             FROM np_sender s
             LEFT JOIN organization o ON o.id = s.organization_id
             ORDER BY s.is_default DESC, s.Description");
        if (!$r['ok']) return array();
        $rows = $r['rows'];
        // Enrich with connection data
        require_once __DIR__ . '/../../integrations/IntegrationSettingsService.php';
        $conns = \IntegrationSettingsService::getConnections('novaposhta');
        $connMap = array();
        foreach ($conns as $c) {
            if (isset($c['metadata']['sender_ref'])) {
                $connMap[$c['metadata']['sender_ref']] = $c;
            }
        }
        foreach ($rows as &$s) {
            if (isset($connMap[$s['Ref']])) {
                $c = $connMap[$s['Ref']];
                $s['api']        = $c['api_key'];
                $s['is_default'] = (int)$c['is_default'];
                $s['conn_active'] = (int)$c['is_active'];
            }
        }
        unset($s);
        // Re-sort: default first
        usort($rows, function($a, $b) {
            return (isset($b['is_default']) ? $b['is_default'] : 0) - (isset($a['is_default']) ? $a['is_default'] : 0);
        });
        return $rows;
    }

    public static function getDefault()
    {
        // Try integration_connections first
        require_once __DIR__ . '/../../integrations/IntegrationSettingsService.php';
        $conn = \IntegrationSettingsService::getDefaultConnection('novaposhta');
        if ($conn && !empty($conn['metadata']['sender_ref'])) {
            $sender = self::getByRefRaw($conn['metadata']['sender_ref']);
            if ($sender) {
                $sender['api']        = $conn['api_key'];
                $sender['is_default'] = 1;
                return $sender;
            }
        }
        // Fallback: old logic
        $r = \Database::fetchRow('Papir',
            "SELECT * FROM np_sender WHERE is_default = 1 LIMIT 1");
        if ($r['ok'] && $r['row']) return $r['row'];
        $r2 = \Database::fetchRow('Papir',
            "SELECT * FROM np_sender ORDER BY Ref LIMIT 1");
        return ($r2['ok'] && $r2['row']) ? $r2['row'] : null;
    }

    /**
     * Raw DB fetch without connection enrichment (to avoid loops).
     */
    private static function getByRefRaw($ref)
    {
        $e = \Database::escape('Papir', $ref);
        $r = \Database::fetchRow('Papir',
            "SELECT * FROM np_sender WHERE Ref = '{$e}' LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    public static function getByRef($ref)
    {
        $sender = self::getByRefRaw($ref);
        return self::enrichWithConnection($sender);
    }

    /** Get sender for a given organization_id */
    public static function getByOrganization($organizationId)
    {
        $r = \Database::fetchRow('Papir',
            "SELECT * FROM np_sender WHERE organization_id = " . (int)$organizationId . " LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    // ── Sender addresses ──────────────────────────────────────────────────────

    public static function getAddresses($senderRef)
    {
        $e = \Database::escape('Papir', $senderRef);
        $r = \Database::fetchAll('Papir',
            "SELECT * FROM np_sender_address
             WHERE sender_ref = '{$e}'
             ORDER BY is_default DESC, Description");
        return ($r['ok']) ? $r['rows'] : array();
    }

    public static function getDefaultAddress($senderRef)
    {
        $e = \Database::escape('Papir', $senderRef);
        $r = \Database::fetchRow('Papir',
            "SELECT * FROM np_sender_address
             WHERE sender_ref = '{$e}' AND is_default = 1 LIMIT 1");
        if ($r['ok'] && $r['row']) return $r['row'];
        // Fallback: first address
        $r2 = \Database::fetchRow('Papir',
            "SELECT * FROM np_sender_address
             WHERE sender_ref = '{$e}' ORDER BY id LIMIT 1");
        return ($r2['ok'] && $r2['row']) ? $r2['row'] : null;
    }

    /**
     * Soft-upsert sender address from NP API response.
     */
    public static function upsertAddress($senderRef, $npAddress)
    {
        $ref = isset($npAddress['Ref']) ? $npAddress['Ref'] : '';
        if (!$ref) return false;

        $data = array(
            'sender_ref'      => $senderRef,
            'Ref'             => $ref,
            'Description'     => isset($npAddress['Description'])     ? $npAddress['Description']     : null,
            'CityRef'         => isset($npAddress['CityRef'])         ? $npAddress['CityRef']         : null,
            'CityDescription' => isset($npAddress['CityDescription']) ? $npAddress['CityDescription'] : null,
            'StreetRef'       => isset($npAddress['StreetRef'])       ? $npAddress['StreetRef']       : null,
            'BuildingRef'     => isset($npAddress['BuildingRef'])     ? $npAddress['BuildingRef']     : null,
            'WarehouseRef'    => isset($npAddress['WarehouseRef'])    ? $npAddress['WarehouseRef']    : null,
            'address_type'    => isset($npAddress['address_type'])    ? $npAddress['address_type']    : 'street',
            'updated_at'      => date('Y-m-d H:i:s'),
        );
        return \Database::upsertOne('Papir', 'np_sender_address', $data,
            array('sender_ref', 'Ref'));
    }

    public static function getContacts($senderRef)
    {
        $e = \Database::escape('Papir', $senderRef);
        $r = \Database::fetchAll('Papir',
            "SELECT cp.id, cp.Ref, cp.sender_ref, cp.full_name, cp.phone, cp.is_default, cp.updated_at,
                    COUNT(t.id) AS ttn_count
             FROM np_sender_contact_persons cp
             LEFT JOIN ttn_novaposhta t
                    ON t.sender_contact_person = cp.full_name COLLATE utf8mb4_0900_ai_ci
                   AND t.sender_ref COLLATE utf8mb4_0900_ai_ci = cp.sender_ref
                   AND t.deletion_mark = 0
             WHERE cp.sender_ref = '{$e}'
             GROUP BY cp.id, cp.Ref, cp.sender_ref, cp.full_name, cp.phone, cp.is_default, cp.updated_at
             ORDER BY cp.is_default DESC, ttn_count DESC, cp.full_name");
        return ($r['ok']) ? $r['rows'] : array();
    }

    public static function getDefaultContact($senderRef)
    {
        $e = \Database::escape('Papir', $senderRef);
        $r = \Database::fetchRow('Papir',
            "SELECT * FROM np_sender_contact_persons
             WHERE sender_ref = '{$e}' AND is_default = 1 LIMIT 1");
        if ($r['ok'] && $r['row']) return $r['row'];
        // Fallback: first contact
        $r2 = \Database::fetchRow('Papir',
            "SELECT * FROM np_sender_contact_persons
             WHERE sender_ref = '{$e}' ORDER BY id LIMIT 1");
        return ($r2['ok'] && $r2['row']) ? $r2['row'] : null;
    }

    public static function setDefaultContact($senderRef, $contactRef)
    {
        $es = \Database::escape('Papir', $senderRef);
        $ec = \Database::escape('Papir', $contactRef);
        \Database::query('Papir',
            "UPDATE np_sender_contact_persons SET is_default = 0 WHERE sender_ref = '{$es}'");
        \Database::query('Papir',
            "UPDATE np_sender_contact_persons SET is_default = 1
             WHERE sender_ref = '{$es}' AND Ref = '{$ec}'");
    }

    public static function setDefaultAddress($senderRef, $addressRef)
    {
        $es = \Database::escape('Papir', $senderRef);
        $ea = \Database::escape('Papir', $addressRef);
        \Database::query('Papir',
            "UPDATE np_sender_address SET is_default = 0 WHERE sender_ref = '{$es}'");
        \Database::query('Papir',
            "UPDATE np_sender_address SET is_default = 1
             WHERE sender_ref = '{$es}' AND Ref = '{$ea}'");
    }
}