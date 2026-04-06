<?php
/**
 * GET /demand/api/get_linked_docs?demand_id=X
 * Returns SVG graph data (nodes + edges) for the "Пов'язані документи" tab.
 * Central node: demand. Left col: customerorder. Right cols: TTN, payments.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../demand_bootstrap.php';

function dld_amt($v)
{
    if ($v === null || $v === '' || (float)$v == 0.0) return '';
    return number_format((float)$v, 2, '.', ' ') . ' ₴';
}

function dld_ttnStatus($t)
{
    if (!empty($t['deletion_mark'])) return 'deleted';
    $def = (int)$t['state_define'];
    if ($def === 9)                                        return 'delivered';
    if (in_array($def, array(7, 8, 105)))                  return 'at_branch';
    if (in_array($def, array(4, 5, 6, 41, 101, 104)))     return 'in_transit';
    if (in_array($def, array(10, 11, 103)))                return 'returned';
    if (in_array($def, array(102, 106)))                   return 'refused';
    if ($def === 2 || $def === 3)                          return 'deleted';
    if ($def === 1)                                        return 'draft';
    return 'created';
}

$demandId = isset($_GET['demand_id']) ? (int)$_GET['demand_id'] : 0;
if ($demandId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'demand_id required'));
    exit;
}

$rDemand = Database::fetchRow('Papir',
    "SELECT d.id, d.number, d.moment, d.status, d.sum_total, d.customerorder_id
     FROM demand d WHERE d.id = {$demandId} AND d.deleted_at IS NULL LIMIT 1");
if (!$rDemand['ok'] || empty($rDemand['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Demand not found'));
    exit;
}
$dem = $rDemand['row'];

$nodes = array();
$edges = array();

// ── central node: demand ──────────────────────────────────────────────────────
$nodes[] = array(
    'id'        => 'demand_' . $demandId,
    'type'      => 'demand',
    'label'     => $dem['number'] ?: ('#' . $demandId),
    'number'    => $dem['number'] ?: ('#' . $demandId),
    'sublabel'  => 'Відвантаження',
    'status'    => $dem['status'],
    'amount'    => dld_amt($dem['sum_total']),
    'moment'    => $dem['moment'],
    'entity_id' => $demandId,
    'url'       => '/demand/edit?id=' . $demandId,
    'col'       => 1,
    'current'   => true,
);

// ── left: linked customerorder ───────────────────────────────────────────────
if (!empty($dem['customerorder_id'])) {
    $orderId = (int)$dem['customerorder_id'];
    $rOrd = Database::fetchRow('Papir',
        "SELECT id, number, status, sum_total, moment FROM customerorder
         WHERE id = {$orderId} AND deleted_at IS NULL LIMIT 1");
    if ($rOrd['ok'] && !empty($rOrd['row'])) {
        $o = $rOrd['row'];
        $nid = 'co_' . $orderId;
        $nodes[] = array(
            'id'        => $nid,
            'type'      => 'customerorder',
            'label'     => $o['number'] ?: ('#' . $orderId),
            'number'    => $o['number'] ?: ('#' . $orderId),
            'sublabel'  => 'Замовлення',
            'status'    => $o['status'],
            'amount'    => dld_amt($o['sum_total']),
            'moment'    => $o['moment'],
            'entity_id' => $orderId,
            'url'       => '/customerorder/edit?id=' . $orderId,
            'col'       => 0,
        );
        $edges[] = array('from' => 'demand_' . $demandId, 'to' => $nid, 'type' => 'shipment');
    }
}

// ── right col 2: TTN Нова Пошта linked to this demand ─────────────────────────
$rTtns = Database::fetchAll('Papir',
    "SELECT id, int_doc_number, state_name, state_define, deletion_mark, created_at
     FROM ttn_novaposhta
     WHERE demand_id = {$demandId} AND (deletion_mark IS NULL OR deletion_mark = 0)
     ORDER BY id ASC
     LIMIT 12");
if ($rTtns['ok']) {
    foreach ($rTtns['rows'] as $t) {
        $nid = 'ttn_np_' . $t['id'];
        $nodes[] = array(
            'id'        => $nid,
            'type'      => 'ttn_np',
            'label'     => $t['int_doc_number'] ?: ('#' . $t['id']),
            'number'    => $t['int_doc_number'] ?: ('#' . $t['id']),
            'sublabel'  => mb_substr($t['state_name'] ?: '—', 0, 30, 'UTF-8'),
            'status'    => dld_ttnStatus($t),
            'moment'    => $t['created_at'],
            'entity_id' => (int)$t['id'],
            'url'       => '/novaposhta/ttns',
            'col'       => 2,
        );
        $edges[] = array('from' => $nid, 'to' => 'demand_' . $demandId, 'type' => 'ttn');
    }
}

// ── right col 2: payments linked via document_link ────────────────────────────
$rPLinks = Database::fetchAll('Papir',
    "SELECT from_type, from_id, from_ms_id, linked_sum
     FROM document_link
     WHERE to_type = 'demand' AND to_id = {$demandId} AND from_type IN ('paymentin','cashin')
     ORDER BY id ASC LIMIT 12");
if ($rPLinks['ok'] && !empty($rPLinks['rows'])) {
    $byType = array();
    foreach ($rPLinks['rows'] as $lnk) { $byType[$lnk['from_type']][] = $lnk; }

    foreach ($byType as $pt => $pRows) {
        $ids   = array();
        $msIds = array();
        foreach ($pRows as $r) {
            if ((int)$r['from_id'] > 0) $ids[] = (int)$r['from_id'];
            elseif (!empty($r['from_ms_id'])) $msIds[] = "'" . Database::escape('Papir', $r['from_ms_id']) . "'";
        }
        $parts = array();
        if (!empty($ids))   $parts[] = 'id IN (' . implode(',', $ids) . ')';
        if (!empty($msIds)) $parts[] = 'id_ms IN (' . implode(',', $msIds) . ')';
        if (empty($parts)) continue;
        $where = '(' . implode(' OR ', $parts) . ')';

        $tbl = ($pt === 'cashin') ? 'finance_cash' : 'finance_bank';
        $rPay = Database::fetchAll('Papir',
            "SELECT id, id_ms, doc_number, moment, sum FROM {$tbl} WHERE direction='in' AND {$where}");
        if (!$rPay['ok']) continue;

        $lsMap = array();
        foreach ($pRows as $pr) {
            $key = (int)$pr['from_id'] > 0 ? 'id:' . (int)$pr['from_id'] : 'ms:' . $pr['from_ms_id'];
            $lsMap[$key] = $pr['linked_sum'];
        }

        foreach ($rPay['rows'] as $c) {
            $k1 = 'id:' . (int)$c['id'];
            $k2 = 'ms:' . $c['id_ms'];
            $ls  = isset($lsMap[$k1]) ? $lsMap[$k1] : (isset($lsMap[$k2]) ? $lsMap[$k2] : null);
            $amt = ($ls !== null) ? (float)$ls : (float)$c['sum'];
            $nid = ($pt === 'cashin' ? 'cashin_' : 'paymentin_') . $c['id'];
            $docNum = $c['doc_number'] ?: ('#' . $c['id']);
            $nodes[] = array(
                'id'        => $nid,
                'type'      => $pt,
                'label'     => $docNum,
                'number'    => $docNum,
                'sublabel'  => dld_amt($amt),
                'status'    => 'posted',
                'amount'    => dld_amt($amt),
                'moment'    => $c['moment'],
                'entity_id' => (int)$c['id'],
                'url'       => ($pt === 'cashin')
                    ? '/finance/cash?search=' . (int)$c['id'] . '&direction=in'
                    : '/finance/bank?search=' . (int)$c['id'] . '&direction=in',
                'col'       => 2,
            );
            $edges[] = array('from' => $nid, 'to' => 'demand_' . $demandId, 'type' => 'payment');
        }
    }
}

echo json_encode(array('ok' => true, 'nodes' => $nodes, 'edges' => $edges));