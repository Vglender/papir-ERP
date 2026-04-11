<?php
/**
 * GET /ukrposhta/api/search_street?city_id={N}&q={query}&limit={N}
 *
 * Returns: { ok, rows: [{id, name, type}] }
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../ukrposhta_bootstrap.php';
require_once __DIR__ . '/../../auth/AuthService.php';

if (!\Papir\Crm\AuthService::getCurrentUser()) {
    echo json_encode(array('ok' => false, 'error' => 'auth'));
    exit;
}

$cityId = isset($_GET['city_id']) ? (int)$_GET['city_id'] : 0;
$q      = isset($_GET['q'])       ? trim($_GET['q'])      : '';
$limit  = isset($_GET['limit'])   ? (int)$_GET['limit']   : 20;

if (!$cityId || mb_strlen($q, 'UTF-8') < 2) {
    echo json_encode(array('ok' => true, 'rows' => array()));
    exit;
}

$rows = \Papir\Crm\ClassifierService::searchStreet($cityId, $q, $limit);

$out = array();
foreach ($rows as $r) {
    $out[] = array(
        'id'   => (int)$r['street_id'],
        'name' => $r['street_name'],
        'type' => $r['street_type'],
    );
}

echo json_encode(array('ok' => true, 'rows' => $out), JSON_UNESCAPED_UNICODE);