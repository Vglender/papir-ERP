<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../print_bootstrap.php';

$orgId = isset($_GET['org_id']) ? (int)$_GET['org_id'] : 0;

$where = '1=1';
if ($orgId > 0) {
    $where = "(org_id = {$orgId} OR org_id IS NULL)";
}

$r = Database::fetchAll('Papir',
    "SELECT * FROM print_pack_profiles WHERE {$where} ORDER BY is_default DESC, id ASC");

$profiles = ($r['ok'] && !empty($r['rows'])) ? $r['rows'] : array();

foreach ($profiles as &$p) {
    $p['items'] = json_decode($p['items_json'], true);
    unset($p['items_json']);
}

echo json_encode(array('ok' => true, 'profiles' => $profiles), JSON_UNESCAPED_UNICODE);
