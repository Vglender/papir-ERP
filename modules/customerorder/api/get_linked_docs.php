<?php
/**
 * GET /customerorder/api/get_linked_docs?order_id=X
 * Returns graph data (nodes + edges) for the "Пов'язані документи" tab.
 * Covers 2 levels: docs → order, and docs → demands that belong to the order.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../customerorder_bootstrap.php';

// ── helpers ──────────────────────────────────────────────────────────────────

function ld_statusLabel($type, $status) {
    static $maps = array(
        'order'  => array('draft'=>'Чернетка','new'=>'Нове','confirmed'=>'Підтверджено',
            'in_progress'=>'В роботі','waiting_payment'=>'Очік. оплату','paid'=>'Оплачено',
            'partially_shipped'=>'Частк. відвантаж.','shipped'=>'Відвантажено',
            'completed'=>'Виконано','cancelled'=>'Скасовано'),
        'demand' => array('new'=>'Нове','assembling'=>'Збирається','assembled'=>'Зібрано',
            'shipped'=>'Відвантажено','arrived'=>'Отримано',
            'transfer'=>'Передача','cancelled'=>'Скасовано'),
    );
    return isset($maps[$type][$status]) ? $maps[$type][$status] : (string)$status;
}

function ld_ttnNpStatus($t) {
    if (!empty($t['deletion_mark'])) return 'deleted';
    $def = (int)$t['state_define'];
    if ($def === 9)                     return 'delivered';
    if (in_array($def, array(102,105))) return 'returned';
    if ($def >= 1)                      return 'in_transit';
    return 'created';
}

function ld_amt($v) {
    if ($v === null || $v === '' || (float)$v == 0.0) return '';
    return number_format((float)$v, 2, '.', ' ') . ' ₴';
}

/**
 * Build WHERE clause to match rows by from_id (numeric) or from_ms_id (UUID).
 * Returns SQL string like "(id IN (1,2) OR id_ms IN ('aaa','bbb'))" or "" if no valid IDs.
 *
 * @param array  $linkRows   document_link rows
 * @param string $idMsField  column name for UUID in target table (e.g. 'id_ms')
 */
function ld_whereIds($linkRows, $idMsField) {
    $ids   = array();
    $msIds = array();
    foreach ($linkRows as $r) {
        if ((int)$r['from_id'] > 0)      $ids[]   = (int)$r['from_id'];
        elseif (!empty($r['from_ms_id'])) $msIds[] = "'" . \Database::escape('Papir', $r['from_ms_id']) . "'";
    }
    $parts = array();
    if (!empty($ids))   $parts[] = 'id IN (' . implode(',', $ids) . ')';
    if (!empty($msIds)) $parts[] = $idMsField . ' IN (' . implode(',', $msIds) . ')';
    return empty($parts) ? '' : '(' . implode(' OR ', $parts) . ')';
}

// ── input validation ──────────────────────────────────────────────────────────

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($orderId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'order_id required'));
    exit;
}

$rOrder = \Database::fetchRow('Papir',
    "SELECT id, number, status, sum_total, moment FROM customerorder WHERE id={$orderId} AND deleted_at IS NULL LIMIT 1");
if (!$rOrder['ok'] || empty($rOrder['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Order not found'));
    exit;
}
$ord = $rOrder['row'];

$nodes = array();
$edges = array();

// ── central node ──────────────────────────────────────────────────────────────

$nodes[] = array(
    'id'        => 'co_' . $orderId,
    'type'      => 'customerorder',
    'label'     => $ord['number'] ?: ('#' . $orderId),
    'number'    => $ord['number'] ?: ('#' . $orderId),
    'sublabel'  => ld_statusLabel('order', $ord['status']),
    'status'    => $ord['status'],
    'amount'    => ld_amt($ord['sum_total']),
    'moment'    => $ord['moment'],
    'entity_id' => $orderId,
    'url'       => '/customerorder/edit?id=' . $orderId,
    'col'       => 1,
    'current'   => true,
);

// ── level-1: all docs directly linked to this order ───────────────────────────

