<?php
// /var/www/papir/modules/demand/edit.php

require_once __DIR__ . '/demand_bootstrap.php';
require_once __DIR__ . '/../shared/StatusColors.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$repo = new DemandRepository();

$demand  = array();
$items   = array();
$history = array();

if ($id) {
    $r = $repo->getById($id);
    if (!$r['ok'] || empty($r['row'])) {
        header('Location: /demand');
        exit;
    }
    $demand = $r['row'];
    $ri     = $repo->getItems($id);
    $items  = ($ri['ok'] && !empty($ri['rows'])) ? $ri['rows'] : array();

    // Document transitions (Створити ▾ dropdown)
    $rTrans = Database::fetchAll('Papir',
        "SELECT dtt.to_type, dtt.link_type, dtt.description, dt.name_uk
         FROM document_type_transition dtt
         JOIN document_type dt ON dt.code = dtt.to_type
         WHERE dtt.from_type = 'demand'
         ORDER BY dt.sort_order");
    $docTransitions = ($rTrans['ok'] && !empty($rTrans['rows'])) ? $rTrans['rows'] : array();

    // History from document_history
    $rHist = Database::fetchAll('Papir',
        "SELECT dh.created_at, dh.event_type, dh.event_type AS event_label,
                COALESCE(e.full_name, dh.actor_name, 'Система') AS employee_name,
                dh.comment
         FROM document_history dh
         LEFT JOIN employee e ON e.id = dh.actor_id
         WHERE dh.document_type = 'demand' AND dh.document_id = {$id}
         ORDER BY dh.created_at DESC
         LIMIT 50");
    $history = ($rHist['ok'] && !empty($rHist['rows'])) ? $rHist['rows'] : array();
    // Fallback: basic history from timestamps
    if (empty($history)) {
        if (!empty($demand['created_at'])) {
            $history[] = array(
                'created_at'    => $demand['created_at'],
                'event_type'    => 'created',
                'event_label'   => 'Створено',
                'employee_name' => 'МойСклад',
                'comment'       => 'Документ синхронізовано з МойСклад',
            );
        }
        if (!empty($demand['updated_at']) && $demand['updated_at'] !== $demand['created_at']) {
            $history[] = array(
                'created_at'    => $demand['updated_at'],
                'event_type'    => 'updated',
                'event_label'   => 'Оновлено',
                'employee_name' => 'МойСклад',
                'comment'       => 'sync_state: ' . ($demand['sync_state'] ?: 'synced'),
            );
        }
        $history = array_reverse($history);
    }
}

// ── Справочники ──────────────────────────────────────────────────────
$organizations = Database::fetchAll('Papir', "SELECT id, name FROM organization WHERE status = 1 ORDER BY name ASC");
$organizations = $organizations['ok'] ? $organizations['rows'] : array();

$stores = Database::fetchAll('Papir', "SELECT id, name FROM store WHERE status = 1 ORDER BY name ASC");
$stores = $stores['ok'] ? $stores['rows'] : array();

$employees = Database::fetchAll('Papir', "SELECT id, full_name FROM employee WHERE status = 1 ORDER BY full_name ASC");
$employees = $employees['ok'] ? $employees['rows'] : array();

$rDMs = Database::fetchAll('Papir',
    "SELECT id, code, name_uk, has_ttn FROM delivery_method WHERE status=1 ORDER BY sort_order");
$deliveryMethods = $rDMs['ok'] ? $rDMs['rows'] : array();

// ── Контрагент ───────────────────────────────────────────────────────
$counterpartyName = '';
if (!empty($demand['counterparty_id'])) {
    $rCp = Database::fetchRow('Papir',
        "SELECT name FROM counterparty WHERE id = " . (int)$demand['counterparty_id'] . " LIMIT 1");
    if ($rCp['ok'] && !empty($rCp['row'])) {
        $counterpartyName = $rCp['row']['name'];
    }
}

// ── Менеджер ─────────────────────────────────────────────────────────
$managerName = '';
if (!empty($demand['manager_employee_id'])) {
    $rMgr = Database::fetchRow('Papir',
        "SELECT full_name FROM employee WHERE id = " . (int)$demand['manager_employee_id'] . " LIMIT 1");
    if ($rMgr['ok'] && !empty($rMgr['row'])) {
        $managerName = $rMgr['row']['full_name'];
    }
} elseif (!empty($demand['customerorder_id'])) {
    // Fallback: from linked customerorder
    $rMgr = Database::fetchRow('Papir',
        "SELECT e.full_name
         FROM customerorder co
         LEFT JOIN employee e ON e.id = co.manager_employee_id
         WHERE co.id = " . (int)$demand['customerorder_id'] . " LIMIT 1");
    if ($rMgr['ok'] && !empty($rMgr['row']['full_name'])) {
        $managerName = $rMgr['row']['full_name'];
    }
}

// ── Пов'язані документи (кількість для бейджа) ──────────────────────
$relatedDocsCount = 0;
if ($id > 0) {
    $rCnt = Database::fetchRow('Papir',
        "SELECT COUNT(*) AS cnt FROM document_link
         WHERE (from_type='demand' AND from_id={$id}) OR (to_type='demand' AND to_id={$id})");
    if ($rCnt['ok']) $relatedDocsCount = (int)$rCnt['row']['cnt'];
}

// ── Реальна маржа ────────────────────────────────────────────────────
$marginData = null;
if ($id > 0 && !empty($items)) {
    $rMargin = Database::fetchRow('Papir',
        "SELECT SUM(di.sum_row) AS sum_total,
                SUM(di.quantity * COALESCE(pp.price_purchase, 0)) AS cost_total
         FROM demand_item di
         LEFT JOIN product_papir pp ON pp.product_id = di.product_id
         WHERE di.demand_id = {$id}");
    if ($rMargin['ok'] && !empty($rMargin['row']) && (float)$rMargin['row']['sum_total'] > 0) {
        $sumTotal  = (float)$rMargin['row']['sum_total'];
        $costTotal = (float)$rMargin['row']['cost_total'];
        $overheadCosts = isset($demand['overhead_costs']) ? (float)$demand['overhead_costs'] : 0;

        // Доставка за наш рахунок — з привʼязаних ТТН де payer_type = 'Sender'
        $deliveryCostDeduct = 0;
        $rTtn = Database::fetchRow('Papir',
            "SELECT SUM(COALESCE(cost_on_site, 0)) AS ttn_cost
             FROM ttn_novaposhta
             WHERE demand_id = {$id}
               AND payer_type = 'Sender'
               AND deletion_mark = 0
               AND state_id NOT IN (2)");
        if ($rTtn['ok'] && !empty($rTtn['row'])) {
            $deliveryCostDeduct = (float)$rTtn['row']['ttn_cost'];
        }

        $margin    = $sumTotal - $costTotal - $overheadCosts - $deliveryCostDeduct;
        $marginPct = $sumTotal > 0 ? round($margin / $sumTotal * 100, 1) : 0;
        $marginData = array(
            'sum_total'            => $sumTotal,
            'cost_total'           => $costTotal,
            'overhead_costs'       => $overheadCosts,
            'delivery_cost_deduct' => $deliveryCostDeduct,
            'margin'               => $margin,
            'margin_pct'           => $marginPct,
        );
    }
}

// Linked customerorder info
$linkedOrder = null;
if (!empty($demand['customerorder_id'])) {
    $rOrd = Database::fetchRow('Papir',
        "SELECT id, number, moment FROM customerorder WHERE id = " . (int)$demand['customerorder_id'] . " LIMIT 1");
    if ($rOrd['ok'] && !empty($rOrd['row'])) {
        $linkedOrder = $rOrd['row'];
    }
}

// Подключаем шаблон
require __DIR__ . '/views/edit.php';