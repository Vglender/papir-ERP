<?php
namespace Papir\Crm;

/**
 * CRUD for shipment_groups (реєстри).
 *
 * Schema (real, uuid is PRIMARY KEY — no int id):
 *   uuid char(36) PRIMARY KEY
 *   name varchar(255)
 *   type enum('EXPRESS','STANDARD')
 *   clientUuid, counterpartyUuid char(36)
 *   counterpartyRegcode varchar(50)
 *   created datetime
 *   barcode_g_id varchar(50)
 *   byCourier tinyint(1) — create=0 (самоздача); 1 = замовлено виклик кур'єра
 *   closed tinyint(1) — 0=open, 1=closed (not editable on Ukrposhta anymore)
 *   printed tinyint(1)
 */
class UpGroupRepository
{
    const TABLE = 'shipment_groups';

    public static function getByUuid($uuid)
    {
        $e = \Database::escape('Papir', (string)$uuid);
        $r = \Database::fetchRow('Papir',
            "SELECT * FROM " . self::TABLE . " WHERE uuid = '{$e}' LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    /**
     * Insert or update by uuid.
     */
    public static function save(array $row)
    {
        if (empty($row['uuid'])) return false;
        $existing = self::getByUuid($row['uuid']);
        if ($existing) {
            $data = $row; unset($data['uuid']);
            $r = \Database::update('Papir', self::TABLE, $data, array('uuid' => $row['uuid']));
            return $r['ok'];
        }
        $r = \Database::insert('Papir', self::TABLE, $row);
        return $r['ok'];
    }

    public static function updateByUuid($uuid, array $data)
    {
        if (!$uuid) return false;
        $r = \Database::update('Papir', self::TABLE, $data, array('uuid' => (string)$uuid));
        return $r['ok'];
    }

    public static function deleteByUuid($uuid)
    {
        $r = \Database::delete('Papir', self::TABLE, array('uuid' => (string)$uuid));
        return $r['ok'];
    }

    /**
     * Find the last open group (not closed, not by courier) for given type/date.
     * Used by scan endpoint to auto-group scanned TTNs into the current registry.
     */
    public static function getLastOpen($type, $date = null)
    {
        $date = $date ?: date('Y-m-d');
        $eDate = \Database::escape('Papir', $date);
        $eType = \Database::escape('Papir', $type);
        $r = \Database::fetchRow('Papir',
            "SELECT * FROM " . self::TABLE . "
             WHERE DATE(created) = '{$eDate}'
               AND byCourier = 0 AND closed = 0
               AND type = '{$eType}'
             ORDER BY created DESC LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    /**
     * Paginated list with filters.
     * @return array ['rows' => [...with ttn_count, totals...], 'total' => int]
     */
    public static function getList($filters = array(), $limit = 50, $offset = 0)
    {
        $where = array('1=1');

        if (!empty($filters['search'])) {
            $e = \Database::escape('Papir', mb_strtolower($filters['search'], 'UTF-8'));
            $where[] = "(LOWER(COALESCE(g.name,'')) LIKE '%{$e}%'
                       OR g.uuid LIKE '%{$e}%'
                       OR g.barcode_g_id LIKE '%{$e}%')";
        }
        if (!empty($filters['client_uuid'])) {
            $e = \Database::escape('Papir', $filters['client_uuid']);
            $where[] = "g.clientUuid = '{$e}'";
        }
        if (!empty($filters['type'])) {
            $e = \Database::escape('Papir', $filters['type']);
            $where[] = "g.type = '{$e}'";
        }
        if (isset($filters['closed']) && $filters['closed'] !== '') {
            $where[] = "g.closed = " . ((int)$filters['closed'] ? 1 : 0);
        }
        if (!empty($filters['date_from'])) {
            $e = \Database::escape('Papir', $filters['date_from']);
            $where[] = "DATE(g.created) >= '{$e}'";
        }
        if (!empty($filters['date_to'])) {
            $e = \Database::escape('Papir', $filters['date_to']);
            $where[] = "DATE(g.created) <= '{$e}'";
        }

        $whereSql = implode(' AND ', $where);
        $lim = max(1, min(500, (int)$limit));
        $off = max(0, (int)$offset);

        $rowsRes = \Database::fetchAll('Papir',
            "SELECT g.*,
                    (SELECT COUNT(*) FROM shipment_group_links sgl
                     WHERE sgl.group_uuid = g.uuid) AS ttn_count,
                    (SELECT COALESCE(SUM(t.declaredPrice),0) FROM shipment_group_links sgl
                      JOIN ttn_ukrposhta t ON t.uuid = sgl.shipment_uuid
                     WHERE sgl.group_uuid = g.uuid) AS total_cost,
                    (SELECT COALESCE(SUM(t.postPayUah),0) FROM shipment_group_links sgl
                      JOIN ttn_ukrposhta t ON t.uuid = sgl.shipment_uuid
                     WHERE sgl.group_uuid = g.uuid) AS total_postpay
             FROM " . self::TABLE . " g
             WHERE {$whereSql}
             ORDER BY g.created DESC
             LIMIT {$lim} OFFSET {$off}");

        $cntRes = \Database::fetchRow('Papir',
            "SELECT COUNT(*) AS cnt FROM " . self::TABLE . " g WHERE {$whereSql}");

        return array(
            'rows'  => $rowsRes['ok'] ? $rowsRes['rows'] : array(),
            'total' => ($cntRes['ok'] && !empty($cntRes['row'])) ? (int)$cntRes['row']['cnt'] : 0,
        );
    }

    /**
     * Return TTNs attached to a group (joined from ttn_ukrposhta by uuid).
     */
    public static function getShipments($groupUuid)
    {
        $e = \Database::escape('Papir', (string)$groupUuid);
        $r = \Database::fetchAll('Papir',
            "SELECT t.*, sgl.created AS linked_at
             FROM shipment_group_links sgl
             INNER JOIN ttn_ukrposhta t ON t.uuid = sgl.shipment_uuid
             WHERE sgl.group_uuid = '{$e}'
             ORDER BY sgl.created ASC, t.id ASC");
        return $r['ok'] ? $r['rows'] : array();
    }

    public static function countShipments($groupUuid)
    {
        $e = \Database::escape('Papir', (string)$groupUuid);
        $r = \Database::fetchRow('Papir',
            "SELECT COUNT(*) AS cnt FROM shipment_group_links WHERE group_uuid = '{$e}'");
        return ($r['ok'] && !empty($r['row'])) ? (int)$r['row']['cnt'] : 0;
    }
}