<?php
namespace Papir\Crm;

/**
 * np_scan_sheets CRUD
 */
class ScanSheetRepository
{
    public static function getList($filters = array(), $limit = 50, $offset = 0)
    {
        $where = array('1=1');

        if (!empty($filters['sender_ref'])) {
            $e = \Database::escape('Papir', $filters['sender_ref']);
            $where[] = "ss.sender_ref = '{$e}'";
        }

        if (!empty($filters['status'])) {
            $e = \Database::escape('Papir', $filters['status']);
            $where[] = "ss.status = '{$e}'";
        }

        if (!empty($filters['date_from'])) {
            $e = \Database::escape('Papir', $filters['date_from']);
            $where[] = "DATE(ss.DateTime) >= '{$e}'";
        }

        if (!empty($filters['date_to'])) {
            $e = \Database::escape('Papir', $filters['date_to']);
            $where[] = "DATE(ss.DateTime) <= '{$e}'";
        }

        $whereStr = implode(' AND ', $where);

        $rTotal = \Database::fetchRow('Papir',
            "SELECT COUNT(*) AS cnt FROM np_scan_sheets ss WHERE {$whereStr}");
        $total = ($rTotal['ok'] && $rTotal['row']) ? (int)$rTotal['row']['cnt'] : 0;

        $rRows = \Database::fetchAll('Papir',
            "SELECT ss.*, s.Description AS sender_desc,
                    agg.total_cost, agg.total_redelivery, agg.total_seats
             FROM np_scan_sheets ss
             LEFT JOIN np_sender s ON s.Ref = ss.sender_ref
             LEFT JOIN (
                 SELECT scan_sheet_ref,
                        SUM(cost) AS total_cost,
                        SUM(COALESCE(afterpayment_on_goods_cost, backward_delivery_money)) AS total_redelivery,
                        SUM(seats_amount) AS total_seats
                 FROM ttn_novaposhta
                 WHERE deletion_mark = 0 AND scan_sheet_ref IS NOT NULL
                 GROUP BY scan_sheet_ref
             ) agg ON agg.scan_sheet_ref = ss.Ref
             WHERE {$whereStr}
             ORDER BY ss.DateTime DESC, ss.id DESC
             LIMIT " . (int)$limit . " OFFSET " . (int)$offset);

        return array('rows' => ($rRows['ok'] ? $rRows['rows'] : array()), 'total' => $total);
    }

    public static function getByRef($ref)
    {
        $e = \Database::escape('Papir', $ref);
        $r = \Database::fetchRow('Papir',
            "SELECT ss.*, s.Description AS sender_desc
             FROM np_scan_sheets ss
             LEFT JOIN np_sender s ON s.Ref = ss.sender_ref
             WHERE ss.Ref = '{$e}' LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    public static function save($data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        if (!empty($data['id'])) {
            $id = (int)$data['id'];
            unset($data['id']);
            return \Database::update('Papir', 'np_scan_sheets', $data, array('id' => $id));
        }
        return \Database::upsertOne('Papir', 'np_scan_sheets', $data, array('Ref'));
    }

    public static function delete($ref)
    {
        $e = \Database::escape('Papir', $ref);
        return \Database::query('Papir',
            "DELETE FROM np_scan_sheets WHERE Ref = '{$e}'");
    }
}
