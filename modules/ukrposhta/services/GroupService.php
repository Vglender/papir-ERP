<?php
namespace Papir\Crm;

/**
 * Ukrposhta shipment-groups (реєстри).
 *
 * Wraps Ukrposhta API endpoints for shipment-groups and keeps local state
 * in shipment_groups + shipment_group_links in sync.
 *
 * The link table is an explicit N:1 join (one TTN → one group); the helper
 * addToOrCreate() implements the "auto-register" flow used by the scan screen:
 *  - look up the currently-open registry of the right type for today
 *  - if absent, create a new one
 *  - attach the TTN to it on Ukrposhta + into the link table locally.
 */
class GroupService
{
    /**
     * Create a new registry on Ukrposhta and store it locally.
     *
     * @return array { ok, group? (row), error? }
     */
    public static function create($name = '', $type = 'STANDARD', $clientUuid = null)
    {
        $api = UkrposhtaApi::getDefault();
        if (!$api) return array('ok' => false, 'error' => 'API not configured');

        $clientUuid = $clientUuid ?: UpDefaults::clientUuid();
        if (!$clientUuid) return array('ok' => false, 'error' => 'No default client UUID');
        if (!$name) $name = date('Y-m-d') . ' / ' . date('H');

        $r = $api->createGroup($name, $clientUuid, strtoupper($type));
        if (!$r['ok']) return array('ok' => false, 'error' => $r['error']);

        $data = $r['data'];
        $uuid = isset($data['uuid']) ? $data['uuid'] : '';
        if (!$uuid) return array('ok' => false, 'error' => 'No uuid in response', 'raw' => $r['raw']);

        $row = array(
            'uuid'                => $uuid,
            'name'                => isset($data['name']) ? $data['name'] : $name,
            'type'                => isset($data['type']) ? $data['type'] : $type,
            'clientUuid'          => isset($data['clientUuid']) ? $data['clientUuid'] : $clientUuid,
            'counterpartyUuid'    => isset($data['counterpartyUuid']) ? $data['counterpartyUuid'] : '',
            'counterpartyRegcode' => isset($data['counterpartyRegcode']) ? $data['counterpartyRegcode'] : '',
            'created'             => isset($data['created'])
                                      ? date('Y-m-d H:i:s', is_numeric($data['created']) ? (int)$data['created'] : strtotime($data['created']))
                                      : date('Y-m-d H:i:s'),
            'barcode_g_id'        => isset($data['barcode_g_id']) ? $data['barcode_g_id'] : '',
            'byCourier'           => !empty($data['byCourier']) ? 1 : 0,
            'closed'              => !empty($data['closed']) ? 1 : 0,
            'printed'             => !empty($data['printed']) ? 1 : 0,
        );
        UpGroupRepository::save($row);

        return array('ok' => true, 'group' => UpGroupRepository::getByUuid($uuid));
    }

    /**
     * Attach a TTN to a specific group.
     */
    public static function addShipment($groupUuid, $shipmentUuid)
    {
        if (!$groupUuid || !$shipmentUuid) return array('ok' => false, 'error' => 'group_uuid and shipment_uuid required');

        $api = UkrposhtaApi::getDefault();
        if (!$api) return array('ok' => false, 'error' => 'API not configured');

        $r = $api->addShipmentToGroup($groupUuid, $shipmentUuid);
        // UP returns 200 with `message` containing "is assigned to group" on success,
        // or an error if already attached/closed.
        $alreadyOk = false;
        if (!$r['ok']) {
            $msg = isset($r['data']['message']) ? (string)$r['data']['message'] : '';
            if (stripos($msg, 'already') !== false) $alreadyOk = true;
            if (!$alreadyOk) return array('ok' => false, 'error' => $r['error']);
        }

        UpGroupLinkRepository::link($groupUuid, $shipmentUuid);
        return array('ok' => true, 'count' => UpGroupRepository::countShipments($groupUuid));
    }

    /**
     * Remove TTN from its group.
     * UP API: DELETE /shipments/{shipmentUuid}/shipment-group
     */
    public static function removeShipment($shipmentUuid)
    {
        if (!$shipmentUuid) return array('ok' => false, 'error' => 'shipment_uuid required');

        $api = UkrposhtaApi::getDefault();
        if (!$api) return array('ok' => false, 'error' => 'API not configured');

        $r = $api->removeShipmentFromGroup($shipmentUuid);
        if (!$r['ok'] && $r['http'] != 404) {
            return array('ok' => false, 'error' => $r['error']);
        }
        UpGroupLinkRepository::unlinkShipment($shipmentUuid);
        return array('ok' => true);
    }

