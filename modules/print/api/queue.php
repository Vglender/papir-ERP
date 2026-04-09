<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../print_bootstrap.php';

$r = Database::fetchAll('Papir',
    "SELECT pj.id, pj.demand_id, pj.profile_id, pj.items_json, pj.created_at,
            pp.name AS profile_name,
            d.number AS demand_number, d.moment AS demand_moment, d.status AS demand_status,
            c.name AS counterparty_name
     FROM print_pack_jobs pj
     LEFT JOIN print_pack_profiles pp ON pp.id = pj.profile_id
     LEFT JOIN demand d ON d.id = pj.demand_id
     LEFT JOIN counterparty c ON c.id = d.counterparty_id
     WHERE pj.queued = 1 AND pj.status = 'ready'
     ORDER BY pj.created_at DESC");

$rows = ($r['ok'] && !empty($r['rows'])) ? $r['rows'] : array();

foreach ($rows as &$row) {
    $row['items'] = json_decode($row['items_json'], true);
    unset($row['items_json']);
}

echo json_encode(array('ok' => true, 'queue' => $rows), JSON_UNESCAPED_UNICODE);
