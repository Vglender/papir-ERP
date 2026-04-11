<?php
namespace Papir\Crm;

/**
 * CRUD for the real ttn_ukrposhta table (13k+ rows, camelCase legacy schema).
 *
 * Key columns used by the module:
 *   id, uuid, barcode, type (STANDARD|EXPRESS)
 *   sender_uuid, sender_name, senderAddressId, returnAddressId, sender_city, sender_phoneNumber
 *   recipient_uuid, recipient_name, recipient_phoneNumber, recipientAddressId, recipient_city, postcode
 *   deliveryType (W2W|W2D|D2W|D2D)
 *   weight, length, width, height   (varchar — in grams / cm, as stored by legacy script)
 *   declaredPrice, deliveryPrice, postPayUah
 *   description, label (PDF URL)
 *   lifecycle_status, lifecycle_statusDate, state_description, eventName
 *   customerorder_id, demand_id, id_order, id_demand, id_agent, id_owner
 *   created_date, lastModified
 *
 * Lifecycle status values (emitted by status-tracking API, see TrackingService):
 *   CREATED, REGISTERED, DELIVERING, IN_DEPARTMENT, DELIVERED,
 *   RETURNING, RETURNED, STORAGE, CANCELLED, FORWARDING, DELETED
 */
class UpTtnRepository
{
    const TABLE = 'ttn_ukrposhta';

    // Lifecycle grouping used by the UI status filter and tracking.
    // Ключі збігаються з канонічними статусами ShipmentStatus (transit → in_transit,
    // branch → at_branch тощо) — UI фільтр використовує ті самі.
    public static $LIFECYCLE_GROUPS = array(
        'transit'  => array('DELIVERING', 'FORWARDING'),
        'branch'   => array('IN_DEPARTMENT', 'STORAGE'),
        'received' => array('DELIVERED'),
        'return'   => array('RETURNING'),        // Повертається (в дорозі назад)
        'returned' => array('RETURNED'),         // Повернення отримано
        'draft'    => array('CREATED', 'REGISTERED', 'UNKNOWN'),
        'cancel'   => array('CANCELLED', 'DELETED'),
    );

    public static $FINAL_STATES = array('DELIVERED', 'RETURNED', 'STORAGE', 'CANCELLED', 'DELETED');