    /**
     * Add TTN to a group, auto-creating one if needed. Mirrors the behaviour
     * of /var/sqript/UP ClientController::addToOrCreateGroup().
     *
     * @param string $barcode    TTN barcode
     * @return array { ok, group, ttn, error? }
     */
    public static function addToOrCreate($barcode)
    {
        $ttn = UpTtnRepository::getByBarcode($barcode);
        if (!$ttn)          return array('ok' => false, 'error' => 'TTN not found in local DB');
        if (!$ttn['uuid'])  return array('ok' => false, 'error' => 'TTN has no uuid');
        if (in_array($ttn['lifecycle_status'], UpTtnRepository::$FINAL_STATES, true)) {
            return array('ok' => false, 'error' => 'TTN has final status (' . $ttn['lifecycle_status'] . ')');
        }

        $type  = $ttn['type'] ?: UpDefaults::shipmentType();
        $group = UpGroupRepository::getLastOpen($type);
        if (!$group) {
            $created = self::create('', $type);
            if (!$created['ok']) return $created;
            $group = $created['group'];
        }
        if (!$group) return array('ok' => false, 'error' => 'Cannot acquire registry');

        $add = self::addShipment($group['uuid'], $ttn['uuid']);
        if (!$add['ok']) return $add;

        return array(
            'ok'    => true,
            'group' => UpGroupRepository::getByUuid($group['uuid']),
            'ttn'   => $ttn,
        );
    }

    /**
     * Close a registry locally. Ukrposhta does not expose a "close" endpoint —
     * the operator marks it closed in the UI meaning "don't append more TTNs".
     * We just flip the local flag.
     */
    public static function close($groupUuid)
    {
        if (!$groupUuid) return array('ok' => false, 'error' => 'group_uuid required');
        $g = UpGroupRepository::getByUuid($groupUuid);
        if (!$g) return array('ok' => false, 'error' => 'Group not found');
        UpGroupRepository::updateByUuid($groupUuid, array('closed' => 1));
        return array('ok' => true);
    }

    public static function reopen($groupUuid)
    {
        if (!$groupUuid) return array('ok' => false, 'error' => 'group_uuid required');
        UpGroupRepository::updateByUuid($groupUuid, array('closed' => 0));
        return array('ok' => true);
    }

    /**
     * Delete registry locally (and detach all links). Ukrposhta has no DELETE
     * for shipment-groups either — we just remove our local record.
     */
    public static function delete($groupUuid)
    {
        if (!$groupUuid) return array('ok' => false, 'error' => 'group_uuid required');
        UpGroupLinkRepository::deleteByGroup($groupUuid);
        UpGroupRepository::deleteByUuid($groupUuid);
        return array('ok' => true);
    }

    /**
     * Sync groups (and their shipments) from Ukrposhta → local DB.
     * Returns { ok, synced_groups, synced_links }.
     */
    public static function syncFromApi($clientUuid = null)
    {
        $api = UkrposhtaApi::getDefault();
        if (!$api) return array('ok' => false, 'error' => 'API not configured');

        $clientUuid = $clientUuid ?: UpDefaults::clientUuid();
        $r = $api->getGroupsByClient($clientUuid);
        if (!$r['ok']) return array('ok' => false, 'error' => $r['error']);

        $syncedGroups = 0;
        $syncedLinks  = 0;

        $groups = is_array($r['data']) ? $r['data'] : array();
        // API may return array of groups directly OR { groups: [...] } — handle both.
        if (isset($groups['uuid'])) $groups = array($groups);

        foreach ($groups as $g) {
            if (empty($g['uuid'])) continue;
            $row = array(
                'uuid'                => $g['uuid'],
                'name'                => isset($g['name']) ? $g['name'] : '',
                'type'                => isset($g['type']) ? $g['type'] : 'STANDARD',
                'clientUuid'          => isset($g['clientUuid']) ? $g['clientUuid'] : $clientUuid,
                'counterpartyUuid'    => isset($g['counterpartyUuid']) ? $g['counterpartyUuid'] : '',
                'counterpartyRegcode' => isset($g['counterpartyRegcode']) ? $g['counterpartyRegcode'] : '',
                'created'             => isset($g['created'])
                                          ? date('Y-m-d H:i:s', is_numeric($g['created']) ? (int)$g['created'] : strtotime($g['created']))
                                          : null,
                'barcode_g_id'        => isset($g['barcode_g_id']) ? $g['barcode_g_id'] : '',
                'byCourier'           => !empty($g['byCourier']) ? 1 : 0,
                'closed'              => !empty($g['closed'])    ? 1 : 0,
                'printed'             => !empty($g['printed'])   ? 1 : 0,
            );
            if (UpGroupRepository::save($row)) $syncedGroups++;

            // Fetch and sync shipments in group
            $rs = $api->getGroupShipments($g['uuid']);
            if ($rs['ok'] && is_array($rs['data'])) {
                foreach ($rs['data'] as $s) {
                    if (empty($s['uuid'])) continue;
                    if (UpGroupLinkRepository::link($g['uuid'], $s['uuid'])) $syncedLinks++;
                }
            }
        }
        return array('ok' => true, 'synced_groups' => $syncedGroups, 'synced_links' => $syncedLinks);
    }

    /**
     * Download form-103a PDF for a group (ТТН-реєстр для передачі у відділення).
     */
    public static function downloadForm103a($groupUuid)
    {
        $api = UkrposhtaApi::getDefault();
        if (!$api) return array('ok' => false, 'error' => 'API not configured');
        return $api->getGroupForm103a($groupUuid);
    }
}