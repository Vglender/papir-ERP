<?php
namespace Papir\Crm;

/**
 * CRUD for ttn_novaposhta table.
 */
class TtnRepository
{
    /**
     * Get list of TTNs with filters + pagination.
     * Returns ['rows'=>array, 'total'=>int]
     */
    public static function getList($filters = array(), $limit = 50, $offset = 0)
    {
        $where = array('1=1');

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $chipSep = (strpos($search, '|||') !== false) ? '/\s*\|\|\|\s*/u' : '/\s*,\s*/u';
            $chips   = preg_split($chipSep, $search);
            $chipConds = array();
            foreach ($chips as $chip) {
                $chip = trim($chip);
                if ($chip === '') continue;
                if (preg_match('/^\d{10,}$/', $chip)) {
                    // TTN number exact
                    $e = \Database::escape('Papir', $chip);
                    $chipConds[] = "t.int_doc_number = '{$e}'";
                } elseif (preg_match('/^\d+$/', $chip)) {
                    // order ID
                    $chipConds[] = "t.customerorder_id = " . (int)$chip;
                } else {
                    $tokens = preg_split('/\s+/u', mb_strtolower($chip, 'UTF-8'));
                    $tokens = array_filter($tokens, function($tok) { return $tok !== ''; });
                    $tParts = array();
                    foreach ($tokens as $tok) {
                        $e = \Database::escape('Papir', $tok);
                        $tParts[] = "(LOWER(COALESCE(t.int_doc_number,''))    LIKE '%{$e}%'
                                   OR LOWER(COALESCE(t.recipient_contact_person,'')) LIKE '%{$e}%'
                                   OR LOWER(COALESCE(t.recipients_phone,''))  LIKE '%{$e}%'
                                   OR LOWER(COALESCE(t.city_recipient_desc,'')) LIKE '%{$e}%'
                                   OR LOWER(COALESCE(t.state_name,''))        LIKE '%{$e}%')";
                    }
                    if ($tParts) {
                        $chipConds[] = '(' . implode(' AND ', $tParts) . ')';
                    }
                }
            }
            if ($chipConds) {
                $where[] = count($chipConds) === 1
                    ? $chipConds[0]
                    : '(' . implode(' OR ', $chipConds) . ')';
            }
        }

        // state_group: named filter group or comma-separated NP StatusCodes
        if (!empty($filters['state_group'])) {
            $groupMap = array(
                'transit'  => array(4, 5, 6),          // В дорозі (у місті відправника / у дорозі / у місті одержувача)
                'branch'   => array(7, 8, 105),         // На відділенні (прибуло / прийнято / прибуло у нове відд.)
                'received' => array(9),                 // Отримана
                'return'   => array(10, 11, 103),       // Повертається
                'refused'  => array(102, 106),          // Відмова
                'new'      => array(1),                 // Нова (ще не передана)
                'courier'  => array(101, 104),          // Кур'єрська доставка
            );
            $sg = $filters['state_group'];
            if (isset($groupMap[$sg])) {
                $codes = implode(',', array_map('intval', $groupMap[$sg]));
                $where[] = "t.state_define IN ({$codes})";
            } else {
                // Fallback: treat as raw comma-separated codes
                $rawCodes = array_map('intval', explode(',', $sg));
                $rawCodes = array_filter($rawCodes);
                if ($rawCodes) {
                    $where[] = "t.state_define IN (" . implode(',', $rawCodes) . ")";
                }
            }
        }

        if (!empty($filters['sender_ref'])) {
            $e = \Database::escape('Papir', $filters['sender_ref']);
            $where[] = "t.sender_ref = '{$e}'";
        }

        if (!empty($filters['date_from'])) {
            $e = \Database::escape('Papir', $filters['date_from']);
            $where[] = "DATE(t.moment) >= '{$e}'";
        }

        if (!empty($filters['date_to'])) {
            $e = \Database::escape('Papir', $filters['date_to']);
            $where[] = "DATE(t.moment) <= '{$e}'";
        }

        if (isset($filters['deletion_mark']) && $filters['deletion_mark'] !== '') {
            $where[] = 't.deletion_mark = ' . (int)$filters['deletion_mark'];
        } else {
            $where[] = 't.deletion_mark = 0';
        }

        $whereStr = implode(' AND ', $where);

        $rTotal = \Database::fetchRow('Papir',
            "SELECT COUNT(*) AS cnt FROM ttn_novaposhta t WHERE {$whereStr}");
        $total = ($rTotal['ok'] && $rTotal['row']) ? (int)$rTotal['row']['cnt'] : 0;

        $rRows = \Database::fetchAll('Papir',
            "SELECT t.id, t.ref, t.int_doc_number, t.customerorder_id, t.demand_id,
                    t.state_name, t.state_define,
                    t.moment, t.estimated_delivery_date,
                    t.city_recipient_desc, t.recipient_address_desc,
                    t.recipient_contact_person, t.recipients_phone,
                    t.backward_delivery_money, t.cost,
                    t.weight, t.seats_amount,
                    t.service_type, t.payment_method,
                    t.sender_ref,
                    s.Description AS sender_desc
             FROM ttn_novaposhta t
             LEFT JOIN np_sender s ON s.Ref = t.sender_ref
             WHERE {$whereStr}
             ORDER BY t.moment DESC, t.id DESC
             LIMIT " . (int)$limit . " OFFSET " . (int)$offset);

        $rows = ($rRows['ok']) ? $rRows['rows'] : array();
        return array('rows' => $rows, 'total' => $total);
    }

    public static function getById($id)
    {
        $r = \Database::fetchRow('Papir',
            "SELECT t.*, s.Description AS sender_desc, s.api AS sender_api,
                    s.CounterpartyFullName AS sender_full_name
             FROM ttn_novaposhta t
             LEFT JOIN np_sender s ON s.Ref = t.sender_ref
             WHERE t.id = " . (int)$id . " LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    public static function getByRef($ref)
    {
        $e = \Database::escape('Papir', $ref);
        $r = \Database::fetchRow('Papir',
            "SELECT * FROM ttn_novaposhta WHERE ref = '{$e}' LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    public static function getByIntDocNumber($num)
    {
        $e = \Database::escape('Papir', $num);
        $r = \Database::fetchRow('Papir',
            "SELECT * FROM ttn_novaposhta WHERE int_doc_number = '{$e}' LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    public static function getByOrder($orderId)
    {
        $r = \Database::fetchAll('Papir',
            "SELECT t.*, s.Description AS sender_desc
             FROM ttn_novaposhta t
             LEFT JOIN np_sender s ON s.Ref = t.sender_ref
             WHERE t.customerorder_id = " . (int)$orderId . "
               AND t.deletion_mark = 0
             ORDER BY t.moment DESC");
        return ($r['ok']) ? $r['rows'] : array();
    }

    /**
     * Insert or update a TTN record.
     * If $data contains 'id' — update, otherwise insert.
     */
    public static function save($data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        if (!empty($data['id'])) {
            $id = (int)$data['id'];
            unset($data['id']);
            return \Database::update('Papir', 'ttn_novaposhta', $data, array('id' => $id));
        }
        return \Database::insert('Papir', 'ttn_novaposhta', $data);
    }

    public static function updateStatus($id, $stateId, $stateName, $stateDefine,
                                         $estimatedDelivery = null, $dateFirstStorage = null,
                                         $arrived = null)
    {
        $upd = array(
            'state_id'                  => $stateId,
            'state_name'                => $stateName,
            'state_define'              => $stateDefine,
            'date_last_updated_status'  => date('Y-m-d H:i:s'),
            'updated_at'                => date('Y-m-d H:i:s'),
        );
        if ($estimatedDelivery !== null) $upd['estimated_delivery_date'] = $estimatedDelivery;
        if ($dateFirstStorage  !== null) $upd['date_first_day_storage']  = $dateFirstStorage;
        if ($arrived           !== null) $upd['arrived']                 = $arrived;
        return \Database::update('Papir', 'ttn_novaposhta', $upd, array('id' => (int)$id));
    }

    /**
     * Get active TTNs eligible for tracking (not delivered/returned).
     * state_define: 1=waiting, 3=in transit, 4=arrived, 5=at warehouse, 7=returning
     * Exclude 9=delivered, 10=returned (final states).
     */
    public static function getForTracking($limit = 100)
    {
        $r = \Database::fetchAll('Papir',
            "SELECT t.id, t.int_doc_number, t.sender_ref, s.api AS sender_api
             FROM ttn_novaposhta t
             LEFT JOIN np_sender s ON s.Ref = t.sender_ref
             WHERE t.deletion_mark = 0
               AND (t.state_define IS NULL OR t.state_define NOT IN (2, 3, 9, 10, 11, 106))
               AND t.int_doc_number IS NOT NULL
               AND t.int_doc_number NOT LIKE 'manual_%'
             ORDER BY t.date_last_updated_status ASC, t.id DESC
             LIMIT " . (int)$limit);
        return ($r['ok']) ? $r['rows'] : array();
    }

    public static function markDeleted($id)
    {
        return \Database::update('Papir', 'ttn_novaposhta',
            array('deletion_mark' => 1, 'updated_at' => date('Y-m-d H:i:s')),
            array('id' => (int)$id));
    }
}