    public static function getById($id)
    {
        $r = \Database::fetchRow('Papir',
            "SELECT * FROM " . self::TABLE . " WHERE id = " . (int)$id . " LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    public static function getByUuid($uuid)
    {
        $e = \Database::escape('Papir', (string)$uuid);
        $r = \Database::fetchRow('Papir',
            "SELECT * FROM " . self::TABLE . " WHERE uuid = '{$e}' LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    public static function getByBarcode($barcode)
    {
        $e = \Database::escape('Papir', (string)$barcode);
        $r = \Database::fetchRow('Papir',
            "SELECT * FROM " . self::TABLE . " WHERE barcode = '{$e}' LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    public static function getByOrder($orderId)
    {
        $r = \Database::fetchAll('Papir',
            "SELECT * FROM " . self::TABLE . "
             WHERE customerorder_id = " . (int)$orderId . "
               AND (lifecycle_status IS NULL OR lifecycle_status NOT IN ('DELETED','CANCELLED'))
             ORDER BY id DESC");
        return ($r['ok']) ? $r['rows'] : array();
    }

    /**
     * Upsert by uuid.
     * @return int row id
     */
    public static function save(array $row)
    {
        if (empty($row['uuid'])) return 0;
        $existing = self::getByUuid($row['uuid']);
        if ($existing) {
            \Database::update('Papir', self::TABLE, $row, array('id' => (int)$existing['id']));
            return (int)$existing['id'];
        }
        $r = \Database::insert('Papir', self::TABLE, $row);
        return ($r['ok']) ? (int)$r['insert_id'] : 0;
    }

    public static function updateById($id, array $data)
    {
        if (!$id || empty($data)) return false;
        $r = \Database::update('Papir', self::TABLE, $data, array('id' => (int)$id));
        return $r['ok'];
    }

    public static function deleteById($id)
    {
        $r = \Database::delete('Papir', self::TABLE, array('id' => (int)$id));
        return $r['ok'];
    }

    public static function linkToOrder($id, $orderId, $demandId = null)
    {
        $data = array('customerorder_id' => (int)$orderId);
        if ($demandId !== null) $data['demand_id'] = (int)$demandId;
        return self::updateById($id, $data);
    }

    /**
     * Paginated, filterable list for the UI.
     *
     * Filters:
     *   search       string  (barcode, order id, phone, recipient name, city)
     *   sender_uuid  string
     *   state_group  string  (key of self::$LIFECYCLE_GROUPS)
     *   date_from, date_to   Y-m-d (on created_date)
     *   in_registry  '1'|'0' — has group link or not
     *   draft        bool    — только CREATED/REGISTERED
     *   with_order   '1'|'0' — has customerorder_id or not
     */
    public static function getList($filters = array(), $limit = 50, $offset = 0)
    {
        $where = array('1=1');
        $joins = '';

        if (!empty($filters['search'])) {
            $q = trim((string)$filters['search']);
            $chipSep = (strpos($q, '|||') !== false) ? '/\s*\|\|\|\s*/u' : '/\s*,\s*/u';
            $chips   = preg_split($chipSep, $q);
            $chipConds = array();
            foreach ($chips as $chip) {
                $chip = trim($chip);
                if ($chip === '') continue;
                if (preg_match('/^\d{12,}$/', $chip)) {
                    // Barcode exact (UP barcodes are 13 digits)
                    $e = \Database::escape('Papir', $chip);
                    $chipConds[] = "t.barcode = '{$e}'";
                } elseif (preg_match('/^\d{9,11}$/', $chip)) {
                    // Phone tail
                    $last9 = \Database::escape('Papir', substr($chip, -9));
                    $chipConds[] = "REPLACE(REPLACE(REPLACE(t.recipient_phoneNumber,' ',''),'-',''),'(','') LIKE '%{$last9}%'";
                } elseif (preg_match('/^\d+$/', $chip)) {
                    $e = \Database::escape('Papir', $chip);
                    $chipConds[] = "(t.customerorder_id = " . (int)$chip . "
                                    OR t.barcode LIKE '%{$e}%'
                                    OR REPLACE(REPLACE(t.recipient_phoneNumber,' ',''),'-','') LIKE '%{$e}%')";
                } else {
                    $tokens = preg_split('/\s+/u', mb_strtolower($chip, 'UTF-8'));
                    $parts  = array();
                    foreach ($tokens as $tok) {
                        if ($tok === '') continue;
                        $e = \Database::escape('Papir', $tok);
                        $parts[] = "(LOWER(COALESCE(t.barcode,''))         LIKE '%{$e}%'
                                   OR LOWER(COALESCE(t.recipient_name,'')) LIKE '%{$e}%'
                                   OR LOWER(COALESCE(t.recipient_city,'')) LIKE '%{$e}%'
                                   OR LOWER(COALESCE(t.eventName,''))      LIKE '%{$e}%'
                                   OR LOWER(COALESCE(t.state_description,'')) LIKE '%{$e}%')";
                    }
                    if ($parts) $chipConds[] = '(' . implode(' AND ', $parts) . ')';
                }
            }
            if ($chipConds) {
                $where[] = count($chipConds) === 1
                    ? $chipConds[0]
                    : '(' . implode(' OR ', $chipConds) . ')';
            }
        }

        if (!empty($filters['sender_uuid'])) {
            $e = \Database::escape('Papir', $filters['sender_uuid']);
            $where[] = "t.sender_uuid = '{$e}'";
        }

        if (!empty($filters['state_group'])) {
            $sg = $filters['state_group'];
            if (isset(self::$LIFECYCLE_GROUPS[$sg])) {
                $codes = array_map(function($c){ return "'" . addslashes($c) . "'"; }, self::$LIFECYCLE_GROUPS[$sg]);
                $where[] = "t.lifecycle_status IN (" . implode(',', $codes) . ")";
            } else {
                $e = \Database::escape('Papir', $sg);
                $where[] = "t.lifecycle_status = '{$e}'";
            }
        }

        if (!empty($filters['draft'])) {
            // Explicit draft filter — CREATED/REGISTERED only.
            $where[] = "(t.lifecycle_status IS NULL OR t.lifecycle_status IN ('CREATED','REGISTERED'))";
        }

        if (!empty($filters['date_from'])) {
            $e = \Database::escape('Papir', $filters['date_from']);
            $where[] = "DATE(COALESCE(t.created_date, t.lifecycle_statusDate)) >= '{$e}'";
        }
        if (!empty($filters['date_to'])) {
            $e = \Database::escape('Papir', $filters['date_to']);
            $where[] = "DATE(COALESCE(t.created_date, t.lifecycle_statusDate)) <= '{$e}'";
        }

        if (isset($filters['in_registry']) && $filters['in_registry'] !== '') {
            if ((string)$filters['in_registry'] === '1') {
                $joins .= " INNER JOIN shipment_group_links sgl ON sgl.shipment_uuid = t.uuid ";
            } else {
                $where[] = "NOT EXISTS (SELECT 1 FROM shipment_group_links sgl WHERE sgl.shipment_uuid = t.uuid)";
            }
        }

        if (isset($filters['with_order']) && $filters['with_order'] !== '') {
            $where[] = ((string)$filters['with_order'] === '1')
                ? "t.customerorder_id IS NOT NULL"
                : "t.customerorder_id IS NULL";
        }

        $whereSql = implode(' AND ', $where);
        $lim = max(1, min(500, (int)$limit));
        $off = max(0, (int)$offset);

        $rowsRes = \Database::fetchAll('Papir',
            "SELECT DISTINCT t.* FROM " . self::TABLE . " t
             {$joins}
             WHERE {$whereSql}
             ORDER BY COALESCE(t.created_date, t.lifecycle_statusDate) DESC, t.id DESC
             LIMIT {$lim} OFFSET {$off}");

        $cntRes = \Database::fetchRow('Papir',
            "SELECT COUNT(DISTINCT t.id) AS cnt FROM " . self::TABLE . " t
             {$joins}
             WHERE {$whereSql}");

        return array(
            'rows'  => $rowsRes['ok'] ? $rowsRes['rows'] : array(),
            'total' => ($cntRes['ok'] && !empty($cntRes['row'])) ? (int)$cntRes['row']['cnt'] : 0,
        );
    }

    /**
     * Rows eligible for tracking refresh.
     *
     * Що трекаємо:
     *   - Все, що вже має реальний lifecycle_status (DELIVERING, IN_DEPARTMENT, STORAGE,
     *     FORWARDING, RETURNING, UNKNOWN, REGISTERED) — активна фаза доставки.
     *   - CREATED-драфти, ТІЛЬКИ якщо вони вже в реєстрі (shipment_group_links) —
     *     тобто фізично передані перевізнику. Інакше UP повертає 404 і засмічує чергу.
     *
     * Не трекаємо:
     *   - Фінальні стани (DELIVERED, RETURNED, STORAGE, CANCELLED, DELETED).
     *   - Старіше 35 днів (крім UNKNOWN — для тих витримуємо пробіг, а потім бросаємо).
     *
     * Пріоритет сортування: non-draft > REGISTERED > CREATED-у-реєстрі, за стажем
     * lifecycle_statusDate ASC (найдавніші першими).
     */
    public static function getForTracking($limit = 100)
    {
        $final = array_map(function($c){ return "'" . addslashes($c) . "'"; }, self::$FINAL_STATES);
        $finalSql = implode(',', $final);
        $old90  = \Database::escape('Papir', date('Y-m-d H:i:s', strtotime('-90 days')));
        $old35  = \Database::escape('Papir', date('Y-m-d H:i:s', strtotime('-35 days')));
        $r = \Database::fetchAll('Papir',
            "SELECT t.*
             FROM " . self::TABLE . " t
             LEFT JOIN shipment_group_links sgl ON sgl.shipment_uuid = t.uuid
             WHERE t.barcode IS NOT NULL AND t.barcode <> ''
               AND (t.lifecycle_status IS NULL OR t.lifecycle_status NOT IN ({$finalSql}))
               AND (
                     -- активна фаза: посилка в русі/на відділенні/на складі
                     (t.lifecycle_status IN ('DELIVERING','FORWARDING','IN_DEPARTMENT','STORAGE','RETURNING','UNKNOWN')
                      AND COALESCE(t.lifecycle_statusDate, t.created_date) >= '{$old90}')
                     -- зареєстрована, чекає першого статусу
                     OR (t.lifecycle_status = 'REGISTERED'
                         AND COALESCE(t.lifecycle_statusDate, t.created_date) >= '{$old35}')
                     -- чернетка, але вже в реєстрі перевізника
                     OR (t.lifecycle_status = 'CREATED'
                         AND sgl.shipment_uuid IS NOT NULL
                         AND t.created_date >= '{$old35}')
               )
             ORDER BY
               CASE WHEN t.lifecycle_status IN ('DELIVERING','IN_DEPARTMENT','STORAGE','FORWARDING','RETURNING') THEN 0
                    WHEN t.lifecycle_status = 'REGISTERED' THEN 1
                    WHEN t.lifecycle_status = 'CREATED' THEN 2
                    ELSE 3 END ASC,
               COALESCE(t.lifecycle_statusDate, t.created_date) ASC,
               t.id ASC
             LIMIT " . (int)$limit);
        return ($r['ok']) ? $r['rows'] : array();
    }
}