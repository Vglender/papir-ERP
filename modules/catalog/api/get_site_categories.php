<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../catalog_bootstrap.php';

$siteId = isset($_GET['site_id']) ? (int)$_GET['site_id'] : 0;
if ($siteId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'site_id required'));
    exit;
}

$siteRes = Database::fetchRow('Papir', "SELECT * FROM sites WHERE site_id={$siteId} AND status=1");
if (!$siteRes['ok'] || empty($siteRes['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Site not found'));
    exit;
}
$site    = $siteRes['row'];
$dbAlias = $site['db_alias'];
$langId  = (int)$site['lang_id'];
$catsRes = Database::fetchAll($dbAlias,
    "SELECT oc.category_id, ocd.name, oc.parent_id, oc.status, oc.sort_order
     FROM oc_category oc
     LEFT JOIN oc_category_description ocd
           ON ocd.category_id = oc.category_id AND ocd.language_id = {$langId}
     WHERE oc.status = 1
     ORDER BY oc.parent_id, oc.sort_order, oc.category_id"
);

if (!$catsRes['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'DB error'));
    exit;
}

echo json_encode(array('ok' => true, 'categories' => $catsRes['rows']));
