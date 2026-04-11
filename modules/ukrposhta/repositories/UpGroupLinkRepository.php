<?php
namespace Papir\Crm;

/**
 * CRUD for shipment_group_links.
 *
 * Schema:
 *   id INT PK
 *   group_uuid char(36)
 *   shipment_uuid char(36)
 *   created timestamp
 *   UNIQUE(group_uuid, shipment_uuid)
 *
 * One TTN may be linked to at most one group at a time (the Ukrposhta API
 * rejects re-linking), but historically some shipments still have multiple
 * rows — the active one is the latest by `created`.
 */
class UpGroupLinkRepository
{
    const TABLE = 'shipment_group_links';

    public static function link($groupUuid, $shipmentUuid)
    {
        if (!$groupUuid || !$shipmentUuid) return false;
        // Detach from any existing group first — UP API allows one group per TTN.
        self::unlinkShipment($shipmentUuid);
        $r = \Database::insert('Papir', self::TABLE, array(
            'group_uuid'    => (string)$groupUuid,
            'shipment_uuid' => (string)$shipmentUuid,
        ));
        return $r['ok'];
    }

    public static function unlink($groupUuid, $shipmentUuid)
    {
        if (!$groupUuid || !$shipmentUuid) return false;
        $r = \Database::delete('Papir', self::TABLE, array(
            'group_uuid'    => (string)$groupUuid,
            'shipment_uuid' => (string)$shipmentUuid,
        ));
        return $r['ok'];
    }

    public static function unlinkShipment($shipmentUuid)
    {
        if (!$shipmentUuid) return false;
        $r = \Database::delete('Papir', self::TABLE, array('shipment_uuid' => (string)$shipmentUuid));
        return $r['ok'];
    }

    public static function deleteByGroup($groupUuid)
    {
        if (!$groupUuid) return false;
        $r = \Database::delete('Papir', self::TABLE, array('group_uuid' => (string)$groupUuid));
        return $r['ok'];
    }

    public static function getGroupUuid($shipmentUuid)
    {
        if (!$shipmentUuid) return null;
        $e = \Database::escape('Papir', (string)$shipmentUuid);
        $r = \Database::fetchRow('Papir',
            "SELECT group_uuid FROM " . self::TABLE . "
             WHERE shipment_uuid = '{$e}'
             ORDER BY created DESC, id DESC LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row']['group_uuid'] : null;
    }

    public static function listByGroup($groupUuid)
    {
        $e = \Database::escape('Papir', (string)$groupUuid);
        $r = \Database::fetchAll('Papir',
            "SELECT * FROM " . self::TABLE . "
             WHERE group_uuid = '{$e}'
             ORDER BY created ASC, id ASC");
        return $r['ok'] ? $r['rows'] : array();
    }
}