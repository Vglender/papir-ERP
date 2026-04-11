<?php
/**
 * GET /ukrposhta/api/get_postoffices?city_id={N}&refresh=0|1
 *
 * Returns: { ok, rows: [{id, name, long_name, type, postindex, street, lat, lon, is_automatic}] }
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../ukrposhta_bootstrap.php';
require_once __DIR__ . '/../../auth/AuthService.php';

if (!\Papir\Crm\AuthService::getCurrentUser()) {
    echo json_encode(array('ok' => false, 'error' => 'auth'));
    exit;
}

$cityId  = isset($_GET['city_id']) ? (int)$_GET['city_id'] : 0;
$refresh = !empty($_GET['refresh']);

if (!$cityId) {
    echo json_encode(array('ok' => false, 'error' => 'city_id required'));
    exit;
}

$rows = \Papir\Crm\ClassifierService::getPostoffices($cityId, $refresh);

$out = array();
foreach ($rows as $r) {
    $out[] = array(
        'id'           => (int)$r['postoffice_id'],
        'name'         => $r['name'],
        'long_name'    => $r['long_name'],
        'type'         => $r['type_long'],
        'postindex'    => $r['postindex'],
        'street'       => $r['street_vpz'],
        'lat'          => $r['latitude']  !== null ? (float)$r['latitude']  : null,
        'lon'          => $r['longitude'] !== null ? (float)$r['longitude'] : null,
        'is_automatic' => (int)$r['is_automatic'],
    );
}

echo json_encode(array('ok' => true, 'rows' => $out), JSON_UNESCAPED_UNICODE);