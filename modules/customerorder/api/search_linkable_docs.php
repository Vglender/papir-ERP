

<?php
/**
 * GET /customerorder/api/search_linkable_docs
 * Returns existing documents of a given type that can be linked to an order.
 * Excludes documents already linked to this order.
 *
 * Params (GET):
 *   order_id   — customerorder.id
 *   doc_type   — demand | paymentin | cashin | salesreturn | invoiceout | ttn_np
 *   date_from  — Y-m-d (optional)
 *   date_to    — Y-m-d (optional)
 *   cp_q       — counterparty name search (optional)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../customerorder_bootstrap.php';

$orderId  = isset($_GET['order_id'])  ? (int)$_GET['order_id']            : 0;
$docType  = isset($_GET['doc_type'])  ? trim($_GET['doc_type'])            : '';
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from'])           : '';
$dateTo   = isset($_GET['date_to'])   ? trim($_GET['date_to'])             : '';
$cpQ      = isset($_GET['cp_q'])      ? trim($_GET['cp_q'])                : '';

if ($orderId <= 0 || $docType === '') {
    echo json_encode(array('ok' => false, 'error' => 'order_id and doc_type required'));
    exit;
}

// Validate allowed types
$allowedTypes = array('demand', 'paymentin', 'cashin', 'salesreturn', 'ttn_np');
if (!in_array($docType, $allowedTypes)) {
    echo json_encode(array('ok' => false, 'error' => 'Unsupported doc_type'));
    exit;
}

// Collect already-linked doc IDs of this type for this order
$rLinked = Database::fetchAll('Papir',
    "SELECT from_id FROM document_link
     WHERE to_type='customerorder' AND to_id={$orderId} AND from_type='" . Database::escape('Papir', $docType) . "'");
$linkedIds = array();
if ($rLinked['ok']) {
    foreach ($rLinked['rows'] as $r) {
        if ((int)$r['from_id'] > 0) $linkedIds[] = (int)$r['from_id'];
    }
}
// Build exclude clause with qualified alias to avoid ambiguous column errors
function ld_exclude($alias, $linkedIds) {
    return !empty($linkedIds) ? ' AND ' . $alias . '.id NOT IN (' . implode(',', $linkedIds) . ')' : '';
}

$rows = array();

// ── demand ────────────────────────────────────────────────────────────────────
if ($docType === 'demand') {
    $where = "d.deleted_at IS NULL" . ld_exclude('d', $linkedIds);
    if ($dateFrom !== '') {
        $df = Database::escape('Papir', $dateFrom);
        $where .= " AND d.moment >= '{$df} 00:00:00'";
    }
    if ($dateTo !== '') {
        $dt = Database::escape('Papir', $dateTo);
        $where .= " AND d.moment <= '{$dt} 23:59:59'";
    }
    if ($cpQ !== '') {
        $q = Database::escape('Papir', mb_strtolower($cpQ, 'UTF-8'));
        $where .= " AND LOWER(COALESCE(cp.name,'')) LIKE '%{$q}%'";
    }
    $rD = Database::fetchAll('Papir',
        "SELECT d.id, d.number, d.moment, d.status, d.sum_total,
                cp.name AS cp_name
         FROM demand d
         LEFT JOIN counterparty cp ON cp.id = d.counterparty_id
         WHERE {$where}
         ORDER BY d.moment DESC LIMIT 100");
    if ($rD['ok']) {
        foreach ($rD['rows'] as $r) {
            $rows[] = array(
                'id'         => (int)$r['id'],
                'type'       => 'demand',
                'type_name'  => 'Відвантаження',
                'number'     => $r['number'] ?: ('#' . $r['id']),
                'moment'     => $r['moment'],
                'counterparty' => $r['cp_name'],
                'amount'     => $r['sum_total'] ? number_format((float)$r['sum_total'], 2, '.', ' ') . ' ₴' : '',
            );
        }
    }
}

// ── paymentin (finance_bank direction=in) ─────────────────────────────────────
if ($docType === 'paymentin') {
    $where = "fb.direction='in'" . ld_exclude('fb', $linkedIds);
    if ($dateFrom !== '') {
        $df = Database::escape('Papir', $dateFrom);
        $where .= " AND fb.moment >= '{$df} 00:00:00'";
    }
    if ($dateTo !== '') {
        $dt = Database::escape('Papir', $dateTo);
        $where .= " AND fb.moment <= '{$dt} 23:59:59'";
    }
    if ($cpQ !== '') {
        $q = Database::escape('Papir', mb_strtolower($cpQ, 'UTF-8'));
        $where .= " AND LOWER(COALESCE(cp_d.name, cp_m.name,'')) LIKE '%{$q}%'";
    }
    $rB = Database::fetchAll('Papir',
        "SELECT fb.id, fb.doc_number, fb.moment, fb.sum,
                COALESCE(cp_d.name, cp_m.name) AS cp_name
         FROM finance_bank fb
         LEFT JOIN counterparty cp_d ON cp_d.id = fb.cp_id
         LEFT JOIN counterparty cp_m ON cp_m.id_ms = fb.agent_ms AND fb.cp_id IS NULL
         WHERE {$where}
         ORDER BY fb.moment DESC LIMIT 100");
    if ($rB['ok']) {
        foreach ($rB['rows'] as $r) {
            $rows[] = array(
                'id'         => (int)$r['id'],
                'type'       => 'paymentin',
                'type_name'  => 'Вхідний платіж',
                'number'     => $r['doc_number'] ?: ('#' . $r['id']),
                'moment'     => $r['moment'],
                'counterparty' => $r['cp_name'],
                'amount'     => $r['sum'] ? number_format((float)$r['sum'], 2, '.', ' ') . ' ₴' : '',
            );
        }
    }
}

// ── cashin (finance_cash direction=in) ────────────────────────────────────────
if ($docType === 'cashin') {
    $where = "fc.direction='in'" . ld_exclude('fc', $linkedIds);
    if ($dateFrom !== '') {
        $df = Database::escape('Papir', $dateFrom);
        $where .= " AND fc.moment >= '{$df} 00:00:00'";
    }
    if ($dateTo !== '') {
        $dt = Database::escape('Papir', $dateTo);
        $where .= " AND fc.moment <= '{$dt} 23:59:59'";
    }
    if ($cpQ !== '') {
        $q = Database::escape('Papir', mb_strtolower($cpQ, 'UTF-8'));
        $where .= " AND LOWER(COALESCE(cp.name,'')) LIKE '%{$q}%'";
    }
    $rC = Database::fetchAll('Papir',
        "SELECT fc.id, fc.doc_number, fc.moment, fc.sum,
                cp.name AS cp_name
         FROM finance_cash fc
         LEFT JOIN counterparty cp ON cp.id_ms = fc.agent_ms
         WHERE {$where}
         ORDER BY fc.moment DESC LIMIT 100");
    if ($rC['ok']) {
        foreach ($rC['rows'] as $r) {
            $rows[] = array(
                'id'         => (int)$r['id'],
                'type'       => 'cashin',
                'type_name'  => 'Прибутковий касовий ордер',
                'number'     => $r['doc_number'] ?: ('#' . $r['id']),
                'moment'     => $r['moment'],
                'counterparty' => $r['cp_name'],
                'amount'     => $r['sum'] ? number_format((float)$r['sum'], 2, '.', ' ') . ' ₴' : '',
            );
        }
    }
}

// ── salesreturn ───────────────────────────────────────────────────────────────
if ($docType === 'salesreturn') {
    $rChk = Database::query('Papir', "SELECT 1 FROM salesreturn LIMIT 1");
    if ($rChk['ok']) {
        $where = "sr.deleted_at IS NULL" . ld_exclude('sr', $linkedIds);
        if ($dateFrom !== '') {
            $df = Database::escape('Papir', $dateFrom);
            $where .= " AND sr.moment >= '{$df} 00:00:00'";
        }
        if ($dateTo !== '') {
            $dt = Database::escape('Papir', $dateTo);
            $where .= " AND sr.moment <= '{$dt} 23:59:59'";
        }
        if ($cpQ !== '') {
            $q = Database::escape('Papir', mb_strtolower($cpQ, 'UTF-8'));
            $where .= " AND LOWER(COALESCE(cp.name,'')) LIKE '%{$q}%'";
        }
        $rS = Database::fetchAll('Papir',
            "SELECT sr.id, sr.number, sr.moment, sr.sum_total,
                    cp.name AS cp_name
             FROM salesreturn sr
             LEFT JOIN counterparty cp ON cp.id = sr.counterparty_id
             WHERE {$where}
             ORDER BY sr.moment DESC LIMIT 100");
        if ($rS['ok']) {
            foreach ($rS['rows'] as $r) {
                $rows[] = array(
                    'id'         => (int)$r['id'],
                    'type'       => 'salesreturn',
                    'type_name'  => 'Повернення покупця',
                    'number'     => $r['number'] ?: ('#' . $r['id']),
                    'moment'     => $r['moment'],
                    'counterparty' => $r['cp_name'],
                    'amount'     => $r['sum_total'] ? number_format((float)$r['sum_total'], 2, '.', ' ') . ' ₴' : '',
                );
            }
        }
    }
}

// ── ttn_np ────────────────────────────────────────────────────────────────────
if ($docType === 'ttn_np') {
    $where = "(deletion_mark IS NULL OR deletion_mark=0)" . ld_exclude('ttn_novaposhta', $linkedIds);
    if ($dateFrom !== '') {
        $df = Database::escape('Papir', $dateFrom);
        $where .= " AND created_at >= '{$df} 00:00:00'";
    }
    if ($dateTo !== '') {
        $dt = Database::escape('Papir', $dateTo);
        $where .= " AND created_at <= '{$dt} 23:59:59'";
    }
    if ($cpQ !== '') {
        $q = Database::escape('Papir', mb_strtolower($cpQ, 'UTF-8'));
        $where .= " AND LOWER(COALESCE(recipient,'')) LIKE '%{$q}%'";
    }
    $rT = Database::fetchAll('Papir',
        "SELECT id, int_doc_number, created_at, recipient
         FROM ttn_novaposhta
         WHERE {$where}
         ORDER BY created_at DESC LIMIT 100");
    if ($rT['ok']) {
        foreach ($rT['rows'] as $r) {
            $rows[] = array(
                'id'         => (int)$r['id'],
                'type'       => 'ttn_np',
                'type_name'  => 'ТТН Нова Пошта',
                'number'     => $r['int_doc_number'] ?: ('#' . $r['id']),
                'moment'     => $r['created_at'],
                'counterparty' => $r['recipient'],
                'amount'     => '',
            );
        }
    }
}

echo json_encode(array('ok' => true, 'rows' => $rows));