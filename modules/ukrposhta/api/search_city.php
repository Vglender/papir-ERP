<?php
/**
 * GET /ukrposhta/api/search_city?q={query}&limit={N}
 *
 * Returns: { ok, rows: [{id, name, type, region, district, postcode}] }
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../ukrposhta_bootstrap.php';
require_once __DIR__ . '/../../auth/AuthService.php';

if (!\Papir\Crm\AuthService::getCurrentUser()) {
    echo json_encode(array('ok' => false, 'error' => 'auth'));
    exit;
}

$q     = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if (mb_strlen($q, 'UTF-8') < 2) {
    echo json_encode(array('ok' => true, 'rows' => array()));
    exit;
}

$rows = \Papir\Crm\ClassifierService::searchCity($q, $limit);

$out = array();
foreach ($rows as $r) {
    $out[] = array(
        'id'       => (int)$r['city_id'],
        'name'     => $r['city_name'],
        'type'     => $r['city_type_ua'],
        'region'   => $r['region_name'],
        'district' => $r['district_name'],
        'postcode' => $r['postcode'],
    );
}

echo json_encode(array('ok' => true, 'rows' => $out), JSON_UNESCAPED_UNICODE);
