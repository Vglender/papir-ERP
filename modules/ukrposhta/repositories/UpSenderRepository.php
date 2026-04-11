<?php
namespace Papir\Crm;

/**
 * Read-only access to sender_ukr — directory of Ukrposhta client (sender) entities.
 *
 * Schema:
 *   id, uuid, name, counterpartyRegcode, addressId, phoneNumber, bankAccount,
 *   accountType_type, accountType_assignmentDate, sender_city, sender_postcode
 *
 * In practice there is one business entity ("Гльондер ФОП") represented by
 * multiple uuid rows (each is a client record at Ukrposhta). The default
 * sender UUID is stored in integration_settings.default_sender_uuid.
 */
class UpSenderRepository
{
    const TABLE = 'sender_ukr';

    public static function getAll()
    {
        $r = \Database::fetchAll('Papir',
            "SELECT * FROM " . self::TABLE . " ORDER BY name, id");
        return $r['ok'] ? $r['rows'] : array();
    }

    public static function getByUuid($uuid)
    {
        $e = \Database::escape('Papir', (string)$uuid);
        $r = \Database::fetchRow('Papir',
            "SELECT * FROM " . self::TABLE . " WHERE uuid = '{$e}' LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    public static function getDefault()
    {
        $uuid = \IntegrationSettingsService::get('ukrposhta', 'default_sender_uuid', '');
        if ($uuid) {
            $row = self::getByUuid($uuid);
            if ($row) return $row;
        }
        // Fallback: most-used sender in ttn_ukrposhta
        $r = \Database::fetchRow('Papir',
            "SELECT s.* FROM " . self::TABLE . " s
             INNER JOIN (
                 SELECT sender_uuid, COUNT(*) AS c FROM ttn_ukrposhta
                 WHERE sender_uuid IS NOT NULL GROUP BY sender_uuid
                 ORDER BY c DESC LIMIT 1
             ) m ON m.sender_uuid = s.uuid
             LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }
}