$rLinks = \Database::fetchAll('Papir',
    "SELECT from_type, from_id, from_ms_id, link_type, linked_sum
     FROM document_link
     WHERE to_type='customerorder' AND to_id={$orderId}
     ORDER BY from_type, id ASC");
$links = ($rLinks['ok']) ? $rLinks['rows'] : array();

$byType = array();
foreach ($links as $lnk) { $byType[$lnk['from_type']][] = $lnk; }

$demandEntityIds = array();
$CAP = 12;

// ── demand ────────────────────────────────────────────────────────────────────
if (!empty($byType['demand'])) {
    $limited  = array_slice($byType['demand'], 0, $CAP);
    $overflow = count($byType['demand']) - count($limited);
    $where = ld_whereIds($limited, 'id_ms');
    if ($where) {
        $rD = \Database::fetchAll('Papir',
            "SELECT id, number, moment, status, sum_total FROM demand WHERE {$where} ORDER BY moment ASC");
        if ($rD['ok']) {
            foreach ($rD['rows'] as $d) {
                $demandEntityIds[] = (int)$d['id'];
                $nid = 'demand_' . $d['id'];
                $nodes[] = array(
                    'id'        => $nid,
                    'type'      => 'demand',
                    'label'     => $d['number'] ?: ('#'.$d['id']),
                    'number'    => $d['number'] ?: ('#'.$d['id']),
                    'sublabel'  => ld_statusLabel('demand', $d['status']),
                    'status'    => $d['status'],
                    'amount'    => ld_amt($d['sum_total']),
                    'moment'    => $d['moment'],
                    'entity_id' => (int)$d['id'],
                    'url'       => '/demand/edit?id=' . $d['id'],
                    'col'       => 2,
                );
                $edges[] = array('from' => $nid, 'to' => 'co_'.$orderId, 'type' => 'shipment');
            }
        }
    }
    if ($overflow > 0) {
        $nodes[] = array('id'=>'ovf_demand','type'=>'overflow','label'=>'+' . $overflow . ' відвантаж.','col'=>2,'url'=>null);
        $edges[] = array('from'=>'ovf_demand','to'=>'co_'.$orderId,'type'=>'shipment');
    }
}

// ── ttn_np (always has numeric from_id) ───────────────────────────────────────
if (!empty($byType['ttn_np'])) {
    $limited  = array_slice($byType['ttn_np'], 0, $CAP);
    $overflow = count($byType['ttn_np']) - count($limited);
    $ids = array_filter(array_map(function($r){ return (int)$r['from_id']; }, $limited));
    if (!empty($ids)) {
        $rT = \Database::fetchAll('Papir',
            "SELECT id, int_doc_number, state_name, state_define, deletion_mark, created_at
             FROM ttn_novaposhta WHERE id IN (" . implode(',', $ids) . ")");
        if ($rT['ok']) {
            foreach ($rT['rows'] as $t) {
                $nid = 'ttn_np_' . $t['id'];
                $nodes[] = array(
                    'id'        => $nid,
                    'type'      => 'ttn_np',
                    'label'     => $t['int_doc_number'] ?: ('#'.$t['id']),
                    'number'    => $t['int_doc_number'] ?: ('#'.$t['id']),
                    'sublabel'  => mb_substr($t['state_name'] ?: '—', 0, 30, 'UTF-8'),
                    'status'    => ld_ttnNpStatus($t),
                    'moment'    => $t['created_at'],
                    'entity_id' => (int)$t['id'],
                    'url'       => '/novaposhta/ttns',
                    'col'       => 3,
                );
                $edges[] = array('from'=>$nid,'to'=>'co_'.$orderId,'type'=>'ttn');
            }
        }
    }
    if ($overflow > 0) {
        $nodes[] = array('id'=>'ovf_ttn_np','type'=>'overflow','label'=>'+' . $overflow . ' ТТН НП','col'=>3,'url'=>'/novaposhta/ttns');
        $edges[] = array('from'=>'ovf_ttn_np','to'=>'co_'.$orderId,'type'=>'ttn');
    }
}

// ── ttn_up (always has numeric from_id) ───────────────────────────────────────
if (!empty($byType['ttn_up'])) {
    $limited  = array_slice($byType['ttn_up'], 0, $CAP);
    $overflow = count($byType['ttn_up']) - count($limited);
    $ids = array_filter(array_map(function($r){ return (int)$r['from_id']; }, $limited));
    if (!empty($ids)) {
        $rT = \Database::fetchAll('Papir',
            "SELECT id, barcode, lifecycle_status FROM ttn_ukrposhta WHERE id IN (" . implode(',', $ids) . ")");
        if ($rT['ok']) {
            foreach ($rT['rows'] as $t) {
                $nid = 'ttn_up_' . $t['id'];
                $nodes[] = array(
                    'id'        => $nid,
                    'type'      => 'ttn_up',
                    'label'     => 'УП ' . ($t['barcode'] ?: ('#'.$t['id'])),
                    'sublabel'  => $t['lifecycle_status'] ?: '—',
                    'status'    => strtolower($t['lifecycle_status'] ?: ''),
                    'entity_id' => (int)$t['id'],
                    'url'       => null,
                    'col'       => 3,
                );
                $edges[] = array('from'=>$nid,'to'=>'co_'.$orderId,'type'=>'ttn');
            }
        }
    }
    if ($overflow > 0) {
        $nodes[] = array('id'=>'ovf_ttn_up','type'=>'overflow','label'=>'+' . $overflow . ' ТТН УП','col'=>3,'url'=>null);
        $edges[] = array('from'=>'ovf_ttn_up','to'=>'co_'.$orderId,'type'=>'ttn');
    }
}

// ── cashin (finance_cash direction=in) ────────────────────────────────────────
if (!empty($byType['cashin'])) {
    $limited  = array_slice($byType['cashin'], 0, $CAP);
    $where = ld_whereIds($limited, 'id_ms');
    if ($where) {
        $lsMap = array();
        foreach ($limited as $lr) {
            $key = (int)$lr['from_id'] > 0 ? 'id:' . (int)$lr['from_id'] : 'ms:' . $lr['from_ms_id'];
            $lsMap[$key] = $lr['linked_sum'];
        }
        $rC = \Database::fetchAll('Papir',
            "SELECT id, id_ms, doc_number, moment, sum FROM finance_cash WHERE direction='in' AND {$where}");
        if ($rC['ok']) {
            foreach ($rC['rows'] as $c) {
                $key = 'id:' . (int)$c['id'];
                $key2 = 'ms:' . $c['id_ms'];
                $ls = isset($lsMap[$key]) ? $lsMap[$key] : (isset($lsMap[$key2]) ? $lsMap[$key2] : null);
                $amt = ($ls !== null) ? (float)$ls : (float)$c['sum'];
                $nid = 'cashin_' . $c['id'];
                $nodes[] = array(
                    'id'        => $nid,
                    'type'      => 'cashin',
                    'label'     => $c['doc_number'] ?: ('#'.$c['id']),
                    'number'    => $c['doc_number'] ?: ('#'.$c['id']),
                    'sublabel'  => ld_amt($amt),
                    'status'    => 'posted',
                    'amount'    => ld_amt($amt),
                    'moment'    => $c['moment'],
                    'entity_id' => (int)$c['id'],
                    'url'       => '/finance/cash?search=' . (int)$c['id'] . '&direction=in',
                    'col'       => 0,
                );
                $edges[] = array('from'=>$nid,'to'=>'co_'.$orderId,'type'=>'payment');
            }
        }
    }
}

// ── paymentin (finance_bank direction=in) ─────────────────────────────────────
if (!empty($byType['paymentin'])) {
    $limited  = array_slice($byType['paymentin'], 0, $CAP);
    $where = ld_whereIds($limited, 'id_ms');
    if ($where) {
        $lsMap = array();
        foreach ($limited as $lr) {
            $key = (int)$lr['from_id'] > 0 ? 'id:' . (int)$lr['from_id'] : 'ms:' . $lr['from_ms_id'];
            $lsMap[$key] = $lr['linked_sum'];
        }
        $rB = \Database::fetchAll('Papir',
            "SELECT id, id_ms, doc_number, moment, sum FROM finance_bank WHERE direction='in' AND {$where}");
        if ($rB['ok']) {
            foreach ($rB['rows'] as $c) {
                $key = 'id:' . (int)$c['id'];
                $key2 = 'ms:' . $c['id_ms'];
                $ls = isset($lsMap[$key]) ? $lsMap[$key] : (isset($lsMap[$key2]) ? $lsMap[$key2] : null);
                $amt = ($ls !== null) ? (float)$ls : (float)$c['sum'];
                $nid = 'paymentin_' . $c['id'];
                $nodes[] = array(
                    'id'        => $nid,
                    'type'      => 'paymentin',
                    'label'     => $c['doc_number'] ?: ('#'.$c['id']),
                    'number'    => $c['doc_number'] ?: ('#'.$c['id']),
                    'sublabel'  => ld_amt($amt),
                    'status'    => 'posted',
                    'amount'    => ld_amt($amt),
                    'moment'    => $c['moment'],
                    'entity_id' => (int)$c['id'],
                    'url'       => '/finance/bank?search=' . (int)$c['id'] . '&direction=in',
                    'col'       => 0,
                );
                $edges[] = array('from'=>$nid,'to'=>'co_'.$orderId,'type'=>'payment');
            }
        }
    }
}

// ── salesreturn (direct to order via doc link) ────────────────────────────────
if (!empty($byType['salesreturn'])) {
    $limited  = array_slice($byType['salesreturn'], 0, $CAP);
    $where = ld_whereIds($limited, 'id_ms');
    if ($where) {
        $rS = \Database::fetchAll('Papir',
            "SELECT id, number, moment, sum_total FROM salesreturn WHERE {$where}");
        if ($rS['ok']) {
            foreach ($rS['rows'] as $s) {
                $nid = 'salesreturn_' . $s['id'];
                $nodes[] = array(
                    'id'        => $nid,
                    'type'      => 'salesreturn',
                    'label'     => $s['number'] ?: ('#'.$s['id']),
                    'number'    => $s['number'] ?: ('#'.$s['id']),
                    'sublabel'  => ld_amt($s['sum_total']),
                    'status'    => 'posted',
                    'amount'    => ld_amt($s['sum_total']),
                    'moment'    => $s['moment'],
                    'entity_id' => (int)$s['id'],
                    'url'       => null,
                    'col'       => 4,
                );
                $edges[] = array('from'=>$nid,'to'=>'co_'.$orderId,'type'=>'return');
            }
        }
    }
}

// ── level-2: docs linked to the demands we found ─────────────────────────────

if (!empty($demandEntityIds)) {
    $demandEntityIds = array_values(array_unique($demandEntityIds));
    $inDemands = implode(',', $demandEntityIds);

    // TTN NP via ttn_novaposhta.demand_id (numeric FK)
    $rTtns = \Database::fetchAll('Papir',
        "SELECT id, int_doc_number, state_name, state_define, deletion_mark, demand_id, created_at
         FROM ttn_novaposhta
         WHERE demand_id IN ({$inDemands}) AND (deletion_mark IS NULL OR deletion_mark=0)
         ORDER BY id ASC");
    if ($rTtns['ok']) {
        $ttnByDemand = array();
        foreach ($rTtns['rows'] as $t) { $ttnByDemand[(int)$t['demand_id']][] = $t; }
        foreach ($ttnByDemand as $demId => $ttnRows) {
            $limited2  = array_slice($ttnRows, 0, $CAP);
            $overflow2 = count($ttnRows) - count($limited2);
            foreach ($limited2 as $t) {
                $nid = 'ttn_np_' . $t['id'];
                $alreadyIn = false;
                foreach ($nodes as $nn) { if ($nn['id'] === $nid) { $alreadyIn = true; break; } }
                if (!$alreadyIn) {
                    $nodes[] = array(
                        'id'        => $nid,
                        'type'      => 'ttn_np',
                        'label'     => $t['int_doc_number'] ?: ('#'.$t['id']),
                        'number'    => $t['int_doc_number'] ?: ('#'.$t['id']),
                        'sublabel'  => mb_substr($t['state_name'] ?: '—', 0, 30, 'UTF-8'),
                        'status'    => ld_ttnNpStatus($t),
                        'moment'    => $t['created_at'],
                        'entity_id' => (int)$t['id'],
                        'url'       => '/novaposhta/ttns',
                        'col'       => 3,
                    );
                }
                $edges[] = array('from'=>$nid,'to'=>'demand_'.$demId,'type'=>'ttn');
            }
            if ($overflow2 > 0) {
                $nid2 = 'ovf_ttn_np_dem_' . $demId;
                $nodes[] = array('id'=>$nid2,'type'=>'overflow','label'=>'+' . $overflow2 . ' ТТН НП','col'=>3,'url'=>'/novaposhta/ttns');
                $edges[] = array('from'=>$nid2,'to'=>'demand_'.$demId,'type'=>'ttn');
            }
        }
    }

    // Salesreturn via salesreturn.demand_id
    $rRets = \Database::fetchAll('Papir',
        "SELECT id, number, moment, sum_total, demand_id FROM salesreturn WHERE demand_id IN ({$inDemands})");
    if ($rRets['ok']) {
        foreach ($rRets['rows'] as $s) {
            $nid = 'salesreturn_' . $s['id'];
            $alreadyIn = false;
            foreach ($nodes as $nn) { if ($nn['id'] === $nid) { $alreadyIn = true; break; } }
            if (!$alreadyIn) {
                $nodes[] = array(
                    'id'        => $nid,
                    'type'      => 'salesreturn',
                    'label'     => $s['number'] ?: ('#'.$s['id']),
                    'number'    => $s['number'] ?: ('#'.$s['id']),
                    'sublabel'  => ld_amt($s['sum_total']),
                    'status'    => 'posted',
                    'amount'    => ld_amt($s['sum_total']),
                    'moment'    => $s['moment'],
                    'entity_id' => (int)$s['id'],
                    'url'       => null,
                    'col'       => 4,
                );
            }
            $edges[] = array('from'=>$nid,'to'=>'demand_'.(int)$s['demand_id'],'type'=>'return');
        }
    }

    // Payments linked to demands (via document_link)
    $rDLinks = \Database::fetchAll('Papir',
        "SELECT from_type, from_id, from_ms_id, to_id AS demand_id, linked_sum
         FROM document_link
         WHERE to_type='demand' AND to_id IN ({$inDemands}) AND from_type IN ('paymentin','cashin')
         ORDER BY from_type, id ASC");
    if ($rDLinks['ok']) {
        $payByType2 = array();
        foreach ($rDLinks['rows'] as $pl) { $payByType2[$pl['from_type']][] = $pl; }
        foreach ($payByType2 as $pt => $pRows) {
            $limited3 = array_slice($pRows, 0, $CAP);
            $where3 = ld_whereIds($limited3, 'id_ms');
            if (!$where3) continue;
            $tbl3 = ($pt === 'cashin') ? 'finance_cash' : 'finance_bank';
            $rPay = \Database::fetchAll('Papir',
                "SELECT id, id_ms, doc_number, moment, sum FROM {$tbl3} WHERE direction='in' AND {$where3}");
            if (!$rPay['ok']) continue;
            $lsMap3 = array();
            foreach ($limited3 as $pr) {
                $key = (int)$pr['from_id'] > 0 ? 'id:'.(int)$pr['from_id'] : 'ms:'.$pr['from_ms_id'];
                $lsMap3[$key] = array('ls' => $pr['linked_sum'], 'dem' => (int)$pr['demand_id']);
            }
            foreach ($rPay['rows'] as $c) {
                $k1 = 'id:'.(int)$c['id']; $k2 = 'ms:'.$c['id_ms'];
                $meta = isset($lsMap3[$k1]) ? $lsMap3[$k1] : (isset($lsMap3[$k2]) ? $lsMap3[$k2] : array('ls'=>null,'dem'=>0));
                $amt  = ($meta['ls'] !== null) ? (float)$meta['ls'] : (float)$c['sum'];
                $nid  = ($pt === 'cashin' ? 'cashin_' : 'paymentin_') . $c['id'];
                $alreadyIn = false;
                foreach ($nodes as $nn) { if ($nn['id'] === $nid) { $alreadyIn = true; break; } }
                if (!$alreadyIn) {
                    $docNum = $c['doc_number'] ?: ('#'.$c['id']);
                    $nodes[] = array(
                        'id'        => $nid,
                        'type'      => $pt,
                        'label'     => $docNum,
                        'number'    => $docNum,
                        'sublabel'  => ld_amt($amt),
                        'status'    => 'posted',
                        'amount'    => ld_amt($amt),
                        'moment'    => $c['moment'],
                        'entity_id' => (int)$c['id'],
                        'url'       => ($pt === 'cashin')
                            ? '/finance/cash?search=' . (int)$c['id'] . '&direction=in'
                            : '/finance/bank?search=' . (int)$c['id'] . '&direction=in',
                        'col'       => 0,
                    );
                }
                if ($meta['dem'] > 0) {
                    $edges[] = array('from'=>$nid,'to'=>'demand_'.$meta['dem'],'type'=>'payment');
                }
            }
        }
    }
}

echo json_encode(array('ok' => true, 'nodes' => $nodes, 'edges' => $edges));