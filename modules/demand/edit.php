<?php
require_once __DIR__ . '/demand_bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$activeNav = 'sales';
$subNav    = 'demands';
$title     = 'Відвантаження';
$extraCss  = '<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">';

$repo = new DemandRepository();

$demand       = array();
$items        = array();
$managerName  = '';
$updatedAt    = '';
$history      = array();

if ($id) {
    $r = $repo->getById($id);
    if (!$r['ok'] || empty($r['row'])) {
        header('Location: /demand');
        exit;
    }
    $demand = $r['row'];
    $ri     = $repo->getItems($id);
    $items  = ($ri['ok'] && !empty($ri['rows'])) ? $ri['rows'] : array();
    $title  = 'Відвантаження ' . (!empty($demand['number']) ? $demand['number'] : '#' . $id);

    // Manager from linked customerorder
    if (!empty($demand['customerorder_id'])) {
        $rMgr = Database::fetchRow('Papir',
            "SELECT e.full_name
             FROM customerorder co
             LEFT JOIN employee e ON e.id = co.manager_employee_id
             WHERE co.id = " . (int)$demand['customerorder_id'] . " LIMIT 1");
        if ($rMgr['ok'] && !empty($rMgr['row']['full_name'])) {
            $managerName = $rMgr['row']['full_name'];
        }
    }

    $updatedAt = !empty($demand['updated_at']) ? $demand['updated_at'] : '';

    // Document transitions (Створити ▾ dropdown)
    $rTrans = Database::fetchAll('Papir',
        "SELECT dtt.to_type, dtt.link_type, dtt.description, dt.name_uk
         FROM document_type_transition dtt
         JOIN document_type dt ON dt.code = dtt.to_type
         WHERE dtt.from_type = 'demand'
         ORDER BY dt.sort_order");
    $docTransitions = ($rTrans['ok'] && !empty($rTrans['rows'])) ? $rTrans['rows'] : array();

    // Simple history from demand data
    $history = array();
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

require_once __DIR__ . '/../shared/layout.php';
require_once __DIR__ . '/views/edit.php';
require_once __DIR__ . '/../shared/print-modal.php';
require_once __DIR__ . '/../shared/layout_end.php';