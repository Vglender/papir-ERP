<?php
namespace Papir\Crm;

class CourierCallRepository
{
    public static function getList($filters = array(), $limit = 50, $offset = 0)
    {
        $where = array('1=1');

        if (!empty($filters['sender_ref'])) {
            $e = \Database::escape('Papir', $filters['sender_ref']);
            $where[] = "cc.sender_ref = '{$e}'";
        }
        if (!empty($filters['date_from'])) {
            $e = \Database::escape('Papir', $filters['date_from']);
            $where[] = "STR_TO_DATE(cc.preferred_delivery_date,'%d.%m.%Y') >= '{$e}'";
        }
        if (!empty($filters['date_to'])) {
            $e = \Database::escape('Papir', $filters['date_to']);
            $where[] = "STR_TO_DATE(cc.preferred_delivery_date,'%d.%m.%Y') <= '{$e}'";
        }

        $whereStr = implode(' AND ', $where);

        $rTotal = \Database::fetchRow('Papir',
            "SELECT COUNT(*) AS cnt FROM np_courier_calls cc WHERE {$whereStr}");
        $total = ($rTotal['ok'] && $rTotal['row']) ? (int)$rTotal['row']['cnt'] : 0;

        $rRows = \Database::fetchAll('Papir',
            "SELECT cc.*,
                    s.Description AS sender_desc,
                    a.Description AS address_desc,
                    a.CityDescription AS address_city
             FROM np_courier_calls cc
             LEFT JOIN np_sender s ON s.Ref = cc.sender_ref
             LEFT JOIN np_sender_address a ON a.Ref = cc.address_sender_ref
             WHERE {$whereStr}
             ORDER BY STR_TO_DATE(cc.preferred_delivery_date,'%d.%m.%Y') DESC, cc.id DESC
             LIMIT " . (int)$limit . " OFFSET " . (int)$offset);

        $rows = ($rRows['ok'] ? $rRows['rows'] : array());

        // Attach TTN stats
        if (!empty($rows)) {
            $callIds = array();
            foreach ($rows as $r2) { $callIds[] = (int)$r2['id']; }
            $stats = self::getCallStats($callIds);
            foreach ($rows as &$r2) {
                $st = isset($stats[(int)$r2['id']]) ? $stats[(int)$r2['id']] : array();
                $r2['ttn_count']    = isset($st['ttn_count'])    ? (int)$st['ttn_count']        : 0;
                $r2['total_weight'] = isset($st['total_weight'])  ? (float)$st['total_weight']   : null;
            }
            unset($r2);
        }

        return array(
            'rows'  => $rows,
            'total' => $total,
        );
    }

    public static function upsert($data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return \Database::upsertOne('Papir', 'np_courier_calls', $data, array('Barcode'));
    }

    public static function insert($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return \Database::insert('Papir', 'np_courier_calls', $data);
    }

    public static function delete($id)
    {
        return \Database::query('Papir',
            "DELETE FROM np_courier_calls WHERE id = " . (int)$id);
    }

    public static function getById($id)
    {
        $r = \Database::fetchRow('Papir',
            "SELECT cc.*, s.Description AS sender_desc,
                    a.Description AS address_desc, a.CityDescription AS address_city
             FROM np_courier_calls cc
             LEFT JOIN np_sender s ON s.Ref = cc.sender_ref
             LEFT JOIN np_sender_address a ON a.Ref = cc.address_sender_ref
             WHERE cc.id = " . (int)$id . " LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    // ── TTN linkage ───────────────────────────────────────────────────────────

    public static function getTtns($callId)
    {
        $r = \Database::fetchAll('Papir',
            "SELECT cct.*, t.state_name, t.cost, t.weight AS ttn_weight,
                    t.recipient, t.phone, t.city
             FROM np_courier_call_ttns cct
             LEFT JOIN ttn_novaposhta t ON t.id = cct.ttn_id
             WHERE cct.courier_call_id = " . (int)$callId . "
             ORDER BY cct.id");
        return ($r['ok']) ? $r['rows'] : array();
    }

    public static function upsertTtn($callId, $intDocNumber, $ttnId, $weight)
    {
        return \Database::upsertOne('Papir', 'np_courier_call_ttns', array(
            'courier_call_id' => (int)$callId,
            'ttn_id'          => $ttnId ? (int)$ttnId : null,
            'int_doc_number'  => $intDocNumber,
            'weight'          => $weight !== null ? (float)$weight : null,
        ), array('courier_call_id', 'int_doc_number'));
    }

    /** Link all existing local TTNs to the call by barcode list */
    public static function linkTtnsFromBarcodes($callId, array $barcodes)
    {
        if (empty($barcodes)) return;
        foreach ($barcodes as $barcode) {
            $barcode = trim($barcode);
            if (!$barcode) continue;
            $eb = \Database::escape('Papir', $barcode);
            // Try to find matching ttn_novaposhta record
            $rt = \Database::fetchRow('Papir',
                "SELECT id, weight FROM ttn_novaposhta WHERE int_doc_number = '{$eb}' LIMIT 1");
            $ttnId = ($rt['ok'] && $rt['row']) ? (int)$rt['row']['id'] : null;
            $weight = ($rt['ok'] && $rt['row']) ? $rt['row']['weight'] : null;
            self::upsertTtn($callId, $barcode, $ttnId, $weight);
        }
    }

    /** After a TTN is saved/updated locally, link it to any courier call that has its barcode */
    public static function refreshTtnLink($intDocNumber, $ttnId, $weight)
    {
        $e = \Database::escape('Papir', $intDocNumber);
        \Database::query('Papir',
            "UPDATE np_courier_call_ttns
             SET ttn_id = " . (int)$ttnId . ", weight = " . (float)$weight . "
             WHERE int_doc_number = '{$e}'");
    }

    /** Count TTNs and total weight for each call_id (for list aggregation) */
    public static function getCallStats(array $callIds)
    {
        if (empty($callIds)) return array();
        $ids = implode(',', array_map('intval', $callIds));
        $r = \Database::fetchAll('Papir',
            "SELECT courier_call_id,
                    COUNT(*) AS ttn_count,
                    SUM(weight) AS total_weight
             FROM np_courier_call_ttns
             WHERE courier_call_id IN ({$ids})
             GROUP BY courier_call_id");
        if (!$r['ok']) return array();
        $map = array();
        foreach ($r['rows'] as $row) {
            $map[(int)$row['courier_call_id']] = $row;
        }
        return $map;
    }
}